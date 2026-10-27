<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\SolicitacaoIngresso;
use App\Models\Curso;
use App\Models\Aula;
use App\Models\SessaoAoVivo;
use App\Models\Matricula;
use App\Models\ProgressoAluno;
use App\Support\BusinessRules;
use App\Support\CourseAccess;
use App\Support\Fuso;
use App\Support\Identity;
use App\Support\InstructorScope;
use App\Support\VigenciaDoCurso;
use App\Support\Visibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Progresso, matrícula (Matricula) e admissões (SolicitacaoIngresso) — espelha
 * src/server/services/enrollmentService.ts. Aluno só vê/edita os próprios dados;
 * instrutor fica restrito aos cursos que leciona; admin é irrestrito. Autorização
 * FK-first com fallback por nome (Identity). Penalidade de cancelamento só quando a
 * flag penalidadesCancelamento está ligada — o cliente nunca decide dias/penalidade.
 *
 * $requester = ['sub'=>id, 'nome'=>..., 'papel'=>...].
 */
final class EnrollmentService
{
    private const EMPTY_ENROLLMENT = [
        'curso_matriculado_id' => null,
        'matriculado_em_texto' => null,
        'cursos_concluidos_ids' => [],
        'penalidade_desistencia_ate' => null,
        'pode_multimatricula' => false,
        'cursos_extras_ids' => [],
    ];

    // ---------- PROGRESSO ----------

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function getProgress(?string $requestedUserId, array $requester): array
    {
        if ($requester['papel'] === 'student') {
            if ($requestedUserId !== null && $requestedUserId !== $requester['sub']) {
                throw ApiException::forbidden('Você só pode consultar o próprio progresso.');
            }

            return Identity::applyOwnRows(ProgressoAluno::query(), $requester)->get()->map->toArray()->all();
        }

        if ($requester['papel'] === 'instructor') {
            $courseIds = InstructorScope::courseIds($requester);

            return ProgressoAluno::query()
                ->whereIn('curso_id', $courseIds)
                ->when($requestedUserId !== null, fn ($q) => $q->where('usuario_id', $requestedUserId))
                ->get()->map->toArray()->all();
        }

        return ProgressoAluno::query()
            ->when($requestedUserId !== null, fn ($q) => $q->where('usuario_id', $requestedUserId))
            ->get()->map->toArray()->all();
    }

    /**
     * @param  array{usuario_id?:string|null,curso_id:string,aulas_concluidas:list<string>,sessoes_ao_vivo_assistidas:list<string>}  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function upsertProgress(array $input, array $requester): array
    {
        if ($requester['papel'] === 'instructor') {
            throw ApiException::forbidden('Instrutores não registram progresso em nome do aluno.');
        }

        /*
         * Aluno só registra progresso no curso a que PERTENCE. Os ids de aula saem no catálogo
         * público, então quem não cursava bastava mandar `curso_id` + todos os ids e, em seguida,
         * pedir o certificado com 100% de frequência (achado da varredura de segurança de
         * 23/10/2026). O Admin Superior segue podendo registrar por qualquer aluno.
         */
        if ($requester['papel'] === 'student' && ! CourseAccess::canAccess($requester, $input['curso_id'])) {
            throw ApiException::forbidden('Você não participa deste curso.');
        }

        $usuario_id = Identity::resolveActorUserId($requester, $input['usuario_id'] ?? null);
        $matricula_id = Matricula::query()->whereKey($usuario_id)->value('id');

        // Integridade acadêmica: só contam como progresso os IDs de aulas/sessões que
        // REALMENTE pertencem ao curso, sem duplicatas. Sem isto, o aluno inflava a
        // própria frequência (e forjava certificado) enviando IDs inventados/repetidos,
        // porque a frequência é recalculada a partir da contagem deste array.
        [$aulas_concluidas, $sessoes_ao_vivo_assistidas] = $this->sanitizeProgressIds(
            $input['curso_id'],
            $input['aulas_concluidas'],
            $input['sessoes_ao_vivo_assistidas'],
        );

        $existing = ProgressoAluno::query()
            ->where('usuario_id', $usuario_id)
            ->where('curso_id', $input['curso_id'])
            ->first();

