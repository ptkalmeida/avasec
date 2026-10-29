<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\CertificatePdfService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A página pública de certificados e o autenticador por QR estão INATIVADOS (ADR 027).
 *
 * Este teste prende o estado DESLIGADO, que é o padrão do projeto, e o caminho de volta:
 * ligando a flag, tudo reaparece. Quem exercita a página e a consulta em si são os testes
 * das outras classes, que ligam a flag com `ComVerificacaoPublicaLigada`.
 */
final class VerificacaoPublicaDesligadaTest extends TestCase
{
    public function test_a_flag_esta_desligada_por_padrao(): void
    {
        $this->assertFalse(config('features.verificacaoPublicaCertificados'));
    }

    public function test_a_pagina_responde_404_comum(): void
    {
        $this->get('/certificados')->assertNotFound();
        $this->get('/certificados?verify=ABC123')->assertNotFound();
    }

    public function test_o_qr_de_um_certificado_ja_impresso_abre_a_home_e_nao_um_404(): void
    {
        // O papel já está na mão de quem o lê: abrir a home é melhor que redirecionar para um 404.
        $this->get('/?verify=ABC123')->assertOk();
    }

    public function test_a_rota_publica_da_api_diz_que_a_funcionalidade_esta_desligada(): void
    {
        $r = $this->getJson('/api/certificados/verificar?code=ABC123');

        $r->assertNotFound();
        $this->assertStringContainsString('FEATURE_DISABLED', $r->getContent() ?: '');
    }

    public function test_cabecalho_e_rodape_nao_oferecem_a_pagina(): void
    {
        $html = $this->get('/')->getContent() ?: '';

        $this->assertStringNotContainsString('href="/certificados"', $html);
        // Sanidade: o filtro tira só a entrada com flag, não o menu inteiro.
        $this->assertStringContainsString('href="/cursos"', $html);
        $this->assertStringContainsString('href="/calendario"', $html);
    }

    public function test_ligando_a_flag_a_pagina_o_menu_e_o_redirecionamento_voltam(): void
    {
        config(['features.verificacaoPublicaCertificados' => true]);

        $html = $this->get('/')->getContent() ?: '';
        // Cabeçalho (desktop e celular) e rodapé.
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'href="/certificados"'));

        $this->get('/certificados')->assertOk();
        $this->get('/?verify=ABC123')->assertRedirect('/certificados?verify=ABC123');
    }

    public function test_o_qr_nao_e_gerado_e_o_marcador_some_do_layout_personalizado(): void
    {
        $servico = app(CertificatePdfService::class);
        $qr = new ReflectionMethod($servico, 'qrDaVerificacao');
        $layout = new ReflectionMethod($servico, 'renderCustomHtml');

        $this->assertSame('', $qr->invoke($servico, 'http://exemplo.test/?verify=X'));
        // Sem <img> vazio nem texto "QR de verificação" num documento oficial.
        $this->assertSame('<p>antes  depois</p>', $layout->invoke($servico, '<p>antes {{qrImg}} depois</p>', ['qrDataUri' => '']));
    }

    public function test_ligada_a_flag_o_qr_volta(): void
    {
        config(['features.verificacaoPublicaCertificados' => true]);
        $servico = app(CertificatePdfService::class);
        $qr = new ReflectionMethod($servico, 'qrDaVerificacao');
        $layout = new ReflectionMethod($servico, 'renderCustomHtml');

        $uri = $qr->invoke($servico, 'http://exemplo.test/?verify=X');
        $this->assertIsString($uri);
        $this->assertStringStartsWith('data:image/svg+xml', $uri);
        $this->assertStringContainsString('alt="QR de verificação"', (string) $layout->invoke($servico, '{{qrImg}}', ['qrDataUri' => $uri]));
    }

    public function test_a_area_do_aluno_e_a_emissao_nao_foram_afetadas(): void
    {
        // `/aluno/certificados` é outra coisa: exige login, e a flag `certificados` segue ligada.
        $this->assertTrue(config('features.certificados'));
        $this->get('/aluno/certificados')->assertRedirect();
    }
}
