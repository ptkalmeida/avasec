// Arquivo central de controle de funcionalidades (Feature Flags)
// Altere para 'true' para ativar ou 'false' para desativar/ocultar a funcionalidade no sistema.
// Todas as funcionalidades desativadas abaixo NÃO foram removidas do código; elas estão apenas
// ocultadas/desabilitadas nesta primeira entrega e podem ser reativadas facilmente alterando false para true.
export const features = {
  // --- FUNCIONALIDADES DESATIVADAS POR PADRÃO NO MVP (Podem ser reativadas a qualquer momento) ---
  
  // Fórum de discussão e mensagens entre alunos e professores
  forum: false,
  
  // Exercícios práticos e envio de atividades para correção (Tarefas avançadas)
  //
  // Desligada em 14/09/2026 por decisão de produto: a coordenação não vai usar
  // exercícios práticos nesta fase. Nada foi removido — o modelo, as rotas, a
  // página de entrega do aluno, a área de lançamento e correção do professor e
  // a seção do admin continuam no código e nos testes (ADR 12). Voltar ao ar é
  // trocar este false por true, junto com o espelho em
  // backend-laravel/config/features.php, que o FeatureFlagParityTest confere.
  //
  // Com ela desligada: /api/exercises e /api/exercise-submissions respondem 404
  // FEATURE_DISABLED, o front não busca os dados na hidratação, o item
  // "Exercícios Práticos" some do menu do admin, as sub-abas de exercício do
  // professor não existem, a fila "A fazer" não conta entregas, e o aluno não
  // vê o bloco nem alcança /aluno/curso/<slug>/exercicios.
  //
  // Atenção ao reativar: `practicalExercises` e `exerciseSubmissions` nascem do
  // localStorage ou de uma lista embutida em `LMSContext` — eles NÃO ficam
  // vazios com a flag desligada. Por isso o aluno é guardado pela flag, e não
  // por "a lista está vazia".
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

  // Eventos, Webinars e palestras integradas ao vivo.
  //
  // Desligada por decisao de produto em 04/09/2026: webinar nao entra nesta
  // fase. Nada foi removido — o modelo, as rotas, a área de gestão do professor,
  // a aba do aluno e a entrada no calendário público continuam no código e nos
  // testes. Voltar ao ar é trocar este false por true (e o espelho em
  // backend-laravel/config/features.php, que o FeatureFlagParityTest confere).
  //
  // Com ela desligada: as três rotas /api/webinars respondem 404
  // FEATURE_DISABLED, o front não busca os dados na hidratação, a aba
  // "Eventos & Webinars" do aluno não existe, o painel do professor não oferece
  // agendar nem gerenciar, e o calendário público monta só com aulas ao vivo.
  eventosWebinars: false,

  // Sala de Transmissão ao Vivo (LiveClassroom) e aulas síncronas integradas
  liveClassroom: false,

  // Dossiê Acadêmico Unificado completo
  dossieAcademico: false,

  // Regras de Participação e Critérios de Cancelamento de matrícula
  penalidadesCancelamento: false,

  // Sistema de mensagens diretas e chats de suporte internos
  mensagensDiretas: true,

  // Permite que o Admin Superior conceda a alunos específicos a possibilidade
  // de cursar mais de uma disciplina simultaneamente (por padrão, 1 por vez)
  matriculasMultiplas: true,

  // Aprovação automática de matrícula (decisão da coordenação em 14/09/2026).
  //
  // Ligada: a solicitação já nasce aprovada e matricula o aluno na mesma
  // transação, sem passar pela fila do professor. A tela "Solicitações de
  // Matrícula" e o aviso de aprovação pendente somem, porque não haverá
  // pendência — item de fila que nunca enche ensina a pessoa a ignorar a fila.
  //
  // As travas do caminho normal CONTINUAM valendo: quem já tem curso ativo, já
  // concluiu aquele curso ou está em restrição por cancelamento é recusado na
  // hora, com a razão escrita. Automática é "sem espera humana", não "sem
  // regra" — sem isso o sistema tiraria o aluno do curso em que ele tem
  // progresso só porque clicou em outro.
  //
  // Desligar devolve a fila de aprovação ao professor. Espelho em
  // backend-laravel/config/features.php.
  aprovacaoAutomaticaMatricula: true,

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

