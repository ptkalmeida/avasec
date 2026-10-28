<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AgendaAoVivo;
use App\Support\Fuso;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * `App\Support\AgendaAoVivo` — porte de leitura do `src/utils/liveSchedule.ts`.
 *
 * Os casos aqui espelham `tests/frontend/liveSchedule.test.ts`, um a um, com as
 * mesmas datas e os mesmos títulos. Não é repetição ociosa: enquanto as duas
 * implementações coexistirem (o painel do aluno e as telas de gestão ainda
 * leem a versão em TypeScript), qualquer divergência entre elas aparece como
 * "a mesma aula em dois horários diferentes, dependendo da tela".
 *
 * Os casos de data impossível são os que mais importam. Tanto o `new Date` do
 * JS quanto o `CarbonImmutable::create` do PHP "consertam" 31/02 virando 03/03
 * em silêncio — e a agenda passaria a mostrar um encontro em dia que ninguém
 * marcou.
 */
final class AgendaAoVivoTest extends TestCase
{
    /** 10/09/2026 às 12:00 — o "agora" de todos os casos, como no Vitest. */
    private function agora(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 9, 10, 12, 0, 0, Fuso::exibicao());
    }

    /**
     * @param  list<array{0:string,1:string,2:string}>  $sessoes
     * @return array<string, mixed>
     */
    private function curso(string $id, string $titulo, array $sessoes): array
    {
        return [
            'id' => $id,
            'titulo' => $titulo,
            'sessoes_ao_vivo' => array_map(static fn (array $s): array => [
                'id' => $s[0],
                'curso_id' => $id,
                'titulo' => $s[1],
                'agendado_para_texto' => $s[2],
                'duracao_minutos' => 60,
                'link_reuniao' => '',
                'ao_vivo' => false,
            ], $sessoes),
        ];
    }

    /** @return array<string, mixed> */
    private function webinar(string $id, string $titulo, string $data, string $hora): array
    {
        return ['id' => $id, 'titulo' => $titulo, 'data_texto' => $data, 'horario' => $hora, 'link' => '#'];
    }

    public function test_le_iso_local_com_e_sem_segundos(): void
    {
        $comSegundos = AgendaAoVivo::instanteDaAula('2026-09-15T19:30:00');
        $sem = AgendaAoVivo::instanteDaAula('2026-09-15T19:30');

        $this->assertNotNull($sem);
        $this->assertNotNull($comSegundos);
        $this->assertSame($sem->toDateTimeString(), $comSegundos->toDateTimeString());
    }

    /** O horário é literal: a aula das 19:30 não pode virar 22:30 na tela. */
    public function test_trata_a_data_como_horario_local_e_nao_utc(): void
    {
        $d = AgendaAoVivo::instanteDaAula('2026-09-15T19:30');

        $this->assertNotNull($d);
        $this->assertSame(19, $d->hour);
        $this->assertSame(30, $d->minute);
        $this->assertSame(Fuso::exibicao()->getName(), $d->timezone->getName());
    }

    /** Valores que existiam MESMO no banco antes de o campo virar data. */
    public function test_texto_livre_do_formato_antigo_fica_de_fora_sem_quebrar(): void
    {
        foreach (['Hoje, às 19:30', 'Amanhã, às 18:00', 'Próxima Segunda, às 20:00'] as $antigo) {
            $this->assertNull(AgendaAoVivo::instanteDaAula($antigo), $antigo);
        }
    }

    public function test_recusa_data_inexistente_em_vez_de_deslizar_para_o_mes_seguinte(): void
    {
        foreach (['2026-02-31T10:00', '2026-13-01T10:00', '2026-09-15T25:00'] as $impossivel) {
            $this->assertNull(AgendaAoVivo::instanteDaAula($impossivel), $impossivel);
        }
    }

    public function test_recusa_vazio_e_nulo(): void
    {
        $this->assertNull(AgendaAoVivo::instanteDaAula(''));
        $this->assertNull(AgendaAoVivo::instanteDaAula(null));
    }

    public function test_le_a_data_brasileira_do_webinar_com_hora_separada(): void
    {
        $d = AgendaAoVivo::instanteDoWebinar('15/09/2026', '19:00');

        $this->assertNotNull($d);
        $this->assertSame('2026-09-15 19:00:00', $d->toDateTimeString());
    }

    public function test_recusa_formato_invalido_ou_data_inexistente_no_webinar(): void
    {
        $this->assertNull(AgendaAoVivo::instanteDoWebinar('2026-09-15', '19:00'));
        $this->assertNull(AgendaAoVivo::instanteDoWebinar('31/02/2026', '10:00'));
        $this->assertNull(AgendaAoVivo::instanteDoWebinar('15/09/2026', '19h'));
    }

    /** @return list<array<string, mixed>> */
    private function cursosDoCenario(): array
    {
        return [
            $this->curso('c1', 'Design de Interfaces', [
                ['s1', 'Mentoria de Wireframes', '2026-09-15T19:30'],
                ['s2', 'Feedback de Portfólio', '2026-09-22T18:00'],
                ['s3', 'Encontro que já passou', '2026-09-01T19:00'],
                ['s4', 'Encontro além dos 30 dias', '2026-11-20T19:00'],
                ['s5', 'Sessão do formato antigo', 'Próxima Segunda, às 20:00'],
            ]),
            $this->curso('c2', 'Economia Criativa', [
                ['s6', 'Abertura da turma', '2026-09-12T20:00'],
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function webinarsDoCenario(): array
    {
        return [
            $this->webinar('w1', 'IA no Design', '18/09/2026', '19:00'),
            $this->webinar('w2', 'Webinar antigo', '01/06/2026', '19:00'),
        ];
    }

    public function test_devolve_so_a_janela_de_30_dias_do_mais_proximo_ao_mais_distante(): void
    {
        $agenda = AgendaAoVivo::montar($this->cursosDoCenario(), $this->webinarsDoCenario(), $this->agora());

        $this->assertSame(
            ['Abertura da turma', 'Mentoria de Wireframes', 'IA no Design', 'Feedback de Portfólio'],
            array_map(static fn (array $e): string => $e['titulo'], $agenda),
        );
    }

    public function test_descarta_o_que_passou_o_que_esta_alem_da_janela_e_o_formato_antigo(): void
    {
        $titulos = array_map(
            static fn (array $e): string => $e['titulo'],
            AgendaAoVivo::montar($this->cursosDoCenario(), $this->webinarsDoCenario(), $this->agora()),
        );

        foreach (['Encontro que já passou', 'Encontro além dos 30 dias', 'Sessão do formato antigo', 'Webinar antigo'] as $fora) {
            $this->assertNotContains($fora, $titulos);
        }
    }

    public function test_identifica_a_origem_de_cada_encontro(): void
    {
        $agenda = AgendaAoVivo::montar($this->cursosDoCenario(), $this->webinarsDoCenario(), $this->agora());
        $porTitulo = [];
        foreach ($agenda as $e) {
            $porTitulo[$e['titulo']] = $e;
        }

        $this->assertSame('aula', $porTitulo['Mentoria de Wireframes']['tipo']);
        $this->assertSame('Design de Interfaces', $porTitulo['Mentoria de Wireframes']['contexto']);
        $this->assertSame(60, $porTitulo['Mentoria de Wireframes']['duracaoMinutos']);

        $this->assertSame('webinar', $porTitulo['IA no Design']['tipo']);
        $this->assertSame('Webinar aberto', $porTitulo['IA no Design']['contexto']);
    }

    /**
     * O link da sala de uma AULA nunca sai daqui, nem quando está gravado: é a
     * chave da sala de uma turma (ISO-01). Só o webinar, que é evento público,
     * carrega convite.
     */
    public function test_aula_nunca_leva_link_de_sala_e_webinar_leva(): void
    {
        $cursos = [$this->curso('c3', 'Com sala', [['s9', 'Com link gravado', '2026-09-15T19:30']])];
        $cursos[0]['sessoes_ao_vivo'][0]['link_reuniao'] = 'https://meet.example/sala-secreta';

        $agenda = AgendaAoVivo::montar($cursos, $this->webinarsDoCenario(), $this->agora());

        foreach ($agenda as $evento) {
            if ($evento['tipo'] === 'aula') {
                $this->assertNull($evento['link']);
            }
        }

        $webinar = array_values(array_filter($agenda, static fn (array $e): bool => $e['tipo'] === 'webinar'));
        $this->assertSame('#', $webinar[0]['link']);
    }

    public function test_respeita_uma_janela_diferente_quando_pedida(): void
    {
        $agenda = AgendaAoVivo::montar($this->cursosDoCenario(), $this->webinarsDoCenario(), $this->agora(), 5);

        $this->assertSame(['Abertura da turma'], array_map(static fn (array $e): string => $e['titulo'], $agenda));
    }

    public function test_lida_com_listas_vazias_e_curso_sem_sessao(): void
    {
        $this->assertSame([], AgendaAoVivo::montar([], [], $this->agora()));
        $this->assertSame([], AgendaAoVivo::montar([$this->curso('c9', 'Vazio', [])], [], $this->agora()));
    }

    public function test_formata_dia_mes_e_hora_para_o_cartao(): void
    {
        $d = CarbonImmutable::create(2026, 9, 5, 9, 7, 0, Fuso::exibicao());

        $this->assertSame('05', AgendaAoVivo::dia($d));
        $this->assertSame('SET', AgendaAoVivo::mes($d));
        $this->assertSame('09:07', AgendaAoVivo::hora($d));
    }

    /**
     * Às 23h, um encontro às 8h do dia seguinte é "amanhã" — faltam nove horas,
     * mas é outro dia, e é assim que quem lê a agenda pensa.
     */
    public function test_conta_por_dia_de_calendario_e_nao_por_24h_corridas(): void
    {
        $noite = CarbonImmutable::create(2026, 9, 10, 23, 0, 0, Fuso::exibicao());
        $manha = CarbonImmutable::create(2026, 9, 11, 8, 0, 0, Fuso::exibicao());

        $this->assertSame('amanhã', AgendaAoVivo::distanciaEmDias($manha, $noite));
    }

    public function test_diz_hoje_amanha_e_em_n_dias(): void
    {
        $agora = $this->agora();

        $this->assertSame('hoje', AgendaAoVivo::distanciaEmDias(
            CarbonImmutable::create(2026, 9, 10, 19, 0, 0, Fuso::exibicao()), $agora));
        $this->assertSame('amanhã', AgendaAoVivo::distanciaEmDias(
            CarbonImmutable::create(2026, 9, 11, 19, 0, 0, Fuso::exibicao()), $agora));
        $this->assertSame('em 5 dias', AgendaAoVivo::distanciaEmDias(
            CarbonImmutable::create(2026, 9, 15, 19, 0, 0, Fuso::exibicao()), $agora));
    }

    public function test_atravessa_a_virada_de_mes_sem_se_perder(): void
    {
        $this->assertSame('amanhã', AgendaAoVivo::distanciaEmDias(
            CarbonImmutable::create(2026, 10, 1, 10, 0, 0, Fuso::exibicao()),
            CarbonImmutable::create(2026, 9, 30, 10, 0, 0, Fuso::exibicao()),
        ));
    }
}
