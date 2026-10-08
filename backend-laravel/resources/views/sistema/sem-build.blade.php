<!DOCTYPE html>
{{--
    O Laravel é a porta de entrada (ADR 017), mas o `dist/` do React não existe.

    Esta página só aparece quando o build do frontend não foi gerado — em
    desenvolvimento, onde o Vite (:5173) costuma ser a porta de entrada, ou num
    deploy em que `npm run build` não rodou. Segue a mesma regra de
    `sistema/status.blade.php` (Norma C.8.1): nada de caminho, versão, IP,
    porta ou stack trace.
--}}
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Aplicação indisponível · AVASEC</title>
    <style>
        :root { color-scheme: light dark; --fundo: #f6f4ef; --cartao: #ffffff; --texto: #1d2432; --apoio: #5b6472; --linha: #e3e0d8; }
        @media (prefers-color-scheme: dark) { :root { --fundo: #14171d; --cartao: #1d2129; --texto: #eef0f3; --apoio: #a3aab5; --linha: #2c313b; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fundo); color: var(--texto); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 520px; margin: 15vh auto; padding: 0 16px; }
        div { background: var(--cartao); border: 1px solid var(--linha); border-radius: 12px; padding: 24px; }
        h1 { font-size: 1.15rem; margin: 0 0 8px; }
        p { margin: 0; color: var(--apoio); font-size: .95rem; }
    </style>
</head>
<body>
<main>
    <div>
        <h1>A aplicação está fora do ar no momento</h1>
        <p>Estamos trabalhando para restabelecer o acesso. Tente novamente em alguns minutos.</p>
    </div>
</main>
</body>
</html>
