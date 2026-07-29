<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR 10 — migração de identidade nome -> FK. Passo 1: backfill.
 * Preenche as FKs de usuário nas tabelas satélites a partir do nome, SOMENTE
 * quando o nome resolve para exatamente 1 User do papel apropriado (homônimos
 * ficam intocados e são tratados na purga seguinte). Data-only; down é no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Satélites de aluno: userId por studentName (role student, nome único).
        foreach (['StudentEnrollment', 'StudentProgress', 'Certificate', 'AdmissionRequest', 'AcademicRequest', 'QuizSubmission', 'ExerciseSubmission'] as $table) {
            // Guard extra: não gravar um userId que já esteja em uso na própria
            // tabela quando houver unique (StudentEnrollment.userId é UNIQUE).
            DB::statement(<<<SQL
                UPDATE `{$table}` t
                JOIN (
                    SELECT name, MIN(id) AS id FROM `User`
                    WHERE role = 'student'
                    GROUP BY name HAVING COUNT(*) = 1
                ) u ON u.name = t.studentName
                LEFT JOIN (
                    SELECT DISTINCT userId FROM `{$table}` WHERE userId IS NOT NULL
                ) taken ON taken.userId = u.id
                SET t.userId = u.id
                WHERE t.userId IS NULL AND taken.userId IS NULL
            SQL);
        }

        // Cursos: instructorId por instructorName (staff, nome único).
        DB::statement(<<<'SQL'
            UPDATE `Course` c
            JOIN (
                SELECT name, MIN(id) AS id FROM `User`
                WHERE role IN ('instructor', 'admin')
                GROUP BY name HAVING COUNT(*) = 1
            ) u ON u.name = c.instructorName
            SET c.instructorId = u.id
            WHERE c.instructorId IS NULL
        SQL);

        // Mensagens: dono da thread (studentUserId) e remetentes (senderUserId).
        DB::statement(<<<'SQL'
            UPDATE `DirectMessage` d
            JOIN (
                SELECT name, MIN(id) AS id FROM `User`
                WHERE role = 'student'
                GROUP BY name HAVING COUNT(*) = 1
            ) u ON u.name = d.studentName
            SET d.studentUserId = u.id
            WHERE d.studentUserId IS NULL
        SQL);
        foreach (['DirectMessage', 'ChatMessage', 'ForumMessage'] as $table) {
            DB::statement(<<<SQL
                UPDATE `{$table}` t
                JOIN (
                    SELECT name, MIN(id) AS id FROM `User`
                    GROUP BY name HAVING COUNT(*) = 1
                ) u ON u.name = t.senderName
                SET t.senderUserId = u.id
                WHERE t.senderUserId IS NULL
            SQL);
        }

        // ForumMessage.likedBy: array JSON de NOMES -> array de userIds.
        // Nomes que não resolvem (ambíguos/inexistentes) são descartados do array.
        $nameToId = DB::table('User')
            ->selectRaw('name, MIN(id) as id, COUNT(*) as c')
            ->groupBy('name')
            ->havingRaw('COUNT(*) = 1')
            ->pluck('id', 'name')
            ->all();
        DB::table('ForumMessage')->orderBy('id')->chunkById(200, function ($messages) use ($nameToId): void {
            foreach ($messages as $message) {
                $likedBy = json_decode(is_string($message->likedBy) ? $message->likedBy : '[]', true);
                if (! is_array($likedBy) || $likedBy === []) {
                    continue;
                }
                $ids = [];
                foreach ($likedBy as $entry) {
                    if (! is_string($entry)) {
                        continue;
                    }
                    // Já é um id conhecido? mantém. É um nome resolvível? converte.
                    if (DB::table('User')->where('id', $entry)->exists()) {
                        $ids[] = $entry;
                    } elseif (isset($nameToId[$entry])) {
                        $ids[] = $nameToId[$entry];
                    }
                }
                DB::table('ForumMessage')->where('id', $message->id)
                    ->update(['likedBy' => json_encode(array_values(array_unique($ids)))]);
            }
        });
    }

    public function down(): void
    {
        // Backfill de dados legados não é reversível de forma segura (não há como
        // distinguir FKs preenchidas aqui das gravadas pela aplicação). No-op.
    }
};
