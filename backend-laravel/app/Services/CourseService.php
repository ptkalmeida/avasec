<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonDocument;
use App\Models\LiveSession;
use App\Models\StudentProgress;
use App\Models\User;
use App\Support\BusinessRules;
use App\Support\CourseAccess;
use App\Support\Payload;
use App\Support\VideoSource;
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
     * aula, videoUrl, documentos e o link do Meet — para qualquer visitante sem
     * login. O que era indevido não é a rota ser aberta: é o tamanho do payload.
     *
     * Vitrine (todos): título, descrição, categoria, capa, carga horária, instrutor,
     * e a lista de aulas apenas com título/duração/ordem — o programa do curso é
     * argumento de matrícula, não material.
     * Completo: admin; instrutor nos cursos que leciona; aluno nos cursos a que
     * pertence (matriculado, concluído ou admissão aprovada) — via CourseAccess,
     * a mesma fonte usada pelo chat e pelos certificados.
     *
     * @param  array{sub:string,name:string,role:string}|null  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listCoursesFor(?array $requester): array
    {
        $irrestrito = $requester !== null && $requester['role'] === 'admin';
        $liberados = ($requester === null || $irrestrito)
            ? []
            : CourseAccess::accessibleCourseIds($requester);

        $catalogo = [];
        foreach (Course::query()->with($this->courseInclude())->get() as $course) {
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
        if (is_array($curso['lessons'] ?? null)) {
            $curso['lessons'] = array_map(static function (mixed $aula): mixed {
                if (! is_array($aula)) {
                    return $aula;
                }
                $aula['content'] = '';
                $aula['videoUrl'] = null;
                $aula['documents'] = [];

                return $aula;
            }, $curso['lessons']);
        }

        if (is_array($curso['liveSessions'] ?? null)) {
            $curso['liveSessions'] = array_map(static function (mixed $sessao): mixed {
                if (! is_array($sessao)) {
                    return $sessao;
                }
                // Link do Meet é a chave da sala: com ele, qualquer um entra na aula.
                $sessao['meetingLink'] = '';

                return $sessao;
            }, $curso['liveSessions']);
        }

        return $curso;
    }

    /** @return array<string, mixed> */
    public function getCourseById(string $id): array
    {
        $course = Course::query()->with($this->courseInclude())->find($id);
        if ($course === null) {
            throw ApiException::notFound('Curso não encontrado.');
        }

        return $course->toArray();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createCourse(array $input, array $requester): array
    {
        $lessons = Payload::assocList($input['lessons'] ?? []);
        $liveSessions = Payload::assocList($input['liveSessions'] ?? []);
        unset($input['lessons'], $input['liveSessions']);

        // Instrutor só cria curso em seu próprio nome; admin pode atribuir a outro
        // instrutor por userId (ADR 10) — instructorName é sempre derivado (display).
        if (($requester['role'] ?? null) === 'admin'
            && is_string($input['instructorId'] ?? null)
            && $input['instructorId'] !== ''
            && $input['instructorId'] !== $requester['sub']) {
            $instructorId = $input['instructorId'];
            $instructorName = User::query()
                ->where('id', $instructorId)
                ->whereIn('role', ['instructor', 'admin'])
                ->value('name');
            if (! is_string($instructorName)) {
                throw ApiException::notFound('Instrutor não encontrado.');
            }
        } else {
            $instructorName = $requester['name'];
            $instructorId = $requester['sub'];
        }

        $id = is_string($input['id'] ?? null) ? $input['id'] : ('course-'.$this->nowMs());

        DB::transaction(function () use ($input, $id, $instructorName, $instructorId, $lessons, $liveSessions): void {
            $courseData = $this->scalarCourseData($input);
            $courseData['id'] = $id;
            $courseData['instructorName'] = $instructorName;
            $courseData['instructorId'] = $instructorId;
            Course::query()->create($courseData);

            foreach ($lessons as $lesson) {
                $this->createLessonWithDocuments($id, $lesson);
            }
            foreach ($liveSessions as $session) {
                LiveSession::query()->create($this->liveSessionData($session, $id));
            }
        });

        return $this->getCourseById($id);
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function updateCourse(string $courseId, array $updates, array $requester): array
    {
        $this->assertCourseOwnership($courseId, $requester);

        $lessons = array_key_exists('lessons', $updates) ? Payload::assocList($updates['lessons']) : null;
        $liveSessions = array_key_exists('liveSessions', $updates) ? Payload::assocList($updates['liveSessions']) : null;
        unset($updates['lessons'], $updates['liveSessions']);

        // Reatribuição de autoria: só admin, endereçada por userId; o nome de
        // exibição é derivado do User para nunca divergir da FK (ADR 10).
        $instructorIdUpdate = [];
        if (is_string($updates['instructorId'] ?? null) && $updates['instructorId'] !== '') {
            if (($requester['role'] ?? null) !== 'admin') {
                throw ApiException::forbidden('Somente administradores podem reatribuir a autoria de um curso.');
            }
            $newName = User::query()
                ->where('id', $updates['instructorId'])
                ->whereIn('role', ['instructor', 'admin'])
                ->value('name');
            if (! is_string($newName)) {
                throw ApiException::notFound('Instrutor não encontrado.');
            }
            $instructorIdUpdate = ['instructorId' => $updates['instructorId'], 'instructorName' => $newName];
        }

        DB::transaction(function () use ($courseId, $updates, $instructorIdUpdate, $lessons, $liveSessions): void {
            $scalar = array_merge($this->scalarCourseData($updates), $instructorIdUpdate);
            if (count($scalar) > 0) {
                Course::query()->where('id', $courseId)->update($scalar);
            }
            if ($lessons !== null) {
                $this->syncLessons($courseId, $lessons);
            }
            if ($liveSessions !== null) {
                $this->syncLiveSessions($courseId, $liveSessions);
            }
            if ($lessons !== null || $liveSessions !== null) {
                $this->limparProgressoOrfao($courseId);
            }
        });

        return $this->getCourseById($courseId);
    }

    /** @param array{sub:string,name:string,role:string} $requester */
    public function deleteCourse(string $courseId, array $requester): void
    {
        $this->assertCourseOwnership($courseId, $requester);
        // FK onDelete: Cascade no banco remove aulas/documentos/sessões atomicamente.
        Course::query()->where('id', $courseId)->delete();
    }

    /** @param array{sub:string,name:string,role:string} $requester */
    private function assertCourseOwnership(string $courseId, array $requester): void
    {
        $course = Course::query()->find($courseId, ['instructorName', 'instructorId']);
        if ($course === null) {
            throw ApiException::notFound('Curso não encontrado.');
        }
        if (($requester['role'] ?? null) === 'admin') {
            return;
        }
        // Posse por FK apenas (ADR 10) — nome nunca é identidade.
        $owns = $course->instructorId !== null && $course->instructorId === $requester['sub'];
        if (($requester['role'] ?? null) === 'instructor' && $owns) {
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
    private function limparProgressoOrfao(string $courseId): void
    {
        $aulasValidas = $this->somenteStrings(Lesson::query()->where('courseId', $courseId)->pluck('id')->all());
        $encontrosValidos = $this->somenteStrings(LiveSession::query()->where('courseId', $courseId)->pluck('id')->all());

        foreach (StudentProgress::query()->where('courseId', $courseId)->get() as $registro) {
            $aulas = $this->somenteStrings(is_array($registro->completedLessons) ? $registro->completedLessons : []);
            $encontros = $this->somenteStrings(is_array($registro->attendedLiveSessions) ? $registro->attendedLiveSessions : []);

            $aulasLimpas = array_values(array_intersect($aulas, $aulasValidas));
            $encontrosLimpos = array_values(array_intersect($encontros, $encontrosValidos));

            if ($aulasLimpas === $aulas && $encontrosLimpos === $encontros) {
                continue;
            }

            $registro->completedLessons = $aulasLimpas;
            $registro->attendedLiveSessions = $encontrosLimpos;
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
    private function syncLessons(string $courseId, array $lessons): void
    {
        $keptIds = array_values(array_filter(array_map(fn ($l) => $l['id'] ?? null, $lessons)));
        Lesson::query()->where('courseId', $courseId)
            ->when(count($keptIds) > 0, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();

        foreach ($lessons as $lesson) {
            $lessonId = is_string($lesson['id'] ?? null)
                ? $lesson['id']
                : ('lesson-'.$courseId.'-'.$this->nowMs().'-'.Str::lower(Str::random(4)));
            $data = $this->lessonScalar($lesson, $courseId);
            $existing = Lesson::query()->find($lessonId);
            if ($existing !== null) {
                $existing->fill($data)->save();
            } else {
                $data['id'] = $lessonId;
                Lesson::query()->create($data);
            }

            $documents = Payload::assocList($lesson['documents'] ?? []);
            $keptDocIds = array_values(array_filter(array_map(fn ($d) => $d['id'] ?? null, $documents)));
            LessonDocument::query()->where('lessonId', $lessonId)
                ->when(count($keptDocIds) > 0, fn ($q) => $q->whereNotIn('id', $keptDocIds))
                ->delete();
            foreach ($documents as $doc) {
                $docId = is_string($doc['id'] ?? null)
                    ? $doc['id']
                    : ('doc-'.$lessonId.'-'.$this->nowMs().'-'.Str::lower(Str::random(4)));
                $docData = $this->documentScalar($doc, $lessonId);
                $existingDoc = LessonDocument::query()->find($docId);
                if ($existingDoc !== null) {
                    $existingDoc->fill($docData)->save();
                } else {
                    $docData['id'] = $docId;
                    LessonDocument::query()->create($docData);
                }
            }
        }
    }

    /** @param list<array<string, mixed>> $liveSessions */
    private function syncLiveSessions(string $courseId, array $liveSessions): void
    {
        $keptIds = array_values(array_filter(array_map(fn ($s) => $s['id'] ?? null, $liveSessions)));
        LiveSession::query()->where('courseId', $courseId)
            ->when(count($keptIds) > 0, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();

        foreach ($liveSessions as $session) {
            $sessionId = is_string($session['id'] ?? null)
                ? $session['id']
                : ('live-'.$courseId.'-'.$this->nowMs().'-'.Str::lower(Str::random(4)));
            $data = $this->liveSessionData($session, $courseId);
            $data['id'] = $sessionId;
            $existing = LiveSession::query()->find($sessionId);
            if ($existing !== null) {
                unset($data['id']);
                $existing->fill($data)->save();
            } else {
                LiveSession::query()->create($data);
            }
        }
    }

    /** @param array<string, mixed> $lesson */
    private function createLessonWithDocuments(string $courseId, array $lesson): void
    {
        $lessonId = is_string($lesson['id'] ?? null)
            ? $lesson['id']
            : ('lesson-'.$courseId.'-'.$this->nowMs().'-'.Str::lower(Str::random(4)));
        $data = $this->lessonScalar($lesson, $courseId);
        $data['id'] = $lessonId;
        Lesson::query()->create($data);
        foreach (Payload::assocList($lesson['documents'] ?? []) as $doc) {
            $docData = $this->documentScalar($doc, $lessonId);
            $docData['id'] = is_string($doc['id'] ?? null)
                ? $doc['id']
                : ('doc-'.$lessonId.'-'.$this->nowMs().'-'.Str::lower(Str::random(4)));
            LessonDocument::query()->create($docData);
        }
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @return array<string, mixed>
     */
    private function lessonScalar(array $lesson, string $courseId): array
    {
        return [
            'courseId' => $courseId,
            'title' => $lesson['title'],
            'duration' => $lesson['duration'],
            // Persistimos a forma canônica (ADR 08) — youtu.be/embed/shorts viram watch?v=.
            'videoUrl' => is_string($lesson['videoUrl'] ?? null)
                ? VideoSource::tryParse($lesson['videoUrl'])?->canonicalUrl
                : null,
            'content' => $lesson['content'] ?? null,
            'lesson_order' => is_numeric($lesson['order'] ?? null) ? (int) $lesson['order'] : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    private function documentScalar(array $doc, string $lessonId): array
    {
        return [
            'lessonId' => $lessonId,
            'title' => $doc['title'],
            'type' => $doc['type'],
            'url' => $doc['url'],
            'size' => $doc['size'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function liveSessionData(array $session, string $courseId): array
    {
        return [
            'id' => $session['id'] ?? ('live-'.$courseId.'-'.$this->nowMs().'-'.Str::lower(Str::random(4))),
            'courseId' => $courseId,
            'title' => $session['title'],
            'scheduledAt' => $session['scheduledAt'],
            'durationMinutes' => is_numeric($session['durationMinutes'] ?? null) ? (int) $session['durationMinutes'] : 0,
            'meetingLink' => $session['meetingLink'],
            // Encontro que a regra das 24h já encerrou não pode voltar a ser
            // marcado como ao vivo: o painel não oferece mais o botão, mas o
            // servidor é quem tem de garantir — um PUT direto reabriria a sala.
            'isLive' => (bool) ($session['isLive'] ?? false)
                && ! BusinessRules::liveSessionExpired(
                    is_string($session['scheduledAt'] ?? null) ? $session['scheduledAt'] : null
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
        // instructorName/instructorId ficam FORA da lista: a autoria só muda pelo
        // fluxo de reatribuição por userId (ADR 10), nunca por campo solto.
        $allowed = [
            'title', 'description', 'category', 'thumbnail', 'coverImage',
            'courseType', 'hasChat', 'minAttendance', 'contractExpirationDate', 'areaTematica',
            'cargaHoraria', 'modalidade', 'nivel', 'emiteCertificado', 'statusCurso',
        ];

        return array_intersect_key($input, array_flip($allowed));
    }

    /** @return array<int|string, string|(\Closure(\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>): mixed)> */
    private function courseInclude(): array
    {
        return [
            'lessons' => fn ($q) => $q->orderBy('lesson_order'),
            'lessons.documents',
            'liveSessions',
        ];
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
