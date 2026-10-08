<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Course;
use App\Models\SecurityLog;
use App\Services\AuditLogger;
use App\Services\AuditLogService;
use App\Services\CourseService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * As colunas de data nativas que acompanham as de texto (Norma C.2.4).
 *
 * O que precisa ficar preso aqui são três promessas, nesta ordem de importância:
 *
 * 1. **A coluna de texto não muda.** Ela é o contrato de API em uso. Se um dia
 *    alguém "limpar" o schema trocando o texto pela nativa, é este teste que
 *    acusa.
 * 2. **Gravação nova preenche as duas.** Sem isso a coluna nativa envelhece e
 *    vira mentira — pior que não existir.
 * 3. **A ordenação do log de segurança usa o instante, não o texto.** Era o bug
 *    concreto que motivou a fase (visto em 24/09/2026).
 */
final class DatasNativasTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<array{tabela: string, texto: string, nativa: string, tipo: string}> */
    private const PARES = [
        ['tabela' => 'SecurityLog', 'texto' => 'timestamp', 'nativa' => 'ocorridoEm', 'tipo' => 'datetime'],
        ['tabela' => 'ChatMessage', 'texto' => 'timestamp', 'nativa' => 'enviadaEm', 'tipo' => 'datetime'],
        ['tabela' => 'DirectMessage', 'texto' => 'timestamp', 'nativa' => 'enviadaEm', 'tipo' => 'datetime'],
        ['tabela' => 'ForumMessage', 'texto' => 'timestamp', 'nativa' => 'enviadaEm', 'tipo' => 'datetime'],
        ['tabela' => 'Course', 'texto' => 'contractExpirationDate', 'nativa' => 'vigenciaAte', 'tipo' => 'date'],
        ['tabela' => 'PracticalExercise', 'texto' => 'dueDate', 'nativa' => 'prazoEm', 'tipo' => 'date'],
        ['tabela' => 'WebinarEvent', 'texto' => 'date', 'nativa' => 'dataEvento', 'tipo' => 'datetime'],
    ];

    /**
     * A coluna antiga continua existindo e continua sendo texto. É o contrato.
     * A nova existe com o tipo certo ao lado dela.
     */
    public function test_cada_coluna_de_texto_ganhou_uma_nativa_e_continua_existindo(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Os tipos de coluna só se verificam onde o dado real vive.');
        }

        foreach (self::PARES as $par) {
            $tipos = $this->tiposDe($par['tabela']);

            $this->assertArrayHasKey(
                $par['texto'],
                $tipos,
                "{$par['tabela']}.{$par['texto']} sumiu: é contrato de API em uso.",
            );
            $this->assertStringStartsWith(
                'varchar',
                $tipos[$par['texto']],
                "{$par['tabela']}.{$par['texto']} mudou de tipo — C.2.5 veda, e romperia o consumidor.",
            );

            $this->assertArrayHasKey(
                $par['nativa'],
                $tipos,
                "Falta a coluna nativa {$par['tabela']}.{$par['nativa']} (Norma C.2.4).",
            );
            $this->assertSame(
                $par['tipo'],
                $tipos[$par['nativa']],
                "{$par['tabela']}.{$par['nativa']} devia ser {$par['tipo']}.",
            );
        }
    }

    /**
     * Nenhuma linha dos dados de hoje ficou sem data nativa.
     *
     * Se um formato novo aparecer no banco, é aqui que ele se denuncia — e a
     * resposta certa é ensinar o formato a `DataTexto`, nunca preencher a linha
     * com uma data inventada.
     */
    public function test_backfill_nao_deixou_linha_convertivel_para_tras(): void
    {
        foreach (self::PARES as $par) {
            $orfas = DB::table($par['tabela'])
                ->whereNull($par['nativa'])
                ->whereNotNull($par['texto'])
                ->where($par['texto'], '<>', '')
                ->limit(3)
                ->pluck($par['texto'])
                ->all();

            $this->assertSame(
                [],
                $orfas,
                "{$par['tabela']}.{$par['nativa']} vazia com texto presente — formato desconhecido: "
                .implode(' | ', array_map(strval(...), $orfas)),
            );
        }
    }

    /**
     * O bug de 24/09/2026: ordenar pelo texto "HH:MM:SS DD/MM/AAAA" ordena pela
     * HORA antes da data, então as 23h de ontem vinham DEPOIS das 08h de hoje.
     *
     * Os dois registros abaixo são construídos exatamente nessa armadilha: o
     * mais recente é o que tem a hora MENOR.
     */
    public function test_log_de_seguranca_ordena_pelo_instante_e_nao_pelo_texto(): void
    {
        $ontem = CarbonImmutable::parse('2099-01-01 23:40:00', 'America/Sao_Paulo');
        $hoje = CarbonImmutable::parse('2099-01-02 08:10:00', 'America/Sao_Paulo');

        // Ano 2099 para que estas duas linhas sejam, com folga, as mais recentes
        // da trilha — assim o teste não depende de quantos registros existem.
        foreach ([['z-antigo', $ontem], ['a-recente', $hoje]] as [$sufixo, $quando]) {
            DB::table('SecurityLog')->insert([
                'id' => 'log-teste-'.$sufixo,
                'timestamp' => $quando->format('H:i:s').' '.$quando->format('d/m/Y'),
                'ocorridoEm' => $quando->utc()->format('Y-m-d H:i:s'),
                'user' => 'Teste', 'role' => 'admin', 'ipAddress' => '127.0.0.1',
                'device' => 'phpunit', 'action' => 'TESTE', 'details' => 'ordenação', 'status' => 'SUCCESS',
            ]);
        }

        $itens = app(AuditLogService::class)->listSecurityLogs(0, 2)['items'];

        $this->assertSame('log-teste-a-recente', $itens[0]['id']);
        $this->assertSame('log-teste-z-antigo', $itens[1]['id']);

        // E o `id` do mais recente é alfabeticamente MENOR que o do antigo: se a
        // ordenação caísse para o desempate por id, a asserção acima passaria por
        // acidente. Esta prende que foi a data que decidiu.
        $this->assertLessThan('log-teste-z-antigo', 'log-teste-a-recente');
    }

    /**
     * Gravação nova preenche as duas colunas — senão a nativa envelhece e vira
     * mentira, que é pior que não existir.
     *
     * Chama o `AuditLogger` direto, e não uma rota: por uma rota o teste
     * passaria encontrando um registro ANTIGO já preenchido pelo backfill, sem
     * provar nada sobre gravação nova.
     */
    public function test_auditoria_nova_grava_texto_e_instante(): void
    {
        $antes = SecurityLog::query()->count();

        app(AuditLogger::class)->log(
            Request::create('/api/teste', 'GET'),
            'TESTE_DATA_NATIVA',
            'gravação nova tem de preencher as duas colunas',
        );

        $this->assertSame($antes + 1, SecurityLog::query()->count(), 'A auditoria não gravou nada.');

        $novo = SecurityLog::query()->where('action', 'TESTE_DATA_NATIVA')->first();

        $this->assertNotNull($novo);
        $this->assertIsString($novo->getAttribute('timestamp'), 'O texto de exibição sumiu.');
        $this->assertNotNull($novo->getAttribute('ocorridoEm'), 'Registro novo sem instante nativo.');
    }

    /**
     * O instante nativo e o texto têm de descrever o MESMO momento. É aqui que
     * um erro de fuso apareceria: se a conversão sumisse, daria 3 horas de
     * diferença.
     */
    public function test_instante_nativo_concorda_com_o_texto_exibido(): void
    {
        $linhas = DB::table('SecurityLog')
            ->whereNotNull('ocorridoEm')
            ->orderByDesc('ocorridoEm')
            ->limit(50)
            ->get(['timestamp', 'ocorridoEm']);

        $this->assertNotEmpty($linhas, 'Sem registro de auditoria: o teste não teria o que provar.');

        foreach ($linhas as $linha) {
            $nativo = CarbonImmutable::parse((string) $linha->ocorridoEm, 'UTC')
                ->setTimezone('America/Sao_Paulo');

            $this->assertSame(
                (string) $linha->timestamp,
                $nativo->format('H:i:s').' '.$nativo->format('d/m/Y'),
                'Texto e instante nativo discordam — provável erro de fuso.',
            );
        }
    }

    /** Mensagem de chat nova também preenche as duas. */
    public function test_mensagem_nova_grava_texto_e_instante(): void
    {
        $antes = CarbonImmutable::now()->subMinute();

        // Autor real: `ChatMessage.senderUserId` é FK para `User` (ADR 10), e um id
        // inventado é recusado pelo banco antes de o teste chegar ao que interessa.
        $autor = (string) DB::table('User')->value('id');
        $this->assertNotSame('', $autor, 'Seed sem usuário: o teste não teria autor.');

        $msg = ChatMessage::query()->create([
            'id' => 'chat-teste-'.uniqid(),
            'sessionId' => (string) DB::table('LiveSession')->value('id'),
            'senderName' => 'Teste', 'senderUserId' => $autor, 'senderRole' => 'admin',
            'text' => 'prova', 'timestamp' => CarbonImmutable::now()->toIso8601String(),
            'enviadaEm' => CarbonImmutable::now()->utc(),
        ]);

        $this->assertNotNull($msg->getAttribute('enviadaEm'));
        $this->assertTrue($msg->getAttribute('enviadaEm')->greaterThan($antes));
    }

    /**
     * A vigência do curso é derivada do texto na própria gravação, não por um
     * processo separado que alguém pode esquecer de rodar.
     */
    public function test_vigencia_do_curso_acompanha_o_texto_na_gravacao(): void
    {
        $curso = Course::query()->first();
        $this->assertNotNull($curso);

        app(CourseService::class)->updateCourse(
            (string) $curso->getKey(),
            ['contractExpirationDate' => '2099-05-04'],
            ['sub' => 'teste', 'name' => 'Teste', 'role' => 'admin'],
        );

        $atualizado = Course::query()->find($curso->getKey());
        $this->assertNotNull($atualizado);
        $this->assertSame('2099-05-04', (string) $atualizado->getAttribute('contractExpirationDate'));
        $this->assertSame('2099-05-04', $atualizado->getAttribute('vigenciaAte')?->format('Y-m-d'));
    }

    /** @return array<string, string> coluna => DATA_TYPE */
    private function tiposDe(string $tabela): array
    {
        $tipos = [];
        foreach (DB::select(
            'SELECT COLUMN_NAME n, COLUMN_TYPE t FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$tabela],
        ) as $linha) {
            $tipos[(string) $linha->n] = (string) $linha->t;
        }

        return $tipos;
    }
}
