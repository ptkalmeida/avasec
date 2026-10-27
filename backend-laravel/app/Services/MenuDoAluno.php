<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ProgressoDoAluno;

/**
 * O que o menu da área do aluno precisa saber, calculado num lugar só.
 *
 * Existe por duas armadilhas. A primeira já ia acontecer: o submenu "Cursos
 * concluídos" recebia a lista de cada PÁGINA, e o layout usava `[]` quando a
 * página não a passava. Toda tela nova que esquecesse faria o menu afirmar
 * "Nenhum curso concluído ainda" para quem concluiu — sem erro, sem aviso, só
 * uma frase falsa. Com este serviço, quem esquece recebe a lista certa.
 *
 * A segunda: o menu largo e o menu das telas estreitas eram duas listas escritas
 * à mão, e já divergiram uma vez (o suporte apontava para uma tela fechada ao
 * aluno em uma delas). Os itens simples — rótulo, endereço e a condição de cada
 * um — saem de `itens()`, e as duas telas leem de lá.
 *
 * Usa os MESMOS serviços que a área do aluno: a matrícula vem do
 * `EnrollmentService` e os cursos de `listCoursesFor`, que aplica a escada de
 * visibilidade — curso inativado ou em rascunho não aparece no menu.
 *
 * É registrado como `scoped` (um por requisição): o menu é desenhado duas vezes
 * na mesma tela (largo e estreito) e a matrícula, os cursos e o progresso são
 * buscados uma vez só.
 */
final class MenuDoAluno
{
    /** @var array{sub:string,matricula:array<string, mixed>,porId:array<string, array<string, mixed>>,registros:array<int|string, mixed>}|null */
    private ?array $base = null;

    public function __construct(
        private readonly EnrollmentService $matriculas,
        private readonly CourseService $cursos,
    ) {}

    /**
     * O menu inteiro, pronto para desenhar — o mesmo para o menu largo e para o das telas estreitas.
     *
     * `$cursoDaTela` é o curso que a página está mostrando (curso, aula, avaliações). Se ele não
     * está em andamento (um curso concluído), entra na lista mesmo assim, como o atual: é nele que
     * moram "Aulas do curso" e "Avaliações", e a pessoa precisa achar o caminho de volta.
     *
     * @param  array{sub:string,nome:string,papel:string}  $aluno
     * @param  array<string, mixed>|null  $cursoDaTela
     * @param  list<array{id:string,slug:string|null,titulo:string}>|null  $concluidos  a lista da página, se ela já tem
     * @return array{
     *     itens:list<array{chave:string,rotulo:string,rota:string,icone:string,grupo:string}>,
     *     cursos:list<array{id:string,rota:string,titulo:string,progresso:string,atual:bool,avaliacoes:string|null}>,
     *     continuar:array{rota:string,aula:string,curso:string}|null,
     *     concluidos:list<array{id:string,slug:string|null,titulo:string}>
     * }
     */
    public function montar(array $aluno, ?array $cursoDaTela, ?string $aulaAtual = null, ?array $concluidos = null): array
    {
        $atualId = is_string($cursoDaTela['id'] ?? null) ? $cursoDaTela['id'] : null;

        $cursos = [];
        $achouOAtual = false;
        foreach ($this->emAndamento($aluno) as $curso) {
            $atual = $curso['id'] === $atualId;
            $achouOAtual = $achouOAtual || $atual;
            $rota = '/aluno/curso/'.($curso['slug'] ?? $curso['id']);
            $cursos[] = [
                'id' => $curso['id'],
                'rota' => $rota,
                'titulo' => $curso['titulo'],
                'progresso' => $this->progresso($curso['aulasConcluidas'], $curso['aulasNoCurso']),
                'atual' => $atual,
                'avaliacoes' => $atual ? $rota.'/avaliacoes' : null,
            ];
        }

        if ($atualId !== null && ! $achouOAtual && $cursoDaTela !== null) {
            $ref = is_string($cursoDaTela['slug'] ?? null) && $cursoDaTela['slug'] !== '' ? $cursoDaTela['slug'] : $atualId;
            $rota = '/aluno/curso/'.$ref;
            $cursos[] = [
                'id' => $atualId,
                'rota' => $rota,
                'titulo' => is_string($cursoDaTela['titulo'] ?? null) ? $cursoDaTela['titulo'] : '',
                'progresso' => is_int($cursoDaTela['aulasConcluidas'] ?? null) && is_int($cursoDaTela['aulasNoCurso'] ?? null)
                    ? $this->progresso($cursoDaTela['aulasConcluidas'], $cursoDaTela['aulasNoCurso'])
                    : '',
                'atual' => true,
                'avaliacoes' => $rota.'/avaliacoes',
            ];
        }

        return [
            'itens' => $this->itens($aulaAtual),
            'cursos' => $cursos,
            'continuar' => $this->continuar($aluno, $atualId),
            'concluidos' => $concluidos ?? $this->concluidos($aluno),
        ];
    }

