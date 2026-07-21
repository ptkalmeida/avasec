// Popula o banco com os dados iniciais do AVASEC (equivalentes aos "defaults" que antes
// viviam espalhados pelo LMSContext.tsx). Compartilhado entre `prisma db seed` (prisma/seed.ts)
// e o endpoint POST /api/reset do servidor.
// É idempotente: usa upsert, então pode rodar várias vezes sem duplicar.
import type { PrismaClient } from '@prisma/client';
import bcrypt from 'bcryptjs';
import { INITIAL_COURSES, INITIAL_LIBRARY, INITIAL_WEBINARS } from '../src/data/mockData';

let prisma: PrismaClient;

async function seedCourses() {
  for (const course of INITIAL_COURSES) {
    const { lessons, liveSessions, ...courseData } = course;

    await prisma.course.upsert({
      where: { id: course.id },
      update: courseData,
      create: courseData,
    });

    for (const lesson of lessons) {
      const { documents, ...lessonData } = lesson;
      await prisma.lesson.upsert({
        where: { id: lesson.id },
        update: lessonData,
        create: lessonData,
      });

      for (const doc of documents ?? []) {
        await prisma.lessonDocument.upsert({
          where: { id: doc.id },
          update: { ...doc, lessonId: lesson.id },
          create: { ...doc, lessonId: lesson.id },
        });
      }
    }

    for (const session of liveSessions) {
      await prisma.liveSession.upsert({
        where: { id: session.id },
        update: session,
        create: session,
      });
    }
  }
  console.log(`✓ ${INITIAL_COURSES.length} cursos populados`);
}

async function seedLibrary() {
  for (const item of INITIAL_LIBRARY) {
    await prisma.libraryItem.upsert({
      where: { id: item.id },
      update: item as any,
      create: item as any,
    });
  }
  console.log(`✓ ${INITIAL_LIBRARY.length} itens de biblioteca populados`);
}

async function seedWebinars() {
  for (const webinar of INITIAL_WEBINARS) {
    await prisma.webinarEvent.upsert({
      where: { id: webinar.id },
      update: webinar,
      create: webinar,
    });
  }
  console.log(`✓ ${INITIAL_WEBINARS.length} webinars populados`);
}

// Usuários demo: os mesmos nomes/papéis que já existiam como "perfis simulados" no frontend,
// agora com senha real (hash bcrypt) em vez de texto puro no localStorage.
const DEMO_USERS: Array<{
  name: string; email: string; role: 'admin' | 'instructor' | 'student'; password: string;
  municipio?: string; uf?: string; areaInteresse?: string; dataCadastro?: string;
}> = [
  { name: 'Admin Superior', email: 'admin@avasec.local', role: 'admin', password: 'admin1234' },
  { name: 'Gestor de Conteúdos', email: 'professor@avasec.local', role: 'instructor', password: 'prof1234' },
  { name: 'João Silva', email: 'joao.silva@lms.edu', role: 'student', password: '1234', municipio: 'São Paulo', uf: 'SP', areaInteresse: 'Design Digital', dataCadastro: '2026-01-10' },
  { name: 'Gabriel Rodrigues', email: 'gabriel.rodrigues@lms.edu', role: 'student', password: '1234', municipio: 'Recife', uf: 'PE', areaInteresse: 'Economia Criativa & IA', dataCadastro: '2026-02-14' },
  { name: 'Beatriz Costa', email: 'beatriz.c@lms.edu', role: 'student', password: '1234', municipio: 'Rio de Janeiro', uf: 'RJ', areaInteresse: 'Design Digital', dataCadastro: '2026-03-05' },
  { name: 'Sofia Rocha', email: 'sofia.rocha@lms.edu', role: 'student', password: '1234', municipio: 'Salvador', uf: 'BA', areaInteresse: 'Políticas e Gestão Culturais', dataCadastro: '2026-03-12' },
  { name: 'Ana Souza', email: 'ana.souza@lms.edu', role: 'student', password: '1234', municipio: 'Olinda', uf: 'PE', areaInteresse: 'Economia Criativa & IA', dataCadastro: '2026-04-01' },
  { name: 'Lucas Santana', email: 'lucas.santana@lms.edu', role: 'student', password: '1234', municipio: 'Belo Horizonte', uf: 'MG', areaInteresse: 'Áreas Técnicas', dataCadastro: '2026-04-18' },
  { name: 'Carolina Mendes', email: 'carol.mendes@lms.edu', role: 'student', password: '1234', municipio: 'Caruaru', uf: 'PE', areaInteresse: 'Políticas e Gestão Culturais', dataCadastro: '2026-05-02' },
];

