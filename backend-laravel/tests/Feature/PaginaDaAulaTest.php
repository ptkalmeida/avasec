<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Usuario;
use App\Support\Jwt;
use Tests\TestCase;

/**
 * Página da aula em Blade — tela 5 do redesenho (plano 18, fase M3).
 *
 * É a tela que entrega o conteúdo didático, e por isso o que este arquivo mais
 * protege é que **o conteúdo chega**. O defeito que aconteceu durante a
 * escrita desta tela foi exatamente o contrário do esperado: a página
 * respondeu 200, com moldura, abas e navegação, e o texto da aula saiu em
 * branco. Um teste de status não veria isso.
 */
final class PaginaDaAulaTest extends TestCase
{
    /** @return array{aluno:Usuario, curso:Curso, aulas:list<Aula>} */
    private function cenario(): array
    {
        $aluno = Usuario::query()
            ->where('papel', 'student')->where('status', 'active')->whereNull('inativado_em')->first();

        if ($aluno === null) {
            $this->markTestSkipped('Sem aluno ativo no banco de desenvolvimento.');
        }

        $matricula = Matricula::query()->where('usuario_id', $aluno->id)->first();
        $cursoId = null;

        if (is_string($matricula?->curso_matriculado_id) && $matricula->curso_matriculado_id !== '') {
            $cursoId = $matricula->curso_matriculado_id;
        } else {
            foreach (is_array($matricula?->cursos_extras_ids) ? $matricula->cursos_extras_ids : [] as $id) {
                if (is_string($id) && $id !== '') {
                    $cursoId = $id;
                    break;
                }
            }
        }

        if ($cursoId === null) {
            $this->markTestSkipped('O aluno de desenvolvimento não tem curso em andamento.');
        }

        $curso = Curso::query()->find($cursoId);
        $aulas = Aula::query()->where('curso_id', $cursoId)->orderBy('ordem')->get()->all();

        if ($curso === null || $aulas === []) {
            $this->markTestSkipped('Curso do aluno sem aulas no banco de desenvolvimento.');
        }

        return ['aluno' => $aluno, 'curso' => $curso, 'aulas' => $aulas];
    }

    private function como(Usuario $aluno): self
    {
        return $this->withUnencryptedCookie(
            JwtAuthenticate::SESSION_COOKIE,
            Jwt::issue((string) $aluno->id, (string) $aluno->nome, 'student'),
        );
    }

    private function endereco(Curso $curso, Aula $aula): string
    {
        $ref = is_string($curso->slug) && $curso->slug !== '' ? $curso->slug : (string) $curso->id;

        return '/aluno/curso/'.$ref.'/aula/'.$aula->id;
    }

    public function test_a_aula_abre_e_mostra_o_titulo_dela_e_o_nome_do_curso(): void
    {
        ['aluno' => $aluno, 'curso' => $curso, 'aulas' => $aulas] = $this->cenario();

        $resposta = $this->como($aluno)->get($this->endereco($curso, $aulas[0]));

        $resposta->assertOk();
        $resposta->assertSee($aulas[0]->titulo, escape: false);
        // No lugar de "Aula X de Y", o nome do curso — decisão da skill.
        $resposta->assertSee($curso->titulo, escape: false);
        $resposta->assertDontSee('Aula 1 de ', escape: false);
    }

    /**
     * O defeito que de fato aconteceu: 200, moldura inteira, e o texto da aula
     * sumindo. Aqui se confere o CONTEÚDO, e não o status.
     */
    public function test_o_conteudo_escrito_da_aula_chega_na_pagina(): void
    {
        ['aluno' => $aluno, 'curso' => $curso, 'aulas' => $aulas] = $this->cenario();

        $comTexto = null;
        foreach ($aulas as $aula) {
            if (is_string($aula->conteudo) && mb_strlen(trim($aula->conteudo)) > 80) {
                $comTexto = $aula;
                break;
            }
        }

        if ($comTexto === null) {
            $this->markTestSkipped('Nenhuma aula do curso tem conteúdo escrito.');
        }

        $html = (string) $this->como($aluno)->get($this->endereco($curso, $comTexto))->getContent();

        // Pega a primeira linha de texto corrido do conteúdo e exige que ela
        // tenha chegado à página.
        $primeiraLinha = null;
        foreach (explode("\n", (string) $comTexto->conteudo) as $linha) {
            $limpa = trim($linha);
            if (mb_strlen($limpa) > 40 && ! str_starts_with($limpa, '#') && ! str_starts_with($limpa, '`')) {
                $primeiraLinha = $limpa;
                break;
            }
        }

        $this->assertNotNull($primeiraLinha, 'A aula escolhida não tem texto corrido para conferir.');

        /*
         * O trecho tem de passar pelas MESMAS retiradas que a renderização faz,
         * ou o teste falha por diferença de marcação e não de conteúdo:
         * o marcador de formatação do autor (`{grande} `) sai, e o `**` do
         * negrito também.
         */
        $limpo = (string) preg_replace('/^\{[a-z -]+\}\s+/u', '', $primeiraLinha);
        $trecho = trim(str_replace('**', '', mb_substr($limpo, 0, 40)));

        $this->assertStringContainsString(
            e($trecho),
            $html,
            'A página respondeu, mas o conteúdo da aula não chegou nela.',
        );
    }

