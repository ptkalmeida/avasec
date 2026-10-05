<!DOCTYPE html>
{{--
    Página pública de status (Norma Técnica TI-SECEC, C.8.1).

    Não pode mostrar endereço IP, porta, versão, nome de container, caminho,
    credencial nem mensagem de erro — só o estado de cada componente, em
    linguagem de quem usa. Há teste varrendo esta página por esses padrões.
--}}
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Status do sistema · AVASEC</title>
    <style>
        :root { color-scheme: light dark; --fundo: #f6f4ef; --cartao: #ffffff; --texto: #1d2432; --apoio: #5b6472; --linha: #e3e0d8; --ok: #0f7a4f; --falha: #b42318; }
        @media (prefers-color-scheme: dark) { :root { --fundo: #14171d; --cartao: #1d2129; --texto: #eef0f3; --apoio: #a3aab5; --linha: #2c313b; --ok: #4cc38a; --falha: #f97066; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fundo); color: var(--texto); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 560px; margin: 48px auto; padding: 0 16px; }
        h1 { font-size: 1.25rem; margin: 0 0 4px; }
        p.apoio { color: var(--apoio); margin: 0 0 24px; font-size: .9rem; }
        ul { list-style: none; margin: 0; padding: 0; background: var(--cartao); border: 1px solid var(--linha); border-radius: 12px; }
        li { display: flex; justify-content: space-between; gap: 16px; padding: 14px 16px; border-top: 1px solid var(--linha); }
        li:first-child { border-top: 0; }
        .ok { color: var(--ok); font-weight: 600; }
        .falha { color: var(--falha); font-weight: 600; }
        footer { color: var(--apoio); font-size: .85rem; margin-top: 16px; }
    </style>
</head>
<body>
<main>
    <h1>Status do sistema</h1>
    <p class="apoio">AVASEC — Escola Estadual da Cultura</p>

    <ul>
        @foreach ($componentes as $nome => $ok)
            <li>
                <span>{{ $nome }}</span>
                <span class="{{ $ok ? 'ok' : 'falha' }}">{{ $ok ? 'Operacional' : 'Indisponível' }}</span>
            </li>
        @endforeach
    </ul>

    <footer>Última verificação: {{ $verificadoEm }}</footer>
</main>
</body>
</html>
