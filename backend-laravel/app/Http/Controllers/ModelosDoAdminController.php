<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Services\AuditLogger;
use App\Services\DocumentTemplateService;
use App\Services\VersoesDeConteudo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Modelos de Certificado e Histórico" do painel administrativo, em Blade
 * (M5, fatia 6): o cabeçalho, as assinaturas e o rodapé dos documentos emitidos.
 *
 * Quem grava é o `DocumentTemplateService` da API, que só aceita o admin. O
 * campo de HTML livre (`customHtml`) continua de fora: o painel React já o
 * tinha removido — campo que aceita marcação arbitrária num documento oficial é
 * superfície de injeção, e o modelo estruturado cobre o uso real.
 *
 * Adicionar e remover assinatura mexem só no RASCUNHO da tela (nada é gravado
 * até "Salvar modelo"), como no painel React.
 */
final class ModelosDoAdminController extends Controller
{
    private const MAX_ASSINATURAS = 10;

    public function __construct(
        private readonly DocumentTemplateService $modelos,
        private readonly AuditLogger $audit,
        private readonly VersoesDeConteudo $versoes,
    ) {}

    public function mostrar(Request $request): View|RedirectResponse
    {
        $tipo = $this->tipo($request->query('tipo'));
        if ($tipo === null) {
            return redirect('/admin/templates');
        }

        $modelo = $this->modelos->get($tipo);

        // Com rascunho em andamento (acrescentar/remover assinatura, ou erro de
        // validação), o que vale é o que a pessoa estava editando.
        $assinaturas = $this->assinaturasDoRascunho($request->old('assinaturas'), $modelo['signatories'] ?? []);

        return view('admin.templates', [
            'tipo' => $tipo,
            'tipos' => ['certificado' => 'Certificado de Conclusão', 'historico' => 'Histórico Escolar'],
            'instituicao' => $request->old('institutionName', is_string($modelo['institutionName'] ?? null) ? $modelo['institutionName'] : ''),
            'rodape' => $request->old('footerText', is_string($modelo['footerText'] ?? null) ? $modelo['footerText'] : ''),
            'assinaturas' => $assinaturas,
            'atualizadoEm' => is_string($modelo['updatedAt'] ?? null) ? $modelo['updatedAt'] : null,
            'maxAssinaturas' => self::MAX_ASSINATURAS,
            'versoes' => $this->versoes->paraTela(VersoesDeConteudo::MODELO_DE_DOCUMENTO, $tipo, static function (array $v): string {
                $nome = is_string($v['institutionName'] ?? null) ? trim($v['institutionName']) : '';
                $assinaturas = is_array($v['signatories'] ?? null) ? count($v['signatories']) : 0;

                return ($nome !== '' ? mb_strimwidth($nome, 0, 90, '…') : 'Sem nome da instituição').' · '.$assinaturas.($assinaturas === 1 ? ' assinatura' : ' assinaturas');
            }),
        ]);
    }

    /** Volta a uma versão anterior do modelo. O que estava no ar entra no histórico. */
    public function restaurar(Request $request, string $tipo, string $versao): RedirectResponse
    {
        $tipoValido = $this->tipo($tipo);
        abort_if($tipoValido === null, 404);

        /** @var array{sub:string,name:string,role:string} $admin */
        $admin = $request->attributes->get('auth_user');

        $this->modelos->restaurarVersao($tipoValido, $versao, $admin);
        $this->audit->log($request, 'Edição de Template de Documento', 'Template "'.$tipoValido.'" voltou a uma versão anterior ('.$versao.').');

        return redirect('/admin/templates?tipo='.$tipoValido)
            ->with('avisoDeModelo', 'A versão anterior voltou ao ar. O que estava publicado ficou guardado no histórico.');
    }

