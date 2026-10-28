<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\HistoricoEmitido;
use App\Models\Usuario;
use App\Support\Fuso;
use Carbon\CarbonImmutable;

/**
 * Histórico escolar emitido pelo admin. O aluno só LÊ o que foi emitido para ele.
 *
 * Os cursos do histórico são os que o aluno concluiu com certificado emitido: é o
 * único registro de conclusão que o banco tem (carga horária, frequência e data).
 * Nota por disciplina não existe como dado consolidado, então não entra — nada de
 * campo inventado.
 */
final class HistoricosEmitidos
{
    /**
     * @param  array{sub:string,nome:string,papel:string}  $admin
     */
    public function emitir(string $alunoId, array $admin): HistoricoEmitido
    {
        if ($admin['papel'] !== 'admin') {
            throw ApiException::forbidden('Apenas o Admin Superior emite o histórico escolar.');
        }

        $aluno = Usuario::query()->where('papel', 'student')->find($alunoId);
        if ($aluno === null) {
            throw ApiException::notFound('Aluno não encontrado.');
        }

        $certificados = Certificado::query()->where('usuario_id', $alunoId)->orderBy('data_emissao')->get();
        if ($certificados->isEmpty()) {
            throw ApiException::validation('Este aluno ainda não concluiu nenhum curso com certificado: não há o que registrar no histórico.');
        }

        $cargas = Curso::query()->withTrashed()->whereIn('id', $certificados->pluck('curso_id'))->pluck('carga_horaria', 'id');

        $linhas = [];
        foreach ($certificados as $cert) {
            $carga = $cargas[$cert->curso_id] ?? null;
            $linhas[] = [
                'curso' => (string) $cert->titulo_curso,
                'carga_horaria' => is_numeric($carga) ? (int) $carga.' horas' : '—',
                'frequencia' => $cert->percentual_frequencia.'%',
                'conclusao' => $cert->data_emissao?->format('d/m/Y') ?? '—',
            ];
        }

        return HistoricoEmitido::query()->create([
            'usuario_id' => $alunoId,
            'emitido_por' => $admin['sub'],
            'emitido_em' => CarbonImmutable::now(),
            'dados' => ['nome_aluno' => (string) $aluno->nome, 'linhas' => $linhas],
        ]);
    }

    /**
     * Emissões ativas do aluno, a mais recente primeiro.
     *
     * @return list<array{id: int, emitido_em: string, cursos: int}>
     */
    public function doAluno(string $alunoId): array
    {
        $lista = [];
        foreach (HistoricoEmitido::query()->where('usuario_id', $alunoId)->orderByDesc('emitido_em')->orderByDesc('id')->get() as $h) {
            $lista[] = [
                'id' => $h->id,
                'emitido_em' => $h->emitido_em->setTimezone(Fuso::exibicao())->format('d/m/Y H:i'),
                'cursos' => count($h->dados['linhas'] ?? []),
            ];
        }

        return $lista;
    }
}
