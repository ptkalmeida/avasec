<!DOCTYPE html>
{{--
    Página não encontrada. Desde a fase M7 (ADR 020) o Laravel é a única
    aplicação: não há mais SPA para onde um endereço desconhecido cair, e o
    que não casa com rota nenhuma é 404 de verdade.

    Segue a regra de `sistema/status.blade.php` (Norma C.8.1): nada de
    caminho, versão, IP, porta ou stack trace.
--}}
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Página não encontrada · AVASEC</title>
    <style>
        :root { color-scheme: light dark; --fundo: #f1f5f9; --cartao: #ffffff; --texto: #0f172a; --apoio: #475569; --linha: #e2e8f0; --primaria: #1d4ed8; }
        @media (prefers-color-scheme: dark) { :root { --fundo: #0f172a; --cartao: #1e293b; --texto: #f1f5f9; --apoio: #cbd5e1; --linha: #334155; --primaria: #93c5fd; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fundo); color: var(--texto); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 520px; margin: 15vh auto; padding: 0 16px; }
        div { background: var(--cartao); border: 1px solid var(--linha); border-radius: 12px; padding: 24px; }
        h1 { font-size: 1.15rem; margin: 0 0 8px; }
        p { margin: 0 0 16px; color: var(--apoio); font-size: .95rem; }
        a { color: var(--primaria); font-weight: 600; }
    </style>
</head>
<body>
<main>
    <div>
        <h1>Página não encontrada</h1>
        <p>O endereço que você abriu não existe ou foi trocado.</p>
        <a href="/">Voltar ao início</a>
    </div>
</main>
</body>
</html>
