/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'motion/react';
import {
  BookOpen, Calendar, CheckCircle, Award, Video, Clock, ChevronRight,
  FileCheck, ArrowRight, ArrowLeft, User, Settings, Sparkles, BookMarked, Monitor, Linkedin, Download, Globe, PlayCircle,
  Lock, MessageSquare, Send, ChevronDown, Check, Play, FileText, Notebook, Layers, HelpCircle, CheckSquare, ExternalLink, Archive, Library, Info,
  Bell, Shield, Smartphone, X, Bold, Italic, Underline, List, ListOrdered,
  AlertTriangle, Lightbulb, Tag, LayoutGrid, Star, PartyPopper
} from 'lucide-react';
import { useLocation, useNavigate } from 'react-router-dom';
import { DashboardTab, useLMS, authFetch } from '../context/LMSContext';
import { RAIZ_ALUNO, caminhoAluno, parseAluno } from '../router/studentRoutes';
import { assuntoDaMensagem, comAssuntoDaAula } from '../utils/assuntoMensagem';
import { cursoPorRef, refDoCurso, refEhCanonica } from '../utils/cursoRef';
import { avaliacoesPendentes, oQueFaltaParaOCertificado } from '../utils/certificadoElegivel';
import { abaVisivelParaAluno } from '../utils/abasAluno';
import { VideoPlayer } from './shared/VideoPlayer';
import { downloadSubmissionFile } from '../utils/fileDownload';
import { courseMinAttendance, QUIZ_PASS_THRESHOLD } from '../config/constants';
import { Course, Lesson, LiveSession, isCourseExpired } from '../types';
import { LiveClassroom } from './LiveClassroom';
import { CourseForum } from './CourseForum';
import { StudentLibraryPanel } from './student/StudentLibraryPanel';
import { ExerciciosPraticosPage } from './student/ExerciciosPraticosPage';
import { AvaliacoesPage } from './student/AvaliacoesPage';
import { StudentEventsPanel } from './student/StudentEventsPanel';
import { features } from '../config/features';
import { parseLessonContent } from '../utils/lessonContent';
import { parseVideoSource } from '../utils/videoSource';
import { LessonContent } from './student/LessonContent';
import { LessonIndex } from './student/LessonIndex';
import { safeHref } from '../utils/safeUrl';
import { sanitizeNoteHtml, escapeHtml } from '../utils/noteHtml';
import { formatScheduledAt, dataCurta, horaCurta, transmissoesDoDia, situacaoTransmissao, encerradaPorTempo } from '../utils/liveSchedule';
import { exerciciosDoCurso } from '../utils/exerciseStatus';
import { tentativaVigente, textoDaTentativa } from '../utils/quizAttempts';

/*
 * `getCourseModules` foi REMOVIDO daqui.
 *
 * Ele montava tres "modulos" por curso — nome e descricao escritos neste
 * arquivo, presos a `course.id === 'course-1'` / `'course-2'`, e dividia as
 * aulas por POSICAO (`lessons.slice(0, 2)`). Modulo nao existe no banco:
 * `Lesson` nao tem coluna de modulo e nao ha tabela `Module`.
 *
 * O que isso causava, medido e nao suposto: o gestor nao podia criar, renomear
 * nem reordenar modulo (nao ha campo); inserir uma aula no comeco fazia as aulas
 * escorregarem de modulo em silencio, sob um titulo que descrevia outro
 * conteudo; e todo curso fora daqueles dois recebia "Modulo 1: Introducao
 * Basica" prometendo "exercicios de fixacao assistida e material complementar"
 * que podiam nao existir.
 *
 * Decisao da coordenacao (09/09/2026): modulo nao precisa existir — a lista de
 * aulas basta. As aulas vem de `aulasEmOrdem`, ordenadas por `order`.
 */

interface StudentDashboardProps {
  onBackToLanding?: () => void;
  onNavigateToProfile?: () => void;
  speakText: (text: string) => void;
}

