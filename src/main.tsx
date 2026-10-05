import {StrictMode} from 'react';
import {createRoot} from 'react-dom/client';
import {createBrowserRouter, RouterProvider} from 'react-router-dom';
import App from './App.tsx';
import './index.css';

/*
 * Rota única que entrega o App inteiro.
 *
 * O App continua decidindo o que renderizar a partir de `currentView` — que
 * agora deriva da URL (src/router/portalRoutes.ts). Isto é de propósito: mover
 * a árvore de render para elementos de rota seria o passo mais arriscado da
 * mudança, e não traria nada que este caminho não traga. O ganho — endereço
 * próprio por tela, link direto, recarregar sem perder o lugar e botão Voltar
 * funcionando — vem de a URL ser a fonte da verdade, não do formato da árvore.
 *
 * `createBrowserRouter` (roteador de dados) em vez de `<BrowserRouter>` porque
 * é o que habilita `useBlocker`: sem ele não há como interceptar o Voltar para
 * cair na confirmação de descarte da prova em andamento.
 */
const router = createBrowserRouter([
  {
    path: '*',
    element: <App />,
  },
]);

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <RouterProvider router={router} />
  </StrictMode>,
);
