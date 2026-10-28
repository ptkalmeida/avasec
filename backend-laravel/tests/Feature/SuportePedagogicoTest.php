<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Aula;
use App\Models\Matricula;
use App\Models\MensagemDireta;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * `/aluno/suporte` — enviar uma dúvida ao gestor do curso.
 *
 * Esta tela existe porque a fatia da tela 5 tirou o cartão de suporte da aula
 * e apontou o menu para `/aluno/mensagens`, que é a tela de LER a conversa —
 * fechada para o aluno com a flag `forum` desligada. O aluno ficou sem nenhum
 * jeito de pedir ajuda, e o teste daquela fatia passava porque conferia só o
 * `href`. Aqui se confere que a mensagem CHEGA ao banco.
 */
final class SuportePedagogicoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    public function test_visitante_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/aluno/suporte')->assertRedirect('/entrar');
    }

    public function test_a_pagina_oferece_o_formulario_e_promete_a_resposta_aqui(): void
    {
        $aluno = $this->makeStudent('Aluno Suporte Tela');

        $resposta = $this->como($aluno['token'])->get('/aluno/suporte');

        $resposta->assertOk();
        $resposta->assertSee('Sua mensagem', escape: false);
        $resposta->assertSee('chega', escape: false);
        // Desde 21/10 a resposta aparece aqui — e a tela pode prometê-la.
        $resposta->assertSee('resposta dele aparece nesta página', escape: false);
    }

    /**
     * O defeito que mais custou nesta tela: havia resposta do gestor no banco
     * que o aluno NUNCA pôde ler, porque a leitura dependia da flag do mural
     * do curso. Este teste é o que impede a volta.
     */
    public function test_o_aluno_le_a_resposta_do_gestor(): void
    {
        $aluno = $this->makeStudent('Aluno Suporte Le Resposta');

        $this->como($aluno['token'])->post('/aluno/suporte', ['texto' => 'Minha duvida de teste.']);

        $gestor = Usuario::query()->where('papel', 'instructor')->first();
        $this->assertNotNull($gestor);
        MensagemDireta::query()->create([
            'id' => 'dm-teste-'.uniqid(),
            'nome_aluno' => $aluno['nome'],
            'aluno_usuario_id' => $aluno['id'],
            'nome_remetente' => (string) $gestor->nome,
            'remetente_usuario_id' => (string) $gestor->id,
            'papel_remetente' => 'instructor',
            'texto' => 'Resposta do gestor para o teste.',
            'registrado_em_texto' => now()->toIso8601String(),
            'enviada_em' => now(),
        ]);

        $this->como($aluno['token'])->get('/aluno/suporte')
            ->assertSee('Minha duvida de teste.', escape: false)
            ->assertSee('Resposta do gestor para o teste.', escape: false)
            ->assertSee(e((string) $gestor->nome), false);
    }

    /** A conversa é a do próprio aluno: a de outro não aparece. */
    public function test_a_conversa_de_outro_aluno_nao_aparece(): void
    {
        $eu = $this->makeStudent('Aluno Suporte Meu');
        $outro = $this->makeStudent('Aluno Suporte Outro');

        $this->como($outro['token'])->post('/aluno/suporte', ['texto' => 'Duvida do outro aluno.']);

        $this->como($eu['token'])->get('/aluno/suporte')
            ->assertDontSee('Duvida do outro aluno.', escape: false);
    }

    /** A etiqueta da aula sai do texto e vira rótulo. */
    public function test_a_aula_aparece_como_etiqueta_e_nao_no_meio_do_texto(): void
    {
        $aluno = $this->makeStudent('Aluno Suporte Etiqueta');
        $aula = Aula::query()->first();
        $this->assertNotNull($aula);

        Matricula::query()->create([
            'id' => (string) Str::uuid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            'curso_matriculado_id' => $aula->curso_id,
            'matriculado_em_texto' => now(),
            'cursos_concluidos_ids' => [],
            'penalidade_desistencia_ate' => null,
            'pode_multimatricula' => false,
            'cursos_extras_ids' => [],
        ]);

        $this->como($aluno['token'])
            ->post('/aluno/suporte', ['texto' => 'Duvida com etiqueta.', 'aula' => $aula->id]);

        $html = (string) $this->como($aluno['token'])->get('/aluno/suporte')->getContent();

        $this->assertStringContainsString('Aula: '.e((string) $aula->titulo), $html);
        $this->assertStringNotContainsString('[Aula:', $html);
    }

    public function test_a_mensagem_enviada_fica_registrada_no_canal_do_proprio_aluno(): void
    {
        $aluno = $this->makeStudent('Aluno Suporte Envia');

        $this->como($aluno['token'])
            ->post('/aluno/suporte', ['texto' => 'Não consigo abrir o vídeo da aula 2.'])
            ->assertRedirect('/aluno/suporte');

        $registrada = MensagemDireta::query()
            ->where('aluno_usuario_id', $aluno['id'])
            ->where('remetente_usuario_id', $aluno['id'])
            ->first();

        $this->assertNotNull($registrada, 'A mensagem não chegou ao banco.');
        $this->assertSame('Não consigo abrir o vídeo da aula 2.', $registrada->texto);
        $this->assertSame('student', $registrada->papel_remetente);
    }

    public function test_depois_de_enviar_a_confirmacao_e_anunciada(): void
    {
        $aluno = $this->makeStudent('Aluno Suporte Confirma');

        $this->como($aluno['token'])
            ->followingRedirects()
            ->post('/aluno/suporte', ['texto' => 'Dúvida de teste.'])
            ->assertSee('Mensagem enviada ao gestor do curso.', escape: false)
            ->assertSee('role="status"', escape: false);
    }

    /**
     * Vindo de uma aula do próprio curso, o assunto leva o título dela — mesmo
     * formato `[Aula: título]` do cartão antigo, que é como o gestor sabe de
     * que aula se trata.
     */
    public function test_vindo_de_uma_aula_do_proprio_curso_o_assunto_leva_o_titulo(): void
    {
        $aluno = $this->makeStudent('Aluno Suporte Aula');
        $aula = Aula::query()->first();
        $this->assertNotNull($aula, 'Seed sem aula.');

        Matricula::query()->create([
            'id' => (string) Str::uuid(),
            'usuario_id' => $aluno['id'],
            'nome_aluno' => $aluno['nome'],
            'curso_matriculado_id' => $aula->curso_id,
            'matriculado_em_texto' => now(),
            'cursos_concluidos_ids' => [],
            'penalidade_desistencia_ate' => null,
            'pode_multimatricula' => false,
            'cursos_extras_ids' => [],
        ]);

        $this->como($aluno['token'])
            ->post('/aluno/suporte', ['texto' => 'Não entendi o exemplo.', 'aula' => $aula->id]);

        $texto = MensagemDireta::query()->where('aluno_usuario_id', $aluno['id'])->value('texto');
        $this->assertSame('[Aula: '.trim((string) $aula->titulo).'] Não entendi o exemplo.', $texto);
    }

    /** Aula de curso alheio não entra no assunto: o id vem da URL. */
    public function test_aula_de_curso_alheio_nao_entra_no_assunto(): void
    {
        $aluno = $this->makeStudent('Aluno Suporte Alheio');
        $aula = Aula::query()->first();
        $this->assertNotNull($aula, 'Seed sem aula.');

        $this->como($aluno['token'])
            ->post('/aluno/suporte', ['texto' => 'Mensagem simples.', 'aula' => $aula->id]);

        $texto = MensagemDireta::query()->where('aluno_usuario_id', $aluno['id'])->value('texto');
        $this->assertSame('Mensagem simples.', $texto);
    }

    public function test_mensagem_vazia_volta_com_o_erro_e_nada_e_gravado(): void
    {
        $aluno = $this->makeStudent('Aluno Suporte Vazio');

        $this->como($aluno['token'])
            ->from('/aluno/suporte')
            ->post('/aluno/suporte', ['texto' => ''])
            ->assertRedirect('/aluno/suporte')
            ->assertSessionHasErrors('texto');

        $this->assertFalse(MensagemDireta::query()->where('aluno_usuario_id', $aluno['id'])->exists());
    }

    /**
     * Com mensagens desligadas, a porta web fecha como a da API — senão viraria
     * o jeito de contornar o desligamento — e o item some do menu.
     */
    public function test_com_mensagens_desligadas_o_envio_fecha_e_o_item_some(): void
    {
        config(['features.mensagensDiretas' => false]);
        $aluno = $this->makeStudent('Aluno Suporte Desligado');

        $this->como($aluno['token'])
            ->post('/aluno/suporte', ['texto' => 'Tentativa.'])
            ->assertNotFound();

        $this->como($aluno['token'])->get('/aluno')
            ->assertDontSee('href="/aluno/suporte', escape: false);
    }
}