async function seedUsers() {
  for (const u of DEMO_USERS) {
    const { password, ...profile } = u;
    const passwordHash = await bcrypt.hash(password, 10);
    await prisma.user.upsert({
      where: { email: u.email },
      update: { ...profile, passwordHash },
      create: { ...profile, passwordHash },
    });
  }
  console.log(`✓ ${DEMO_USERS.length} usuários demo criados (ver senhas em prisma/seed.ts)`);
}

async function seedEnrollments() {
  const enrollments = [
    {
      studentName: 'João Silva',
      enrolledCourseId: 'course-1',
      enrolledAt: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString(),
      completedCourseIds: [] as string[],
      dropOutPenaltyUntil: null as string | null,
    },
    {
      studentName: 'Gabriel Rodrigues',
      enrolledCourseId: 'course-2',
      enrolledAt: new Date(Date.now() - 10 * 24 * 60 * 60 * 1000).toISOString(),
      completedCourseIds: [],
      dropOutPenaltyUntil: null,
    },
    {
      studentName: 'Beatriz Costa',
      enrolledCourseId: null,
      enrolledAt: null,
      completedCourseIds: ['course-1'],
      dropOutPenaltyUntil: null,
    },
    {
      studentName: 'Sofia Rocha',
      enrolledCourseId: null,
      enrolledAt: null,
      completedCourseIds: [],
      dropOutPenaltyUntil: new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString(),
    },
  ];

  for (const e of enrollments) {
    await prisma.studentEnrollment.upsert({
      where: { studentName: e.studentName },
      update: e,
      create: e,
    });
  }
  console.log(`✓ ${enrollments.length} matrículas iniciais populadas`);
}

async function seedProgress() {
  const progress = [
    { studentName: 'João Silva', courseId: 'course-1', completedLessons: ['lesson-1-1', 'lesson-1-2', 'lesson-1-3'], attendedLiveSessions: ['live-1-2'] },
    { studentName: 'João Silva', courseId: 'course-2', completedLessons: ['lesson-2-1'], attendedLiveSessions: [] },
  ];
  for (const p of progress) {
    await prisma.studentProgress.upsert({
      where: { studentName_courseId: { studentName: p.studentName, courseId: p.courseId } },
      update: p,
      create: p,
    });
  }
  console.log(`✓ ${progress.length} registros de progresso populados`);
}

