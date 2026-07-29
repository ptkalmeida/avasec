<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ADR 10 — migração de identidade nome -> FK. Passo 2: purga.
 * Remove linhas satélites cuja FK de usuário não pôde ser resolvida pelo
 * backfill (nomes de seed sem User correspondente ou homônimos). Necessário
 * para os NOT NULL/PK das migrations seguintes. EXCEÇÃO: Certificate é
 * preservada (documento histórico; verify público funciona por hash+nome).
 * Contagens vão para o log para auditoria. Down é no-op (dados descartáveis
 * de dev; não há produção — ADR 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        $purged = [];
        foreach (['StudentEnrollment', 'StudentProgress', 'AdmissionRequest', 'AcademicRequest', 'QuizSubmission', 'ExerciseSubmission'] as $table) {
            $purged[$table] = DB::table($table)->whereNull('userId')->delete();
        }
        $purged['DirectMessage'] = DB::table('DirectMessage')->whereNull('studentUserId')->delete();

        Log::warning('[ADR10] Purga de linhas legadas sem FK resolvível', $purged);
    }

    public function down(): void
    {
        // Irreversível por design (linhas deletadas eram inutilizáveis: nenhum
        // usuário real conseguia autenticar como elas).
    }
};
