<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Course;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\Jwt;
use Tests\TestCase;

/**
 * Área do aluno em Blade — telas 3 e 4 do redesenho (plano 18, fase M3).
 *
 * O arquivo tem dois assuntos, e o primeiro é o que importa mais: **quem
 * alcança a tela**. Ela passou a existir fora da API, com um middleware novo, e
 * um erro aqui mostra o registro acadêmico de um aluno para quem não é ele.
 *
 * O segundo assunto é o desenho aprovado: a lista de aulas uma única vez por
 * tela, o texto "X de Y aulas concluídas" sem percentual repetido, e a ausência
 * do que a skill mandou remover.
 *
 * Os dados vêm do banco de desenvolvimento, como no resto deste diretório —
 * nada é criado nem apagado (ADR 12).
 */
final class AreaDoAlunoBladeTest extends TestCase
{
    /**
     * Um aluno ativo, preferindo quem TEM curso em andamento.
     *
     * A preferência não é detalhe: sem ela, a escolha caía num aluno sem
     * matrícula e metade deste arquivo se pulava sozinha. Teste pulado parece
     * teste verde no resumo, e esta é a parte que guarda registro acadêmico.
     */
    private function aluno(): User
    {
        $base = User::query()
            ->where('role', 'student')
            ->where('status', 'active')
            ->whereNull('inativadoEm');

        $comCurso = (clone $base)
            ->whereIn('id', $this->matriculadosComCurso())
            ->first();

        $aluno = $comCurso ?? $base->first();

        if ($aluno === null) {
            $this->markTestSkipped('Sem aluno ativo no banco de desenvolvimento.');
        }

        return $aluno;
    }

    /**
     * `withUnencryptedCookie`, e não `withCookie`.
     *
     * `ava_session` está na lista de exceção do `encryptCookies` (ele é emitido
     * como JWT, não pelo cofre de cookies do Laravel). O `withCookie` dos
     * testes CRIPTOGRAFA o valor esperando que a aplicação o decifre — e, como
     * ela não decifra este, chegaria um texto cifrado onde se espera um JWT. O
     * sintoma é ótimo para perder uma tarde: todo teste autenticado "desloga".
     */
    private function comoAluno(User $aluno, ?string $papelNoToken = null): self
    {
        return $this->withUnencryptedCookie(
            JwtAuthenticate::SESSION_COOKIE,
            Jwt::issue((string) $aluno->id, (string) $aluno->name, $papelNoToken ?? (string) $aluno->role),
        );
    }

    // ---------- quem alcança ----------

    public function test_visitante_sem_token_vai_para_o_login_e_nao_ve_a_tela(): void
    {
        $resposta = $this->get('/aluno');

        $resposta->assertRedirect('/entrar');
    }

    public function test_token_adulterado_vai_para_o_login_em_vez_de_quebrar(): void
    {
        $resposta = $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, 'nao.e.um.jwt')->get('/aluno');

