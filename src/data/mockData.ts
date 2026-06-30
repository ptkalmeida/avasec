/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Course } from '../types';

export const INITIAL_COURSES: Course[] = [
  {
    id: 'course-1',
    title: 'UX/UI Design: Interfaces de Alta Performance',
    description: 'Aprenda do zero ao avançado como planejar, estruturar e prototipar sistemas complexos utilizando as melhores práticas do Figma, Design Systems e testes de usabilidade para alta conversão.',
    category: 'Design Digital',
    thumbnail: 'https://images.unsplash.com/photo-1561070791-26c113006238?w=800&auto=format&fit=crop&q=60',
    instructorName: 'Gestor de Cursos',
    lessons: [
      {
        id: 'lesson-1-1',
        courseId: 'course-1',
        title: 'Introdução ao AVA & Fundamentos do UX Design',
        duration: '15 min',
        videoUrl: 'https://vjs.zencdn.net/v/oceans.mp4',
        content: `### O que é UX Design?
UX (User Experience) ou Experiência do Usuário trata da forma como uma pessoa interage e se sente ao utilizar um produto, serviço ou sistema digital.

Nesta aula introdutória, vamos cobrir os seguintes pontos:
1. **Os 5 Pilares de Jesse James Garrett** (Estratégia, Escopo, Estrutura, Esqueleto, Superfície)
2. **Design Centrado no Usuário (UCD)** - Por que colocar o usuário no centro das decisões de negócio.
3. **Métricas de Sucesso** - Como medir se a experiência é de alta performance.

### Atividade de Fixação
Pense em um aplicativo que você usa diariamente. Identifique uma dor ou atrito que você enfrenta nele e escreva 3 possíveis melhorias focadas na facilidade de uso.`,
        order: 1,
        documents: [
          { id: 'doc-1-1', title: 'Fundamentos de UX - Slides da Aula.pdf', type: 'pdf', url: 'https://example.com/slides-ux.pdf', size: '2.4 MB' },
          { id: 'doc-1-2', title: 'Link de Apoio: Heurísticas de Nielsen', type: 'url', url: 'https://www.nngroup.com/articles/ten-usability-heuristics/' }
        ]
      },
      {
        id: 'lesson-1-2',
        courseId: 'course-1',
        title: 'Arquitetura de Informação e Fluxos de Navegação',
        duration: '22 min',
        videoUrl: 'https://vjs.zencdn.net/v/oceans.mp4',
        content: `### Estruturando o seu AVA
A arquitetura de informação lida com a organização dos conteúdos digitais de forma que façam sentido e guiem o usuário intuitivamente.

### Tópicos Abordados:
- **Card Sorting**: Técnica para entender como os usuários agrupam categorias em suas mentes.
- **Sitemaps**: O mapa visual das telas e links do seu sistema.
- **Fluxogramas do Usuário (User Flows)**: O caminho exato que uma pessoa traça para atingir um objetivo (ex: comprar um curso, emitir um certificado).

*Dica do instrutor*: Mantenha sempre a hierarquia clara, minimizando cliques!`,
        order: 2,
        documents: [
          { id: 'doc-1-3', title: 'Guia de Arquitetura de Informação.pdf', type: 'pdf', url: 'https://example.com/arquitetura.pdf', size: '1.8 MB' }
        ]
      },
      {
        id: 'lesson-1-3',
        courseId: 'course-1',
        title: 'Wireframes e Layouts Gráficos no Figma',
        duration: '30 min',
        content: `### Do Rabisco ao Esqueleto Digital
Os wireframes servem para validar a estrutura do layout sem o viés visual de cores, imagens e fontes específicas.

### Tipos de Wireframe:
1. **Baixa Fidelidade**: Desenhos rápidos no papel (paper prototyping). Útil para brainstorming.
2. **Média/Alta Fidelidade**: Feitos diretamente em ferramentas como o Figma, definindo espaçamentos exatos em pixels, grids (ex: grid de 12 colunas ou grid de 8pt) e áreas específicas para textos.

### Principais Exercícios no Figma:
- Utilização de Auto-layout para componentes responsivos.
- Grid de 8 pontos para espaçamento consistente.`,
        order: 3
      },
      {
        id: 'lesson-1-4',
        courseId: 'course-1',
        title: 'Construindo um Component Library / Design System',
        duration: '25 min',
        content: `### O que é um Design System?
É a única fonte da verdade (Single Source of Truth) para o design e a programação de um ecossistema digital. Contém componentes reutilizáveis, tokens visuais (cores, espaçamentos, sombras) orientados por regras e padrões claros.

### Benefícios:
- Consistência de marca.
- Velocidade na equipe de desenvolvimento.
- Escalabilidade para novas funcionalidades.

Vamos aprender a estruturar variantes de botões, inputs, cards e breadcrumbs de forma aninhada.`,
        order: 4
      },
      {
        id: 'lesson-1-5',
        courseId: 'course-1',
        title: 'Testes de Usabilidade e Coleta de Feedbacks',
        duration: '18 min',
        content: `### O Teste com Usuários Reais
Um design nunca está 100% pronto até que passe pelo teste empírico de um usuário real. Descubra as principais metodologias de teste:

- **Pensar em voz alta (Think Aloud)**: Peça ao usuário que relate tudo o que está pensando enquanto realiza tarefas específicas.
- **Testes A/B**: Comparação de duas opções funcionais para medir taxas de cliques e conversão de formulários.
- **Análise Heurística**: Avaliação técnica baseada nas 10 Heurísticas de Usabilidade de Nielsen (ex: visibilidade do status do sistema, prevenção de erros).`,
        order: 5
      }
    ],
    liveSessions: [
      {
        id: 'live-1-1',
        courseId: 'course-1',
        title: 'Abertura do Curso: Mentoria de Wireframes de Alta Fidelidade',
        scheduledAt: 'Hoje, às 19:30',
        durationMinutes: 60,
        meetingLink: 'https://meet.google.com/abc-defg-hij',
        isLive: true
      },
      {
        id: 'live-1-2',
        courseId: 'course-1',
        title: 'Feedback de Projetos Finais e Portfólio de UX',
        scheduledAt: 'Amanhã, às 18:00',
        durationMinutes: 90,
        meetingLink: 'https://meet.google.com/xyz-wdsa-qwe',
        isLive: false
      }
    ]
  },
  {
    id: 'course-2',
    title: 'Desenvolvimento Full-Stack: React, Node.js e APIs modernas',
    description: 'Domine a stack dominante do mercado. Construa aplicações robustas do backend ao frontend com TypeScript, rotas autenticadas, segurança de dados e deploying real.',
    category: 'Desenvolvimento Web',
    thumbnail: 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&auto=format&fit=crop&q=60',
    instructorName: 'Gestor de Cursos',
    lessons: [
      {
        id: 'lesson-2-1',
        courseId: 'course-2',
        title: 'Fundamentos de React & Configuração do Ambiente Vite',
        duration: '20 min',
        videoUrl: 'https://vjs.zencdn.net/v/oceans.mp4',
        content: `### Modern React com Vite
O Vite se tornou a ferramenta padrão de tooling para SPA por sua velocidade de compilação absurda e HMR otimizado.

Nesta aula:
1. **Componentização**: Pensamento atômico e reuso de interface.
2. **Propriedades (Props) e Estados (State)**: Renderizações reativas orientadas a alterações de dados locais.
3. **Hooks Fundamentais**: Entendendo o ciclo de renderização com \`useState\` e \`useEffect\`.`,
        order: 1
      },
      {
        id: 'lesson-2-2',
        courseId: 'course-2',
        title: 'NodeJS Express: Levantamento de APIs e Rotas REST',
        duration: '25 min',
        content: `### Servidores RESTful com Node & Express
Aprenda a estruturar um servidor de backend limpo escalável para o AVA usando TypeScript:

- **Sintaxe de Importação ES6 (ESM)**.
- **Roteadores do Express**: Organizando recursos como \`/courses\`, \`/users\` e \`/certificates\`.
- **Middlewares de Log e CORS**: Proteção de rotas e formatação de cabeçalhos de requisição.
- **Query, Params e Body**: As três maneiras de enviar dados para a API Express.`,
        order: 2
      },
      {
        id: 'lesson-2-3',
        courseId: 'course-2',
        title: 'Comunicação Full-Stack: Fetch, Axios e Custom Hooks',
        duration: '18 min',
        content: `### Conectando Pontas: Frontend & Backend
Veja como o seu aplicativo React se comunica com o backend Express:

- Tratamento de status HTTP (\`200 OK\`, \`401 Unauthorized\`, \`500 Internal Server Error\`).
- Manipulação de carregamento e manipulação robusta de erros.
- **Persistência das credenciais**: Manejo seguro com tokens de autenticação sem expor senhas a fraudes.`,
        order: 3
      },
      {
        id: 'lesson-2-4',
        courseId: 'course-2',
        title: 'Arquitetura de Dados: Noções de Bancos Relacionais e NoSQL',
        duration: '32 min',
        content: `### Armazenando Informações com Segurança
O coração de qualquer AVA é o armazenamento persistente de dados. Vamos analisar as diferenças:

- **Bancos Relacionais (SQL, e.g. PostgreSQL)**: Perfeitos para manter relacionamentos firmes entre alunos, inscrições, presença e notas. Uso de chaves estrangeiras.
- **Bancos Não-Relacionais (NoSQL, e.g. MongoDB, Firestore)**: Alto desempenho e flexibilidade para logs de bate-papo ao vivo e mensagens rápidas.
- Estruturando esquemas robustos para trilhas e subtrilhas.`,
        order: 4
      }
    ],
    liveSessions: [
      {
        id: 'live-2-1',
        courseId: 'course-2',
        title: 'Live Class: Desenvolvendo uma API Completa e Conexão de Banco de Dados',
        scheduledAt: 'Hoje, às 16:30',
        durationMinutes: 90,
        meetingLink: 'https://meet.google.com/pqr-stuv-wxy',
        isLive: true
      },
      {
        id: 'live-2-2',
        courseId: 'course-2',
        title: 'Implantando Serviços Web com Docker',
        scheduledAt: 'Próxima Segunda, às 20:00',
        durationMinutes: 60,
        meetingLink: 'https://meet.google.com/mno-abcd-efg',
        isLive: false
      }
    ]
  },
  {
    id: 'course-3',
    title: 'Metodologias Ágeis e Kanban na Gestão Escolar e TI',
    description: 'Transforme a gestão de entregas da sua equipe escolar ou corporativa utilizando Scrum, OKRs e Kanban estruturado para maximizar resultados.',
    category: 'Organização & Gestão',
    thumbnail: 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=800&auto=format&fit=crop&q=60',
    instructorName: 'Gestor de Cursos',
    lessons: [
      {
        id: 'lesson-3-1',
        courseId: 'course-3',
        title: 'Framework Scrum: Papéis e Cerimônias Ágeis',
        duration: '12 min',
        content: `### O Universo de Agilidade
Nesta aula inicial, desmistificamos os principais termos de projetos ágeis:

- **Product Owner (PO)**: Defensor das dores reais do cliente e mapeamento do Product Backlog.
- **Scrum Master (SM)**: Facilitador do time, focado em destravar impedimentos operacionais.
- **Developers/Tech Team**: O time focado na entrega incremental funcional.

### Cerimônias Importantes:
- Daily Scrum (Dailies de 15 minutos).
- Sprint Planning.
- Sprint Review e Retrospectiva.`,
        order: 1
      },
      {
        id: 'lesson-3-2',
        courseId: 'course-3',
        title: 'Fluxos Visuais com quadros Kanban e WIP Limit',
        duration: '16 min',
        content: `### Visualizando Gargalos
Kanban não é apenas colar post-its na parede! É sobre gerenciar um fluxo contínuo e limitar o trabalho em progresso:

- **WIP (Work In Progress) Limit**: Limitar o número máximo de itens em andamento para evitar multitarefas e estresse da equipe.
- **Classes de Serviço**: Priorização de bugs urgentes/criticos sobre features de backlog.
- **Lead Time & Cycle Time**: Métricas fundamentais de velocidade física da esteira de código.`,
        order: 2
      }
    ],
    liveSessions: [
      {
        id: 'live-3-1',
        courseId: 'course-3',
        title: 'Simulação de Sprint Game e Dinâmicas em Equipe',
        scheduledAt: 'Hoje, às 14:00',
        durationMinutes: 120,
        meetingLink: 'https://meet.google.com/klm-nopq-rst',
        isLive: false
      }
    ]
  }
];

