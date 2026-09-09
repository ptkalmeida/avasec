<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\StudentEnrollment;
use App\Models\StudentProgress;
use App\Support\BusinessRules;
use App\Support\Fuso;
use App\Support\Identity;
use App\Support\InstructorScope;
use App\Support\Visibilidade;
use Carbon\CarbonImmutable;

/**
 * Emissão e consulta de certificados — espelha src/server/services/certificateService.ts.
 * A emissão NUNCA confia no percentual do cliente: recalcula a frequência a partir do
 * curso + progresso reais. Idempotente por (studentName, courseId).
 */
final class CertificateService
{
    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function listCertificates(array $requester, int $skip, int $take): array
    {
        // Aluno vê só os próprios; instrutor só os dos cursos que leciona; admin, todos.
        // Antes o instrutor listava todos os certificados da plataforma.
        $base = Certificate::query();
        if ($requester['role'] === 'student') {
            Identity::applyOwnRows($base, $requester);
        } elseif ($requester['role'] === 'instructor') {
            $base->whereIn('courseId', InstructorScope::courseIds($requester));
        }

        $total = (clone $base)->count();
        $items = $base->orderBy('issueDate', 'desc')->skip($skip)->take($take)->get()
            ->map->toArray()->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Consulta pública de verificação (id, hash ou nome). Resposta em whitelist:
     * rota sem autenticação nunca expõe identificadores internos (userId/enrollmentId).
     *
     * Inclui os REVOGADOS, e é o ponto dessa rota existir.
     *
     * O certificado revogado é inativado (ADR 12), e `Certificate::query()` com
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
        $cert = Certificate::withTrashed()
            ->where(function ($q) use ($trimmed): void {
                $q->where('id', $trimmed)
                    ->orWhere('verificationHash', $trimmed)
                    ->orWhere('studentName', 'like', '%'.$trimmed.'%');
            })
            ->first();
        if ($cert === null) {
            return null;
        }

        $cargaHoraria = Course::query()->find($cert->courseId)?->cargaHoraria;

        return [
            'id' => $cert->id,
            'studentName' => $cert->studentName,
            'courseTitle' => $cert->courseTitle,
            // Acesso direto devolve Carbon (cast date) — formata explicitamente
            // porque este array não passa pela serialização do model.
            'issueDate' => $cert->issueDate?->format('d/m/Y'),
            'attendancePercent' => $cert->attendancePercent,
            'verificationHash' => $cert->verificationHash,
            'cargaHoraria' => is_numeric($cargaHoraria) ? (int) $cargaHoraria : null,
            // Estado do documento. `revogado` é o que decide o que a tela diz;
            // a data e o motivo existem para a pessoa que está com o papel na
            // mão entender por quê. Quem revogou NÃO sai daqui: rota pública não
            // expõe identificador interno de servidor.
            'revogado' => $cert->estaInativo(),
            'revogadoEm' => Fuso::local($cert->inativadoEm)?->format('d/m/Y'),
            'motivoRevogacao' => $cert->estaInativo() ? $cert->motivoInativacao : null,
        ];
    }

    /**
     * @param  array{userId?:string|null,courseId:string}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function issueCertificate(array $input, array $requester): array
    {
        $userId = Identity::resolveActorUserId($requester, $input['userId'] ?? null);

        $course = Course::query()->with(['lessons', 'liveSessions'])->find($input['courseId']);
        if ($course === null) {
            throw ApiException::notFound('Curso não encontrado.');
        }

        // Instrutor emite certificado apenas nos cursos que leciona — a mesma regra
        // que CertificatePdfService já aplica no download. Faltava aqui: quem podia
        // baixar só o próprio conseguia EMITIR para qualquer curso da escola.
        // Para o aluno o risco já era contido (a frequência é recalculada abaixo).
        if ($requester['role'] === 'instructor'
            && ! in_array($course->id, InstructorScope::courseIds($requester), true)) {
            throw ApiException::forbidden('Você só pode emitir certificados dos seus cursos.');
        }

        /*
         * Idempotência: se já existe certificado para aluno+curso, apenas retorna.
         *
         * `withTrashed()` é obrigatório aqui (armadilha 1 da ADR 12). Sem ele o
         * certificado REVOGADO não é encontrado, o `create()` abaixo segue em
         * frente e bate na UNIQUE (studentName, courseId) — erro de servidor. Ou
         * seja: revogar um certificado tornava aquele aluno impossível de
         * certificar naquele curso para sempre.
         */
        $existing = Certificate::withTrashed()
            ->where('userId', $userId)
            ->where('courseId', $input['courseId'])
            ->first();
        if ($existing !== null && ! $existing->estaInativo()) {
            return $existing->toArray();
        }
        if ($existing !== null) {
            /*
             * Revogado NÃO é reemitido por chamada de rotina.
             *
             * A UNIQUE (studentName, courseId) obriga a reemissão a reaproveitar
             * a MESMA linha, e `reativar()` limpa `inativadoPor` e
             * `motivoInativacao` — reemitir aqui apagaria o registro de que houve
             * revogação, que é justamente o que a ADR 12 manda preservar. Devolver
             * a validade a um documento revogado é decisão administrativa, e
             * precisa de fluxo próprio que registre a reabilitação.
             */
            $quando = Fuso::local($existing->inativadoEm)?->format('d/m/Y') ?? 'data não registrada';
            throw ApiException::forbidden(
                "Este certificado foi revogado em {$quando} e não é reemitido automaticamente. "
                .'A reabilitação é ato da administração e tem de ficar registrada.'
            );
        }

