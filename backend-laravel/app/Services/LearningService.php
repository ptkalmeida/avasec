<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Avaliacao;
use App\Models\EntregaExercicio;
use App\Models\ExercicioPratico;
use App\Models\MensagemForum;
use App\Models\QuestaoAvaliacao;
use App\Models\TentativaAvaliacao;
use App\Support\BusinessRules;
use App\Support\CourseAccess;
use App\Support\DataTexto;
use App\Support\Fuso;
use App\Support\Identity;
use App\Support\InstructorScope;
use App\Support\Payload;
use App\Support\QuestaoDeProva;
use App\Support\Visibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Quizzes, fórum e exercícios práticos — espelha src/server/services/learningService.ts.
 * nome_remetente/nome_aluno sempre vêm da identidade autenticada, nunca do corpo.
 *
 * $requester = ['sub'=>id, 'nome'=>..., 'papel'=>...].
 */
final class LearningService
{
    // ---------- QUIZZES ----------

    /**
     * Quizzes dos cursos a que o requester pertence.
     *
     * O ALUNO recebe só enunciados e alternativas: `indice_opcao_correta`,
     * `explanation` e `mensagem_revisao` ficam no servidor (decisão de 23/10/2026).
     * Antes eles iam junto "para o feedback imediato", mas a tela do aluno é
     * Blade desde a M3 e corrige no servidor (`provaParaResponder` +
     * `submitQuiz`); quem chamasse a API à mão lia o gabarito de toda prova do
     * seu curso e tirava 100%. Aluno também não vê prova em rascunho ou restrita.
     * Instrutor e admin continuam recebendo tudo, porque editam a prova.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listQuizzes(array $requester): array
    {
        $query = Avaliacao::query()->with('questoes');
        if ($requester['papel'] !== 'admin') {
            $query->whereIn('curso_id', CourseAccess::accessibleCourseIds($requester));
        }

        $ehAluno = $requester['papel'] === 'student';
        if ($ehAluno) {
            Visibilidade::aplicar($query, 'student');
        }

        $quizzes = $query->get()->map->toArray()->all();

        if (! $ehAluno) {
            return $quizzes;
        }

        foreach ($quizzes as &$quiz) {
            $questoes = is_array($quiz['questoes'] ?? null) ? $quiz['questoes'] : [];
            $quiz['questoes'] = array_values(array_map(
                static fn (mixed $q): mixed => is_array($q)
                    ? array_diff_key($q, array_flip(['indice_opcao_correta', 'explicacao', 'mensagem_revisao']))
                    : $q,
                $questoes,
            ));
        }
        unset($quiz);

        return $quizzes;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function createQuiz(array $input, array $requester): array
    {
        $curso_id = is_string($input['curso_id'] ?? null) ? $input['curso_id'] : null;
        $this->assertInstructorOwnsCourse($curso_id, $requester);

        /*
         * O gabarito tem de apontar para uma alternativa que EXISTE. Até 21/10
         * esta conferência só morava no navegador (`problemaNaQuestao`), e a API
         * aceitava `indice_opcao_correta` qualquer: duas alternativas com gabarito
         * 9 eram gravadas sem reclamação, e a prova ficava impossível de
         * acertar — bloqueando o certificado da turma inteira, já que a prova é
         * exigida para ele.
         */
        foreach (Payload::assocList($input['questoes'] ?? []) as $i => $questao) {
            $opcoes = is_array($questao['opcoes'] ?? null)
                ? array_values(array_map(static fn (mixed $o): string => is_scalar($o) ? (string) $o : '', $questao['opcoes']))
                : [];
            $problema = QuestaoDeProva::problema(
                is_string($questao['texto_questao'] ?? null) ? $questao['texto_questao'] : '',
                $opcoes,
                is_numeric($questao['indice_opcao_correta'] ?? null) ? (int) $questao['indice_opcao_correta'] : 0,
            );
            if ($problema !== null) {
                throw ApiException::validation('Questão '.($i + 1).': '.$problema);
            }
        }

        $id = is_string($input['id'] ?? null) ? $input['id'] : ('quiz-'.$this->nowMs());
        $quiz = Avaliacao::query()->withTrashed()->find($id);
        // Um quiz existente não pode ser sequestrado para outro curso alheio.
        if ($quiz !== null && is_string($quiz->curso_id)) {
            $this->assertInstructorOwnsCourse($quiz->curso_id, $requester);
        }
        if ($quiz !== null) {
            $quiz->fill(['curso_id' => $input['curso_id'], 'titulo' => $input['titulo']])->save();
        } else {
            Avaliacao::query()->create(['id' => $id, 'curso_id' => $input['curso_id'], 'titulo' => $input['titulo']]);
        }

