<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\DocumentTemplate;
use App\Models\SystemSettings;
use App\Models\User;
use App\Support\CanalDeMensagens;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsIdentity;
use Tests\TestCase;

/**
 * "Configurações" e "Modelos de Certificado e Histórico" do painel
 * administrativo (fatia 6 do M5). Gravam em tabelas globais — o que muda para
 * todos os alunos —, então o que pesa é: só o admin escreve, só as chaves
 * conhecidas são gravadas, e nada é gravado sem a pessoa pedir.
 */
final class ConfiguracoesEModelosDoAdminTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsIdentity;

    private function admin(): string
    {
        return $this->staffToken('admin');
    }

    private function como(string $token): self
    {
        return $this->withUnencryptedCookie(JwtAuthenticate::SESSION_COOKIE, $token);
    }

    /** @return array<string, mixed> */
    private function gravadas(): array
    {
        $linha = SystemSettings::query()->find('singleton');

        return is_array($linha?->data) ? $linha->data : [];
    }

    // ---- acesso ---------------------------------------------------------------
    public function test_so_o_admin_chega_e_escreve(): void
    {
        $aluno = $this->makeStudent('Aluno Config Acesso');
        $gestor = User::query()->where('role', 'instructor')->firstOrFail();
        $tokenDoGestor = Jwt::issue($gestor->id, $gestor->name, 'instructor');

        // O visitante vem primeiro: o cookie de `como()` persiste no cliente de teste.
        foreach (['/admin/configuracoes', '/admin/templates'] as $caminho) {
            $this->get($caminho)->assertRedirect('/entrar');
        }
        $this->post('/admin/configuracoes', ['autoArchiveDuration' => 'nunca'])->assertRedirect('/entrar');
        foreach (['/admin/configuracoes', '/admin/templates'] as $caminho) {
            $this->como($aluno['token'])->get($caminho)->assertRedirect('/aluno');
            $this->como($tokenDoGestor)->get($caminho)->assertRedirect('/inst');
        }
        $this->como($tokenDoGestor)->post('/admin/templates/certificado', ['institutionName' => 'X'])->assertRedirect('/inst');
    }

    // ---- configurações --------------------------------------------------------
    public function test_a_tela_mostra_o_que_esta_valendo(): void
    {
        SystemSettings::query()->updateOrCreate(['id' => 'singleton'], ['data' => [
            'allowDirectMessages' => true, 'allowGlobalChat' => false, 'openEnrollment' => true,
            'autoCertify' => false, 'liveClassRecording' => true, 'autoArchiveDuration' => '3_meses',
        ]]);

        $tela = $this->como($this->admin())->get('/admin/configuracoes');

        $tela->assertOk();
        $tela->assertDontSee('<div id="root">', false);
        $tela->assertSee('Habilitar mensagens diretas (DMs)');
        $tela->assertSee('Salvar configurações');
        $html = (string) $tela->getContent();
        $this->assertMatchesRegularExpression('/name="allowDirectMessages"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="autoCertify"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/<option value="3_meses"\s+selected/', $html);
    }

    public function test_salvar_grava_as_seis_chaves_e_so_elas(): void
    {
        $token = $this->admin();

        $this->como($token)->post('/admin/configuracoes', [
            'allowDirectMessages' => '1',
            'openEnrollment' => '1',
            'autoArchiveDuration' => 'nunca',
            // Chave inventada no formulário não é gravada.
            'chaveInventada' => 'x',
        ])->assertRedirect('/admin/configuracoes')->assertSessionHas('avisoDeConfiguracoes');

        $gravado = $this->gravadas();
        $this->assertTrue($gravado['allowDirectMessages']);
        $this->assertFalse($gravado['allowGlobalChat']);
        $this->assertTrue($gravado['openEnrollment']);
        $this->assertFalse($gravado['autoCertify']);
        $this->assertFalse($gravado['liveClassRecording']);
        $this->assertSame('nunca', $gravado['autoArchiveDuration']);
        $this->assertArrayNotHasKey('chaveInventada', $gravado);

        $this->assertTrue(DB::table('SecurityLog')->where('action', 'Alteração de Configurações do Sistema')->exists());
    }

    public function test_mensagens_diretas_ligadas_abrem_o_canal(): void
    {
        $this->como($this->admin())->post('/admin/configuracoes', ['allowDirectMessages' => '1', 'autoArchiveDuration' => '6_meses']);
        $this->assertTrue(CanalDeMensagens::abertoParaAluno() === (config('features.mensagensDiretas') === true));

        $this->como($this->admin())->post('/admin/configuracoes', ['autoArchiveDuration' => '6_meses']);
        $this->assertFalse(CanalDeMensagens::abertoParaAluno());
    }

    public function test_duracao_fora_da_lista_e_recusada_e_nao_grava(): void
    {
        $antes = $this->gravadas();

        $this->como($this->admin())->from('/admin/configuracoes')
            ->post('/admin/configuracoes', ['autoArchiveDuration' => 'para-sempre-e-um-dia'])
            ->assertSessionHasErrors('autoArchiveDuration');

        $this->assertSame($antes, $this->gravadas());
    }

    /** A base de hoje tem "12 meses", que não está na lista: salvar não pode trocá-lo sem aviso. */
    public function test_valor_antigo_de_arquivamento_continua_ofertado_e_valido(): void
    {
        SystemSettings::query()->updateOrCreate(['id' => 'singleton'], ['data' => ['autoArchiveDuration' => '12 meses']]);
        $token = $this->admin();

        $this->como($token)->get('/admin/configuracoes')->assertSee('12 meses (valor atual)');

        $this->como($token)->post('/admin/configuracoes', ['autoArchiveDuration' => '12 meses'])->assertRedirect('/admin/configuracoes');
        $this->assertSame('12 meses', $this->gravadas()['autoArchiveDuration']);
    }

    public function test_a_flag_desligada_responde_404(): void
    {
        config(['features.perfilBasico' => false]);

        $this->como($this->admin())->get('/admin/configuracoes')->assertNotFound();
        $this->como($this->admin())->post('/admin/configuracoes', ['autoArchiveDuration' => 'nunca'])->assertNotFound();
    }

    public function test_nada_do_que_era_de_enfeite_volta(): void
    {
        $tela = $this->como($this->admin())->get('/admin/configuracoes');

        $tela->assertDontSee('Nota da Assessoria');
        $tela->assertDontSee('bancos na memória do navegador');
        $tela->assertDontSee('type="range"', false);
    }

    // ---- modelos de documento -------------------------------------------------
    public function test_mostra_o_modelo_do_certificado_com_as_assinaturas(): void
    {
        $tela = $this->como($this->admin())->get('/admin/templates');

        $tela->assertOk();
        $tela->assertDontSee('<div id="root">', false);
        $tela->assertSee('Certificado de Conclusão');
        $tela->assertSee('Histórico Escolar');
        $tela->assertSee('name="institutionName"', false);
        $tela->assertSee('name="assinaturas[0][name]"', false);
        $tela->assertSee('/api/document-templates/certificado/preview', false);
        $tela->assertDontSee('customHtml');
    }

    public function test_o_historico_avisa_que_ainda_nao_gera_documento(): void
    {
        $tela = $this->como($this->admin())->get('/admin/templates?tipo=historico');

        $tela->assertSee('ainda não tem emissão de PDF própria');
        $tela->assertDontSee('/api/document-templates/certificado/preview', false);
    }

    public function test_tipo_inventado_volta_para_o_certificado(): void
    {
        $this->como($this->admin())->get('/admin/templates?tipo=diploma')->assertRedirect('/admin/templates');
        $this->como($this->admin())->post('/admin/templates/diploma', ['institutionName' => 'X'])->assertNotFound();
    }

    public function test_salvar_o_modelo_grava_instituicao_rodape_e_assinaturas(): void
    {
        $token = $this->admin();

        $this->como($token)->post('/admin/templates/certificado', [
            'acao' => 'salvar',
            'institutionName' => 'Escola Estadual da Cultura RJ',
            'footerText' => 'Rodapé novo do certificado.',
            'assinaturas' => [
                ['name' => 'Fulana de Tal', 'role' => 'Diretora'],
                ['name' => '', 'role' => ''],
                ['name' => 'Beltrano Silva', 'role' => 'Secretário'],
            ],
        ])->assertRedirect('/admin/templates?tipo=certificado')->assertSessionHas('avisoDeModelo');

        $modelo = DocumentTemplate::query()->findOrFail('certificado');
        $this->assertSame('Escola Estadual da Cultura RJ', $modelo->institutionName);
        $this->assertSame('Rodapé novo do certificado.', $modelo->footerText);
        // A linha em branco é espaço de preenchimento: não vira assinatura.
        $this->assertSame([
            ['name' => 'Fulana de Tal', 'role' => 'Diretora'],
            ['name' => 'Beltrano Silva', 'role' => 'Secretário'],
        ], $modelo->signatories);
        // O HTML livre nunca é tocado por esta tela.
        $this->assertNull($modelo->customHtml);

        $this->como($token)->get('/admin/templates')->assertSee('Fulana de Tal')->assertSee('Rodapé novo do certificado.');
        $this->assertTrue(DB::table('SecurityLog')->where('action', 'Edição de Template de Documento')->exists());
    }

    public function test_assinatura_pela_metade_e_um_erro_visivel_e_nao_grava(): void
    {
        $antes = DocumentTemplate::query()->find('certificado')?->institutionName;

        $this->como($this->admin())->post('/admin/templates/certificado', [
            'acao' => 'salvar',
            'institutionName' => 'Nome Que Nao Deve Gravar',
            'assinaturas' => [['name' => 'Só o nome', 'role' => '']],
        ])->assertRedirect('/admin/templates?tipo=certificado')->assertSessionHasErrors('assinaturas');

        $this->assertSame($antes, DocumentTemplate::query()->find('certificado')?->institutionName);

        // E a tela volta com o que a pessoa digitou.
        $this->como($this->admin())->get('/admin/templates')->assertSee('Só o nome');
    }

    public function test_adicionar_e_remover_assinatura_mexem_so_no_rascunho(): void
    {
        $token = $this->admin();
        $antes = DocumentTemplate::query()->find('historico')?->signatories;

        $this->como($token)->post('/admin/templates/historico', [
            'acao' => 'adicionar',
            'institutionName' => 'Rascunho',
            'assinaturas' => [['name' => 'Primeira', 'role' => 'Cargo 1']],
        ])->assertRedirect('/admin/templates?tipo=historico');

        $tela = $this->como($token)->get('/admin/templates?tipo=historico');
        $tela->assertSee('name="assinaturas[1][name]"', false);   // a linha em branco acrescentada
        $tela->assertSee('Primeira');

        // Nada foi gravado.
        $this->assertSame($antes, DocumentTemplate::query()->find('historico')?->signatories);

        $this->como($token)->post('/admin/templates/historico', [
            'acao' => 'remover:0',
            'assinaturas' => [['name' => 'Primeira', 'role' => 'Cargo 1'], ['name' => 'Segunda', 'role' => 'Cargo 2']],
        ])->assertRedirect();
        $depois = $this->como($token)->get('/admin/templates?tipo=historico');
        $depois->assertDontSee('Primeira');
        $depois->assertSee('Segunda');
        $this->assertSame($antes, DocumentTemplate::query()->find('historico')?->signatories);
    }

    public function test_limite_de_dez_assinaturas(): void
    {
        $linhas = [];
        for ($i = 0; $i < 10; $i++) {
            $linhas[] = ['name' => 'Pessoa '.$i, 'role' => 'Cargo '.$i];
        }

        $this->como($this->admin())->post('/admin/templates/certificado', ['acao' => 'adicionar', 'assinaturas' => $linhas])
            ->assertSessionHasErrors('assinaturas');
    }

    public function test_enter_nos_campos_salva_em_vez_de_remover(): void
    {
        $html = (string) $this->como($this->admin())->get('/admin/templates')->getContent();

        // O primeiro botão do formulário (o que Enter aciona) é o de salvar.
        $primeiro = strpos($html, 'name="acao"');
        $this->assertNotFalse($primeiro);
        $this->assertStringContainsString('value="salvar"', substr($html, $primeiro, 40));
    }
}
