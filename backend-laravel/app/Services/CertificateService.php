<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Avaliacao;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\ProgressoAluno;
use App\Models\TentativaAvaliacao;
use App\Support\BusinessRules;
use App\Support\CourseAccess;
use App\Support\Fuso;
use App\Support\Identity;
use App\Support\InstructorScope;
use App\Support\Visibilidade;
use Carbon\CarbonImmutable;

/**
 * Emissão e consulta de certificados — espelha src/server/services/certificateService.ts.
 * A emissão NUNCA confia no percentual do cliente: recalcula a frequência a partir do
 * curso + progresso reais. Idempotente por (nome_aluno, curso_id).
 */
final class CertificateService
{
    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function listCertificates(array $requester, int $skip, int $take): array
    {
        // Aluno vê só os próprios; instrutor só os dos cursos que leciona; admin, todos.
        // Antes o instrutor listava todos os certificados da plataforma.
        $base = Certificado::query();
        if ($requester['papel'] === 'student') {
            Identity::applyOwnRows($base, $requester);
        } elseif ($requester['papel'] === 'instructor') {
            $base->whereIn('curso_id', InstructorScope::courseIds($requester));
        }

        $total = (clone $base)->count();
        $items = $base->orderBy('data_emissao', 'desc')->skip($skip)->take($take)->get()
            ->map->toArray()->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Consulta pública de verificação (id, hash ou nome). Resposta em whitelist:
     * rota sem autenticação nunca expõe identificadores internos (usuario_id/matricula_id).
     *
     * Inclui os REVOGADOS, e é o ponto dessa rota existir.
     *
     * O certificado revogado é inativado (ADR 12), e `Certificado::query()` com
     * SoftDeletes o omite. Sem `withTrashed()` aqui, revogar fazia a verificação
     * pública responder "não existe" sobre um documento que existe no mundo —
     * está impresso, tem hash e pode estar anexado a um processo. Quem confere um
     * papel precisa da resposta "foi revogado", que é diferente de "nunca houve".
     *
     * @return array<string, mixed>|null
     */
    public function verifyCertificatePublic(string $query): ?array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return null;
        }

        /*
         * O NOME tem de ser o nome completo, igual (a comparação do MySQL ignora caixa e acento).
         * Antes era `LIKE %termo%`: `q=Ma`, ou só `%`, devolvia nome, curso, frequência e hash do
         * primeiro aluno que casasse, e variando o termo se varria a lista de alunos pela rota
         * pública (achado da varredura de segurança de 23/10/2026). A tela continua prometendo
         * "código ou nome completo do aluno": quem já sabe o nome inteiro continua achando.
         */
        $cert = Certificado::withTrashed()
            ->where(function ($q) use ($trimmed): void {
                $q->where('id', $trimmed)
                    ->orWhere('hash_verificacao', $trimmed)
                    ->orWhere('nome_aluno', $trimmed);
            })
            ->first();
        if ($cert === null) {
            return null;
        }

        $carga_horaria = Curso::query()->find($cert->curso_id)?->carga_horaria;

