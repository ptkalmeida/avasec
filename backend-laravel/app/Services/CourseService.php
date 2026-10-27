<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Curso;
use App\Models\Aula;
use App\Models\DocumentoAula;
use App\Models\SessaoAoVivo;
use App\Models\ProgressoAluno;
use App\Models\Usuario;
use App\Support\BusinessRules;
use App\Support\CourseAccess;
use App\Support\CursoSlug;
use App\Support\DataTexto;
use App\Support\Payload;
use App\Support\SafeUrl;
use App\Support\VideoSource;
use App\Support\Visibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regras de negócio de cursos — espelha src/server/services/courseService.ts.
 * CRUD com sincronização aninhada de aulas/documentos/sessões ao vivo em transação
 * (nunca deixa o curso em estado parcial), e ownership FK-first com fallback por nome.
 */
final class CourseService
{
    /**
     * Catálogo com o material protegido. O catálogo é PÚBLICO por escopo declarado
     * (01-visao-geral.md), mas devolvia o curso inteiro — texto de estudo de cada
     * aula, url_video, documentos e o link do Meet — para qualquer visitante sem
     * login. O que era indevido não é a rota ser aberta: é o tamanho do payload.
     *
     * Vitrine (todos): título, descrição, categoria, capa, carga horária, instrutor,
     * e a lista de aulas apenas com título/duração/ordem — o programa do curso é
     * argumento de matrícula, não material.
     * Completo: admin; instrutor nos cursos que leciona; aluno nos cursos a que
     * pertence (matriculado, concluído ou admissão aprovada) — via CourseAccess,
     * a mesma fonte usada pelo chat e pelos certificados.
     *
     * @param  array{sub:string,nome:string,papel:string}|null  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listCoursesFor(?array $requester): array
    {
        $irrestrito = $requester !== null && $requester['papel'] === 'admin';
        $liberados = ($requester === null || $irrestrito)
            ? []
            : CourseAccess::accessibleCourseIds($requester);

        /*
         * Escada de audiência da ADR 12: `status` 1 publicado (aluno vê),
         * 2 restrito (gestor e admin), 3 rascunho (só admin). Sem este filtro a
         * coluna seria decoração — foi o que aconteceu com `status_curso`, que
         * existe com 'Ativo'/NULL e nenhuma consulta lê.
         *
         * Curso INATIVADO não precisa de filtro aqui: o SoftDeletes o exclui de
         * toda consulta automaticamente, e é essa a razão de tê-lo escolhido.
         */
        $consulta = Curso::query()->with($this->courseInclude());
        Visibilidade::aplicar($consulta, $requester['papel'] ?? null);

        $catalogo = [];
        foreach ($consulta->get() as $course) {
            $curso = $course->toArray();
            $catalogo[] = ($irrestrito || in_array($course->id, $liberados, true))
                ? $curso
                : self::semMaterial($curso);
        }

