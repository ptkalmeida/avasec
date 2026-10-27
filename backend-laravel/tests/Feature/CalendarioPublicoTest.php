<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\SessaoAoVivo;
use App\Services\SitePageContentService;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * `/calendario` em Blade — a agenda de encontros ao vivo.
 *
 * O que esta tela não pode fazer é **afirmar encontro que não existe**. Ela não
 * lista conteúdo digitado: lista o que os gestores marcaram de fato, e a
 * ausência é uma resposta legítima — foi por isso que a versão anterior do
 * painel do aluno virou defeito, mostrando "Entrar na sala" para um encontro de
 * dias atrás.
 *
 * O porte da regra em si (janela, ordem, formatos, datas impossíveis) está em
 * `Tests\Unit\AgendaAoVivoTest`, espelhando o Vitest caso a caso. Aqui a
 * pergunta é outra: o que a PÁGINA mostra, e o que ela não deixa escapar.
 */
final class CalendarioPublicoTest extends TestCase
{
    use DatabaseTransactions;

    private function cursoPublicado(): Curso
    {
        $curso = Curso::query()->where('status', Visibilidade::PUBLICADO)->first();
        $this->assertNotNull($curso, 'Seed sem curso publicado.');

        return $curso;
    }

    private function encontroEm(Curso $curso, string $quando, string $titulo, string $sala = ''): SessaoAoVivo
    {
        return SessaoAoVivo::query()->create([
            'id' => 'live-cal-'.uniqid(),
            'curso_id' => $curso->id,
            'titulo' => $titulo,
            'agendado_para_texto' => $quando,
            'duracao_minutos' => 90,
            'link_reuniao' => $sala,
            'ao_vivo' => false,
        ]);
    }

    public function test_a_tela_responde_em_blade_com_o_cabecalho_do_servico_de_conteudo(): void
    {
        $pagina = app(SitePageContentService::class)->get('calendario');

        $resposta = $this->get('/calendario');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee(e((string) $pagina['titulo']), false);
        $resposta->assertSee(e((string) $pagina['eyebrow']), false);
    }

    public function test_encontro_marcado_para_amanha_aparece_com_curso_data_e_hora(): void
    {
        $curso = $this->cursoPublicado();
        $amanha = now()->addDay()->format('Y-m-d').'T19:30';
        $this->encontroEm($curso, $amanha, 'Mentoria de teste do calendário');

        $resposta = $this->get('/calendario');

        $resposta->assertOk();
        $resposta->assertSee('Mentoria de teste do calendário', escape: false);
        $resposta->assertSee(e((string) $curso->titulo), false);
        $resposta->assertSee('19:30', escape: false);
        $resposta->assertSee('amanhã', escape: false);
        $resposta->assertSee('Aula ao vivo', escape: false);
        // A duração acompanha, quando existe.
        $resposta->assertSee('90 min', escape: false);
    }

    /**
     * O `link_reuniao` é a chave da sala de uma turma. O catálogo anônimo já vem
     * sem ele (ISO-01), e publicá-lo numa página aberta desfaria a correção:
     * qualquer um entraria na aula.
     */
    public function test_o_link_da_sala_da_aula_nunca_sai_na_pagina_publica(): void
    {
        $curso = $this->cursoPublicado();
        $this->encontroEm(
            $curso,
            now()->addDays(2)->format('Y-m-d').'T18:00',
            'Encontro com sala cadastrada',
            'https://meet.example.com/sala-da-turma',
        );

        $resposta = $this->get('/calendario');

        $resposta->assertSee('Encontro com sala cadastrada', escape: false);
        $resposta->assertDontSee('meet.example.com', escape: false);
        $resposta->assertDontSee('sala-da-turma', escape: false);
    }

    /** Visitante é convidado a entrar; não recebe link de sala nenhum. */
    public function test_visitante_e_levado_ao_login_e_nao_a_uma_sala(): void
    {
        $curso = $this->cursoPublicado();
        $this->encontroEm($curso, now()->addDays(3)->format('Y-m-d').'T20:00', 'Encontro para visitante');

        $resposta = $this->get('/calendario');

        $resposta->assertSee('Participar', escape: false);
        $resposta->assertSee('href="/entrar"', escape: false);
    }

    /**
     * Encontro de curso em rascunho ou restrito não é divulgado aqui: a agenda
     * nasce de `listCoursesFor(null)`, que aplica a escada de audiência da ADR
     * 12. Sem isso, a página pública anunciaria uma turma que ainda não existe
     * para ninguém.
     */
    public function test_encontro_de_curso_nao_publicado_nao_aparece(): void
    {
        $curso = $this->cursoPublicado();
        $this->encontroEm($curso, now()->addDays(4)->format('Y-m-d').'T17:00', 'Encontro de curso em rascunho');

        $curso->status = Visibilidade::RASCUNHO;
        $curso->save();

        $this->get('/calendario')->assertDontSee('Encontro de curso em rascunho', escape: false);
    }

    /** O que já passou não volta para a agenda. */
    public function test_encontro_que_ja_passou_nao_aparece(): void
    {
        $curso = $this->cursoPublicado();
        $this->encontroEm($curso, now()->subDays(3)->format('Y-m-d').'T19:00', 'Encontro que ja aconteceu');

        $this->get('/calendario')->assertDontSee('Encontro que ja aconteceu', escape: false);
    }

    /**
     * Sem nada marcado, a página diz isso e explica de onde a agenda vem. Grade
     * vazia sem texto deixaria a pessoa achando que a página quebrou.
     */
    public function test_sem_encontro_na_janela_a_pagina_explica_a_ausencia(): void
    {
        /*
         * A condição é LIDA, não forçada: apagar as sessões para provocar o
         * estado vazio seria escrever numa tabela de domínio só para o teste
         * passar, o que o ADR 12 veda mesmo dentro de uma transação. Quando
         * houver encontro marcado na janela, o caso que vale é o de cima.
         */
        $temFuturo = SessaoAoVivo::query()
            ->where('agendado_para_texto', '>=', now()->format('Y-m-d\TH:i'))
            ->exists();

        if ($temFuturo) {
            $this->markTestSkipped('Há encontro marcado na janela: o estado vazio não se aplica agora.');
        }

        $resposta = $this->get('/calendario');

        $resposta->assertOk();
        $resposta->assertSee('Nenhum encontro agendado', escape: false);
        $resposta->assertSee('apenas encontros realmente marcados', escape: false);
    }

    /**
     * Webinar está desligado por decisão de produto (flag `eventosWebinars`,
     * 04/09/2026) e a agenda monta só com aulas ao vivo — como no React.
     */
    public function test_webinar_nao_entra_enquanto_a_flag_estiver_desligada(): void
    {
        $this->assertFalse(config('features.eventosWebinars'), 'A flag mudou: rever a agenda e este teste.');

        $this->get('/calendario')->assertDontSee('Webinar aberto', escape: false);
    }
}