        $resposta->assertRedirect('/entrar');
    }

    /**
     * Gestor e admin não são barrados por erro — eles têm painel próprio. O que
     * não pode é a área do aluno responder para eles como se fossem alunos.
     */
    public function test_quem_nao_e_aluno_nao_entra_na_area_do_aluno(): void
    {
        $gestor = User::query()->whereIn('role', ['instructor', 'admin'])->whereNull('inativadoEm')->first();

        if ($gestor === null) {
            $this->markTestSkipped('Sem gestor/admin no banco de desenvolvimento.');
        }

        $resposta = $this->comoAluno($gestor)->get('/aluno');

        $resposta->assertRedirect('/');
    }

    /**
     * O papel vem do BANCO, nunca do token: um token de 12h pode descrever um
     * papel que já mudou. Aqui o token diz "admin" para um usuário que é aluno,
     * e a tela tem de tratá-lo como aluno.
     */
    public function test_papel_do_token_nao_sobrepoe_o_papel_do_banco(): void
    {
        $aluno = $this->aluno();

        $resposta = $this->comoAluno($aluno, papelNoToken: 'admin')->get('/aluno');

        $resposta->assertOk();
        $resposta->assertSee('Olá, '.$aluno->name, escape: false);
    }

    // ---------- tela 3: painel ----------

    public function test_painel_sauda_pelo_nome_e_nao_usa_foto_de_perfil(): void
    {
        $aluno = $this->aluno();

        $resposta = $this->comoAluno($aluno)->get('/aluno');

        $resposta->assertOk();
        $resposta->assertSee('Olá, '.$aluno->name, escape: false);

        // A skill proíbe foto de perfil: a pessoa é identificada por texto.
        $resposta->assertDontSee('avatar', escape: false);
    }

    /**
     * As remoções aprovadas em 19/10. Teste de ausência, porque é o único jeito
     * de impedir que voltem por reflexo num ajuste de layout.
     */
    public function test_painel_nao_traz_o_que_a_skill_removeu(): void
    {
        $resposta = $this->comoAluno($this->aluno())->get('/aluno');

        foreach (['Presença média', 'Curso ativo', 'Meu progresso', 'Início da Jornada', 'Disciplinas acadêmicas disponíveis'] as $removido) {
            $resposta->assertDontSee($removido, escape: false);
        }
    }

    /**
     * "Tirar do painel sem tirar a função": as duas funções que viviam nos
     * blocos removidos continuam alcançáveis pelo menu lateral.
     */
    public function test_as_funcoes_removidas_do_painel_continuam_alcancaveis(): void
    {
        $resposta = $this->comoAluno($this->aluno())->get('/aluno');

        // Matrícula em outro curso, que vivia em "Disciplinas disponíveis".
        $resposta->assertSee('href="/aluno/catalogo"', escape: false);
        // Cursos concluídos, que eram seção da página.
        $resposta->assertSee('Cursos concluídos', escape: false);
    }

    public function test_painel_mostra_aulas_concluidas_sem_repetir_o_percentual(): void
    {
        $aluno = $this->aluno();

        if ($this->cursoDoAluno($aluno) === null) {
            $this->markTestSkipped('O aluno de desenvolvimento não tem curso em andamento.');
        }

        $html = (string) $this->comoAluno($aluno)->get('/aluno')->getContent();

        $this->assertMatchesRegularExpression(
            '/\d+ de \d+ aulas concluídas/u',
            $html,
            'O painel deveria dizer quantas aulas foram concluídas, de quantas.',
        );

        // O percentual existe só como largura da barra e `aria-valuenow`; como
        // TEXTO ele seria o número repetido que a skill proíbe.
        $this->assertDoesNotMatchRegularExpression(
            '/>\s*\d{1,3}%\s*</u',
            $html,
            'O percentual não pode aparecer como texto ao lado da contagem de aulas.',
        );
    }

    // ---------- tela 4: navegação do curso ----------

    /**
     * O curso que o aluno cursa — por `enrolledCourseId` **ou** por
     * `extraCourseIds`.
     *
     * Olhar só a primeira coluna não é simplificação: o aluno do banco de
     * desenvolvimento tem exatamente o segundo caso (matrícula concorrente
     * concedida pela coordenação), e um teste que olhasse só `enrolledCourseId`
     * se pularia sozinho justamente onde há dado para conferir.
     */
    private function cursoDoAluno(User $aluno): ?string
    {
        $matricula = StudentEnrollment::query()->where('userId', $aluno->id)->first();

        if (is_string($matricula?->enrolledCourseId) && $matricula->enrolledCourseId !== '') {
            return $matricula->enrolledCourseId;
        }

        $extras = is_array($matricula?->extraCourseIds) ? $matricula->extraCourseIds : [];
        foreach ($extras as $id) {
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    /** @return list<string> ids de alunos que têm algum curso em andamento. */
    private function matriculadosComCurso(): array
    {
        $ids = [];

        foreach (StudentEnrollment::query()->get(['userId', 'enrolledCourseId', 'extraCourseIds']) as $linha) {
            $temPrincipal = is_string($linha->enrolledCourseId) && $linha->enrolledCourseId !== '';
            $temExtra = is_array($linha->extraCourseIds) && $linha->extraCourseIds !== [];

            if ($temPrincipal || $temExtra) {
                $ids[] = (string) $linha->userId;
            }
        }

        return $ids;
    }

    public function test_curso_do_aluno_abre_e_a_lista_de_aulas_aparece_uma_unica_vez(): void
    {
        $aluno = $this->aluno();
        $cursoId = $this->cursoDoAluno($aluno);

        if ($cursoId === null) {
            $this->markTestSkipped('O aluno de desenvolvimento não tem curso em andamento.');
        }

        // Segue o redirecionamento: o id leva ao endereço de hoje (slug), e é
        // a tela de lá que interessa aqui.
        $html = (string) $this->comoAluno($aluno)->followingRedirects()->get('/aluno/curso/'.$cursoId)->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'Aulas do curso'),
            'A lista de aulas apareceu mais de uma vez na mesma tela — é o controle duplicado que a skill proíbe.',
        );
    }

    public function test_curso_traz_as_acoes_aprovadas_e_nao_o_que_foi_removido(): void
    {
        $aluno = $this->aluno();
        $cursoId = $this->cursoDoAluno($aluno);

        if ($cursoId === null) {
            $this->markTestSkipped('O aluno de desenvolvimento não tem curso em andamento.');
        }

        $resposta = $this->comoAluno($aluno)->followingRedirects()->get('/aluno/curso/'.$cursoId);

        $resposta->assertOk();
        $resposta->assertSee('Iniciar aula', escape: false);
        $resposta->assertSee('Trocar de Curso', escape: false);

        foreach (['Instrutor responsável', 'Online', 'Frequência cumprida', 'SUA FREQUÊNCIA'] as $removido) {
            $resposta->assertDontSee($removido, escape: false);
        }
    }

    /**
     * Esta tela é "o MEU curso". Sem a conferência de matrícula ela abriria para
     * qualquer curso publicado, com a lista de aulas clicável e nada atrás.
     */
    public function test_curso_em_que_o_aluno_nao_esta_matriculado_responde_404(): void
    {
        $aluno = $this->aluno();
        $meu = $this->cursoDoAluno($aluno);

        $outro = Course::query()
            ->when($meu !== null, fn ($q) => $q->where('id', '!=', $meu))
            ->where('status', 1)
            ->first();

        if ($outro === null) {
            $this->markTestSkipped('Só existe um curso no banco de desenvolvimento.');
        }

        $this->comoAluno($aluno)->get('/aluno/curso/'.$outro->id)->assertNotFound();
    }

    public function test_curso_inexistente_responde_404_e_nao_500(): void
    {
        $this->comoAluno($this->aluno())->get('/aluno/curso/nao-existe')->assertNotFound();
    }

    /**
     * Link antigo (id) continua abrindo, mas leva ao endereço de hoje em vez de
     * responder no antigo — senão o endereço velho se propaga de tela em tela.
     */
    public function test_endereco_antigo_redireciona_para_o_canonico(): void
    {
        $aluno = $this->aluno();
        $cursoId = $this->cursoDoAluno($aluno);

        if ($cursoId === null) {
            $this->markTestSkipped('O aluno de desenvolvimento não tem curso em andamento.');
        }

        $slug = Course::query()->where('id', $cursoId)->value('slug');

        if (! is_string($slug) || $slug === '') {
            $this->markTestSkipped('O curso do aluno não tem slug.');
        }

        $this->comoAluno($aluno)->get('/aluno/curso/'.$cursoId)->assertRedirect('/aluno/curso/'.$slug);
    }

    public function test_area_do_aluno_nao_e_indexavel_por_buscador(): void
    {
        $this->comoAluno($this->aluno())->get('/aluno')->assertSee('noindex', escape: false);
    }
}