        $questions = Payload::assocList($input['questoes'] ?? []);

        /*
         * O id da questão é DECIDIDO AQUI, e não aceito do cliente.
         *
         * O editor React mandava `rascunho-N`, um marcador provisório cujo
         * contador recomeça a cada carregamento da página. O servidor tratava
         * qualquer id existente como EDIÇÃO — inclusive o de OUTRA prova. Foi o
         * que aconteceu: ao criar o "Desafio 2", `rascunho-3` já existia no
         * "Desafio 1", e a questão foi MOVIDA para a prova nova com o texto
         * sobrescrito. A original se perdeu.
         *
         * É também um furo de autorização: `assertInstructorOwnsCourse` confere
         * a prova, não a questão, então quem soubesse (ou adivinhasse) o id de
         * uma questão de outro curso a sobrescrevia.
         *
         * Regra: id vindo do cliente só vale se a questão JÁ PERTENCE a esta
         * prova. Qualquer outro caso ganha id novo.
         */
        $idsDasQuestoes = [];
        foreach ($questions as $i => $q) {
            $idsDasQuestoes[$i] = $this->idDaQuestao(is_string($q['id'] ?? null) ? $q['id'] : null, $id, $i);
        }
        $keptIds = array_values($idsDasQuestoes);
        // Questão que saiu da edição é INATIVADA, não apagada (ADR 12): a
        // resposta que o aluno já deu continua apontando para um item existente.
        QuestaoAvaliacao::query()->where('avaliacao_id', $id)
            ->when(count($keptIds) > 0, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();

        foreach ($questions as $i => $q) {
            $qId = $idsDasQuestoes[$i];
            $data = [
                'avaliacao_id' => $id,
                'texto_questao' => $q['texto_questao'],
                'opcoes' => $q['opcoes'],
                'indice_opcao_correta' => is_numeric($q['indice_opcao_correta'] ?? null) ? (int) $q['indice_opcao_correta'] : 0,
                'explicacao' => $q['explicacao'] ?? null,
                'mensagem_revisao' => $q['mensagem_revisao'] ?? null,
                'modulo_recomendado' => $q['modulo_recomendado'] ?? null,
                'permite_nova_tentativa' => $q['permite_nova_tentativa'] ?? null,
            ];
            // withTrashed: questão retirada e depois readicionada com o mesmo id
            // não seria encontrada por find(), e o create() estouraria a chave.
            $existing = QuestaoAvaliacao::query()->withTrashed()->find($qId);
            if ($existing !== null) {
                if ($existing->estaInativo()) {
                    $existing->reativar();
                }
                $existing->fill($data)->save();
            } else {
                $data['id'] = $qId;
                QuestaoAvaliacao::query()->create($data);
            }
        }

        return Avaliacao::query()->with('questoes')->find($id)?->toArray() ?? [];
    }

    /**
     * O id que a questão terá. O pedido do cliente só é aceito quando a questão
     * já pertence a ESTA prova (ver o comentário em `createQuiz`).
     */
    private function idDaQuestao(?string $pedido, string $avaliacao_id, int $posicao): string
    {
        if ($pedido !== null) {
            $existente = QuestaoAvaliacao::query()->withTrashed()->find($pedido, ['id', 'avaliacao_id']);
            if ($existente !== null && $existente->avaliacao_id === $avaliacao_id) {
                return $pedido;
            }
        }

        return $avaliacao_id.'-q-'.$this->nowMs().'-'.$posicao.'-'.Str::lower(Str::random(5));
    }

