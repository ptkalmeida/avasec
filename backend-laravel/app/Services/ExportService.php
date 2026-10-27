<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\ProgressoAluno;
use App\Models\Usuario;

/**
 * Exportação de Dados Gerenciais (restrita a admin) — espelha
 * src/server/services/exportService.ts. Só as bases autorizadas; NUNCA inclui
 * senha_hash ou qualquer dado de autenticação.
 */
final class ExportService
{
    public const EXPORTABLE = ['students', 'courses', 'enrollments', 'progress', 'certificates'];

    /** @return array<int, array<string, mixed>> */
    public function exportDataset(string $dataset): array
    {
        return match ($dataset) {
            'students' => Usuario::query()->where('papel', 'student')
                ->get(['id', 'nome', 'email', 'status', 'municipio', 'uf', 'area_interesse', 'data_cadastro_texto', 'criado_em'])
                ->map->toArray()->all(),
            'courses' => Curso::query()
                ->get(['id', 'titulo', 'categoria', 'area_tematica', 'nome_instrutor', 'instrutor_id', 'carga_horaria', 'modalidade', 'nivel', 'status_curso', 'emite_certificado', 'frequencia_minima'])
                ->map->toArray()->all(),
            'enrollments' => Matricula::query()->get()->map->toArray()->all(),
            'progress' => ProgressoAluno::query()->get()->map->toArray()->all(),
            'certificates' => Certificado::query()->get()->map->toArray()->all(),
            default => throw ApiException::validation('Base de dados não reconhecida.'),
        };
    }
}
