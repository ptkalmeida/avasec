<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\SolicitacaoAcademica;
use App\Support\Fuso;
use App\Support\Identity;

/**
 * Solicitações acadêmicas ("justificativas") — espelha src/server/services/requestService.ts.
 * Aluno só cria/vê as próprias; instrutor/admin acompanham todas (secretaria centralizada).
 */
final class RequestService
{
    /**
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listAcademicRequests(array $requester): array
    {
        $q = SolicitacaoAcademica::query();
        if ($requester['papel'] === 'student') {
            Identity::applyOwnRows($q, $requester);
        }

        return $q->orderBy('enviado_em_texto', 'desc')->get()->map->toArray()->all();
    }

    /**
     * @param  array{usuario_id?:string|null,tipo:string,descricao:string,titulo_curso?:string|null}  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function createAcademicRequest(array $input, array $requester): array
    {
        // Aluno solicita por si (token); staff informa o usuario_id do aluno (ADR 10).
        $usuario_id = Identity::resolveActorUserId($requester, $input['usuario_id'] ?? null);

        return SolicitacaoAcademica::query()->create([
            'id' => 'req-'.(int) round(microtime(true) * 1000),
            'nome_aluno' => Identity::displayName($usuario_id, $requester),
            'usuario_id' => $usuario_id,
            'tipo' => $input['tipo'],
            'descricao' => $input['descricao'],
            'titulo_curso' => $input['titulo_curso'] ?? null,
            'status' => 'pending',
            'enviado_em_texto' => Fuso::agora()->format('d/m/Y'),
        ])->toArray();
    }

    /** @return array<string, mixed> */
    public function updateAcademicRequestStatus(string $id, string $status): array
    {
        $req = SolicitacaoAcademica::query()->find($id);
        if ($req === null) {
            throw ApiException::notFound('Solicitação acadêmica não encontrada.');
        }
        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw ApiException::validation('Status de solicitação inválido.');
        }
        $req->status = $status;
        $req->save();

        return $req->toArray();
    }
}