async function seedQuizzes() {
  const quizzes = [
    {
      id: 'quiz-1',
      courseId: 'course-1',
      title: 'Quiz de UX e Heurísticas de Usabilidade',
      questions: [
        {
          id: 'quiz-1-q1',
          questionText: 'Qual heurística de Nielsen foca em manter o usuário ciente do andamento das ações do sistema?',
          options: ['Visibilidade do Status do Sistema', 'Consistência e Padrões', 'Prevenção de Erros', 'Flexibilidade e Eficiência de Uso'],
          correctOptionIndex: 0,
          explanation: 'A visibilidade do status do sistema garante que o usuário seja informado sobre o que está acontecendo por meio de feedbacks apropriados em tempo hábil.',
          reviewMessage: 'A visibilidade ajuda o usuário a se situar no fluxo da interface.',
          recommendedModule: 'Módulo 1 — Fundamentos de UX e Heurísticas de Nielsen',
          allowRetry: true,
        },
        {
          id: 'quiz-1-q2',
          questionText: 'Qual o tamanho de grid recomendado pelo Material Design como subdivisor padrão no Figma?',
          options: ['Grid de 5pt', 'Grid de 8pt', 'Grid de 12pt', 'Grid de 10pt'],
          correctOptionIndex: 1,
          explanation: 'O Material Design adota o grid de 8pt (e subdivisões de 4pt) como padrão por conta da consistência de renderização em diferentes resoluções de tela físicas.',
          reviewMessage: 'O grid de 8pt ajuda no alinhamento espacial de margens, paddings e elementos de UI.',
          recommendedModule: 'Módulo 2 — Construção de Grid e Layout no Figma',
          allowRetry: true,
        },
        {
          id: 'quiz-1-q3',
          questionText: 'Ao conduzir um teste Think Aloud, qual é o principal papel do facilitador?',
          options: [
            'Explicar passo a passo como resolver a interface',
            'Apenas observar em silêncio absoluto sem gerar áudio',
            'Incentivar o usuário a expressar seus pensamentos em voz alta sem direcionar suas escolhas',
            'Avaliar o usuário atribuindo uma nota de inteligência',
          ],
          correctOptionIndex: 2,
          explanation: 'O método Think Aloud visa extrair o fluxo mental consciente do usuário durante o uso. O facilitador deve lembrá-lo de verbalizar pensamentos de forma neutra.',
          reviewMessage: 'O Think Aloud foca na escuta ativa e neutralidade para extrair insights reais de usabilidade.',
          recommendedModule: 'Módulo 3 — Métodos de Testes de Usabilidade com Usuários',
          allowRetry: true,
        },
      ],
    },
    {
      id: 'quiz-2',
      courseId: 'course-2',
      title: 'Quiz de Fundamentos de Servidores Express',
      questions: [
        {
          id: 'quiz-2-q1',
          questionText:
            'Qual método HTTP deve ser preferencialmente utilizado de acordo com o padrão REST para atualizar parcialmente dados contidos em um registro existente?',
          options: ['POST', 'GET', 'PATCH', 'DELETE'],
          correctOptionIndex: 2,
          explanation: 'O método PATCH é recomendado para atualizações parciais, enquanto o PUT costuma ser usado para substituições completas do recurso.',
          reviewMessage: 'Utilizar os verbos corretos mantém a consistência da arquitetura RESTful.',
          recommendedModule: 'Módulo 1 — Rotas e Métodos de Requisição HTTP no Express',
          allowRetry: true,
        },
        {
          id: 'quiz-2-q2',
          questionText: 'Qual o papel principal do middleware de CORS em rotas Express de ambiente de produção?',
          options: [
            'Melhorar o visual de erro retornado ao usuário',
            'Permitir ou restringir requisições vindas de origens externas autorizadas',
            'Criptografar automaticamente todas as senhas armazenadas no PostgreSQL',
            'Acelerar a renderização do React',
          ],
          correctOptionIndex: 1,
          explanation: 'CORS (Cross-Origin Resource Sharing) controla a segurança de navegadores permitindo que recursos restritos de um site sejam solicitados por domínios autorizados.',
          reviewMessage: 'A configuração adequada de CORS evita brechas de segurança no acesso à API.',
          recommendedModule: 'Módulo 2 — Middlewares Essenciais e Segurança no Express',
          allowRetry: true,
        },
      ],
    },
  ];

  for (const quiz of quizzes) {
    const { questions, ...quizData } = quiz;
    await prisma.quiz.upsert({ where: { id: quiz.id }, update: quizData, create: quizData });
    for (const q of questions) {
      await prisma.quizQuestion.upsert({
        where: { id: q.id },
        update: { ...q, quizId: quiz.id },
        create: { ...q, quizId: quiz.id },
      });
    }
  }
  console.log(`✓ ${quizzes.length} quizzes populados`);
}