    /** "X de Y aulas concluídas" — a contagem que a skill manda exibir; o percentual nunca vira texto. */
    private function progresso(int $concluidas, int $total): string
    {
        return $total > 0 ? $concluidas.' de '.$total.' aulas concluídas' : '';
    }

    /**
     * Os itens simples do menu, na ordem em que aparecem. O que depende de dados
     * (cursos, "Continuar estudando") é montado à parte, pelas telas.
     *
     * "Ajuda e suporte" é um item só, com duas abas (dúvidas e conversa com o
     * gestor). Dentro de uma aula ele abre já na conversa, com a aula junto: o
     * gestor recebe o título dela no assunto. Com mensagens desligadas ele leva
     * às dúvidas, em vez de a uma página que só diz que não dá.
     *
     * @return list<array{chave:string,rotulo:string,rota:string,icone:string,grupo:string}>
     */
    public function itens(?string $aulaAtual = null): array
    {
        $suporteLigado = config('features.mensagensDiretas') === true;

        $itens = [
            ['chave' => 'painel', 'rotulo' => 'Início', 'rota' => '/aluno', 'icone' => 'painel', 'grupo' => 'principal'],
            ['chave' => 'catalogo', 'rotulo' => 'Descobrir cursos', 'rota' => '/aluno/catalogo', 'icone' => 'catalogo', 'grupo' => 'principal'],
            ['chave' => 'certificados', 'rotulo' => 'Certificados', 'rota' => '/aluno/certificados', 'icone' => 'certificado', 'grupo' => 'principal'],
        ];

        // `bibliotecaDigital` é a flag da biblioteca, desligada por decisão de 21/10. No React a tela
        // dependia de `materiaisComplementares`, que é a do MATERIAL DA AULA: desligar a biblioteca
        // por ela levaria os anexos das aulas junto.
        if (config('features.bibliotecaDigital') === true) {
            $itens[] = ['chave' => 'biblioteca', 'rotulo' => 'Biblioteca', 'rota' => '/aluno/biblioteca', 'icone' => 'biblioteca', 'grupo' => 'principal'];
        }

        $rotaDaAjuda = $suporteLigado && $aulaAtual !== null
            ? '/aluno/suporte?aula='.urlencode($aulaAtual)
            : '/aluno/duvidas';

        $itens[] = ['chave' => 'ajuda', 'rotulo' => 'Ajuda e suporte', 'rota' => $rotaDaAjuda, 'icone' => 'ajuda', 'grupo' => 'rodape'];
        $itens[] = ['chave' => 'perfil', 'rotulo' => 'Perfil', 'rota' => '/aluno/perfil', 'icone' => 'perfil', 'grupo' => 'rodape'];

        return $itens;
    }

    /**
     * Cursos em andamento: o da matrícula principal e os concorrentes, quando a
     * coordenação concedeu matrícula múltipla. Cada um traz o que o menu mostra
     * ("X de Y aulas concluídas") e a próxima aula a estudar.
     *
     * @param  array{sub:string,nome:string,papel:string}  $aluno
     * @return list<array{id:string,slug:string|null,titulo:string,aulasConcluidas:int,aulasNoCurso:int,proxima:array{id:string,titulo:string}|null}>
     */
    public function emAndamento(array $aluno): array
    {
        $base = $this->base($aluno);
        $matricula = $base['matricula'];

        $extras = is_array($matricula['cursos_extras_ids'] ?? null) ? $matricula['cursos_extras_ids'] : [];
        $ids = array_merge(
            is_string($matricula['curso_matriculado_id'] ?? null) ? [$matricula['curso_matriculado_id']] : [],
            array_values(array_filter($extras, static fn (mixed $id): bool => is_string($id))),
        );

        $lista = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $curso = $base['porId'][$id] ?? null;
            if ($curso !== null) {
                $lista[] = $this->resumo($curso, $base['registros']);
            }
        }