        return [
            'id' => $cert->id,
            'nome_aluno' => $cert->nome_aluno,
            'titulo_curso' => $cert->titulo_curso,
            // Acesso direto devolve Carbon (cast date) — formata explicitamente
            // porque este array não passa pela serialização do model.
            'data_emissao' => $cert->data_emissao?->format('d/m/Y'),
            'percentual_frequencia' => $cert->percentual_frequencia,
            'hash_verificacao' => $cert->hash_verificacao,
            'carga_horaria' => is_numeric($carga_horaria) ? (int) $carga_horaria : null,
            // Estado do documento. `revogado` é o que decide o que a tela diz;
            // a data e o motivo existem para a pessoa que está com o papel na
            // mão entender por quê. Quem revogou NÃO sai daqui: rota pública não
            // expõe identificador interno de servidor.
            'revogado' => $cert->estaInativo(),
            'revogadoEm' => Fuso::local($cert->inativado_em)?->format('d/m/Y'),
            'motivoRevogacao' => $cert->estaInativo() ? $cert->motivo_inativacao : null,
        ];
    }

    /**
     * @param  array{usuario_id?:string|null,curso_id:string}  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function issueCertificate(array $input, array $requester): array
    {
        $usuario_id = Identity::resolveActorUserId($requester, $input['usuario_id'] ?? null);

        $course = Curso::query()->with(['aulas', 'sessoesAoVivo'])->find($input['curso_id']);
        if ($course === null) {
            throw ApiException::notFound('Curso não encontrado.');
        }

        /*
         * O aluno precisa PERTENCER ao curso: matrícula, conclusão registrada ou admissão aprovada.
         * Sem isto, quem forjava progresso num curso alheio (ou tinha progresso solto no banco)
         * recebia certificado de um curso que nunca cursou. Vale para quem emite, inclusive o admin.
         */
        if (! CourseAccess::canAccess(['sub' => $usuario_id, 'nome' => '', 'papel' => 'student'], $course->id)) {
            throw ApiException::forbidden('Este aluno não participa do curso.');
        }

        // Instrutor emite certificado apenas nos cursos que leciona — a mesma regra
        // que CertificatePdfService já aplica no download. Faltava aqui: quem podia
        // baixar só o próprio conseguia EMITIR para qualquer curso da escola.
        // Para o aluno o risco já era contido (a frequência é recalculada abaixo).
        if ($requester['papel'] === 'instructor'
            && ! in_array($course->id, InstructorScope::courseIds($requester), true)) {
            throw ApiException::forbidden('Você só pode emitir certificados dos seus cursos.');
        }

        /*
         * Idempotência: se já existe certificado para aluno+curso, apenas retorna.
         *
         * `withTrashed()` é obrigatório aqui (armadilha 1 da ADR 12). Sem ele o
         * certificado REVOGADO não é encontrado, o `create()` abaixo segue em
         * frente e bate na UNIQUE (nome_aluno, curso_id) — erro de servidor. Ou
         * seja: revogar um certificado tornava aquele aluno impossível de
         * certificar naquele curso para sempre.
         */
        $existing = Certificado::withTrashed()
            ->where('usuario_id', $usuario_id)
            ->where('curso_id', $input['curso_id'])
            ->first();
        if ($existing !== null && ! $existing->estaInativo()) {
            return $existing->toArray();
        }
        if ($existing !== null) {
            /*
             * Revogado NÃO é reemitido por chamada de rotina.
             *
             * A UNIQUE (nome_aluno, curso_id) obriga a reemissão a reaproveitar
             * a MESMA linha, e `reativar()` limpa `inativado_por` e
             * `motivo_inativacao` — reemitir aqui apagaria o registro de que houve
             * revogação, que é justamente o que a ADR 12 manda preservar. Devolver
             * a validade a um documento revogado é decisão administrativa, e
             * precisa de fluxo próprio que registre a reabilitação.
             */
            $quando = Fuso::local($existing->inativado_em)?->format('d/m/Y') ?? 'data não registrada';
            throw ApiException::forbidden(
                "Este certificado foi revogado em {$quando} e não é reemitido automaticamente. "
                .'A reabilitação é ato da administração e tem de ficar registrada.'
            );
        }

        $progress = ProgressoAluno::query()
            ->where('usuario_id', $usuario_id)
            ->where('curso_id', $input['curso_id'])
            ->first();

        $percentual_frequencia = $this->computeAttendancePercent($course, $progress);
        $frequencia_minima = BusinessRules::courseMinAttendance($course->frequencia_minima);

        if ($percentual_frequencia < $frequencia_minima) {
            throw ApiException::forbidden("Critério de frequência ainda não atingido para emissão do certificado ({$percentual_frequencia}% de {$frequencia_minima}% exigidos).");
        }

        /*
         * Frequência não basta quando o curso avalia.
         *
         * A regra existe por uma consequência concreta: desde que concluir aula
         * passou a ser automático ao avançar, quem clicasse "Próxima aula" até o
         * fim atingia o mínimo de frequência e o certificado saía sozinho — sem
         * responder uma única questão. Certificado de escola pública é registro
         * acadêmico, e revogar depois é ato administrativo, não um desfazer.
         *
         * Decisão da coordenação (09/09/2026): se o curso tem avaliação, o
         * certificado automático exige TODAS as avaliações ativas APROVADAS.
         * Curso sem avaliação segue só pela frequência — é o que ele mede.
         */
        $pendentes = $this->avaliacoesNaoAprovadas($course->id, $usuario_id);
        if ($pendentes !== []) {
            $quantas = count($pendentes);
            $lista = implode(', ', $pendentes);
            throw ApiException::forbidden(
                $quantas === 1
                    ? "Falta ser aprovado na avaliação \"{$lista}\" para a emissão do certificado."
                    : "Faltam {$quantas} avaliações aprovadas para a emissão do certificado: {$lista}."
            );
        }

        $matricula_id = Matricula::query()->whereKey($usuario_id)->value('id');

        $hashHex = strtoupper(bin2hex(random_bytes(8)));
        $certificate = Certificado::query()->create([
            'id' => "cert-{$course->id}-{$hashHex}",
            'nome_aluno' => Identity::displayName($usuario_id, $requester),
            'usuario_id' => $usuario_id,
            'matricula_id' => $matricula_id,
            'curso_id' => $course->id,
            'titulo_curso' => $course->titulo,
            // Coluna DATE — o cast do model serializa como d/m/Y no contrato.
            'data_emissao' => CarbonImmutable::now(),
            'percentual_frequencia' => $percentual_frequencia,
            'hash_verificacao' => "AVA-{$hashHex}",
        ]);

        return $certificate->toArray();
    }

    /**
     * O que ainda falta para o certificado de um curso sair — só leitura.
     *
     * Existe para a TELA não refazer a conta. A frequência é o número que
     * decide a emissão, e tê-la calculada em dois lugares é como a tela passa a
     * dizer 68% enquanto a emissão recusa por 64% — nenhum dos dois erra
     * sozinho, e ninguém descobre qual está certo.
     *
     * As avaliações pendentes vêm junto porque desde a ADR 14 elas também
     * seguram o certificado. O painel antigo só falava de frequência: com a
     * frequência cumprida e uma prova em aberto, ele dizia "continue assistindo
     * as aulas" para quem já tinha assistido todas.
     *
     * @return array{frequencia:int, minima:int, avaliacoesPendentes:list<string>, emite:bool}
     */
    public function situacaoParaCertificado(string $curso_id, string $usuario_id): array
    {
        $course = Curso::query()->with(['aulas', 'sessoesAoVivo'])->find($curso_id);

        if ($course === null) {
            return ['frequencia' => 0, 'minima' => BusinessRules::defaultMinAttendance(), 'avaliacoesPendentes' => [], 'emite' => false];
        }

        $progress = ProgressoAluno::query()
            ->where('usuario_id', $usuario_id)
            ->where('curso_id', $curso_id)
            ->first();

        return [
            'frequencia' => $this->computeAttendancePercent($course, $progress),
            'minima' => BusinessRules::courseMinAttendance($course->frequencia_minima),
            'avaliacoesPendentes' => array_values($this->avaliacoesNaoAprovadas($curso_id, $usuario_id)),
            /*
             * Uma fonte só: a COLUNA `emite_certificado` do curso, a que o admin e o
             * instrutor editam (`NULL` conta como "emite": a coluna nasceu depois
             * dos cursos que já existiam). Até 23/10/2026 também valia a convenção
             * de escrever "Sem Certificado" na categoria; o responsável decidiu que
             * vale o curso apenas, e nenhum curso usava a convenção.
             */
            'emite' => $course->emite_certificado !== false,
        ];
    }

    /**
     * Avaliações do curso em que o aluno ainda NÃO foi aprovado, por título.
     *
     * Três recortes, e cada um evita travar o certificado de quem não tem culpa:
     *
     * 1. Avaliação **inativada** não conta — o SoftDeletes já a exclui. Tirar uma
     *    prova do ar não pode congelar o certificado de todo mundo.
     * 2. Só avaliação **visível ao aluno** conta, e a escada é consultada com o
     *    papel `student` FIXO, não com o papel de quem chama. Uma prova em
     *    rascunho, que o aluno nem enxerga, não pode ser cobrada dele — e se o
     *    papel do requisitante entrasse aqui, o gestor emitindo em nome do aluno
     *    seria cobrado por uma prova que só o gestor vê.
     * 3. Aprovação é `aprovado` em QUALQUER tentativa, não na última: a ADR 12
     *    manda acrescentar tentativa em vez de sobrescrever, então reprovar
     *    depois de já ter sido aprovado não desfaz a aprovação.
     *
     * @return array<int, string>
     */
    private function avaliacoesNaoAprovadas(string $curso_id, string $usuario_id): array
    {
        $exigidas = Avaliacao::query()->where('curso_id', $curso_id);
        Visibilidade::aplicar($exigidas, 'student');
        /** @var array<string, string> $titulos */
        $titulos = $exigidas->pluck('titulo', 'id')->all();

        if ($titulos === []) {
            return [];
        }

        /** @var array<int, string> $aprovadas */
        $aprovadas = TentativaAvaliacao::query()
            ->where('usuario_id', $usuario_id)
            ->where('curso_id', $curso_id)
            ->where('aprovado', true)
            ->whereIn('avaliacao_id', array_keys($titulos))
            ->pluck('avaliacao_id')
            ->all();

        $pendentes = [];
        foreach ($titulos as $avaliacao_id => $titulo) {
            if (! in_array($avaliacao_id, $aprovadas, true)) {
                $pendentes[] = $titulo;
            }
        }

        return $pendentes;
    }

    /**
     * Revoga um certificado (ADR 12). Não apaga.
     *
     * Certificado é documento: já foi emitido, tem hash de verificação pública e
     * pode estar impresso ou anexado num processo. Some do banco significa que a
     * consulta pública passa a dizer "não existe" sobre um papel que existe no
     * mundo. Revogar registra quem revogou, quando e por quê — e é isso que a
     * verificação pública precisa poder responder.
     */
    public function deleteCertificate(string $id, ?string $porUserId = null, ?string $motivo = null): void
    {
        Certificado::query()->find($id)?->inativar($porUserId, $motivo);
    }

    private function computeAttendancePercent(Curso $course, ?ProgressoAluno $progress): int
    {
        $totalActivities = $course->aulas->count() + $course->sessoesAoVivo->count();
        if ($totalActivities === 0) {
            return 0;
        }
        // Conta apenas ids que AINDA existem no curso. Resíduo de aula apagada
        // ficava em `aulas_concluidas` e era contado como presença — e esta é a
        // conta que decide a EMISSÃO do certificado, não um número de tela.
        $aulas = is_array($progress?->aulas_concluidas) ? $progress->aulas_concluidas : [];
        $encontros = is_array($progress?->sessoes_ao_vivo_assistidas) ? $progress->sessoes_ao_vivo_assistidas : [];
        $done = count(array_intersect(
            array_values(array_filter($aulas, static fn ($id): bool => is_string($id))),
            array_values(array_filter($course->aulas->pluck('id')->all(), static fn ($id): bool => is_string($id))),
        )) + count(array_intersect(
            array_values(array_filter($encontros, static fn ($id): bool => is_string($id))),
            array_values(array_filter($course->sessoesAoVivo->pluck('id')->all(), static fn ($id): bool => is_string($id))),
        ));

        return min(100, (int) round(($done / $totalActivities) * 100));
    }
}