        $data = [
            'nome_aluno' => Identity::displayName($usuario_id, $requester),
            'curso_id' => $input['curso_id'],
            'aulas_concluidas' => $aulas_concluidas,
            'sessoes_ao_vivo_assistidas' => $sessoes_ao_vivo_assistidas,
            'usuario_id' => $usuario_id,
            'matricula_id' => $matricula_id,
        ];

        if ($existing !== null) {
            $existing->fill($data)->save();

            return $existing->toArray();
        }

        $data['id'] = (string) Str::uuid();
        $created = ProgressoAluno::query()->create($data);

        return $created->toArray();
    }

    /**
     * Filtra as listas de progresso do cliente para conter apenas IDs de aulas
     * (Aula) e sessões ao vivo (SessaoAoVivo) que pertencem ao curso, sem
     * duplicatas. Comparação por FK apenas (ADR 10).
     *
     * @param  list<string>  $aulas_concluidas
     * @param  list<string>  $sessoes_ao_vivo_assistidas
     * @return array{0: list<string>, 1: list<string>}
     */
    private function sanitizeProgressIds(string $curso_id, array $aulas_concluidas, array $sessoes_ao_vivo_assistidas): array
    {
        $validLessonIds = array_values(array_filter(
            Aula::query()->where('curso_id', $curso_id)->pluck('id')->all(),
            static fn ($id): bool => is_string($id),
        ));
        $validSessionIds = array_values(array_filter(
            SessaoAoVivo::query()->where('curso_id', $curso_id)->pluck('id')->all(),
            static fn ($id): bool => is_string($id),
        ));

        $keepLessons = array_values(array_unique(array_intersect($aulas_concluidas, $validLessonIds)));
        $keepSessions = array_values(array_unique(array_intersect($sessoes_ao_vivo_assistidas, $validSessionIds)));

        return [$keepLessons, $keepSessions];
    }

    // ---------- MATRÍCULA ----------

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function getEnrollments(array $requester): array
    {
        if ($requester['papel'] === 'student') {
            $row = Identity::applyOwnRows(Matricula::query(), $requester)->first();

            return [
                $requester['sub'] => $row
                    ? $this->toPublicEnrollment($row)
                    : array_merge(self::EMPTY_ENROLLMENT, ['usuario_id' => $requester['sub'], 'nome_aluno' => $requester['nome']]),
            ];
        }

        // Instrutor só vê matrículas dos próprios alunos (admitidos/matriculados em seus
        // cursos); admin vê todas. Antes vazava o mapa completo para qualquer não-aluno.
        $query = Matricula::query();
        if ($requester['papel'] === 'instructor') {
            $query->whereIn('usuario_id', InstructorScope::studentIds($requester));
        }

        $map = [];
        foreach ($query->get() as $row) {
            $map[$row->usuario_id] = $this->toPublicEnrollment($row);
        }

        return $map;
    }

    /**
     * @param  array{curso_matriculado_id?:string|null,matriculado_em_texto?:string|null,cursos_concluidos_ids?:list<string>,penalidade_desistencia_ate?:string|null,pode_multimatricula?:bool}  $updates
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function upsertEnrollment(string $usuario_id, array $updates, array $requester): array
    {
        $this->assertInstructorCanManage($updates['curso_matriculado_id'] ?? null, $requester);

        // Só o Admin Superior concede matrícula múltipla concorrente — nem o
        // instrutor dono do curso pode ligar isso para os próprios alunos.
        if (array_key_exists('pode_multimatricula', $updates) && $requester['papel'] !== 'admin') {
            throw ApiException::forbidden('Apenas o Admin Superior pode conceder matrícula múltipla simultânea.');
        }

        $targetUserId = Identity::resolveActorUserId($requester, $usuario_id);
        $current = Matricula::query()->whereKey($targetUserId)->first();

        /*
         * O instrutor gerencia a matrícula no CURSO DELE, e só. Antes bastava ser dono
         * de um curso (`curso_matriculado_id`) para, na mesma chamada, marcar qualquer
         * curso como concluído para qualquer aluno (`cursos_concluidos_ids`), mexer na
         * penalidade de evasão, ou tirar o aluno do curso de OUTRO instrutor ao
         * trocá-lo para o seu. Conclusão e penalidade são registro acadêmico do
         * Admin Superior; e o aluno que está no curso alheio só sai por quem é dono
         * dele (achado da varredura de segurança de 23/10/2026).
         */
        if ($requester['papel'] !== 'admin') {
            foreach (['cursos_concluidos_ids', 'penalidade_desistencia_ate'] as $campo) {
                if (array_key_exists($campo, $updates)) {
                    throw ApiException::forbidden('Apenas o Admin Superior altera conclusões de curso e penalidades de evasão.');
                }
            }
            $cursoAtual = $current?->curso_matriculado_id;
            if (is_string($cursoAtual) && $cursoAtual !== '' && $cursoAtual !== ($updates['curso_matriculado_id'] ?? null)) {
                $this->assertInstructorCanManage($cursoAtual, $requester);
            }
        }

        $merged = array_merge(self::EMPTY_ENROLLMENT, $this->currentRest($current), $updates);

        $saved = $this->persistEnrollment($targetUserId, Identity::displayName($targetUserId, $requester), $merged);

        return $this->toPublicEnrollment($saved);
    }

    // ---------- AÇÕES DO PRÓPRIO ALUNO ----------

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array{enrollment: array<string, mixed>}
     */
    public function selfEnroll(string $curso_id, array $requester): array
    {
        $this->exigirCursoVisivelAoAluno($curso_id, $requester);
        $this->recusarCursoComVigenciaVencida($curso_id);

        $current = $this->ownEnrollment($requester);
        $cursos_extras_ids = $current->cursos_extras_ids ?? [];

        if ($this->modoDaNovaMatricula($current, $curso_id) === 'extra') {
            $merged = array_merge($this->currentRest($current), [
                'cursos_extras_ids' => array_values(array_unique([...$cursos_extras_ids, $curso_id])),
            ]);
            $saved = $this->persistEnrollment($requester['sub'], $requester['nome'], $merged);

            return ['enrollment' => $this->toPublicEnrollment($saved)];
        }

        $merged = array_merge(self::EMPTY_ENROLLMENT, [
            'cursos_concluidos_ids' => $current->cursos_concluidos_ids ?? [],
            'penalidade_desistencia_ate' => $current?->penalidade_desistencia_ate,
            'pode_multimatricula' => $current->pode_multimatricula ?? false,
            'cursos_extras_ids' => $cursos_extras_ids,
            'curso_matriculado_id' => $curso_id,
            'matriculado_em_texto' => CarbonImmutable::now()->toIso8601String(),
        ]);
        $saved = $this->persistEnrollment($requester['sub'], $requester['nome'], $merged);

        return ['enrollment' => $this->toPublicEnrollment($saved)];
    }

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array{enrollment: array<string, mixed>, penaltyApplied: bool}
     */
    public function selfDrop(string $curso_id, array $requester): array
    {
        $current = $this->ownEnrollment($requester);
        $cursos_extras_ids = $current->cursos_extras_ids ?? [];
        $isPrimary = $current?->curso_matriculado_id === $curso_id;
        $isExtra = in_array($curso_id, $cursos_extras_ids, true);
        if (! $isPrimary && ! $isExtra) {
            throw new ApiException(400, 'BAD_REQUEST', 'Você não possui matrícula ativa neste curso.');
        }

        // Cancelamento de uma matrícula EXTRA (concorrente): sem penalidade — a
        // penalidade de cancelamento tardio só se aplica ao curso principal, único
        // com `matriculado_em_texto` rastreado.
        if ($isExtra) {
            $merged = array_merge($this->currentRest($current), [
                'cursos_extras_ids' => array_values(array_filter($cursos_extras_ids, fn ($id) => $id !== $curso_id)),
            ]);
            $saved = $this->persistEnrollment($requester['sub'], $requester['nome'], $merged);

            return ['enrollment' => $this->toPublicEnrollment($saved), 'penaltyApplied' => false];
        }

        // Dias contados a partir do matriculado_em_texto REAL persistido — nunca de valor do cliente.
        $penaltyApplied = false;
        $penaltyUntil = $current->penalidade_desistencia_ate;
        if (config('features.penalidadesCancelamento') === true && $current->matriculado_em_texto) {
            $matriculado_em_texto = CarbonImmutable::parse($current->matriculado_em_texto);
            $daysEnrolled = (int) ceil($matriculado_em_texto->diffInSeconds(CarbonImmutable::now()) / 86400);
            if ($daysEnrolled > BusinessRules::dropoutPenaltyFreeDays()) {
                $penaltyApplied = true;
                $penaltyUntil = CarbonImmutable::now()->addDays(BusinessRules::dropoutPenaltyDays())->toIso8601String();
            }
        }

        $merged = array_merge($this->currentRest($current), [
            'curso_matriculado_id' => null,
            'matriculado_em_texto' => null,
            'penalidade_desistencia_ate' => $penaltyUntil,
        ]);
        $saved = $this->persistEnrollment($requester['sub'], $requester['nome'], $merged);

        return ['enrollment' => $this->toPublicEnrollment($saved), 'penaltyApplied' => $penaltyApplied];
    }

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array{enrollment: array<string, mixed>}
     */
    public function selfComplete(string $curso_id, array $requester): array
    {
        $current = $this->ownEnrollment($requester);
        $cursos_extras_ids = $current->cursos_extras_ids ?? [];
        $isPrimary = $current?->curso_matriculado_id === $curso_id;
        $isExtra = in_array($curso_id, $cursos_extras_ids, true);
        if (! $isPrimary && ! $isExtra) {
            throw new ApiException(400, 'BAD_REQUEST', 'Você não possui matrícula ativa neste curso.');
        }

        $course = Curso::query()->with(['aulas', 'sessoesAoVivo'])->find($curso_id);
        if ($course === null) {
            throw ApiException::notFound('Curso não encontrado.');
        }

        $progress = ProgressoAluno::query()
            ->where('usuario_id', $requester['sub'])->where('curso_id', $curso_id)->first();

        $totalActivities = $course->aulas->count() + $course->sessoesAoVivo->count();
        $done = count($progress->aulas_concluidas ?? []) + count($progress->sessoes_ao_vivo_assistidas ?? []);
        $attendance = $totalActivities === 0 ? 0 : min(100, (int) round(($done / $totalActivities) * 100));
        $frequencia_minima = BusinessRules::courseMinAttendance($course->frequencia_minima);
        if ($attendance < $frequencia_minima) {
            throw ApiException::forbidden("Critério de frequência ainda não atingido para concluir o curso ({$attendance}% de {$frequencia_minima}% exigidos).");
        }

        $completed = array_values(array_unique([...($current->cursos_concluidos_ids ?? []), $curso_id]));
        $merged = array_merge($this->currentRest($current), [
            'cursos_concluidos_ids' => $completed,
            ...($isExtra
                ? ['cursos_extras_ids' => array_values(array_filter($cursos_extras_ids, fn ($id) => $id !== $curso_id))]
                : ['curso_matriculado_id' => null, 'matriculado_em_texto' => null]),
        ]);
        $saved = $this->persistEnrollment($requester['sub'], $requester['nome'], $merged);

        return ['enrollment' => $this->toPublicEnrollment($saved)];
    }

    // ---------- ADMISSÕES ----------

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listAdmissions(array $requester): array
    {
        if ($requester['papel'] === 'student') {
            return Identity::applyOwnRows(SolicitacaoIngresso::query(), $requester)->get()->map->toArray()->all();
        }
        if ($requester['papel'] === 'instructor') {
            $courseIds = InstructorScope::courseIds($requester);

            return SolicitacaoIngresso::query()->whereIn('curso_id', $courseIds)->get()->map->toArray()->all();
        }

        return SolicitacaoIngresso::query()->get()->map->toArray()->all();
    }

    /**
     * @param  array{id?:string|null,usuario_id?:string|null,curso_id:string}  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function createAdmission(array $input, array $requester): array
    {
        $this->exigirCursoVisivelAoAluno($input['curso_id'], $requester);

        // Vigência vencida não aceita pedido de matrícula: com a aprovação
        // automática ligada este pedido MATRICULA na hora, e era uma porta que o
        // bloqueio de `selfEnroll` não cobria.
        $this->recusarCursoComVigenciaVencida($input['curso_id']);

        $usuario_id = Identity::resolveActorUserId($requester, $input['usuario_id'] ?? null);

        $duplicate = SolicitacaoIngresso::query()
            ->where('usuario_id', $usuario_id)
            ->where('curso_id', $input['curso_id'])
            ->where('status', 'pending')
            ->exists();
        if ($duplicate) {
            throw new ApiException(409, 'CONFLICT', 'Matrícula pendente para este curso já registrada.');
        }

        /*
         * Aprovação automática (decisão da coordenação, 14/09/2026).
         *
         * A regra vive AQUI, no serviço, e não em esconder o botão na tela: sem
         * isto os pedidos continuariam nascendo "pending" e ficariam presos para
         * sempre, porque a fila que os resolvia deixou de ser exibida.
         *
         * As travas são as mesmas do caminho normal — de propósito. Automática
         * quer dizer "sem espera humana", não "sem regra": quem já tem curso
         * ativo, já concluiu aquele curso ou está em restrição continua sendo
         * recusado, agora na hora e com a razão escrita, em vez de esperar por
         * uma aprovação que ninguém mais vai dar.
         */
        $automatica = config('features.aprovacaoAutomaticaMatricula') === true;
        $modo = 'principal';

        if ($automatica) {
            $atual = Matricula::query()->whereKey($usuario_id)->first();
            $modo = $this->modoDaNovaMatricula($atual, $input['curso_id']);
        }

        $admission = DB::transaction(function () use ($input, $usuario_id, $requester, $automatica, $modo) {
            $admission = SolicitacaoIngresso::query()->create([
                'id' => $input['id'] ?? ('adm-'.$this->nowMs()),
                'nome_aluno' => Identity::displayName($usuario_id, $requester),
                'usuario_id' => $usuario_id,
                'curso_id' => $input['curso_id'],
                'status' => $automatica ? 'approved' : 'pending',
                'enviado_em_texto' => Fuso::agora()->format('d/m/Y'),
            ]);

            // Mesma transação: "aprovada" sem matrícula efetivada não pode existir.
            if ($automatica) {
                $this->efetivarAprovacao($admission, $modo);
            }

            return $admission;
        });

        return $admission->toArray();
    }

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function updateAdmissionStatus(string $id, string $status, array $requester): array
    {
        $admission = SolicitacaoIngresso::query()->find($id);
        if ($admission === null) {
            throw ApiException::notFound('Matrícula não encontrada.');
        }

        $this->assertInstructorCanManage($admission->curso_id, $requester);

        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw ApiException::validation('Status de matrícula inválido.');
        }

        // Aprovar MATRICULA. Reprovar um pedido de curso vencido continua possível.
        if ($status === 'approved') {
            $this->recusarCursoComVigenciaVencida((string) $admission->curso_id);
        }

        // Aprovação efetiva a matrícula na MESMA transação — nunca "aprovada" sem matricular.
        $updated = DB::transaction(function () use ($admission, $status) {
            $admission->status = $status;
            $admission->save();

            if ($status === 'approved') {
                $this->efetivarAprovacao($admission, 'principal');
            }

            return $admission;
        });

        return $updated->toArray();
    }

    // ---------- helpers ----------

    /**
     * Curso com a VIGÊNCIA VENCIDA não aceita matrícula nova, por nenhuma porta.
     *
     * A licença de exibição acabou, e o painel do instrutor descreve esse estado
     * como "bloqueado no catálogo de alunos para proteção jurídica contra
     * distribuição ilegal de direitos autorais expirados". O bloqueio só existia
     * no navegador: o React filtrava a lista, e o servidor não olhava a data.
     *
     * Há TRÊS portas que matriculam — `selfEnroll`, `createAdmission` (que com a
     * aprovação automática matricula na hora) e `updateAdmissionStatus` ao
     * aprovar —, e a primeira correção cobriu só a primeira. Esta função existe
     * para a regra ter UM lugar.
     *
     * Quem JÁ está matriculado não é afetado: tirar alguém do meio de um curso
     * tem consequência acadêmica, e é decisão do responsável (plano 18 §24).
     *
     * @throws ApiException 409
     */
    /**
     * O ALUNO só se matricula em curso que o catálogo lhe mostra: existente, no ar e publicado.
     *
     * A tela web já conferia a visibilidade, mas a API não: com o id de um curso em rascunho ou
     * restrito (ou de um que nem existe), a matrícula era criada, e `CourseAccess` passava a dar ao
     * aluno acesso às provas dele — `submitQuiz` devolvia a nota de uma prova ainda não publicada
     * (achado da varredura de segurança de 23/10/2026). Gestor e admin matriculam como sempre.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     */
    private function exigirCursoVisivelAoAluno(string $curso_id, array $requester): void
    {
        if ($requester['papel'] !== 'student') {
            return;
        }

        $consulta = Curso::query()->whereKey($curso_id);
        Visibilidade::aplicar($consulta, 'student');
        if (! $consulta->exists()) {
            throw ApiException::notFound('Curso não encontrado.');
        }
    }

    private function recusarCursoComVigenciaVencida(string $curso_id): void
    {
        $curso = Curso::query()->find($curso_id, ['vigencia_ate', 'vencimento_contrato_texto']);
        if ($curso === null) {
            return;
        }

        if (VigenciaDoCurso::vencida([
            'vigencia_ate' => $curso->getAttribute('vigencia_ate'),
            'vencimento_contrato_texto' => $curso->vencimento_contrato_texto,
        ])) {
            throw new ApiException(
                409,
                'CONFLICT',
                'A vigência de exibição deste curso terminou. Procure a coordenação.',
            );
        }
    }

    /** @param array{sub:string,nome:string,papel:string} $requester */
    private function assertInstructorCanManage(?string $curso_id, array $requester): void
    {
        if ($requester['papel'] === 'admin') {
            return;
        }
        if ($requester['papel'] === 'instructor' && $curso_id) {
            // Posse por FK apenas (ADR 10): responsável ou gestor adicional do curso.
            if ((new GestoresDoCurso)->gerencia($curso_id, $requester['sub'])) {
                return;
            }
        }
        throw ApiException::forbidden('Você só pode gerenciar matrículas de cursos vinculados ao seu perfil.');
    }

    /** @param array{sub:string,nome:string,papel:string} $requester */
    private function ownEnrollment(array $requester): ?Matricula
    {
        return Identity::applyOwnRows(Matricula::query(), $requester)->first();
    }

    /**
     * As travas de TODA matrícula nova, num lugar só.
     *
     * Existe porque havia dois caminhos para matricular e apenas um tinha
     * regra: `selfEnroll` validava cinco condições, e `updateAdmissionStatus`
     * (o botão "Aprovar Acesso") não validava nenhuma — ele sobrescrevia
     * `curso_matriculado_id` direto. Com a aprovação automática ligada isso deixa
     * de ser assimetria e vira dano: o aluno pede um curso novo e o sistema o
     * tira, sem aviso e sem ninguém olhando, do curso em que ele já tem
     * progresso e frequência.
     *
     * Devolve o modo da matrícula em vez de um booleano porque o resultado não
     * é "pode ou não pode": é "entra como principal" ou "entra como simultânea"
     * — e quem chama precisa saber qual, para gravar no campo certo.
     *
     * @return 'principal'|'extra'
     */
    private function modoDaNovaMatricula(?Matricula $current, string $curso_id): string
    {
        if (! Curso::query()->whereKey($curso_id)->exists()) {
            throw ApiException::notFound('Curso não encontrado.');
        }

        if ($this->hasActivePenalty($current)) {
            throw ApiException::forbidden('Você está em período de restrição temporária de nova matrícula por cancelamento tardio. Aguarde o fim da restrição ou solicite liberação à coordenação.');
        }

        $cursos_extras_ids = $current->cursos_extras_ids ?? [];
        if ($current?->curso_matriculado_id === $curso_id || in_array($curso_id, $cursos_extras_ids, true)) {
            throw new ApiException(409, 'CONFLICT', 'Você já está matriculado neste curso.');
        }

        // Curso concluído não aceita nova matrícula. O acesso ao conteúdo continua
        // pelo modo revisão (vitalício), que não depende de matrícula ativa.
        if (in_array($curso_id, $current->cursos_concluidos_ids ?? [], true)) {
            throw new ApiException(409, 'CONFLICT', 'Você já concluiu este curso. Ele continua disponível para revisão, mas não aceita nova matrícula.');
        }

        if ($current?->curso_matriculado_id) {
            // Segunda (ou mais) matrícula simultânea: só permitido com a flag global
            // ligada E a permissão concedida pelo Admin Superior a este aluno.
            if (config('features.matriculasMultiplas') !== true || $current->pode_multimatricula !== true) {
                throw new ApiException(409, 'CONFLICT', 'Você já possui uma matrícula ativa. Conclua ou cancele o curso atual antes de iniciar outro.');
            }

            return 'extra';
        }

        return 'principal';
    }

    /**
     * Efetiva a matrícula de uma solicitação aprovada.
     *
     * Separado de `updateAdmissionStatus` porque a aprovação automática precisa
     * exatamente do mesmo efeito: "aprovada" sem matricular é um estado que não
     * pode existir — o aluno veria acesso liberado na tela e nenhuma matrícula
     * no registro.
     */
    private function efetivarAprovacao(SolicitacaoIngresso $admission, string $modo): void
    {
        $current = Matricula::query()->whereKey($admission->usuario_id)->first();

        $merged = $modo === 'extra'
            ? array_merge($this->currentRest($current), [
                'cursos_extras_ids' => array_values(array_unique([
                    ...($current->cursos_extras_ids ?? []),
                    $admission->curso_id,
                ])),
            ])
            : array_merge(self::EMPTY_ENROLLMENT, $this->currentRest($current), [
                'curso_matriculado_id' => $admission->curso_id,
                'matriculado_em_texto' => CarbonImmutable::now()->toIso8601String(),
                'penalidade_desistencia_ate' => null,
            ]);

        $this->persistEnrollment($admission->usuario_id, $admission->nome_aluno, $merged);
    }

    private function hasActivePenalty(?Matricula $row): bool
    {
        if (config('features.penalidadesCancelamento') !== true) {
            return false;
        }
        if (! $row?->penalidade_desistencia_ate) {
            return false;
        }
        try {
            return CarbonImmutable::parse($row->penalidade_desistencia_ate)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Upsert keyed pela PK usuario_id (ADR 10); nome_aluno é apenas snapshot de exibição.
     *
     * @param  array<string, mixed>  $merged
     */
    private function persistEnrollment(string $usuario_id, string $nome_aluno, array $merged): Matricula
    {
        $existing = Matricula::query()->whereKey($usuario_id)->first();
        if ($existing !== null) {
            $existing->fill(array_merge($merged, ['nome_aluno' => $nome_aluno]))->save();

            return $existing;
        }

        return Matricula::query()->create(array_merge($merged, [
            'nome_aluno' => $nome_aluno,
            'id' => (string) Str::uuid(),
            'usuario_id' => $usuario_id,
        ]));
    }

    /**
     * Campos da matrícula atual sem nome_aluno/id/usuario_id (para o merge de atualização).
     *
     * @return array<string, mixed>
     */
    private function currentRest(?Matricula $current): array
    {
        if ($current === null) {
            return [];
        }
        $rest = $current->only([
            'curso_matriculado_id', 'matriculado_em_texto', 'cursos_concluidos_ids', 'penalidade_desistencia_ate',
            'pode_multimatricula', 'cursos_extras_ids',
        ]);

        return $rest;
    }

    /**
     * Forma pública da matrícula. Desde a ADR 10 inclui usuario_id (identidade) e
     * nome_aluno (display) para o frontend indexar por id.
     *
     * @return array<string, mixed>
     */
    private function toPublicEnrollment(Matricula $row): array
    {
        return [
            'usuario_id' => $row->usuario_id,
            'nome_aluno' => $row->nome_aluno,
            'curso_matriculado_id' => $row->curso_matriculado_id,
            'matriculado_em_texto' => $row->matriculado_em_texto,
            'cursos_concluidos_ids' => $row->cursos_concluidos_ids ?? [],
            'penalidade_desistencia_ate' => $row->penalidade_desistencia_ate,
            'pode_multimatricula' => (bool) $row->pode_multimatricula,
            'cursos_extras_ids' => $row->cursos_extras_ids ?? [],
        ];
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