async function seedForumMessages() {
  const messages = [
    { id: 'forum-msg-1', courseId: 'course-1', senderName: 'Sofia Rocha', senderRole: 'student' as const, text: 'Oi pessoal! Alguém tem dicas sobre como aplicar a heurística de Prevenção de Erros em formulários longos em nossa aplicação?', timestamp: '15/06/2026, 14:32', likes: 3, likedBy: ['João Silva', 'Gabriel Rodrigues'] },
    { id: 'forum-msg-2', courseId: 'course-1', senderName: 'João Silva', senderRole: 'student' as const, text: 'Oi Sofia! Geralmente desabilitar o botão de continuar até que os campos de inputs obrigatórios estejam com formatos válidos ajuda imensamente, além de exibir feedback visual imediato.', timestamp: '15/06/2026, 14:48', likes: 5, likedBy: ['Sofia Rocha', 'Gabriel Rodrigues', 'Beatriz Costa'] },
    { id: 'forum-msg-3', courseId: 'course-1', senderName: 'Gestor de Conteúdos', senderRole: 'instructor' as const, text: 'Excelente discussão e fomento de ideias! Lembrem-se também de detalhar os erros de forma humanizada ao invés de usar códigos enigmáticos como "Error 412: Campo Requerido" (Heurística de Diagnóstico e Recuperação de Erros).', timestamp: '15/06/2026, 16:10', likes: 8, likedBy: ['Sofia Rocha', 'João Silva', 'Gabriel Rodrigues', 'Beatriz Costa'] },
    { id: 'forum-msg-4', courseId: 'course-2', senderName: 'Gabriel Rodrigues', senderRole: 'student' as const, text: 'Fala galera de Vídeo Mapping! Alguém que já trabalha na área indica algum projetor bacana para início de carreira ou instalações domésticas em paredes brancas simples?', timestamp: '16/06/2026, 10:15', likes: 2, likedBy: ['João Silva'] },
    { id: 'forum-msg-5', courseId: 'course-2', senderName: 'Gestor de Conteúdos', senderRole: 'instructor' as const, text: 'Olá Gabriel! Para superfícies brancas internas convencionais de baixa iluminação, projetores Epson de curta distância (Short Throw) com pelo menos 3000 ANSI Lumens atendem o alinhamento com folga. Desative o HMR e aproveite o alinhamento de canais!', timestamp: '16/06/2026, 11:02', likes: 4, likedBy: ['Gabriel Rodrigues', 'João Silva'] },
  ];
  for (const m of messages) {
    await prisma.forumMessage.upsert({ where: { id: m.id }, update: m, create: m });
  }
  console.log(`✓ ${messages.length} mensagens de fórum populadas`);
}

async function seedExercises() {
  const exercises = [
    { id: 'exercise-1', courseId: 'course-1', title: 'Análise de Heurísticas de Usabilidade', description: 'Escolha um site ou aplicativo de sua preferência e faça um relatório identificando pelo menos 3 violações das heurísticas de usabilidade de Nielsen, justificando sua análise.', instructions: 'Envie um relatório curto no campo de texto detalhando os pontos de atenção e propondo soluções de design simples para cada violação identificada.', maxPoints: 100, dueDate: '10/07/2026' },
    { id: 'exercise-2', courseId: 'course-2', title: 'Planejamento de Máscaras e Alinhamento', description: 'Elabore um plano de mapeamento de projeção para uma fachada de prédio geométrica simples contendo 3 janelas e uma porta central.', instructions: 'Escreva um plano passo-a-passo detalhando como você organizaria as camadas de mascaramento de corte para as janelas e portas para evitar luz intrusiva nos vidros, e quais softwares usaria.', maxPoints: 100, dueDate: '15/07/2026' },
  ];
  for (const ex of exercises) {
    await prisma.practicalExercise.upsert({ where: { id: ex.id }, update: ex, create: ex });
  }

  const submissions = [
    {
      id: 'submission-1',
      exerciseId: 'exercise-1',
      studentName: 'João Silva',
      submissionText:
        'Relatório de Usabilidade: Analisei o portal municipal da biblioteca.\n\n1. Visibilidade do status do sistema: Quando reservo um livro, a tela recarrega lentamente sem confirmação imediata, deixando o usuário sem saber se a operação deu certo.\n2. Prevenção de erros: O campo de busca de CPF aceita caracteres não-numéricos e quebra o banco.\n3. Consistência: Os botões de confirmação trocam de cor e lado dependendo da tela (às vezes verde na direita, às vezes azul na esquerda).\n\nRecomendação: Adicionar um Toast de sucesso e regex de validação de campo.',
      submittedAt: '26/06/2026, 15:42',
      status: 'approved' as const,
      score: 95,
      feedback: 'Excelente análise, João! Você compreendeu perfeitamente as Heurísticas de Usabilidade de Nielsen e propôs correções elegantes e econômicas. Parabéns!',
      gradedAt: '26/06/2026, 17:00',
      gradedBy: 'Gestor de Conteúdos',
    },
  ];
  for (const sub of submissions) {
    await prisma.exerciseSubmission.upsert({ where: { id: sub.id }, update: sub, create: sub });
  }
  console.log(`✓ ${exercises.length} exercícios e ${submissions.length} entregas populados`);
}

