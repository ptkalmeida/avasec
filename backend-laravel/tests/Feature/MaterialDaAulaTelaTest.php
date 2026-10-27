<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\Curso;
use App\Models\Aula;
use App\Models\Usuario;
use App\Services\CourseService;
use App\Support\Jwt;
use App\Support\Visibilidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * O material didático da aula na tela: `?editar=material` e `POST .../material`.
 *
 * O que prendem: que cada ação grava só o texto DESTA aula e só o trecho tocado,
 * que a trava de versão recusa a edição sobre um texto que já mudou, que remover
 * deixa o texto do trecho na auditoria, e que o que o editor recusa volta para o
 * formulário certo com o que a pessoa digitou.
 */
final class MaterialDaAulaTelaTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private const TEXTO = "## Primeira\n\nUm parágrafo.\n\n- um\n- dois\n\nCódigo 1: exemplo\n```java\nint a = 1;\n    int b = 2;\n```\n\n### Fim";

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array{id:string,name:string,token:string} */
    private function instrutor(string $nome): array
    {
        $id = (string) Str::uuid();
        Usuario::query()->create([
            'id' => $id,
            'nome' => $nome,
            'email' => Str::lower(Str::random(10)).'@example.com',
            'senha_hash' => password_hash('senha123456', PASSWORD_BCRYPT),
            'papel' => 'instructor',
        ]);

        return ['id' => $id, 'nome' => $nome, 'token' => Jwt::issue($id, $nome, 'instructor')];
    }

    /** @return array{0:Curso,1:Aula,2:Aula} */
    private function cursoComAulas(string $instrutor_id, string $titulo = 'Curso Do Material'): array
    {
        $curso = Curso::query()->create([
            'id' => 'course-mat-'.uniqid(),
            'titulo' => $titulo,
            'descricao' => 'Descrição longa o bastante para o validador aceitar.',
            'categoria' => 'Teste',
            'miniatura' => 'sem-imagem.png',
            'instrutor_id' => $instrutor_id,
            'nome_instrutor' => 'Instrutor',
        ]);
        Curso::query()->whereKey($curso->id)->update([
            'slug' => Str::slug($titulo).'-'.Str::lower(Str::random(5)),
            'status' => Visibilidade::PUBLICADO,
        ]);

        $a = Aula::query()->create([
            'id' => 'lesson-mat-'.$curso->id.'-1', 'curso_id' => $curso->id, 'titulo' => 'Aula do material',
            'duracao' => '30 min', 'conteudo' => self::TEXTO, 'ordem' => 1,
        ]);
        $b = Aula::query()->create([
            'id' => 'lesson-mat-'.$curso->id.'-2', 'curso_id' => $curso->id, 'titulo' => 'Outra aula',
            'duracao' => '10 min', 'conteudo' => 'Texto da outra aula.', 'ordem' => 2,
        ]);

        return [$curso->refresh(), $a, $b];
    }

    private function pagina(Curso $curso, Aula $aula, string $resto = ''): string
    {
        return '/inst/curso/'.$curso->slug.'/aulas/'.$aula->id.$resto;
    }

    private function conteudo(Aula $aula): string
    {
        return (string) Aula::query()->findOrFail($aula->id)->conteudo;
    }

    private function versao(Aula $aula): string
    {
        return CourseService::versaoDoConteudo($this->conteudo($aula));
    }

    /** @param array<string, mixed> $campos */
    private function acao(array $eu, Curso $curso, Aula $aula, array $campos, ?string $versao = null): TestResponse
    {
        return $this->como($eu['token'])->post($this->pagina($curso, $aula, '/material'), $campos + ['versao' => $versao ?? $this->versao($aula)]);
    }

    private function png(string $nome, int $kb = 0): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+P+/HgAFhAJ/wlseKgAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($nome, $bytes);
    }

    // ---- a zona ---------------------------------------------------------------

    public function test_a_zona_aberta_mostra_os_trechos_com_os_controles_e_a_contagem(): void
    {
        $eu = $this->instrutor('Gestor Material');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $resposta = $this->como($eu['token'])->get($this->pagina($curso, $aula).'?editar=material');

        $resposta->assertOk();
        $resposta->assertSee('Como funciona');
        $resposta->assertSee('Um parágrafo.');
        $resposta->assertSee('+ Adicionar', false);
        foreach (['Subir', 'Descer', 'Editar', 'Remover'] as $controle) {
            $resposta->assertSee($controle);
        }
        $resposta->assertSee('5 trechos');
        $resposta->assertSee('contém bloco de código');
        // O primeiro não sobe e o último não desce: 5 trechos, 4 setas para cada lado.
        $html = (string) $resposta->getContent();
        $this->assertSame(4, substr_count($html, 'name="direcao" value="up"'));
        $this->assertSame(4, substr_count($html, 'name="direcao" value="down"'));
    }

    public function test_fechada_a_zona_continua_so_leitura(): void
    {
        $eu = $this->instrutor('Gestor Fechada');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])->get($this->pagina($curso, $aula))
            ->assertOk()->assertSee('Como o aluno vê')->assertDontSee('+ Adicionar', false);
    }

    public function test_o_bloco_aberto_mostra_o_formulario_do_tipo_dele(): void
    {
        $eu = $this->instrutor('Gestor Bloco');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])->get($this->pagina($curso, $aula).'?editar=material&bloco=1')
            ->assertOk()->assertSee('Editando: parágrafo')->assertSee('Aplicar')->assertSee('Texto do parágrafo');
        $this->como($eu['token'])->get($this->pagina($curso, $aula).'?editar=material&bloco=3')
            ->assertOk()->assertSee('Editando: bloco de código')->assertSee('int a = 1;')->assertSee('Código 1: exemplo');
    }

    public function test_menu_adicionar_e_formularios_de_midia_abrem_pelo_endereco(): void
    {
        $eu = $this->instrutor('Gestor Menu');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->como($eu['token'])->get($this->pagina($curso, $aula).'?editar=material&adicionar=1')
            ->assertOk()->assertSee('Adicionar aqui')->assertSee('Subtítulo')->assertSee('Lista numerada')
            ->assertDontSee('Bloco de código</button>', false);
        $this->como($eu['token'])->get($this->pagina($curso, $aula).'?editar=material&novo=imagem&apos=1')
            ->assertOk()->assertSee('Descrição da imagem (obrigatória)')->assertSee('Arquivo da imagem');
        $this->como($eu['token'])->get($this->pagina($curso, $aula).'?editar=material&novo=video&apos=-1')
            ->assertOk()->assertSee('Link do vídeo no YouTube')->assertSee('Título do vídeo (obrigatório)');
    }

    // ---- mover -----------------------------------------------------------------

    public function test_mover_troca_os_trechos_e_so_toca_nesta_aula(): void
    {
        $eu = $this->instrutor('Gestor Mover');
        [$curso, $aula, $outra] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'mover', 'indice' => 1, 'direcao' => 'down'])
            ->assertRedirect($this->pagina($curso, $aula).'?editar=material#bloco-2')
            ->assertSessionHas('avisoDoCurso');

        $this->assertStringStartsWith("## Primeira\n\n- um\n- dois\n\nUm parágrafo.", $this->conteudo($aula));
        $this->assertStringContainsString("    int b = 2;\n", $this->conteudo($aula), 'a indentação do código é dele');
        $this->assertSame('Texto da outra aula.', $this->conteudo($outra));
    }

    public function test_mover_a_primeira_para_cima_nao_muda_nada(): void
    {
        $eu = $this->instrutor('Gestor Ponta');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'mover', 'indice' => 0, 'direcao' => 'up'])->assertRedirect();

        $this->assertSame(self::TEXTO, $this->conteudo($aula));
    }

    public function test_mover_o_que_viraria_legenda_e_recusado_e_o_texto_fica(): void
    {
        $eu = $this->instrutor('Gestor Legenda');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);
        Aula::query()->whereKey($aula->id)->update(['conteudo' => "Exemplo: veja o fluxo abaixo.\n\nOutro parágrafo.\n\n![fluxo](/uploads/f.png)"]);

        $this->acao($eu, $curso, $aula, ['acao' => 'mover', 'indice' => 0, 'direcao' => 'down'])
            ->assertSessionHasErrors('material');

        $this->assertStringStartsWith('Exemplo: veja o fluxo abaixo.', $this->conteudo($aula));
    }

    // ---- remover ---------------------------------------------------------------

    public function test_remover_tira_o_trecho_e_deixa_o_texto_dele_na_auditoria(): void
    {
        $eu = $this->instrutor('Gestor Remover');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'remover', 'indice' => 1])
            ->assertRedirect($this->pagina($curso, $aula).'?editar=material#material');

        $this->assertStringNotContainsString('Um parágrafo.', $this->conteudo($aula));
        $this->assertDoesNotMatchRegularExpression('/\n{3,}/', $this->conteudo($aula));

        $registro = DB::table('log_seguranca')->where('usuario', $eu['nome'])->where('detalhes', 'like', '%Trecho removido%')->first();
        $this->assertNotNull($registro, 'a remoção tem de ficar no registro');
        $this->assertStringContainsString('Um parágrafo.', $registro->detalhes);
        $this->assertSame('WARNING', $registro->status);
    }

    // ---- inserir ---------------------------------------------------------------

    public function test_inserir_modelo_abre_o_formulario_do_trecho_novo(): void
    {
        $eu = $this->instrutor('Gestor Inserir');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'inserir', 'apos' => 0, 'tipo' => 'subsecao'])
            ->assertRedirect($this->pagina($curso, $aula).'?editar=material&bloco=1#bloco-1');

        $this->assertStringStartsWith("## Primeira\n\n### Subtítulo\n\nUm parágrafo.", $this->conteudo($aula));
    }

    public function test_inserir_no_comeco(): void
    {
        $eu = $this->instrutor('Gestor Comeco');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'inserir', 'apos' => -1, 'tipo' => 'paragrafo']);

        $this->assertStringStartsWith("Escreva o parágrafo aqui.\n\n## Primeira", $this->conteudo($aula));
    }

    public function test_inserir_tipo_inventado_e_posicao_fora_da_faixa_sao_recusados(): void
    {
        $eu = $this->instrutor('Gestor Recusa Inserir');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'inserir', 'apos' => 0, 'tipo' => 'codigo'])->assertSessionHasErrors('material');
        $this->acao($eu, $curso, $aula, ['acao' => 'inserir', 'apos' => 99, 'tipo' => 'paragrafo'])->assertSessionHasErrors('material');

        $this->assertSame(self::TEXTO, $this->conteudo($aula));
    }

    public function test_inserir_video_complementar_e_avisar_quando_repete_o_principal(): void
    {
        $eu = $this->instrutor('Gestor Video Corpo');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);
        Aula::query()->whereKey($aula->id)->update(['url_video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->acao($eu, $curso, $aula, [
            'acao' => 'inserir', 'apos' => 1, 'tipo' => 'video', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'descricao' => 'Demonstração', 'legenda' => 'Vídeo 1 - demo',
        ])
            ->assertRedirect($this->pagina($curso, $aula).'?editar=material#bloco-2')
            ->assertSessionHas('avisosDoMaterial');

        $this->assertStringContainsString("Vídeo 1 - demo\n@[Demonstração](https://youtu.be/dQw4w9WgXcQ)", $this->conteudo($aula));
    }

    public function test_video_que_nao_e_reconhecido_volta_ao_formulario_com_o_digitado(): void
    {
        $eu = $this->instrutor('Gestor Video Ruim');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'inserir', 'apos' => 1, 'tipo' => 'video', 'url' => 'https://exemplo.com/x', 'descricao' => 'Meu título'])
            ->assertRedirect($this->pagina($curso, $aula).'?editar=material&novo=video&apos=1#novo')
            ->assertSessionHasErrors('material')
            ->assertSessionHasInput('descricao', 'Meu título');

        $this->assertSame(self::TEXTO, $this->conteudo($aula));
    }

    public function test_inserir_imagem_envia_o_arquivo_pelo_servico_da_api(): void
    {
        Storage::fake('publico');
        $eu = $this->instrutor('Gestor Imagem');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, [
            'acao' => 'inserir', 'apos' => 1, 'tipo' => 'imagem', 'descricao' => 'Mapa do campus', 'legenda' => 'Figura 1 - mapa', 'arquivo' => $this->png('mapa.png'),
        ])->assertRedirect($this->pagina($curso, $aula).'?editar=material#bloco-2');

        preg_match('~Figura 1 - mapa\n!\[Mapa do campus\]\((/uploads/[^)]+)\)~', $this->conteudo($aula), $m);
        $this->assertNotEmpty($m, 'a imagem entrou no texto');
        Storage::disk('publico')->assertExists(basename($m[1]));
    }

    public function test_imagem_sem_arquivo_ou_sem_descricao_ou_de_tipo_errado_e_recusada(): void
    {
        Storage::fake('publico');
        $eu = $this->instrutor('Gestor Imagem Ruim');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'inserir', 'apos' => 1, 'tipo' => 'imagem', 'descricao' => 'x'])->assertSessionHasErrors('material');
        $this->acao($eu, $curso, $aula, ['acao' => 'inserir', 'apos' => 1, 'tipo' => 'imagem', 'descricao' => '', 'arquivo' => $this->png('a.png')])->assertSessionHasErrors('material');
        // PDF passa no UploadService da API, mas não cabe num bloco de imagem.
        $pdf = UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF");
        $this->acao($eu, $curso, $aula, ['acao' => 'inserir', 'apos' => 1, 'tipo' => 'imagem', 'descricao' => 'x', 'arquivo' => $pdf])->assertSessionHasErrors('material');

        $this->assertSame(self::TEXTO, $this->conteudo($aula));
    }

    // ---- atualizar -------------------------------------------------------------

    public function test_atualizar_um_paragrafo_troca_so_o_trecho(): void
    {
        $eu = $this->instrutor('Gestor Atualiza');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'atualizar', 'indice' => 1, 'tipo' => 'paragrafo', 'texto' => 'Texto trocado.', 'tamanho' => 'grande', 'alinhamento' => 'centro'])
            ->assertRedirect($this->pagina($curso, $aula).'?editar=material#bloco-1');

        $this->assertSame(str_replace('Um parágrafo.', '{centro grande} Texto trocado.', self::TEXTO), $this->conteudo($aula));
    }

    public function test_converter_o_tipo_do_trecho(): void
    {
        $eu = $this->instrutor('Gestor Converte');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'atualizar', 'indice' => 2, 'tipo' => 'secao', 'texto' => "um\ndois"]);

        $this->assertStringContainsString("## um dois\n\nCódigo 1: exemplo", $this->conteudo($aula));
    }

    public function test_atualizar_o_codigo_preserva_a_indentacao(): void
    {
        $eu = $this->instrutor('Gestor Codigo');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'atualizar', 'indice' => 3, 'linguagem' => 'java', 'legenda' => 'Código 1: exemplo', 'codigo' => "int a = 2;\n    int b = 3;"]);

        $this->assertStringContainsString("```java\nint a = 2;\n    int b = 3;\n```", $this->conteudo($aula));
    }

    public function test_atualizar_imagem_sem_arquivo_novo_fica_com_o_endereco_gravado_no_texto(): void
    {
        $eu = $this->instrutor('Gestor Imagem Fica');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);
        Aula::query()->whereKey($aula->id)->update(['conteudo' => "Texto antes.\n\n![Antiga](/uploads/original.png)"]);

        // O endereço NUNCA vem do formulário: mandar outro é ignorado.
        $this->acao($eu, $curso, $aula, ['acao' => 'atualizar', 'indice' => 1, 'tipo' => 'imagem', 'url' => 'https://evil.example/x.png', 'descricao' => 'Nova descrição']);

        $this->assertSame("Texto antes.\n\n![Nova descrição](/uploads/original.png)", $this->conteudo($aula));
    }

    public function test_atualizar_recusado_reabre_o_formulario_do_bloco_com_o_digitado(): void
    {
        $eu = $this->instrutor('Gestor Recusa Atualiza');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'atualizar', 'indice' => 1, 'tipo' => 'paragrafo', 'texto' => '   '])
            ->assertRedirect($this->pagina($curso, $aula).'?editar=material&bloco=1#bloco-1')
            ->assertSessionHasErrors('material');
        $this->acao($eu, $curso, $aula, ['acao' => 'atualizar', 'indice' => 1, 'tipo' => 'paragrafo', 'texto' => 'Ok', 'tamanho' => 'gigante'])
            ->assertSessionHasErrors('material');

        $this->assertSame(self::TEXTO, $this->conteudo($aula));
    }

    // ---- versão ----------------------------------------------------------------

    public function test_texto_que_mudou_desde_que_a_pagina_abriu_recusa_a_edicao(): void
    {
        $eu = $this->instrutor('Gestor Versao');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);
        $versaoQueAPessoaViu = $this->versao($aula);

        // Outra pessoa salvou no meio.
        Aula::query()->whereKey($aula->id)->update(['conteudo' => "## Reescrita\n\nTudo novo."]);

        $this->acao($eu, $curso, $aula, ['acao' => 'remover', 'indice' => 0], $versaoQueAPessoaViu)
            ->assertSessionHasErrors('material');

        $this->assertSame("## Reescrita\n\nTudo novo.", $this->conteudo($aula));
    }

    public function test_indice_de_trecho_que_nao_existe_pede_para_recarregar(): void
    {
        $eu = $this->instrutor('Gestor Indice');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'remover', 'indice' => 40])->assertSessionHasErrors('material');

        $this->assertSame(self::TEXTO, $this->conteudo($aula));
    }

    public function test_remover_o_ultimo_trecho_e_recusado_porque_a_aula_nao_pode_ficar_vazia(): void
    {
        $eu = $this->instrutor('Gestor Vazia');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);
        Aula::query()->whereKey($aula->id)->update(['conteudo' => 'Só este.']);

        $this->acao($eu, $curso, $aula, ['acao' => 'remover', 'indice' => 0])->assertSessionHasErrors('material');

        $this->assertSame('Só este.', $this->conteudo($aula));
    }

    // ---- acesso ----------------------------------------------------------------

    public function test_visitante_vai_para_o_login_e_curso_alheio_responde_404(): void
    {
        $this->post('/inst/curso/x/aulas/y/material', ['acao' => 'remover'])->assertRedirect('/entrar');

        $eu = $this->instrutor('Gestor Um Material');
        $outro = $this->instrutor('Gestor Dois Material');
        [$alheio, $aula] = $this->cursoComAulas($outro['id'], 'Curso Alheio Do Material');

        $this->acao($eu, $alheio, $aula, ['acao' => 'remover', 'indice' => 0])->assertNotFound();
        $this->assertSame(self::TEXTO, $this->conteudo($aula));
    }

    public function test_acao_desconhecida_e_recusada(): void
    {
        $eu = $this->instrutor('Gestor Acao');
        [$curso, $aula] = $this->cursoComAulas($eu['id']);

        $this->acao($eu, $curso, $aula, ['acao' => 'apagar-tudo'])->assertSessionHasErrors('acao');

        $this->assertSame(self::TEXTO, $this->conteudo($aula));
    }
}
