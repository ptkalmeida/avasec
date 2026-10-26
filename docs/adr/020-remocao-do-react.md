# ADR 020 — O React sai do repositório (fase M7)

- **Data:** 22/10/2026
- **Situação:** aceita — fase M7 do plano 16, autorizada pelo responsável em 22/10
  ("pode rodar o M7") depois do levantamento do que sairia
- **Afeta:** ADR 017 (MPA em vez de SPA), ADR 018, ADR 019

## Contexto

Com a M5 (admin) fechada em 22/10, nenhum endereço da aplicação servia mais o SPA
React: `/aluno`, `/inst` e `/admin` são prefixos do servidor, o portal público e a
autenticação já eram Blade. O que restava do React era código sem rota, um
`Route::fallback` que entregava o `dist/index.html` a qualquer endereço desconhecido,
e uma cadeia de build e teste que só existia para ele.

O plano 16 (§4) condicionava a M7 a "M1–M6 em produção e homologadas". A homologação
não existe ainda (plano 17), então o responsável **assumiu o risco**: a volta atrás
passa a ser o histórico do git (o último commit com `src/` é o `94c2caf`, o fim
da M5), e não mais o código ao lado.

## Decisão

1. **Remover** `src/` (85 arquivos), `index.html`, `vite.config.ts`, `vitest*.config.ts`,
   `tests/frontend/` (os Vitest), `scripts/verificar-build-producao.mjs` e as dependências
   React (react, react-dom, react-router-dom, recharts, lucide-react, motion,
   react-syntax-highlighter, plugin-react, Testing Library, jsdom, vitest).
2. **Endereço desconhecido é 404**, com página própria (`errors/404.blade.php`), no
   lugar do fallback do SPA. `AppShellController`, `sistema/sem-build` e `config/mpa.php`
   saíram. Sob `/api/*` o erro continua sendo o JSON do contrato.
3. **`rotas-laravel.json` e o `RoteadorMpaTest` saíram.** O arquivo existia para o
   proxy do Vite e para decidir "Blade ou React"; sem as duas coisas, a tabela de
   `routes/web.php` é o único roteador. O que o teste protegia de verdade (recurso do
   servidor inexistente é 404, e `/api` devolve JSON) passou para
   `EnderecoDesconhecidoTest`.
4. **Os tokens do portal** (`src/index.css`) foram movidos com `git mv` para
   `backend-laravel/resources/css/tokens.css` e `blade.css` passou a importá-los dali.
5. **A porta de entrada em desenvolvimento é o Laravel (8000).** O Vite de dev do React
   (5173) deixou de existir; `npm run dev` agora sobe só o Vite dos assets Blade (5174),
   e o Playwright aponta para `http://localhost:8000`. `npm run build` compila os assets
   das telas em `backend-laravel/public/build`.
6. **Servidor:** o Nginx deixa de servir `/assets/` a partir de `dist/` e a variável
   `$raiz_dist` some. `DEPLOY_LARAVEL.md`, `docker/nginx/*`, `docker-compose.espelho.yml`,
   `README.md` e `TECNOLOGIAS.md` foram atualizados.

## Testes: o que saiu e por quê

| Saiu | Motivo |
|---|---|
| ~720 Vitest de `tests/frontend/` | Testavam componentes e utilitários TypeScript que deixaram de existir. A regra que eles cobriam já vive em PHP (os portes `BasesGerenciais`, `ConteudoDaAula`, `AgendaAoVivo` etc., conferidos contra o TypeScript na época de cada migração), e as telas são cobertas pelos testes de rota Blade e pelos e2e. Os utilitários puros sem porte em PHP (por exemplo `cpf.ts`, `courseProgress.ts`) morreram com as telas que os chamavam |
| 4 testes de paridade (`CatalogoPublico`, `FeatureFlag`, `MenuPublico`, `SystemSettingsKey`) | Comparavam um arquivo PHP com o TypeScript "irmão". Sem o TypeScript, o PHP é a fonte única e não há o que comparar |
| `RoteadorMpaTest` | Ver decisão 3 |

O número total de testes caiu (o plano 16 §5 pedia que não caísse); isto é consequência
direta de remover o que eles testavam, e foi aceito ao autorizar a M7. Gates ao fim:
PHPUnit, e2e (Playwright), Pint, PHPStan nível 9, `tsc` e `npm run build` verdes.

## Consequências e pendências

- **Irreversível sem o git.** Para recuperar qualquer tela React:
  `git show 94c2caf:src/<arquivo>`.
- **O painel de acessibilidade** (que estava só no React e sem porta desde 20/10) continua
  pendente; está no histórico, e o rodapé do portal registra isso.
- **Comentários antigos** em PHP e Blade que citam `src/…` ("traduz `X.tsx`") foram mantidos
  de propósito: registram a origem de cada regra e valem como índice do histórico.
- **Documentos históricos** (`MIGRACAO_LARAVEL.md`, `HARDENING.md`, `DOCUMENTATION.md`)
  não foram reescritos; descrevem o momento em que foram escritos.
- **O build deixa de ter o gate de "bundle de produção"** (`verificar-build-producao`):
  ele protegia contra PINs de demonstração do React em produção, que não existem mais.
- **A página `errors/404` é nova** e vale para todo o portal; o texto é do projeto,
  não de norma, e pode ser ajustado pela coordenação.