async function seedChatAndDirectMessages() {
  const chatMessages = [
    { id: 'msg-1', sessionId: 'live-1-1', senderName: 'Gestor de Conteúdos', senderRole: 'instructor' as const, text: 'Sejam bem-vindos à aula ao vivo sobre UX de Alta Performance! Podem enviar dúvidas aqui no chat.', timestamp: new Date().toISOString() },
    { id: 'msg-2', sessionId: 'live-1-1', senderName: 'João Silva', senderRole: 'student' as const, text: 'Olá Gestor! Esse grid de 8pt se aplica também para design mobile ou focamos em layouts web no Figma?', timestamp: new Date().toISOString() },
    { id: 'msg-3', sessionId: 'live-2-1', senderName: 'Gestor de Conteúdos', senderRole: 'instructor' as const, text: 'Iniciando em breve nossa aula prática de Express APIs!', timestamp: new Date().toISOString() },
  ];
  for (const m of chatMessages) {
    await prisma.chatMessage.upsert({ where: { id: m.id }, update: m, create: m });
  }

  const directMessages = [
    { id: 'dm-1', studentName: 'João Silva', senderName: 'João Silva', senderRole: 'student' as const, text: 'Olá Gestor, tudo bem? Estou gostando muito do curso de UX! Quando teremos o próximo feedback de portfólios?', timestamp: new Date(Date.now() - 3600000 * 5).toISOString() },
    { id: 'dm-2', studentName: 'João Silva', senderName: 'Gestor de Conteúdos', senderRole: 'instructor' as const, text: 'Olá João! Que ótimo que está curtindo. Teremos uma mentoria sobre isso hoje mesmo às 19:30, mas você pode também agendar um horário direto comigo se precisar!', timestamp: new Date(Date.now() - 3600000 * 4).toISOString() },
    { id: 'dm-3', studentName: 'Gabriel Rodrigues', senderName: 'Gabriel Rodrigues', senderRole: 'student' as const, text: 'Olá tutor Gestor! Enviei o link do meu protótipo no Figma para avaliação. Poderia dar uma olhada no fluxo de navegação?', timestamp: new Date(Date.now() - 3600000 * 3).toISOString() },
    { id: 'dm-4', studentName: 'Beatriz Costa', senderName: 'Beatriz Costa', senderRole: 'student' as const, text: 'Professor, tenho uma dúvida conceitual sobre a prestação de contas de nosso coletivo para editais da Lei Paulo Gustavo. Existe algum modelo de planilha que possamos seguir?', timestamp: new Date(Date.now() - 3600000 * 2.5).toISOString() },
    { id: 'dm-5', studentName: 'Sofia Rocha', senderName: 'Sofia Rocha', senderRole: 'student' as const, text: 'Estou com dificuldades para rodar o software de Vídeo Mapping em meu notebook antigo. Há alguma alternativa de projeção ou simulador mais leve recomendável?', timestamp: new Date(Date.now() - 3600000 * 1.5).toISOString() },
  ];
  for (const dm of directMessages) {
    await prisma.directMessage.upsert({ where: { id: dm.id }, update: dm, create: dm });
  }
  console.log(`✓ ${chatMessages.length} mensagens de chat e ${directMessages.length} mensagens diretas populadas`);
}