export const INITIAL_LIBRARY = [
  { 
    id: 'lib-1', 
    title: 'Guia de Heurísticas de Usabilidade', 
    type: 'pdf', 
    category: 'Design', 
    description: 'Um guia completo sobre as 10 heurísticas de Nielsen para interfaces digitais.', 
    url: '#' 
  },
  { 
    id: 'lib-2', 
    title: 'Manual do Desenvolvedor Node.js', 
    type: 'pdf', 
    category: 'Desenvolvimento', 
    description: 'Referência rápida de comandos e boas práticas para servidores Express.', 
    url: '#' 
  },
  { 
    id: 'lib-3', 
    title: 'Templates de Branding e Logotipo', 
    type: 'link', 
    category: 'Design', 
    description: 'Recursos externos e assets do Figma para construção de marcas.', 
    url: '#' 
  },
  { 
    id: 'lib-4', 
    title: 'Checklist de Acessibilidade WCAG 2.1', 
    type: 'pdf', 
    category: 'Desenvolvimento', 
    description: 'Garanta que seus produtos digitais sejam inclusivos seguindo padrões globais.', 
    url: '#' 
  },
  { 
    id: 'lib-5', 
    title: 'Guia de Carreira: De Junior a Senior', 
    type: 'pdf', 
    category: 'Carreira', 
    description: 'Planejamento estratégico para evolução profissional na área de tecnologia.', 
    url: '#' 
  },
  { 
    id: 'lib-6', 
    title: 'Repositório Central de Assets UI', 
    type: 'link', 
    category: 'Design', 
    description: 'Coleção curada de ícones, ilustrações e fotos de alta resolução.', 
    url: '#' 
  },
];