        return $lista;
    }

    /**
     * Para onde "Continuar estudando" leva: a primeira aula não concluída do
     * curso em tela, se ele está em andamento e ainda tem aula; senão, do
     * primeiro curso em andamento que tenha. Sem aula pendente em nenhum, não há
     * botão — apontar para uma aula já concluída seria mandar rever, e não
     * continuar.
     *
     * @param  array{sub:string,nome:string,papel:string}  $aluno
     * @return array{rota:string,aula:string,curso:string}|null
     */
    public function continuar(array $aluno, ?string $cursoAtualId = null): ?array
    {
        $cursos = $this->emAndamento($aluno);

        usort($cursos, static fn (array $a, array $b): int => ($b['id'] === $cursoAtualId) <=> ($a['id'] === $cursoAtualId));

        foreach ($cursos as $curso) {
            if ($curso['proxima'] === null) {
                continue;
            }

            return [
                'rota' => '/aluno/curso/'.($curso['slug'] ?? $curso['id']).'/aula/'.$curso['proxima']['id'],
                'aula' => $curso['proxima']['titulo'],
                'curso' => $curso['titulo'],
            ];
        }

        return null;
    }

    /**
     * @param  array{sub:string,nome:string,papel:string}  $aluno
     * @return list<array{id:string,slug:string|null,titulo:string}>
     */
    public function concluidos(array $aluno): array
    {
        $base = $this->base($aluno);
        $ids = is_array($base['matricula']['cursos_concluidos_ids'] ?? null) ? $base['matricula']['cursos_concluidos_ids'] : [];

        $lista = [];
        foreach ($ids as $id) {
            if (! is_string($id) || ! isset($base['porId'][$id])) {
                continue;
            }

            $curso = $base['porId'][$id];
            $lista[] = [
                'id' => $id,
                'slug' => $this->slugDe($curso),
                'titulo' => is_string($curso['titulo'] ?? null) ? $curso['titulo'] : '',
            ];
        }

        return $lista;
    }

    /**
     * @param  array<string, mixed>  $curso
     * @param  array<int|string, mixed>  $registros
     * @return array{id:string,slug:string|null,titulo:string,aulasConcluidas:int,aulasNoCurso:int,proxima:array{id:string,titulo:string}|null}
     */
    private function resumo(array $curso, array $registros): array
    {
        $id = is_string($curso['id'] ?? null) ? $curso['id'] : '';
        $aulas = $this->aulasEmOrdem($curso);
        $idsDasAulas = array_map(static fn (array $aula): mixed => $aula['id'] ?? null, $aulas);
        $concluidas = $this->concluidasDe($id, $registros);

        $proxima = null;
        foreach ($aulas as $aula) {
            if (is_string($aula['id'] ?? null) && ! in_array($aula['id'], $concluidas, true)) {
                $proxima = ['id' => $aula['id'], 'titulo' => is_string($aula['titulo'] ?? null) ? $aula['titulo'] : ''];
                break;
            }
        }

        return [
            'id' => $id,
            'slug' => $this->slugDe($curso),
            'titulo' => is_string($curso['titulo'] ?? null) ? $curso['titulo'] : '',
            'aulasConcluidas' => ProgressoDoAluno::contarValidas($concluidas, $idsDasAulas),
            'aulasNoCurso' => count($idsDasAulas),
            'proxima' => $proxima,
        ];
    }

    /**
     * Pela ORDEM da grade, nunca pela de chegada do banco: foi assim que "Iniciar
     * primeira aula" chegou a abrir uma aula do meio no React.
     *
     * @param  array<string, mixed>  $curso
     * @return list<array<string, mixed>>
     */
    private function aulasEmOrdem(array $curso): array
    {
        $aulas = [];
        foreach (is_array($curso['aulas'] ?? null) ? $curso['aulas'] : [] as $aula) {
            if (is_array($aula)) {
                /** @var array<string, mixed> $aula */
                $aulas[] = $aula;
            }
        }

        $ordem = static fn (array $aula): int => is_numeric($aula['ordem'] ?? null) ? (int) $aula['ordem'] : 0;
        usort($aulas, static fn (array $a, array $b): int => $ordem($a) <=> $ordem($b));

        return $aulas;
    }

    /**
     * @param  array<int|string, mixed>  $registros
     * @return list<mixed>
     */
    private function concluidasDe(string $cursoId, array $registros): array
    {
        foreach ($registros as $registro) {
            if (! is_array($registro) || ($registro['curso_id'] ?? null) !== $cursoId) {
                continue;
            }

            return is_array($registro['aulas_concluidas'] ?? null) ? array_values($registro['aulas_concluidas']) : [];
        }

        return [];
    }

    /** @param array<string, mixed> $curso */
    private function slugDe(array $curso): ?string
    {
        return is_string($curso['slug'] ?? null) && $curso['slug'] !== '' ? $curso['slug'] : null;
    }

    /**
     * Matrícula, cursos visíveis e progresso do aluno: buscados uma vez por
     * requisição (o serviço é `scoped`) e refeitos se o aluno mudar.
     *
     * @param  array{sub:string,nome:string,papel:string}  $aluno
     * @return array{sub:string,matricula:array<string, mixed>,porId:array<string, array<string, mixed>>,registros:array<int|string, mixed>}
     */
    private function base(array $aluno): array
    {
        if ($this->base !== null && $this->base['sub'] === $aluno['sub']) {
            return $this->base;
        }

        $linha = $this->matriculas->getEnrollments($aluno)[$aluno['sub']] ?? [];

        $porId = [];
        foreach ($this->cursos->listCoursesFor($aluno) as $curso) {
            if (is_string($curso['id'] ?? null)) {
                $porId[$curso['id']] = $curso;
            }
        }

        return $this->base = [
            'sub' => $aluno['sub'],
            'matricula' => is_array($linha) ? $linha : [],
            'porId' => $porId,
            'registros' => $this->matriculas->getProgress(null, $aluno),
        ];
    }
}