async function seedAcademicRequests() {
  const requests = [
    { id: 'req-1', studentName: 'João Silva', type: 'certificado' as const, description: 'Solicito a emissão do certificado prioritário do curso de Design de Interfaces de Alta Performance para comprovação de horas complementares na graduação.', status: 'pending' as const, submittedAt: '24/05/2026', courseTitle: 'Design de Interfaces de Alta Performance' },
    { id: 'req-2', studentName: 'Ana Souza', type: 'historico' as const, description: 'Necessito do envio do meu Histórico Escolar Acadêmico oficial em PDF referente ao meu progresso acumulado na plataforma para validação de estágio obrigatório.', status: 'pending' as const, submittedAt: '25/05/2026' },
    { id: 'req-3', studentName: 'Lucas Santana', type: 'matricula' as const, description: 'Não consigo acessar as aulas do curso de Desenvolvimento de Servidores com Node.js e Express. Solicito liberação manual da coordenação.', status: 'approved' as const, submittedAt: '26/05/2026', courseTitle: 'Desenvolvimento de Servidores com Node.js e Express' },
  ];
  for (const r of requests) {
    await prisma.academicRequest.upsert({ where: { id: r.id }, update: r, create: r });
  }
  console.log(`✓ ${requests.length} solicitações acadêmicas populadas`);
}

async function seedSystemSettings() {
  // Mesmo formato usado pelo AdminDashboard (painel de configurações da plataforma).
  const defaults = {
    allowDirectMessages: true,
    allowGlobalChat: true,
    openEnrollment: true,
    autoCertify: true,
    autoArchiveDuration: '12 meses',
    liveClassRecording: true,
  };
  await prisma.systemSettings.upsert({
    where: { id: 'singleton' },
    update: {},
    create: { id: 'singleton', data: defaults },
  });
  console.log('✓ Configurações do sistema inicializadas');
}

async function seedSecurityLogs() {
  const now = Date.now();
  const fmt = (ms: number) => new Date(ms).toLocaleTimeString('pt-BR') + ' ' + new Date(ms).toLocaleDateString('pt-BR');

  const logs = [
    { id: 'log-1', timestamp: fmt(now - 3600000 * 5), user: 'Admin Superior', role: 'admin', ipAddress: '192.168.1.14', device: 'Chrome / macOS (Sistema Autenticado)', action: 'Auditoria de Sistema', details: 'Geração de relatório geral de matrículas ativas na Escola da Cultura.', status: 'SUCCESS' as const },
    { id: 'log-2', timestamp: fmt(now - 3600000 * 3), user: 'Gestor de Conteúdos', role: 'instructor', ipAddress: '172.16.254.12', device: 'Firefox / Windows 11', action: 'Atualização de Aula', details: 'Novas diretrizes e links adicionados na aula inaugural de Vídeo Mapping.', status: 'SUCCESS' as const },
    { id: 'log-3', timestamp: fmt(now - 3600000 * 1), user: 'João Silva', role: 'student', ipAddress: '189.122.45.92', device: 'Safari / iPhone 15 Pro', action: 'Autenticação no Sistema', details: 'Acesso realizado com êxito sob as diretrizes de LGPD e segurança de canais.', status: 'SUCCESS' as const },
  ];

  for (const log of logs) {
    await prisma.securityLog.upsert({ where: { id: log.id }, update: log, create: log });
  }
  console.log(`✓ ${logs.length} logs de auditoria populados`);
}

export async function runSeed(client: PrismaClient) {
  prisma = client;
  console.log('Iniciando seed do AVASEC...');
  await seedSystemSettings();
  await seedUsers();
  await seedCourses();
  await seedLibrary();
  await seedWebinars();
  await seedEnrollments();
  await seedProgress();
  await seedQuizzes();
  await seedForumMessages();
  await seedExercises();
  await seedChatAndDirectMessages();
  await seedAcademicRequests();
  await seedSecurityLogs();
  console.log('Seed concluído com sucesso.');
}
