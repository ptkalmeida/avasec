<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Http\Middleware\JwtAuthenticate;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Usuario;
use App\Models\VersaoDeConteudo;
use App\Services\DocumentTemplateService;
use App\Services\SitePageContentService;
use App\Services\VersoesDeConteudo;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * O histórico de versões (ADR 021): página do site, modelo de documento e aula
 * eram SUBSTITUÍDOS ao salvar. Agora o que ia se perder fica guardado, e dá para
 * voltar — sem que voltar apague nada.
 */
final class VersoesDeConteudoTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array{sub:string,name:string,role:string} */
    private function admin(): array
    {
        $admin = Usuario::query()->where('papel', 'admin')->firstOrFail();

        return ['sub' => $admin->id, 'nome' => $admin->nome, 'papel' => 'admin'];
    }

    private function versoes(): VersoesDeConteudo
    {
        return app(VersoesDeConteudo::class);
    }

    // ---- páginas do site -------------------------------------------------------

    public function test_salvar_pagina_guarda_o_texto_que_estava_no_ar_inclusive_o_de_fabrica(): void
    {
        $servico = app(SitePageContentService::class);
        $fabrica = $servico->conteudoDaPagina('o-ava');
        $admin = $this->admin();

        $servico->upsert('o-ava', array_merge($fabrica, ['titulo' => 'Primeiro título']), $admin);
        $servico->upsert('o-ava', array_merge($fabrica, ['titulo' => 'Segundo título']), $admin);

        $lista = $this->versoes()->listar(VersoesDeConteudo::PAGINA_DO_SITE, 'o-ava');

        $this->assertCount(2, $lista);
        // A mais recente primeiro: o que estava no ar antes do SEGUNDO salvamento.
        $this->assertSame('Primeiro título', $lista[0]['conteudo']['titulo']);
        // E a outra é o texto de fábrica, que antes se perdia no primeiro salvamento.
        $this->assertSame($fabrica['titulo'], $lista[1]['conteudo']['titulo']);
        $this->assertSame($admin['nome'], $lista[0]['porNome']);
        $this->assertSame('edicao', $lista[0]['origem']);
    }

    public function test_salvar_sem_mudar_nada_nao_cria_versao(): void
    {
        $servico = app(SitePageContentService::class);
        $admin = $this->admin();
        $servico->upsert('duvidas', array_merge($servico->conteudoDaPagina('duvidas'), ['titulo' => 'Estável']), $admin);
        $antes = VersaoDeConteudo::query()->where('referencia', 'duvidas')->count();

        $servico->upsert('duvidas', $servico->conteudoDaPagina('duvidas'), $admin);

        $this->assertSame($antes, VersaoDeConteudo::query()->where('referencia', 'duvidas')->count());
    }

    public function test_restaurar_pagina_traz_o_texto_de_volta_e_guarda_o_que_estava_no_ar(): void
    {
        $servico = app(SitePageContentService::class);
        $admin = $this->admin();
        $base = $servico->conteudoDaPagina('noticias');

        $servico->upsert('noticias', array_merge($base, ['titulo' => 'Versão A']), $admin);
        $servico->upsert('noticias', array_merge($base, ['titulo' => 'Versão B']), $admin);
        $versaoA = $this->versoes()->listar(VersoesDeConteudo::PAGINA_DO_SITE, 'noticias')[0];
        $this->assertSame('Versão A', $versaoA['conteudo']['titulo']);

        $servico->restaurarVersao('noticias', $versaoA['id'], $admin);

        $this->assertSame('Versão A', $servico->get('noticias')['titulo']);
        $titulos = array_map(
            static fn (array $v): mixed => $v['conteudo']['titulo'],
            $this->versoes()->listar(VersoesDeConteudo::PAGINA_DO_SITE, 'noticias'),
        );
        // A "Versão B", que estava no ar na hora de restaurar, está no histórico: dá para desfazer.
        $this->assertContains('Versão B', $titulos);
    }

    public function test_versao_de_outra_pagina_nao_restaura_em_cima_desta(): void
    {
        $servico = app(SitePageContentService::class);
        $admin = $this->admin();
        $servico->upsert('o-ava', array_merge($servico->conteudoDaPagina('o-ava'), ['titulo' => 'Do O-AVA']), $admin);
        $servico->upsert('o-ava', array_merge($servico->conteudoDaPagina('o-ava'), ['titulo' => 'Outro']), $admin);
        $deOAva = $this->versoes()->listar(VersoesDeConteudo::PAGINA_DO_SITE, 'o-ava')[0]['id'];

        $this->expectException(ApiException::class);
        $servico->restaurarVersao('duvidas', $deOAva, $admin);
    }

    public function test_so_o_admin_restaura_pagina(): void
    {
        $servico = app(SitePageContentService::class);
        $gestor = Usuario::query()->where('papel', 'instructor')->firstOrFail();

        $this->expectException(ApiException::class);
        $servico->restaurarVersao('o-ava', 'qualquer', ['sub' => $gestor->id, 'nome' => $gestor->nome, 'papel' => 'instructor']);
    }

    public function test_a_tela_lista_as_versoes_e_o_botao_restaura(): void
    {
        $servico = app(SitePageContentService::class);
        $admin = $this->admin();
        $token = $this->staffToken('admin');
        $servico->upsert('orientacoes', array_merge($servico->conteudoDaPagina('orientacoes'), ['titulo' => 'Texto Antigo Na Tela']), $admin);
        $servico->upsert('orientacoes', array_merge($servico->conteudoDaPagina('orientacoes'), ['titulo' => 'Texto Novo Na Tela']), $admin);
        $versao = $this->versoes()->listar(VersoesDeConteudo::PAGINA_DO_SITE, 'orientacoes')[0]['id'];

        $tela = $this->como($token)->get('/admin/paginas-do-site?pagina=orientacoes');
        $tela->assertOk();
        $tela->assertSee('Versões anteriores');
        $tela->assertSee('Texto Antigo Na Tela');
        $tela->assertSee('/admin/paginas-do-site/orientacoes/versoes/'.$versao.'/restaurar', false);

        $this->como($token)->post('/admin/paginas-do-site/orientacoes/versoes/'.$versao.'/restaurar')
            ->assertRedirect('/admin/paginas-do-site?pagina=orientacoes');

        $this->assertSame('Texto Antigo Na Tela', $servico->get('orientacoes')['titulo']);
    }

    public function test_restaurar_por_http_exige_admin_e_versao_da_propria_pagina(): void
    {
        $servico = app(SitePageContentService::class);
        $admin = $this->admin();
        $servico->upsert('orientacoes', array_merge($servico->conteudoDaPagina('orientacoes'), ['titulo' => 'A']), $admin);
        $servico->upsert('orientacoes', array_merge($servico->conteudoDaPagina('orientacoes'), ['titulo' => 'B']), $admin);
        $versao = $this->versoes()->listar(VersoesDeConteudo::PAGINA_DO_SITE, 'orientacoes')[0]['id'];
        $aluno = $this->makeStudent('Aluno Restaura Pagina');

        // O visitante vem primeiro: o cookie de `como()` persiste no cliente de teste.
        $this->post('/admin/paginas-do-site/orientacoes/versoes/'.$versao.'/restaurar')->assertRedirect('/entrar');
        $this->como($aluno['token'])->post('/admin/paginas-do-site/orientacoes/versoes/'.$versao.'/restaurar')->assertRedirect('/aluno');
        $this->assertSame('B', $servico->get('orientacoes')['titulo']);

        // Versão da página errada: 404, e nada muda.
        $this->como($this->staffToken('admin'))->post('/admin/paginas-do-site/duvidas/versoes/'.$versao.'/restaurar')->assertNotFound();
        $this->assertSame('B', $servico->get('orientacoes')['titulo']);
    }

    // ---- modelos de documento --------------------------------------------------

    public function test_salvar_modelo_guarda_o_anterior_e_restaurar_devolve(): void
    {
        $servico = app(DocumentTemplateService::class);
        $admin = $this->admin();

        $servico->upsert('certificado', ['nome_instituicao' => 'Instituição Um', 'texto_rodape' => 'Rodapé um'], $admin);
        $servico->upsert('certificado', ['nome_instituicao' => 'Instituição Dois'], $admin);

        $lista = $this->versoes()->listar(VersoesDeConteudo::MODELO_DE_DOCUMENTO, 'certificado');
        $this->assertSame('Instituição Um', $lista[0]['conteudo']['nome_instituicao']);

        $servico->restaurarVersao('certificado', $lista[0]['id'], $admin);

        $this->assertSame('Instituição Um', $servico->get('certificado')['nome_instituicao']);
        $nomes = array_map(
            static fn (array $v): mixed => $v['conteudo']['nome_instituicao'] ?? null,
            $this->versoes()->listar(VersoesDeConteudo::MODELO_DE_DOCUMENTO, 'certificado'),
        );
        $this->assertContains('Instituição Dois', $nomes);
    }

    public function test_a_tela_de_modelos_mostra_o_historico(): void
    {
        $servico = app(DocumentTemplateService::class);
        $admin = $this->admin();
        $servico->upsert('historico', ['nome_instituicao' => 'Nome Antigo Do Modelo'], $admin);
        $servico->upsert('historico', ['nome_instituicao' => 'Nome Novo Do Modelo'], $admin);

        $this->como($this->staffToken('admin'))->get('/admin/templates?tipo=historico')
            ->assertOk()
            ->assertSee('Versões anteriores')
            ->assertSee('Nome Antigo Do Modelo');
    }

    // ---- aulas -----------------------------------------------------------------

    /** @return array{0:array{id:string,name:string,token:string},1:Curso,2:Aula} */
    private function aulaDeInstrutor(): array
    {
        $id = (string) Str::uuid();
        Usuario::query()->create([
            'id' => $id, 'nome' => 'Gestor Versoes', 'email' => Str::lower(Str::random(10)).'@example.com',
            'senha_hash' => password_hash('senha123456', PASSWORD_BCRYPT), 'papel' => 'instructor',
        ]);
        $curso = Curso::query()->create([
            'id' => 'course-versoes-'.uniqid(), 'titulo' => 'Curso Das Versoes',
            'descricao' => 'Descrição longa o bastante para o validador aceitar.', 'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png', 'instrutor_id' => $id, 'nome_instrutor' => 'Gestor Versoes',
        ]);
        Curso::query()->whereKey($curso->id)->update(['slug' => 'curso-das-versoes-'.Str::lower(Str::random(5)), 'status' => Visibilidade::PUBLICADO]);
        $aula = Aula::query()->create([
            'id' => 'lesson-versoes-'.uniqid(), 'curso_id' => $curso->id, 'titulo' => 'Aula Original',
            'duracao' => '30 min', 'conteudo' => "## Seção original\n\nTexto original da aula.", 'ordem' => 1,
        ]);

        return [['id' => $id, 'nome' => 'Gestor Versoes', 'token' => Jwt::issue($id, 'Gestor Versoes', 'instructor')], $curso->refresh(), $aula];
    }

    public function test_alterar_a_aula_guarda_a_versao_anterior_com_autor(): void
    {
        [$eu, $curso, $aula] = $this->aulaDeInstrutor();
        $base = '/inst/curso/'.$curso->slug.'/aulas/'.$aula->id;

        $this->como($eu['token'])->post($base.'/identificacao', ['titulo' => 'Aula Renomeada', 'carga' => '45 min'])
            ->assertRedirect();

        $lista = $this->versoes()->listar(VersoesDeConteudo::AULA, $aula->id);
        $this->assertCount(1, $lista);
        $this->assertSame('Aula Original', $lista[0]['conteudo']['titulo']);
        $this->assertSame('30 min', $lista[0]['conteudo']['duracao']);
        $this->assertSame("## Seção original\n\nTexto original da aula.", $lista[0]['conteudo']['conteudo']);
        $this->assertSame('Gestor Versoes', $lista[0]['porNome']);
    }

    public function test_salvar_a_aula_sem_mudar_nada_nao_cria_versao(): void
    {
        [$eu, $curso, $aula] = $this->aulaDeInstrutor();
        $base = '/inst/curso/'.$curso->slug.'/aulas/'.$aula->id;

        $this->como($eu['token'])->post($base.'/identificacao', ['titulo' => 'Aula Original', 'carga' => '30 min'])->assertRedirect();

        $this->assertSame(0, VersaoDeConteudo::query()->where('tipo', 'aula')->where('referencia', $aula->id)->count());
    }

    public function test_a_pagina_da_aula_mostra_o_historico_e_o_botao_restaura(): void
    {
        [$eu, $curso, $aula] = $this->aulaDeInstrutor();
        $base = '/inst/curso/'.$curso->slug.'/aulas/'.$aula->id;
        $this->como($eu['token'])->post($base.'/identificacao', ['titulo' => 'Aula Renomeada', 'carga' => '45 min']);

        $versao = $this->versoes()->listar(VersoesDeConteudo::AULA, $aula->id)[0]['id'];

        $tela = $this->como($eu['token'])->get($base);
        $tela->assertOk();
        $tela->assertSee('Histórico de versões');
        $tela->assertSee('Aula Original');
        $tela->assertSee($base.'/versoes/'.$versao.'/restaurar', false);

        $this->como($eu['token'])->post($base.'/versoes/'.$versao.'/restaurar')->assertRedirect($base);

        $depois = Aula::query()->findOrFail($aula->id);
        $this->assertSame('Aula Original', $depois->titulo);
        $this->assertSame('30 min', $depois->duracao);
        // O que estava no ar na hora de restaurar também ficou guardado.
        $titulos = array_map(
            static fn (array $v): mixed => $v['conteudo']['titulo'],
            $this->versoes()->listar(VersoesDeConteudo::AULA, $aula->id),
        );
        $this->assertContains('Aula Renomeada', $titulos);
    }

    public function test_instrutor_alheio_nao_restaura_nem_ve_o_historico(): void
    {
        [$dono, $curso, $aula] = $this->aulaDeInstrutor();
        $base = '/inst/curso/'.$curso->slug.'/aulas/'.$aula->id;
        $this->como($dono['token'])->post($base.'/identificacao', ['titulo' => 'Aula Renomeada', 'carga' => '45 min']);
        $versao = $this->versoes()->listar(VersoesDeConteudo::AULA, $aula->id)[0]['id'];

        $outroId = (string) Str::uuid();
        Usuario::query()->create([
            'id' => $outroId, 'nome' => 'Outro Gestor', 'email' => Str::lower(Str::random(10)).'@example.com',
            'senha_hash' => password_hash('senha123456', PASSWORD_BCRYPT), 'papel' => 'instructor',
        ]);
        $token = Jwt::issue($outroId, 'Outro Gestor', 'instructor');

        $this->como($token)->get($base)->assertNotFound();
        $this->como($token)->post($base.'/versoes/'.$versao.'/restaurar')->assertNotFound();
        $this->como($token)->post($base.'/previa', ['indice' => 0, 'tipo' => 'paragrafo', 'texto' => 'x'])->assertNotFound();

        $this->assertSame('Aula Renomeada', Aula::query()->findOrFail($aula->id)->titulo);
    }

    public function test_versao_de_outra_aula_nao_restaura_nesta(): void
    {
        [$eu, $curso, $aula] = $this->aulaDeInstrutor();
        $base = '/inst/curso/'.$curso->slug.'/aulas/'.$aula->id;
        $this->como($eu['token'])->post($base.'/identificacao', ['titulo' => 'Aula Renomeada', 'carga' => '45 min']);
        $versao = $this->versoes()->listar(VersoesDeConteudo::AULA, $aula->id)[0]['id'];

        $segunda = Aula::query()->create([
            'id' => 'lesson-versoes-2-'.uniqid(), 'curso_id' => $curso->id, 'titulo' => 'Segunda Aula',
            'duracao' => '10 min', 'conteudo' => 'Texto da segunda.', 'ordem' => 2,
        ]);

        $this->como($eu['token'])
            ->post('/inst/curso/'.$curso->slug.'/aulas/'.$segunda->id.'/versoes/'.$versao.'/restaurar')
            ->assertNotFound();
        $this->assertSame('Segunda Aula', Aula::query()->findOrFail($segunda->id)->titulo);
    }

    // ---- prévia ao vivo --------------------------------------------------------

    public function test_a_previa_mostra_o_trecho_como_o_aluno_ve_sem_gravar(): void
    {
        [$eu, $curso, $aula] = $this->aulaDeInstrutor();
        $base = '/inst/curso/'.$curso->slug.'/aulas/'.$aula->id;
        $original = Aula::query()->findOrFail($aula->id)->conteudo;

        $resposta = $this->como($eu['token'])->post($base.'/previa', [
            'indice' => 1, 'tipo' => 'paragrafo', 'texto' => 'Texto com **destaque** novo.',
            'tamanho' => 'normal', 'alinhamento' => 'centro',
        ]);

        $resposta->assertOk();
        $resposta->assertSee('Texto com', false);
        $resposta->assertSee('<strong', false);
        $resposta->assertSee('destaque', false);
        $this->assertSame($original, Aula::query()->findOrFail($aula->id)->conteudo);
        $this->assertSame(0, VersaoDeConteudo::query()->where('tipo', 'aula')->where('referencia', $aula->id)->count());
    }

    public function test_a_previa_escapa_html_digitado(): void
    {
        [$eu, $curso, $aula] = $this->aulaDeInstrutor();
        $base = '/inst/curso/'.$curso->slug.'/aulas/'.$aula->id;

        $resposta = $this->como($eu['token'])->post($base.'/previa', [
            'indice' => 1, 'tipo' => 'paragrafo', 'texto' => '<script>alert(1)</script>',
            'tamanho' => 'normal', 'alinhamento' => 'esquerda',
        ]);

        $resposta->assertOk();
        $resposta->assertDontSee('<script>alert(1)</script>', false);
        $resposta->assertSee('&lt;script&gt;', false);
    }

    public function test_a_previa_de_trecho_invalido_devolve_422_com_a_regra(): void
    {
        [$eu, $curso, $aula] = $this->aulaDeInstrutor();
        $base = '/inst/curso/'.$curso->slug.'/aulas/'.$aula->id;

        $resposta = $this->como($eu['token'])->post($base.'/previa', ['indice' => 99, 'tipo' => 'paragrafo', 'texto' => 'x']);

        $resposta->assertStatus(422);
        $resposta->assertSee('Esse trecho não existe mais', false);
    }

    public function test_a_previa_exige_login(): void
    {
        $this->post('/inst/curso/x/aulas/y/previa', ['indice' => 0])->assertRedirect('/entrar');
    }
}
