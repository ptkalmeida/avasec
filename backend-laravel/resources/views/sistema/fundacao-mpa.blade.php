<!DOCTYPE html>
{{--
    Prova da fundação do MPA (fase M0 do plano 16, ADR 017).

    Se esta página aparecer em /sistema/fundacao-mpa, está provado que uma rota
    registrada em routes/web.php é atendida pelo Laravel em Blade, enquanto todo
    o resto do sistema continua sendo servido pelo React — que é exatamente o
    mecanismo de que as fases M1 a M6 dependem.

    É descartável. Sai quando a primeira tela de produto migrar (M1), junto com
    a rota que a registra. Por isso não usa layout, token de design nem Tailwind:
    não vale a pena construir a casa para depois demoli-la, e o pipeline de
    assets do Blade é decisão da M1, quando a primeira tela real disser do que
    precisa.
--}}
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Fundação MPA · AVASEC</title>
    <style>
        :root { color-scheme: light dark; --fundo: #f6f4ef; --cartao: #ffffff; --texto: #1d2432; --apoio: #5b6472; --linha: #e3e0d8; --ok: #0f7a4f; }
        @media (prefers-color-scheme: dark) { :root { --fundo: #14171d; --cartao: #1d2129; --texto: #eef0f3; --apoio: #a3aab5; --linha: #2c313b; --ok: #4cc38a; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fundo); color: var(--texto); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 620px; margin: 10vh auto; padding: 0 16px; }
        section { background: var(--cartao); border: 1px solid var(--linha); border-radius: 12px; padding: 24px; }
        h1 { font-size: 1.2rem; margin: 0 0 4px; }
        p { color: var(--apoio); font-size: .95rem; }
        p.selo { color: var(--ok); font-weight: 600; margin: 0 0 16px; }
        dl { margin: 16px 0 0; display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; font-size: .9rem; }
        dt { color: var(--apoio); }
        dd { margin: 0; }
    </style>
</head>
<body>
<main>
    <section>
        <h1>Fundação do MPA</h1>
        <p class="selo">Esta página foi renderizada pelo Laravel, em Blade.</p>
        <p>
            Chegar aqui prova que uma rota declarada em <code>routes/web.php</code> é
            atendida pelo servidor, enquanto todas as outras continuam sendo entregues
            ao React. É o mecanismo da substituição gradual: cada tela migrada vira uma
            linha ali, e para de cair no SPA no mesmo instante.
        </p>
        <dl>
            <dt>Decisão</dt><dd>ADR 017 — MPA em vez de SPA</dd>
            <dt>Fase</dt><dd>M0 — fundação, sem migrar tela nenhuma</dd>
            <dt>Validade</dt><dd>descartável; sai na M1</dd>
        </dl>
    </section>
</main>
</body>
</html>
