{{--
    Histórico escolar (PDF via dompdf). Mesmo visual do certificado; o título, o
    texto de abertura, o logo, os nomes das colunas, as assinaturas e o rodapé vêm
    do modelo editado em /admin/templates. Restrições do dompdf: CSS 2.1, sem flex/grid.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 24px; }
        body { font-family: 'DejaVu Serif', serif; margin: 0; color: #334155; }
        .frame {
            border: 8px double #78350f;
            background-color: #fffcf9;
            padding: 36px 48px;
            text-align: center;
            /* min-height: com muitos cursos a tabela segue para a página seguinte, em vez de vazar do quadro. */
            min-height: 640px;
        }
        .logo { max-height: 56px; max-width: 220px; margin-bottom: 8px; }
        .institution { font-size: 10px; letter-spacing: 3px; text-transform: uppercase; color: #92400e; font-weight: bold; margin-bottom: 14px; }
        h1 { font-size: 30px; color: #451a03; margin: 0 0 14px 0; }
        .lead { font-size: 12px; color: #334155; margin: 0 auto 10px auto; max-width: 620px; }
        .campo-aluno { font-weight: bold; color: #78350f; }
        .campo-curso, .campo-destaque { font-weight: bold; }
        table.historico { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; }
        table.historico th { background-color: #f5e9dc; color: #451a03; text-align: left; padding: 6px 8px; border-bottom: 2px solid #d9b38c; }
        table.historico td { padding: 6px 8px; border-bottom: 1px solid #e8ddce; text-align: left; }
        .signatures { width: 100%; margin-top: 26px; border-top: 1px solid #e8ddce; padding-top: 16px; }
        .signatures td { text-align: center; vertical-align: top; }
        .sig-line { width: 160px; border-top: 1px solid #cbd5e1; margin: 4px auto; }
        .sig-name { font-size: 10.5px; font-weight: bold; color: #334155; }
        .sig-role { font-size: 9px; color: #64748b; }
        .footnote { margin-top: 12px; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="frame">
        @if ($logoDataUri)
            <img class="logo" src="{{ $logoDataUri }}" alt="">
        @endif
        <div class="institution">{{ $nome_instituicao }}</div>

        <h1>{{ $titulo }}</h1>

        {!! $corpoHtml !!}

        <table class="historico">
            <thead>
                <tr>
                    <th>{{ $rotulos['curso'] }}</th>
                    <th>{{ $rotulos['carga_horaria'] }}</th>
                    <th>{{ $rotulos['frequencia'] }}</th>
                    <th>{{ $rotulos['conclusao'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($linhas as $linha)
                    <tr>
                        <td>{{ $linha['curso'] }}</td>
                        <td>{{ $linha['carga_horaria'] }}</td>
                        <td>{{ $linha['frequencia'] }}</td>
                        <td>{{ $linha['conclusao'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="signatures">
            <tr>
                @foreach ($signatarios as $signatory)
                    <td style="width: {{ 100 / max(count($signatarios), 1) }}%;">
                        <div class="sig-line"></div>
                        <div class="sig-name">{{ $signatory['name'] }}</div>
                        <div class="sig-role">{{ $signatory['role'] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>

        <div class="footnote">* {{ $texto_rodape }}</div>
    </div>
</body>
</html>
