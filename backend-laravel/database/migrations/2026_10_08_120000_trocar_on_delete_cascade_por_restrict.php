<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tira o `ON DELETE CASCADE` das 12 chaves estrangeiras que ainda o tinham.
 *
 * Por que isto existe: o ADR 12 ("nada é apagado") nasceu justamente do
 * levantamento em que apagar UM usuário destruía, em cascata, todas as notas,
 * entregas, progresso e matrícula dele — deixando o certificado apontando para
 * ninguém. O código foi corrigido na época (todo apagar de domínio passa pelo
 * trait `Inativavel`), mas a trava ficou só na aplicação: o banco continuou
 * aceitando a destruição se alguém executasse um DELETE por fora.
 *
 * A Norma Técnica do Protocolo TI-SECEC diz o mesmo em C.2.2: "não deverá ser
 * utilizado ON DELETE CASCADE como mecanismo padrão para exclusão de dados de
 * negócio; a integridade funcional deverá ser controlada pela aplicação".
 *
 * Com RESTRICT o banco passa a recusar o DELETE físico do pai. Nenhum fluxo da
 * aplicação muda, porque nenhum fluxo apaga fisicamente: conferido em 08/10/2026
 * que não há `forceDelete`, `truncate` nem DELETE cru em `app/`, e que User,
 * Course, Lesson, Quiz e PracticalExercise usam `Inativavel`.
 *
 * `ON UPDATE CASCADE` é mantido: a identidade é por FK (ADR 10) e a renomeação
 * segura depende dele.
 *
 * Momento: C.2.5 proíbe alteração estrutural destrutiva depois que a estrutura
 * estiver em HML ou PRD. O AVASEC ainda não foi implantado — esta é a janela
 * barata. Depois, viraria procedimento de saneamento, fora do ciclo de deploy.
 */
return new class extends Migration
{
    /**
     * As 12 FKs, conferidas em `information_schema` em 25/09 e de novo em 08/10.
     *
     * @var list<array{tabela: string, coluna: string, referencia: string}>
     */
    private const CHAVES = [
        ['tabela' => 'AcademicRequest',   'coluna' => 'userId',        'referencia' => 'User'],
        ['tabela' => 'AdmissionRequest',  'coluna' => 'userId',        'referencia' => 'User'],
        ['tabela' => 'DirectMessage',     'coluna' => 'studentUserId', 'referencia' => 'User'],
        ['tabela' => 'ExerciseSubmission', 'coluna' => 'exerciseId',   'referencia' => 'PracticalExercise'],
        ['tabela' => 'ExerciseSubmission', 'coluna' => 'userId',       'referencia' => 'User'],
        ['tabela' => 'Lesson',            'coluna' => 'courseId',      'referencia' => 'Course'],
        ['tabela' => 'LessonDocument',    'coluna' => 'lessonId',      'referencia' => 'Lesson'],
        ['tabela' => 'LiveSession',       'coluna' => 'courseId',      'referencia' => 'Course'],
        ['tabela' => 'QuizQuestion',      'coluna' => 'quizId',        'referencia' => 'Quiz'],
        ['tabela' => 'QuizSubmission',    'coluna' => 'userId',        'referencia' => 'User'],
        ['tabela' => 'StudentEnrollment', 'coluna' => 'userId',        'referencia' => 'User'],
        ['tabela' => 'StudentProgress',   'coluna' => 'userId',        'referencia' => 'User'],
    ];

    public function up(): void
    {
        $this->recriar('restrict');
    }

    /**
     * Volta ao CASCADE. Existe para a migration ser reversível em DEV; não é
     * caminho de rollback em produção — C.2.6 veda `migrate:rollback` automático
     * como consequência de rollback da aplicação.
     */
    public function down(): void
    {
        $this->recriar('cascade');
    }

    private function recriar(string $aoApagar): void
    {
        // SQLite (usado nos testes) não altera FK por ALTER TABLE, e também não
        // aplica a regra do mesmo jeito. A trava que importa é a do MySQL, que é
        // onde o dado real vive.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::CHAVES as $chave) {
            $tabela = $chave['tabela'];
            $coluna = $chave['coluna'];

            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $coluna)) {
                continue;
            }

            $nome = $this->nomeDaChave($tabela, $coluna);

            if ($nome !== null) {
                // O índice que sustenta a FK é preservado: dropar a constraint
                // não dropa o índice, e recriá-la o reaproveita.
                DB::statement("ALTER TABLE `{$tabela}` DROP FOREIGN KEY `{$nome}`");
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) '.
                'REFERENCES `%s` (`id`) ON DELETE %s ON UPDATE CASCADE',
                $tabela,
                $nome ?? "{$tabela}_{$coluna}_foreign",
                $coluna,
                $chave['referencia'],
                strtoupper($aoApagar),
            ));
        }
    }

    /**
     * Descobre o nome real da constraint em vez de supor a convenção do Laravel:
     * parte deste schema veio do Node, e nem toda FK segue `<tabela>_<coluna>_foreign`.
     */
    private function nomeDaChave(string $tabela, string $coluna): ?string
    {
        $linhas = DB::select(
            'SELECT CONSTRAINT_NAME AS nome
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$tabela, $coluna],
        );

        return $linhas === [] ? null : (string) $linhas[0]->nome;
    }
};
