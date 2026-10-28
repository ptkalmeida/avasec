<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\MensagemChat;
use App\Models\MensagemDireta;
use App\Models\SessaoAoVivo;
use App\Support\CourseAccess;
use App\Support\Identity;
use App\Support\InstructorScope;
use Carbon\CarbonImmutable;

/**
 * Chat de aulas ao vivo e mensagens diretas — espelha src/server/services/messagingService.ts.
 * nome_remetente/papel_remetente sempre da identidade autenticada. Leitura de DMs do aluno usa FK-first
 * com fallback por nome.
 */
final class MessagingService
{
    /**
     * Chat de aula ao vivo escopado por curso (ADR 10): o requester só lê o chat de
     * sessões de cursos aos quais pertence. Antes, qualquer autenticado enumerava
     * sessao_id e lia/escrevia em turmas alheias.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listChatMessages(?string $sessao_id, array $requester): array
    {
        if ($sessao_id !== null) {
            $curso_id = SessaoAoVivo::query()->whereKey($sessao_id)->value('curso_id');
            if (! is_string($curso_id) || ! CourseAccess::canAccess($requester, $curso_id)) {
                return [];
            }

            // Ordena pelo instante nativo, não pelo texto ISO. O `id` desempata
            // mensagens do mesmo segundo e mantém a ordem estável; linha sem
            // `enviada_em` (texto não reconhecível) é tratada como a mais antiga.
            return MensagemChat::query()->where('sessao_id', $sessao_id)
                ->orderBy('enviada_em')->orderBy('id')->get()->map->toArray()->all();
        }

        // Sem sessao_id: admin vê tudo; demais recebem só o chat das sessões de cursos
        // a que têm acesso (o frontend carrega o chat em massa e filtra por sessão).
        $query = MensagemChat::query();
        if ($requester['papel'] !== 'admin') {
            $accessibleSessionIds = $this->accessibleSessionIds($requester);
            $query->whereIn('sessao_id', $accessibleSessionIds);
        }

        return $query->orderBy('enviada_em')->orderBy('id')->get()->map->toArray()->all();
    }

    /**
     * @param  array{sessao_id:string,texto:string}  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function createChatMessage(array $input, array $requester): array
    {
        $curso_id = SessaoAoVivo::query()->whereKey($input['sessao_id'])->value('curso_id');
        if (! is_string($curso_id) || ! CourseAccess::canAccess($requester, $curso_id)) {
            throw ApiException::forbidden('Você não participa desta turma.');
        }

        return MensagemChat::query()->create([
            'id' => 'chat-'.$this->nowMs(),
            'sessao_id' => $input['sessao_id'],
            'nome_remetente' => $requester['nome'],
            'remetente_usuario_id' => $requester['sub'],
            'papel_remetente' => $requester['papel'],
            'texto' => $input['texto'],
            'registrado_em_texto' => CarbonImmutable::now()->toIso8601String(),
            // Mesmo instante em coluna nativa (Norma C.2.4). `timestamp` segue sendo
            // o texto ISO que a API já entrega; `enviada_em` é o eixo de ordenação,
            // que texto não dá de graça.
            'enviada_em' => CarbonImmutable::now()->utc(),
        ])->toArray();
    }

    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listDirectMessages(array $requester, ?string $aluno_usuario_id): array
    {
        if ($requester['papel'] === 'student') {
            return Identity::applyOwnRows(MensagemDireta::query(), $requester, 'aluno_usuario_id')
                ->orderBy('enviada_em')->orderBy('id')->get()->map->toArray()->all();
        }

        // Instrutor só lê DMs dos próprios alunos; admin, todas. Antes qualquer instrutor
        // fazia dump de toda a caixa de mensagens diretas da escola.
        $query = MensagemDireta::query();
        if ($requester['papel'] === 'instructor') {
            $scoped = InstructorScope::studentIds($requester);
            if ($aluno_usuario_id !== null) {
                $scoped = in_array($aluno_usuario_id, $scoped, true) ? [$aluno_usuario_id] : [];
            }
            $query->whereIn('aluno_usuario_id', $scoped);
        } elseif ($aluno_usuario_id !== null) {
            $query->where('aluno_usuario_id', $aluno_usuario_id);
        }

        return $query->orderBy('enviada_em')->orderBy('id')->get()->map->toArray()->all();
    }

    /**
     * @param  array{aluno_usuario_id?:string|null,texto:string}  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function createDirectMessage(array $input, array $requester): array
    {
        // Aluno só escreve no próprio canal; staff endereça o aluno por usuario_id (ADR 10).
        $aluno_usuario_id = Identity::resolveActorUserId($requester, $input['aluno_usuario_id'] ?? null);

        /*
         * O instrutor escreve para os alunos DELE — o mesmo escopo que a
         * leitura aplica logo acima (`InstructorScope::studentIds`).
         *
         * A assimetria era antiga: ler era escopado, escrever não. Qualquer
         * instrutor autenticado podia mandar mensagem a qualquer aluno da
         * escola, inclusive de turma que não é dele, e a mensagem aparecia na
         * caixa daquele aluno com o nome dele. Nada no produto pedia isso: o
         * gestor responde quem o procurou.
         *
         * É mudança de regra de autorização, e foi decidida com o responsável
         * em 21/10, junto com a tela de resposta (plano 18 §23).
         */
        if ($requester['papel'] === 'instructor'
            && ! in_array($aluno_usuario_id, InstructorScope::studentIds($requester), true)) {
            throw ApiException::forbidden('Você só pode escrever para alunos dos seus cursos.');
        }

        return MensagemDireta::query()->create([
            'id' => 'dm-'.$this->nowMs(),
            'nome_aluno' => Identity::displayName($aluno_usuario_id, $requester),
            'aluno_usuario_id' => $aluno_usuario_id,
            'nome_remetente' => $requester['nome'],
            'remetente_usuario_id' => $requester['sub'],
            'papel_remetente' => $requester['papel'],
            'texto' => $input['texto'],
            'registrado_em_texto' => CarbonImmutable::now()->toIso8601String(),
            // Ver a nota em createChatMessage: coluna nativa ao lado do texto.
            'enviada_em' => CarbonImmutable::now()->utc(),
        ])->toArray();
    }

    /**
     * IDs das sessões ao vivo dos cursos acessíveis ao requester (não-admin).
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return list<string>
     */
    private function accessibleSessionIds(array $requester): array
    {
        $courseIds = CourseAccess::accessibleCourseIds($requester);
        if (count($courseIds) === 0) {
            return [];
        }

        return array_values(array_filter(
            SessaoAoVivo::query()->whereIn('curso_id', $courseIds)->pluck('id')->all(),
            static fn ($id): bool => is_string($id),
        ));
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
