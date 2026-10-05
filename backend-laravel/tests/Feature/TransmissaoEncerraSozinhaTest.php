<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LiveSession;
use App\Support\BusinessRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * Encerramento automático da transmissão 24h após o horário agendado.
 *
 * `LiveSession.isLive` é um interruptor MANUAL: quem dá aula clica "Iniciar" e
 * precisa clicar "Finalizar". Quando esquece, a sessão fica ao vivo para sempre.
 * Encontrado nos dados de dev em 03/09/2026: duas sessões de 01/09 ainda com
 * `isLive = 1`, oferecendo sala aberta com ninguém dentro.
 *
 * A regra é derivada, não gravada: o valor no banco registra o que a pessoa
 * clicou, e a verdade servida pela API é ele + o tempo decorrido. Por isso os
 * testes olham a RESPOSTA da API, não a coluna.
 */
final class TransmissaoEncerraSozinhaTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    private function cursoDoInstrutor(): Course
    {
        $instructorId = DB::table('User')->where('role', 'instructor')->where('status', 'active')->value('id');
        $curso = Course::query()->where('instructorId', $instructorId)->first();
        $this->assertNotNull($curso, 'Seed sem curso do instrutor.');

        return $curso;
    }

    /** Cria uma sessão marcada ao vivo, agendada há $horas horas. */
    private function sessaoAoVivoHa(Course $curso, float $horas): LiveSession
    {
        $quando = CarbonImmutable::now()->subMinutes((int) round($horas * 60));

        return LiveSession::query()->create([
            'id' => 'live-teste-'.uniqid(),
            'courseId' => $curso->id,
            'title' => 'Encontro de teste',
            // Formato do banco: ISO local sem fuso, o que o datetime-local produz.
            'scheduledAt' => $quando->format('Y-m-d\TH:i'),
            'durationMinutes' => 60,
            'meetingLink' => 'https://meet.example/teste',
            'isLive' => true,
        ]);
    }

    /** `isLive` que a API devolve para aquela sessão dentro do curso. */
    private function isLiveNaApi(string $courseId, string $sessionId, string $token): ?bool
    {
        // Não há GET /courses/{id}: a listagem é a rota que o app usa.
        $cursos = $this->getJson('/api/courses', $this->auth($token))->assertOk()->json();
        $lista = is_array($cursos['items'] ?? null) ? $cursos['items'] : $cursos;
        foreach ($lista as $curso) {
            if (($curso['id'] ?? null) !== $courseId) {
                continue;
            }
            foreach ($curso['liveSessions'] ?? [] as $s) {
                if (($s['id'] ?? null) === $sessionId) {
                    return (bool) $s['isLive'];
                }
            }
        }

        return null;
    }

    public function test_a_janela_e_de_24_horas(): void
    {
        $this->assertSame(24, BusinessRules::liveSessionAutoEndHours());
    }

    public function test_transmissao_recente_continua_ao_vivo_na_api(): void
    {
        $curso = $this->cursoDoInstrutor();
        $sessao = $this->sessaoAoVivoHa($curso, 2);

        $this->assertTrue(
            $this->isLiveNaApi($curso->id, $sessao->id, $this->staffToken('instructor')),
            'Transmissão de 2h atrás não deveria ter encerrado.'
        );
    }

    public function test_passadas_24h_a_api_deixa_de_dizer_que_esta_ao_vivo(): void
    {
        $curso = $this->cursoDoInstrutor();
        $sessao = $this->sessaoAoVivoHa($curso, 30);

        $this->assertFalse(
            $this->isLiveNaApi($curso->id, $sessao->id, $this->staffToken('instructor')),
            'Passadas 24h do horário agendado, a API não pode reportar a sessão ao vivo.'
        );

        // O valor gravado NÃO é reescrito: ele registra o clique de quem deu aula.
        // A verdade exibida é derivada dele + o tempo.
        $this->assertTrue((bool) DB::table('LiveSession')->where('id', $sessao->id)->value('isLive'));
    }

    public function test_o_aluno_tambem_recebe_a_sessao_encerrada(): void
    {
        // A regra não pode valer só para quem administra: era o aluno que entrava
        // na sala vazia.
        $curso = $this->cursoDoInstrutor();
        $sessao = $this->sessaoAoVivoHa($curso, 48);
        $aluno = $this->makeStudent('Aluno Transmissao');

        $this->assertFalse($this->isLiveNaApi($curso->id, $sessao->id, $aluno['token']));
    }

    public function test_put_direto_nao_reabre_transmissao_encerrada(): void
    {
        // O painel não oferece mais o botão, mas o servidor é que tem de garantir.
        $curso = $this->cursoDoInstrutor();
        $sessao = $this->sessaoAoVivoHa($curso, 72);

        $this->putJson("/api/courses/{$curso->id}", [
            'title' => $curso->title,
            'description' => $curso->description,
            'category' => $curso->category,
            'liveSessions' => [[
                'id' => $sessao->id,
                'title' => $sessao->title,
                'scheduledAt' => $sessao->scheduledAt,
                'durationMinutes' => 60,
                'meetingLink' => 'https://meet.example/teste',
                'isLive' => true,
            ]],
        ], $this->auth($this->staffToken('instructor')))->assertOk();

        $this->assertFalse((bool) DB::table('LiveSession')->where('id', $sessao->id)->value('isLive'));
    }

    public function test_data_em_formato_antigo_nao_encerra_por_tempo(): void
    {
        // Sem data legível não há de onde contar, e chutar encerraria um encontro
        // que talvez ainda vá acontecer.
        $this->assertFalse(BusinessRules::liveSessionExpired('Próxima Segunda, às 20:00'));
        $this->assertFalse(BusinessRules::liveSessionExpired(null));
        $this->assertFalse(BusinessRules::liveSessionExpired(''));
    }

    public function test_a_fronteira_das_24h(): void
    {
        $agora = CarbonImmutable::create(2026, 9, 11, 19, 30);
        $this->assertNotNull($agora);

        // 23h59 depois: dentro da janela. Exatamente 24h: fora.
        $this->assertFalse(BusinessRules::liveSessionExpired('2026-09-10T19:31', $agora));
        $this->assertTrue(BusinessRules::liveSessionExpired('2026-09-10T19:30', $agora));
        // Encontro futuro nunca está encerrado.
        $this->assertFalse(BusinessRules::liveSessionExpired('2026-09-20T19:30', $agora));
    }
}
