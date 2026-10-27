{{--
    Certificado de conclusão (PDF via dompdf) — espelha o visual de
    src/components/CertificateTemplate.tsx (paleta amber, serif, assinaturas).
    Restrições do dompdf: CSS 2.1 (sem flex/grid), DejaVu Serif no lugar de
    Georgia, QR como <img> com data URI (SVG inline não é confiável).
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 24px; }
        body {
            font-family: 'DejaVu Serif', serif;
            margin: 0;
            color: #334155;
        }
        .frame {
            border: 8px double #78350f;
            background-color: #fffcf9;
            padding: 36px 48px;
            text-align: center;
            position: relative;
            /* Altura fixa em px: 96% + borda e padding passava da folha e abria uma segunda página em branco. */
            height: 640px;
        }
        .corner { position: absolute; width: 56px; height: 56px; }
        .corner-tl { top: 8px; left: 8px; border-top: 4px solid #b4886b; border-left: 4px solid #b4886b; }
        .corner-tr { top: 8px; right: 8px; border-top: 4px solid #b4886b; border-right: 4px solid #b4886b; }
        .corner-bl { bottom: 8px; left: 8px; border-bottom: 4px solid #b4886b; border-left: 4px solid #b4886b; }
        .corner-br { bottom: 8px; right: 8px; border-bottom: 4px solid #b4886b; border-right: 4px solid #b4886b; }
        .institution {
            font-size: 10px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #92400e;
            font-weight: bold;
            margin-bottom: 14px;
        }
        h1 { font-size: 34px; color: #451a03; margin: 0 0 18px 0; }
        .lead { font-size: 13px; color: #334155; margin: 0 auto 6px auto; max-width: 620px; }
        .logo { max-height: 56px; max-width: 220px; margin-bottom: 8px; }
        .campo-aluno {
            font-size: 28px;
            font-weight: bold;
            color: #78350f;
            border-bottom: 2px solid #d9b38c;
            padding: 0 24px 4px 24px;
        }
        .campo-curso { font-size: 18px; font-weight: bold; color: #0f172a; }
        .campo-destaque { font-weight: bold; color: #047857; }
        .signatures { width: 100%; margin-top: 34px; border-top: 1px solid #e8ddce; padding-top: 22px; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; }
        .sig-script { font-style: italic; color: #94a3b8; font-size: 13px; }
        .sig-line { width: 160px; border-top: 1px solid #cbd5e1; margin: 4px auto; }
        .sig-name { font-size: 10.5px; font-weight: bold; color: #334155; }
        .sig-role { font-size: 9px; color: #64748b; }
        .verify { margin-top: 26px; }
        .verify td { vertical-align: middle; }
        .verify-text { text-align: left; font-size: 9px; color: #64748b; font-family: 'DejaVu Sans Mono', monospace; }
        .verify-text strong { color: #334155; }
        .footnote { margin-top: 14px; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="frame">
        <div class="corner corner-tl"></div>
        <div class="corner corner-tr"></div>
        <div class="corner corner-bl"></div>
        <div class="corner corner-br"></div>

        @if ($logoDataUri)
            <img class="logo" src="{{ $logoDataUri }}" alt="">
        @endif
        <div class="institution">{{ $nome_instituicao }}</div>

        <h1>{{ $titulo }}</h1>

        {{-- Texto do corpo escrito pelo admin: já escapado, com os campos automáticos trocados (App\Support\TextoDoDocumento). --}}
        {!! $corpoHtml !!}

        <table class="signatures">
            <tr>
                @foreach ($signatarios as $signatory)
                    <td style="width: {{ 100 / max(count($signatarios), 1) }}%;">
                        <div class="sig-script">{{ $signatory['name'] }}</div>
                        <div class="sig-line"></div>
                        <div class="sig-name">{{ $signatory['name'] }}</div>
                        <div class="sig-role">{{ $signatory['role'] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>

        <div class="footnote">
            * {{ $texto_rodape }}
        </div>
    </div>
</body>
</html>