export const StudentDashboard: React.FC<StudentDashboardProps> = ({ onBackToLanding, onNavigateToProfile, speakText }) => {
  const {
    courses,
    progress,
    certificates,
    activeUser,
    directMessages,
    toggleLessonCompletion,
    calculateAttendancePercent,
    sendDirectMessage,
    quizzes,
    quizSubmissions,
    submitQuiz,
    academicRequests,
    addAcademicRequest,
    systemSettings,
    accessibilitySettings,
    updateAccessibilitySettings,
    currentLang,
    setCurrentLang,
    textSizeMultiplier,
    setTextSizeMultiplier,
    isSpeechEnabled,
    setIsSpeechEnabled,
    studentEnrollments,
    enrollStudentInCourse,
    dropStudentFromCourse,
    completeStudentCourse,
    clearStudentPenalty,
    practicalExercises,
    exerciseSubmissions,
    submitExercise,
  } = useLMS();

  /*
   * `undefined` aqui significa "ainda não chegou do servidor", e o padrão abaixo
   * o transforma em "não tem matrícula" — indistinguíveis para quem lê depois.
   * A guarda de endereço precisa da diferença: agir sobre "não tem matrícula"
   * enquanto a resposta está em trânsito tira o aluno do curso dele.
   */
  const matriculaCarregada = studentEnrollments[activeUser.id] !== undefined;
  const enrollmentRecord = studentEnrollments[activeUser.id] || { enrolledCourseId: null, completedCourseIds: [], dropOutPenaltyUntil: null, canMultiEnroll: false, extraCourseIds: [] };
  const activeEnrolledCourseIds = [enrollmentRecord.enrolledCourseId, ...(enrollmentRecord.extraCourseIds || [])]
    .filter((id): id is string => !!id);
  const canEnrollInMoreCourses = activeEnrolledCourseIds.length === 0
    || (features.matriculasMultiplas && enrollmentRecord.canMultiEnroll);

  // Cursos que o aluno realmente pode cursar agora: fora os vencidos, os que já
  // cursa e os já concluídos (esses ficam em "Cursos Concluídos", para revisão).
  // Fonte única do catálogo, para os filtros e a grade não divergirem na contagem.
  const enrollableCourses = courses.filter(c =>
    !isCourseExpired(c.contractExpirationDate)
    && !activeEnrolledCourseIds.includes(c.id)
    && !(enrollmentRecord.completedCourseIds ?? []).includes(c.id)
  );

  // Presença do gestor responsável pelo curso ativo — a chave de presença é por userId (ADR 10).
  const enrolledCourseInstructorId = courses.find(c => c.id === enrollmentRecord.enrolledCourseId)?.instructorId ?? '';

  /**
   * Upload do anexo da entrega. Fica aqui, e não na página, porque a rota e a
   * visibilidade do arquivo (private) são decisão desta aplicação, não do
   * componente de tela.
   */
  const enviarAnexoDeEntrega = async (
    file: File
  ): Promise<{ name: string; url: string } | { error: string }> => {
    try {
      const formData = new FormData();
      formData.append('file', file);
      const res = await authFetch('/api/upload?visibility=private', { method: 'POST', body: formData });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        return { error: err.message || 'Falha ao enviar o arquivo. Verifique o formato e o tamanho.' };
      }
      const data = await res.json();
      return { name: data.fileName, url: data.url };
    } catch {
      return { error: 'Servidor indisponível para envio de arquivos.' };
    }
  };

  /**
   * Abre a página de avaliações, opcionalmente já dentro de uma prova.
   *
   * Uma navegação só. Eram dois setters em sequência, e com o endereço no
   * comando isso deixaria duas entradas no histórico para um clique — a segunda
   * ainda calculada a partir do destino do render, desfazendo a primeira.
   */
  const abrirAvaliacoes = (quizId?: string) => {
    irPara({ tela: 'avaliacoes', quizId: quizId ?? null });
  };

  const handleBack = () => {
    if (showExercicios) {
      setShowExercicios(false);
    } else if (showAvaliacoes) {
      setShowAvaliacoes(false);
    } else if (activeLesson) {
      setActiveLesson(null);
    } else if (selectedCourse) {
      setSelectedCourse(null);
    } else if (activeDashboardTab !== 'general') {
      setActiveDashboardTab('general');
    } else if (onBackToLanding) {
      onBackToLanding();
    }
  };

  const getBackLabel = () => {
    if (showExercicios) return "Voltar ao Curso";
    if (showAvaliacoes) return "Voltar ao Curso";
    if (activeLesson) return "Voltar ao Curso";
    if (selectedCourse) return "Voltar p/ Meus Cursos";
    if (activeDashboardTab !== 'general') return "Voltar ao Ambiente de Estudos";
    return "Sair p/ Portal";
  };

  /*
   * NAVEGAÇÃO — derivada do ENDEREÇO, não guardada em `useState`.
   *
   * Eram seis estados invisíveis (curso, aula, avaliações, prova, exercícios,
   * sala ao vivo) e todos moravam sob a mesma URL, `/app`. A prova em andamento,
   * a aula com vídeo e a lista de cursos tinham o mesmo endereço: nada linkável,
   * e um F5 no meio da prova caía na lista de cursos.
   *
   * As ASSINATURAS dos setters continuam idênticas de propósito — `setSelectedCourse`
   * recebe um `Course | null` como antes, `setActiveLesson` um `Lesson | null`.
   * São dezenas de pontos de chamada na árvore de render, e nenhum precisou saber
   * que agora aquilo empurra uma entrada no histórico.
   *
   * O que MUDOU e exigiu cuidado: dois setters no mesmo handler dão duas
   * navegações, e a segunda calcula o caminho a partir do MESMO `destino` do
   * render — então ela desfaz a primeira. Os handlers que trocavam dois estados
   * de uma vez foram reunidos em uma navegação só (`voltarParaMeusCursos`,
   * `abrirModulo`, `abrirAvaliacoes`).
   */
  const navigate = useNavigate();
  const location = useLocation();
  const destino = React.useMemo(
    () => parseAluno(location.pathname, location.search),
    [location.pathname, location.search]
  );

  /** Uma navegação: parte do destino atual e troca só o que o setter pediu. */
  const irPara = (
    mudanca: Parameters<typeof caminhoAluno>[0],
    substituir = false
  ): void => {
    navigate(caminhoAluno({ ...destino, ...mudanca }), { replace: substituir });
  };

  /*
   * Curso do endereço. Antes de existir URL, `selectedCourse` era um objeto
   * guardado; agora é procurado pelo id a cada render. Id que não corresponde a
   * curso nenhum devolve null — e a tela cai em "Meus Cursos", que é o que já
   * acontecia quando nada estava selecionado.
   *
   * A busca é RESTRITA aos cursos que este aluno pode abrir: os que cursa mais
   * os que concluiu (concluído é revisável, só não é rematriculável). Antes o
   * curso só podia ser alcançado por clique num card que já era dele; com o
   * endereço digitável, `/aluno/curso/<id>` de qualquer curso do catálogo
   * abriria a casca da sala de aula de um curso alheio. O material em si nunca
   * sairia — o servidor entrega conteúdo só para quem pertence ao curso —, mas a
   * tela não pode oferecer o que a API vai negar.
   */
  const idsQueMePertencem = [
    ...activeEnrolledCourseIds,
    ...(enrollmentRecord.completedCourseIds ?? []),
  ];
  // A chave é a lista serializada, não o array: `activeEnrolledCourseIds` é
  // recriado a cada render, e o memo com ele na dependência nunca reaproveitaria.
  const chaveDosMeusCursos = idsQueMePertencem.join('|');
  const cursosQueMePertencem = React.useMemo(
    () => new Set<string>(idsQueMePertencem),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [chaveDosMeusCursos]
  );

  /*
   * O endereço traz o SLUG do curso (ADR 13), e ainda aceita o id de link
   * antigo. O pertencimento continua sendo checado pelo ID — a lista de cursos
   * do aluno é de ids, e comparar slug com id daria sempre falso, tirando o
   * aluno do próprio curso.
   */
  const cursoDoEndereco = cursoPorRef(courses, destino.cursoRef);
  const selectedCourse: Course | null = cursoDoEndereco !== null && cursosQueMePertencem.has(cursoDoEndereco.id)
    ? cursoDoEndereco
    : null;

  /*
   * O que ainda falta para o certificado deste curso.
   *
   * Frequencia deixou de bastar quando o curso avalia (decisao de 09/09/2026):
   * com conclusao automatica ao avancar, clicar "Proxima aula" ate o fim dava
   * 100% de presenca e o certificado saia sem uma questao respondida. Quem
   * decide e o servidor; isto existe para a tela nao prometer o que ele vai
   * recusar.
   */
  const avaliacoesQueFaltam = selectedCourse === null
    ? []
    : avaliacoesPendentes(quizzes, quizSubmissions, selectedCourse.id, activeUser.id);
  const faltaParaCertificado = selectedCourse === null
    ? null
    : oQueFaltaParaOCertificado({
      frequencia: calculateAttendancePercent(selectedCourse.id),
      frequenciaMinima: courseMinAttendance(selectedCourse),
      pendentes: avaliacoesQueFaltam,
    });

  /*
   * Aulas na ordem que o gestor definiu.
   *
   * `order` e o eixo real da grade; confiar na ordem em que o array chegou
   * deixaria a lista mudar de posicao entre renders sem ninguem ter mexido nela.
   */
  const aulasEmOrdem: Lesson[] = React.useMemo(
    () => (selectedCourse === null ? [] : [...selectedCourse.lessons].sort((a, b) => a.order - b.order)),
    [selectedCourse]
  );

  const activeLesson: Lesson | null = destino.lessonId === null || selectedCourse === null
    ? null
    : selectedCourse.lessons.find((l) => l.id === destino.lessonId) ?? null;

  const activeLiveSession: LiveSession | null = destino.sessionId === null || selectedCourse === null
    ? null
    : (selectedCourse.liveSessions ?? []).find((s) => s.id === destino.sessionId) ?? null;

  /*
   * Avaliações e exercícios são página cheia, não modal. Prova é a atividade
   * mais longa que o aluno faz aqui e vivia num modal que fechava por clique no
   * backdrop; exercício pertence ao curso e era uma aba dentro da aula 1.
   *
   * O ANDAMENTO da prova (questão atual, respostas, resultado) segue morando
   * dentro da página — não é assunto deste painel. O que o endereço carrega é
   * QUAL prova está aberta, não o que já foi respondido: recarregar volta para a
   * prova, com a tentativa começando de novo, e é por isso que o Voltar do
   * navegador precisa cair na confirmação de descarte (ver `AvaliacoesPage`).
   */
  /*
   * As duas dependem de HAVER curso. O redirecionamento abaixo roda depois do
   * render, então sem esta condição um `/aluno/curso/<id-invalido>/avaliacoes`
   * renderizaria a página de avaliações com curso nulo por um quadro — e o
   * `tsconfig` deste projeto não tem `strictNullChecks`, então o compilador não
   * avisaria.
   */
  const showAvaliacoes = destino.tela === 'avaliacoes' && selectedCourse !== null;
  const showExercicios = destino.tela === 'exercicios' && selectedCourse !== null;
  /** Avaliação que o endereço pede para abrir direto. */
  const avaliacaoInicial = destino.quizId;

  const viewingCatalogCourse: Course | null = destino.catalogoId === null
    ? null
    : cursoPorRef(courses, destino.catalogoId);

  const activeDashboardTab: DashboardTab = destino.aba;

  const setSelectedCourse = (curso: Course | null): void => {
    irPara(curso === null
      ? { tela: 'painel', aba: 'general', cursoRef: null }
      : { tela: 'curso', cursoRef: refDoCurso(curso) });
  };

  const setActiveLesson = (aula: Lesson | null): void => {
    irPara(aula === null ? { tela: 'curso' } : { tela: 'aula', lessonId: aula.id });
  };

  const setActiveLiveSession = (sessao: LiveSession | null): void => {
    irPara(sessao === null ? { tela: 'curso' } : { tela: 'ao-vivo', sessionId: sessao.id });
  };

  const setShowAvaliacoes = (mostrar: boolean): void => {
    irPara(mostrar ? { tela: 'avaliacoes' } : { tela: 'curso' });
  };

  const setShowExercicios = (mostrar: boolean): void => {
    irPara(mostrar ? { tela: 'exercicios' } : { tela: 'curso' });
  };

  const setViewingCatalogCourse = (curso: Course | null): void => {
    irPara(curso === null
      ? { tela: 'painel', aba: 'general', catalogoId: null }
      : { tela: 'catalogo', catalogoId: curso.id });
  };

  const setActiveDashboardTab = (aba: DashboardTab): void => {
    irPara({ tela: 'painel', aba, cursoRef: null, catalogoId: null });
  };

  /** Sai do curso inteiro numa navegação só. */
  const voltarParaMeusCursos = (): void => {
    irPara({ tela: 'painel', aba: 'general', cursoRef: null, catalogoId: null });
  };

  // Custom non-blocking alert/confirm states
  const [alertState, setAlertState] = useState<{ message: string; show: boolean } | null>(null);
  const [confirmState, setConfirmState] = useState<{ message: string; onConfirm: () => void; show: boolean } | null>(null);

  const showAlert = (message: string) => {
    setAlertState({ message, show: true });
    speakText(message);
  };

  const showConfirm = (message: string, onConfirm: () => void) => {
    setConfirmState({ message, onConfirm, show: true });
  };

  // Custom states
  const [searchQuery, setSearchQuery] = useState('');
  const [sortType, setSortType] = useState<'alphabetical-asc' | 'alphabetical-desc' | 'recent'>('recent');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [expandedLessons, setExpandedLessons] = useState<{[key: string]: boolean}>({ '0': true });
  const [isEnrollModalOpen, setIsEnrollModalOpen] = useState(false);
  const [isEnrollRulesChecked, setIsEnrollRulesChecked] = useState(false);
  const [enrollSuccessMessage, setEnrollSuccessMessage] = useState<string | null>(null);
  const [isFullSyllabusOpen, setIsFullSyllabusOpen] = useState(false);
  const [faqSearchQuery, setFaqSearchQuery] = useState('');
  const [isFaqDrawerOpen, setIsFaqDrawerOpen] = useState(false);
  const [expandedFaqId, setExpandedFaqId] = useState<string | null>(null);
  const [selectedFaqCategory, setSelectedFaqCategory] = useState<string>('all');
  const [lockedCourseWarning, setLockedCourseWarning] = useState<string | null>(null);
  const [showUpcomingCalendar, setShowUpcomingCalendar] = useState(false);
  const [showKnowledgeBase, setShowKnowledgeBase] = useState(false);
  const [notifications, setNotifications] = useState({ email: true, push: true, sms: false });
  const [twoFactor, setTwoFactor] = useState(false);
  const [language, setLanguage] = useState('Português (BR)');
  const [penaltyJustification, setPenaltyJustification] = useState('');


  /*
   * Slug APOSENTADO: o curso foi renomeado depois de alguém salvar o link.
   *
   * O slug antigo não está na lista de cursos — ele vive no histórico, no banco
   * — então sem isto o link salvo cairia na regra de "curso que não é deste
   * aluno" e a pessoa seria devolvida à raiz do painel. Era exatamente o defeito
   * que a ADR 13 pagou uma migration para não ter.
   *
   * `resolvendoSlug` segura o redirecionamento enquanto a pergunta está no ar.
   * Sem essa trava as duas regras corriam juntas e a mais rápida ganhava — o
   * tipo de falha que aparece em uma máquina e não na outra.
   */
  const [resolvendoSlug, setResolvendoSlug] = useState(false);

  useEffect(() => {
    const ref = destino.cursoRef;
    if (ref === null || courses.length === 0) return;
    // A referência já é um curso conhecido: nada a resolver.
    if (cursoPorRef(courses, ref) !== null) return;

    let cancelado = false;
    setResolvendoSlug(true);

    fetch(`/api/courses/resolve/${encodeURIComponent(ref)}`, { credentials: 'include' })
      .then((res) => (res.ok ? res.json() : null))
      .then((dados) => {
        if (cancelado) return;
        const canonico = typeof dados?.slug === 'string' && dados.slug !== '' ? dados.slug : null;
        // Só troca se o servidor devolveu OUTRA referência. Devolver a mesma e
        // navegar de novo seria um laço de navegação.
        if (canonico !== null && canonico !== ref) {
          navigate(caminhoAluno({ ...destino, cursoRef: canonico }), { replace: true });
        }
      })
      .catch(() => {
        // Curso desconhecido ou rede fora: a guarda seguinte cuida do destino.
        // Falhar em silêncio aqui é correto — não há nada a dizer ao aluno sobre
        // um endereço que ele não digitou.
      })
      .finally(() => {
        if (!cancelado) setResolvendoSlug(false);
      });

    return () => { cancelado = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [destino.cursoRef, courses.length]);

  /*
   * Endereço com a referência NÃO canônica (id antigo, ou slug aposentado que já
   * foi resolvido) é trocado pelo endereço de hoje.
   *
   * Sem isto o `course-1` sobrevive à mudança que veio removê-lo: a pessoa abre
   * por um link antigo, copia da barra e manda adiante a forma velha.
   */
  useEffect(() => {
    if (selectedCourse === null) return;
    if (refEhCanonica(selectedCourse, destino.cursoRef)) return;

    navigate(caminhoAluno({ ...destino, cursoRef: refDoCurso(selectedCourse) }), { replace: true });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedCourse, destino.cursoRef]);

  /*
   * Endereço que aponta para curso que não é deste aluno volta para a raiz do
   * painel, SEM deixar entrada no histórico — senão o Voltar devolveria a pessoa
   * ao endereço recusado, num laço.
   *
   * Espera `courses` E a MATRÍCULA carregarem. Esperar só o catálogo não basta,
   * e isto foi observado no navegador, não previsto: as duas cargas são
   * independentes, e quando a lista de cursos chega primeiro existe um instante
   * em que o aluno não parece pertencer a curso nenhum. Redirecionar nesse
   * instante expulsa a pessoa do próprio curso ao dar F5 — de forma
   * intermitente, que é o pior modo de falhar.
   */
  useEffect(() => {
    if (destino.cursoRef === null || courses.length === 0 || !matriculaCarregada) return;
    if (selectedCourse !== null) return;
    // Slug aposentado (curso renomeado) não está na lista: o servidor resolve, e
    // enquanto isso não se redireciona. Ver o efeito de canonicalização abaixo.
    if (resolvendoSlug) return;

    navigate(RAIZ_ALUNO, { replace: true });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [destino.cursoRef, selectedCourse, courses.length, matriculaCarregada, resolvendoSlug]);

  /*
   * Link no formato ANTIGO (`?courseId=&module=&lesson=`) vira o endereço novo.
   *
   * Aquele formato era lido só na montagem e não sobrevivia a nada — mas pode
   * ter sido enviado a alguém, então em vez de simplesmente sumir ele é
   * traduzido. `replace`: o endereço velho não fica no histórico, senão o Voltar
   * devolveria a pessoa a um formato que já não é lido.
   */
  useEffect(() => {
    const params = new URLSearchParams(location.search);
    const courseIdParam = params.get('courseId');
    if (courseIdParam === null || courses.length === 0) return;

    const course = courses.find((c) => c.id === courseIdParam);
    if (!course) return;

    const lessonParam = params.get('lesson');
    const lesson = lessonParam === null
      ? undefined
      : course.lessons.find((l) => l.id === lessonParam);

    navigate(
      caminhoAluno({
        tela: lesson ? 'aula' : 'curso',
        cursoRef: refDoCurso(course),
        lessonId: lesson?.id ?? null,
      }),
      { replace: true }
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [courses, location.search]);

  /*
   * Volta para a raiz do painel num salto só. Eram quatro setters em sequência,
   * o que agora seriam quatro entradas no histórico para um evento — e as três
   * últimas, calculadas a partir do destino do render, desfariam a primeira.
   */
  useEffect(() => {
    const handleResetDashboard = () => navigate(RAIZ_ALUNO);
    window.addEventListener('reset-dashboard', handleResetDashboard);
    return () => window.removeEventListener('reset-dashboard', handleResetDashboard);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Student private notebook states
  const [activeTab, setActiveTab] = useState<'teoria' | 'anotacao' | 'suporte' | 'forum'>('teoria');
  
  // Auto-switch away from disabled tabs
  useEffect(() => {
    if (activeTab === 'forum' && !features.forum) {
      setActiveTab('teoria');
    }
    // Suporte depende do canal de mensagens; sem ele a aba não existe.
    if (activeTab === 'suporte' && !features.mensagensDiretas) {
      setActiveTab('teoria');
    }
  }, [activeTab]);

  
  const [savedNotes, setSavedNotes] = useState<{[key: string]: string}>(() => {
    try {
      const saved = localStorage.getItem('ava_student_lesson_notes');
      return saved ? JSON.parse(saved) : {};
    } catch {
      return {};
    }
  });

  // Editor WYSIWYG (contentEditable + execCommand) — sem dependência nova.
  // contentEditable é inerentemente não-controlado, então o conteúdo é lido/
  // escrito via ref (innerHTML), não via state a cada tecla.
  const noteEditorRef = React.useRef<HTMLDivElement>(null);
  const [noteSaved, setNoteSaved] = useState(false);

  /*
   * Carrega a anotação salva no editor.
   *
   * O DEFEITO: este efeito dependia só de `activeLesson?.id`. A aba "Anotações
   * Privadas" é renderizada condicionalmente (`activeTab === 'anotacao'`), então
   * no instante em que a aula muda o editor NÃO está montado — `noteEditorRef`
   * é null e a atribuição era descartada em silêncio. Depois, ao abrir a aba, o
   * editor montava vazio e nada mais o preenchia.
   *
   * O resultado, do lado de quem usa: a anotação era gravada corretamente (a
   * chave sempre foi `savedNotes[activeLesson.id]`, por aula), mas nunca voltava
   * — e parecia que "Salvar" tinha apagado tudo. Também explica por que "Baixar"
   * só trazia o que estava digitado na hora: ele lê o editor, e o editor estava
   * vazio.
   *
   * `activeTab` na dependência é a correção: quando a aba abre, o editor existe.
   */
  React.useEffect(() => {
    if (activeTab !== 'anotacao') return;
    if (!activeLesson || !noteEditorRef.current) return;

    // Sanitiza na ENTRADA: a anotação pode ter sido gravada antes desta regra,
    // ou colada de outra página trazendo a marcação da origem.
    noteEditorRef.current.innerHTML = sanitizeNoteHtml(savedNotes[activeLesson.id] || '');
    setNoteSaved(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeLesson?.id, activeTab]);

  const applyNoteFormat = (command: string, value?: string) => {
    noteEditorRef.current?.focus();
    document.execCommand(command, false, value);
  };

  // Conteúdo da aula parseado uma vez por aula (títulos, listas, blocos de
  // código) — alimenta tanto a renderização quanto o índice de seções.
  const parsedLesson = React.useMemo(
    () => parseLessonContent(activeLesson?.content ?? ''),
    [activeLesson?.id, activeLesson?.content]
  );

  // Uma aula pode não ter vídeo. Usamos o MESMO parser do player (ADR 08) para
  // decidir, senão uma URL inválida abriria o player só para mostrar erro.
  const lessonHasVideo = parseVideoSource(activeLesson?.videoUrl) !== null;

  const handleSaveNoteText = () => {
    if (activeLesson && noteEditorRef.current) {
      const html = sanitizeNoteHtml(noteEditorRef.current.innerHTML);
      const updated = { ...savedNotes, [activeLesson.id]: html };
      setSavedNotes(updated);
      localStorage.setItem('ava_student_lesson_notes', JSON.stringify(updated));
      setNoteSaved(true);
      setTimeout(() => setNoteSaved(false), 3000);
    }
  };

  /**
   * Gera o PDF da anotação pelo NAVEGADOR.
   *
   * Antes baixava um `.html`, que quase ninguém quer receber. E o PDF não pode
   * ser gerado no servidor: esta tela promete, com essas palavras, que a
   * anotação é "gravada e persistida localmente no seu navegador". Mandá-la para
   * o servidor só para virar PDF quebraria a promessa por conveniência de
   * implementação — e anotação de aluno carrega o que ele quiser escrever.
   *
   * Então: um iframe oculto recebe a anotação e o `print()` do navegador a
   * transforma em PDF. Iframe, e não `window.open`, porque janela nova é
   * bloqueada por bloqueador de pop-up e o botão simplesmente não faria nada.
   *
   * O que isto exige de quem usa: escolher "Salvar como PDF" no diálogo de
   * impressão. É o preço de não ter dependência nova nem mandar o texto embora.
   */
  const baixarAnotacaoEmPdf = () => {
    if (!activeLesson || !noteEditorRef.current) return;

    const html = sanitizeNoteHtml(noteEditorRef.current.innerHTML);
    if (!html.trim()) {
      showAlert('Escreva algo na anotação antes de gerar o PDF.');

      return;
    }

    // Título e curso vêm do SERVIDOR (quem gerencia o curso), então precisam de
    // escape: sem ele, `</title><script>` num título executaria no documento
    // gerado na máquina do aluno.
    const tituloAula = escapeHtml(activeLesson.title);
    const tituloCurso = escapeHtml(selectedCourse?.title ?? '');
    const aluno = escapeHtml(activeUser.name);
    const quando = new Date().toLocaleString('pt-BR');

    const documento = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">
<title>Anotacoes - ${tituloAula}</title>
<style>
  @page { size: A4; margin: 18mm 16mm; }
  body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; line-height: 1.6; font-size: 12pt; margin: 0; }
  header { border-bottom: 2px solid #0d9488; padding-bottom: 10px; margin-bottom: 18px; }
  h1 { font-size: 15pt; margin: 0 0 4px; }
  .meta { font-size: 9pt; color: #64748b; }
  ul { list-style: disc; padding-left: 22px; }
  ol { list-style: decimal; padding-left: 22px; }
  img { max-width: 100%; }
</style></head><body>
<header>
  <h1>Anotações — ${tituloAula}</h1>
  <div class="meta">${tituloCurso}</div>
  <div class="meta">${aluno} • gerado em ${quando}</div>
</header>
${html}
</body></html>`;

    const iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.position = 'fixed';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.visibility = 'hidden';
    document.body.appendChild(iframe);

    const janela = iframe.contentWindow;
    if (!janela) {
      document.body.removeChild(iframe);
      showAlert('Não foi possível preparar o PDF neste navegador.');

      return;
    }

    janela.document.open();
    janela.document.write(documento);
    janela.document.close();

    // Espera a janela do iframe carregar antes de imprimir: chamar `print()` num
    // documento ainda em branco produz uma folha vazia.
    const imprimir = () => {
      janela.focus();
      janela.print();
      // Remove depois do diálogo. `print()` é bloqueante na maioria dos
      // navegadores, mas o timeout cobre os que retornam antes.
      setTimeout(() => {
        if (iframe.parentNode) document.body.removeChild(iframe);
      }, 1000);
    };

    if (janela.document.readyState === 'complete') {
      imprimir();
    } else {
      janela.addEventListener('load', imprimir, { once: true });
    }
  };

  const [lessonSupportMessage, setLessonSupportMessage] = useState('');
  const [lessonSupportMessageSent, setLessonSupportMessageSent] = useState(false);

  /*
   * Só anuncia envio depois de o servidor aceitar.
   *
   * Antes chamava `sendDirectMessage` e acendia "Mensagem enviada!" na linha
   * seguinte, sem olhar o resultado. Com `features.mensagensDiretas` desligada a
   * rota responde 404 FEATURE_DISABLED — então o aluno relatava um problema, lia
   * a confirmação, e a mensagem não existia em lugar nenhum.
   */
  const handleSendLessonSupportMessage = async () => {
    const text = lessonSupportMessage.trim();
    if (!text || !activeLesson) return;

    const r = await sendDirectMessage(activeUser.id, comAssuntoDaAula(activeLesson.title, text));
    if (!r.ok) {
      showAlert(r.error ?? 'Não foi possível enviar a mensagem.');

      return;
    }

    setLessonSupportMessage('');
    setLessonSupportMessageSent(true);
    setTimeout(() => setLessonSupportMessageSent(false), 4000);
  };

  // Calculations for summary metrics based on the student's actual enrollment record(s)
  const activeEnrollments = activeEnrolledCourseIds.length;

  // Average Global Attendance across every active enrolled course
  const avgGlobalAttendance = activeEnrolledCourseIds.length > 0
    ? Math.round(
        activeEnrolledCourseIds.reduce((sum, id) => sum + calculateAttendancePercent(id), 0) / activeEnrolledCourseIds.length
      )
    : 0;
  
  // Filter courses based on search
  const filteredCourses = courses.filter((c) =>
    c.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
    c.category.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const currentCourseProgress = selectedCourse
    ? progress.find((p) => p.courseId === selectedCourse.id && p.userId === activeUser.id)
    : null;

  /*
   * Transmissões que acontecem hoje. `new Date()` fica aqui, num único ponto, e o
   * recorte por dia mora em liveSchedule — testável sem depender do relógio.
   */
  /*
   * Exercícios de fixação do curso aberto. Vem daqui em vez de filtrar inline
   * porque o bloco INTEIRO só existe quando há algum: disciplina sem exercício
   * exibia uma caixa com título e a frase "Nenhum exercício prático lançado
   * neste curso" — ocupando a coluna para dizer que não há nada a fazer.
   *
   * `practicalExercises` já chega sem os inativados: a API os exclui (ADR 12).
   */
  const exerciciosDoCursoAberto = React.useMemo(
    () => (selectedCourse ? exerciciosDoCurso(practicalExercises, selectedCourse.id) : []),
    [practicalExercises, selectedCourse]
  );

  /*
   * Avaliações do curso aberto. Mesmo motivo do bloco de exercícios: sem
   * avaliação nenhuma, a caixa dizia "Nenhum teste elaborado para este curso no
   * momento" e ocupava a coluna do aluno para anunciar que não há o que fazer.
   *
   * `quizzes` chega sem os inativados (a API os exclui, ADR 12), então avaliação
   * retirada do ar também faz o bloco desaparecer.
   */
  const avaliacoesDoCursoAberto = React.useMemo(
    () => (selectedCourse ? quizzes.filter((q) => q.courseId === selectedCourse.id) : []),
    [quizzes, selectedCourse]
  );

  const agoraTransmissao = new Date();
  const transmissoesDeHoje = React.useMemo(
    () => transmissoesDoDia(selectedCourse?.liveSessions, new Date()),
    [selectedCourse]
  );

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 md:px-6">
      {/* Student Welcome Header — só na página de boas-vindas (painel geral, sem curso selecionado) */}
      {activeDashboardTab === 'general' && !selectedCourse && (
      <div className="mb-8 rounded-2xl bg-gradient-to-br from-purple-50/50 via-white to-teal-50/30 border border-slate-150 p-6 md:p-7 shadow-xs relative overflow-hidden text-left">
        {/* Ambient subtle light glows */}
        <div className="absolute top-0 right-0 -mt-10 -mr-10 w-48 h-48 bg-[#540D6E]/5 rounded-full blur-3xl pointer-events-none" />
        <div className="absolute bottom-0 left-1/4 -mb-10 w-44 h-44 bg-teal-400/5 rounded-full blur-3xl pointer-events-none" />
        
        <div className="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          {/* Left section: Avatar, Greeting, Badge & Exit Button */}
          <div className="flex flex-col sm:flex-row sm:items-center gap-5">
            {/* Avatar container with live status indicator badge */}
            <div className="relative shrink-0 w-14 h-14">
              <div className="rounded-2xl bg-teal-50 p-3 w-14 h-14 border border-teal-100 shadow-3xs flex items-center justify-center">
                <User className="h-7 w-7 text-teal-600" />
              </div>
              <span className="absolute -bottom-1 -right-1 flex h-3.5 w-3.5">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-3.5 w-3.5 bg-teal-500"></span>
              </span>
            </div>

            <div className="space-y-1.5">
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-[9px] uppercase font-black tracking-widest text-teal-800 bg-teal-100/40 border border-teal-200/50 px-2.5 py-0.5 rounded-md inline-flex items-center gap-1.5 shadow-3xs">
                  Painel de Estudos AVASEC
                </span>
                
                {onBackToLanding && (
                  <button
                    onClick={() => {
                      const label = getBackLabel();
                      speakText(`${label}. Voltando um nível no fluxo.`);
                      handleBack();
                    }}
                    className="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 transition-all cursor-pointer text-[9px] font-bold uppercase tracking-wider border border-slate-200/60"
                    title={getBackLabel()}
                  >
                    <ArrowLeft className="h-3 w-3 text-slate-500" />
                    <span>{getBackLabel()}</span>
                  </button>
                )}
              </div>
              
              <h2 className="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 tracking-tight leading-tight pt-0.5">
                Olá, {activeUser.name}
              </h2>
              <p className="text-xs text-slate-500 font-medium">Pronto para acelerar os seus conhecimentos profissionais hoje?</p>
            </div>
          </div>

          {/* Right section: Indicators as mini cards */}
          <div className="flex flex-wrap items-center gap-3 w-full lg:w-auto">
            <div className="bg-white/60 border border-slate-150 rounded-xl px-4 py-2.5 text-left shadow-3xs hover:bg-white/90 transition-all flex-1 sm:flex-initial min-w-[115px]">
              <span className="block text-xl font-black text-[#540D6E] font-mono tracking-tight">{activeEnrollments}</span>
              <span className="text-[10px] text-slate-500 font-semibold block mt-0.5 whitespace-nowrap">Cursos ativos</span>
            </div>
            <div className="bg-white/60 border border-slate-150 rounded-xl px-4 py-2.5 text-left shadow-3xs hover:bg-white/90 transition-all flex-1 sm:flex-initial min-w-[115px]">
              <span className="block text-xl font-black text-teal-600 font-mono tracking-tight">{avgGlobalAttendance}%</span>
              <span className="text-[10px] text-slate-500 font-semibold block mt-0.5 whitespace-nowrap">Presença média</span>
            </div>
          </div>
        </div>
      </div>
      )}

      {/*
        A barra de abas NAO depende de mensagens diretas.
        Ela estava inteira dentro de
        `{features.mensagensDiretas && systemSettings.allowDirectMessages && (`,
        e `allowDirectMessages` e chave EDITAVEL pelo admin nas Configuracoes do
        Sistema. Ou seja: um administrador desligava mensagens e o aluno perdia
        de uma vez Documentos, Biblioteca Digital, Eventos, Central de Ajuda e
        Meu Perfil — sem erro e sem aviso. A condicao pertence ao botao de
        Mensagens, e e la que ela esta agora.
      */}
      <div className="flex border-b border-slate-200 mb-8 gap-3 p-1.5 bg-slate-100 rounded-2xl w-full sm:w-fit flex-wrap overflow-x-auto no-scrollbar">
          <button
            onClick={() => setActiveDashboardTab('general')}
            className={`px-6 py-3 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all flex items-center gap-2.5 cursor-pointer whitespace-nowrap ${
              activeDashboardTab === 'general'
                ? 'bg-[#540D6E] text-white shadow-md transform scale-[1.02]'
                : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50/50'
            }`}
          >
            <BookOpen className="h-4 w-4" />
            <span>Meu Painel de Estudos</span>
          </button>

          {abaVisivelParaAluno('documents', features, systemSettings) && (
            <button
              onClick={() => setActiveDashboardTab('documents')}
              className={`px-6 py-3 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all flex items-center gap-2.5 cursor-pointer whitespace-nowrap ${
                activeDashboardTab === 'documents'
                  ? 'bg-[#540D6E] text-white shadow-md transform scale-[1.02]'
                  : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50/50'
              }`}
            >
              <FileCheck className="h-4 w-4" />
              <span>Documentos</span>
            </button>
          )}

          {abaVisivelParaAluno('messages', features, systemSettings) && (
            <button
              onClick={() => setActiveDashboardTab('messages')}
              className={`px-6 py-3 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all flex items-center gap-2.5 cursor-pointer whitespace-nowrap ${
                activeDashboardTab === 'messages'
                  ? 'bg-[#540D6E] text-white shadow-md transform scale-[1.02]'
                  : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50/50'
              }`}
            >
              <MessageSquare className="h-4 w-4" />
              <span>Mensagens & Suporte</span>
            </button>
          )}

          {abaVisivelParaAluno('library', features, systemSettings) && (
            <button
              onClick={() => setActiveDashboardTab('library')}
              className={`px-6 py-3 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all flex items-center gap-2.5 cursor-pointer whitespace-nowrap ${
                activeDashboardTab === 'library'
                  ? 'bg-[#540D6E] text-white shadow-md transform scale-[1.02]'
                  : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50/50'
              }`}
            >
              <Library className="h-4 w-4" />
              <span>Biblioteca Digital</span>
            </button>
          )}

          {abaVisivelParaAluno('events', features, systemSettings) && (
          <button
            onClick={() => setActiveDashboardTab('events')}
            className={`px-6 py-3 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all flex items-center gap-2.5 cursor-pointer whitespace-nowrap ${
              activeDashboardTab === 'events'
                ? 'bg-[#540D6E] text-white shadow-md transform scale-[1.02]'
                : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50/50'
            }`}
          >
            <Globe className="h-4 w-4" />
            <span>Eventos & Webinars</span>
          </button>
          )}

          <button
            onClick={() => setIsFaqDrawerOpen(true)}
            className={`px-6 py-3 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all flex items-center gap-2.5 cursor-pointer whitespace-nowrap ${
              isFaqDrawerOpen
                ? 'bg-[#540D6E] text-white shadow-md transform scale-[1.02]'
                : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50/50'
            }`}
          >
            <HelpCircle className="h-4 w-4" />
            <span>Central de Ajuda / FAQ</span>
          </button>

          {abaVisivelParaAluno('settings', features, systemSettings) && (
            <button
              onClick={() => setActiveDashboardTab('settings')}
              className={`px-6 py-3 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all flex items-center gap-2.5 cursor-pointer whitespace-nowrap ${
                activeDashboardTab === 'settings'
                  ? 'bg-[#540D6E] text-white shadow-md transform scale-[1.02]'
                  : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50/50'
              }`}
            >
              <User className="h-4 w-4" />
              <span>Meu Perfil</span>
            </button>
          )}
        </div>

      {/*
        Aba desligada: a MESMA fonte que esconde o botao decide a mensagem, em vez
        de uma segunda lista de flags que pode divergir dela (era o caso: a lista
        aqui nao citava `eventosWebinars`, e nem `mensagensDiretas`).

        E o texto deixou de dizer "temporariamente". Um recurso pode ficar meses
        desligado por decisao de produto — o comentario em `features.ts` diz que
        webinar "nao entra nesta fase" — e prometer volta breve para quem nunca
        vai ver a tela e informacao falsa. Quem chega aqui chegou por link antigo
        ou por endereco digitado: o que serve e saber o que aconteceu e para onde
        ir.
      */}
      {!abaVisivelParaAluno(activeDashboardTab, features, systemSettings) ? (
        <div className="bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl p-8 text-center max-w-xl mx-auto my-12 shadow-3xs space-y-3">
          <Lock className="h-10 w-10 text-amber-600 mx-auto" />
          <h3 className="font-extrabold text-base">Esta seção não está disponível nesta versão da plataforma.</h3>
          <p className="text-xs text-slate-500">
            Ela não aparece no menu porque está desativada. Se você chegou aqui por um link
            salvo, use o menu acima para voltar ao seu Painel de Estudos.
          </p>
        </div>
      ) : activeDashboardTab === 'general' ? (
        /* Main split: left courses or detail / right certificates tracking */
        <div className="grid grid-cols-1 gap-8 lg:grid-cols-1">
        
        {/* Course Directory Columns */}
        <div className="space-y-6">
          
          {selectedCourse ? (
            isCourseExpired(selectedCourse.contractExpirationDate) ? (
              /* Course Expiration Lock Screen for Student */
              <div className="rounded-2xl border border-amber-250 bg-amber-50/15 p-6 shadow-sm text-center animate-in fade-in duration-300">
                <div className="max-w-xl mx-auto py-10 space-y-5">
                  <div className="inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-700 animate-bounce">
                    <Archive className="h-7 w-7" />
                  </div>
                  <h3 className="text-xl font-black text-slate-800">Vigência de Exibição Encerrada</h3>
                  <p className="text-sm text-slate-600 leading-relaxed text-center">
                    O contrato de licenciamento e exibição deste curso encerrou-se em <strong className="font-bold underline">{selectedCourse.contractExpirationDate}</strong>. 
                    Por razões de conformidade legal e direitos autorais da coordenação, este material foi <strong>arquivado preventivamente</strong> e o acesso às aulas foi suspenso.
                  </p>
                  
                  <div className="bg-white border border-amber-200 rounded-xl p-4 text-xs text-amber-900 text-left space-y-1">
                    <strong className="block text-amber-950 font-bold uppercase text-[10px] tracking-wider mb-1">Proteção Jurídica Ativa:</strong>
                    <p className="flex items-start gap-1.5"><Check className="h-3.5 w-3.5 mt-0.5 shrink-0 text-amber-700" />Reprodução de vídeos suspensa.</p>
                    <p className="flex items-start gap-1.5"><Check className="h-3.5 w-3.5 mt-0.5 shrink-0 text-amber-700" />Download de anexos bloqueado de acordo com a vigência de exibição.</p>
                    <p className="flex items-start gap-1.5"><Check className="h-3.5 w-3.5 mt-0.5 shrink-0 text-amber-700" />Cadastro de novas presenças desativado.</p>
                  </div>

                  <button
                    onClick={voltarParaMeusCursos}
                    className="mt-4 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs uppercase tracking-wider py-3 px-6 rounded-xl transition-all cursor-pointer"
                  >
                    Voltar para Meus Cursos
                  </button>
                </div>
              </div>
            ) : (
              /* Selected Course Detail View Workspace */
              <div className="rounded-2xl border border-slate-200 bg-white p-5 md:p-6 shadow-sm text-left animate-in fade-in duration-300">
              
              {/* Back breadcrumb and global course indicators */}
              <div className="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-5">
                <button
                  onClick={voltarParaMeusCursos}
                  className="flex items-center gap-1.5 text-xs font-bold text-teal-600 hover:text-teal-500 transition-colors cursor-pointer"
                >
                  <span>← Sair do Curso</span>
                </button>

                <div className="flex items-center gap-2">
                  <span className="text-[10px] uppercase font-bold text-slate-400">Trilha de Estudos:</span>
                  <span className="rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-xs font-bold text-slate-600">
                    {selectedCourse.category}
                  </span>
                </div>
              </div>

              {/* Course Title Information & Attendance Tracker */}
              <div className="mb-6 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                <div className="text-left">
                  <h3 className="text-lg md:text-xl font-black text-slate-900 leading-tight">
                    {selectedCourse.title}
                  </h3>
                  <div className="flex flex-wrap items-center gap-2 mt-1">
                    <span className="text-xs text-slate-550 font-medium">Instrutor responsável: Prof. {selectedCourse.instructorName}</span>
                    <span className="inline-flex items-center gap-1 bg-white border border-slate-200 px-2 py-0.5 rounded-full text-[9px] font-bold">
                      <span className={`h-1.5 w-1.5 rounded-full ${
                        (localStorage.getItem(`ava_presence_status_${selectedCourse.instructorId ?? ''}`) || 'online') === 'online'
                          ? 'bg-emerald-500 animate-pulse'
                          : 'bg-slate-400'
                      }`} />
                      <span className={(localStorage.getItem(`ava_presence_status_${selectedCourse.instructorId ?? ''}`) || 'online') === 'online' ? 'text-emerald-600' : 'text-slate-500'}>
                        {(localStorage.getItem(`ava_presence_status_${selectedCourse.instructorId ?? ''}`) || 'online') === 'online' ? 'Online' : 'Offline'}
                      </span>
                    </span>
                  </div>
                </div>

                {/*
                  Lia-se como divisão. "Frequência Total 100% / 70%" faz a pessoa
                  perguntar "100% de 70% dá quanto?", quando os dois números são
                  coisas diferentes: o primeiro é a frequência dela, o segundo é o
                  mínimo do curso. Agora cada um tem seu rótulo.

                  E o 70 estava FIXO em três lugares aqui, inclusive na condição do
                  banner de certificado logo abaixo — um curso com `minAttendance`
                  diferente anunciaria qualificação no limite errado. O mínimo passa
                  a vir de `courseMinAttendance`, que é a fonte única.
                */}
                {(() => {
                  const frequencia = calculateAttendancePercent(selectedCourse.id);
                  const minimo = courseMinAttendance(selectedCourse);
                  const qualificado = frequencia >= minimo;

                  return (
                    <div className="flex items-center gap-3 bg-white px-3 py-2 rounded-lg border border-slate-250">
                      <div className="text-right">
                        <span className="block text-[9px] uppercase font-semibold text-slate-400 leading-none">Sua frequência</span>
                        <strong className={`text-sm font-black font-mono mt-0.5 block ${qualificado ? 'text-emerald-700' : 'text-teal-700'}`}>
                          {frequencia}%
                        </strong>
                        {/*
                          Frequencia deixou de ser o unico critério (09/09/2026).
                          Dizer so "minimo de 70% para o certificado" faria o
                          medidor prometer o certificado a quem bate a presenca e
                          nao passou na prova — o mesmo defeito do banner abaixo.
                        */}
                        <span className="block text-[9px] text-slate-400 leading-tight mt-0.5">
                          mínimo de {minimo}%
                          {avaliacoesQueFaltam.length > 0
                            ? ' e aprovação nas avaliações'
                            : ' para o certificado'}
                        </span>
                      </div>
                      <div
                        className={`h-9 w-9 shrink-0 rounded-full border-2 flex items-center justify-center text-[8px] font-bold leading-none text-center ${
                          qualificado
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : 'border-slate-200 bg-slate-50 text-slate-500'
                        }`}
                        title={faltaParaCertificado ?? `Critérios cumpridos: ${frequencia}% de frequência, mínimo de ${minimo}%.`}
                      >
                        {qualificado ? <Check className="h-4 w-4" /> : <span>faltam<br />{minimo - frequencia}%</span>}
                      </div>
                    </div>
                  );
                })()}
              </div>

              {/*
                Banner de conclusão. A frequência abre o banner; o que ele DIZ
                depende de o curso ter avaliação pendente.

                Antes ele afirmava "seu certificado foi emitido" com base só na
                presença. Desde 09/09/2026 o servidor exige as avaliações
                aprovadas, então essa frase passaria a ser falsa exatamente para
                quem clicou "Próxima aula" até o fim — e a pessoa iria procurar
                no perfil um documento que não existe.
              */}
              {calculateAttendancePercent(selectedCourse.id) >= courseMinAttendance(selectedCourse) && (() => {
                // Curso já concluído não pode ser concluído de novo: a matrícula
                // deixou de ser ativa, então o botão só daria erro.
                const jaConcluido = enrollmentRecord.completedCourseIds?.includes(selectedCourse.id) ?? false;
                const temPendencia = avaliacoesQueFaltam.length > 0 && !jaConcluido;

                if (temPendencia) {
                  /*
                   * "Concluir curso" fica FORA daqui de propósito. Concluir
                   * encerra a matrícula e tira o curso da lista de disponíveis —
                   * quem concluísse com avaliação pendente ficaria sem o
                   * certificado E sem poder se matricular de novo para fazer a
                   * prova. Nenhum aviso conserta isso depois de clicado.
                   */
                  return (
                    <div className="mb-6 rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900 flex items-start gap-2.5">
                      <Award className="h-5 w-5 text-amber-600 shrink-0" />
                      <div>
                        <strong className="block font-bold mb-0.5">
                          Frequência cumprida — falta a avaliação
                        </strong>
                        <span>
                          {faltaParaCertificado ?? ''} O certificado é emitido assim que a aprovação
                          for registrada.
                        </span>
                      </div>
                    </div>
                  );
                }

                return (
                <div className="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-xs text-emerald-800 flex items-center justify-between gap-4">
                  <div className="flex items-center gap-2.5">
                    <CheckCircle className="h-5 w-5 text-emerald-600 shrink-0" />
                    <div>
                      <strong className="block font-bold mb-0.5">
                        {jaConcluido ? 'Curso concluído' : 'Parabéns! Frequência Qualificada para Certificação'}
                      </strong>
                      {jaConcluido
                        ? 'Você já concluiu este curso. Seu certificado acadêmico digital está disponível no seu perfil.'
                        : `Você atingiu ${calculateAttendancePercent(selectedCourse.id)}% de presença e cumpriu as avaliações do curso. Seu certificado acadêmico digital está no seu perfil.`}
                    </div>
                  </div>
                  <div className="flex flex-col sm:flex-row items-center gap-2">
                    {!jaConcluido && (
                    <button
                      onClick={async () => {
                        // O servidor confere o critério de frequência antes de concluir.
                        const result = await completeStudentCourse(activeUser.id, selectedCourse.id);
                        if (!result.ok) {
                          showAlert(result.error || 'Critério de conclusão ainda não atingido.');
                          return;
                        }
                        speakText("Parabéns pela conclusão da disciplina! Agora você pode escolher um novo curso para iniciar seus estudos.");
                        voltarParaMeusCursos();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                      }}
                      title="Marca o curso como concluído e volta para o catálogo"
                      className="shrink-0 rounded-lg bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs px-3.5 py-2 transition-colors flex items-center gap-1.5 shadow-xs whitespace-nowrap cursor-pointer"
                    >
                      <Check className="h-3.5 w-3.5" />
                      <span className="uppercase tracking-wider">Concluir curso</span>
                    </button>
                    )}

                    {certificates.find((cert) => cert.courseId === selectedCourse.id && cert.userId === activeUser.id) && (
                      <button
                        onClick={() => {
                          speakText("Seu certificado está disponível no seu Perfil.");
                          onNavigateToProfile?.();
                        }}
                        title="Abre o certificado no seu perfil"
                        className="shrink-0 rounded-lg border border-emerald-300 bg-white hover:bg-emerald-100 text-emerald-800 font-bold text-xs px-3.5 py-2 transition-colors flex items-center gap-1.5 shadow-xs whitespace-nowrap cursor-pointer"
                      >
                        <Award className="h-3.5 w-3.5" />
                        <span className="uppercase tracking-wider">Ver certificado</span>
                      </button>
                    )}
                  </div>
                </div>
                );
              })()}

              {/* Página cheia de testes e avaliações do curso (esconde o grid). */}
              {showAvaliacoes && !activeLesson ? (
                <AvaliacoesPage
                  courseTitle={selectedCourse.title}
                  courseId={selectedCourse.id}
                  quizzes={quizzes}
                  submissions={quizSubmissions}
                  userId={activeUser.id}
                  quizInicial={avaliacaoInicial}
                  onQuizChange={(quizId) => irPara({ tela: 'avaliacoes', quizId })}
                  onBack={() => setShowAvaliacoes(false)}
                  onSubmit={(quizId, scorePercent, passed, answers) =>
                    submitQuiz(selectedCourse.id, quizId, scorePercent, passed, answers)}
                  notify={speakText}
                />
              ) : /* Página cheia de exercícios práticos do curso (esconde o grid). */
              showExercicios && !activeLesson ? (
                <ExerciciosPraticosPage
                  courseTitle={selectedCourse.title}
                  courseId={selectedCourse.id}
                  exercises={practicalExercises}
                  submissions={exerciseSubmissions}
                  userId={activeUser.id}
                  onBack={() => setShowExercicios(false)}
                  onSubmit={submitExercise}
                  onUpload={enviarAnexoDeEntrega}
                  onDownload={downloadSubmissionFile}
                  notify={showAlert}
                  permiteAnexo={features.uploadArquivos}
                />
              ) : (
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                {/* 1. Play Station & Notebook Center */}
                <div className={`${(activeLesson) ? "lg:col-span-12" : "lg:col-span-8"} space-y-5 animate-in fade-in duration-300`}>
                  
                  {activeLesson ? (
                    /* Lesson Player Station active */
                    <div className="space-y-5 flex flex-col items-center">
                      
                      {/* Cabeçalho fixo: voltar, título e índice ficam alcançáveis
                          em qualquer ponto da rolagem. */}
                      <div className="sticky top-0 z-20 -mx-4 px-4 pt-2 pb-2.5 bg-white/95 backdrop-blur border-b border-slate-150 w-[calc(100%+2rem)]">
                        <div className="max-w-3xl mx-auto flex items-center justify-between gap-3">
                          <button
                            onClick={() => setActiveLesson(null)}
                            className="text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors flex items-center gap-1 cursor-pointer shrink-0"
                          >
                            <ArrowRight className="h-3.5 w-3.5 rotate-180" />
                            <span className="hidden sm:inline">Voltar ao Curso</span>
                            <span className="sm:hidden">Voltar</span>
                          </button>

                          <div className="min-w-0 flex-1 text-center hidden md:block">
                            <p className="text-[11px] font-bold text-slate-700 truncate">{activeLesson.title}</p>
                            <span className="text-[9px] font-mono text-slate-400">
                              Aula {activeLesson.order} de {selectedCourse.lessons.length}
                            </span>
                          </div>

                          <div className="shrink-0">
                            <LessonIndex sections={parsedLesson.sections} />
                          </div>
                        </div>
                      </div>

                      {/* Vídeo só aparece quando a aula TEM vídeo — aula de
                          leitura não deve abrir com meia tela de caixa preta. */}
                      {lessonHasVideo && (
                        <div className="relative rounded-2xl bg-slate-950 border border-slate-850 overflow-hidden shadow-md group w-full max-w-3xl mx-auto">
                          {/* 16:9 Screen ratio representation with max height constraint */}
                          <div className="aspect-video w-full max-h-[50vh]">
                            {/* Player único da plataforma (ADR 08) — usa os controles
                                nativos: YouTube no iframe, navegador nos vídeos mp4. */}
                            <VideoPlayer
                              key={activeLesson.id}
                              videoUrl={activeLesson.videoUrl}
                              title={activeLesson.title}
                              controls
                            />
                          </div>
                        </div>
                      )}

                      {/* Aula sem vídeo: abre com o título e a natureza do
                          conteúdo, em vez de um player vazio. */}
                      {!lessonHasVideo && (
                        <div className="w-full max-w-3xl mx-auto text-left space-y-2 pt-1">
                          <span className="inline-flex items-center gap-1.5 rounded-full bg-teal-50 border border-teal-150 text-teal-800 text-[9px] font-black uppercase tracking-widest px-2.5 py-1">
                            <FileText className="h-3 w-3" />
                            Conteúdo de leitura
                          </span>
                          <h2 className="text-lg md:text-2xl font-black text-slate-900 font-serif leading-tight">
                            {activeLesson.title}
                          </h2>
                          <p className="text-[11px] text-slate-400 font-mono">
                            Aula {activeLesson.order} de {selectedCourse.lessons.length}
                            {activeLesson.duration ? ` • ${activeLesson.duration} de leitura` : ''}
                          </p>
                        </div>
                      )}

                      {/* Controles da aula. A navegação entre aulas vive só no rodapé
                          da aula (um par de botões, não dois fazendo a mesma coisa). */}
                      <div className="bg-slate-50 p-4 rounded-xl border border-slate-200/65 flex items-center justify-between gap-4 w-full max-w-3xl mx-auto">
                        <span className="hidden sm:inline text-[10px] font-mono text-slate-400 select-none">
                          Aula {activeLesson.order} de {selectedCourse.lessons.length}
                        </span>

                        {/*
                          ESTADO primeiro, ação depois.
                          Antes havia só um botão, e ele mostrava sempre a AÇÃO
                          disponível — nunca a situação da aula. O efeito: ao clicar
                          em "Próxima aula" e chegar numa aula ainda não concluída,
                          aparecia "Concluir esta Aula de Fixação", e isso se lê como
                          "a aula acabou de virar pendente". Nada era alterado —
                          `toggleLessonCompletion` só é chamado por clique explícito —
                          mas a tela não dava como saber disso.
                        */}
                        {(() => {
                          const concluida = currentCourseProgress?.completedLessons.includes(activeLesson.id) ?? false;

                          if (concluida) {
                            return (
                              <div className="flex items-center gap-2.5">
                                <span className="flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800">
                                  <CheckCircle className="h-4 w-4" />
                                  Aula concluída
                                </span>
                                <button
                                  onClick={() => toggleLessonCompletion(selectedCourse.id, activeLesson.id)}
                                  title="Voltar esta aula para pendente"
                                  className="cursor-pointer text-[10px] font-bold uppercase tracking-wider text-slate-450 underline decoration-slate-300 hover:text-slate-700"
                                >
                                  Desfazer
                                </button>
                              </div>
                            );
                          }

                          return (
                            <button
                              onClick={() => toggleLessonCompletion(selectedCourse.id, activeLesson.id)}
                              className="flex cursor-pointer items-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition-all hover:bg-teal-500"
                            >
                              <CheckCircle className="h-4 w-4" />
                              <span>Marcar esta aula como concluída</span>
                            </button>
                          );
                        })()}
                      </div>

                      {/* Modular Details Hub: Tabs system under Lesson */}
                      <div className="border border-slate-200 rounded-2xl overflow-hidden bg-white w-full max-w-3xl mx-auto">
                        
                        {/* Tab trigger anchors with design visual borders */}
                        <div className="flex border-b border-slate-200 bg-slate-50/50">
                          <button
                            onClick={() => setActiveTab('teoria')}
                            className={`flex-1 min-h-14 py-3 px-2 sm:px-4 text-[11px] sm:text-xs font-bold text-slate-700 border-b-2 transition-colors flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap ${
                              activeTab === 'teoria' ? 'border-teal-600 text-teal-600 bg-white' : 'border-transparent hover:text-teal-500'
                            }`}
                          >
                            <FileText className="h-4 w-4 shrink-0" />
                            <span>Material Didático</span>
                          </button>

                          <button
                            onClick={() => setActiveTab('anotacao')}
                            className={`flex-1 min-h-14 py-3 px-2 sm:px-4 text-[11px] sm:text-xs font-bold text-slate-700 border-b-2 transition-colors flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap ${
                              activeTab === 'anotacao' ? 'border-teal-600 text-teal-600 bg-white' : 'border-transparent hover:text-teal-500'
                            }`}
                          >
                            <Notebook className="h-4 w-4 shrink-0" />
                            <span>Anotações Privadas</span>
                            {savedNotes[activeLesson.id] && (
                              <span className="w-1.5 h-1.5 bg-teal-600 rounded-full inline-block shrink-0" />
                            )}
                          </button>

                          {/*
                            A aba depende do canal de mensagens. `mensagensDiretas`
                            desligada faz `POST /api/dms` responder 404
                            FEATURE_DISABLED, e a aba oferecia um envio que nunca
                            chegava — com confirmação na tela. Mesma escada que o
                            Fórum, a Biblioteca e o Perfil já usam: recurso
                            desligado desaparece, em vez de prometer.
                          */}
                          {features.mensagensDiretas && (
                            <button
                              onClick={() => setActiveTab('suporte')}
                              className={`flex-1 min-h-14 py-3 px-2 sm:px-4 text-[11px] sm:text-xs font-bold text-slate-700 border-b-2 transition-colors flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap ${
                                activeTab === 'suporte' ? 'border-teal-600 text-teal-600 bg-white' : 'border-transparent hover:text-teal-500'
                              }`}
                            >
                              <HelpCircle className="h-4 w-4 shrink-0" />
                              <span>Suporte Pedagógico</span>
                            </button>
                          )}

                          {features.forum && (
                            <button
                              onClick={() => setActiveTab('forum')}
                              className={`flex-1 min-h-14 py-3 px-2 sm:px-4 text-[11px] sm:text-xs font-bold text-slate-700 border-b-2 transition-colors flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap ${
                                activeTab === 'forum' ? 'border-teal-600 text-teal-600 bg-white' : 'border-transparent hover:text-teal-500'
                              }`}
                            >
                              <MessageSquare className="h-4 w-4 text-teal-650 shrink-0" />
                              <span className="flex items-center gap-1">
                                Fórum Interativo
                                <span className="bg-teal-100 text-teal-800 text-[8px] font-black uppercase px-2 py-0.5 rounded-full animate-pulse shrink-0">Comunidade</span>
                              </span>
                            </button>
                          )}

                        </div>

                        {/* Tab panel display content */}
                        <div className="p-5 text-left text-xs text-slate-700 leading-relaxed max-w-none">
                          {activeTab === 'teoria' && (
                            <div className="space-y-4">
                              <h4 className="font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center gap-1.5">
                                <Sparkles className="h-4 w-4 text-teal-500 animate-pulse" />
                                <span>Roteiro Consolidado de Aprendizado</span>
                              </h4>
                              <div className="mb-6">
                                <LessonContent blocks={parsedLesson.blocks} />
                              </div>

                              {/* Student-Facing attached documents list */}
                              {activeLesson.documents && activeLesson.documents.length > 0 && (
                                <div className="mt-8 border-t border-slate-150 pt-6 space-y-3.5">
                                  <h4 className="font-extrabold text-slate-950 text-xs uppercase tracking-wider flex items-center gap-2">
                                    <Archive className="h-4 w-4 text-teal-600" />
                                    Material de Apoio e Documentos Anexos ({activeLesson.documents.length})
                                  </h4>
                                  <p className="text-[10px] text-slate-400 -mt-1 leading-none">Arquivos e links disponibilizados pelo seu instrutor para aprofundamento.</p>
                                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1.5">
                                    {activeLesson.documents.map((doc, docIdx) => {
                                      let docBg = 'bg-slate-50 border-slate-200 hover:bg-slate-100 hover:border-slate-350';
                                      let labelColor = 'bg-slate-100 text-slate-700';
                                      
                                      if (doc.type === 'pdf') {
                                        docBg = 'bg-rose-50/50 border-rose-100 hover:bg-rose-50 hover:border-rose-250';
                                        labelColor = 'bg-rose-100 text-rose-800';
                                      } else if (doc.type === 'drive') {
                                        docBg = 'bg-emerald-50/50 border-emerald-100 hover:bg-emerald-50 hover:border-emerald-250';
                                        labelColor = 'bg-emerald-100 text-emerald-800';
                                      } else if (doc.type === 'url') {
                                        docBg = 'bg-amber-50/50 border-amber-100 hover:bg-amber-50 hover:border-amber-250';
                                        labelColor = 'bg-amber-100 text-amber-800';
                                      }

                                      return (
                                        <a
                                          key={`${doc.id}-${typeof docIdx !== "undefined" ? docIdx : 0}`}
                                          href={safeHref(doc.url)}
                                          target="_blank"
                                          referrerPolicy="no-referrer"
                                          rel="noopener noreferrer"
                                          className={`p-3.5 rounded-xl border transition-all text-left flex items-start justify-between gap-3 group/doc shadow-3xs ${docBg}`}
                                        >
                                          <div className="flex items-start gap-3 min-w-0">
                                            <div className="p-2 rounded-lg bg-white shrink-0 border border-slate-100 shadow-3xs">
                                              <FileText className="h-4.5 w-4.5 text-teal-600" />
                                            </div>
                                            <div className="min-w-0">
                                              <p className="font-extrabold text-slate-900 text-xs truncate group-hover/doc:text-teal-700">{doc.title}</p>
                                              <div className="flex items-center gap-1.5 mt-1">
                                                <span className={`px-1.5 py-0.2 rounded text-[8px] font-black uppercase tracking-wider ${labelColor}`}>
                                                  {doc.type}
                                                </span>
                                                {doc.size && (
                                                  <span className="text-[10px] text-slate-400 font-mono">{doc.size}</span>
                                                )}
                                              </div>
                                            </div>
                                          </div>
                                          <ExternalLink className="h-3.5 w-3.5 text-slate-400 group-hover/doc:text-teal-600 transition-colors shrink-0 self-center" />
                                        </a>
                                      );
                                    })}
                                  </div>
                                </div>
                              )}
                            </div>
                          )}

                          {activeTab === 'anotacao' && (
                            <div className="space-y-3.5">
                              <div>
                                <h4 className="font-bold text-slate-900 flex items-center gap-1.5">
                                  <Notebook className="h-4 w-4 text-teal-500" />
                                  <span>Suas Anotações Digitais Privadas</span>
                                </h4>
                                <p className="text-[10px] text-slate-400 leading-normal mt-1">
                                  Gravadas localmente no seu navegador, separadas por aula. O PDF também
                                  é gerado aqui — a anotação não é enviada para o servidor.
                                </p>
                              </div>

                              <div className="rounded-xl border border-slate-200 bg-slate-50/50 overflow-hidden focus-within:ring-1 focus-within:ring-teal-500">
                                {/* Barra de ferramentas do editor WYSIWYG */}
                                <div className="flex items-center gap-1 border-b border-slate-200 bg-white px-2 py-1.5">
                                  <button
                                    type="button"
                                    onMouseDown={(e) => { e.preventDefault(); applyNoteFormat('bold'); }}
                                    title="Negrito"
                                    className="p-1.5 rounded-md text-slate-600 hover:bg-slate-100 hover:text-teal-600 cursor-pointer"
                                  >
                                    <Bold className="h-3.5 w-3.5" />
                                  </button>
                                  <button
                                    type="button"
                                    onMouseDown={(e) => { e.preventDefault(); applyNoteFormat('italic'); }}
                                    title="Itálico"
                                    className="p-1.5 rounded-md text-slate-600 hover:bg-slate-100 hover:text-teal-600 cursor-pointer"
                                  >
                                    <Italic className="h-3.5 w-3.5" />
                                  </button>
                                  <button
                                    type="button"
                                    onMouseDown={(e) => { e.preventDefault(); applyNoteFormat('underline'); }}
                                    title="Sublinhado"
                                    className="p-1.5 rounded-md text-slate-600 hover:bg-slate-100 hover:text-teal-600 cursor-pointer"
                                  >
                                    <Underline className="h-3.5 w-3.5" />
                                  </button>
                                  <span className="w-px h-4 bg-slate-200 mx-1" />
                                  <button
                                    type="button"
                                    onMouseDown={(e) => { e.preventDefault(); applyNoteFormat('insertUnorderedList'); }}
                                    title="Lista com marcadores"
                                    className="p-1.5 rounded-md text-slate-600 hover:bg-slate-100 hover:text-teal-600 cursor-pointer"
                                  >
                                    <List className="h-3.5 w-3.5" />
                                  </button>
                                  <button
                                    type="button"
                                    onMouseDown={(e) => { e.preventDefault(); applyNoteFormat('insertOrderedList'); }}
                                    title="Lista numerada"
                                    className="p-1.5 rounded-md text-slate-600 hover:bg-slate-100 hover:text-teal-600 cursor-pointer"
                                  >
                                    <ListOrdered className="h-3.5 w-3.5" />
                                  </button>
                                </div>

                                <div
                                  ref={noteEditorRef}
                                  contentEditable
                                  suppressContentEditableWarning
                                  data-placeholder="Grave observações importantes, trechos de código ou anotações teóricas desta aula aqui..."
                                  className="w-full min-h-32 p-3 text-xs font-sans text-slate-800 focus:outline-hidden empty:before:content-[attr(data-placeholder)] empty:before:text-slate-400 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5"
                                />
                              </div>

                              <div className="flex items-center justify-end gap-3 text-right">
                                {noteSaved && (
                                  <span className="text-[10.5px] font-bold text-emerald-600 flex items-center gap-1">
                                    <Check className="h-3.5 w-3.5" />
                                    Anotação salva!
                                  </span>
                                )}
                                <button
                                  onClick={baixarAnotacaoEmPdf}
                                  title="Abre o diálogo de impressão; escolha “Salvar como PDF”."
                                  className="rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-xs px-4 py-1.8 shadow-xs transition-colors cursor-pointer flex items-center gap-1.5"
                                >
                                  <Download className="h-3.5 w-3.5" />
                                  Baixar em PDF
                                </button>
                                <button
                                  onClick={handleSaveNoteText}
                                  className="rounded-lg bg-teal-600 hover:bg-teal-500 text-white font-semibold text-xs px-4 py-1.8 shadow-xs transition-transform hover:scale-[1.02] cursor-pointer animate-none"
                                >
                                  Salvar Anotação
                                </button>
                              </div>
                            </div>
                          )}

                          {features.mensagensDiretas && activeTab === 'suporte' && (
                            <div className="space-y-4">
                              <h4 className="font-bold text-slate-900 flex items-center gap-1.5 pb-2 border-b border-slate-100">
                                <HelpCircle className="h-4 w-4 text-teal-500" />
                                <span>Suporte Pedagógico</span>
                              </h4>
                              <p className="text-[11px] text-slate-500 leading-relaxed">
                                Tem dúvidas sobre o conteúdo desta aula ou sobre algum problema técnico? Envie sua mensagem diretamente ao Gestor de Conteúdos abaixo — ela é registrada no seu canal de mensagens e respondida por lá.
                              </p>

                              <div className="bg-slate-50 border border-slate-150 rounded-xl p-3 flex items-start gap-3 mt-2">
                                <div className="relative">
                                  <User className="h-8 w-8 text-slate-400 p-1 bg-slate-200 rounded-full" />
                                  <span className={`absolute -bottom-0.5 -right-0.5 block h-2.5 w-2.5 rounded-full border border-white ${
                                    (localStorage.getItem(`ava_presence_status_${selectedCourse.instructorId ?? ''}`) || 'online') === 'online'
                                      ? 'bg-emerald-500 animate-pulse'
                                      : 'bg-slate-400'
                                  }`} />
                                </div>
                                <div className="space-y-1">
                                  <strong className="text-slate-900 block font-bold leading-tight flex items-center gap-1.5">
                                    <span>Prof. {selectedCourse.instructorName}</span>
                                    <span className={`text-[9px] font-black leading-none ${
                                      (localStorage.getItem(`ava_presence_status_${selectedCourse.instructorId ?? ''}`) || 'online') === 'online'
                                        ? 'text-emerald-600 bg-emerald-50 px-1 py-0.5 rounded border border-emerald-500/10'
                                        : 'text-slate-500 text-slate-500 bg-slate-100 px-1 py-0.5 rounded border border-slate-200'
                                    }`}>
                                      {(localStorage.getItem(`ava_presence_status_${selectedCourse.instructorId ?? ''}`) || 'online') === 'online' ? 'ONLINE' : 'OFFLINE'}
                                    </span>
                                  </strong>
                                  <span className="text-[10px] text-slate-450 block">Tempo de resposta esperado: &lt; 2 horas</span>
                                </div>
                              </div>

                              <div className="space-y-2">
                                <textarea
                                  value={lessonSupportMessage}
                                  onChange={(e) => setLessonSupportMessage(e.target.value)}
                                  placeholder="Escreva sua dúvida ou relate um problema sobre esta aula..."
                                  className="w-full rounded-xl border border-slate-200 bg-slate-50/50 p-3 h-24 focus:outline-hidden focus:ring-1 focus:ring-teal-500 text-xs font-sans text-slate-800"
                                />
                                <div className="flex items-center justify-end gap-3">
                                  {lessonSupportMessageSent && (
                                    <span className="text-[10.5px] font-bold text-emerald-600 flex items-center gap-1">
                                      <CheckCircle className="h-3.5 w-3.5" />
                                      Mensagem enviada!
                                    </span>
                                  )}
                                  <button
                                    onClick={handleSendLessonSupportMessage}
                                    disabled={!lessonSupportMessage.trim()}
                                    className="rounded-lg bg-teal-600 hover:bg-teal-500 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-semibold text-xs px-4 py-1.8 shadow-xs transition-colors cursor-pointer flex items-center gap-1.5"
                                  >
                                    <Send className="h-3.5 w-3.5" />
                                    <span>Enviar Mensagem</span>
                                  </button>
                                </div>
                              </div>
                            </div>
                          )}

                          {features.forum && activeTab === 'forum' && (
                            <div className="space-y-4">
                              <h4 className="font-bold text-slate-900 flex items-center gap-1.5 pb-2 border-b border-slate-100">
                                <MessageSquare className="h-4 w-4 text-teal-500 animate-pulse" />
                                <span>Fórum de Dúvidas & Interação da Comunidade</span>
                              </h4>
                              <p className="text-[11px] text-slate-500 leading-relaxed">
                                Faça perguntas sobre o conteúdo atual da aula ou debata soluções com seus colegas sem sair do ambiente de aprendizado.
                              </p>
                              <div className="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                                <CourseForum selectedCourse={selectedCourse} />
                              </div>
                            </div>
                          )}

                        </div>
                      </div>

                      {/* Navegação no FIM da aula: quem terminou de ler não deve
                          rolar de volta ao topo para seguir adiante. */}
                      <nav
                        aria-label="Navegação entre aulas"
                        className="w-full max-w-3xl mx-auto flex items-center justify-between gap-3 pt-1"
                      >
                        {(() => {
                          const prev = selectedCourse.lessons.find(l => l.order === activeLesson.order - 1);
                          const next = selectedCourse.lessons.find(l => l.order === activeLesson.order + 1);

                          return (
                            <>
                              <button
                                onClick={() => {
                                  if (prev) {
                                    setActiveLesson(prev);
                                    window.scrollTo({ top: 0, behavior: 'smooth' });
                                  }
                                }}
                                disabled={!prev}
                                title={prev ? prev.title : 'Esta é a primeira aula'}
                                className={`flex items-center gap-2 rounded-xl border px-4 py-2.5 text-xs font-bold transition-all min-w-0 ${
                                  prev
                                    ? 'border-slate-300 text-slate-700 hover:bg-slate-100 cursor-pointer'
                                    : 'border-slate-200 text-slate-300 cursor-not-allowed'
                                }`}
                              >
                                <ArrowRight className="h-3.5 w-3.5 rotate-180 shrink-0" />
                                {/* Rótulo genérico: o nome da aula de destino fica no title/tooltip. */}
                                <span className="truncate uppercase tracking-wider">Aula anterior</span>
                              </button>

                              <button
                                onClick={() => {
                                  if (!next) return;

                                  /*
                                   * Avançar CONCLUI a aula atual. Decisão do
                                   * usuário em 08/09/2026: vários alunos leram
                                   * "Concluir esta aula" como status que mudou, e
                                   * avançar sem concluir deixava a grade cheia de
                                   * aulas lidas e não marcadas.
                                   *
                                   * A checagem antes é obrigatória, não zelo:
                                   * `toggleLessonCompletion` ALTERNA, então numa
                                   * aula já concluída avançar a DESMARCARIA — e a
                                   * frequência cairia por navegar para frente.
                                   */
                                  const jaConcluida = currentCourseProgress?.completedLessons.includes(activeLesson.id) ?? false;
                                  if (!jaConcluida) {
                                    toggleLessonCompletion(selectedCourse.id, activeLesson.id);
                                  }

                                  setActiveLesson(next);
                                  window.scrollTo({ top: 0, behavior: 'smooth' });
                                }}
                                disabled={!next}
                                title={next
                                  ? `Conclui esta aula e abre: ${next.title}`
                                  : 'Esta é a última aula — conclua-a pelo botão acima'}
                                className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition-all min-w-0 ${
                                  next
                                    ? 'bg-[#540D6E] hover:bg-purple-950 text-white cursor-pointer shadow-xs'
                                    : 'border border-slate-200 text-slate-300 cursor-not-allowed'
                                }`}
                              >
                                <span className="truncate uppercase tracking-wider">{next ? 'Próxima aula' : 'Última aula'}</span>
                                <ArrowRight className="h-3.5 w-3.5 shrink-0" />
                              </button>
                            </>
                          );
                        })()}
                      </nav>

                      {/* O efeito colateral fica ESCRITO. Botão que muda registro
                          acadêmico em silêncio é a origem da próxima reclamação. */}
                      <p className="w-full max-w-3xl mx-auto text-[10px] text-slate-400 text-right leading-normal">
                        Avançar marca esta aula como concluída e conta para a sua frequência.
                      </p>
                    </div>
                  ) : (
                    /* Initial Welcome course billboard if no active lesson selected */
                    <div className="rounded-2xl border border-dashed border-teal-200 bg-teal-50/5 p-8 text-center text-slate-600 space-y-5 animate-in fade-in duration-300">
                      <div className="inline-flex rounded-full bg-teal-100 text-teal-600 p-4 shrink-0 shadow-xs border border-teal-200/55">
                        <Monitor className="h-10 w-10 animate-pulse" />
                      </div>
                      
                      <div className="max-w-md mx-auto space-y-2">
                        <h4 className="font-black text-slate-900 text-lg">Aulas de {selectedCourse.title}</h4>
                        <p className="text-xs text-slate-500 leading-relaxed">
                          Selecione uma aula na barra lateral para abrir a estação de aprendizagem. As avaliações e os encontros ao vivo do curso ficam nas abas acima.
                        </p>
                      </div>

                      <div className="flex justify-center flex-col sm:flex-row gap-3 pt-3">
                        <button
                          onClick={() => {
                            // A PRIMEIRA aula e a de menor `order`, nao a
                            // primeira do array: a ordem de chegada nao e a da
                            // grade, e "Iniciar" abria uma aula do meio.
                            const primeira = aulasEmOrdem[0];
                            if (primeira !== undefined) setActiveLesson(primeira);
                          }}
                          className="rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs px-5 py-2.5 shadow-sm hover:scale-[1.01] transition-transform flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                          <Play className="h-3.5 w-3.5 fill-white" />
                          <span>Iniciar primeira aula</span>
                        </button>
                        
                        <button
                          onClick={voltarParaMeusCursos}
                          className="rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 font-bold text-xs px-5 py-2.5 transition-colors cursor-pointer"
                        >
                          Trocar de Curso
                        </button>
                      </div>
                    </div>
                  )}

                  {/* Fórum de Discussão do Curso (controlado pela feature flag) */}
                  {features.forum && !activeLesson && !showAvaliacoes && (
                    <div className="mt-6 animate-in fade-in duration-300">
                      <CourseForum selectedCourse={selectedCourse} />
                    </div>
                  )}

                </div>

                {/* 2. Structured Syllabus Selector Sidebar Accordion Grid (lg:col-span-4) */}
                {(!activeLesson && !showAvaliacoes) && (
                <div className="lg:col-span-4 space-y-4">
                  
                  {/*
                    Lista de AULAS, e nao de modulos.
                    O painel mostrava tres "modulos" por curso — nome e descricao
                    escritos no proprio componente, presos a `course.id ===
                    'course-1'`, dividindo as aulas por POSICAO (`slice(0, 2)`).
                    Modulo nao existe no banco: `Lesson` nao tem coluna de modulo
                    e nao ha tabela `Module`. Consequencias medidas: o gestor nao
                    podia criar nem renomear modulo; inserir uma aula no comeco
                    fazia as aulas escorregarem de modulo sem aviso, sob um
                    titulo que descrevia outro conteudo; e todo curso fora do
                    course-1/course-2 recebia "Introducao Basica" prometendo
                    "exercicios de fixacao assistida e material complementar" que
                    podiam nao existir.
                    Decisao da coordenacao (09/09/2026): modulo nao precisa
                    existir — a lista de aulas basta.
                  */}
                  <div className="flex items-center justify-between">
                    <h4 className="font-black text-slate-900 flex items-center gap-1.5 text-xs uppercase tracking-wider">
                      <BookOpen className="h-4 w-4 text-teal-600" />
                      <span>Aulas do Curso</span>
                    </h4>

                    <span className="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                      {currentCourseProgress?.completedLessons.length || 0} / {selectedCourse.lessons.length} Aulas
                    </span>
                  </div>

                  <div className="space-y-2.5">
                    {aulasEmOrdem.length === 0 ? (
                      // Curso sem aula diz que nao tem aula. O texto anterior
                      // anunciava modulos que ele tambem nao tinha.
                      <p className="text-[11px] text-slate-500 bg-slate-50 border border-slate-200 rounded-lg p-3">
                        Este curso ainda não tem aulas publicadas.
                      </p>
                    ) : aulasEmOrdem.map((lesson, idx) => {
                      const isDone = currentCourseProgress?.completedLessons.includes(lesson.id) ?? false;

                      return (
                        <div
                          key={lesson.id}
                          onClick={() => setActiveLesson(lesson)}
                          className="border rounded-lg overflow-hidden transition-all cursor-pointer p-3 flex items-center justify-between gap-3 group text-left bg-white border-slate-200 hover:border-teal-300"
                        >
                          <div className="flex-1 text-left min-w-0">
                            <span className="block text-[11px] font-bold leading-tight text-slate-800 group-hover:text-teal-600 transition-colors">
                              {idx + 1}. {lesson.title}
                            </span>
                            <div className="flex items-center gap-1 mt-1.5">
                              {/*
                                Duracao vem de texto livre digitado no cadastro e
                                pode estar em branco — nesse caso nao se afirma
                                duracao nenhuma, em vez de mostrar "undefined".
                              */}
                              <span className="text-[9px] font-mono text-slate-500">
                                {(lesson.duration ?? '').trim() !== '' ? lesson.duration : 'Duração não informada'}
                              </span>
                              <ChevronRight className="h-2.5 w-2.5 text-slate-400 group-hover:text-teal-500 group-hover:translate-x-0.5 transition-transform" />
                            </div>
                          </div>

                          <div className="flex items-center shrink-0">
                            {isDone ? (
                              <span className="text-emerald-600 flex items-center justify-center p-1 bg-emerald-50 rounded-full" title="Aula concluída">
                                <CheckCircle className="h-3.5 w-3.5" />
                              </span>
                            ) : (
                              <span className="text-[9.5px] font-bold font-mono px-1.5 py-0.5 rounded border bg-slate-100 border-slate-200 text-slate-500">
                                Abrir
                              </span>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>

                  {/*
                    3. Transmissões ao vivo — SÓ as de hoje.
                    Listava `liveSessions` inteiro: encontro de dias atrás ficava
                    na tela com "Entrar na Sala" ativo e o convite a "aguardar o
                    professor". Sem nenhuma hoje o bloco não aparece, em vez de
                    virar uma caixa vazia com título.
                  */}
                  {transmissoesDeHoje.length > 0 && (
                  <div className="border border-teal-100 bg-teal-50/15 rounded-xl p-3 text-left space-y-2.5">
                    <h5 className="font-bold text-slate-900 text-[10px] uppercase tracking-wider flex items-center gap-1.5">
                      <Video className="h-3.5 w-3.5 text-teal-600" />
                      <span>Transmissões de hoje</span>
                    </h5>

                    <div className="space-y-2">
                      {transmissoesDeHoje.map((session, idx) => {
                        const isAttended = currentCourseProgress?.attendedLiveSessions.includes(session.id) || false;
                        return (
                          <div key={`${session.id}-${idx}`} className="bg-white rounded-lg border border-teal-100/40 p-2.5 leading-relaxed text-left text-[11px]">
                            
                            <div className="flex items-start justify-between gap-1.5">
                              <div>
                                <strong className="font-bold text-slate-900">{session.title}</strong>
                                <span className="text-[9px] text-slate-400 block mt-0.5">{formatScheduledAt(session.scheduledAt)} ({session.durationMinutes} min)</span>
                              </div>

                              <span className={`text-[8px] font-extrabold uppercase px-1.5 rounded shrink-0 leading-normal ${
                                isAttended 
                                  ? 'bg-emerald-50 text-emerald-700' 
                                  : 'bg-amber-50 text-amber-600'
                              }`}>
                                {isAttended ? 'Presente' : 'Ausente'}
                              </span>
                            </div>

                            <div className="mt-2.5 pt-2 border-t border-slate-100 flex flex-col gap-1.5">
                              <div className="flex flex-col sm:flex-row gap-1.5 w-full">
                                <button
                                  onClick={() => setActiveLiveSession(session)}
                                  className={`flex-1 font-bold py-2 px-3 rounded-lg text-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer ${
                                    situacaoTransmissao(session, agoraTransmissao) === 'ao-vivo'
                                      ? 'bg-red-600 hover:bg-red-500 text-white animate-pulse'
                                      : 'bg-teal-600 hover:bg-teal-500 text-white'
                                  }`}
                                >
                                  <Video className="h-4 w-4 fill-white shrink-0" />
                                  <span>{situacaoTransmissao(session, agoraTransmissao) === 'ao-vivo' ? 'Entrar ao Vivo' : 'Entrar na Sala'}</span>
                                </button>
                                
                                <a
                                  href={safeHref(session.meetingLink)}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/60 font-bold py-2 px-3 rounded-lg text-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer text-center"
                                >
                                  <ExternalLink className="h-4 w-4 text-slate-500 shrink-0" />
                                  <span>Google Meet</span>
                                </a>
                              </div>
                              {situacaoTransmissao(session, agoraTransmissao) === 'agendada' && (
                                <span className="text-[9px] text-slate-400 block text-center mt-1">
                                  Encontro agendado. Você pode entrar na sala virtual e aguardar o professor.
                                </span>
                              )}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                  )}

                  {/* 4. Testes e avaliações — só quando a disciplina tem alguma. */}
                  {avaliacoesDoCursoAberto.length > 0 && (
                  <div className="border border-amber-100 bg-amber-50/10 rounded-xl p-3.5 text-left space-y-3 shadow-2xs">
                    <h5 className="font-bold text-slate-900 text-[10px] uppercase tracking-wider flex items-center gap-1.5">
                      <CheckSquare className="h-3.5 w-3.5 text-amber-600" />
                      <span>Testes e Avaliações</span>
                    </h5>

                    <div className="space-y-2.5">
                      {avaliacoesDoCursoAberto.map((quiz) => {
                          // A tentativa VIGENTE, não a primeira que a lista trouxer:
                          // o histórico de tentativas ficou no ar e `find()` passou a
                          // devolver qualquer uma delas.
                          const userSub = tentativaVigente(quizSubmissions, quiz.id, activeUser.id);
                          return (
                            <div key={quiz.id} className="bg-white rounded-xl border border-slate-200 p-3.5 leading-relaxed text-left text-xs space-y-3 shadow-xs">
                              <div className="flex items-start justify-between gap-1.5">
                                <div className="space-y-0.5">
                                  <strong className="font-bold text-slate-900 block text-xs leading-snug">{quiz.title}</strong>
                                  <span className="text-[10px] text-slate-450 font-medium block">{quiz.questions.length} questões</span>
                                </div>
                                {userSub && (
                                  <span className={`text-[9px] font-extrabold px-2 py-0.5 rounded-md uppercase shrink-0 ${
                                    userSub.passed ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-amber-50 text-amber-700 border border-amber-100'
                                  }`}>
                                    {userSub.passed ? `Nota: ${userSub.scorePercent}%` : `${userSub.scorePercent}%`}
                                  </span>
                                )}
                              </div>

                              {userSub ? (
                                <div className="space-y-2">
                                  <div className="text-[10px] font-semibold text-slate-500 block">
                                    Último envio: {textoDaTentativa(userSub)}
                                  </div>
                                  <button
                                    onClick={() => abrirAvaliacoes(quiz.id)}
                                    className="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 font-bold py-2 px-4 rounded-lg text-xs transition-colors flex items-center justify-center cursor-pointer shadow-2xs"
                                  >
                                    Refazer Avaliação
                                  </button>
                                </div>
                              ) : (
                                <button
                                  onClick={() => abrirAvaliacoes(quiz.id)}
                                  className="w-full bg-amber-600 hover:bg-amber-500 text-white font-bold py-2 px-4 rounded-lg text-xs uppercase tracking-wider transition-all flex items-center justify-center cursor-pointer shadow-xs"
                                >
                                  Começar
                                </button>
                              )}
                            </div>
                          );
                        })}
                    </div>
                  </div>
                  )}

                  {/* 5. Exercícios de fixação — só quando a disciplina tem algum. */}
                  {exerciciosDoCursoAberto.length > 0 && (
                  <div className="border border-teal-100 bg-teal-50/10 rounded-xl p-3.5 text-left space-y-3 shadow-2xs">
                    <h5 className="font-bold text-slate-900 text-[10px] uppercase tracking-wider flex items-center gap-1.5">
                      <FileCheck className="h-3.5 w-3.5 text-teal-600" />
                      <span>Exercícios de Fixação</span>
                    </h5>

                    <div className="space-y-2.5">
                        <div className="space-y-2.5">
                          {exerciciosDoCursoAberto.map((ex, idx) => {
                            const studentSub = exerciseSubmissions.find(s => s.exerciseId === ex.id && s.userId === activeUser.id);
                            return (
                              <div key={`${ex.id}-${idx}`} className="bg-white rounded-xl border border-slate-200 p-3.5 leading-relaxed text-left text-xs space-y-3 shadow-xs">
                                <div className="flex items-start justify-between gap-1.5">
                                  <div className="space-y-0.5">
                                    <strong className="font-bold text-slate-900 block text-xs leading-snug">{ex.title}</strong>
                                    <span className="text-[10px] text-slate-450 font-medium block">Exercício Prático</span>
                                  </div>
                                  <span className={`text-[9px] font-extrabold px-2 py-0.5 rounded-md uppercase shrink-0 ${
                                    studentSub?.status === 'approved' 
                                      ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' 
                                      : studentSub?.status === 'pending'
                                      ? 'bg-indigo-50 text-indigo-750 border border-indigo-100'
                                      : studentSub?.status === 'rejected' || studentSub?.status === 'revision'
                                      ? 'bg-amber-50 text-amber-700 font-extrabold border border-amber-100'
                                      : 'bg-slate-100 text-slate-500 border border-slate-200'
                                  }`}>
                                    {
                                      studentSub?.status === 'approved' ? `Nota: ${studentSub.score}/${ex.maxPoints}` :
                                      studentSub?.status === 'pending' ? 'Aguardando' :
                                      studentSub?.status === 'rejected' ? 'Refazer' :
                                      studentSub?.status === 'revision' ? 'Revisar' : 'Pendente'
                                    }
                                  </span>
                                </div>
                              </div>
                            );
                          })}

                          {/*
                            Abre a página de exercícios do CURSO. Antes isto
                            pulava para a primeira aula e trocava de aba — e com
                            a flag desligada a aba nem existia, então o botão
                            simplesmente não fazia nada.
                          */}
                          <button
                            onClick={() => setShowExercicios(true)}
                            className="w-full bg-teal-600 hover:bg-teal-500 text-white font-bold py-2 px-4 rounded-lg text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 cursor-pointer shadow-xs"
                          >
                            <FileCheck className="h-4 w-4" />
                            <span>Abrir Atividades Práticas</span>
                          </button>
                        </div>
                    </div>
                  </div>
                  )}

                </div>
                )}

                </div>
                )}


            </div>
          )
          ) : (
            /* Browse All Courses Grid */
            <div className="space-y-5 text-left relative">
              
              {/* Calendar Modal Overlay */}
              {showUpcomingCalendar && (
                <div className="absolute inset-x-0 -top-12 z-50 animate-in zoom-in-95 fade-in duration-200">
                  <div className="bg-white rounded-2xl border border-slate-200 shadow-xl overflow-hidden max-w-xl mx-auto">
                    <div className="bg-[#540D6E] p-4 text-white flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Calendar className="h-5 w-5" />
                        <h4 className="font-black uppercase tracking-widest text-xs">Próximas Sessões ao Vivo</h4>
                      </div>
                      <button onClick={() => setShowUpcomingCalendar(false)} className="bg-white/10 hover:bg-white/20 p-1.5 rounded-lg text-[10px] uppercase font-bold">Fechar</button>
                    </div>
                    <div className="p-4 space-y-3 max-h-[400px] overflow-y-auto">
                      {courses
                        .flatMap(c => c.liveSessions)
                        /*
                          Este bloco se chama "Próximas Sessões" e listava tudo
                          que não estava ao vivo — inclusive encontro de dias
                          atrás, anunciado como próximo. Encerrado pela regra das
                          24h sai da lista.
                        */
                        .filter(s => !encerradaPorTempo(s, agoraTransmissao))
                        .filter(s => !s.isLive)
                        .map((session, idx) => (
                        <div key={`${session.id}-${idx}`} className="flex items-center gap-4 p-3 rounded-xl border border-slate-100 bg-slate-50 hover:bg-white transition-colors group">
                           <div className="bg-white p-2.5 rounded-lg border border-slate-200 text-center min-w-[70px] group-hover:border-teal-200 group-hover:bg-teal-50 transition-all">
                              <span className="block text-[10px] font-black text-slate-400 uppercase leading-none mb-1">DATA</span>
                              <span className="text-sm font-black text-slate-700 leading-none">{dataCurta(session.scheduledAt)}</span>
                           </div>
                           <div className="flex-1 min-w-0">
                              <span className="text-[9px] font-bold text-teal-600 bg-teal-50 px-1.5 py-0.5 rounded uppercase tracking-wide">
                                {courses.find(c => c.id === session.courseId)?.title}
                              </span>
                              <h5 className="text-[11px] font-bold text-slate-900 mt-1 truncate">{session.title}</h5>
                              <div className="flex items-center gap-3 mt-1.5 text-[9px] text-slate-500 font-medium">
                                <span className="flex items-center gap-1"><Clock className="h-2.5 w-2.5" />{horaCurta(session.scheduledAt)}</span>
                                <span className="flex items-center gap-1"><Globe className="h-2.5 w-2.5" />Horário de Brasília (Local)</span>
                              </div>
                           </div>
                           <a href={safeHref(session.meetingLink)} target="_blank" rel="noreferrer" className="bg-slate-950 text-white p-2 rounded-lg hover:bg-slate-800 transition-colors">
                              <ExternalLink className="h-4 w-4" />
                           </a>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              )}

              {viewingCatalogCourse ? (
                /* Specs detailed previews sheet with rich pedagogical curriculum to prevent churn */
                <div id="course-catalog-detailed-syllabus-preview" className="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-md text-left animate-in fade-in duration-300 space-y-6">
                  {/* Top Header Navigation */}
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-5">
                    <button
                      id="btn-back-to-catalog"
                      onClick={() => setViewingCatalogCourse(null)}
                      className="flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors cursor-pointer"
                    >
                      ← Voltar à Vitrine / Catálogo de Cursos
                    </button>
                    <div className="flex items-center gap-2">
                      <span className="rounded-full bg-slate-100 border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600 uppercase tracking-wider text-[10px]">
                        {viewingCatalogCourse.category}
                      </span>
                      <span className="rounded-full bg-amber-50 text-amber-700 border border-amber-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-[10px] flex items-center gap-1">
                        <Lock className="h-3 w-3" /> Inscrição Pendente
                      </span>
                    </div>
                  </div>

                  {/* Main Header Presentation */}
                  <div className="space-y-3">
                    <h2 className="text-2xl md:text-3xl font-black text-slate-900 leading-tight tracking-tight">
                      {viewingCatalogCourse.title}
                    </h2>
                    <p className="text-xs md:text-sm text-slate-600 leading-relaxed max-w-4xl">
                      {viewingCatalogCourse.description}
                    </p>
                  </div>

                  <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 pt-2">
                    
                    {/* Course syllabus / Curriculum grade details */}
                    <div className="lg:col-span-2 space-y-4">
                      
                      {/* Short "Sobre o curso" Section */}
                      <div className="border border-slate-200 rounded-xl bg-slate-50/20 p-5 space-y-4 text-left">
                        <div>
                          <h3 className="text-sm font-black text-slate-850 uppercase tracking-wider">Sobre o curso</h3>
                          <p className="text-xs text-slate-600 leading-relaxed mt-2 font-medium">
                            Aprenda a construir aplicações full-stack modernas, integrando frontend, backend, APIs REST, autenticação e boas práticas de organização do código.
                          </p>
                        </div>
                        <div className="space-y-2 pt-2 border-t border-slate-100">
                          <strong className="block text-xs font-black text-slate-700 uppercase tracking-wide">Você vai aprender a:</strong>
                          <ul className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-600 font-semibold list-disc pl-4">
                            <li>configurar um ambiente moderno com React e Vite;</li>
                            <li>criar APIs com Node.js e Express;</li>
                            <li>consumir dados no frontend;</li>
                            <li>aplicar conceitos de autenticação e segurança;</li>
                            <li>organizar uma aplicação full-stack de forma prática.</li>
                          </ul>
                        </div>
                      </div>

                      <div className="border border-slate-200 rounded-xl bg-slate-50/40 p-5 space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                          <div>
                            <h4 className="text-xs uppercase font-black text-[#540D6E] tracking-wider">Prévia da grade do curso</h4>
                            <span className="text-[10px] text-slate-400 font-bold block mt-0.5">Conheça as aulas do curso antes de iniciar sua matrícula.</span>
                          </div>
                          <button
                            onClick={() => setIsFullSyllabusOpen(true)}
                            className="text-[10px] uppercase font-black bg-teal-50 hover:bg-teal-100 text-teal-800 px-3.5 py-2 border border-teal-200 rounded-lg transition-all cursor-pointer whitespace-nowrap flex items-center gap-1 shadow-xs"
                          >
                            <Layers className="h-3.5 w-3.5 text-teal-600" />
                            <span>Ver grade completa</span>
                          </button>
                        </div>

                        {/* Lessons syllabus list (Collapsible UX) */}
                        <div className="space-y-2.5">
                          {viewingCatalogCourse.lessons && viewingCatalogCourse.lessons.length > 0 ? (
                            viewingCatalogCourse.lessons.map((lesson, idx) => {
                              const isExpanded = !!expandedLessons[idx];
                              const toggleExpanded = () => {
                                setExpandedLessons(prev => ({
                                  ...prev,
                                  [idx]: !prev[idx]
                                }));
                              };

                              /*
                               * Havia AQUI quatro descricoes fixas sorteadas por
                               * `idx % 4` — a aula 3 de qualquer curso era
                               * descrita como "Avaliacao Teorica de Meio-Termo",
                               * a aula 5 voltava a ser "Introducao Basica". Era
                               * texto de vitrine, lido por quem decide se se
                               * matricula, e nao descrevia a aula nenhuma.
                               *
                               * O catalogo e publico e por isso NAO recebe
                               * `lesson.content` (o material sai zerado por
                               * escopo). Ou seja: nao existe descricao de aula
                               * para mostrar aqui. Entao nao se mostra nenhuma —
                               * o que ha de verdade e o titulo, a duracao e se a
                               * aula tem material de leitura.
                               */

                              return (
                                <div key={`${lesson.id}-${idx}`} className="bg-white rounded-xl border border-slate-200 transition-all overflow-hidden">
                                  <button
                                    onClick={toggleExpanded}
                                    className="w-full text-left p-3.5 flex items-center justify-between gap-4 hover:bg-slate-50/50 transition-colors cursor-pointer"
                                  >
                                    <div className="flex items-center gap-2.5 min-w-0">
                                      <span className="flex items-center justify-center h-6 w-6 rounded-lg bg-teal-50 border border-teal-200 text-[10px] font-black text-teal-850 shrink-0">
                                        {idx + 1}
                                      </span>
                                      <strong className="text-xs font-bold text-slate-800 leading-snug truncate">{lesson.title}</strong>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                      <span className="text-slate-500 text-[9px] font-mono font-bold bg-slate-50 px-2 py-0.5 rounded border border-slate-150">
                                        ⏰ {lesson.duration || '45 min'}
                                      </span>
                                      <ChevronDown className={`h-4 w-4 text-slate-400 transition-transform duration-200 ${isExpanded ? 'rotate-180' : ''}`} />
                                    </div>
                                  </button>
                                  
                                  {isExpanded && (
                                    <div className="px-4 pb-4 pt-1 text-[11px] text-slate-500 leading-relaxed font-medium bg-slate-50/40 border-t border-slate-100 animate-in fade-in slide-in-from-top-1">
                                      <span className="font-extrabold text-[#540D6E] block text-[9.5px] uppercase tracking-wider mb-1">Sobre esta aula:</span>
                                      {(lesson.duration ?? '').trim() !== ''
                                        ? `Duração estimada: ${lesson.duration}. `
                                        : 'Duração ainda não informada. '}
                                      O conteúdo desta aula fica disponível após a matrícula.
                                    </div>
                                  )}
                                </div>
                              );
                            })
                          ) : (
                            <p className="text-xs text-slate-400 italic text-center py-6">Nenhuma aula cadastrada ainda nesta disciplina.</p>
                          )}
                        </div>
                      </div>

                    </div>

                    {/* Enrollment CTA Panel Sidebar */}
                    <div className="space-y-4">
                      
                      {/* Teacher Profile & Direct Availability Details */}
                      <div className="bg-slate-50 border border-slate-200 rounded-xl p-5 space-y-4">
                        <h4 className="text-sm font-black text-slate-800 uppercase tracking-wider border-b border-slate-150 pb-2">Resumo do curso</h4>
                        <div className="flex items-center gap-2.5">
                          <div className="h-10 w-10 rounded-full bg-[#540D6E] text-white flex items-center justify-center text-xs font-black shrink-0 shadow-xs">
                            {viewingCatalogCourse.instructorName ? viewingCatalogCourse.instructorName.charAt(0) : 'P'}
                          </div>
                          <div className="min-w-0">
                            <strong className="text-xs font-black text-slate-800 block truncate">Prof. {viewingCatalogCourse.instructorName || 'Gestor de Conteúdos'}</strong>
                          </div>
                        </div>

                        {/* Quality Specifications - Simple list with light dividers and less heavy boxes */}
                        <div className="space-y-2.5 pt-2 text-xs font-medium text-slate-600 border-t border-slate-100">
                          <div className="flex justify-between items-center py-1.5 border-b border-slate-100">
                            <span>Aulas:</span>
                            <strong className="text-slate-800 font-bold font-mono text-[11px]">
                              20
                            </strong>
                          </div>
                          <div className="flex justify-between items-center py-1.5 border-b border-slate-100">
                            <span>Frequência mínima:</span>
                            <strong className="text-emerald-700 font-bold font-mono text-[11px]">
                              {courseMinAttendance(viewingCatalogCourse)}%
                            </strong>
                          </div>
                          <div className="flex justify-between items-center py-1.5 border-b border-slate-100">
                            <span>Modalidade:</span>
                            <strong className="text-slate-700 font-bold font-sans text-[11px]">
                              EAD autoinstrucional
                            </strong>
                          </div>
                          <div className="flex justify-between items-center py-1.5">
                            <span>Idioma:</span>
                            <strong className="text-slate-700 font-bold font-sans text-[11px]">
                              Português (Brasil)
                            </strong>
                          </div>
                        </div>

                        {/* CTA button to confirm enrollment */}
                        <div className="pt-3 border-t border-slate-200">
                          <button
                            id="btn-confirm-enroll"
                            onClick={() => {
                              setIsEnrollRulesChecked(false);
                              setEnrollSuccessMessage(null);
                              setIsEnrollModalOpen(true);
                            }}
                            className="w-full bg-[#540D6E] hover:bg-[#430a58] text-white font-black text-xs uppercase tracking-wide py-3.5 rounded-xl text-center transition-all cursor-pointer shadow-md hover:scale-[1.01] flex items-center justify-center gap-1.5"
                          >
                            <BookOpen className="h-4.5 w-4.5" />
                            <span>Inscrever-se</span>
                          </button>
                          <p className="text-[10px] text-slate-500 font-semibold text-center mt-2.5">
                            Comece seus estudos imediatamente após a confirmação.
                          </p>
                        </div>
                      </div>

                    </div>
                  </div>
                </div>
              ) : (
                <div className="space-y-6 text-left">
                  {/* Certificados agora vivem exclusivamente no Meu Perfil — sem card/atalho aqui. */}

                  {/* Scenario 1: Active Enrolled Course Card(s) — normalmente 1, mas pode haver mais
                      de uma matrícula ativa quando o Admin Superior concede canMultiEnroll */}
                  {activeEnrolledCourseIds.map((activeCourseId) => {
                    const activeCourse = courses.find(c => c.id === activeCourseId);
                    if (!activeCourse) return null;
                    const attendance = calculateAttendancePercent(activeCourse.id);
                    const minAttendance = courseMinAttendance(activeCourse);
                    const expired = isCourseExpired(activeCourse.contractExpirationDate);

                    if (expired) {
                      return (
                        <div key={activeCourse.id} className="rounded-2xl border border-amber-250 bg-amber-50/20 p-5 md:p-6 shadow-xs animate-in fade-in duration-300">
                          <div className="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-6">
                            <div className="space-y-2 max-w-xl">
                              <span className="bg-amber-100 text-amber-800 border border-amber-200 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest font-mono inline-flex items-center gap-1"><AlertTriangle className="h-3 w-3" />Vigência de Exibição Encerrada (Arquivado)</span>
                              <h3 className="text-base md:text-lg font-black text-slate-850 leading-tight">{activeCourse.title}</h3>
                              <p className="text-xs text-slate-500 leading-relaxed">
                                Este curso foi <strong>arquivado preventivamente</strong> e o acesso letivo foi suspenso, pois o prazo contratual de exibição encerrou em <strong>{activeCourse.contractExpirationDate}</strong>.
                              </p>
                              <p className="text-[11px] text-amber-900 bg-amber-100/40 p-3 rounded-xl border border-amber-200/50 leading-relaxed mt-2.5">
                                <Lightbulb className="h-3.5 w-3.5 inline-block mr-1 -mt-0.5 text-amber-700" /><strong>Como estudar outra disciplina?</strong> Para liberar seu cadastro e escolher um novo curso ativo, clique no botão <strong>"Cancelar inscrição"</strong> ao lado. Isso abrirá imediatamente o catálogo de disciplinas disponíveis para você se matricular e começar a estudar!
                              </p>
                            </div>
                            <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                              <button
                                onClick={async () => {
                                  const result = await dropStudentFromCourse(activeUser.id, activeCourse.id);
                                  if (!result.ok) {
                                    showAlert(result.error || 'Não foi possível cancelar a inscrição.');
                                    return;
                                  }
                                  speakText("Sua inscrição no curso expirado foi cancelada.");
                                }}
                                className="bg-slate-900 hover:bg-slate-800 text-white font-black text-xs uppercase tracking-wider px-5 py-3 rounded-xl transition-all shadow-md text-center flex items-center justify-center gap-1.5 cursor-pointer"
                              >
                                <Archive className="h-4 w-4 text-amber-400" />
                                <span>Cancelar inscrição</span>
                              </button>
                            </div>
                          </div>
                        </div>
                      );
                    }

                    return (
                      <div key={activeCourse.id} className="rounded-2xl border border-teal-200 bg-teal-50/20 p-5 md:p-6 shadow-xs animate-in fade-in duration-300">
                        <div className="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-6">
                          <div className="space-y-2 max-w-xl">
                            <span className="bg-teal-100 text-teal-850 border border-teal-200 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest font-mono">Curso Ativo em Andamento</span>
                            <h3 className="text-base md:text-lg font-black text-slate-900 leading-tight">{activeCourse.title}</h3>
                            <p className="text-xs text-slate-500 leading-relaxed line-clamp-2">{activeCourse.description}</p>

                            <div className="flex flex-wrap items-center gap-4 mt-2">
                              <div className="text-[10px] text-slate-600 font-medium">
                                Prof. <strong className="text-slate-800 font-bold">{activeCourse.instructorName}</strong>
                              </div>
                              <div className="text-[10px] text-slate-600 flex items-center gap-1.5">
                                <span>Frequência Atual:</span>
                                <strong className={`font-mono text-xs ${attendance >= minAttendance ? 'text-emerald-600 font-black' : 'text-amber-600'}`}>{attendance}%</strong>
                                <span className="text-slate-400">/ Mínimo {minAttendance}%</span>
                              </div>
                            </div>
                          </div>

                          <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                            <button
                              onClick={() => {
                                setSelectedCourse(activeCourse);
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                              }}
                              className="bg-[#540D6E] hover:bg-[#430858] text-white font-black text-xs uppercase tracking-wider px-5 py-3 rounded-xl transition-all shadow-md hover:scale-[1.01] text-center flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                              <PlayCircle className="h-4 w-4 animate-pulse" />
                              <span>Entrar na Sala de Aula</span>
                            </button>
                          </div>
                        </div>
                      </div>
                    );
                  })}

                  {/* Scenario 2: Active Dropout Penalty Warning Card */}
                  {features.penalidadesCancelamento && enrollmentRecord.dropOutPenaltyUntil && new Date(enrollmentRecord.dropOutPenaltyUntil).getTime() > Date.now() ? (() => {
                    const pendingPenaltyRequest = academicRequests.find(r => r.userId === activeUser.id && r.type === 'matricula' && r.status === 'pending');
                    const rejectedPenaltyRequest = academicRequests.find(r => r.userId === activeUser.id && r.type === 'matricula' && r.status === 'rejected');

                    return (
                      <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 shadow-xs animate-in fade-in duration-300 text-left">
                        <div className="flex flex-col sm:flex-row items-start justify-between gap-5">
                          <div className="space-y-1.5 max-w-2xl w-full">
                            <div className="flex items-center gap-2 text-rose-800 font-bold text-sm">
                              <Lock className="h-5 w-5 text-rose-600 animate-pulse" />
                              <span>Restrição Temporária de Matrícula — Justificativa Pendente</span>
                            </div>
                            <p className="text-xs text-rose-900/85 leading-relaxed">
                              Caso o aluno possua uma restrição temporária de nova matrícula por não conclusão anterior, o sistema informa a data prevista para nova solicitação ou permite o envio de justificativa para análise da coordenação.
                            </p>
                            <div className="text-[11px] text-rose-700 font-semibold pt-1">
                              Sua restrição expira em: <span className="underline font-bold font-mono bg-rose-100 px-1.5 py-0.5 rounded">{new Date(enrollmentRecord.dropOutPenaltyUntil).toLocaleDateString('pt-BR')}</span>
                            </div>

                            {/* Justification Form and Statuses */}
                            <div className="mt-4 pt-4 border-t border-rose-200/50 w-full">
                              {pendingPenaltyRequest ? (
                                <div className="p-3 bg-amber-50 border border-amber-200 rounded-xl space-y-1">
                                  <div className="flex items-center gap-1.5 text-[11px] font-bold text-amber-800">
                                    <div className="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                                    Solicitação de Reversão em Análise
                                  </div>
                                  <p className="text-[10.5px] text-amber-900/90 italic leading-normal">
                                    "{pendingPenaltyRequest.description}"
                                  </p>
                                  <p className="text-[10px] text-slate-500 font-medium">
                                    Sua justificativa foi protocolada com sucesso. O administrador analisará os motivos apresentados e dará o parecer em breve.
                                  </p>
                                </div>
                              ) : (
                                <div className="space-y-3 w-full">
                                  {rejectedPenaltyRequest && (
                                    <div className="p-3 bg-red-50 border border-red-200 rounded-xl mb-2">
                                      <p className="text-[11px] font-bold text-red-800">Sua solicitação anterior foi indeferida</p>
                                      <p className="text-[10.5px] text-red-900 italic leading-normal">"{rejectedPenaltyRequest.description}"</p>
                                      <p className="text-[10px] text-slate-600 mt-1">Você pode submeter uma nova justificativa abaixo se possuir novos fatos ou documentos comprovantes.</p>
                                    </div>
                                  )}
                                  
                                  <label className="block text-[10px] font-black uppercase tracking-wider text-rose-900/80">
                                    Justificar Cancelamento de Inscrição
                                  </label>
                                  <p className="text-[10.5px] text-rose-800/80 leading-normal">
                                    Apresente abaixo a justificativa (ex: motivo de saúde, trabalho ou força maior) para que a coordenação pedagógica julgue a reversão da restrição de matrícula:
                                  </p>
                                  <textarea
                                    value={penaltyJustification}
                                    onChange={(e) => setPenaltyJustification(e.target.value)}
                                    placeholder="Escreva detalhadamente o seu motivo aqui..."
                                    className="w-full text-xs p-3 rounded-xl border border-rose-300 bg-white text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-rose-500 min-h-[80px] placeholder:text-slate-400"
                                  />
                                  <button
                                    onClick={() => {
                                      const text = penaltyJustification.trim();
                                      if (!text) {
                                        showAlert("Por favor, preencha o motivo de sua justificativa antes de enviar.");
                                        return;
                                      }
                                      addAcademicRequest({
                                        type: 'matricula',
                                        description: `[Reversão de Restrição] Motivo: ${text}`,
                                        courseTitle: 'Justificativa de Cancelamento'
                                      });
                                      setPenaltyJustification('');
                                      speakText("Sua justificativa foi registrada e enviada para o julgamento da administração da plataforma.");
                                    }}
                                    className="bg-slate-900 hover:bg-slate-800 text-white font-bold text-[10px] uppercase tracking-wider px-4 py-2.5 rounded-xl transition-all border border-slate-700 cursor-pointer flex items-center gap-1.5"
                                  >
                                    Solicitar Liberação de Matrícula
                                  </button>
                                </div>
                              )}
                            </div>
                          </div>
                        </div>
                      </div>
                    );
                  })() : null}

                  {/* Scenario 3: Course Selection Catalog (sem matrícula ativa, ou com permissão de matrícula múltipla, e sem restrição) */}
                  {canEnrollInMoreCourses && !(features.penalidadesCancelamento && enrollmentRecord.dropOutPenaltyUntil && new Date(enrollmentRecord.dropOutPenaltyUntil).getTime() > Date.now()) && (
                    <div className="space-y-5">
                      <div className="bg-teal-50/55 p-4 rounded-2xl border border-teal-150/40 flex items-center gap-3">
                        <Sparkles className="h-4.5 w-4.5 text-teal-600 shrink-0" />
                        <div className="text-left text-xs text-slate-700 leading-relaxed">
                          <span className="font-extrabold text-teal-950 mr-1.5">Início da Jornada:</span>
                          {activeEnrolledCourseIds.length > 0
                            ? 'Você tem permissão para cursar mais de uma disciplina ao mesmo tempo. Selecione outra disciplina abaixo para se matricular também!'
                            : 'Selecione um curso na lista abaixo para se matricular e iniciar os seus estudos de forma imediata!'}
                        </div>
                      </div>

                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <div className="h-2 w-2 rounded-full bg-teal-500 animate-pulse"></div>
                          <h3 className="text-sm font-black text-slate-850 uppercase tracking-widest">Disciplinas Acadêmicas Disponíveis</h3>
                        </div>
                      </div>

                      {/* Interactive Catalog Filter and Sort Strip */}
                      <div className="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 flex flex-col gap-4 text-left">
                        {/* Row 1: Search and Sort Control bar */}
                        <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
                          <div className="flex-1 min-w-0 max-w-md relative">
                            <input
                              type="text"
                              placeholder="Buscar por nome ou categoria do curso..."
                              value={searchQuery}
                              onChange={(e) => setSearchQuery(e.target.value)}
                              className="w-full rounded-xl border border-slate-200 bg-white pl-9 pr-14 py-2 text-xs text-slate-700 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 shadow-2xs"
                            />
                            <div className="absolute left-3 top-2.5 text-slate-400">
                              <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                              </svg>
                            </div>
                            {searchQuery && (
                              <button 
                                type="button"
                                onClick={() => setSearchQuery('')}
                                className="absolute right-3 top-2 text-slate-400 hover:text-slate-600 font-extrabold text-[10px] uppercase bg-slate-100 hover:bg-slate-200 px-1.5 py-0.5 rounded cursor-pointer"
                              >
                                Limpar
                              </button>
                            )}
                          </div>
                          
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest block md:inline">Ordenar por:</span>
                            <div className="relative">
                              <select
                                value={sortType}
                                onChange={(e) => setSortType(e.target.value as any)}
                                className="appearance-none bg-white border border-slate-200 rounded-xl px-4 py-2 pr-9 text-xs font-bold text-slate-700 focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 shadow-2xs cursor-pointer"
                              >
                                <option value="recent">Mais recentes</option>
                                <option value="alphabetical-asc">Ordem alfabética (A-Z)</option>
                                <option value="alphabetical-desc">Ordem alfabética (Z-A)</option>
                              </select>
                              <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                                <ChevronDown className="h-3.5 w-3.5" />
                              </div>
                            </div>
                          </div>
                        </div>

                        {/* Row 2: Category pill buttons with interactive state */}
                        <div className="flex flex-col gap-1.5 pt-3 border-t border-slate-200/55">
                          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-left">Filtrar por Categoria / Área:</span>
                          <div className="flex flex-wrap gap-1.5">
                            {(() => {
                              const activeCourses = enrollableCourses;
                              return (
                                <>
                                  <button
                                    type="button"
                                    onClick={() => {
                                      setSelectedCategory('all');
                                      speakText("Exibindo todas as áreas acadêmicas.");
                                    }}
                                    className={`inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold cursor-pointer transition-colors ${
                                      selectedCategory === 'all'
                                        ? 'bg-[#540D6E] text-white shadow-md font-black'
                                        : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'
                                    }`}
                                  >
                                    <LayoutGrid className="h-3.5 w-3.5" />
                                    <span>Ver Tudo ({activeCourses.length})</span>
                                  </button>
                                  {Array.from(new Set(activeCourses.map(c => c.category))).map(category => {
                                    const count = activeCourses.filter(c => c.category === category).length;
                                    return (
                                      <button
                                        type="button"
                                        key={category}
                                        onClick={() => {
                                          setSelectedCategory(category);
                                          speakText(`Filtrando disciplinas para a área de ${category}`);
                                        }}
                                        className={`inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-bold cursor-pointer transition-colors ${
                                          selectedCategory === category
                                            ? 'bg-teal-600 text-white shadow-md font-black'
                                            : 'bg-white border border-slate-200 text-slate-600 hover:bg-teal-50'
                                        }`}
                                      >
                                        <Tag className="h-3.5 w-3.5" />
                                        <span>{category} ({count})</span>
                                      </button>
                                    );
                                  })}
                                </>
                              );
                            })()}
                          </div>
                        </div>
                      </div>

                      {/* Displaying match counts dynamically */}
                      {(() => {
                        const filtered = enrollableCourses
                          .filter(c => {
                            const matchesSearch = c.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                                                  c.category.toLowerCase().includes(searchQuery.toLowerCase());
                            const matchesCategory = selectedCategory === 'all' || c.category === selectedCategory;
                            return matchesSearch && matchesCategory;
                          })
                          .sort((a, b) => {
                            if (sortType === 'alphabetical-asc') return a.title.localeCompare(b.title, 'pt-BR');
                            if (sortType === 'alphabetical-desc') return b.title.localeCompare(a.title, 'pt-BR');
                            if (sortType === 'recent') return b.id.localeCompare(a.id);
                            return 0;
                          });

                        if (filtered.length === 0) {
                          return (
                            <div className="text-center py-10 bg-slate-50 rounded-2xl border border-slate-200 p-6 space-y-3">
                              <Info className="h-8 w-8 text-slate-400 mx-auto" />
                              <p className="text-sm font-extrabold text-slate-800">Ops! Sem resultados correspondentes</p>
                              <p className="text-xs text-slate-500 max-w-sm mx-auto">
                                Não encontramos nenhuma disciplina letiva que combine com sua busca "{searchQuery}" ou filtros selecionados.
                              </p>
                              <button
                                type="button"
                                onClick={() => {
                                  setSearchQuery('');
                                  setSelectedCategory('all');
                                  setSortType('recent');
                                  speakText("Todos os filtros foram redefinidos para os valores padrão.");
                                }}
                                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition-all cursor-pointer"
                              >
                                Limpar Todos os Filtros
                              </button>
                            </div>
                          );
                        }

                        return (
                          <div className="space-y-4">
                            <div className="text-[11px] font-bold text-slate-400 uppercase tracking-widest text-left flex items-center justify-between">
                              <span>Grade Curricular Disponível para Matrícula:</span>
                              <span className="text-teal-600 font-mono font-black shrink-0">
                                {filtered.length} {filtered.length === 1 ? 'curso encontrado' : 'cursos encontrados'}
                              </span>
                            </div>
                            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                              {filtered.map((course, idx) => {
                                const minAtt = courseMinAttendance(course);
                                return (
                                  <div key={`${course.id}-${idx}`} className="group relative rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition-with-duration hover:shadow-md hover:border-[#540D6E]/30 flex flex-col justify-between text-left animate-in fade-in zoom-in-95 duration-150">
                                    <div className="space-y-3 ms-0.5">
                                      <div className="flex items-center justify-between">
                                        <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500 border border-slate-200 flex items-center gap-1">
                                          <Tag className="h-3 w-3" />
                                          {course.category}
                                        </span>
                                        <span className="text-[10px] text-teal-600 font-bold bg-teal-50 border border-teal-100 px-2 py-0.5 rounded-full shadow-2xs">
                                          Meta: {minAtt}% pres.
                                        </span>
                                      </div>
                                      <div>
                                        <h4 className="text-sm font-black text-slate-950 group-hover:text-[#540D6E] transition-colors line-clamp-1">{course.title}</h4>
                                        <p className="mt-1 text-xs text-slate-500 leading-relaxed line-clamp-2">{course.description}</p>
                                        <div className="flex items-center gap-2 mt-2">
                                          <span className="text-[9px] bg-slate-100 text-slate-600 font-bold px-1.5 py-0.5 rounded-md flex items-center gap-1 border border-slate-150">
                                            <BookOpen className="h-3 w-3" />
                                            {course.lessons ? course.lessons.length : 0} {course.lessons && course.lessons.length === 1 ? 'Aula' : 'Aulas'}
                                          </span>
                                          <span className="text-[9px] bg-slate-100 text-slate-600 font-bold px-1.5 py-0.5 rounded-md flex items-center gap-1 border border-slate-150">
                                            <Video className="h-3 w-3" />
                                            {course.liveSessions ? course.liveSessions.length : 0} {course.liveSessions && course.liveSessions.length === 1 ? 'Sessão Ao Vivo' : 'Sessões'}
                                          </span>
                                        </div>
                                      </div>
                                    </div>

                                    <div className="mt-4 pt-4 border-t border-slate-150 flex items-center justify-between">
                                      <div className="text-[10px] text-slate-500 font-medium">
                                        Prof. <strong className="text-slate-700 font-bold">{course.instructorName}</strong>
                                      </div>
                                      <button
                                        onClick={() => setViewingCatalogCourse(course)}
                                        className="text-[10px] bg-[#540D6E] hover:bg-purple-950 text-white font-black uppercase tracking-widest px-4 py-2.5 rounded-xl transition-colors cursor-pointer flex items-center gap-1.5 select-none shadow-md"
                                      >
                                        <span>Ver e Escolher</span>
                                        <ArrowRight className="h-3 w-3" />
                                      </button>
                                    </div>
                                  </div>
                                );
                              })}
                            </div>
                          </div>
                        );
                      })()}
                    </div>
                  )}

                  {/* Scenario 4: Completed Courses review panel (Always visible if any completed) */}
                  {enrollmentRecord.completedCourseIds && enrollmentRecord.completedCourseIds.length > 0 && (
                    <div className="space-y-4 pt-6 border-t border-slate-200 animate-in fade-in duration-500">
                      <div className="flex items-center gap-2 text-[#540D6E]">
                        <CheckCircle className="h-5 w-5 text-emerald-500" />
                        <h3 className="text-xs font-black uppercase tracking-wider">Cursos Concluídos (Acesso Vitalício de Revisão)</h3>
                      </div>
                      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        {courses
                          .filter(c => enrollmentRecord.completedCourseIds.includes(c.id))
                          .map((course, idx) => (
                            <div 
                              key={`${course.id}-${idx}`}
                              onClick={() => {
                                setSelectedCourse(course);
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                              }}
                              className="group p-4 bg-emerald-50/10 border border-emerald-100 rounded-xl cursor-pointer hover:bg-emerald-50/20 hover:border-emerald-200 transition-all flex flex-col justify-between"
                            >
                              <div className="space-y-1 text-left">
                                <span className="inline-block text-[8px] bg-emerald-100 text-emerald-850 px-1.5 py-0.2 rounded font-black uppercase tracking-wider mb-1">Grade Completa</span>
                                <h4 className="text-xs font-black text-slate-850 group-hover:text-emerald-700 transition-colors block line-clamp-1">{course.title}</h4>
                                <span className="text-[10px] text-slate-400 block">Prof. {course.instructorName}</span>
                              </div>
                              <span className="text-[10px] text-teal-600 hover:underline font-bold mt-3 block text-right font-mono">Modo Revisão →</span>
                            </div>
                          ))}
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
      ) : activeDashboardTab === 'documents' ? (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-4">
          <div className="text-left mb-2">
            <button
              onClick={() => setActiveDashboardTab('general')}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 transition-all cursor-pointer text-xs font-black uppercase tracking-wider border border-slate-200/65"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
              <span>Voltar ao Meu Painel de Estudos</span>
            </button>
          </div>
          <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs text-left max-w-2xl">
            <div className="flex items-center gap-2 mb-2">
              <FileCheck className="h-5 w-5 text-teal-600" />
              <h3 className="font-black text-slate-800 text-sm uppercase tracking-wider">Solicitações de Documentos</h3>
            </div>
            <p className="text-xs text-slate-500 leading-relaxed mb-6">
              Precisa de um documento acadêmico ou comprovante? Abra um requerimento e acompanhe o parecer digital homologado pela coordenação.
            </p>
            
            <div className="space-y-4 mb-8">
              {academicRequests.filter(r => r.userId === activeUser.id).map(req => (
                <div key={req.id} className="p-4 rounded-xl border border-slate-200 bg-slate-50/30 flex flex-col gap-3">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <div className="p-1.5 rounded-lg bg-teal-50 text-teal-600">
                        {req.type === 'certificado' ? <Award className="h-4 w-4" /> : <FileText className="h-4 w-4" />}
                      </div>
                      <span className="text-xs font-black text-slate-800 uppercase">{req.type === 'certificado' ? 'Certificado' : 'Histórico Escolar'}</span>
                    </div>
                    <span className={`text-[10px] uppercase font-black px-2 py-0.5 rounded-full ${
                      req.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'
                    }`}>
                      {req.status === 'pending' ? 'Aguardando' : 'Aprovado'}
                    </span>
                  </div>
                  {req.courseTitle && <p className="text-[11px] font-bold text-slate-600">Curso: {req.courseTitle}</p>}
                  <p className="text-[11px] italic text-slate-500 leading-relaxed">"{req.description}"</p>
                  <span className="text-[9px] font-mono text-slate-400">Protocolo: {req.submittedAt}</span>
                </div>
              ))}
            </div>

            <div className="border-t border-slate-100 pt-6">
                <span className="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-4">Novo Requerimento</span>
                <form onSubmit={(e) => {
                  e.preventDefault();
                  const form = e.currentTarget;
                  const type = (form.elements.namedItem('reqType') as HTMLSelectElement).value as any;
                  const description = (form.elements.namedItem('reqDesc') as HTMLTextAreaElement).value.trim();
                  const courseTitle = (form.elements.namedItem('reqCourse') as HTMLSelectElement).value;

                  if (description) {
                    addAcademicRequest({
                      type,
                      description,
                      courseTitle: courseTitle || undefined
                    });
                    form.reset();
                  }
                }} className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 ml-1">Tipo de Documento</label>
                      <select name="reqType" className="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:outline-none cursor-pointer">
                        <option value="historico">Histórico Escolar</option>
                        <option value="certificado">Certificado de Conclusão</option>
                        <option value="matricula">Declaração de Matrícula</option>
                        <option value="outro">Outros Pedidos</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 ml-1">Curso Relacionado</label>
                      <select name="reqCourse" className="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:outline-none cursor-pointer">
                        <option value="">Nenhum / Geral</option>
                        {courses.map((c, idx) => <option key={`${c.id}-${idx}`} value={c.title}>{c.title}</option>)}
                      </select>
                    </div>
                  </div>
                  <div>
                    <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 ml-1">Motivo / Justificativa</label>
                    <textarea name="reqDesc" required placeholder="Descreva detalhes adicionais ou justificativa para a emissão..." className="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-700 h-20 focus:ring-2 focus:ring-teal-500/20 focus:outline-none resize-none"></textarea>
                  </div>
                  <button type="submit" className="w-full bg-teal-600 hover:bg-teal-700 text-white font-black text-xs uppercase py-3 rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer">
                    <Send className="h-4 w-4" />
                    Protocolar Pedido Secundário
                  </button>
                </form>
            </div>
          </div>
        </div>
      ) : activeDashboardTab === 'library' ? (
        <StudentLibraryPanel onBack={() => setActiveDashboardTab('general')} />
      ) : features.eventosWebinars && activeDashboardTab === 'events' ? (
        <StudentEventsPanel onBack={() => setActiveDashboardTab('general')} />
      ) : activeDashboardTab === 'faq' ? (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-6 text-left max-w-4xl mx-auto">
          <div className="text-left mb-2">
            <button
              onClick={() => setActiveDashboardTab('general')}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 transition-all cursor-pointer text-xs font-black uppercase tracking-wider border border-slate-200/65"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
              <span>Voltar ao Meu Painel de Estudos</span>
            </button>
          </div>
          <div>
            <h3 className="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
              <HelpCircle className="h-5 w-5 text-[#540D6E]" />
              <span>Central de Ajuda & FAQ</span>
            </h3>
            <p className="text-xs text-slate-500 mt-1">Encontre respostas rápidas para dúvidas acadêmicas, regras de frequência, certificados e prazos de contrato.</p>
          </div>

          {/* Search and Filters */}
          <div className="bg-white border border-slate-200 rounded-3xl p-5 md:p-6 shadow-xs space-y-4">
            <div className="relative">
              <input
                type="text"
                placeholder="Pesquise por termos como 'presença', 'certificado', 'vaga', 'cancelar'..."
                value={faqSearchQuery}
                onChange={(e) => setFaqSearchQuery(e.target.value)}
                className="w-full bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-[#540D6E]/10 focus:border-[#540D6E] focus:outline-none rounded-xl py-3 px-4 text-xs font-medium text-slate-800"
              />
            </div>

            {/* Category Tags */}
            <div className="flex gap-2 flex-wrap">
              {[
                { id: 'all', label: 'Tudo' },
                { id: 'academic', label: 'Acadêmico & Presença' },
                { id: 'certificates', label: 'Certificados' },
                { id: 'prazos', label: 'Vigência & Prazos' },
                { id: 'support', label: 'Suporte & Contato' }
              ].map(category => (
                <button
                  key={category.id}
                  onClick={() => setSelectedFaqCategory(category.id)}
                  className={`px-3.5 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer border ${
                    selectedFaqCategory === category.id
                      ? 'bg-[#540D6E] text-white border-transparent'
                      : 'bg-white text-slate-500 border-slate-200 hover:text-slate-800 hover:border-slate-300'
                  }`}
                >
                  {category.label}
                </button>
              ))}
            </div>
          </div>

          {/* FAQ Accordion List */}
          <div className="space-y-3">
            {(() => {
              const lmsFaqs = [
                {
                  id: 'faq-1',
                  category: 'academic',
                  question: 'Como funciona a contabilização de presença?',
                  answer: 'A sua presença é computada de forma 100% automatizada pelo AVA ao longo dos módulos. Ela é calculada através de três ações: (1) participação nas transmissões síncronas ao vivo, (2) conclusão de quizzes rápidos de fixação e (3) confirmação de leitura do material teórico de suporte de cada lição.'
                },
                {
                  id: 'faq-2',
                  category: 'certificates',
                  question: 'Como e quando posso emitir meu certificado?',
                  answer: 'O certificado é liberado quando você cumpre DOIS critérios: a frequência mínima do curso (70% por padrão, mas cada curso pode exigir outro percentual — o valor do seu aparece no painel do curso) e a aprovação em todas as avaliações do curso. Curso sem avaliação depende só da frequência. Cumpridos os critérios, o certificado aparece na seção "Certificados" do seu Perfil, em PDF.'
                },
                {
                  id: 'faq-3',
                  category: 'prazos',
                  question: 'O que acontece se meu curso expirar e for arquivado preventivamente?',
                  answer: 'Se o prazo de vigência de exibição da disciplina terminar, ela será arquivada preventivamente para liberar a vaga letiva de alunos inativos. Você pode simplesmente clicar em "Cancelar inscrição" no seu painel para escolher imediatamente uma nova disciplina do catálogo e recomeçar seus estudos!'
                },
                {
                  id: 'faq-4',
                  category: 'academic',
                  question: 'O que é a Política de Saída Desimpedida (Tolerância Acadêmica de 5 Dias)?',
                  answer: 'É uma garantia acadêmica que permite desistir ou alterar sua disciplina atual nos primeiros 5 dias letivos contados a partir da matrícula. Isso garante que sua ficha escolar permaneça limpa e sem pendências caso queira ajustar sua rota de aprendizado.'
                },
                {
                  id: 'faq-5',
                  category: 'support',
                  question: 'Como tirar dúvidas diretamente com meu professor?',
                  answer: 'Você pode mandar mensagens para o professor responsável a qualquer momento na aba "Mensagens & Suporte" do seu painel. Além disso, as dúvidas conceituais podem ser dirimidas em tempo real no chat interativo durante as transmissões síncronas semanais.'
                },
                {
                  id: 'faq-6',
                  category: 'academic',
                  question: 'Perdi a aula síncrona ao vivo, posso assistir depois?',
                  answer: 'Sim, plenamente! Todas as transmissões e reuniões síncronas semanais são gravadas integralmente e adicionadas à lição correspondente em até 24 horas úteis, permitindo que você estude e revise todo o conteúdo no seu próprio horário.'
                }
              ];

              const filtered = lmsFaqs.filter(faq => {
                const matchesCategory = selectedFaqCategory === 'all' || faq.category === selectedFaqCategory;
                const searchLower = faqSearchQuery.toLowerCase();
                const matchesSearch = faq.question.toLowerCase().includes(searchLower) || faq.answer.toLowerCase().includes(searchLower);
                return matchesCategory && matchesSearch;
              });

              if (filtered.length === 0) {
                return (
                  <div className="bg-white rounded-3xl p-8 text-center border border-slate-200">
                    <p className="text-xs text-slate-500 font-medium">Nenhuma pergunta encontrada para sua pesquisa.</p>
                  </div>
                );
              }

              return filtered.map((faq, idx) => {
                const isExpanded = expandedFaqId === faq.id;
                const toggle = () => setExpandedFaqId(isExpanded ? null : faq.id);

                return (
                  <div key={`${faq.id}-${idx}`} className="bg-white border border-slate-200 rounded-2xl overflow-hidden transition-all hover:border-teal-200">
                    <button
                      onClick={toggle}
                      className="w-full text-left p-4 md:p-5 flex items-center justify-between gap-4 cursor-pointer hover:bg-slate-50/40 transition-colors"
                    >
                      <strong className="text-xs font-bold text-slate-800 leading-snug">{faq.question}</strong>
                      <ChevronDown className={`h-4 w-4 text-slate-400 transition-transform duration-200 shrink-0 ${isExpanded ? 'rotate-180 text-teal-600' : ''}`} />
                    </button>
                    {isExpanded && (
                      <div className="px-5 pb-5 pt-1 text-xs text-slate-500 leading-relaxed bg-slate-50/40 border-t border-slate-100 animate-in fade-in slide-in-from-top-1">
                        <p className="font-medium text-slate-650 whitespace-pre-wrap">{faq.answer}</p>
                      </div>
                    )}
                  </div>
                );
              });
            })()}
          </div>

          {/* Quick Support CTA */}
          <div className="bg-gradient-to-r from-[#540D6E]/5 to-indigo-50 border border-[#540D6E]/10 rounded-3xl p-6 flex flex-col md:flex-row items-center justify-between gap-6">
            <div className="space-y-1 text-center md:text-left">
              <strong className="text-sm font-black text-slate-800 block">Ainda tem dúvidas ou precisa de ajuda técnica?</strong>
              <p className="text-xs text-slate-500 font-medium">Nossa equipe de suporte acadêmico e coordenação está pronta para te atender de forma personalizada.</p>
            </div>
            <button
              onClick={() => {
                setActiveDashboardTab('messages');
                window.scrollTo({ top: 0, behavior: 'smooth' });
              }}
              className="bg-[#540D6E] hover:bg-[#540D6E]/90 text-white font-black text-[10px] uppercase tracking-widest px-6 py-3 rounded-xl transition-all cursor-pointer shadow-md shrink-0 flex items-center gap-2"
            >
              <MessageSquare className="h-4 w-4" />
              <span>Falar com a Equipe</span>
            </button>
          </div>
        </div>
      ) : activeDashboardTab === 'settings' ? (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-6 text-left max-w-4xl mx-auto">
          <div className="text-left mb-2">
            <button
              onClick={() => setActiveDashboardTab('general')}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 transition-all cursor-pointer text-xs font-black uppercase tracking-wider border border-slate-200/65"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
              <span>Voltar ao Meu Painel de Estudos</span>
            </button>
          </div>
           <div className="bg-white border border-slate-200 rounded-3xl p-8 shadow-xs">
              <div className="flex items-center gap-4 mb-8">
                 <div className="h-16 w-16 rounded-2xl bg-[#540D6E] flex items-center justify-center text-white text-2xl font-black">
                   {activeUser.name.charAt(0)}
                 </div>
                 <div>
                    <h3 className="text-xl font-black text-slate-900 leading-none">{activeUser.name}</h3>
                    <p className="text-xs text-slate-500 mt-1.5 uppercase font-bold tracking-widest leading-none">Status: Aluno Ativo • Versão 2.4</p>
                 </div>
              </div>

              <div className="space-y-8">
                 <section>
                    <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                       <Monitor className="h-4 w-4" />
                       Ajustes de Acessibilidade
                    </h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                       <div className="p-5 rounded-2xl border border-slate-100 bg-slate-50/50 flex items-center justify-between group hover:border-teal-200 transition-all">
                          <div className="flex items-center gap-3">
                             <div className={`p-2 rounded-xl border ${accessibilitySettings.highContrast ? 'bg-[#540D6E] text-white border-transparent' : 'bg-white border-slate-200 text-slate-400'}`}>
                                <Sparkles className="h-5 w-5" />
                             </div>
                             <div>
                                <span className="block text-xs font-bold text-slate-800 uppercase tracking-tight">Alto Contraste</span>
                                <span className="text-[10px] text-slate-500">Melhora a legibilidade visual.</span>
                             </div>
                          </div>
                          <button 
                            onClick={() => updateAccessibilitySettings({ highContrast: !accessibilitySettings.highContrast })}
                            className={`w-10 h-5 rounded-full transition-colors relative ${accessibilitySettings.highContrast ? 'bg-emerald-500' : 'bg-slate-300'}`}
                          >
                             <div className={`absolute top-0.5 w-4 h-4 rounded-full bg-white transition-all ${accessibilitySettings.highContrast ? 'left-5.5' : 'left-0.5'}`} />
                          </button>
                       </div>

                       <div className="p-5 rounded-2xl border border-slate-100 bg-slate-50/50 flex items-center justify-between group hover:border-teal-200 transition-all">
                          <div className="flex items-center gap-3">
                             <div className={`p-2 rounded-xl border ${accessibilitySettings.dyslexicFont ? 'bg-[#540D6E] text-white border-transparent' : 'bg-white border-slate-200 text-slate-400'}`}>
                                <Info className="h-5 w-5" />
                             </div>
                             <div>
                                <span className="block text-xs font-bold text-slate-800 uppercase tracking-tight">Fonte para Dislexia</span>
                                <span className="text-[10px] text-slate-500">Usa a fonte OpenDyslexic.</span>
                             </div>
                          </div>
                          <button 
                            onClick={() => updateAccessibilitySettings({ dyslexicFont: !accessibilitySettings.dyslexicFont })}
                            className={`w-10 h-5 rounded-full transition-colors relative ${accessibilitySettings.dyslexicFont ? 'bg-emerald-500' : 'bg-slate-300'}`}
                          >
                             <div className={`absolute top-0.5 w-4 h-4 rounded-full bg-white transition-all ${accessibilitySettings.dyslexicFont ? 'left-5.5' : 'left-0.5'}`} />
                          </button>
                       </div>

                       <div className="p-5 rounded-2xl border border-slate-100 bg-slate-50/50 flex flex-col gap-4 group hover:border-teal-200 transition-all sm:col-span-2">
                          <div className="flex items-center justify-between">
                             <div className="flex items-center gap-3">
                                <div className="p-2 rounded-xl border bg-white border-slate-200 text-slate-400">
                                   <BookMarked className="h-5 w-5" />
                                </div>
                                <div>
                                   <span className="block text-xs font-bold text-slate-800 uppercase tracking-tight">Tamanho da Fonte Global</span>
                                   <span className="text-[10px] text-slate-500">Ajuste o tamanho dos textos de toda a plataforma.</span>
                                </div>
                             </div>
                             <span className="text-[10px] font-black uppercase text-teal-600 bg-teal-50 px-2 py-0.5 rounded tracking-widest">{accessibilitySettings.fontSize === 'small' ? 'Pequena' : accessibilitySettings.fontSize === 'medium' ? 'Padrão' : 'Grande'}</span>
                          </div>
                          <div className="flex items-center gap-3">
                             {['small', 'medium', 'large'].map(size => (
                               <button 
                                 key={size}
                                 onClick={() => updateAccessibilitySettings({ fontSize: size as any })}
                                 className={`flex-1 py-2.5 rounded-xl border text-[10px] font-black uppercase tracking-widest transition-all ${
                                   accessibilitySettings.fontSize === size 
                                     ? 'bg-[#540D6E] text-white border-transparent shadow-md' 
                                     : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'
                                 }`}
                               >
                                 {size === 'small' ? 'A-' : size === 'medium' ? 'AA' : 'A+'}
                               </button>
                             ))}
                          </div>
                       </div>
                    </div>
                 </section>

                 <section>
                    <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                       <User className="h-4 w-4" />
                       Dados da Conta
                    </h4>
                    <div className="space-y-4">
                       <div className="flex items-center justify-between p-4 border border-slate-100 rounded-2xl">
                          <div>
                             <span className="block text-[9px] font-bold text-slate-400 uppercase tracking-tighter">Nome Civil</span>
                             <span className="text-xs font-bold text-slate-700">{activeUser.name}</span>
                          </div>
                       </div>
                       <div className="flex items-center justify-between p-4 border border-slate-100 rounded-2xl bg-slate-50/30">
                          <div>
                             <span className="block text-[9px] font-bold text-slate-400 uppercase tracking-tighter">ID de Aluno (RA)</span>
                             <span className="text-xs font-mono font-bold text-slate-700">#AVA-2026-XQ45</span>
                          </div>
                       </div>
                    </div>
                 </section>
                 <section>
                    <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                       <Bell className="h-4 w-4" />
                       Preferências de Notificação
                    </h4>
                    <div className="space-y-3">
                       {Object.entries(notifications).map(([key, value]) => (
                         <div key={key} className="flex items-center justify-between p-4 border border-slate-100 rounded-2xl bg-white hover:border-teal-100 transition-all">
                            <div className="flex items-center gap-3">
                               <div className="p-2 rounded-xl bg-slate-50 text-slate-400">
                                  {key === 'email' ? <Send className="h-4 w-4" /> : key === 'push' ? <Bell className="h-4 w-4" /> : <Smartphone className="h-4 w-4" />}
                               </div>
                               <div>
                                  <span className="block text-xs font-bold text-slate-800 uppercase tracking-tight">Notificações por {key === 'email' ? 'E-mail' : key === 'push' ? 'Desktop/Push' : 'SMS'}</span>
                                  <span className="text-[10px] text-slate-500">Receba alertas de novas aulas e respostas.</span>
                                </div>
                            </div>
                            <button 
                              onClick={() => setNotifications(prev => ({ ...prev, [key]: !value }))}
                              className={`w-9 h-4.5 rounded-full transition-colors relative ${value ? 'bg-emerald-500' : 'bg-slate-300'}`}
                            >
                               <div className={`absolute top-0.5 w-3.5 h-3.5 rounded-full bg-white transition-all ${value ? 'left-5' : 'left-0.5'}`} />
                            </button>
                         </div>
                       ))}
                    </div>
                 </section>

                 <section>
                    <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                       <Shield className="h-4 w-4" />
                       Segurança & Privacidade
                    </h4>
                    <div className="p-5 rounded-2xl border border-slate-100 bg-teal-50/20 flex items-center justify-between group hover:border-teal-200 transition-all">
                       <div className="flex items-center gap-3">
                          <div className={`p-2 rounded-xl border ${twoFactor ? 'bg-teal-600 text-white border-transparent' : 'bg-white border-slate-200 text-slate-400'}`}>
                             <Lock className="h-5 w-5" />
                          </div>
                          <div>
                             <span className="block text-xs font-bold text-slate-800 uppercase tracking-tight">Autenticação de Dois Fatores (2FA)</span>
                             <span className="text-[10px] text-slate-500">Adicione uma camada extra de proteção na conta.</span>
                          </div>
                       </div>
                       <button 
                         onClick={() => setTwoFactor(!twoFactor)}
                         className={`px-4 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all ${
                           twoFactor ? 'bg-teal-600 text-white' : 'bg-white border border-slate-200 text-slate-500 hover:bg-slate-50'
                         }`}
                       >
                          {twoFactor ? 'Ativado' : 'Ativar'}
                       </button>
                    </div>
                 </section>

                 <section>
                    <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                       <Globe className="h-4 w-4" />
                       Idioma e Região
                    </h4>
                    <div className="p-5 rounded-2xl border border-slate-100 bg-white flex items-center justify-between group hover:border-teal-200 transition-all text-left">
                       <div>
                          <span className="block text-xs font-bold text-slate-800 uppercase tracking-tight">Idioma da Interface</span>
                          <span className="text-[10px] text-slate-500">Altere o idioma global do sistema para navegação.</span>
                       </div>
                       <select 
                         value={language}
                         onChange={(e) => setLanguage(e.target.value)}
                         className="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-700 focus:outline-hidden focus:ring-1 focus:ring-teal-500"
                       >
                          <option>Português (BR)</option>
                          <option>English (US)</option>
                          <option>Español (ES)</option>
                       </select>
                    </div>
                 </section>
              </div>
           </div>
        </div>
      ) : (
        /* Support & Communications workspace - Tab 2 */
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div className="text-left">
              <button
                onClick={() => setActiveDashboardTab('general')}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 transition-all cursor-pointer text-xs font-black uppercase tracking-wider border border-slate-200/65"
              >
                <ArrowLeft className="h-3.5 w-3.5" />
                <span>Voltar ao Meu Painel de Estudos</span>
              </button>
            </div>
            <div className="flex justify-end">
             <button 
               onClick={() => setShowKnowledgeBase(true)}
               className="flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-xl text-xs font-bold text-teal-600 hover:bg-teal-50 transition-all shadow-xs cursor-pointer"
             >
                <HelpCircle className="h-4 w-4" />
                <span>Base de Conhecimento (Tutoriais)</span>
             </button>
            </div>
          </div>

          {/* Knowledge Base Modal */}
          {showKnowledgeBase && (
            <div className="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" onClick={() => setShowKnowledgeBase(false)}>
               <div className="bg-white rounded-3xl border border-slate-200 shadow-2xl overflow-hidden max-w-2xl w-full animate-in zoom-in-95 duration-200 text-left" onClick={e => e.stopPropagation()}>
                  <div className="bg-[#540D6E] p-6 text-white flex items-center justify-between">
                     <div className="flex items-center gap-3">
                        <Notebook className="h-6 w-6" />
                        <div>
                           <h4 className="font-black uppercase tracking-widest text-sm leading-none">Central de Ajuda</h4>
                           <p className="text-[10px] text-white/60 mt-1.5 uppercase font-bold">Autoatendimento Acadêmico</p>
                        </div>
                     </div>
                     <button onClick={() => setShowKnowledgeBase(false)} className="bg-white/10 hover:bg-white/20 p-2 rounded-xl text-[10px] uppercase font-black cursor-pointer">Fechar</button>
                  </div>
                  <div className="p-8 grid grid-cols-1 sm:grid-cols-2 gap-5 bg-slate-50/50">
                     <div className="p-5 border border-slate-200 rounded-2xl bg-white hover:border-teal-300 hover:shadow-lg transition-all cursor-pointer group">
                        <div className="bg-teal-50 p-2.5 rounded-xl w-fit mb-4 group-hover:bg-teal-100 transition-colors">
                           <Video className="h-6 w-6 text-teal-600" />
                        </div>
                        <h5 className="font-black text-slate-800 text-xs uppercase tracking-tight">Primeiros Passos no AVA</h5>
                        <p className="text-[11px] text-slate-500 mt-2 leading-relaxed">Aprenda a estruturar seu cronograma e encontrar materiais de apoio.</p>
                     </div>
                     <div className="p-5 border border-slate-200 rounded-2xl bg-white hover:border-amber-300 hover:shadow-lg transition-all cursor-pointer group">
                        <div className="bg-amber-50 p-2.5 rounded-xl w-fit mb-4 group-hover:bg-amber-100 transition-colors">
                           <Award className="h-6 w-6 text-amber-600" />
                        </div>
                        <h5 className="font-black text-slate-800 text-xs uppercase tracking-tight">Certificação & Presença</h5>
                        <p className="text-[11px] text-slate-500 mt-2 leading-relaxed">Entenda como atingir os 70% de frequência mínima exigida por curso.</p>
                     </div>
                     <div className="p-5 border border-slate-200 rounded-2xl bg-white hover:border-blue-300 hover:shadow-lg transition-all cursor-pointer group">
                        <div className="bg-blue-50 p-2.5 rounded-xl w-fit mb-4 group-hover:bg-blue-100 transition-colors">
                           <MessageSquare className="h-6 w-6 text-blue-600" />
                        </div>
                        <h5 className="font-black text-slate-800 text-xs uppercase tracking-tight">Suporte às Vagas</h5>
                        <p className="text-[11px] text-slate-500 mt-2 leading-relaxed">Dicas de como usar seu certificado para se destacar em processos seletivos.</p>
                     </div>
                     <div className="p-5 border border-slate-200 rounded-2xl bg-white hover:border-indigo-300 hover:shadow-lg transition-all cursor-pointer group">
                        <div className="bg-indigo-50 p-2.5 rounded-xl w-fit mb-4 group-hover:bg-indigo-100 transition-colors">
                           <HelpCircle className="h-6 w-6 text-indigo-600" />
                        </div>
                        <h5 className="font-black text-slate-800 text-xs uppercase tracking-tight">Chat de Suporte Direto</h5>
                        <p className="text-[11px] text-slate-500 mt-2 leading-relaxed">Vídeo tutorial sobre como usar o chat direto com coordenadores.</p>
                     </div>
                  </div>
                  <div className="p-6 border-t border-slate-200 bg-white text-center">
                     <p className="text-[10px] text-slate-400 font-medium">Ainda com dúvidas? Envie uma mensagem direta na aba de suporte abaixo.</p>
                  </div>
               </div>
            </div>
          )}

          <div className="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-xs space-y-6 text-left">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-5">
            <div className="space-y-1">
              <span className="text-[10px] font-extrabold text-[#540D6E] uppercase tracking-wider font-mono">Central de Atendimento</span>
              <h3 className="text-xl md:text-2xl font-black text-slate-900 uppercase tracking-tight">Canal Direto com Professores</h3>
              <p className="text-xs text-slate-500 font-medium">Tire dúvidas técnicas, receba correções de código e feedbacks individuais de estudos.</p>
            </div>
            
            {/* Minimal metadata information cards badge styles */}
            <div className="flex gap-2 shrink-0">
              <div className="bg-slate-50 border border-slate-200 px-3 py-2 rounded-xl flex items-center gap-2 text-xs text-slate-700">
                <Clock className="h-4 w-4 text-teal-600 shrink-0" />
                <div>
                  <span className="block text-[8px] text-slate-400 font-bold uppercase leading-none">Tempo de Retorno</span>
                  <span className="font-bold text-[10.5px]">~15 minutos</span>
                </div>
              </div>
              <div className="bg-slate-50 border border-slate-200 px-3 py-2 rounded-xl flex items-center gap-2 text-xs text-slate-700">
                <div className="relative">
                  <User className="h-4 w-4 text-teal-600 shrink-0" />
                  <span className={`absolute -bottom-1 -right-1 block h-2.5 w-2.5 rounded-full border border-white ${
                    (localStorage.getItem(`ava_presence_status_${enrolledCourseInstructorId}`) || 'online') === 'online'
                      ? 'bg-emerald-500 animate-pulse'
                      : 'bg-slate-400'
                  }`} />
                </div>
                <div>
                  <span className="block text-[8px] text-slate-400 font-bold uppercase leading-none">Gestor Responsável</span>
                  <span className="font-bold text-[10.5px] flex items-center gap-1.5 leading-none mt-0.5">
                    <span>Gestor de Conteúdos</span>
                    <span className={`text-[9px] font-black ${
                      (localStorage.getItem(`ava_presence_status_${enrolledCourseInstructorId}`) || 'online') === 'online'
                        ? 'text-emerald-600'
                        : 'text-slate-500'
                    }`}>
                      ({(localStorage.getItem(`ava_presence_status_${enrolledCourseInstructorId}`) || 'online') === 'online' ? 'Online' : 'Offline'})
                    </span>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {/* The Chat Area (8 cols) */}
            <div id="chat-portal-section" className="lg:col-span-8 rounded-2xl border border-slate-200 bg-slate-50/50 p-5 shadow-3xs flex flex-col h-[480px]">
              {/* Message history */}
              <div className="flex-1 space-y-3 overflow-y-auto pr-2 mb-4 flex flex-col gap-1.5 scrollbar-thin">
                {directMessages.filter(m => m.studentUserId === activeUser.id).length === 0 ? (
                  <div className="flex-1 flex flex-col items-center justify-center text-slate-400 space-y-2 py-10">
                    <MessageSquare className="h-10 w-10 text-slate-300 animate-pulse" />
                    <p className="text-xs font-bold text-slate-500">Nenhuma conversa ativa no momento.</p>
                    <p className="text-[10px] text-slate-400 max-w-[280px] text-center leading-relaxed">Envie uma mensagem abaixo para abrir seu canal direto de tutoria acadêmica!</p>
                  </div>
                ) : (
                  directMessages
                    .filter(m => m.studentUserId === activeUser.id)
                    .map((msg, idx) => {
                      const isStudent = msg.senderRole === 'student';
                      const { aula, corpo } = assuntoDaMensagem(msg.text);
                      return (
                        <div key={`${msg.id}-${idx}`} className={`flex flex-col ${isStudent ? 'items-end' : 'items-start'} animate-in fade-in slide-in-from-bottom-1 duration-200`}>
                          <div className={`max-w-[75%] rounded-2xl px-4 py-2.5 text-xs leading-normal ${
                            isStudent 
                              ? 'bg-teal-600 text-white rounded-tr-none shadow-3xs' 
                              : 'bg-white border border-slate-200 text-slate-800 rounded-tl-none shadow-3xs'
                          }`}>
                            <div className="flex items-center gap-1.5 mb-1 opacity-75">
                              <span className="font-extrabold text-[9px] uppercase tracking-wide">{msg.senderName}</span>
                              <span className="text-[8px] font-mono">• {msg.senderRole === 'student' ? 'Estudante' : 'Professor'}</span>
                            </div>
                            {aula !== null && (
                              <span className={`mb-1.5 flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wide ${
                                isStudent ? 'bg-white/20 text-white' : 'bg-amber-50 text-amber-800 border border-amber-200'
                              }`}>
                                <BookOpen className="h-3 w-3 shrink-0" />
                                <span className="truncate">Aula: {aula}</span>
                              </span>
                            )}
                            <p className="whitespace-pre-line text-[11.5px] font-sans leading-relaxed break-words">{corpo}</p>
                          </div>
                          <span className="text-[8px] text-slate-400 mt-1 px-1 font-mono">
                            {new Date(msg.timestamp).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                          </span>
                        </div>
                      );
                    })
                )}
              </div>

              {/*
                A RESPOSTA AUTOMÁTICA SIMULADA SAIU DAQUI.

                Havia um `setTimeout` que, 1,8 s depois do envio, escrevia no
                `localStorage` uma resposta sorteada de três frases prontas,
                assinada "Gestor de Conteúdos" e com papel `instructor` — sem
                passar pela API, então o professor nunca via a conversa que o
                aluno via. Uma das frases chamava a pessoa de "João", fosse quem
                fosse.

                Era tolerável enquanto o canal estava desligado e nada saía da
                máquina. Com `mensagensDiretas` LIGADA passa a ser dano: o aluno
                pergunta sobre uma aula, lê "vou abordar isso no encerramento da
                transmissão de hoje" e espera por uma resposta que ninguém deu.
              */}
              <form onSubmit={async (e) => {
                e.preventDefault();
                const input = (e.currentTarget.elements.namedItem('messageText') as HTMLInputElement);
                const text = input.value.trim();
                if (!text) return;

                // Só limpa o campo depois do aceite: recusa com campo vazio faria
                // a pessoa reescrever a mensagem inteira.
                const r = await sendDirectMessage(activeUser.id, text);
                if (!r.ok) {
                  showAlert(r.error ?? 'Não foi possível enviar a mensagem.');

                  return;
                }
                input.value = '';
              }} className="flex gap-2">
                <input
                  name="messageText"
                  type="text"
                  required
                  placeholder="Digite sua mensagem ao Gestor de Conteúdos..."
                  className="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-700 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all shadow-3xs"
                />
                <button
                  type="submit"
                  className="bg-teal-600 hover:bg-teal-500 text-white rounded-xl px-4 py-3 shrink-0 transition-colors flex items-center justify-center cursor-pointer shadow-sm text-xs font-black uppercase tracking-wider gap-1.5"
                >
                  <Send className="h-4 w-4" />
                  <span>Enviar</span>
                </button>
              </form>
            </div>

            {/* Explanatory Academic Sideboard (4 cols) */}
            <div className="lg:col-span-4 space-y-4">
              <div className="bg-teal-950/20 border border-teal-500/15 p-5 rounded-2xl text-left space-y-2.5">
                <span className="text-[9px] uppercase tracking-widest text-teal-600 font-extrabold font-mono block">DIRETRIZES DE SUPORTE</span>
                <h4 className="font-bold text-slate-800 text-xs">O que falar no canal com os professores?</h4>
                <ul className="space-y-1.5 text-[11px] text-slate-600 leading-relaxed list-disc list-inside">
                  <li>Envio de snippets ou feedback de códigos;</li>
                  <li>Revisões de conceitos teóricos dos módulos;</li>
                  <li>Presença acadêmica e cronograma síncrono.</li>
                </ul>
              </div>

              {/*
                Havia dois e-mails aqui: `faleconosco@paulo-freire.org.br` e
                `suporte-digital@freire.com`. Os dois eram INVENTADOS — nenhum
                existe — e um aluno que escrevesse para eles acharia que pediu
                ajuda sem ter pedido.

                Decisao da coordenacao (10/09/2026): tirar da tela em vez de
                trocar por outro endereco plausivel. Nao ha canal falso; ficam
                os que funcionam de fato, e o principal e a mensagem ao gestor,
                na aba Suporte de dentro da aula.
              */}
              <div className="bg-slate-50 border border-slate-200 p-5 rounded-2xl text-left space-y-2.5">
                <span className="text-[9px] uppercase tracking-widest text-[#540D6E] font-extrabold font-mono block">COMO PEDIR AJUDA</span>
                <p className="text-[11px] text-slate-500 leading-relaxed">
                  Use a aba <strong>Suporte</strong> dentro da aula: a mensagem chega à coordenação
                  já indicando de qual aula é a dúvida.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    )}

      {/* Floating Help / FAQ Button - Present on ALL tabs/screens except when on the FAQ tab itself */}
      {!isFaqDrawerOpen && activeDashboardTab !== 'faq' && (
        <div className="fixed bottom-6 right-6 z-40 md:bottom-8 md:right-8 flex flex-col items-end">
          <button
            onClick={() => setIsFaqDrawerOpen(true)}
            className="bg-[#540D6E] hover:bg-[#430a58] text-white font-black rounded-full transition-all cursor-pointer shadow-lg hover:shadow-xl hover:-translate-y-0.5 flex items-center justify-center gap-2.5 p-3.5 sm:px-5 sm:py-3.5 active:scale-95 select-none relative"
            title="Central de Ajuda & FAQ"
          >
            {/* Subtle live pulse wave */}
            <span className="absolute -top-1 -right-1 flex h-4 w-4">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-4 w-4 bg-teal-500 justify-center items-center text-[8px] font-black text-white">?</span>
            </span>
            <HelpCircle className="h-5 w-5 sm:h-4.5 sm:w-4.5" />
            <span className="hidden sm:inline-block text-[11px] font-black uppercase tracking-widest text-slate-100">
              Dúvidas & FAQ
            </span>
          </button>
        </div>
      )}

      {/* Global Slide-over Help & FAQ Drawer */}
      {isFaqDrawerOpen && (
        <>
          {/* Backdrop */}
          <div 
            className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 transition-opacity animate-in fade-in duration-300"
            onClick={() => setIsFaqDrawerOpen(false)}
          />
          
          {/* Drawer Panel */}
          <div className="fixed top-0 right-0 h-full w-full sm:w-[500px] bg-slate-50 shadow-2xl z-50 flex flex-col transition-transform animate-in slide-in-from-right duration-300 text-left">
            {/* Header */}
            <div className="p-5 md:p-6 bg-white border-b border-slate-200 flex items-center justify-between shrink-0">
              <div className="flex items-center gap-3">
                <div className="p-2.5 bg-[#540D6E]/10 rounded-xl text-[#540D6E]">
                  <HelpCircle className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-sm font-black text-slate-900 uppercase tracking-wider">Central de Ajuda & FAQ</h3>
                  <p className="text-[10px] text-slate-400 font-bold uppercase mt-0.5 tracking-wider">Suporte e Respostas Rápidas</p>
                </div>
              </div>
              <button
                onClick={() => setIsFaqDrawerOpen(false)}
                className="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Scrollable Content */}
            <div className="flex-1 overflow-y-auto p-5 md:p-6 space-y-6 no-scrollbar">
              {/* Informative Banner */}
              <div className="bg-gradient-to-r from-teal-600 to-teal-700 text-white p-4.5 rounded-2xl shadow-sm space-y-1.5 text-left relative overflow-hidden">
                <div className="absolute top-0 right-0 -mt-4 -mr-4 w-20 h-20 bg-white/10 rounded-full blur-xl pointer-events-none" />
                <span className="inline-block text-[8px] bg-teal-500/50 text-white border border-teal-400/40 px-2 py-0.5 rounded-full font-black uppercase tracking-wider">Atendimento Imediato</span>
                <strong className="block text-xs font-black tracking-tight mt-1">Dúvidas Acadêmicas e Administrativas</strong>
                <p className="text-[10.5px] text-teal-100/90 leading-relaxed font-medium">
                  Nosso sistema oferece respostas 100% automatizadas para facilitar seu andamento no AVA. Caso precise de acompanhamento humano, use o botão de suporte no rodapé!
                </p>
              </div>

              {/* Search Box */}
              <div className="space-y-2">
                <span className="text-[9px] uppercase tracking-wider font-black text-slate-400">O que você está procurando?</span>
                <div className="relative">
                  <input
                    type="text"
                    placeholder="Pesquise por presença, certificado, vigência, cancelamento..."
                    value={faqSearchQuery}
                    onChange={(e) => setFaqSearchQuery(e.target.value)}
                    className="w-full bg-white border border-slate-200 focus:ring-2 focus:ring-[#540D6E]/10 focus:border-[#540D6E] focus:outline-none rounded-xl py-3 px-4 text-xs font-medium text-slate-800 shadow-3xs"
                  />
                </div>
              </div>

              {/* Category Tags */}
              <div className="space-y-2">
                <span className="text-[9px] uppercase tracking-wider font-black text-slate-400">Categorias de Suporte</span>
                <div className="flex gap-1.5 flex-wrap">
                  {[
                    { id: 'all', label: 'Tudo' },
                    { id: 'academic', label: 'Presença & Aulas' },
                    { id: 'certificates', label: 'Certificados' },
                    { id: 'prazos', label: 'Vigência e Contrato' },
                    { id: 'support', label: 'Suporte Técnico' }
                  ].map(category => (
                    <button
                      key={category.id}
                      onClick={() => setSelectedFaqCategory(category.id)}
                      className={`px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-wider transition-all cursor-pointer border ${
                        selectedFaqCategory === category.id
                          ? 'bg-[#540D6E] text-white border-transparent shadow-3xs'
                          : 'bg-white text-slate-500 border-slate-200 hover:text-slate-800 hover:border-slate-300'
                      }`}
                    >
                      {category.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* Accordion list */}
              <div className="space-y-3">
                {(() => {
                  const lmsFaqs = [
                    {
                      id: 'faq-1',
                      category: 'academic',
                      question: 'Como funciona a contabilização de presença?',
                      answer: 'A sua presença é computada de forma 100% automatizada pelo AVA ao longo dos módulos. Ela é calculada através de três ações combinadas: (1) participação nas transmissões síncronas semanais ao vivo, (2) conclusão de mini-quizzes rápidos de fixação ao fim de cada módulo e (3) confirmação de leitura do material teórico de suporte de cada lição.'
                    },
                    {
                      id: 'faq-2',
                      category: 'certificates',
                      question: 'Como e quando posso emitir meu certificado?',
                      answer: 'O certificado é liberado quando você cumpre DOIS critérios: a frequência mínima do curso (70% por padrão, mas cada curso pode exigir outro percentual — o valor do seu aparece no painel do curso) e a aprovação em todas as avaliações do curso. Curso sem avaliação depende só da frequência. Cumpridos os critérios, o certificado aparece na seção "Certificados" do seu Perfil, em PDF.'
                    },
                    {
                      id: 'faq-3',
                      category: 'prazos',
                      question: 'O que acontece se meu curso expirar e for arquivado preventivamente?',
                      answer: 'Se o prazo de vigência de exibição da disciplina terminar, ela será arquivada preventivamente para liberar a vaga letiva de alunos inativos. Você pode simplesmente clicar em "Cancelar inscrição" no seu painel para escolher imediatamente uma nova disciplina do catálogo e recomeçar seus estudos!'
                    },
                    {
                      id: 'faq-4',
                      category: 'academic',
                      question: 'O que é a Política de Saída Desimpedida (Tolerância Acadêmica de 5 Dias)?',
                      answer: 'É uma garantia acadêmica que permite desistir ou alterar sua disciplina atual nos primeiros 5 dias letivos contados a partir da matrícula. Isso garante que sua ficha escolar permaneça limpa e sem pendências caso queira ajustar sua rota de aprendizado.'
                    },
                    {
                      id: 'faq-5',
                      category: 'support',
                      question: 'Como tirar dúvidas diretamente com meu professor?',
                      answer: 'Você pode mandar mensagens para o professor responsável a qualquer momento na aba "Mensagens & Suporte" do seu painel. Além disso, as dúvidas conceituais podem ser dirimidas em tempo real no chat interativo durante as transmissões síncronas semanais.'
                    },
                    {
                      id: 'faq-6',
                      category: 'academic',
                      question: 'Perdi a aula síncrona ao vivo, posso assistir depois?',
                      answer: 'Sim, plenamente! Todas as transmissões e reuniões síncronas semanais são gravadas integralmente e adicionadas à lição correspondente em até 24 horas úteis, permitindo que você estude e revise todo o conteúdo no seu próprio horário.'
                    },
                    {
                      id: 'faq-7',
                      category: 'support',
                      question: 'Estou enfrentando problemas técnicos com o vídeo ou questionários. O que fazer?',
                      answer: 'Caso algum vídeo ou quiz apresente instabilidade, tente primeiro atualizar a página. Caso o erro persista, você pode limpar os arquivos temporários do navegador (cache) ou abrir um chamado técnico clicando no botão de contato direto no rodapé deste painel.'
                    }
                  ];

                  const filtered = lmsFaqs.filter(faq => {
                    const matchesCategory = selectedFaqCategory === 'all' || faq.category === selectedFaqCategory;
                    const searchLower = faqSearchQuery.toLowerCase();
                    const matchesSearch = faq.question.toLowerCase().includes(searchLower) || faq.answer.toLowerCase().includes(searchLower);
                    return matchesCategory && matchesSearch;
                  });

                  if (filtered.length === 0) {
                    return (
                      <div className="bg-white rounded-2xl p-8 text-center border border-slate-200">
                        <HelpCircle className="h-8 w-8 text-slate-300 mx-auto mb-2 animate-pulse" />
                        <p className="text-xs text-slate-500 font-medium">Nenhuma dúvida encontrada para sua pesquisa.</p>
                      </div>
                    );
                  }

                  return filtered.map((faq, idx) => {
                    const isExpanded = expandedFaqId === faq.id;
                    const toggle = () => setExpandedFaqId(isExpanded ? null : faq.id);

                    return (
                      <div key={`${faq.id}-${idx}`} className="bg-white border border-slate-200 rounded-xl overflow-hidden transition-all hover:border-teal-200">
                        <button
                          onClick={toggle}
                          className="w-full text-left p-4 flex items-center justify-between gap-3 cursor-pointer hover:bg-slate-50/40 transition-colors"
                        >
                          <strong className="text-xs font-bold text-slate-850 leading-snug">{faq.question}</strong>
                          <ChevronDown className={`h-4 w-4 text-slate-400 transition-transform duration-200 shrink-0 ${isExpanded ? 'rotate-180 text-teal-600' : ''}`} />
                        </button>
                        {isExpanded && (
                          <div className="px-4 pb-4 pt-1 text-[11px] text-slate-500 leading-relaxed bg-slate-50/40 border-t border-slate-100 animate-in fade-in slide-in-from-top-1">
                            <p className="font-medium text-slate-600 whitespace-pre-wrap">{faq.answer}</p>
                          </div>
                        )}
                      </div>
                    );
                  });
                })()}
              </div>
            </div>

            {/* Sticky Footer CTA */}
            <div className="p-5 md:p-6 bg-white border-t border-slate-200 shrink-0">
              <div className="flex items-center justify-between gap-4">
                <div className="text-left">
                  <strong className="text-xs font-black text-slate-800 block">Não encontrou o que precisava?</strong>
                  <span className="text-[10px] text-slate-400 font-bold block mt-0.5">Fale diretamente com nossa coordenação</span>
                </div>
                <button
                  onClick={() => {
                    setIsFaqDrawerOpen(false);
                    setActiveDashboardTab('messages');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                  }}
                  className="bg-[#540D6E] hover:bg-[#430a58] text-white font-black text-[10px] uppercase tracking-widest px-4 py-2.5 rounded-xl transition-all cursor-pointer shadow-md flex items-center gap-1.5"
                >
                  <MessageSquare className="h-3.5 w-3.5" />
                  <span>Suporte</span>
                </button>
              </div>
            </div>
          </div>
        </>
      )}

      {/* Live Classroom modal overlay (controlado pela feature flag) */}
      {features.liveClassroom && selectedCourse && activeLiveSession && (
        <LiveClassroom
          course={selectedCourse}
          session={activeLiveSession}
          onClose={() => {
            setActiveLiveSession(null);
          }}
        />
      )}

      {/* Full Grade / Curriculum Modal Overlay */}
      {isFullSyllabusOpen && (
        <>
          {/* Backdrop */}
          <div 
            className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 transition-opacity animate-in fade-in duration-300"
            onClick={() => setIsFullSyllabusOpen(false)}
          />
          
          {/* Modal Container */}
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl overflow-hidden animate-in zoom-in-95 duration-250 text-left">
              {/* Header */}
              <div className="p-5 md:p-6 border-b border-slate-100 flex items-center justify-between shrink-0">
                <div className="flex items-center gap-3">
                  <div className="p-2.5 bg-teal-50 rounded-xl text-teal-700">
                    <Layers className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="text-sm font-black text-slate-900 uppercase tracking-wider">Grade Curricular Completa</h3>
                    <p className="text-[10px] text-slate-400 font-bold uppercase mt-0.5 tracking-wider">Detalhamento Pedagógico Completo</p>
                  </div>
                </div>
                <button
                  onClick={() => setIsFullSyllabusOpen(false)}
                  className="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/*
                Aqui havia uma EMENTA INTEIRA INVENTADA: quatro modulos fixos
                ("Modulo 1: Introducao & Conceitos Iniciais", "5 horas"), com
                nomes de aula proprios ("Aula 1.1: Boas-vindas e Configuracao de
                Perfil"), objetivos de aprendizagem e pre-requisitos — os MESMOS
                para todos os cursos, escritos neste arquivo.

                Era o pior caso da familia: texto de vitrine, lido por quem
                decide se se matricula numa escola publica, descrevendo um curso
                que nao e o que a pessoa esta olhando.

                No lugar, a grade REAL: as aulas cadastradas, na ordem do gestor.
                O catalogo e publico e nao recebe `lesson.content` (o material
                sai zerado por escopo), entao nao ha o que dizer de cada aula
                alem do titulo e da duracao — e e so isso que se diz.
              */}
              <div className="p-6 overflow-y-auto space-y-5 flex-1 max-h-[60vh] no-scrollbar">
                {(() => {
                  const aulas = [...(viewingCatalogCourse?.lessons ?? [])].sort((a, b) => a.order - b.order);

                  if (aulas.length === 0) {
                    // Curso sem aula diz que nao tem aula, em vez de exibir uma
                    // ementa de quatro modulos que ninguem cadastrou.
                    return (
                      <p className="text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-xl p-4 font-semibold">
                        Este curso ainda não tem aulas cadastradas.
                      </p>
                    );
                  }

                  return (
                    <>
                      <div className="bg-teal-50/50 border border-teal-100 rounded-xl p-4 text-xs font-medium text-teal-900 leading-relaxed">
                        <Lightbulb className="h-3.5 w-3.5 inline-block mr-1 -mt-0.5 text-teal-700" />
                        <strong>Grade do curso:</strong> {aulas.length} {aulas.length === 1 ? 'aula cadastrada' : 'aulas cadastradas'}.
                        {' '}O conteúdo de cada aula fica disponível após a matrícula.
                      </div>

                      <div className="space-y-2.5">
                        {aulas.map((aula, idx) => (
                          <div
                            key={aula.id}
                            className="border border-slate-200 rounded-xl p-3.5 bg-white flex items-start justify-between gap-3"
                          >
                            <span className="block text-xs font-bold text-slate-800 leading-snug min-w-0">
                              {idx + 1}. {aula.title}
                            </span>
                            <span className="text-[9px] font-mono font-bold bg-slate-100 text-slate-600 px-2 py-0.5 rounded border border-slate-150 shrink-0 whitespace-nowrap">
                              <Clock className="h-2.5 w-2.5 inline-block mr-1 -mt-px" />
                              {(aula.duration ?? '').trim() !== '' ? aula.duration : 'a definir'}
                            </span>
                          </div>
                        ))}
                      </div>
                    </>
                  );
                })()}
              </div>

              {/* Sticky Footer */}
              <div className="p-4 bg-slate-50 border-t border-slate-100 text-right shrink-0">
                <button
                  onClick={() => setIsFullSyllabusOpen(false)}
                  className="bg-[#540D6E] hover:bg-[#430a58] text-white font-black text-[10px] uppercase tracking-widest px-5 py-2.5 rounded-xl transition-all cursor-pointer shadow-xs"
                >
                  Fechar Grade Completa
                </button>
              </div>
            </div>
          </div>
        </>
      )}

      {/* Enrollment Confirmation Modal Overlay */}
      {isEnrollModalOpen && (
        <>
          {/* Backdrop */}
          <div 
            className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 transition-opacity animate-in fade-in duration-300"
            onClick={() => {
              if (!enrollSuccessMessage) {
                setIsEnrollModalOpen(false);
              }
            }}
          />
          
          {/* Modal Container */}
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden animate-in zoom-in-95 duration-250 text-left">
              {!enrollSuccessMessage ? (
                <>
                  {/* Step 1: Confirmation Form */}
                  <div className="p-5 md:p-6 border-b border-slate-100 flex items-center gap-3">
                    <div className="p-2.5 bg-[#540D6E]/10 rounded-xl text-[#540D6E]">
                      <FileText className="h-5 w-5" />
                    </div>
                    <div>
                      <h3 className="text-sm font-black text-slate-900 uppercase tracking-wider">Antes de concluir sua matrícula</h3>
                      <p className="text-[10px] text-slate-400 font-bold uppercase mt-0.5 tracking-wider">Regulamento Acadêmico</p>
                    </div>
                  </div>

                  <div className="p-5 md:p-6 space-y-4">
                    <p className="text-xs text-slate-600 font-semibold">
                      Confira as regras principais antes de confirmar sua inscrição no curso:
                    </p>

                    <div className="space-y-2.5">
                      {[
                        "Para emissão do certificado, é necessário cumprir a frequência mínima do curso (70% por padrão) e ser aprovado em todas as avaliações do curso.",
                        "O aluno deve acompanhar as aulas e realizar as atividades obrigatórias, quando houver.",
                        "Após a confirmação, o curso ficará disponível para início imediato.",
                        "O certificado será liberado conforme os critérios de conclusão do curso."
                      ].map((item, idx) => (
                        <div key={idx} className="flex gap-2 text-[11px] text-slate-650 font-semibold items-start">
                          <CheckCircle className="h-4 w-4 text-teal-600 mt-0.5 shrink-0" />
                          <span>{item}</span>
                        </div>
                      ))}
                    </div>

                    <label className="flex items-start gap-3 bg-slate-50 border border-slate-200 rounded-xl p-3.5 select-none cursor-pointer hover:bg-slate-100/50 transition-all mt-4">
                      <input
                        type="checkbox"
                        checked={isEnrollRulesChecked}
                        onChange={(e) => setIsEnrollRulesChecked(e.target.checked)}
                        className="accent-[#540D6E] h-4.5 w-4.5 mt-0.5 cursor-pointer rounded-md"
                      />
                      <div className="text-left">
                        <strong className="block text-xs font-bold text-slate-800 leading-tight">Termo de Ciência</strong>
                        <p className="text-[10.5px] text-slate-500 leading-normal mt-0.5 font-bold">
                          Li e estou ciente das regras para matrícula e certificação.
                        </p>
                      </div>
                    </label>
                  </div>

                  {/* Actions */}
                  <div className="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button
                      onClick={() => setIsEnrollModalOpen(false)}
                      className="px-4 py-2 bg-white hover:bg-slate-100 text-slate-500 hover:text-slate-800 rounded-xl text-xs font-bold border border-slate-200 transition-all cursor-pointer"
                    >
                      Cancelar
                    </button>
                    <button
                      disabled={!isEnrollRulesChecked}
                      onClick={async () => {
                        // O servidor valida penalidade ativa e matrícula duplicada.
                        const result = await enrollStudentInCourse(activeUser.id, viewingCatalogCourse!.id);
                        if (!result.ok) {
                          showAlert(result.error || 'Não foi possível efetuar a matrícula.');
                          return;
                        }
                        speakText("Matrícula realizada com sucesso!");
                        setEnrollSuccessMessage("Matrícula realizada com sucesso. Você já pode iniciar seus estudos.");
                      }}
                      className={`px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-1 cursor-pointer ${
                        isEnrollRulesChecked
                          ? 'bg-teal-600 hover:bg-teal-500 text-white shadow-md'
                          : 'bg-slate-200 text-slate-400 border-slate-300 cursor-not-allowed'
                      }`}
                    >
                      Confirmar matrícula
                    </button>
                  </div>
                </>
              ) : (
                <>
                  {/* Step 2: Success State */}
                  <div className="p-6 md:p-8 text-center space-y-4">
                    <div className="mx-auto h-12 w-12 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center animate-bounce">
                      <Award className="h-6 w-6" />
                    </div>
                    <div className="space-y-1.5">
                      <h3 className="text-base font-black text-slate-900 uppercase tracking-wider">Sucesso!</h3>
                      <p className="text-xs text-slate-600 font-bold leading-relaxed">
                        {enrollSuccessMessage}
                      </p>
                    </div>
                    <button
                      onClick={() => {
                        // Sai da vitrine e entra no curso numa navegação só: o
                        // `setViewingCatalogCourse(null)` seguinte partiria do
                        // mesmo destino e voltaria para o painel.
                        irPara({
                          tela: 'curso',
                          cursoRef: refDoCurso(viewingCatalogCourse),
                          catalogoId: null,
                        });
                        setIsEnrollModalOpen(false);
                        setEnrollSuccessMessage(null);
                        setIsEnrollRulesChecked(false);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                      }}
                      className="w-full bg-[#540D6E] hover:bg-[#430a58] text-white font-black text-xs uppercase tracking-widest py-3 rounded-xl transition-all shadow-md"
                    >
                      Começar curso
                    </button>
                  </div>
                </>
              )}
            </div>
          </div>
        </>
      )}

      {/* Interactive Quiz / Test Modal Overlay */}

      {/* Custom Alert Modal */}
      <AnimatePresence>
        {alertState && alertState.show && (
          <div className="fixed inset-0 z-[999] flex items-center justify-center p-4">
            <div 
              className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
              onClick={() => setAlertState(null)}
            />
            <motion.div 
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-150 text-center space-y-4 relative z-10"
            >
              <div className="h-12 w-12 rounded-full bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 mx-auto">
                <Info className="h-6 w-6" />
              </div>
              <div className="space-y-1">
                <h4 className="text-sm font-black text-slate-900 font-serif">Aviso do Sistema</h4>
                <p className="text-xs text-slate-500 leading-relaxed font-light">
                  {alertState.message}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setAlertState(null)}
                className="w-full py-2 bg-[#540D6E] hover:bg-purple-950 text-white font-black text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer"
              >
                Entendi
              </button>
            </motion.div>
          </div>
        )}
      </AnimatePresence>

      {/* Custom Confirm Modal */}
      <AnimatePresence>
        {confirmState && confirmState.show && (
          <div className="fixed inset-0 z-[999] flex items-center justify-center p-4">
            <div 
              className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
              onClick={() => setConfirmState(null)}
            />
            <motion.div 
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-150 text-center space-y-4 relative z-10"
            >
              <div className="h-12 w-12 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 mx-auto">
                <HelpCircle className="h-6 w-6" />
              </div>
              <div className="space-y-1">
                <h4 className="text-sm font-black text-slate-900 font-serif">Confirmar Ação</h4>
                <p className="text-xs text-slate-500 leading-relaxed font-light">
                  {confirmState.message}
                </p>
              </div>
              <div className="grid grid-cols-2 gap-3.5 pt-2">
                <button
                  type="button"
                  onClick={() => setConfirmState(null)}
                  className="py-2 bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer"
                >
                  Cancelar
                </button>
                <button
                  type="button"
                  onClick={() => {
                    confirmState.onConfirm();
                    setConfirmState(null);
                  }}
                  className="py-2 bg-[#540D6E] hover:bg-purple-950 text-white font-black text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer"
                >
                  Confirmar
                </button>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </div>
  );
};