    public function test_as_duas_abas_existem(): void
    {
        ['aluno' => $aluno, 'curso' => $curso, 'aulas' => $aulas] = $this->cenario();

        $resposta = $this->como($aluno)->get($this->endereco($curso, $aulas[0]));

        $resposta->assertSee('Material Didático', escape: false);
        $resposta->assertSee('Anotações Privadas', escape: false);
    }

    /**
     * Nesta tela a lista de aulas mora no menu lateral e **não** se repete na
     * área principal — é a regra de controle duplicado da skill.
     */
    public function test_a_lista_de_aulas_aparece_uma_unica_vez(): void
    {
        ['aluno' => $aluno, 'curso' => $curso, 'aulas' => $aulas] = $this->cenario();

        $html = (string) $this->como($aluno)->get($this->endereco($curso, $aulas[0]))->getContent();

        $this->assertSame(1, substr_count($html, 'Aulas do curso'));
    }

    public function test_nao_traz_o_que_a_skill_removeu_da_aula(): void
    {
        ['aluno' => $aluno, 'curso' => $curso, 'aulas' => $aulas] = $this->cenario();

        $resposta = $this->como($aluno)->get($this->endereco($curso, $aulas[0]));

        foreach (['Frequência', 'frequência cumprida', 'Online', 'Instrutor responsável', 'Suporte Pedagógico'] as $removido) {
            $resposta->assertDontSee($removido, escape: false);
        }
    }

    /**
     * A função do suporte não sumiu: mudou para o menu lateral, e leva a aula
     * junto para o gestor saber de que aula se trata.
     *
     * Este teste conferia `href="/aluno/mensagens"` — e passava justamente
     * porque o link estava errado: aquela é a tela de LER mensagens, fechada
     * para o aluno com a flag `forum` desligada. Conferir só o `href` provou que
     * o link existia, não que levava a algum lugar.
     */
    public function test_suporte_pedagogico_continua_alcancavel_pelo_menu_com_a_aula(): void
    {
        ['aluno' => $aluno, 'curso' => $curso, 'aulas' => $aulas] = $this->cenario();

        $resposta = $this->como($aluno)->get($this->endereco($curso, $aulas[0]));

        // "Ajuda e suporte" é um item só (ADR 026); dentro da aula ele abre na conversa, com a aula junto.
        $resposta->assertSee('Ajuda e suporte', escape: false);
        $resposta->assertSee('href="/aluno/suporte?aula='.urlencode((string) $aulas[0]->id).'"', escape: false);
    }

    public function test_navegacao_entre_aulas_na_primeira_e_na_ultima(): void
    {
        ['aluno' => $aluno, 'curso' => $curso, 'aulas' => $aulas] = $this->cenario();

        if (count($aulas) < 2) {
            $this->markTestSkipped('O curso tem uma aula só.');
        }

        $primeira = $this->como($aluno)->get($this->endereco($curso, $aulas[0]));
        $primeira->assertSee('Próxima aula', escape: false);
        $primeira->assertDontSee('Aula anterior', escape: false);

        $ultima = $this->como($aluno)->get($this->endereco($curso, $aulas[count($aulas) - 1]));
        $ultima->assertSee('Aula anterior', escape: false);
        // Na última, o caminho honesto é voltar ao curso — e não uma próxima
        // aula que não existe.
        $ultima->assertSee('Voltar ao curso', escape: false);
    }

    /**
     * Aula de outro curso no endereço deste é link quebrado, não "aula vazia".
     * Responder 200 com a moldura e nada dentro esconderia o erro.
     */
    public function test_aula_que_nao_e_do_curso_responde_404(): void
    {
        ['aluno' => $aluno, 'curso' => $curso] = $this->cenario();

        $deOutroCurso = Aula::query()->where('curso_id', '!=', $curso->id)->first();

        if ($deOutroCurso === null) {
            $this->markTestSkipped('Não há aula de outro curso no banco.');
        }

        $ref = is_string($curso->slug) && $curso->slug !== '' ? $curso->slug : (string) $curso->id;

        $this->como($aluno)->get('/aluno/curso/'.$ref.'/aula/'.$deOutroCurso->id)->assertNotFound();
        $this->como($aluno)->get('/aluno/curso/'.$ref.'/aula/nao-existe')->assertNotFound();
    }

    public function test_visitante_sem_sessao_nao_le_a_aula(): void
    {
        ['curso' => $curso, 'aulas' => $aulas] = $this->cenario();

        $this->get($this->endereco($curso, $aulas[0]))->assertRedirect('/entrar');
    }

    /** O vídeo sai no domínio sem cookie, e nunca com reprodução automática. */
    public function test_video_do_youtube_usa_o_dominio_sem_cookie_e_sem_autoplay(): void
    {
        ['aluno' => $aluno, 'curso' => $curso, 'aulas' => $aulas] = $this->cenario();

        $comVideo = null;
        foreach ($aulas as $aula) {
            if (is_string($aula->url_video) && str_contains($aula->url_video, 'youtu')) {
                $comVideo = $aula;
                break;
            }
        }

        if ($comVideo === null) {
            $this->markTestSkipped('Nenhuma aula do curso tem vídeo do YouTube.');
        }

        $resposta = $this->como($aluno)->get($this->endereco($curso, $comVideo));

        $resposta->assertSee('youtube-nocookie.com/embed/', escape: false);
        $resposta->assertSee('autoplay=0', escape: false);
    }
}
