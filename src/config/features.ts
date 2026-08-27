// Arquivo central de controle de funcionalidades (Feature Flags)
// Altere para 'true' para ativar ou 'false' para desativar/ocultar a funcionalidade no sistema.
// Todas as funcionalidades desativadas abaixo NÃO foram removidas do código; elas estão apenas
// ocultadas/desabilitadas nesta primeira entrega e podem ser reativadas facilmente alterando false para true.
export const features = {
  // --- FUNCIONALIDADES DESATIVADAS POR PADRÃO NO MVP (Podem ser reativadas a qualquer momento) ---
  
  // Fórum de discussão e mensagens entre alunos e professores
  forum: false,
  
  // Exercícios práticos e envio de atividades para correção (Tarefas avançadas)
  atividadesPraticasAvancadas: false,
  
  // Upload de arquivos (como documentos, PDFs e comprovantes de matrícula)
  // Ativado: o backend agora salva os arquivos de verdade em /uploads (ver src/server/upload.ts).
  uploadArquivos: true,
  
  // Gráficos e dashboards analíticos complexos (Recharts, D3, etc.)
  graficosAvancados: false,
  
  // Acompanhamento detalhado de participação e logs de auditoria dos alunos
  acompanhamentoParticipacao: false,
  
  // Histórico escolar avançado completo com todos os detalhes adicionais
  historicoAvancado: false,
  
  // Suporte a múltiplos idiomas (Inglês, Espanhol, Português, etc.)
  internacionalizacao: false,
  
  // Gamificação (sistema de pontos, conquistas, rankings e medalhas)
  gamificacao: false,
  
  // Trilhas de aprendizagem avançadas e pré-requisitos de cursos
  trilhasAvancadas: false,
  
  // Solicitações acadêmicas formais e envio de requerimentos (Documentos em análise / Secretaria)
  solicitacoesAcademicas: false,

  // Biblioteca Digital de arquivos adicionais globais
  bibliotecaDigital: false,

  // Eventos, Webinars e palestras integradas ao vivo
  eventosWebinars: false,

  // Sala de Transmissão ao Vivo (LiveClassroom) e aulas síncronas integradas
  liveClassroom: false,

  // Dossiê Acadêmico Unificado completo
  dossieAcademico: false,

  // Regras de Participação e Critérios de Cancelamento de matrícula
  penalidadesCancelamento: false,

  // Sistema de mensagens diretas e chats de suporte internos
  mensagensDiretas: false,

  // Permite que o Admin Superior conceda a alunos específicos a possibilidade
  // de cursar mais de uma disciplina simultaneamente (por padrão, 1 por vez)
  matriculasMultiplas: true,

  // --- FUNCIONALIDADES ATIVADAS POR PADRÃO NO MVP ---
  
  // Catálogo público de cursos disponíveis
  catalogoCursos: true,
  
  // Visualização de detalhes do curso, ementa e informações gerais
  detalhesCurso: true,
  
  // Matrícula ativa e cancelamento simples de matrículas (sem restrições)
  matricula: true,
  
  // Navegação por módulos e assistir aulas gravadas
  modulosAulas: true,
  
  // Acesso a materiais de leitura e links complementares de cada aula
  materiaisComplementares: true,
  
  // Quiz simples de múltipla escolha para fixação do conteúdo ao final da aula
  quizSimples: true,
  
  // Barra de progresso e estatísticas de conclusão do curso
  progresso: true,
  
  // Emissão de certificado de conclusão após concluir o percentual mínimo configurado
  certificados: true,
  
  // Dados de gestão escolar (relatórios simples para administradores/professores, exportação de dados)
  dadosGerenciais: true,
  
  // Visualização do perfil básico do usuário (dados pessoais essenciais)
  perfilBasico: true,

  // Gestão do conteúdo das páginas públicas do portal pelo Admin Superior
  // (textos e listas de O AVA, O Projeto, Cursos, Certificados, Calendário,
  // Notícias, Dúvidas e Orientações). Desligada = páginas usam os defaults.
  gestaoConteudoSite: true
};