        return $catalogo;
    }

    /**
     * Remove do curso o que só quem tem acesso deve receber. Os campos são zerados
     * em vez de removidos para o contrato não mudar de forma: o cliente continua
     * lendo `lessons[].content`, só que vazio, e não precisa de dois caminhos.
     *
     * @param  array<string, mixed>  $curso
     * @return array<string, mixed>
     */
    private static function semMaterial(array $curso): array
    {
        if (is_array($curso['aulas'] ?? null)) {
            $curso['aulas'] = array_map(static function (mixed $aula): mixed {
                if (! is_array($aula)) {
                    return $aula;
                }
                $aula['conteudo'] = '';
                $aula['url_video'] = null;
                $aula['documentos'] = [];

                return $aula;
            }, $curso['aulas']);
        }

        if (is_array($curso['sessoes_ao_vivo'] ?? null)) {
            $curso['sessoes_ao_vivo'] = array_map(static function (mixed $sessao): mixed {
                if (! is_array($sessao)) {
                    return $sessao;
                }
                // Link do Meet é a chave da sala: com ele, qualquer um entra na aula.
                $sessao['link_reuniao'] = '';

                return $sessao;
            }, $curso['sessoes_ao_vivo']);
        }

        return $curso;
    }

    /** @return array<string, mixed> */
    public function getCourseById(string $id): array
    {
        $course = Curso::query()->with($this->courseInclude())->find($id);
        if ($course === null) {
            throw ApiException::notFound('Curso não encontrado.');
        }

        return $course->toArray();
    }

    /**
     * Curso a partir de um slug, de um slug aposentado ou de um id.
     *
     * A ordem é slug atual -> slug aposentado -> id, e as três formas resolvem
     * porque as três circulam em link salvo: o slug é o endereço de hoje, o
     * aposentado é o endereço de antes de alguém renomear o curso, e o id é o
     * endereço de antes desta mudança existir (ADR 13). Um link que já funcionou
     * não pode passar a dar 404 por causa de uma melhoria de URL.
     *
     * `canonico` diz se o valor recebido É o endereço de hoje. Quando é false, o
     * cliente troca a URL da barra pela canônica — senão o endereço antigo se
     * propaga para sempre, copiado de tela em tela.
     *
     * A escada de visibilidade vale AQUI TAMBÉM (`Visibilidade::aplicar`). Sem
     * ela a rota responderia "existe, e o id é este" para o slug de um curso em
     * rascunho, e passaria a ser o único jeito de um visitante confirmar que um
     * curso não publicado existe — o catálogo já filtra por papel. Resolver id é
     * pouca informação, mas é informação.
     *
     * @return array{id:string, slug:string, canonico:bool}
     */
    public function resolverCurso(string $valor, ?string $role = null): array
    {
        if ($valor === '') {
            throw ApiException::notFound('Curso não encontrado.');
        }

        $visivel = function (string $coluna, string $busca) use ($role): ?Curso {
            $q = Curso::query()->where($coluna, $busca);
            Visibilidade::aplicar($q, $role);

            return $q->first(['id', 'slug']);
        };

        $porSlug = $visivel('slug', $valor);
        if ($porSlug !== null) {
            return ['id' => (string) $porSlug->id, 'slug' => (string) $porSlug->slug, 'canonico' => true];
        }

        $aposentado = DB::table('historico_slug_curso')->where('slug', $valor)->value('curso_id');
        if (is_string($aposentado)) {
            $atual = $visivel('id', $aposentado);
            if ($atual !== null) {
                return ['id' => (string) $atual->id, 'slug' => (string) $atual->slug, 'canonico' => false];
            }
        }

        $porId = $visivel('id', $valor);
        if ($porId !== null) {
            return ['id' => (string) $porId->id, 'slug' => (string) $porId->slug, 'canonico' => false];
        }

        throw ApiException::notFound('Curso não encontrado.');
    }

    /**
     * Todo slug que não está livre: os em uso e os aposentados.
     *
     * Os aposentados entram porque continuam resolvendo para o curso antigo
     * (`resolverCurso`). Reaproveitar um faria o link salvo de um curso abrir
     * outro — o pior defeito possível numa mudança feita para melhorar endereços.
     *
     * Lê `curso` com SQL cru para incluir curso INATIVADO: o SoftDeletes o
     * esconderia, e é a mesma armadilha de upsert registrada na ADR 12 — o id
     * (aqui, o slug) continua ocupado no banco mesmo invisível na consulta.
     *
     * @return array<int, string>
     */
    private function slugsTomados(): array
    {
        /** @var array<int, string> $emUso */
        $emUso = DB::table('curso')->whereNotNull('slug')->pluck('slug')->all();
        /** @var array<int, string> $aposentados */
        $aposentados = DB::table('historico_slug_curso')->pluck('slug')->all();

        return array_merge($emUso, $aposentados);
    }

    /**
     * Aposenta o slug atual do curso, se o título mudou de verdade.
     *
     * Devolve o slug novo, ou null quando não há nada a trocar. Título igual não
     * gera slug novo — senão salvar o formulário sem tocar no título aposentaria
     * o endereço e encheria o histórico de linhas iguais.
     */
    private function trocarSlugPorTitulo(string $curso_id, string $tituloNovo): ?string
    {
        $atual = DB::table('curso')->where('id', $curso_id)->first(['titulo', 'slug']);
        if ($atual === null) {
            return null;
        }

        $slugAtual = is_string($atual->slug) ? $atual->slug : '';
        if (is_string($atual->titulo) && $atual->titulo === $tituloNovo && $slugAtual !== '') {
            return null;
        }

        $novo = CursoSlug::unico($tituloNovo, $this->slugsTomados());
        if ($novo === $slugAtual) {
            return null;
        }

        if ($slugAtual !== '') {
            // insertOrIgnore: o mesmo slug pode voltar a ser aposentado depois de
            // um rename de ida e volta, e a segunda vez não pode estourar a PK.
            DB::table('historico_slug_curso')->insertOrIgnore([
                'slug' => $slugAtual,
                'curso_id' => $curso_id,
                'aposentado_em' => CarbonImmutable::now()->toDateTimeString(),
            ]);
        }

        return $novo;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function createCourse(array $input, array $requester): array
    {
        $lessons = Payload::assocList($input['aulas'] ?? []);
        $liveSessions = Payload::assocList($input['sessoes_ao_vivo'] ?? []);
        unset($input['aulas'], $input['sessoes_ao_vivo']);

        // Instrutor só cria curso em seu próprio nome; admin pode atribuir a outro
        // instrutor por usuario_id (ADR 10) — nome_instrutor é sempre derivado (display).
        if (($requester['papel'] ?? null) === 'admin'
            && is_string($input['instrutor_id'] ?? null)
            && $input['instrutor_id'] !== ''
            && $input['instrutor_id'] !== $requester['sub']) {
            $instrutor_id = $input['instrutor_id'];
            $nome_instrutor = Usuario::query()
                ->where('id', $instrutor_id)
                ->whereIn('papel', ['instructor', 'admin'])
                ->value('nome');
            if (! is_string($nome_instrutor)) {
                throw ApiException::notFound('Instrutor não encontrado.');
            }
        } else {
            $nome_instrutor = $requester['nome'];
            $instrutor_id = $requester['sub'];
        }

        $id = is_string($input['id'] ?? null) ? $input['id'] : ('course-'.$this->nowMs());

        DB::transaction(function () use ($input, $id, $nome_instrutor, $instrutor_id, $lessons, $liveSessions): void {
            $courseData = $this->scalarCourseData($input);
            $courseData['id'] = $id;
            // Slug é DERIVADO do título, nunca aceito do cliente — mesma regra de
            // nome_instrutor. Slug escolhido pelo requisitante seria um campo de
            // identidade editável por quem manda o JSON.
            $courseData['slug'] = CursoSlug::unico(
                is_string($input['titulo'] ?? null) ? $input['titulo'] : '',
                $this->slugsTomados(),
            );
            $courseData['nome_instrutor'] = $nome_instrutor;
            $courseData['instrutor_id'] = $instrutor_id;
            Curso::query()->create($courseData);

            foreach ($lessons as $lesson) {
                $this->createLessonWithDocuments($id, $lesson);
            }
            foreach ($liveSessions as $session) {
                SessaoAoVivo::query()->create($this->liveSessionData($session, $id));
            }
        });

        return $this->getCourseById($id);
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function updateCourse(string $curso_id, array $updates, array $requester): array
    {
        $this->assertCourseOwnership($curso_id, $requester);

        $lessons = array_key_exists('aulas', $updates) ? Payload::assocList($updates['aulas']) : null;
        $liveSessions = array_key_exists('sessoes_ao_vivo', $updates) ? Payload::assocList($updates['sessoes_ao_vivo']) : null;
        unset($updates['aulas'], $updates['sessoes_ao_vivo']);

        // Reatribuição de autoria: só admin, endereçada por usuario_id; o nome de
        // exibição é derivado do Usuario para nunca divergir da FK (ADR 10).
        $instructorIdUpdate = [];
        if (is_string($updates['instrutor_id'] ?? null) && $updates['instrutor_id'] !== '') {
            if (($requester['papel'] ?? null) !== 'admin') {
                throw ApiException::forbidden('Somente administradores podem reatribuir a autoria de um curso.');
            }
            $newName = Usuario::query()
                ->where('id', $updates['instrutor_id'])
                ->whereIn('papel', ['instructor', 'admin'])
                ->value('nome');
            if (! is_string($newName)) {
                throw ApiException::notFound('Instrutor não encontrado.');
            }
            $instructorIdUpdate = ['instrutor_id' => $updates['instrutor_id'], 'nome_instrutor' => $newName];
        }

        DB::transaction(function () use ($curso_id, $updates, $instructorIdUpdate, $lessons, $liveSessions): void {
            $scalar = array_merge($this->scalarCourseData($updates), $instructorIdUpdate);
            if (is_string($updates['titulo'] ?? null) && $updates['titulo'] !== '') {
                $slugNovo = $this->trocarSlugPorTitulo($curso_id, $updates['titulo']);
                if ($slugNovo !== null) {
                    $scalar['slug'] = $slugNovo;
                }
            }
            if (count($scalar) > 0) {
                Curso::query()->where('id', $curso_id)->update($scalar);
            }
            if ($lessons !== null) {
                $this->syncLessons($curso_id, $lessons);
            }
            if ($liveSessions !== null) {
                $this->syncLiveSessions($curso_id, $liveSessions);
            }
            if ($lessons !== null || $liveSessions !== null) {
                $this->limparProgressoOrfao($curso_id);
            }
        });

        return $this->getCourseById($curso_id);
    }

    /** @param array{sub:string,nome:string,papel:string} $requester */
    public function deleteCourse(string $curso_id, array $requester, ?string $motivo = null): void
    {
        $this->assertCourseOwnership($curso_id, $requester);
        /*
         * Inativa a disciplina (ADR 12). Antes era exclusão física, e o
         * ON DELETE CASCADE do banco levava aulas, documentos e encontros junto
         * — junto com o histórico de quem cursou.
         *
         * Aulas e encontros continuam ativos por baixo, de propósito: ficam
         * inalcançáveis porque a disciplina está fora do ar, e reativar a
         * disciplina devolve o conteúdo inteiro sem precisar recompor nada.
         */
        Curso::query()->find($curso_id)?->inativar($requester['sub'] ?? null, $motivo);
    }

    /**
     * Devolve ao ar uma disciplina retirada (ADR 12).
     *
     * A retirada só inativa a linha do curso — aulas, encontros, avaliações e
     * matrículas continuam ativos por baixo (ver `deleteCourse`) —, então reativar é
     * `restore()` e nada mais: o conteúdo inteiro volta junto, para todos os gestores
     * do curso e para os alunos, sem recompor nada.
     *
     * O curso volta **como estava**: o mesmo status de visibilidade e a mesma vigência.
     * Não se publica nem se estende vigência por baixo dos panos — quem chama é que
     * avisa a pessoa quando algo disso a impediria de ver o curso aberto a matrículas.
     *
     * Reativar não pode colidir com um curso novo: o `slug` é único em todas as linhas,
     * inclusive as retiradas (`course_slug_unique`).
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    public function reactivateCourse(string $curso_id, array $requester): Curso
    {
        // Só o administrador devolve um curso ao ar; gestor não vê nem os retirados.
        if (($requester['papel'] ?? null) !== 'admin') {
            throw ApiException::forbidden('Somente o administrador reativa uma disciplina retirada.');
        }

        $curso = Curso::onlyTrashed()->find($curso_id);
        if ($curso === null) {
            // Já estava no ar (duplo clique) ou nunca existiu: o resultado pedido ou existe, ou não há o que reativar.
            throw ApiException::notFound('Disciplina retirada não encontrada.');
        }

        $curso->reativar();

        return $curso->refresh();
    }

    /**
     * Troca a aula de lugar com a vizinha, e renumera a grade de 1 a N.
     *
     * Existe porque a tela de grade em Blade não pode reenviar o curso inteiro
     * para mudar uma posição. O React fazia isso (`PUT /api/cursos/{id}` com o
     * array `lessons` completo), e dois instrutores — ou duas abas — editando o
     * mesmo curso se sobrescreviam sem aviso: quem salvasse por último levava o
     * trabalho do outro. Aqui só a ordem muda, e só o que mudou é gravado.
     *
     * A ordem de partida é a mesma que a tela mostra (`ordem`, depois
     * `id`), então "subir" move a aula que a pessoa está VENDO, mesmo quando o
     * banco tem posições repetidas ou com buraco.
     *
     * @param  'up'|'down'  $direcao
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    public function moverAula(string $curso_id, string $aula_id, string $direcao, array $requester): void
    {
        $this->assertCourseOwnership($curso_id, $requester);

        DB::transaction(function () use ($curso_id, $aula_id, $direcao): void {
            /** @var list<string> $ids */
            $ids = $this->somenteStrings(
                Aula::query()->where('curso_id', $curso_id)
                    ->orderBy('ordem')->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id')->all()
            );

            $posicao = array_search($aula_id, $ids, true);
            if ($posicao === false) {
                throw ApiException::notFound('Aula não encontrada neste curso.');
            }

            $destino = $direcao === 'up' ? $posicao - 1 : $posicao + 1;
            // Primeira não sobe e última não desce: nada a fazer, e não é erro.
            if ($destino >= 0 && $destino < count($ids)) {
                [$ids[$posicao], $ids[$destino]] = [$ids[$destino], $ids[$posicao]];
            }

            foreach ($ids as $i => $id) {
                Aula::query()->whereKey($id)->update(['ordem' => $i + 1]);
            }
        });
    }

    /**
     * Tira a aula do ar. Não apaga (ADR 12).
     *
     * O progresso dos alunos que apontava para ela é limpo, porque id órfão em
     * `aulas_concluidas` contava como aula concluída: um curso com 1 aula e 2
     * ids órfãos dava 200% — e a frequência inflada é o gatilho da emissão
     * automática de certificado (ver `limparProgressoOrfao`).
     *
     * Existe também porque `updateCourse` NÃO consegue tirar a ÚLTIMA aula de
     * um curso: o `syncLessons` só inativa as que "saíram" quando a lista que
     * sobrou não está vazia (`when(count($keptIds) > 0, ...)`), então mandar o
     * curso sem aula nenhuma não inativa nenhuma.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    public function inativarAula(string $curso_id, string $aula_id, array $requester, ?string $motivo = null): void
    {
        $this->assertCourseOwnership($curso_id, $requester);

        $aula = Aula::query()->where('curso_id', $curso_id)->whereKey($aula_id)->first();
        if ($aula === null) {
            throw ApiException::notFound('Aula não encontrada neste curso.');
        }

        DB::transaction(function () use ($aula, $requester, $motivo, $curso_id): void {
            $aula->inativar($requester['sub'], $motivo);
            $this->limparProgressoOrfao($curso_id);
        });
    }

    /**
     * Cria UMA aula no fim da grade.
     *
     * O React gravava o curso inteiro (`PUT /api/cursos/{id}` com todas as
     * aulas) para acrescentar uma. Aqui só a aula nova é escrita, e a posição é
     * calculada no servidor, dentro da transação: a lista que a tela tinha pode
     * estar velha, e dois instrutores criando ao mesmo tempo não disputam o mesmo
     * número.
     *
     * @param  array{titulo:string,duracao:string,url_video?:?string,conteudo?:?string}  $dados
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return string o id da aula criada
     */
    public function criarAula(string $curso_id, array $dados, array $requester): string
    {
        $this->assertCourseOwnership($curso_id, $requester);

        $campos = $this->camposDaAula($dados, exigeTitulo: true);
        if (($campos['conteudo'] ?? null) === null) {
            throw ApiException::validation('Informe o texto de estudo da aula.');
        }

        return DB::transaction(function () use ($curso_id, $campos): string {
            // Trava o curso para serializar as criações: sem isso, duas aulas
            // nascidas juntas leem o mesmo "maior número" e empatam na grade.
            Curso::query()->whereKey($curso_id)->lockForUpdate()->first();

            $maior = Aula::query()->where('curso_id', $curso_id)->max('ordem');
            $id = 'lesson-'.$curso_id.'-'.$this->nowMs().'-'.Str::lower(Str::random(4));

            Aula::query()->create([
                'id' => $id,
                'curso_id' => $curso_id,
                'ordem' => (is_numeric($maior) ? (int) $maior : 0) + 1,
            ] + $campos);

            return $id;
        });
    }

    /**
     * Atualiza título, carga, vídeo e/ou texto de UMA aula. Só as chaves enviadas
     * mudam; as outras ficam como estão.
     *
     * Existe no lugar do `PUT /api/cursos/{id}` que reenviava todas as aulas do
     * curso: com ele, quem salvava a identificação de uma aula também regravava o
     * texto de todas as outras com o que a tela dela guardava, e uma aba velha
     * desfazia em silêncio o trabalho de outra pessoa.
     *
     * @param  array{titulo?:string,duracao?:string,url_video?:?string,conteudo?:?string}  $campos
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    public function atualizarAula(string $curso_id, string $aula_id, array $campos, array $requester): void
    {
        $this->assertCourseOwnership($curso_id, $requester);

        $novos = $this->camposDaAula($campos, exigeTitulo: false);

        DB::transaction(function () use ($curso_id, $aula_id, $novos): void {
            $this->aulaTravada($curso_id, $aula_id)->fill($novos)->save();
        });
    }

    /**
     * Edita o TEXTO da aula com trava otimista.
     *
     * A edição de um bloco parte de uma posição lida numa tela que pode estar
     * velha; se o texto mudou desde então, aplicar a edição na mesma posição
     * trocaria o trecho errado. `$versao` é o hash do texto que a pessoa viu
     * (`versaoDoConteudo`): se não bate com o que está gravado, nada é escrito e
     * ela recebe o aviso para recarregar.
     *
     * @param  callable(string): string  $edicao  recebe o texto ATUAL e devolve o novo
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return string o texto novo
     */
    public function editarConteudoDaAula(string $curso_id, string $aula_id, string $versao, callable $edicao, array $requester): string
    {
        $this->assertCourseOwnership($curso_id, $requester);

        return DB::transaction(function () use ($curso_id, $aula_id, $versao, $edicao): string {
            $aula = $this->aulaTravada($curso_id, $aula_id);
            $atual = is_string($aula->conteudo) ? $aula->conteudo : '';

            if (! hash_equals(self::versaoDoConteudo($atual), $versao)) {
                throw ApiException::validation('O texto desta aula mudou desde que você abriu a página. Recarregue para ver a versão atual e refaça a alteração.');
            }

            $novo = $edicao($atual);
            $campos = $this->camposDaAula(['conteudo' => $novo], exigeTitulo: false);
            if (($campos['conteudo'] ?? null) === null) {
                throw ApiException::validation('O material didático não pode ficar vazio.');
            }
            $aula->fill($campos)->save();

            return is_string($campos['conteudo'] ?? null) ? $campos['conteudo'] : '';
        });
    }

    /**
     * Anexa UM documento (arquivo já enviado ou link) à aula.
     *
     * Só o anexo novo é escrito. O React regravava a lista inteira de anexos da
     * aula — e, pelo `PUT` do curso, a de todas as aulas — a cada vínculo.
     *
     * @param  array{titulo:string,tipo:string,url:string,tamanho?:?string}  $dados
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return string o id do documento criado
     */
    public function anexarDocumento(string $curso_id, string $aula_id, array $dados, array $requester): string
    {
        $this->assertCourseOwnership($curso_id, $requester);

        $titulo = trim($dados['titulo']);
        if ($titulo === '') {
            throw ApiException::validation('Dê um título ao recurso.');
        }
        if (mb_strlen($titulo) > 191) {
            throw ApiException::validation('O título do recurso pode ter no máximo 191 caracteres.');
        }

        if (! in_array($dados['tipo'], ['pdf', 'doc', 'url', 'drive', 'outro'], true)) {
            throw ApiException::validation('Escolha o tipo do recurso.');
        }

        $url = trim($dados['url']);
        if ($url === '') {
            throw ApiException::validation('Informe o endereço do conteúdo ou selecione um arquivo.');
        }
        // A mesma regra de href da API: só http(s) ou caminho interno. Um anexo
        // com `javascript:` viraria um link que roda código no navegador do aluno.
        if (mb_strlen($url) > 2000 || ! SafeUrl::isValid($url)) {
            throw ApiException::validation('O endereço deve ser uma URL http(s) válida ou um caminho interno começando com "/".');
        }

        $tamanho = isset($dados['tamanho']) && is_string($dados['tamanho']) ? trim($dados['tamanho']) : '';
        if (mb_strlen($tamanho) > 30) {
            throw ApiException::validation('O tamanho pode ter no máximo 30 caracteres.');
        }

        return DB::transaction(function () use ($curso_id, $aula_id, $titulo, $dados, $url, $tamanho): string {
            $this->aulaTravada($curso_id, $aula_id);

            $id = 'doc-'.$aula_id.'-'.$this->nowMs().'-'.Str::lower(Str::random(4));
            DocumentoAula::query()->create([
                'id' => $id,
                'aula_id' => $aula_id,
                'titulo' => $titulo,
                'tipo' => $dados['tipo'],
                'url' => $url,
                'tamanho' => $tamanho === '' ? null : $tamanho,
            ]);

            return $id;
        });
    }

    /**
     * Desvincula um documento da aula. Não apaga (ADR 12): o registro fica, com
     * quem desvinculou e quando.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    public function desvincularDocumento(string $curso_id, string $aula_id, string $documentId, array $requester, ?string $motivo = null): void
    {
        $this->assertCourseOwnership($curso_id, $requester);

        DB::transaction(function () use ($curso_id, $aula_id, $documentId, $requester, $motivo): void {
            $this->aulaTravada($curso_id, $aula_id);

            $doc = DocumentoAula::query()->where('aula_id', $aula_id)->whereKey($documentId)->first();
            if ($doc === null) {
                throw ApiException::notFound('Documento não encontrado nesta aula.');
            }

            $doc->inativar($requester['sub'], $motivo);
        });
    }

    /** Hash do texto da aula, para a trava otimista de `editarConteudoDaAula`. */
    public static function versaoDoConteudo(?string $conteudo): string
    {
        return hash('sha256', $conteudo ?? '');
    }

    /**
     * A aula, com a linha travada para a transação. 404 quando não é deste curso
     * ou já foi retirada: o id de outro curso nunca alcança a aula alheia.
     */
    private function aulaTravada(string $curso_id, string $aula_id): Aula
    {
        $aula = Aula::query()->where('curso_id', $curso_id)->whereKey($aula_id)->lockForUpdate()->first();
        if ($aula === null) {
            throw ApiException::notFound('Aula não encontrada neste curso.');
        }

        return $aula;
    }

    /**
     * Valida e normaliza os campos de uma aula. Os limites são os da coluna, não
     * os do contrato da API: `title` é varchar(191), e aceitar 200 caracteres
     * viraria um erro de banco em vez de uma mensagem.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function camposDaAula(array $dados, bool $exigeTitulo): array
    {
        $campos = [];

        if (array_key_exists('titulo', $dados) || $exigeTitulo) {
            $titulo = is_string($dados['titulo'] ?? null) ? trim($dados['titulo']) : '';
            if ($titulo === '') {
                throw ApiException::validation('O título da aula não pode ficar vazio.');
            }
            if (mb_strlen($titulo) > 191) {
                throw ApiException::validation('O título da aula pode ter no máximo 191 caracteres.');
            }
            $campos['titulo'] = $titulo;
        }

        if (array_key_exists('duracao', $dados) || $exigeTitulo) {
            $carga = is_string($dados['duracao'] ?? null) ? trim($dados['duracao']) : '';
            if ($carga === '') {
                throw ApiException::validation('Informe a carga da aula, por exemplo "20 min".');
            }
            if (mb_strlen($carga) > 30) {
                throw ApiException::validation('A carga da aula pode ter no máximo 30 caracteres.');
            }
            $campos['duracao'] = $carga;
        }

        if (array_key_exists('url_video', $dados)) {
            $video = is_string($dados['url_video']) ? trim($dados['url_video']) : '';
            if ($video === '') {
                $campos['url_video'] = null;
            } else {
                // A forma canônica (ADR 08): youtu.be, embed e shorts viram watch?v=.
                $fonte = VideoSource::tryParse($video);
                if ($fonte === null) {
                    throw ApiException::validation('Informe um link de vídeo do YouTube ou um arquivo de vídeo (mp4, webm, ogg, m4v, mov).');
                }
                $campos['url_video'] = $fonte->canonicalUrl;
            }
        }

        if (array_key_exists('conteudo', $dados)) {
            $texto = is_string($dados['conteudo']) ? trim($dados['conteudo']) : '';
            if (mb_strlen($texto) > 20000) {
                throw ApiException::validation('O material didático pode ter no máximo 20.000 caracteres.');
            }
            $campos['conteudo'] = $texto === '' ? null : $texto;
        }

        return $campos;
    }

    /** @param array{sub:string,nome:string,papel:string} $requester */
    private function assertCourseOwnership(string $curso_id, array $requester): void
    {
        $course = Curso::query()->find($curso_id, ['nome_instrutor', 'instrutor_id']);
        if ($course === null) {
            throw ApiException::notFound('Curso não encontrado.');
        }
        if (($requester['papel'] ?? null) === 'admin') {
            return;
        }
        // Posse por FK apenas (ADR 10) — nome nunca é identidade.
        $owns = $course->instrutor_id !== null && $course->instrutor_id === $requester['sub'];
        if (($requester['papel'] ?? null) === 'instructor' && $owns) {
            return;
        }
        throw ApiException::forbidden('Você só pode gerenciar cursos vinculados ao seu próprio perfil de instrutor.');
    }

    /**
     * Remove do progresso dos alunos os ids de aula/encontro que acabaram de
     * deixar de existir.
     *
     * A escrita de progresso já filtrava ids invalidos (EnrollmentService::
     * sanitizeProgressIds), mas nada limpava o que ja estava gravado quando uma
     * aula era APAGADA depois. O residuo era contado como aula concluida: um
     * curso com 1 aula e 2 ids orfaos dava 200% de progresso, e o Perfil chegou
     * a exibir "113% de progresso medio". Pior, a frequencia inflada e o gatilho
     * da emissao automatica de certificado.
     */
    private function limparProgressoOrfao(string $curso_id): void
    {
        $aulasValidas = $this->somenteStrings(Aula::query()->where('curso_id', $curso_id)->pluck('id')->all());
        $encontrosValidos = $this->somenteStrings(SessaoAoVivo::query()->where('curso_id', $curso_id)->pluck('id')->all());

        foreach (ProgressoAluno::query()->where('curso_id', $curso_id)->get() as $registro) {
            $aulas = $this->somenteStrings(is_array($registro->aulas_concluidas) ? $registro->aulas_concluidas : []);
            $encontros = $this->somenteStrings(is_array($registro->sessoes_ao_vivo_assistidas) ? $registro->sessoes_ao_vivo_assistidas : []);

            $aulasLimpas = array_values(array_intersect($aulas, $aulasValidas));
            $encontrosLimpos = array_values(array_intersect($encontros, $encontrosValidos));

            if ($aulasLimpas === $aulas && $encontrosLimpos === $encontros) {
                continue;
            }

            $registro->aulas_concluidas = $aulasLimpas;
            $registro->sessoes_ao_vivo_assistidas = $encontrosLimpos;
            $registro->save();
        }
    }

    /**
     * Descarta o que não é string. Id é sempre string aqui; a checagem existe
     * porque o valor vem de coluna JSON, onde qualquer coisa pode ter sido
     * gravada por uma versão antiga.
     *
     * @param  array<mixed>  $valores
     * @return list<string>
     */
    private function somenteStrings(array $valores): array
    {
        return array_values(array_filter($valores, static fn ($v): bool => is_string($v)));
    }

    /** @param list<array<string, mixed>> $lessons */
    private function syncLessons(string $curso_id, array $lessons): void
    {
        $keptIds = array_values(array_filter(array_map(fn ($l) => $l['id'] ?? null, $lessons)));
        // Aula que saiu da edição é INATIVADA, não apagada (ADR 12): com o trait
        // Inativavel no model, este ->delete() do builder grava `inativado_em`.
        // Readicionar a mesma aula depois a reativa (ver withTrashed abaixo).
        Aula::query()->where('curso_id', $curso_id)
            ->when(count($keptIds) > 0, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();

        foreach ($lessons as $lesson) {
            $aula_id = is_string($lesson['id'] ?? null)
                ? $lesson['id']
                : ('lesson-'.$curso_id.'-'.$this->nowMs().'-'.Str::lower(Str::random(4)));
            $data = $this->lessonScalar($lesson, $curso_id);
            // withTrashed é OBRIGATÓRIO aqui: com a inativação da ADR 12, uma
            // aula retirada e depois readicionada com o mesmo id não seria
            // encontrada por find(), e o create() estouraria a chave primária.
            $existing = Aula::query()->withTrashed()->find($aula_id);
            /*
             * Um id que já pertence a OUTRO curso não é desta edição. Os ids saem no catálogo
             * público: sem esta guarda, o instrutor de um curso mandava o id de uma aula
             * alheia, e ela era movida para o curso dele com o texto sobrescrito (achado da
             * varredura de segurança de 23/10/2026). Vira aula nova, com id novo.
             */
            if ($existing !== null && $existing->curso_id !== $curso_id) {
                $aula_id = 'lesson-'.$curso_id.'-'.$this->nowMs().'-'.Str::lower(Str::random(4));
                $existing = null;
            }
            if ($existing !== null) {
                if ($existing->estaInativo()) {
                    $existing->reativar();
                }
                $existing->fill($data)->save();
            } else {
                $data['id'] = $aula_id;
                Aula::query()->create($data);
            }

            $documents = Payload::assocList($lesson['documentos'] ?? []);
            $keptDocIds = array_values(array_filter(array_map(fn ($d) => $d['id'] ?? null, $documents)));
            DocumentoAula::query()->where('aula_id', $aula_id)
                ->when(count($keptDocIds) > 0, fn ($q) => $q->whereNotIn('id', $keptDocIds))
                ->delete();
            foreach ($documents as $doc) {
                $docId = is_string($doc['id'] ?? null)
                    ? $doc['id']
                    : ('doc-'.$aula_id.'-'.$this->nowMs().'-'.Str::lower(Str::random(4)));
                $docData = $this->documentScalar($doc, $aula_id);
                $existingDoc = DocumentoAula::query()->withTrashed()->find($docId);
                // Mesma guarda das aulas: documento de outra aula não é movido para esta.
                if ($existingDoc !== null && $existingDoc->aula_id !== $aula_id) {
                    $docId = 'doc-'.$aula_id.'-'.$this->nowMs().'-'.Str::lower(Str::random(4));
                    $existingDoc = null;
                }
                if ($existingDoc !== null) {
                    if ($existingDoc->estaInativo()) {
                        $existingDoc->reativar();
                    }
                    $existingDoc->fill($docData)->save();
                } else {
                    $docData['id'] = $docId;
                    DocumentoAula::query()->create($docData);
                }
            }
        }
    }

    /** @param list<array<string, mixed>> $liveSessions */
    private function syncLiveSessions(string $curso_id, array $liveSessions): void
    {
        $keptIds = array_values(array_filter(array_map(fn ($s) => $s['id'] ?? null, $liveSessions)));
        SessaoAoVivo::query()->where('curso_id', $curso_id)
            ->when(count($keptIds) > 0, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();

        foreach ($liveSessions as $session) {
            $sessao_id = is_string($session['id'] ?? null)
                ? $session['id']
                : ('live-'.$curso_id.'-'.$this->nowMs().'-'.Str::lower(Str::random(4)));
            $data = $this->liveSessionData($session, $curso_id);
            $existing = SessaoAoVivo::query()->withTrashed()->find($sessao_id);
            // Mesma guarda das aulas: encontro de outro curso não é sobrescrito nem movido.
            if ($existing !== null && $existing->curso_id !== $curso_id) {
                $sessao_id = 'live-'.$curso_id.'-'.$this->nowMs().'-'.Str::lower(Str::random(4));
                $existing = null;
            }
            $data['id'] = $sessao_id;
            if ($existing !== null) {
                unset($data['id']);
                if ($existing->estaInativo()) {
                    $existing->reativar();
                }
                $existing->fill($data)->save();
            } else {
                SessaoAoVivo::query()->create($data);
            }
        }
    }

    /** @param array<string, mixed> $lesson */
    private function createLessonWithDocuments(string $curso_id, array $lesson): void
    {
        $aula_id = is_string($lesson['id'] ?? null)
            ? $lesson['id']
            : ('lesson-'.$curso_id.'-'.$this->nowMs().'-'.Str::lower(Str::random(4)));
        $data = $this->lessonScalar($lesson, $curso_id);
        $data['id'] = $aula_id;
        Aula::query()->create($data);
        foreach (Payload::assocList($lesson['documentos'] ?? []) as $doc) {
            $docData = $this->documentScalar($doc, $aula_id);
            $docData['id'] = is_string($doc['id'] ?? null)
                ? $doc['id']
                : ('doc-'.$aula_id.'-'.$this->nowMs().'-'.Str::lower(Str::random(4)));
            DocumentoAula::query()->create($docData);
        }
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @return array<string, mixed>
     */
    private function lessonScalar(array $lesson, string $curso_id): array
    {
        return [
            'curso_id' => $curso_id,
            'titulo' => $lesson['titulo'],
            'duracao' => $lesson['duracao'],
            // Persistimos a forma canônica (ADR 08) — youtu.be/embed/shorts viram watch?v=.
            'url_video' => is_string($lesson['url_video'] ?? null)
                ? VideoSource::tryParse($lesson['url_video'])?->canonicalUrl
                : null,
            'conteudo' => $lesson['conteudo'] ?? null,
            'ordem' => is_numeric($lesson['ordem'] ?? null) ? (int) $lesson['ordem'] : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    private function documentScalar(array $doc, string $aula_id): array
    {
        return [
            'aula_id' => $aula_id,
            'titulo' => $doc['titulo'],
            'tipo' => $doc['tipo'],
            'url' => $doc['url'],
            'tamanho' => $doc['tamanho'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function liveSessionData(array $session, string $curso_id): array
    {
        return [
            'id' => $session['id'] ?? ('live-'.$curso_id.'-'.$this->nowMs().'-'.Str::lower(Str::random(4))),
            'curso_id' => $curso_id,
            'titulo' => $session['titulo'],
            'agendado_para_texto' => $session['agendado_para_texto'],
            'duracao_minutos' => is_numeric($session['duracao_minutos'] ?? null) ? (int) $session['duracao_minutos'] : 0,
            'link_reuniao' => $session['link_reuniao'],
            // Encontro que a regra das 24h já encerrou não pode voltar a ser
            // marcado como ao vivo: o painel não oferece mais o botão, mas o
            // servidor é quem tem de garantir — um PUT direto reabriria a sala.
            'ao_vivo' => (bool) ($session['ao_vivo'] ?? false)
                && ! BusinessRules::liveSessionExpired(
                    is_string($session['agendado_para_texto'] ?? null) ? $session['agendado_para_texto'] : null
                ),
        ];
    }

    /**
     * Só os campos escalares do curso (sem lessons/liveSessions/id/instructor*).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function scalarCourseData(array $input): array
    {
        // nome_instrutor/instrutor_id ficam FORA da lista: a autoria só muda pelo
        // fluxo de reatribuição por usuario_id (ADR 10), nunca por campo solto.
        $allowed = [
            'titulo', 'descricao', 'categoria', 'miniatura', 'imagem_capa',
            'tipo_curso', 'tem_chat', 'frequencia_minima', 'vencimento_contrato_texto', 'area_tematica',
            'carga_horaria', 'modalidade', 'nivel', 'emite_certificado', 'status_curso',
        ];

        $campos = array_intersect_key($input, array_flip($allowed));

        /*
         * `vigencia_ate` acompanha `vencimento_contrato_texto` (Norma C.2.4). O texto
         * continua sendo o campo do contrato de API; a coluna DATE ao lado é a data
         * comparável. Só é tocada quando o texto vem no pedido, para que uma edição
         * parcial do curso não apague uma vigência que ninguém mandou mudar.
         *
         * A validação da rota aceita qualquer string de até 60 caracteres aqui, então
         * o valor realmente pode não ser data: nesse caso a coluna fica NULL, sem
         * inventar vencimento para um contrato.
         */
        if (array_key_exists('vencimento_contrato_texto', $campos)) {
            $texto = $campos['vencimento_contrato_texto'];
            $campos['vigencia_ate'] = DataTexto::dia(is_string($texto) ? $texto : null);
        }

        return $campos;
    }

    /** @return array<int|string, string|(\Closure(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)> */
    private function courseInclude(): array
    {
        return [
            'aulas' => fn ($q) => $q->orderBy('ordem'),
            'aulas.documentos',
            'sessoesAoVivo',
        ];
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