export const INITIAL_WEBINARS = [
  { 
    id: 'web-1', 
    title: 'O Futuro da Inteligência Artificial no Design', 
    date: '15/06/2026', 
    time: '19:00', 
    description: 'Como ferramentas generativas estão mudando o workflow dos criativos.', 
    link: '#', 
    image: 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&auto=format&fit=crop&q=60' 
  },
  { 
    id: 'web-2', 
    title: 'Arquiteturas Modernas: De Monólitos a Microsserviços', 
    date: '22/06/2026', 
    time: '20:00', 
    description: 'Uma jornada técnica sobre escalabilidade de grandes plataformas.', 
    link: '#', 
    image: 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=800&auto=format&fit=crop&q=60' 
  },
  { 
    id: 'web-3', 
    title: 'Soft Skills: O Diferencial em Times de Alta Performance', 
    date: '30/06/2026', 
    time: '18:30', 
    description: 'Comunicação assertiva e resolução de conflitos em times remotos.', 
    link: '#', 
    image: 'https://images.unsplash.com/photo-1522071820081-37d40de3a9a1?w=800&auto=format&fit=crop&q=60' 
  },
  { 
    id: 'web-4', 
    title: 'Segurança da Informação e LGPD para Desenvolvedores', 
    date: '05/07/2026', 
    time: '15:00', 
    description: 'Proteção de dados e conformidade legal em aplicações modernas.', 
    link: '#', 
    image: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800&auto=format&fit=crop&q=60' 
  },
];
