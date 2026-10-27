<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\Usuario;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * "Equipe Pedagógica" do painel administrativo (fatia 3 do M5): lista de
 * gestores, as trilhas de cada um e busca por nome. Somente leitura.
 */
final class EquipeDoAdminTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    private function gestor(string $nome, string $status = 'active'): Usuario
    {
        return Usuario::query()->create([
            'id' => (string) Str::uuid(),
            'nome' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'senha_hash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'papel' => 'instructor',
            'status' => $status,
        ]);
    }

    private function curso(string $titulo, string $gestorId): Curso
    {
        $curso = Curso::query()->create([
            'id' => 'course-eq-'.uniqid(),
            'titulo' => $titulo,
            'descricao' => 'd',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'instrutor_id' => $gestorId,
            'nome_instrutor' => 'Gestor',
        ]);
        Curso::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
        ]);

        return $curso;
    }

    public function test_so_o_admin_entra(): void
    {
        $this->get('/admin/equipe')->assertRedirect('/entrar');

        $aluno = $this->makeStudent('Aluno Equipe');
        $this->como($aluno['token'])->get('/admin/equipe')->assertRedirect('/aluno');

        $gestor = $this->gestor('Gestor Equipe Fora');
        $this->como(Jwt::issue($gestor->id, $gestor->nome, 'instructor'))->get('/admin/equipe')->assertRedirect('/inst');
    }

    public function test_lista_o_gestor_com_as_trilhas_dele(): void
    {
        $gestor = $this->gestor('Gestora Zuleica Equipe');
        $this->curso('Trilha Zuleica Um', $gestor->id);
        $this->curso('Trilha Zuleica Dois', $gestor->id);

        $resposta = $this->como($this->staffToken('admin'))->get('/admin/equipe');

        $resposta->assertOk();
        $resposta->assertDontSee('<div id="root">', false);
        $resposta->assertSee('Gestora Zuleica Equipe');
        $resposta->assertSee('Trilhas sob gestão: 2');
        $resposta->assertSee('Trilha Zuleica Um');
        $resposta->assertSee('Trilha Zuleica Dois');
    }

    public function test_gestor_sem_trilha_diz_que_nao_ha_disciplina(): void
    {
        $this->gestor('Gestor Sem Trilha Xyz');

        $resposta = $this->como($this->staffToken('admin'))->get('/admin/equipe?busca=Sem+Trilha+Xyz');

        $resposta->assertSee('Gestor Sem Trilha Xyz');
        $resposta->assertSee('Trilhas sob gestão: 0');
        $resposta->assertSee('Nenhuma disciplina vinculada');
    }

    public function test_a_busca_filtra_por_nome_e_nao_trata_curinga_como_curinga(): void
    {
        $this->gestor('Gestor Busca Alfa');
        $this->gestor('Gestor Busca Beta');
        $token = $this->staffToken('admin');

        $alfa = $this->como($token)->get('/admin/equipe?busca=busca+alfa');
        $alfa->assertSee('Gestor Busca Alfa');
        $alfa->assertDontSee('Gestor Busca Beta');

        // `%` digitado é texto: não pode casar com todo mundo.
        $curinga = $this->como($token)->get('/admin/equipe?busca=%25');
        $curinga->assertDontSee('Gestor Busca Alfa');
        $curinga->assertSee('Nenhum gestor com esse nome.');
    }

    public function test_gestor_bloqueado_mostra_o_selo_e_inativado_some(): void
    {
        $this->gestor('Gestor Bloqueado Zzq', 'blocked');
        $inativo = $this->gestor('Gestor Inativado Zzq');
        $inativo->inativar($inativo->id, 'teste');

        $resposta = $this->como($this->staffToken('admin'))->get('/admin/equipe?busca=Zzq');

        $resposta->assertSee('Gestor Bloqueado Zzq');
        $resposta->assertSee('Bloqueado');

        // O retirado não é um gestor ativo: fora da lista da equipe, mas — desde 26/10/2026 —
        // presente na seção "Gestores retirados da equipe", em vez de sumir da tela (o "limbo").
        // `RetiradosDoAdminTest` cobre a reativação.
        $html = $resposta->getContent();
        $this->assertIsString($html);
        $secao = strpos($html, 'Gestores retirados da equipe (');
        $this->assertNotFalse($secao, 'A seção de retirados precisa existir.');
        $this->assertStringNotContainsString('Gestor Inativado Zzq', substr($html, 0, $secao), 'O retirado não pode estar entre os ativos.');
        $this->assertStringContainsString('Gestor Inativado Zzq', substr($html, $secao), 'O retirado precisa estar na seção de retirados.');
    }

    public function test_nada_do_que_era_escrito_no_codigo_volta(): void
    {
        $resposta = $this->como($this->staffToken('admin'))->get('/admin/equipe');

        $resposta->assertDontSee('Design de Interfaces');
        $resposta->assertDontSee('Especialidade Principal');
        $resposta->assertDontSee('GESTOR-01');
    }
}