    /**
     * Inativa a avaliação (ADR 12). As respostas dos alunos NÃO são tocadas.
     *
     * Isto apagava `tentativa_avaliacao` do quiz junto — as notas que os alunos já
     * tinham tirado desapareciam do banco porque quem deu aula excluiu a
     * avaliação. Nota lançada é registro acadêmico e sobrevive à retirada do
     * instrumento que a gerou.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    public function deleteQuiz(string $id, array $requester, ?string $motivo = null): void
    {
        $quiz = Avaliacao::query()->find($id);
        if ($quiz === null) {
            return;
        }
        $this->assertInstructorOwnsCourse(is_string($quiz->curso_id) ? $quiz->curso_id : null, $requester);
        $quiz->inativar($requester['sub'] ?? null, $motivo);
    }

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listQuizSubmissions(array $requester): array
    {
        $q = TentativaAvaliacao::query();
        if ($requester['papel'] === 'student') {
            Identity::applyOwnRows($q, $requester);
        } elseif ($requester['papel'] === 'instructor') {
            $q->whereIn('curso_id', InstructorScope::courseIds($requester));
        }

        /*
         * Mais recente primeiro. Isto é contrato, não conveniência: a lista antes
         * tinha uma linha por aluno+quiz, e há consumidor que ainda lê a primeira
         * e a chama de nota do aluno — inclusive a declaração impressa. Com o
         * histórico no ar, "a primeira" tem de ser a vigente.
         *
         * `id` como desempate porque carrega o timestamp em milissegundos: duas
         * tentativas no mesmo segundo ainda saem na ordem em que aconteceram.
         * Linha sem `enviado_em` (string antiga não reconhecível) cai para o fim,
         * que é onde a mais antiga pertence.
         */
        return $q->orderByDesc('enviado_em')->orderByDesc('id')->get()->map->toArray()->all();
    }

    /**
     * A nota NUNCA vem do cliente: é recalculada no servidor comparando as respostas
     * enviadas ($input['answers']: questionId => índice escolhido) com o
     * indice_opcao_correta de cada QuestaoAvaliacao. O curso_id também é derivado do quiz,
     * não do corpo. Isso impede o aluno de auto-declarar percentual_nota/aprovado.
     *
     * @param  array{avaliacao_id:string,answers:array<string,int>}  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function submitQuiz(array $input, array $requester): array
    {
        if ($requester['papel'] !== 'student') {
            throw ApiException::forbidden('Somente alunos podem responder quizzes.');
        }

        $quiz = Avaliacao::query()->with('questoes')->find($input['avaliacao_id']);
        if ($quiz === null) {
            throw ApiException::notFound('Avaliacao não encontrado.');
        }

        // Prova em rascunho ou restrita não existe para o aluno: sem esta checagem, quem soubesse o id
        // respondia (e recebia a nota de) uma prova que a escola ainda não publicou.
        if (! Visibilidade::papelAlcanca('student', is_numeric($quiz->getAttribute('status')) ? (int) $quiz->getAttribute('status') : Visibilidade::PUBLICADO)) {
            throw ApiException::notFound('Avaliacao não encontrado.');
        }

        // Responder avaliação de curso alheio gerava submissão válida num curso ao qual
        // o aluno não pertence — poluindo o painel do instrutor daquela turma.
        if (! is_string($quiz->curso_id) || ! CourseAccess::canAccess($requester, $quiz->curso_id)) {
            throw ApiException::forbidden('Você não participa deste curso.');
        }

        $acertos = $this->acertosPorQuestao($quiz, $input['answers']);
        $total = count($acertos);
        $correct = count(array_filter($acertos));
        $percentual_nota = $total === 0 ? 0 : (int) round(($correct / $total) * 100);
        $aprovado = $percentual_nota >= BusinessRules::quizPassThreshold();

        /*
         * A tentativa nova é ACRESCENTADA. A anterior fica no ar, visível, como
         * a tentativa que foi.
         *
         * Antes, responder de novo inativava a anterior — o dado ficava no banco
         * (ADR 12) mas saía das listagens, e a tela só sabia dizer "última
         * tentativa". Faltava a coluna `enviado_em`, sem a qual não havia como
         * afirmar QUAL é a vigente: `enviado_em_texto` é texto de exibição e ordena
         * alfabeticamente. Com o eixo de ordenação, o histórico inteiro aparece.
         */
        /*
         * DOIS papéis, de propósito em duas variáveis. `enviado_em` é o instante
         * gravado e fica em UTC, que é o relógio de armazenamento do sistema;
         * `enviado_em_texto` é texto para uma pessoa ler e vai no fuso de exibição.
         * Uma variável só para os dois gravaria BRT na coluna e quebraria o
         * invariante — e o erro não apareceria até alguém comparar duas datas.
         */
        $agora = CarbonImmutable::now();
        $agoraLocal = Fuso::de($agora);

        return TentativaAvaliacao::query()->create([
            'id' => 'sub-'.$this->nowMs().'-'.random_int(0, 999),
            'nome_aluno' => $requester['nome'],
            'usuario_id' => $requester['sub'],
            'curso_id' => $quiz->curso_id,
            'avaliacao_id' => $quiz->id,
            'percentual_nota' => $percentual_nota,
            'aprovado' => $aprovado,
            'enviado_em_texto' => $agoraLocal->format('d/m/Y').' às '.$agoraLocal->format('H:i'),
            'enviado_em' => $agora,
        ])->toArray();
    }

    /**
     * Acerto de cada questão, pela ordem do quiz. É a ÚNICA comparação com o
     * gabarito: a nota de `submitQuiz` e a correção que a tela mostra saem
     * daqui, e por isso não podem discordar.
     *
     * @param  array<array-key, mixed>  $respostas  questionId => índice escolhido
     * @return array<string, bool>
     */
    private function acertosPorQuestao(Avaliacao $quiz, array $respostas): array
    {
        $acertos = [];
        foreach ($quiz->questoes as $question) {
            $dada = $respostas[$question->id] ?? null;
            $acertos[(string) $question->id] = is_int($dada) && $dada === $question->indice_opcao_correta;
        }

        return $acertos;
    }

    /**
     * As avaliações de um curso como o aluno as vê, com a situação de cada uma.
     *
     * Só as publicadas (escada de `Visibilidade`) — a mesma regra que o
     * certificado usa para decidir quais são exigidas.
     *
     * "Aprovada" segue o certificado: basta UMA tentativa aprovada. Refazer
     * depois de aprovado e ir pior não desfaz a aprovação, e a tela não pode
     * dizer outra coisa que o certificado diz. A nota mostrada é a da
     * tentativa mais recente.
     *
     * @param  array{sub:string,nome:string,papel:string}  $aluno
     * @return list<array{id:string,titulo:string,questoes:int,aprovada:bool,tentativas:int,ultima:array{percentual_nota:int,aprovado:bool,enviado_em_texto:string}|null}>
     */
    public function avaliacoesDoCursoParaAluno(string $curso_id, array $aluno): array
    {
        $consulta = Avaliacao::query()->withCount('questoes')->where('curso_id', $curso_id);
        Visibilidade::aplicar($consulta, 'student');
        $quizzes = $consulta->orderBy('id')->get();

        $tentativas = TentativaAvaliacao::query()
            ->where('usuario_id', $aluno['sub'])
            ->where('curso_id', $curso_id)
            ->orderByDesc('enviado_em')->orderByDesc('id')
            ->get()
            ->groupBy('avaliacao_id');

        $lista = [];
        foreach ($quizzes as $quiz) {
            $doQuiz = $tentativas->get($quiz->id) ?? collect();
            $ultima = $doQuiz->first();
            $lista[] = [
                'id' => (string) $quiz->id,
                'titulo' => (string) $quiz->titulo,
                'questoes' => is_numeric($n = $quiz->getAttribute('questoes_count')) ? (int) $n : 0,
                'aprovada' => $doQuiz->contains(static fn (TentativaAvaliacao $t): bool => (bool) $t->aprovado),
                'tentativas' => $doQuiz->count(),
                'ultima' => $ultima === null ? null : [
                    'percentual_nota' => (int) $ultima->percentual_nota,
                    'aprovado' => (bool) $ultima->aprovado,
                    'enviado_em_texto' => (string) $ultima->enviado_em_texto,
                ],
            ];
        }

        return $lista;
    }

    /**
     * As avaliações de um curso como o INSTRUTOR as vê.
     *
     * Conta ALUNOS, e não tentativas. O painel React somava tentativas: com
     * refazer ilimitado, um aluno que tentou três vezes contava três vezes em
     * "respostas" e, se passou nas três, três vezes em "aprovados".
     *
     * "Aprovado" segue o certificado — uma tentativa aprovada basta, mesmo que
     * a última tenha sido pior —, para a tela não dizer outra coisa que a
     * emissão diz.
     *
     * Entram as avaliações que o PAPEL alcança na escada de `Visibilidade`: o
     * instrutor vê as restritas, que é onde ele as prepara, e o admin vê também
     * os rascunhos. O aluno só alcança as publicadas — e só elas contam para o
     * certificado (`avaliacoesNaoAprovadas`).
     *
     * @return list<array{id:string,titulo:string,questoes:int,alunos:int,aprovados:int,tentativas:int}>
     */
    public function avaliacoesDoCurso(string $curso_id, string $papel): array
    {
        $consulta = Avaliacao::query()->withCount('questoes')->where('curso_id', $curso_id);
        Visibilidade::aplicar($consulta, $papel);
        $quizzes = $consulta->orderBy('id')->get();

        $tentativas = TentativaAvaliacao::query()
            ->where('curso_id', $curso_id)
            ->get(['avaliacao_id', 'usuario_id', 'aprovado'])
            ->groupBy('avaliacao_id');

        $lista = [];
        foreach ($quizzes as $quiz) {
            $doQuiz = $tentativas->get($quiz->id) ?? collect();
            $porAluno = $doQuiz->groupBy('usuario_id');

            $n = $quiz->getAttribute('questoes_count');
            $lista[] = [
                'id' => (string) $quiz->id,
                'titulo' => (string) $quiz->titulo,
                'questoes' => is_numeric($n) ? (int) $n : 0,
                'alunos' => $porAluno->count(),
                'aprovados' => $porAluno->filter(
                    static fn ($linhas): bool => $linhas->contains(static fn (TentativaAvaliacao $t): bool => (bool) $t->aprovado),
                )->count(),
                'tentativas' => $doQuiz->count(),
            ];
        }

        return $lista;
    }

    /**
     * Uma avaliação, com o gabarito, para o instrutor editar.
     *
     * Aqui o gabarito VAI — é para quem o escreve. A diferença para
     * `provaParaResponder`, que o esconde do aluno, é de quem pergunta, e é por
     * isso que as duas são funções separadas: uma só, com um parâmetro, seria
     * um `if` entre mostrar e esconder a resposta.
     *
     * Null quando a avaliação não é deste curso.
     *
     * @return array{id:string,titulo:string,questoes:list<array{id:string,texto_questao:string,opcoes:list<string>,indice_opcao_correta:int,explicacao:string|null,mensagem_revisao:string|null,modulo_recomendado:string|null,permite_nova_tentativa:bool}>}|null
     */
    public function avaliacaoParaEditar(string $curso_id, string $avaliacao_id): ?array
    {
        $quiz = Avaliacao::query()->with('questoes')->whereKey($avaliacao_id)->where('curso_id', $curso_id)->first();
        if ($quiz === null) {
            return null;
        }

        $questoes = [];
        foreach ($quiz->questoes as $q) {
            $opcoes = is_array($q->opcoes) ? $q->opcoes : [];
            $questoes[] = [
                'id' => (string) $q->id,
                'texto_questao' => (string) $q->texto_questao,
                'opcoes' => array_values(array_map(static fn (mixed $o): string => is_scalar($o) ? (string) $o : '', $opcoes)),
                'indice_opcao_correta' => (int) $q->indice_opcao_correta,
                'explicacao' => is_string($q->explicacao) ? $q->explicacao : null,
                'mensagem_revisao' => is_string($q->mensagem_revisao) ? $q->mensagem_revisao : null,
                'modulo_recomendado' => is_string($q->modulo_recomendado) ? $q->modulo_recomendado : null,
                // `null` no banco quer dizer "usar o padrão", que é permitir.
                'permite_nova_tentativa' => $q->permite_nova_tentativa !== false,
            ];
        }

        return ['id' => (string) $quiz->id, 'titulo' => (string) $quiz->titulo, 'questoes' => $questoes];
    }

    /** Quantos alunos já responderam esta avaliação — o aviso de quem edita uma publicada. */
    public function quantosJaResponderam(string $avaliacao_id): int
    {
        return TentativaAvaliacao::query()->where('avaliacao_id', $avaliacao_id)->distinct()->count('usuario_id');
    }

    /**
     * A prova para o aluno responder — enunciados e alternativas, SEM gabarito.
     *
     * Não sai daqui `indice_opcao_correta`, `explanation`, `mensagem_revisao` nem
     * `modulo_recomendado`. Na API eles iam junto (`listQuizzes`, até 23/10), e a tela do
     * React mostrava a alternativa certa a cada questão e deixava refazer a
     * questão até acertar: a nota "recalculada no servidor" era sobre
     * respostas dadas depois de ver o gabarito. Decisão de 20/10: a prova é
     * corrigida no fim, e o gabarito não chega ao navegador.
     *
     * Null quando o quiz não é deste curso ou não está publicado.
     *
     * @return array{id:string,titulo:string,questoes:list<array{id:string,texto_questao:string,opcoes:list<string>}>}|null
     */
    public function provaParaResponder(string $curso_id, string $avaliacao_id): ?array
    {
        $consulta = Avaliacao::query()->with('questoes')->whereKey($avaliacao_id)->where('curso_id', $curso_id);
        Visibilidade::aplicar($consulta, 'student');
        $quiz = $consulta->first();
        if ($quiz === null) {
            return null;
        }

        $questoes = [];
        foreach ($quiz->questoes as $q) {
            $opcoes = is_array($q->opcoes) ? $q->opcoes : [];
            $questoes[] = [
                'id' => (string) $q->id,
                'texto_questao' => (string) $q->texto_questao,
                'opcoes' => array_values(array_map(static fn (mixed $o): string => is_scalar($o) ? (string) $o : '', $opcoes)),
            ];
        }

        return ['id' => (string) $quiz->id, 'titulo' => (string) $quiz->titulo, 'questoes' => $questoes];
    }

    /**
     * O que a tela de resultado mostra de cada questão, depois de enviada.
     *
     * Diz se acertou — mas NÃO qual era a alternativa certa: o aluno pode
     * refazer a prova, e com a letra na mão a segunda tentativa deixaria de
     * medir alguma coisa.
     *
     * Os dois textos da questão têm destinatários diferentes, e isso decide
     * quem vê qual. Nos cursos cadastrados, `explanation` é escrita para QUEM
     * ACERTOU ("Correto! As fotografias foram reunidas principalmente
     * para…") — explica a resposta certa, e por isso a entrega. `mensagem_revisao`
     * é a dica para quem errou ("Observe o objetivo das fotografias…"). Então:
     * acertou, vê a explicação; errou, vê a dica e o módulo a revisar.
     *
     * @param  array<array-key, mixed>  $respostas
     * @return list<array{numero:int,texto_questao:string,acertou:bool,explicacao:string|null,mensagem_revisao:string|null,modulo_recomendado:string|null}>
     */
    public function correcaoParaAluno(string $avaliacao_id, array $respostas): array
    {
        $quiz = Avaliacao::query()->with('questoes')->find($avaliacao_id);
        if ($quiz === null) {
            return [];
        }

        $acertos = $this->acertosPorQuestao($quiz, $respostas);
        $texto = static fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? $v : null;

        $correcao = [];
        foreach ($quiz->questoes->values() as $i => $q) {
            $acertou = $acertos[(string) $q->id] ?? false;
            $correcao[] = [
                'numero' => $i + 1,
                'texto_questao' => (string) $q->texto_questao,
                'acertou' => $acertou,
                'explicacao' => $acertou ? $texto($q->explicacao) : null,
                'mensagem_revisao' => $acertou ? null : $texto($q->mensagem_revisao),
                'modulo_recomendado' => $acertou ? null : $texto($q->modulo_recomendado),
            ];
        }

        return $correcao;
    }

    // ---------- FÓRUM ----------

    /**
     * Fórum escopado por curso, como o chat de aula (MessagingService::listChatMessages).
     * Antes devolvia o fórum de TODOS os cursos para qualquer autenticado: a regra já
     * existia no projeto, faltava aqui.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listForumMessages(array $requester): array
    {
        $query = MensagemForum::query();
        if ($requester['papel'] !== 'admin') {
            $query->whereIn('curso_id', CourseAccess::accessibleCourseIds($requester));
        }

        return $query->get()->map->toArray()->all();
    }

    /**
     * @param  array{curso_id:string,texto:string}  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function createForumMessage(array $input, array $requester): array
    {
        // curso_id vem do corpo: sem esta checagem, qualquer autenticado publicava no
        // fórum de qualquer turma. createChatMessage já barrava o caso equivalente.
        if (! CourseAccess::canAccess($requester, $input['curso_id'])) {
            throw ApiException::forbidden('Você não participa deste curso.');
        }

        return MensagemForum::query()->create([
            'id' => 'forum-msg-'.$this->nowMs(),
            'curso_id' => $input['curso_id'],
            'nome_remetente' => $requester['nome'],
            'remetente_usuario_id' => $requester['sub'],
            'papel_remetente' => $requester['papel'],
            'texto' => $input['texto'],
            'registrado_em_texto' => Fuso::agora()->format('d/m/Y H:i'),
            // Mesmo instante em coluna nativa (Norma C.2.4). O texto acima é hora
            // de Brasília e continua sendo o que a tela mostra; a coluna guarda em
            // UTC, como todo instante aqui.
            'enviada_em' => CarbonImmutable::now()->utc(),
            'curtidas' => 0,
            'curtido_por' => [],
        ])->toArray();
    }

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function toggleForumLike(string $messageId, array $requester): array
    {
        $msg = MensagemForum::query()->find($messageId);
        if ($msg === null) {
            throw ApiException::notFound('Mensagem não encontrada.');
        }
        // Curtir devolve a mensagem inteira: sem checar o curso, qualquer logado lia o fórum de qualquer
        // curso só com o id (achado da varredura de segurança de 23/10/2026).
        if (! is_string($msg->curso_id) || ! CourseAccess::canAccess($requester, $msg->curso_id)) {
            throw ApiException::forbidden('Você não participa deste curso.');
        }
        // curtido_por é um array de userIds desde a ADR 10 (backfill converteu nomes).
        $curtido_por = is_array($msg->curtido_por) ? $msg->curtido_por : [];
        $hasLiked = in_array($requester['sub'], $curtido_por, true);
        $next = $hasLiked
            ? array_values(array_filter($curtido_por, fn ($u) => $u !== $requester['sub']))
            : [...$curtido_por, $requester['sub']];
        $msg->curtido_por = $next;
        $msg->curtidas = count($next);
        $msg->save();

        return $msg->toArray();
    }

    /** @param array{sub:string,nome:string,papel:string} $requester */
    public function deleteForumMessage(string $id, array $requester): void
    {
        $msg = MensagemForum::query()->find($id);
        if ($msg === null) {
            return;
        }
        $owns = Identity::ownsRow($msg->remetente_usuario_id, $requester);
        if ($requester['papel'] !== 'admin' && ! $owns) {
            throw ApiException::forbidden('Você só pode remover as próprias mensagens.');
        }
        // Sai do fórum, fica no registro (ADR 12): moderação precisa poder
        // responder depois o que foi dito e por quem.
        $msg->inativar($requester['sub'] ?? null);
    }

    // ---------- EXERCÍCIOS PRÁTICOS ----------

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listExercises(array $requester): array
    {
        $query = ExercicioPratico::query();
        if ($requester['papel'] !== 'admin') {
            $query->whereIn('curso_id', CourseAccess::accessibleCourseIds($requester));
        }

        return $query->get()->map->toArray()->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function createExercise(array $input, array $requester): array
    {
        $this->assertInstructorOwnsCourse(is_string($input['curso_id'] ?? null) ? $input['curso_id'] : null, $requester);

        $id = $input['id'] ?? ('exercise-'.$this->nowMs());
        $data = $this->exerciseScalar($input);
        $data['id'] = $id;

        return ExercicioPratico::query()->create($data)->toArray();
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function updateExercise(string $id, array $updates, array $requester): array
    {
        $exercise = ExercicioPratico::query()->find($id);
        if ($exercise === null) {
            throw ApiException::notFound('Exercício não encontrado.');
        }
        // Posse do curso atual do exercício e, se o payload mudar o curso, também do novo.
        $this->assertInstructorOwnsCourse(is_string($exercise->curso_id) ? $exercise->curso_id : null, $requester);
        if (is_string($updates['curso_id'] ?? null) && $updates['curso_id'] !== $exercise->curso_id) {
            $this->assertInstructorOwnsCourse($updates['curso_id'], $requester);
        }
        $exercise->fill($this->exerciseScalar($updates))->save();

        return $exercise->toArray();
    }

    /** @param array{sub:string,nome:string,papel:string} $requester */
    public function deleteExercise(string $id, array $requester, ?string $motivo = null): void
    {
        $exercise = ExercicioPratico::query()->find($id);
        if ($exercise === null) {
            return;
        }
        $this->assertInstructorOwnsCourse(is_string($exercise->curso_id) ? $exercise->curso_id : null, $requester);
        // As entregas dos alunos NÃO são tocadas: com a exclusão física, o
        // ON DELETE CASCADE de EntregaExercicio.exercicio_id levava embora todo
        // trabalho entregue e corrigido daquele exercício.
        $exercise->inativar($requester['sub'] ?? null, $motivo);
    }

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listExerciseSubmissions(array $requester): array
    {
        $q = EntregaExercicio::query();
        if ($requester['papel'] === 'student') {
            Identity::applyOwnRows($q, $requester);
        } elseif ($requester['papel'] === 'instructor') {
            // EntregaExercicio não tem curso_id; escopo via exercícios dos cursos do instrutor.
            $exerciseIds = ExercicioPratico::query()
                ->whereIn('curso_id', InstructorScope::courseIds($requester))
                ->pluck('id')->all();
            $q->whereIn('exercicio_id', $exerciseIds);
        }

        return $q->get()->map->toArray()->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function submitExercise(array $input, array $requester): array
    {
        if ($requester['papel'] !== 'student') {
            throw ApiException::forbidden('Somente alunos podem enviar entregas de exercícios.');
        }

        // A entrega é de um exercício de um curso a que o aluno pertence, e não de qualquer id.
        $exercicio = ExercicioPratico::query()->whereKey($input['exercicio_id'])->first(['id', 'curso_id']);
        if ($exercicio === null) {
            throw ApiException::notFound('Exercício não encontrado.');
        }
        if (! is_string($exercicio->curso_id) || ! CourseAccess::canAccess($requester, $exercicio->curso_id)) {
            throw ApiException::forbidden('Você não participa deste curso.');
        }

        $data = [
            'exercicio_id' => $input['exercicio_id'],
            'nome_aluno' => $requester['nome'],
            'usuario_id' => $requester['sub'],
            'texto_entrega' => $input['texto_entrega'],
            'url_arquivo' => $input['url_arquivo'] ?? null,
            'nome_arquivo' => $input['nome_arquivo'] ?? null,
            'enviado_em_texto' => Fuso::agora()->format('d/m/Y H:i:s'),
            'status' => 'pending',
        ];
        $existing = EntregaExercicio::query()
            ->where('exercicio_id', $input['exercicio_id'])->where('usuario_id', $requester['sub'])->first();
        if ($existing !== null) {
            $existing->fill($data)->save();

            return $existing->toArray();
        }
        $data['id'] = 'sub-'.$this->nowMs();

        return EntregaExercicio::query()->create($data)->toArray();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function gradeSubmission(string $submissionId, array $input, array $requester): array
    {
        if ($requester['papel'] === 'student') {
            throw ApiException::forbidden('Alunos não podem corrigir entregas.');
        }
        $submission = EntregaExercicio::query()->find($submissionId);
        if ($submission === null) {
            throw ApiException::notFound('Entrega não encontrada.');
        }
        // Instrutor só corrige entregas de exercícios dos seus cursos (antes: qualquer um).
        $exerciseCourseId = ExercicioPratico::query()->whereKey($submission->exercicio_id)->value('curso_id');
        $this->assertInstructorOwnsCourse(is_string($exerciseCourseId) ? $exerciseCourseId : null, $requester);
        $submission->fill([
            'nota' => is_numeric($input['nota'] ?? null) ? (int) $input['nota'] : 0,
            'devolutiva' => $input['devolutiva'],
            'status' => $input['status'],
            'corrigido_em_texto' => Fuso::agora()->format('d/m/Y H:i:s'),
            'corrigido_por' => $requester['nome'],
        ])->save();

        return $submission->toArray();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function exerciseScalar(array $input): array
    {
        $campos = array_intersect_key($input, array_flip(['curso_id', 'titulo', 'descricao', 'instrucoes', 'pontuacao_maxima', 'prazo_texto']));

        /*
         * `prazo_em` acompanha `prazo_texto` (Norma C.2.4): o texto 'd/m/Y' segue sendo
         * o que a tela exibe e o que a API entrega, e a coluna DATE ao lado é o
         * prazo comparável. Só é tocada quando `prazo_texto` vem no pedido — assim uma
         * edição parcial não zera um prazo que ninguém pediu para mudar.
         *
         * Data que não converte vira NULL em vez de data inventada; a validação da
         * rota já exige `date_format:d/m/Y`, então isso é rede, não caminho normal.
         */
        if (array_key_exists('prazo_texto', $campos)) {
            $campos['prazo_em'] = DataTexto::dia(is_string($campos['prazo_texto']) ? $campos['prazo_texto'] : null);
        }

        return $campos;
    }

    /**
     * Posse de curso do instrutor (ADR 10): admin é irrestrito; instrutor só age em
     * curso que leciona; qualquer outro caso é 403. Evita que um instrutor gerencie
     * quizzes/exercícios/notas de cursos de terceiros.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    private function assertInstructorOwnsCourse(?string $curso_id, array $requester): void
    {
        if ($requester['papel'] === 'admin') {
            return;
        }
        if ($requester['papel'] === 'instructor'
            && $curso_id !== null
            && in_array($curso_id, InstructorScope::courseIds($requester), true)) {
            return;
        }
        throw ApiException::forbidden('Você só pode gerenciar conteúdo dos seus próprios cursos.');
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
