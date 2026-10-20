<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\DirectMessage;
use App\Models\Lesson;
use App\Models\StudentEnrollment;
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

    public function test_a_pagina_oferece_o_formulario_e_nao_promete_resposta_aqui(): void
    {
        $aluno = $this->makeStudent('Aluno Suporte Tela');

        $resposta = $this->como($aluno['token'])->get('/aluno/suporte');

        $resposta->assertOk();
        $resposta->assertSee('Sua mensagem', escape: false);
        $resposta->assertSee('chega ao gestor', escape: false);
        // Ler a resposta depende do canal fechado; a tela não pode prometer isso.
        $resposta->assertDontSee('resposta aparece', escape: false);
    }

    public function test_a_mensagem_enviada_fica_registrada_no_canal_do_proprio_aluno(): void
    {
        $aluno = $this->makeStudent('Aluno Suporte Envia');

        $this->como($aluno['token'])
            ->post('/aluno/suporte', ['texto' => 'Não consigo abrir o vídeo da aula 2.'])
            ->assertRedirect('/aluno/suporte');

        $registrada = DirectMessage::query()
            ->where('studentUserId', $aluno['id'])
            ->where('senderUserId', $aluno['id'])
            ->first();

        $this->assertNotNull($registrada, 'A mensagem não chegou ao banco.');
        $this->assertSame('Não consigo abrir o vídeo da aula 2.', $registrada->text);
        $this->assertSame('student', $registrada->senderRole);
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
        $aula = Lesson::query()->first();
        $this->assertNotNull($aula, 'Seed sem aula.');

        StudentEnrollment::query()->create([
            'id' => (string) Str::uuid(),
            'userId' => $aluno['id'],
            'studentName' => $aluno['name'],
            'enrolledCourseId' => $aula->courseId,
            'enrolledAt' => now(),
            'completedCourseIds' => [],
            'dropOutPenaltyUntil' => null,
            'canMultiEnroll' => false,
            'extraCourseIds' => [],
        ]);

        $this->como($aluno['token'])
            ->post('/aluno/suporte', ['texto' => 'Não entendi o exemplo.', 'aula' => $aula->id]);

        $texto = DirectMessage::query()->where('studentUserId', $aluno['id'])->value('text');
        $this->assertSame('[Aula: '.trim((string) $aula->title).'] Não entendi o exemplo.', $texto);
    }

    /** Aula de curso alheio não entra no assunto: o id vem da URL. */
    public function test_aula_de_curso_alheio_nao_entra_no_assunto(): void
    {
        $aluno = $this->makeStudent('Aluno Suporte Alheio');
        $aula = Lesson::query()->first();
        $this->assertNotNull($aula, 'Seed sem aula.');

        $this->como($aluno['token'])
            ->post('/aluno/suporte', ['texto' => 'Mensagem simples.', 'aula' => $aula->id]);

        $texto = DirectMessage::query()->where('studentUserId', $aluno['id'])->value('text');
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

        $this->assertFalse(DirectMessage::query()->where('studentUserId', $aluno['id'])->exists());
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
