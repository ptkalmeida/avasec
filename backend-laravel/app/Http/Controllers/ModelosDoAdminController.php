<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Services\AuditLogger;
use App\Services\CertificatePdfService;
use App\Services\DocumentTemplateService;
use App\Services\UploadService;
use App\Services\VersoesDeConteudo;
use App\Support\TextoDoDocumento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * "Modelos de Certificado e Histórico" do painel administrativo, em Blade
 * (M5, fatia 6): o cabeçalho, as assinaturas e o rodapé dos documentos emitidos.
 *
 * Quem grava é o `DocumentTemplateService` da API, que só aceita o admin. O
 * campo de HTML livre (`html_personalizado`) continua de fora: o painel React já o
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
        private readonly CertificatePdfService $pdf,
        private readonly UploadService $uploads,
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
        $assinaturas = $this->assinaturasDoRascunho($request->old('assinaturas'), $modelo['signatarios'] ?? []);

        return view('admin.templates', [
            'tipo' => $tipo,
            'tipos' => ['certificado' => 'Certificado de Conclusão', 'historico' => 'Histórico Escolar'],
            'instituicao' => $request->old('nome_instituicao', is_string($modelo['nome_instituicao'] ?? null) ? $modelo['nome_instituicao'] : ''),
            'rodape' => $request->old('texto_rodape', is_string($modelo['texto_rodape'] ?? null) ? $modelo['texto_rodape'] : ''),
            'titulo' => $request->old('titulo', is_string($modelo['titulo'] ?? null) ? $modelo['titulo'] : ''),
            'corpo' => $request->old('texto_corpo', is_string($modelo['texto_corpo'] ?? null) ? $modelo['texto_corpo'] : ''),
            'rotulos' => array_merge(
                DocumentTemplateService::ROTULOS_DO_HISTORICO,
                is_array($modelo['rotulos_colunas'] ?? null) ? $modelo['rotulos_colunas'] : [],
                array_filter((array) $request->old('rotulos_colunas', []), 'is_string'),
            ),
            'camposDoTipo' => array_intersect_key(TextoDoDocumento::CAMPOS, array_flip(TextoDoDocumento::CAMPOS_POR_TIPO[$tipo])),
            'logo' => is_string($modelo['caminho_logo_instituicao'] ?? null) ? $modelo['caminho_logo_instituicao'] : null,
            'assinaturas' => $assinaturas,
            'atualizadoEm' => is_string($modelo['atualizado_em'] ?? null) ? $modelo['atualizado_em'] : null,
            'maxAssinaturas' => self::MAX_ASSINATURAS,
            'versoes' => $this->versoes->paraTela(VersoesDeConteudo::MODELO_DE_DOCUMENTO, $tipo, static function (array $v): string {
                $nome = is_string($v['nome_instituicao'] ?? null) ? trim($v['nome_instituicao']) : '';
                $assinaturas = is_array($v['signatarios'] ?? null) ? count($v['signatarios']) : 0;

                return ($nome !== '' ? mb_strimwidth($nome, 0, 90, '…') : 'Sem nome da instituição').' · '.$assinaturas.($assinaturas === 1 ? ' assinatura' : ' assinaturas');
            }),
        ]);
    }

    /** Volta a uma versão anterior do modelo. O que estava no ar entra no histórico. */
    public function restaurar(Request $request, string $tipo, string $versao): RedirectResponse
    {
        $tipoValido = $this->tipo($tipo);
        abort_if($tipoValido === null, 404);

        /** @var array{sub:string,nome:string,papel:string} $admin */
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

        /** @var array{sub:string,nome:string,papel:string} $admin */
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

        $dados = $request->validate($this->regras(), [], $this->nomesDosCampos());

        $desconhecidos = TextoDoDocumento::camposDesconhecidos((string) ($dados['texto_corpo'] ?? ''), $tipoValido);
        if ($desconhecidos !== []) {
            return $this->devolver($request, $tipoValido, $linhas)->withErrors([
                'texto_corpo' => 'Campo automático desconhecido: {'.implode('}, {', $desconhecidos).'}. Use só os botões abaixo do texto.',
            ]);
        }

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
                'nome_instituicao' => $this->opcional($dados['nome_instituicao'] ?? null),
                'titulo' => $this->opcional($dados['titulo'] ?? null),
                'texto_corpo' => $this->opcional($dados['texto_corpo'] ?? null),
                'rotulos_colunas' => $tipoValido === 'historico' ? $this->rotulos($dados['rotulos_colunas'] ?? null) : null,
                'texto_rodape' => $this->opcional($dados['texto_rodape'] ?? null),
                'signatarios' => $assinaturas,
            ], $admin);
        } catch (ApiException $e) {
            return $this->devolver($request, $tipoValido, $linhas)->withErrors(['assinaturas' => $e->getMessage()]);
        }

        $this->audit->log($request, 'Edição de Template de Documento', 'Template "'.$tipoValido.'" foi atualizado.');

        return redirect('/admin/templates?tipo='.$tipoValido)->with('avisoDeModelo', 'Modelo salvo.');
    }

    /**
     * Prévia do que está na tela, sem gravar: o PDF abre em outra aba. Aceita o
     * rascunho como ele está (campo desconhecido fica como foi digitado).
     */
    public function previa(Request $request, string $tipo): Response
    {
        $tipoValido = $this->tipo($tipo);
        abort_if($tipoValido === null, 404);

        $dados = $request->validate($this->regras(), [], $this->nomesDosCampos());

        $assinaturas = [];
        foreach ($this->assinaturasDoRascunho($request->input('assinaturas'), []) as $linha) {
            if (trim($linha['name']) !== '' && trim($linha['role']) !== '') {
                $assinaturas[] = ['name' => trim($linha['name']), 'role' => trim($linha['role'])];
            }
        }

        $rascunho = [
            'nome_instituicao' => $this->opcional($dados['nome_instituicao'] ?? null),
            'titulo' => $this->opcional($dados['titulo'] ?? null),
            'texto_corpo' => $this->opcional($dados['texto_corpo'] ?? null),
            'texto_rodape' => $this->opcional($dados['texto_rodape'] ?? null),
            'signatarios' => array_slice($assinaturas, 0, self::MAX_ASSINATURAS),
        ];
        // Campo em branco volta ao texto de fábrica, como ao salvar.
        $rascunho = array_filter($rascunho, static fn (mixed $v): bool => $v !== null);
        if ($tipoValido === 'historico') {
            $rascunho['rotulos_colunas'] = $this->rotulos($dados['rotulos_colunas'] ?? null);
        }

        $resultado = $this->pdf->renderPreviewPdf($tipoValido, $rascunho);

        return response($resultado['conteudo'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultado['filename'].'"',
        ]);
    }

    /** Troca o logo do documento: o arquivo vai para o disco público, o modelo guarda o endereço. */
    public function enviarLogo(Request $request, string $tipo): RedirectResponse
    {
        $tipoValido = $this->tipo($tipo);
        abort_if($tipoValido === null, 404);

        /** @var array{sub:string,nome:string,papel:string} $admin */
        $admin = $request->attributes->get('auth_user');

        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
        ], [
            'logo.required' => 'Escolha uma imagem para o logo.',
            'logo.mimes' => 'O logo precisa ser PNG ou JPEG.',
            'logo.max' => 'A imagem passa de 2 MB, o limite do logo.',
            'logo.uploaded' => 'A imagem não pôde ser recebida: ela é maior do que o servidor aceita.',
        ]);

        $arquivo = $request->file('logo');
        if ($arquivo === null || ! $arquivo->isValid()) {
            return redirect('/admin/templates?tipo='.$tipoValido)->withErrors(['logo' => 'A imagem não pôde ser recebida.']);
        }
        if (config('features.uploadArquivos') !== true) {
            return redirect('/admin/templates?tipo='.$tipoValido)->withErrors(['logo' => 'O envio de arquivos está desligado nesta instalação.']);
        }

        try {
            $url = $this->uploads->store($arquivo, 'public', $admin['sub'])['url'];
            $this->modelos->upsert($tipoValido, ['caminho_logo_instituicao' => $url], $admin);
        } catch (ApiException $e) {
            return redirect('/admin/templates?tipo='.$tipoValido)->withErrors(['logo' => $e->getMessage()]);
        }

        $this->audit->log($request, 'Edição de Template de Documento', 'Logo do template "'.$tipoValido.'" atualizado.');

        return redirect('/admin/templates?tipo='.$tipoValido)->with('avisoDeModelo', 'Logo atualizado.');
    }

    /** Tira o logo do modelo. O arquivo continua guardado e a versão anterior fica no histórico (ADR 12). */
    public function tirarLogo(Request $request, string $tipo): RedirectResponse
    {
        $tipoValido = $this->tipo($tipo);
        abort_if($tipoValido === null, 404);

        /** @var array{sub:string,nome:string,papel:string} $admin */
        $admin = $request->attributes->get('auth_user');

        $this->modelos->upsert($tipoValido, ['caminho_logo_instituicao' => null], $admin);
        $this->audit->log($request, 'Edição de Template de Documento', 'Logo do template "'.$tipoValido.'" removido do modelo.');

        return redirect('/admin/templates?tipo='.$tipoValido)->with('avisoDeModelo', 'Logo removido do modelo.');
    }

    /** @return array<string, list<string>> */
    private function regras(): array
    {
        return [
            'nome_instituicao' => ['nullable', 'string', 'max:191'],
            'titulo' => ['nullable', 'string', 'max:191'],
            'texto_corpo' => ['nullable', 'string', 'max:3000'],
            'texto_rodape' => ['nullable', 'string', 'max:2000'],
            'rotulos_colunas' => ['nullable', 'array'],
            'rotulos_colunas.*' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    private function nomesDosCampos(): array
    {
        return [
            'nome_instituicao' => 'nome da instituição',
            'titulo' => 'título',
            'texto_corpo' => 'texto do corpo',
            'texto_rodape' => 'texto de rodapé',
            'rotulos_colunas.*' => 'nome da coluna',
        ];
    }

    /**
     * Só as colunas que existem; coluna em branco volta ao nome de fábrica.
     *
     * @return array<string, string>
     */
    private function rotulos(mixed $valores): array
    {
        $lista = [];
        foreach (is_array($valores) ? $valores : [] as $chave => $texto) {
            if (isset(DocumentTemplateService::ROTULOS_DO_HISTORICO[$chave]) && is_string($texto) && trim($texto) !== '') {
                $lista[$chave] = trim($texto);
            }
        }

        return $lista;
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
     * @return list<array{name: string, role: string}>
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

    /** @param list<array{name: string, role: string}> $linhas */
    private function devolver(Request $request, string $tipo, array $linhas): RedirectResponse
    {
        return redirect('/admin/templates?tipo='.$tipo)->withInput([
            'nome_instituicao' => $request->string('nome_instituicao')->toString(),
            'texto_rodape' => $request->string('texto_rodape')->toString(),
            'titulo' => $request->string('titulo')->toString(),
            'texto_corpo' => $request->string('texto_corpo')->toString(),
            'rotulos_colunas' => (array) $request->input('rotulos_colunas', []),
            'assinaturas' => $linhas,
        ]);
    }

    private function opcional(mixed $valor): ?string
    {
        $texto = is_string($valor) ? trim($valor) : '';

        return $texto === '' ? null : $texto;
    }
}
