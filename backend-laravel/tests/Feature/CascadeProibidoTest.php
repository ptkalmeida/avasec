<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O banco tem de RECUSAR o apagar físico de um registro de domínio que tenha
 * filhos — e não apagá-los em cascata.
 *
 * Por que o teste existe: o ADR 12 nasceu do levantamento em que apagar um
 * usuário destruía notas, entregas, progresso e matrícula, deixando o
 * certificado órfão. A correção da época foi no código (trait `Inativavel`), e o
 * código continua sendo a primeira trava. Este teste cuida da segunda: que o
 * banco também recuse, para o caso de alguém executar um DELETE por fora da
 * aplicação — numa manutenção, num script, num console.
 *
 * Também é o teste da Norma C.2.2: "não deverá ser utilizado ON DELETE CASCADE
 * como mecanismo padrão para exclusão de dados de negócio".
 *
 * Nada aqui exercita a aplicação: é propositalmente SQL cru, porque é exatamente
 * o caminho que a aplicação não usa e que precisava de trava.
 */
final class CascadeProibidoTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * As mesmas 12 chaves da migration
     * `2026_10_08_120000_trocar_on_delete_cascade_por_restrict`.
     */
    public function test_nenhuma_chave_estrangeira_usa_on_delete_cascade(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('A regra vale no MySQL, que é onde o dado real vive.');
        }

        $emCascata = DB::select(
            "SELECT k.TABLE_NAME AS tabela, k.COLUMN_NAME AS coluna
               FROM information_schema.REFERENTIAL_CONSTRAINTS rc
               JOIN information_schema.KEY_COLUMN_USAGE k
                 ON k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
              WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
                AND rc.DELETE_RULE = 'CASCADE'"
        );

        $nomes = array_map(
            static fn (object $l): string => "{$l->tabela}.{$l->coluna}",
            $emCascata,
        );

        $this->assertSame(
            [],
            $nomes,
            'Chave estrangeira com ON DELETE CASCADE em tabela de domínio (ADR 12, Norma C.2.2): '
            .implode(', ', $nomes),
        );
    }

    public function test_banco_recusa_apagar_usuario_que_tem_matricula(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('A regra vale no MySQL.');
        }

        $matricula = DB::table('StudentEnrollment')->first(['userId']);
        $this->assertNotNull($matricula, 'Seed sem matrícula: o teste não teria o que provar.');

        $this->expectException(QueryException::class);

        DB::table('User')->where('id', $matricula->userId)->delete();
    }

    public function test_banco_recusa_apagar_curso_que_tem_aula(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('A regra vale no MySQL.');
        }

        $aula = DB::table('Lesson')->whereNotNull('courseId')->first(['courseId']);
        $this->assertNotNull($aula, 'Seed sem aula vinculada a curso.');

        $this->expectException(QueryException::class);

        DB::table('Course')->where('id', $aula->courseId)->delete();
    }

    /**
     * O outro lado da moeda: inativar continua funcionando. Se esta garantia
     * cair, a trava acima teria transformado "não apagar" em "não conseguir
     * tirar do ar", que é um bug diferente e pior.
     */
    public function test_inativar_usuario_continua_funcionando(): void
    {
        $matricula = DB::table('StudentEnrollment')->first(['userId']);
        $this->assertNotNull($matricula);

        $usuario = User::withTrashed()->find($matricula->userId);
        $this->assertNotNull($usuario);

        $usuario->inativar($matricula->userId, 'teste automatizado');

        $this->assertTrue(
            User::withTrashed()->find($matricula->userId)->estaInativo(),
            'Inativação deixou de funcionar depois da troca para RESTRICT.',
        );
    }
}