        $progress = StudentProgress::query()
            ->where('userId', $userId)
            ->where('courseId', $input['courseId'])
            ->first();

        $attendancePercent = $this->computeAttendancePercent($course, $progress);
        $minAttendance = BusinessRules::courseMinAttendance($course->minAttendance);

        if ($attendancePercent < $minAttendance) {
            throw ApiException::forbidden("Critério de frequência ainda não atingido para emissão do certificado ({$attendancePercent}% de {$minAttendance}% exigidos).");
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
        $pendentes = $this->avaliacoesNaoAprovadas($course->id, $userId);
        if ($pendentes !== []) {
            $quantas = count($pendentes);
            $lista = implode(', ', $pendentes);
            throw ApiException::forbidden(
                $quantas === 1
                    ? "Falta ser aprovado na avaliação \"{$lista}\" para a emissão do certificado."
                    : "Faltam {$quantas} avaliações aprovadas para a emissão do certificado: {$lista}."
            );
        }

        $enrollmentId = StudentEnrollment::query()->whereKey($userId)->value('id');

        $hashHex = strtoupper(bin2hex(random_bytes(8)));
        $certificate = Certificate::query()->create([
            'id' => "cert-{$course->id}-{$hashHex}",
            'studentName' => Identity::displayName($userId, $requester),
            'userId' => $userId,
            'enrollmentId' => $enrollmentId,
            'courseId' => $course->id,
            'courseTitle' => $course->title,
            // Coluna DATE — o cast do model serializa como d/m/Y no contrato.
            'issueDate' => CarbonImmutable::now(),
            'attendancePercent' => $attendancePercent,
            'verificationHash' => "AVA-{$hashHex}",
        ]);

        return $certificate->toArray();
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
     * 3. Aprovação é `passed` em QUALQUER tentativa, não na última: a ADR 12
     *    manda acrescentar tentativa em vez de sobrescrever, então reprovar
     *    depois de já ter sido aprovado não desfaz a aprovação.
     *
     * @return array<int, string>
     */
    private function avaliacoesNaoAprovadas(string $courseId, string $userId): array
    {
        $exigidas = Quiz::query()->where('courseId', $courseId);
        Visibilidade::aplicar($exigidas, 'student');
        /** @var array<string, string> $titulos */
        $titulos = $exigidas->pluck('title', 'id')->all();

        if ($titulos === []) {
            return [];
        }

        /** @var array<int, string> $aprovadas */
        $aprovadas = QuizSubmission::query()
            ->where('userId', $userId)
            ->where('courseId', $courseId)
            ->where('passed', true)
            ->whereIn('quizId', array_keys($titulos))
            ->pluck('quizId')
            ->all();

        $pendentes = [];
        foreach ($titulos as $quizId => $titulo) {
            if (! in_array($quizId, $aprovadas, true)) {
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
        Certificate::query()->find($id)?->inativar($porUserId, $motivo);
    }

    private function computeAttendancePercent(Course $course, ?StudentProgress $progress): int
    {
        $totalActivities = $course->lessons->count() + $course->liveSessions->count();
        if ($totalActivities === 0) {
            return 0;
        }
        // Conta apenas ids que AINDA existem no curso. Resíduo de aula apagada
        // ficava em `completedLessons` e era contado como presença — e esta é a
        // conta que decide a EMISSÃO do certificado, não um número de tela.
        $aulas = is_array($progress?->completedLessons) ? $progress->completedLessons : [];
        $encontros = is_array($progress?->attendedLiveSessions) ? $progress->attendedLiveSessions : [];
        $done = count(array_intersect(
            array_values(array_filter($aulas, static fn ($id): bool => is_string($id))),
            array_values(array_filter($course->lessons->pluck('id')->all(), static fn ($id): bool => is_string($id))),
        )) + count(array_intersect(
            array_values(array_filter($encontros, static fn ($id): bool => is_string($id))),
            array_values(array_filter($course->liveSessions->pluck('id')->all(), static fn ($id): bool => is_string($id))),
        ));

        return min(100, (int) round(($done / $totalActivities) * 100));
    }
}
