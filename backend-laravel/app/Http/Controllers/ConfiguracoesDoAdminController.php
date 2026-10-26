<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Configurações" do painel administrativo, em Blade (M5, fatia 6).
 *
 * Grava as seis chaves que a API já aceita (`SettingsService::ALLOWED_KEYS`) e
 * nada além disso. A leitura é pública por desenho (o visitante monta o site
 * com `/api/system-settings`), então esta tela nunca grava segredo.
 *
 * ## O que o painel React tinha e NÃO veio
 *
 * - O **controle deslizante de presença mínima (50–90%)** era um `useState` que
 *   nenhum botão gravava: mexer nele não mudava nada, nem recarregando. A
 *   frequência mínima de verdade é por curso (`minAttendance`), e se edita em
 *   Cursos.
 * - A **"Nota da Assessoria de T.I."**, que descrevia "bancos na memória do
 *   navegador" — o que deixou de ser verdade quando o banco passou a ser o
 *   servidor.
 * - O botão "Salvar Configurações" só mostrava um aviso de sucesso: cada chave
 *   era gravada na hora, ao clicar. Aqui o botão é o que grava, de verdade.
 */
final class ConfiguracoesDoAdminController extends Controller
{
    /** Valores do arquivamento automático, com o texto que a pessoa lê. */
    public const ARQUIVAMENTO = [
        'nunca' => 'Nunca arquivar (manual)',
        '1_mes' => '1 mês após inatividade',
        '3_meses' => '3 meses após inatividade',
        '6_meses' => '6 meses após inatividade',
        '12_meses' => '1 ano após inatividade',
    ];

    /** O que vale quando a chave nunca foi gravada. `allowDirectMessages` é falso porque é assim que o servidor a lê. */
    private const PADRAO = [
        'allowDirectMessages' => false,
        'allowGlobalChat' => false,
        'openEnrollment' => true,
        'autoCertify' => true,
        'liveClassRecording' => true,
        'autoArchiveDuration' => '6_meses',
    ];

    private const INTERRUPTORES = ['allowDirectMessages', 'allowGlobalChat', 'openEnrollment', 'autoCertify', 'liveClassRecording'];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function mostrar(): View
    {
        abort_unless(config('features.perfilBasico') === true, 404);

        $atuais = $this->valores();

        // Um valor antigo que não está na lista (a base de hoje tem "12 meses")
        // continua aparecendo — senão salvar o formulário o trocaria sem aviso.
        $opcoes = self::ARQUIVAMENTO;
        $arquivamento = $atuais['autoArchiveDuration'];
        if (! array_key_exists($arquivamento, $opcoes)) {
            $opcoes[$arquivamento] = $arquivamento.' (valor atual)';
        }

        return view('admin.configuracoes', [
            'valores' => $atuais,
            'opcoesDeArquivamento' => $opcoes,
        ]);
    }

    public function salvar(Request $request): RedirectResponse
    {
        abort_unless(config('features.perfilBasico') === true, 404);

        $atuais = $this->valores();
        $arquivamento = $request->string('autoArchiveDuration')->toString();

        $permitidos = array_keys(self::ARQUIVAMENTO);
        $permitidos[] = $atuais['autoArchiveDuration'];
        $request->validate(['autoArchiveDuration' => ['required', 'string', 'in:'.implode(',', array_unique($permitidos))]], [
            'autoArchiveDuration.in' => 'Escolha uma das durações da lista.',
        ], ['autoArchiveDuration' => 'duração do arquivamento']);

        $novos = ['autoArchiveDuration' => $arquivamento];
        foreach (self::INTERRUPTORES as $chave) {
            $novos[$chave] = $request->boolean($chave);
        }

        $mudaram = array_keys(array_filter($novos, static fn (mixed $valor, string $chave): bool => $valor !== ($atuais[$chave] ?? null), ARRAY_FILTER_USE_BOTH));

        $this->settings->update($novos);
        $this->audit->log(
            $request,
            'Alteração de Configurações do Sistema',
            $mudaram === [] ? 'Configurações salvas sem alterações.' : 'Configurações atualizadas: '.implode(', ', $mudaram).'.',
        );

        return redirect('/admin/configuracoes')->with('avisoDeConfiguracoes', 'Configurações salvas.');
    }

    /**
     * O que está valendo agora, com o padrão para a chave que nunca foi gravada.
     *
     * @return array{allowDirectMessages:bool,allowGlobalChat:bool,openEnrollment:bool,autoCertify:bool,liveClassRecording:bool,autoArchiveDuration:string}
     */
    private function valores(): array
    {
        $gravado = $this->settings->get();
        $arquivamento = $gravado['autoArchiveDuration'] ?? self::PADRAO['autoArchiveDuration'];

        return [
            'allowDirectMessages' => ($gravado['allowDirectMessages'] ?? self::PADRAO['allowDirectMessages']) === true,
            'allowGlobalChat' => ($gravado['allowGlobalChat'] ?? self::PADRAO['allowGlobalChat']) === true,
            'openEnrollment' => ($gravado['openEnrollment'] ?? self::PADRAO['openEnrollment']) === true,
            'autoCertify' => ($gravado['autoCertify'] ?? self::PADRAO['autoCertify']) === true,
            'liveClassRecording' => ($gravado['liveClassRecording'] ?? self::PADRAO['liveClassRecording']) === true,
            'autoArchiveDuration' => is_string($arquivamento) && $arquivamento !== '' ? $arquivamento : self::PADRAO['autoArchiveDuration'],
        ];
    }
}