    public function salvar(Request $request, string $tipo): RedirectResponse
    {
        $tipoValido = $this->tipo($tipo);
        abort_if($tipoValido === null, 404);

        /** @var array{sub:string,name:string,role:string} $admin */
        $admin = $request->attributes->get('auth_user');

        $linhas = $this->assinaturasDoRascunho($request->input('assinaturas'), []);
        $acao = $request->string('acao')->toString();

        // Ações de rascunho: não gravam nada, devolvem a tela com a lista mudada.
        if ($acao === 'adicionar' || str_starts_with($acao, 'remover:')) {
            if ($acao === 'adicionar') {
                if (count($linhas) >= self::MAX_ASSINATURAS) {
                    return $this->devolver($request, $tipoValido, $linhas)
                        ->withErrors(['assinaturas' => 'Um modelo aceita no máximo '.self::MAX_ASSINATURAS.' assinaturas.']);
                }
                $linhas[] = ['name' => '', 'role' => ''];
            } else {
                unset($linhas[(int) substr($acao, 8)]);
                $linhas = array_values($linhas);
            }

            return $this->devolver($request, $tipoValido, $linhas);
        }

        $dados = $request->validate([
            'institutionName' => ['nullable', 'string', 'max:191'],
            'footerText' => ['nullable', 'string', 'max:2000'],
        ], [], ['institutionName' => 'nome da instituição', 'footerText' => 'texto de rodapé']);

        // Linha totalmente em branco é só espaço de preenchimento; linha pela
        // metade é erro que a pessoa precisa ver, e não um silêncio.
        $assinaturas = [];
        foreach ($linhas as $posicao => $linha) {
            $nome = trim($linha['name']);
            $cargo = trim($linha['role']);
            if ($nome === '' && $cargo === '') {
                continue;
            }
            if ($nome === '' || $cargo === '') {
                return $this->devolver($request, $tipoValido, $linhas)
                    ->withErrors(['assinaturas' => 'Assinatura '.($posicao + 1).': informe o nome e o cargo, ou remova a linha.']);
            }
            if (mb_strlen($nome) > 191 || mb_strlen($cargo) > 191) {
                return $this->devolver($request, $tipoValido, $linhas)
                    ->withErrors(['assinaturas' => 'Assinatura '.($posicao + 1).': nome e cargo aceitam até 191 caracteres.']);
            }
            $assinaturas[] = ['name' => $nome, 'role' => $cargo];
        }
        if (count($assinaturas) > self::MAX_ASSINATURAS) {
            return $this->devolver($request, $tipoValido, $linhas)
                ->withErrors(['assinaturas' => 'Um modelo aceita no máximo '.self::MAX_ASSINATURAS.' assinaturas.']);
        }

        try {
            $this->modelos->upsert($tipoValido, [
                'institutionName' => $this->opcional($dados['institutionName'] ?? null),
                'footerText' => $this->opcional($dados['footerText'] ?? null),
                'signatories' => $assinaturas,
            ], $admin);
        } catch (ApiException $e) {
            return $this->devolver($request, $tipoValido, $linhas)->withErrors(['assinaturas' => $e->getMessage()]);
        }

        $this->audit->log($request, 'Edição de Template de Documento', 'Template "'.$tipoValido.'" foi atualizado.');

        return redirect('/admin/templates?tipo='.$tipoValido)->with('avisoDeModelo', 'Modelo salvo.');
    }

    private function tipo(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return 'certificado';
        }

        return is_string($valor) && in_array($valor, DocumentTemplateService::TYPES, true) ? $valor : null;
    }

    /**
     * As assinaturas do rascunho (o que a pessoa estava editando) ou, sem rascunho, as gravadas.
     *
     * @return list<array{name:string,role:string}>
     */
    private function assinaturasDoRascunho(mixed $rascunho, mixed $gravadas): array
    {
        $fonte = is_array($rascunho) ? $rascunho : (is_array($gravadas) ? $gravadas : []);

        $lista = [];
        foreach ($fonte as $linha) {
            if (! is_array($linha)) {
                continue;
            }
            $lista[] = [
                'name' => is_string($linha['name'] ?? null) ? $linha['name'] : '',
                'role' => is_string($linha['role'] ?? null) ? $linha['role'] : '',
            ];
        }

        return $lista;
    }

    /** @param list<array{name:string,role:string}> $linhas */
    private function devolver(Request $request, string $tipo, array $linhas): RedirectResponse
    {
        return redirect('/admin/templates?tipo='.$tipo)->withInput([
            'institutionName' => $request->string('institutionName')->toString(),
            'footerText' => $request->string('footerText')->toString(),
            'assinaturas' => $linhas,
        ]);
    }

    private function opcional(mixed $valor): ?string
    {
        $texto = is_string($valor) ? trim($valor) : '';

        return $texto === '' ? null : $texto;
    }
}
