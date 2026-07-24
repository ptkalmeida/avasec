<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\StudentEnrollment;
use App\Models\StudentProgress;
use App\Models\User;

/**
 * Exportação de Dados Gerenciais (restrita a admin) — espelha
 * src/server/services/exportService.ts. Só as bases autorizadas; NUNCA inclui
 * passwordHash ou qualquer dado de autenticação.
 */
final class ExportService
{
    public const EXPORTABLE = ['students', 'courses', 'enrollments', 'progress', 'certificates'];

    /** @return array<int, array<string, mixed>> */
    public function exportDataset(string $dataset): array
    {
        return match ($dataset) {
            'students' => User::query()->where('role', 'student')
                ->get(['id', 'name', 'email', 'status', 'municipio', 'uf', 'areaInteresse', 'dataCadastro', 'createdAt'])
                ->map->toArray()->all(),
            'courses' => Course::query()
                ->get(['id', 'title', 'category', 'areaTematica', 'instructorName', 'instructorId', 'cargaHoraria', 'modalidade', 'nivel', 'statusCurso', 'emiteCertificado', 'minAttendance'])
                ->map->toArray()->all(),
            'enrollments' => StudentEnrollment::query()->get()->map->toArray()->all(),
            'progress' => StudentProgress::query()->get()->map->toArray()->all(),
            'certificates' => Certificate::query()->get()->map->toArray()->all(),
            default => throw ApiException::validation('Base de dados não reconhecida.'),
        };
    }
}
