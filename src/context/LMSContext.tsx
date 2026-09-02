/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { createContext, useContext, useState, useEffect, useMemo } from 'react';
import { Course, StudentProgress, Certificate, ChatMessage, DirectMessage, Quiz, QuizQuestion, QuizSubmission, AcademicRequest, LibraryItem, WebinarEvent, AccessibilitySettings, AdmissionRequest, SecurityLog, StudentEnrollment, ForumMessage, Lesson, PracticalExercise, ExerciseSubmission, AuthUser, PersonRef, DocumentTemplate, SitePageContent, SitePageSchema, SitePageKey, RegistrationDetails } from '../types';
import { INITIAL_COURSES, INITIAL_LIBRARY, INITIAL_WEBINARS, MOCK_IDS } from '../data/mockData';
import { features } from '../config/features';
import { courseMinAttendance } from '../config/constants';
// Uma única geradora de senha inicial, ao lado da política que ela precisa cumprir.
// A anterior (base-36 de bytes) podia sair só com dígitos ou só com letras, e nesse
// caso a API rejeitava o cadastro sem a tela explicar por quê.
import { generateInitialPassword } from '../utils/cpf';
import { frequenciaPercent, registroDoAluno } from '../utils/courseProgress';

// Wrapper de fetch autenticado. A sessão do navegador vive num cookie HttpOnly
// (ava_session), enviado automaticamente em requisições same-origin — nenhum token fica
// acessível a JavaScript. O fallback de header cobre apenas sessões antigas que ainda
// tenham um token remanescente em localStorage (compatibilidade transitória).
export function authFetch(url: string, options: RequestInit = {}): Promise<Response> {
  const legacyToken = localStorage.getItem('ava_auth_token');
  const headers = new Headers(options.headers || {});
  if (legacyToken) headers.set('Authorization', `Bearer ${legacyToken}`);
  return window.fetch(url, { ...options, headers, credentials: 'same-origin' });
}


/**
 * Resultado de uma tentativa de avaliação. `scorePercent`/`passed` são os do
 * SERVIDOR — o backend recalcula a nota e ignora a que o cliente enviou.
 */
export interface QuizResult {
  ok: boolean;
  error?: string;
  scorePercent?: number;
  passed?: boolean;
}

/**
 * Abas do painel de gestão. Era a mesma união literal copiada em três lugares
 * (contrato, setter e useState) — acrescentar uma aba exigia acertar as três,
 * e um esquecimento só aparecia como erro de tipo no ponto de uso.
 */
export type DashboardTab =
  | 'general' | 'messages' | 'certificates' | 'documents' | 'library' | 'events'
  | 'settings' | 'curriculum' | 'students' | 'faq' | 'avaliacoes';

/**
 * Resultado de gravar/apagar uma avaliação. `quiz` traz o que o SERVIDOR gravou
 * (com os ids definitivos das questões), para a tela não seguir com uma versão
 * inventada no cliente.
 */
export interface QuizWriteResult {
  ok: boolean;
  error?: string;
  quiz?: Quiz;
}

/** Resultado de uma escrita de exercício: quem chama precisa poder avisar a pessoa. */
export interface ExerciseResult {
  ok: boolean;
  error?: string;
}

interface LMSContextProps {
  courses: Course[];
  activeUser: { id: string; name: string; role: 'student' | 'instructor' | 'admin' };
  authUser: AuthUser | null;
  /** Identificador aceita e-mail (staff), CPF (aluno) ou nome (contas demo). */
  loginWithPassword: (identifier: string, password: string) => Promise<{ ok: boolean; user?: AuthUser; error?: string }>;
  registerUser: (name: string, email: string, password: string, role?: 'student' | 'instructor' | 'admin', details?: RegistrationDetails) => Promise<{ ok: boolean; pending?: boolean; user?: AuthUser; error?: string }>;
  logoutAuth: () => void;
  changePassword: (newPassword: string, currentPassword?: string) => Promise<{ ok: boolean; error?: string }>;
  /** Redefinição pela coordenação (sem a senha atual). Restrita a admin no servidor. */
  adminResetPassword: (userId: string, newPassword: string) => Promise<{ ok: boolean; error?: string }>;
  progress: StudentProgress[];
  certificates: Certificate[];
  chatMessages: ChatMessage[];
  directMessages: DirectMessage[];
  quizzes: Quiz[];
  quizSubmissions: QuizSubmission[];
  professorsList: PersonRef[];
  studentsList: { id?: string; name: string; email: string; municipio?: string; uf?: string; areaInteresse?: string; dataCadastro?: string; lastAccess?: string }[];
  academicRequests: AcademicRequest[];
  libraryItems: LibraryItem[];
  webinarEvents: WebinarEvent[];
  accessibilitySettings: AccessibilitySettings;
  isSpeechEnabled: boolean;
  setIsSpeechEnabled: (v: boolean) => void;
  currentLang: 'pt' | 'en' | 'es';
  setCurrentLang: (v: 'pt' | 'en' | 'es') => void;
  textSizeMultiplier: number;
  setTextSizeMultiplier: (v: number) => void;
  categoriesList: string[];
  updateUserName: (newName: string) => Promise<string | null>;
  toggleLessonCompletion: (courseId: string, lessonId: string) => void;
  attendLiveSession: (courseId: string, liveSessionId: string) => void;
  calculateAttendancePercent: (courseId: string) => number;
  addCourse: (course: Course) => void;
  deleteCourse: (courseId: string) => void;
  updateCourseInstructor: (courseId: string, instructorId: string) => void;
  updateCourseProps: (courseId: string, updates: Partial<Course>) => void;
  addLessonToCourse: (courseId: string, lessonTitle: string, duration: string, content: string, videoUrl?: string) => void;
  updateLesson: (courseId: string, lessonId: string, updates: Partial<Lesson>) => void;
  deleteLesson: (courseId: string, lessonId: string) => void;
  addLiveSessionToCourse: (courseId: string, title: string, scheduledAt: string, durationMinutes: number, meetingLink: string, isLive: boolean) => void;
  removeLiveSession: (courseId: string, sessionId: string) => void;
  sendLiveChatMessage: (sessionId: string, text: string) => void;
  setLiveSessionStatus: (courseId: string, sessionId: string, isLive: boolean) => void;
  sendDirectMessage: (studentUserId: string, text: string) => void;
  addQuiz: (courseId: string, title: string, questions: QuizQuestion[]) => Promise<QuizWriteResult>;
  updateQuiz: (
    quizId: string, courseId: string, title: string, questions: QuizQuestion[]
  ) => Promise<QuizWriteResult>;
  deleteQuiz: (quizId: string) => Promise<QuizWriteResult>;
  submitQuiz: (courseId: string, quizId: string, scorePercent: number, passed: boolean, answers: Record<string, number>) => Promise<QuizResult>;
  addProfessor: (name: string, password?: string) => void;
  deleteProfessor: (name: string) => void;
  /**
   * Cria a conta do aluno no backend. `cpf` é o identificador de login (ADR 11)
   * e por isso obrigatório. Devolve o resultado REAL do servidor: quem chama
   * precisa saber se a conta nasceu antes de dizer "matriculado com sucesso".
   */
  addStudent: (name: string, email: string, password: string | undefined, municipio: string | undefined, uf: string | undefined, areaInteresse: string | undefined, dataCadastro: string | undefined, cpf: string) => Promise<{ ok: boolean; error?: string }>;
  deleteStudent: (name: string) => void;
  addAcademicRequest: (req: Omit<AcademicRequest, 'id' | 'status' | 'submittedAt' | 'userId' | 'studentName'> & { userId?: string }) => void;
  updateRequestStatus: (reqId: string, status: 'approved' | 'rejected') => void;
  addCategory: (categoryName: string) => void;
  updateAccessibilitySettings: (updates: Partial<AccessibilitySettings>) => void;
  addLibraryItem: (item: Omit<LibraryItem, 'id'>) => void;
  /** Agenda ou atualiza um webinar; espera o servidor confirmar antes de mexer no estado. */
  addWebinarEvent: (webinar: Omit<WebinarEvent, 'id'> & { id?: string }) => Promise<{ ok: boolean; error?: string }>;
  deleteWebinarEvent: (id: string) => Promise<{ ok: boolean; error?: string }>;
  systemSettings: {
    allowDirectMessages: boolean;
    allowGlobalChat: boolean;
    openEnrollment: boolean;
    autoCertify: boolean;
    autoArchiveDuration: string;
    liveClassRecording: boolean;
  };
  updateSystemSettings: (updates: Partial<LMSContextProps['systemSettings']>) => void;
  activeDashboardTab: DashboardTab;
  setActiveDashboardTab: (tab: DashboardTab) => void;
  admissionRequests: AdmissionRequest[];
  addAdmissionRequest: (userId: string, courseId: string, status?: 'pending' | 'approved' | 'rejected') => void;
  updateAdmissionStatus: (reqId: string, status: 'approved' | 'rejected') => void;
  securityLogs: SecurityLog[];
  addSecurityLog: (action: string, details: string, status?: 'SUCCESS' | 'WARNING' | 'FAILED') => void;
  clearSecurityLogs: () => void;
  studentEnrollments: { [userId: string]: StudentEnrollment };
  enrollStudentInCourse: (userId: string, courseId: string) => Promise<{ ok: boolean; error?: string }>;
  dropStudentFromCourse: (userId: string, courseId: string) => Promise<{ ok: boolean; penaltyApplied: boolean; error?: string }>;
  completeStudentCourse: (userId: string, courseId: string) => Promise<{ ok: boolean; error?: string }>;
  clearStudentPenalty: (userId: string) => void;
  setStudentMultiEnrollPermission: (userId: string, allowed: boolean) => void;
  /** Conteúdo das páginas públicas; null enquanto não carregou (usa defaults). */
  sitePageContent: Record<string, SitePageContent> | null;
  /** Schema de campos servido pela API, usado para montar o formulário do admin. */
  sitePageSchema: Record<string, SitePageSchema> | null;
  updateSitePageContent: (pageKey: SitePageKey, content: Partial<SitePageContent>) => Promise<{ ok: boolean; page?: SitePageContent; error?: string }>;
  getDocumentTemplate: (type: DocumentTemplate['type']) => Promise<{ ok: boolean; template?: DocumentTemplate; error?: string }>;
  updateDocumentTemplate: (type: DocumentTemplate['type'], updates: Partial<Pick<DocumentTemplate, 'institutionName' | 'institutionLogoPath' | 'signatories' | 'footerText' | 'customHtml'>>) => Promise<{ ok: boolean; template?: DocumentTemplate; error?: string }>;
  forumMessages: ForumMessage[];
  addForumMessage: (courseId: string, text: string) => void;
  toggleForumMessageLike: (messageId: string) => void;
  deleteForumMessage: (messageId: string) => void;
  practicalExercises: PracticalExercise[];
  exerciseSubmissions: ExerciseSubmission[];
  /**
   * Exercícios práticos. Todas devolvem o resultado REAL do servidor: a nota e a
   * entrega são registro acadêmico, e uma falha silenciosa aqui deixava o dado
   * só no localStorage de quem clicou — o aluno via "Aguardando" e o professor
   * via a nota que ninguém mais no sistema tinha.
   */
  addPracticalExercise: (courseId: string, title: string, description: string, instructions: string, maxPoints: number, dueDate?: string) => Promise<ExerciseResult>;
  updatePracticalExercise: (exerciseId: string, updates: Partial<PracticalExercise>) => Promise<ExerciseResult>;
  deletePracticalExercise: (exerciseId: string) => Promise<ExerciseResult>;
  submitExercise: (exerciseId: string, submissionText: string, fileUrl?: string, fileName?: string) => Promise<ExerciseResult>;
  /** `graderName` saiu: o servidor grava `gradedBy` a partir do token, não do cliente. */
  gradeSubmission: (submissionId: string, score: number, feedback: string, status: 'approved' | 'rejected' | 'revision') => Promise<ExerciseResult>;
}

const LMSContext = createContext<LMSContextProps | undefined>(undefined);

// Versão do schema do estado persistido em localStorage. A v2 (ADR 10) indexa tudo por
// userId; dados name-keyed pré-ADR10 não têm mapa nome→id disponível offline, então a
// migração é descartar as chaves antigas — o backend re-hidrata o estado no mount.
const LMS_STORAGE_VERSION = '2';

function purgeLegacyNameKeyedStorage() {
  if (localStorage.getItem('ava_schema_version') === LMS_STORAGE_VERSION) return;
  const staleKeys: string[] = [];
  for (let i = 0; i < localStorage.length; i++) {
    const key = localStorage.key(i);
    if (key && key.startsWith('ava_') && key !== 'ava_auth_user') staleKeys.push(key);
  }
  staleKeys.forEach((key) => localStorage.removeItem(key));
  localStorage.setItem('ava_schema_version', LMS_STORAGE_VERSION);
}

export const LMSProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  // Roda ANTES dos inicializadores de useState abaixo, que leem o localStorage.
  purgeLegacyNameKeyedStorage();

  const [courses, setCourses] = useState<Course[]>(() => {
    const saved = localStorage.getItem('ava_courses');
    const parsed = saved ? JSON.parse(saved) : INITIAL_COURSES;
    if (Array.isArray(parsed)) {
      const seen = new Set<string>();
      const deduped: Course[] = [];
      for (const c of parsed) {
        if (c && c.id && !seen.has(c.id)) {
          seen.add(c.id);
          deduped.push(c);
        }
      }
      return deduped;
    }
    return INITIAL_COURSES;
  });

  const [categoriesList, setCategoriesList] = useState<string[]>(() => {
    const saved = localStorage.getItem('ava_categories');
    const defaultCats = ['Tecnologia', 'Design Digital', 'Ciência de Dados', 'Engenharia de Software', 'Economia Criativa & IA', 'Áreas Técnicas', 'Políticas e Gestão Culturais'];
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        if (Array.isArray(parsed)) {
          return Array.from(new Set(parsed)).filter(Boolean);
        }
      } catch (e) {
        console.error(e);
      }
    }
    return defaultCats;
  });

  useEffect(() => {
    localStorage.setItem('ava_categories', JSON.stringify(categoriesList));
  }, [categoriesList]);

  const addCategory = (categoryName: string) => {
    setCategoriesList(prev => {
      if (prev.includes(categoryName)) return prev;
      return [...prev, categoryName];
    });
  };

  // --- AUTENTICAÇÃO REAL (JWT + bcrypt no backend) ---
  const [authUser, setAuthUser] = useState<AuthUser | null>(() => {
    const saved = localStorage.getItem('ava_auth_user');
    if (saved) {
      try {
        return JSON.parse(saved);
      } catch (e) {}
    }
    return null;
  });

  useEffect(() => {
    if (authUser) localStorage.setItem('ava_auth_user', JSON.stringify(authUser));
    else localStorage.removeItem('ava_auth_user');
  }, [authUser]);

  // A sessão do cookie HttpOnly é a fonte da verdade; o perfil em localStorage é só
  // exibição. Ao carregar, pergunta ao servidor quem está autenticado:
  //  - 200 -> adota a identidade que o servidor informou (ADR 10: identidade vem do token);
  //  - 401 -> desloga de verdade, mesmo que o localStorage ainda tenha um perfil.
  //
  // Antes havia um `if (!authUser) return;` no começo, que só VALIDAVA uma sessão já
  // conhecida e nunca a ESTABELECIA. Efeito: com o cookie válido e o localStorage
  // limpo — outra aba, dados do site apagados, navegador que descarta storage — a
  // pessoa aparecia deslogada enquanto a sessão seguia ativa por 12h no servidor,
  // exatamente o contrário do que este comentário promete.
  useEffect(() => {
    authFetch('/api/auth/me')
      .then(async (res) => {
        if (res.status === 401) {
          setAuthUser(null);
          return;
        }
        if (!res.ok) return;
        const usuario: AuthUser = await res.json();
        if (usuario?.id) setAuthUser(usuario);
      })
      .catch(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Faz login contra o backend por nome (usado pelo seletor de perfil/PIN) ou e-mail (contém "@").
  const loginWithPassword = async (identifier: string, password: string) => {
    try {
      // Três identificadores (ADR 11): e-mail (admin/gestor), CPF (aluno) e
      // nome (contas demo internas). 11 dígitos = CPF.
      const digits = identifier.replace(/\D/g, '');
      let credentials: Record<string, string>;
      if (identifier.includes('@')) {
        credentials = { email: identifier, password };
      } else if (digits.length === 11) {
        credentials = { cpf: digits, password };
      } else {
        credentials = { name: identifier, password };
      }

      const res = await authFetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(credentials),
      });
      const data = await res.json();
      // Erros padronizados vêm como { error: true, code, message } — inclui os 403
      // institucionais de conta bloqueada/pendente (sem emissão de token).
      if (!res.ok) return { ok: false, error: data.message || 'Falha na autenticação.' };
      if (!data.token) {
        return { ok: false, error: data.message || 'Sua conta ainda não está liberada para acesso.' };
      }
      // A credencial fica no cookie HttpOnly setado pelo servidor — nada vai para localStorage.
      setAuthUser(data.user);
      return { ok: true, user: data.user as AuthUser };
    } catch (err) {
      console.error('Erro ao fazer login:', err);
      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  const registerUser = async (
    name: string,
    email: string,
    password: string,
    role: 'student' | 'instructor' | 'admin' = 'student',
    details: RegistrationDetails = {}
  ) => {
    try {
      const res = await authFetch('/api/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        // CPF é obrigatório para conta de aluno no backend (ADR 11); campos
        // vazios são omitidos para não gravar string vazia no banco.
        body: JSON.stringify({
          name,
          email,
          password,
          role,
          ...Object.fromEntries(
            Object.entries(details).filter(([, v]) => typeof v === 'string' && v.trim() !== '')
          ),
        }),
      });
      const data = await res.json();
      if (!res.ok) return { ok: false, error: data.message || 'Falha ao cadastrar.' };
      // Cadastro público nasce pending_confirmation e NÃO recebe token — a pessoa só entra
      // após homologação pela coordenação. Sinalizamos com pending=true para a UI.
      if (!data.token) {
        return { ok: true, pending: true, user: data.user as AuthUser };
      }
      // Cookie HttpOnly setado pelo servidor carrega a sessão.
      setAuthUser(data.user);
      return { ok: true, user: data.user as AuthUser };
    } catch (err) {
      console.error('Erro ao cadastrar usuário:', err);
      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  const logoutAuth = () => {
    // Limpa o cookie HttpOnly no servidor e qualquer token legado remanescente no navegador.
    authFetch('/api/auth/logout', { method: 'POST' }).catch(() => {});
    localStorage.removeItem('ava_auth_token');
    setAuthUser(null);
  };

  const changePassword = async (newPassword: string, currentPassword?: string) => {
    try {
      const res = await authFetch('/api/auth/password', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ newPassword, currentPassword }),
      });
      const data = await res.json();
      if (!res.ok) return { ok: false, error: data.error || 'Falha ao trocar senha.' };
      return { ok: true };
    } catch (err) {
      console.error('Erro ao trocar senha:', err);
      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  // Redefinição de senha pela coordenação. Antes disso a tela de admin só alterava
  // estado local: a senha do aluno continuava a antiga e ninguém percebia.
  const adminResetPassword = async (userId: string, newPassword: string) => {
    try {
      const res = await authFetch(`/api/auth/users/${userId}/password`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ newPassword }),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        return { ok: false, error: data.message || 'Falha ao redefinir a senha.' };
      }
      return { ok: true };
    } catch (err) {
      console.error('Erro ao redefinir senha:', err);
      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  // Identidade ativa é sempre DERIVADA da sessão autenticada (ADR 10) — nada de
  // perfil paralelo persistido em localStorage.
  const activeUser = useMemo(
    () => (authUser
      ? { id: authUser.id, name: authUser.name, role: authUser.role }
      : { id: '', name: '', role: 'student' as const }),
    [authUser]
  );

  const [professorsList, setProfessorsList] = useState<PersonRef[]>(() => {
    return [{ id: MOCK_IDS.gestor, name: 'Gestor de Conteúdos' }];
  });

  const [studentsList, setStudentsList] = useState<{ id?: string; name: string; email: string; municipio?: string; uf?: string; areaInteresse?: string; dataCadastro?: string; lastAccess?: string }[]>(() => {
    const defaultStudents = [
      { name: 'João Silva', email: 'joao.silva@lms.edu', municipio: 'São Paulo', uf: 'SP', areaInteresse: 'Design Digital', dataCadastro: '2026-01-10' },
      { name: 'Gabriel Rodrigues', email: 'gabriel.rodrigues@lms.edu', municipio: 'Recife', uf: 'PE', areaInteresse: 'Economia Criativa & IA', dataCadastro: '2026-02-14' },
      { name: 'Beatriz Costa', email: 'beatriz.c@lms.edu', municipio: 'Rio de Janeiro', uf: 'RJ', areaInteresse: 'Design Digital', dataCadastro: '2026-03-05' },
      { name: 'Sofia Rocha', email: 'sofia.rocha@lms.edu', municipio: 'Salvador', uf: 'BA', areaInteresse: 'Políticas e Gestão Culturais', dataCadastro: '2026-03-12' },
      { name: 'Ana Souza', email: 'ana.souza@lms.edu', municipio: 'Olinda', uf: 'PE', areaInteresse: 'Economia Criativa & IA', dataCadastro: '2026-04-01' },
      { name: 'Lucas Santana', email: 'lucas.santana@lms.edu', municipio: 'Belo Horizonte', uf: 'MG', areaInteresse: 'Áreas Técnicas', dataCadastro: '2026-04-18' },
      { name: 'Carolina Mendes', email: 'carol.mendes@lms.edu', municipio: 'Caruaru', uf: 'PE', areaInteresse: 'Políticas e Gestão Culturais', dataCadastro: '2026-05-02' }
    ];
    const saved = localStorage.getItem('ava_students');
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        if (Array.isArray(parsed)) {
          const seenEmails = new Set<string>();
          const dedupedParsed: { id?: string; name: string; email: string; municipio?: string; uf?: string; areaInteresse?: string; dataCadastro?: string; lastAccess?: string }[] = [];
          
          parsed.forEach(p => {
            if (p && p.email && !seenEmails.has(p.email.toLowerCase())) {
              seenEmails.add(p.email.toLowerCase());
              dedupedParsed.push(p);
            }
          });

          const merged = [...dedupedParsed];
          defaultStudents.forEach(item => {
            if (!seenEmails.has(item.email.toLowerCase())) {
              merged.push(item);
              seenEmails.add(item.email.toLowerCase());
            }
          });
          return merged;
        }
      } catch (e) {
        console.error(e);
      }
    }
    return defaultStudents;
  });

  const [progress, setProgress] = useState<StudentProgress[]>(() => {
    const saved = localStorage.getItem('ava_student_progress');
    let parsed: StudentProgress[] | null = null;
    try {
      if (saved) parsed = JSON.parse(saved);
    } catch (e) {
      console.error("Error parsing ava_student_progress:", e);
    }
    
    if (!parsed || !Array.isArray(parsed)) {
      parsed = [
        {
          userId: MOCK_IDS.joao,
          studentName: 'João Silva',
          courseId: 'course-1',
          completedLessons: ['lesson-1-1', 'lesson-1-2', 'lesson-1-3'], // 3 out of 5 lessons completed initially (60% lesson prog)
          attendedLiveSessions: ['live-1-2'], // Attended 1 out of 2 live sessions
        },
        {
          userId: MOCK_IDS.joao,
          studentName: 'João Silva',
          courseId: 'course-2',
          completedLessons: ['lesson-2-1'],
          attendedLiveSessions: [],
        }
      ];
    }

    const seen = new Set<string>();
    const deduped: StudentProgress[] = [];
    for (const p of parsed) {
      const key = `${p.userId ?? p.studentName}::${p.courseId}`;
      if (!seen.has(key)) {
        seen.add(key);
        deduped.push(p);
      }
    }
    return deduped;
  });

  const [certificates, setCertificates] = useState<Certificate[]>(() => {
    const saved = localStorage.getItem('ava_certificates');
    let parsed: Certificate[] | null = null;
    try {
      if (saved) parsed = JSON.parse(saved);
    } catch (e) {
      console.error("Error parsing ava_certificates:", e);
    }

    if (!parsed || !Array.isArray(parsed)) {
      return [];
    }

    const seenKeys = new Set<string>();
    const seenIds = new Set<string>();
    const deduped: Certificate[] = [];
    for (const cert of parsed) {
      if (!cert || !cert.id) continue;
      // Certificados históricos de contas removidas podem ter userId null — cai no nome.
      const key = `${cert.courseId}-${cert.userId ?? cert.studentName}`;
      if (!seenKeys.has(key) && !seenIds.has(cert.id)) {
        seenKeys.add(key);
        seenIds.add(cert.id);
        deduped.push(cert);
      }
    }
    return deduped;
  });

  // Mapa de matrículas keyed por userId (ADR 10) — o GET /api/enrollments já responde assim.
  const [studentEnrollments, setStudentEnrollments] = useState<{[userId: string]: StudentEnrollment}>(() => {
    const saved = localStorage.getItem('ava_student_enrollments');
    if (saved) {
      try {
        return JSON.parse(saved);
      } catch (e) {
        console.error(e);
      }
    }
    return {
      [MOCK_IDS.joao]: {
        userId: MOCK_IDS.joao,
        studentName: 'João Silva',
        enrolledCourseId: 'course-1',
        enrolledAt: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString(),
        completedCourseIds: [],
        dropOutPenaltyUntil: null,
        canMultiEnroll: false,
        extraCourseIds: []
      },
      [MOCK_IDS.gabriel]: {
        userId: MOCK_IDS.gabriel,
        studentName: 'Gabriel Rodrigues',
        enrolledCourseId: 'course-2',
        enrolledAt: new Date(Date.now() - 10 * 24 * 60 * 60 * 1000).toISOString(),
        completedCourseIds: [],
        dropOutPenaltyUntil: null,
        canMultiEnroll: false,
        extraCourseIds: []
      },
      [MOCK_IDS.beatriz]: {
        userId: MOCK_IDS.beatriz,
        studentName: 'Beatriz Costa',
        enrolledCourseId: null,
        enrolledAt: null,
        completedCourseIds: ['course-1'],
        dropOutPenaltyUntil: null,
        canMultiEnroll: false,
        extraCourseIds: []
      },
      [MOCK_IDS.sofia]: {
        userId: MOCK_IDS.sofia,
        studentName: 'Sofia Rocha',
        enrolledCourseId: null,
        enrolledAt: null,
        completedCourseIds: [],
        dropOutPenaltyUntil: new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString(),
        canMultiEnroll: false,
        extraCourseIds: []
      }
    };
  });

  useEffect(() => {
    localStorage.setItem('ava_student_enrollments', JSON.stringify(studentEnrollments));
  }, [studentEnrollments]);

  const [quizzes, setQuizzes] = useState<Quiz[]>(() => {
    const saved = localStorage.getItem('ava_quizzes');
    let parsed: Quiz[] | null = null;
    if (saved) {
      try {
        parsed = JSON.parse(saved);
      } catch (e) {
        console.error("Error parsing ava_quizzes:", e);
      }
    }
    const defaults = [
      {
        id: 'quiz-1',
        courseId: 'course-1',
        title: 'Quiz de UX e Heurísticas de Usabilidade',
        questions: [
          {
            id: 'quiz-1-q1',
            questionText: 'Qual heurística de Nielsen foca em manter o usuário ciente do andamento das ações do sistema?',
            options: [
              'Visibilidade do Status do Sistema',
              'Consistência e Padrões',
              'Prevenção de Erros',
              'Flexibilidade e Eficiência de Uso'
            ],
            correctOptionIndex: 0,
            explanation: 'A visibilidade do status do sistema garante que o usuário seja informado sobre o que está acontecendo por meio de feedbacks apropriados em tempo hábil.',
            reviewMessage: 'A visibilidade ajuda o usuário a se situar no fluxo da interface.',
            recommendedModule: 'Módulo 1 — Fundamentos de UX e Heurísticas de Nielsen',
            allowRetry: true
          },
          {
            id: 'quiz-1-q2',
            questionText: 'Qual o tamanho de grid recomendado pelo Material Design como subdivisor padrão no Figma?',
            options: [
              'Grid de 5pt',
              'Grid de 8pt',
              'Grid de 12pt',
              'Grid de 10pt'
            ],
            correctOptionIndex: 1,
            explanation: 'O Material Design adota o grid de 8pt (e subdivisões de 4pt) como padrão por conta da consistência de renderização em diferentes resoluções de tela físicas.',
            reviewMessage: 'O grid de 8pt ajuda no alinhamento espacial de margens, paddings e elementos de UI.',
            recommendedModule: 'Módulo 2 — Construção de Grid e Layout no Figma',
            allowRetry: true
          },
          {
            id: 'quiz-1-q3',
            questionText: 'Ao conduzir um teste Think Aloud, qual é o principal papel do facilitador?',
            options: [
              'Explicar passo a passo como resolver a interface',
              'Apenas observar em silêncio absoluto sem gerar áudio',
              'Incentivar o usuário a expressar seus pensamentos em voz alta sem direcionar suas escolhas',
              'Avaliar o usuário atribuindo uma nota de inteligência'
            ],
            correctOptionIndex: 2,
            explanation: 'O método Think Aloud visa extrair o fluxo mental consciente do usuário durante o uso. O facilitador deve lembrá-lo de verbalizar pensamentos de forma neutra.',
            reviewMessage: 'O Think Aloud foca na escuta ativa e neutralidade para extrair insights reais de usabilidade.',
            recommendedModule: 'Módulo 3 — Métodos de Testes de Usabilidade com Usuários',
            allowRetry: true
          }
        ]
      },
      {
        id: 'quiz-2',
        courseId: 'course-2',
        title: 'Quiz de Fundamentos de Servidores Express',
        questions: [
          {
            id: 'quiz-2-q1',
            questionText: 'Qual método HTTP deve ser preferencialmente utilizado de acordo com o padrão REST para atualizar parcialmente dados contidos em um registro existente?',
            options: [
              'POST',
              'GET',
              'PATCH',
              'DELETE'
            ],
            correctOptionIndex: 2,
            explanation: 'O método PATCH é recomendado para atualizações parciais, enquanto o PUT costuma ser usado para substituições completas do recurso.',
            reviewMessage: 'Utilizar os verbos corretos mantém a consistência da arquitetura RESTful.',
            recommendedModule: 'Módulo 1 — Rotas e Métodos de Requisição HTTP no Express',
            allowRetry: true
          },
          {
            id: 'quiz-2-q2',
            questionText: 'Qual o papel principal do middleware de CORS em rotas Express de ambiente de produção?',
            options: [
              'Melhorar o visual de erro retornado ao usuário',
              'Permitir ou restringir requisições vindas de origens externas autorizadas',
              'Criptografar automaticamente todas as senhas armazenadas no PostgreSQL',
              'Acelerar a renderização do React'
            ],
            correctOptionIndex: 1,
            explanation: 'CORS (Cross-Origin Resource Sharing) controla a segurança de navegadores permitindo que recursos restritos de um site sejam solicitados por domínios autorizados.',
            reviewMessage: 'A configuração adequada de CORS evita brechas de segurança no acesso à API.',
            recommendedModule: 'Módulo 2 — Middlewares Essenciais e Segurança no Express',
            allowRetry: true
          }
        ]
      }
    ];

    const source = parsed && Array.isArray(parsed) ? parsed : defaults;
    if (parsed && Array.isArray(parsed)) {
      parsed.forEach(q => {
        const defQ = defaults.find(dq => dq.id === q.id);
        if (defQ) {
          q.questions.forEach(quest => {
            const defQuest = defQ.questions.find(dqQuest => dqQuest.id === quest.id);
            if (defQuest) {
              if (quest.explanation === undefined) quest.explanation = defQuest.explanation;
              if (quest.reviewMessage === undefined) quest.reviewMessage = defQuest.reviewMessage;
              if (quest.recommendedModule === undefined) quest.recommendedModule = defQuest.recommendedModule;
              if (quest.allowRetry === undefined) quest.allowRetry = defQuest.allowRetry;
            }
          });
        }
      });
    }
    const seen = new Set<string>();
    const deduped: Quiz[] = [];
    for (const q of source) {
      if (q && q.id && !seen.has(q.id)) {
        seen.add(q.id);
        deduped.push(q);
      }
    }
    return deduped;
  });

  const [activeDashboardTab, setActiveDashboardTab] = useState<DashboardTab>('general');
  const [libraryItems, setLibraryItems] = useState<LibraryItem[]>(() => {
    const saved = localStorage.getItem('ava_library_items');
    let parsed: LibraryItem[] | null = null;
    if (saved) {
      try {
        parsed = JSON.parse(saved);
      } catch (e) {
        console.error("Error parsing ava_library_items:", e);
      }
    }
    const source = parsed && Array.isArray(parsed) ? parsed : (INITIAL_LIBRARY as LibraryItem[]);
    const seen = new Set<string>();
    const deduped: LibraryItem[] = [];
    for (const item of source) {
      if (item && item.id && !seen.has(item.id)) {
        seen.add(item.id);
        deduped.push(item);
      }
    }
    return deduped;
  });
  const [webinarEvents, setWebinarEvents] = useState<WebinarEvent[]>(() => {
    const saved = localStorage.getItem('ava_webinar_events');
    let parsed: WebinarEvent[] | null = null;
    if (saved) {
      try {
        parsed = JSON.parse(saved);
      } catch (e) {
        console.error("Error parsing ava_webinar_events:", e);
      }
    }
    const source = parsed && Array.isArray(parsed) ? parsed : (INITIAL_WEBINARS as WebinarEvent[]);
    const seen = new Set<string>();
    const deduped: WebinarEvent[] = [];
    for (const item of source) {
      if (item && item.id && !seen.has(item.id)) {
        seen.add(item.id);
        deduped.push(item);
      }
    }
    return deduped;
  });

  const addLibraryItem = (item: Omit<LibraryItem, 'id'>) => {
    const newItem = { ...item, id: `lib-${Date.now()}` };
    setLibraryItems(prev => [newItem, ...prev]);
    authFetch('/api/library', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newItem)
    }).catch(err => console.error(err));
  };

  /**
   * Agenda (ou atualiza, informando o id) um webinar.
   *
   * Espera a resposta do servidor antes de mexer no estado. Antes inseria na lista
   * local ANTES de chamar a API e engolia o erro com console.error: o webinar
   * aparecia na tela de quem agendou, era gravado em localStorage, e não existia no
   * banco. Quem agendava via a confirmação e o evento nunca chegava ao site.
   */
  const addWebinarEvent = async (
    webinar: Omit<WebinarEvent, 'id'> & { id?: string }
  ): Promise<{ ok: boolean; error?: string }> => {
    const payload = { ...webinar, id: webinar.id ?? `web-${Date.now()}` };
    try {
      const res = await authFetch('/api/webinars', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        return { ok: false, error: data.message || 'Não foi possível agendar o webinar.' };
      }
      // Usa o registro que o servidor devolveu, não o que foi enviado: o id e os
      // campos normalizados passam a ser os mesmos que os outros clientes verão.
      const salvo: WebinarEvent = await res.json();
      setWebinarEvents(prev => [salvo, ...prev.filter(w => w.id !== salvo.id)]);

      return { ok: true };
    } catch (err) {
      console.error('Erro ao agendar webinar:', err);

      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  const deleteWebinarEvent = async (id: string): Promise<{ ok: boolean; error?: string }> => {
    try {
      const res = await authFetch(`/api/webinars/${id}`, { method: 'DELETE' });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        return { ok: false, error: data.message || 'Não foi possível remover o webinar.' };
      }
      setWebinarEvents(prev => prev.filter(w => w.id !== id));

      return { ok: true };
    } catch (err) {
      console.error('Erro ao remover webinar:', err);

      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  useEffect(() => {
    localStorage.setItem('ava_library_items', JSON.stringify(libraryItems));
  }, [libraryItems]);

  useEffect(() => {
    localStorage.setItem('ava_webinar_events', JSON.stringify(webinarEvents));
  }, [webinarEvents]);

  const [accessibilitySettings, setAccessibilitySettings] = useState<AccessibilitySettings>(() => {
    const saved = localStorage.getItem('ava_accessibility_settings');
    if (saved) return JSON.parse(saved);
    return {
      highContrast: false,
      fontSize: 'medium',
      dyslexicFont: false
    };
  });

  const [isSpeechEnabled, setIsSpeechEnabled] = useState(() => {
    const saved = localStorage.getItem('ava_speech_enabled');
    return saved ? JSON.parse(saved) : false;
  });

  const [currentLang, setCurrentLang] = useState<'pt' | 'en' | 'es'>(() => {
    const saved = localStorage.getItem('ava_current_lang');
    return (saved as 'pt' | 'en' | 'es') || 'pt';
  });

  const [textSizeMultiplier, setTextSizeMultiplier] = useState(() => {
    const saved = localStorage.getItem('ava_text_size_multiplier');
    return saved ? JSON.parse(saved) : 1.0;
  });

  const updateAccessibilitySettings = (updates: Partial<AccessibilitySettings>) => {
    setAccessibilitySettings(prev => ({ ...prev, ...updates }));
  };

  useEffect(() => {
    localStorage.setItem('ava_accessibility_settings', JSON.stringify(accessibilitySettings));
  }, [accessibilitySettings]);

  useEffect(() => {
    localStorage.setItem('ava_speech_enabled', JSON.stringify(isSpeechEnabled));
  }, [isSpeechEnabled]);

  useEffect(() => {
    localStorage.setItem('ava_current_lang', currentLang);
  }, [currentLang]);

  useEffect(() => {
    localStorage.setItem('ava_text_size_multiplier', JSON.stringify(textSizeMultiplier));
  }, [textSizeMultiplier]);

  const [quizSubmissions, setQuizSubmissions] = useState<QuizSubmission[]>(() => {
    const saved = localStorage.getItem('ava_quiz_submissions');
    let parsed: QuizSubmission[] | null = null;
    if (saved) {
      try {
        parsed = JSON.parse(saved);
      } catch (e) {
        console.error("Error parsing ava_quiz_submissions:", e);
      }
    }
    const source = parsed && Array.isArray(parsed) ? parsed : [];
    const seen = new Set<string>();
    const deduped: QuizSubmission[] = [];
    for (const sub of source) {
      if (sub && sub.id && !seen.has(sub.id)) {
        seen.add(sub.id);
        deduped.push(sub);
      }
    }
    return deduped;
  });

  const [forumMessages, setForumMessages] = useState<ForumMessage[]>(() => {
    const saved = localStorage.getItem('ava_forum_messages');
    let parsed: ForumMessage[] | null = null;
    if (saved) {
      try {
        parsed = JSON.parse(saved);
      } catch (e) {
        console.error("Error parsing ava_forum_messages:", e);
      }
    }
    const defaults: ForumMessage[] = [
      {
        id: 'forum-msg-1',
        courseId: 'course-1',
        senderName: 'Sofia Rocha',
        senderRole: 'student',
        text: 'Oi pessoal! Alguém tem dicas sobre como aplicar a heurística de Prevenção de Erros em formulários longos em nossa aplicação?',
        timestamp: '15/06/2026, 14:32',
        likes: 3,
        likedBy: [MOCK_IDS.joao, MOCK_IDS.gabriel]
      },
      {
        id: 'forum-msg-2',
        courseId: 'course-1',
        senderName: 'João Silva',
        senderRole: 'student',
        text: 'Oi Sofia! Geralmente desabilitar o botão de continuar até que os campos de inputs obrigatórios estejam com formatos válidos ajuda imensamente, além de exibir feedback visual imediato.',
        timestamp: '15/06/2026, 14:48',
        likes: 5,
        likedBy: [MOCK_IDS.sofia, MOCK_IDS.gabriel, MOCK_IDS.beatriz]
      },
      {
        id: 'forum-msg-3',
        courseId: 'course-1',
        senderName: 'Gestor de Conteúdos',
        senderRole: 'instructor',
        text: 'Excelente discussão e fomento de ideias! Lembrem-se também de detalhar os erros de forma humanizada ao invés de usar códigos enigmáticos como "Error 412: Campo Requerido" (Heurística de Diagnóstico e Recuperação de Erros).',
        timestamp: '15/06/2026, 16:10',
        likes: 8,
        likedBy: [MOCK_IDS.sofia, MOCK_IDS.joao, MOCK_IDS.gabriel, MOCK_IDS.beatriz]
      },
      {
        id: 'forum-msg-4',
        courseId: 'course-2',
        senderName: 'Gabriel Rodrigues',
        senderRole: 'student',
        text: 'Fala galera de Vídeo Mapping! Alguém que já trabalha na área indica algum projetor bacana para início de carreira ou instalações domésticas em paredes brancas simples?',
        timestamp: '16/06/2026, 10:15',
        likes: 2,
        likedBy: [MOCK_IDS.joao]
      },
      {
        id: 'forum-msg-5',
        courseId: 'course-2',
        senderName: 'Gestor de Conteúdos',
        senderRole: 'instructor',
        text: 'Olá Gabriel! Para superfícies brancas internas convencionais de baixa iluminação, projetores Epson de curta distância (Short Throw) com pelo menos 3000 ANSI Lumens atendem o alinhamento com folga. Desative o HMR e aproveite o alinhamento de canais!',
        timestamp: '16/06/2026, 11:02',
        likes: 4,
        likedBy: [MOCK_IDS.gabriel, MOCK_IDS.joao]
      }
    ];

    const source = parsed && Array.isArray(parsed) ? parsed : defaults;
    const seen = new Set<string>();
    const deduped: ForumMessage[] = [];
    for (const msg of source) {
      if (msg && msg.id && !seen.has(msg.id)) {
        seen.add(msg.id);
        deduped.push(msg);
      }
    }
    return deduped;
  });

  useEffect(() => {
    localStorage.setItem('ava_forum_messages', JSON.stringify(forumMessages));
  }, [forumMessages]);

  const [practicalExercises, setPracticalExercises] = useState<PracticalExercise[]>(() => {
    const saved = localStorage.getItem('ava_practical_exercises');
    let parsed: PracticalExercise[] | null = null;
    if (saved) {
      try {
        parsed = JSON.parse(saved);
      } catch (e) {
        console.error("Error parsing ava_practical_exercises:", e);
      }
    }
    const defaults = [
      {
        id: 'exercise-1',
        courseId: 'course-1',
        title: 'Análise de Heurísticas de Usabilidade',
        description: 'Escolha um site ou aplicativo de sua preferência e faça um relatório identificando pelo menos 3 violações das heurísticas de usabilidade de Nielsen, justificando sua análise.',
        instructions: 'Envie um relatório curto no campo de texto detalhando os pontos de atenção e propondo soluções de design simples para cada violação identificada.',
        maxPoints: 100,
        dueDate: '10/07/2026'
      },
      {
        id: 'exercise-2',
        courseId: 'course-2',
        title: 'Planejamento de Máscaras e Alinhamento',
        description: 'Elabore um plano de mapeamento de projeção para uma fachada de prédio geométrica simples contendo 3 janelas e uma porta central.',
        instructions: 'Escreva um plano passo-a-passo detalhando como você organizaria as camadas de mascaramento de corte para as janelas e portas para evitar luz intrusiva nos vidros, e quais softwares usaria.',
        maxPoints: 100,
        dueDate: '15/07/2026'
      }
    ];

    const source = parsed && Array.isArray(parsed) ? parsed : defaults;
    const seen = new Set<string>();
    const deduped: PracticalExercise[] = [];
    for (const ex of source) {
      if (ex && ex.id && !seen.has(ex.id)) {
        seen.add(ex.id);
        deduped.push(ex);
      }
    }
    return deduped;
  });

  const [exerciseSubmissions, setExerciseSubmissions] = useState<ExerciseSubmission[]>(() => {
    const saved = localStorage.getItem('ava_exercise_submissions');
    let parsed: ExerciseSubmission[] | null = null;
    if (saved) {
      try {
        parsed = JSON.parse(saved);
      } catch (e) {
        console.error("Error parsing ava_exercise_submissions:", e);
      }
    }
    const defaults: ExerciseSubmission[] = [
      {
        id: 'submission-1',
        exerciseId: 'exercise-1',
        userId: MOCK_IDS.joao,
        studentName: 'João Silva',
        submissionText: 'Relatório de Usabilidade: Analisei o portal municipal da biblioteca.\n\n1. Visibilidade do status do sistema: Quando reservo um livro, a tela recarrega lentamente sem confirmação imediata, deixando o usuário sem saber se a operação deu certo.\n2. Prevenção de erros: O campo de busca de CPF aceita caracteres não-numéricos e quebra o banco.\n3. Consistência: Os botões de confirmação trocam de cor e lado dependendo da tela (às vezes verde na direita, às vezes azul na esquerda).\n\nRecomendação: Adicionar um Toast de sucesso e regex de validação de campo.',
        submittedAt: '26/06/2026, 15:42',
        status: 'approved',
        score: 95,
        feedback: 'Excelente análise, João! Você compreendeu perfeitamente as Heurísticas de Usabilidade de Nielsen e propôs correções elegantes e econômicas. Parabéns!',
        gradedAt: '26/06/2026, 17:00',
        gradedBy: 'Gestor de Conteúdos'
      }
    ];

    const source = parsed && Array.isArray(parsed) ? parsed : defaults;
    const seen = new Set<string>();
    const deduped: ExerciseSubmission[] = [];
    for (const sub of source) {
      if (sub && sub.id && !seen.has(sub.id)) {
        seen.add(sub.id);
        deduped.push(sub);
      }
    }
    return deduped;
  });

  useEffect(() => {
    localStorage.setItem('ava_practical_exercises', JSON.stringify(practicalExercises));
  }, [practicalExercises]);

  useEffect(() => {
    localStorage.setItem('ava_exercise_submissions', JSON.stringify(exerciseSubmissions));
  }, [exerciseSubmissions]);

  const [academicRequests, setAcademicRequests] = useState<AcademicRequest[]>(() => {
    const saved = localStorage.getItem('ava_academic_requests');
    let parsed: AcademicRequest[] | null = null;
    if (saved) {
      try {
        parsed = JSON.parse(saved);
      } catch (e) {
        console.error("Error parsing ava_academic_requests:", e);
      }
    }
    const defaults: AcademicRequest[] = [
      {
        id: 'req-1',
        userId: MOCK_IDS.joao,
        studentName: 'João Silva',
        type: 'certificado',
        description: 'Solicito a emissão do certificado prioritário do curso de Design de Interfaces de Alta Performance para comprovação de horas complementares na graduação.',
        status: 'pending',
        submittedAt: '24/05/2026',
        courseTitle: 'Design de Interfaces de Alta Performance'
      },
      {
        id: 'req-2',
        userId: MOCK_IDS.ana,
        studentName: 'Ana Souza',
        type: 'historico',
        description: 'Necessito do envio do meu Histórico Escolar Acadêmico oficial em PDF referente ao meu progresso acumulado na plataforma para validação de estágio obrigatório.',
        status: 'pending',
        submittedAt: '25/05/2026'
      },
      {
        id: 'req-3',
        userId: MOCK_IDS.lucas,
        studentName: 'Lucas Santana',
        type: 'matricula',
        description: 'Não consigo acessar as aulas do curso de Desenvolvimento de Servidores com Node.js e Express. Solicito liberação manual da coordenação.',
        status: 'approved',
        submittedAt: '26/05/2026',
        courseTitle: 'Desenvolvimento de Servidores com Node.js e Express'
      }
    ];

    const source = parsed && Array.isArray(parsed) ? parsed : defaults;
    const seen = new Set<string>();
    const deduped: AcademicRequest[] = [];
    for (const r of source) {
      if (r && r.id && !seen.has(r.id)) {
        seen.add(r.id);
        deduped.push(r);
      }
    }
    return deduped;
  });

  const [chatMessages, setChatMessages] = useState<ChatMessage[]>(() => {
    const saved = localStorage.getItem('ava_chat_messages');
    let parsed: ChatMessage[] | null = null;
    if (saved) {
      try {
        parsed = JSON.parse(saved);
      } catch (e) {
        console.error("Error parsing ava_chat_messages:", e);
      }
    }
    const defaults: ChatMessage[] = [
      {
        id: 'msg-1',
        sessionId: 'live-1-1',
        senderName: 'Gestor de Conteúdos',
        senderRole: 'instructor',
        text: 'Sejam bem-vindos à aula ao vivo sobre UX de Alta Performance! Podem enviar dúvidas aqui no chat.',
        timestamp: new Date().toISOString()
      },
      {
        id: 'msg-2',
        sessionId: 'live-1-1',
        senderName: 'João Silva',
        senderRole: 'student',
        text: 'Olá Gestor! Esse grid de 8pt se aplica também para design mobile ou focamos em layouts web no Figma?',
        timestamp: new Date().toISOString()
      },
      {
        id: 'msg-3',
        sessionId: 'live-2-1',
        senderName: 'Gestor de Conteúdos',
        senderRole: 'instructor',
        text: 'Iniciando em breve nossa aula prática de Express APIs!',
        timestamp: new Date().toISOString()
      }
    ];

    const source = parsed && Array.isArray(parsed) ? parsed : defaults;
    const seen = new Set<string>();
    const deduped: ChatMessage[] = [];
    for (const msg of source) {
      if (msg && msg.id && !seen.has(msg.id)) {
        seen.add(msg.id);
        deduped.push(msg);
      }
    }
    return deduped;
  });

  const [directMessages, setDirectMessages] = useState<DirectMessage[]>(() => {
    const defaultDMs: DirectMessage[] = [
      {
        id: 'dm-1',
        studentUserId: MOCK_IDS.joao,
        studentName: 'João Silva',
        senderUserId: MOCK_IDS.joao,
        senderName: 'João Silva',
        senderRole: 'student',
        text: 'Olá Gestor, tudo bem? Estou gostando muito do curso de UX! Quando teremos o próximo feedback de portfólios?',
        timestamp: new Date(Date.now() - 3600000 * 5).toISOString() // 5h ago
      },
      {
        id: 'dm-2',
        studentUserId: MOCK_IDS.joao,
        studentName: 'João Silva',
        senderUserId: MOCK_IDS.gestor,
        senderName: 'Gestor de Conteúdos',
        senderRole: 'instructor',
        text: 'Olá João! Que ótimo que está curtindo. Teremos uma mentoria sobre isso hoje mesmo às 19:30, mas você pode também agendar um horário direto comigo se precisar!',
        timestamp: new Date(Date.now() - 3600000 * 4).toISOString() // 4h ago
      },
      {
        id: 'dm-3',
        studentUserId: MOCK_IDS.gabriel,
        studentName: 'Gabriel Rodrigues',
        senderUserId: MOCK_IDS.gabriel,
        senderName: 'Gabriel Rodrigues',
        senderRole: 'student',
        text: 'Olá tutor Gestor! Enviei o link do meu protótipo no Figma para avaliação. Poderia dar uma olhada no fluxo de navegação?',
        timestamp: new Date(Date.now() - 3600000 * 3).toISOString() // 3h ago
      },
      {
        id: 'dm-4',
        studentUserId: MOCK_IDS.beatriz,
        studentName: 'Beatriz Costa',
        senderUserId: MOCK_IDS.beatriz,
        senderName: 'Beatriz Costa',
        senderRole: 'student',
        text: 'Professor, tenho uma dúvida conceitual sobre a prestação de contas de nosso coletivo para editais da Lei Paulo Gustavo. Existe algum modelo de planilha que possamos seguir?',
        timestamp: new Date(Date.now() - 3600000 * 2.5).toISOString()
      },
      {
        id: 'dm-5',
        studentUserId: MOCK_IDS.sofia,
        studentName: 'Sofia Rocha',
        senderUserId: MOCK_IDS.sofia,
        senderName: 'Sofia Rocha',
        senderRole: 'student',
        text: 'Estou com dificuldades para rodar o software de Vídeo Mapping em meu notebook antigo. Há alguma alternativa de projeção ou simulador mais leve recomendável?',
        timestamp: new Date(Date.now() - 3600000 * 1.5).toISOString()
      }
    ];
    const saved = localStorage.getItem('ava_direct_messages');
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        if (Array.isArray(parsed)) {
          const merged: DirectMessage[] = [];
          const seen = new Set<string>();
          for (const dm of parsed) {
            if (dm && dm.id && !seen.has(dm.id)) {
              seen.add(dm.id);
              merged.push(dm);
            }
          }
          defaultDMs.forEach(item => {
            if (!seen.has(item.id)) {
              seen.add(item.id);
              merged.push(item);
            }
          });
          return merged;
        }
      } catch (e) {
        console.error("Error parsing ava_direct_messages:", e);
      }
    }
    return defaultDMs;
  });

  const [systemSettings, setSystemSettings] = useState(() => {
    const saved = localStorage.getItem('ava_system_settings');
    if (saved) return JSON.parse(saved);
    return {
      allowDirectMessages: true,
      allowGlobalChat: false,
      openEnrollment: true,
      autoCertify: true,
      autoArchiveDuration: '6_meses',
      liveClassRecording: true
    };
  });

  const updateSystemSettings = (updates: Partial<LMSContextProps['systemSettings']>) => {
    setSystemSettings((prev: LMSContextProps['systemSettings']) => {
      const next = { ...prev, ...updates };
      authFetch('/api/system-settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updates)
      }).catch(err => console.error(err));
      return next;
    });
  };

  useEffect(() => {
    localStorage.setItem('ava_system_settings', JSON.stringify(systemSettings));
  }, [systemSettings]);

  // Conteúdo editável das páginas públicas. Buscado num efeito próprio, sem
  // depender de login: o visitante anônimo precisa disso para montar o site.
  // Null = ainda não carregou (ou API offline) e cada página usa seus defaults.
  const [sitePageContent, setSitePageContent] = useState<Record<string, SitePageContent> | null>(null);
  const [sitePageSchema, setSitePageSchema] = useState<Record<string, SitePageSchema> | null>(null);

  useEffect(() => {
    if (!features.gestaoConteudoSite) return;
    let cancelled = false;

    authFetch('/api/site-content')
      .then(async (res) => {
        if (!res.ok) return;
        const data = await res.json();
        if (cancelled) return;
        setSitePageContent(data.pages ?? null);
        setSitePageSchema(data.schema ?? null);
      })
      .catch(() => {
        // API offline: as páginas seguem com o conteúdo padrão embutido.
      });

    return () => { cancelled = true; };
  }, []);

  const updateSitePageContent = async (
    pageKey: SitePageKey,
    content: Partial<SitePageContent>
  ): Promise<{ ok: boolean; page?: SitePageContent; error?: string }> => {
    try {
      const res = await authFetch(`/api/site-content/${encodeURIComponent(pageKey)}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(content)
      });
      const data = await res.json();
      if (!res.ok) return { ok: false, error: data.message || 'Não foi possível salvar o conteúdo.' };

      // O servidor devolve a versão canônica (normalizada); é ela que vale.
      const page = data as SitePageContent;
      setSitePageContent((prev) => ({ ...(prev ?? {}), [pageKey]: page }));
      return { ok: true, page };
    } catch (err) {
      console.error('Erro ao salvar conteúdo do site:', err);
      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  const [securityLogs, setSecurityLogs] = useState<SecurityLog[]>(() => {
    const saved = localStorage.getItem('ava_security_logs');
    let parsed: SecurityLog[] | null = null;
    if (saved) {
      try {
        parsed = JSON.parse(saved);
      } catch (e) {
        console.error("Error parsing ava_security_logs:", e);
      }
    }
    const defaults = [
      {
        id: 'log-1',
        timestamp: new Date(Date.now() - 3600000 * 5).toLocaleTimeString('pt-BR') + ' ' + new Date(Date.now() - 3600000 * 5).toLocaleDateString('pt-BR'),
        user: 'Admin Superior',
        role: 'admin',
        ipAddress: '192.168.1.14',
        device: 'Chrome / macOS (Sistema Autenticado)',
        action: 'Auditoria de Sistema',
        details: 'Geração de relatório geral de matrículas ativas na Escola da Cultura.',
        status: 'SUCCESS' as const
      },
      {
        id: 'log-2',
        timestamp: new Date(Date.now() - 3600000 * 3).toLocaleTimeString('pt-BR') + ' ' + new Date(Date.now() - 3600000 * 3).toLocaleDateString('pt-BR'),
        user: 'Gestor de Conteúdos',
        role: 'instructor',
        ipAddress: '172.16.254.12',
        device: 'Firefox / Windows 11',
        action: 'Atualização de Aula',
        details: 'Novas diretrizes e links adicionados na aula inaugural de Vídeo Mapping.',
        status: 'SUCCESS' as const
      },
      {
        id: 'log-3',
        timestamp: new Date(Date.now() - 3600000 * 1).toLocaleTimeString('pt-BR') + ' ' + new Date(Date.now() - 3600000 * 1).toLocaleDateString('pt-BR'),
        user: 'João Silva',
        role: 'student',
        ipAddress: '189.122.45.92',
        device: 'Safari / iPhone 15 Pro',
        action: 'Autenticação no Sistema',
        details: 'Acesso realizado com êxito sob as diretrizes de LGPD e segurança de canais.',
        status: 'SUCCESS' as const
      }
    ];

    const source = parsed && Array.isArray(parsed) ? parsed : defaults;
    const seen = new Set<string>();
    const deduped: SecurityLog[] = [];
    for (const log of source) {
      if (log && log.id && !seen.has(log.id)) {
        seen.add(log.id);
        deduped.push(log);
      }
    }
    return deduped;
  });

  // Telemetria de UI: eventos informativos do frontend vão para /api/telemetry (tabela
  // ClientEvent), SEPARADOS da trilha de auditoria — o SecurityLog é gravado apenas pelo
  // servidor a partir de ações reais (login, matrícula, exportação, etc.).
  // O eco local no estado é só feedback imediato na tela do admin; a fonte de verdade da
  // auditoria vem do GET /api/security-logs.
  const addSecurityLog = (action: string, details: string, status: 'SUCCESS' | 'WARNING' | 'FAILED' = 'SUCCESS') => {
    const currentFormattedTime = new Date().toLocaleTimeString('pt-BR') + ' ' + new Date().toLocaleDateString('pt-BR');
    const userName = activeUser?.name || 'Visitante Anônimo';
    const userRole = activeUser?.role || 'student';

    const localEcho: SecurityLog = {
      id: `evt-${Date.now()}`,
      timestamp: currentFormattedTime,
      user: userName,
      role: userRole,
      ipAddress: '—',
      device: navigator.userAgent?.slice(0, 60) || 'navegador',
      action,
      details,
      status
    };
    setSecurityLogs((prev) => [localEcho, ...prev].slice(0, 50));

    authFetch('/api/telemetry', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, details, status })
    }).catch(err => console.error('Erro ao enviar telemetria:', err));
  };

  const clearSecurityLogs = () => {
    setSecurityLogs([]);
    authFetch('/api/security-logs', { method: 'DELETE' })
      .catch((err) => console.error("Erro ao limpar logs com backend:", err));
  };

  useEffect(() => {
    localStorage.setItem('ava_security_logs', JSON.stringify(securityLogs));
  }, [securityLogs]);

  // Sincronização inicial do aplicativo com o backend Express ao montar o contexto.
  // Rotas de funcionalidades desativadas por feature flag nem são consultadas — o backend
  // também as bloqueia (404 FEATURE_DISABLED), este é apenas o espelho no cliente.
  useEffect(() => {
    const skipped = Promise.resolve({ ok: false } as Response);
    const fetchIf = (enabled: boolean, url: string) => (enabled ? authFetch(url) : skipped);

    const fetchBackendState = async () => {
      try {
        const [
          coursesRes,
          libraryRes,
          webinarsRes,
          progressRes,
          certificatesRes,
          chatRes,
          dmsRes,
          requestsRes,
          admissionsRes,
          logsRes,
          settingsRes,
          studentsRes,
          instructorsRes,
          enrollmentsRes,
          quizzesRes,
          quizSubmissionsRes,
          forumRes,
          exercisesRes,
          exerciseSubmissionsRes
        ] = await Promise.all([
          fetchIf(features.catalogoCursos, '/api/courses'),
          fetchIf(features.materiaisComplementares, '/api/library'),
          fetchIf(features.eventosWebinars, '/api/webinars'),
          fetchIf(features.progresso, '/api/progress'),
          fetchIf(features.certificados, '/api/certificates'),
          fetchIf(features.liveClassroom, '/api/chat'),
          fetchIf(features.mensagensDiretas, '/api/dms'),
          fetchIf(features.solicitacoesAcademicas, '/api/academic-requests'),
          fetchIf(features.matricula, '/api/admissions'),
          authFetch('/api/security-logs'),
          authFetch('/api/system-settings'),
          authFetch('/api/auth/users?role=student'),
          authFetch('/api/auth/users?role=instructor'),
          fetchIf(features.matricula, '/api/enrollments'),
          fetchIf(features.quizSimples, '/api/quizzes'),
          fetchIf(features.quizSimples, '/api/quiz-submissions'),
          fetchIf(features.forum, '/api/forum'),
          fetchIf(features.atividadesPraticasAvancadas, '/api/exercises'),
          fetchIf(features.atividadesPraticasAvancadas, '/api/exercise-submissions')
        ]);

        if (coursesRes.ok) setCourses(await coursesRes.json());
        if (libraryRes.ok) setLibraryItems(await libraryRes.json());
        if (webinarsRes.ok) setWebinarEvents(await webinarsRes.json());
        if (progressRes.ok) setProgress(await progressRes.json());
        if (certificatesRes.ok) setCertificates((await certificatesRes.json()).items);
        if (chatRes.ok) setChatMessages(await chatRes.json());
        if (dmsRes.ok) setDirectMessages(await dmsRes.json());
        if (requestsRes.ok) setAcademicRequests(await requestsRes.json());
        if (admissionsRes.ok) setAdmissionRequests(await admissionsRes.json());
        if (logsRes.ok) setSecurityLogs((await logsRes.json()).items);
        if (settingsRes.ok) setSystemSettings(await settingsRes.json());
        if (studentsRes.ok) {
          const { items: users } = await studentsRes.json();
          setStudentsList(users.map((u: any) => ({
            id: u.id, name: u.name, email: u.email,
            municipio: u.municipio, uf: u.uf, areaInteresse: u.areaInteresse, dataCadastro: u.dataCadastro
          })));
        }
        if (instructorsRes.ok) {
          const { items: users } = await instructorsRes.json();
          setProfessorsList(users.map((u: any) => ({ id: u.id, name: u.name })));
        }
        if (enrollmentsRes.ok) setStudentEnrollments(await enrollmentsRes.json());
        if (quizzesRes.ok) setQuizzes(await quizzesRes.json());
        if (quizSubmissionsRes.ok) setQuizSubmissions(await quizSubmissionsRes.json());
        if (forumRes.ok) setForumMessages(await forumRes.json());
        if (exercisesRes.ok) setPracticalExercises(await exercisesRes.json());
        if (exerciseSubmissionsRes.ok) setExerciseSubmissions(await exerciseSubmissionsRes.json());
      } catch (err) {
        console.warn("Servidor Backend Express offline. Inicializado no modo de fallback offline:", err);
      }
    };
    fetchBackendState();
    // Reexecuta sempre que a sessão autenticada mudar (login/logout), já que a maioria das
    // rotas internas agora exige token — sem isso, os dados do usuário só apareceriam após
    // um F5 depois do login.
  }, [authUser?.id]);

  // Save changes to localStorage on any state changes
  useEffect(() => {
    localStorage.setItem('ava_courses', JSON.stringify(courses));
  }, [courses]);

  useEffect(() => {
    localStorage.setItem('ava_student_progress', JSON.stringify(progress));
  }, [progress]);

  useEffect(() => {
    localStorage.setItem('ava_certificates', JSON.stringify(certificates));
  }, [certificates]);

  useEffect(() => {
    localStorage.setItem('ava_chat_messages', JSON.stringify(chatMessages));
  }, [chatMessages]);

  useEffect(() => {
    localStorage.setItem('ava_direct_messages', JSON.stringify(directMessages));
  }, [directMessages]);

  // Synchronize simulated student direct messages across separate dashboards elegantly
  useEffect(() => {
    const handleStorageSync = () => {
      const saved = localStorage.getItem('ava_direct_messages');
      if (saved) {
        try {
          const parsed = JSON.parse(saved);
          if (JSON.stringify(parsed) !== JSON.stringify(directMessages)) {
            setDirectMessages(parsed);
          }
        } catch (err) {
          console.error("Erro sincronizando mensagens do localStorage:", err);
        }
      }
    };
    window.addEventListener('storage', handleStorageSync);
    return () => window.removeEventListener('storage', handleStorageSync);
  }, [directMessages]);

  useEffect(() => {
    localStorage.setItem('ava_quizzes', JSON.stringify(quizzes));
  }, [quizzes]);

  useEffect(() => {
    localStorage.setItem('ava_quiz_submissions', JSON.stringify(quizSubmissions));
  }, [quizSubmissions]);

  useEffect(() => {
    localStorage.setItem('ava_professors', JSON.stringify(professorsList));
  }, [professorsList]);

  useEffect(() => {
    localStorage.setItem('ava_academic_requests', JSON.stringify(academicRequests));
  }, [academicRequests]);

  useEffect(() => {
    localStorage.setItem('ava_students', JSON.stringify(studentsList));
  }, [studentsList]);

  // Rename real (PUT /api/auth/users/{id}/name) — o display em respostas do servidor
  // deriva do User, então basta atualizar a sessão local. Retorna mensagem de erro ou null.
  const updateUserName = async (newName: string): Promise<string | null> => {
    if (!authUser) return 'Sessão expirada. Faça login novamente.';
    try {
      const res = await authFetch(`/api/auth/users/${authUser.id}/name`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: newName })
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        return data.message || 'Não foi possível atualizar o nome.';
      }
      setAuthUser({ ...authUser, name: newName });
      return null;
    } catch (err) {
      console.error('Erro ao renomear usuário:', err);
      return 'Servidor indisponível. Tente novamente em instantes.';
    }
  };

  const calculateAttendancePercent = (courseId: string): number => {
    const course = courses.find((c) => c.id === courseId);
    if (!course) return 0;

    // Aula concluída conta como presença de aula; encontro assistido conta como
    // presença de encontro. A conta ignora id que não existe mais no curso: um
    // resíduo de aula apagada inflava a frequência e podia disparar a emissão
    // automática de certificado abaixo — ver src/utils/courseProgress.ts.
    return frequenciaPercent(course, registroDoAluno(progress, courseId, activeUser.id));
  };

  // Automatic Certificate Issuance Logic when attendance hits the custom required or default 70% minimum!
  useEffect(() => {
    if (activeUser.role !== 'student') return;

    courses.forEach((course) => {
      const attendance = calculateAttendancePercent(course.id);
      const minAttendance = courseMinAttendance(course);
      
      // If student has at least required minimum attendance and doesn't have a certificate for this course yet, issue it automatically!
      if (attendance >= minAttendance) {
        const alreadyIssued = certificates.some(
          (cert) => cert.courseId === course.id && cert.userId === activeUser.id
        );
        if (alreadyIssued) return;

        // O backend recalcula a frequência a partir do progresso real e só emite se o
        // critério for de fato atingido — o cliente nunca decide isso sozinho. A
        // identidade vem do token de sessão, nunca do corpo.
        authFetch('/api/certificates', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ courseId: course.id })
        })
          .then(async (res) => {
            if (!res.ok) return;
            const issued: Certificate = await res.json();
            setCertificates((prev) => (prev.some((c) => c.id === issued.id) ? prev : [...prev, issued]));
          })
          .catch((err) => console.error('Erro ao emitir certificado:', err));
      }
      // NÃO existe ramo "else" que remove o certificado da lista.
      //
      // Havia um: quando a frequência caía abaixo do mínimo, o certificado
      // desaparecia do estado LOCAL — e continuava no banco, com o código de
      // validação funcionando. Ou seja, a tela passava a discordar do registro
      // oficial em silêncio. Certificado emitido é documento: se precisar
      // deixar de valer, isso é revogação no servidor, com trilha de auditoria
      // e decisão de gente — não um filtro no navegador de quem está olhando.
    });
  }, [progress, activeUser.id, courses, activeUser.role]);

  // POST /api/progress sem identidade no corpo — o token decide de quem é o progresso.
  const postProgressUpdate = (updated: StudentProgress) => {
    authFetch('/api/progress', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        courseId: updated.courseId,
        completedLessons: updated.completedLessons,
        attendedLiveSessions: updated.attendedLiveSessions
      })
    }).catch(err => console.error("Erro ao atualizar progresso:", err));
  };

  const toggleLessonCompletion = (courseId: string, lessonId: string) => {
    const userId = activeUser.id;
    setProgress((prev) => {
      const existing = prev.find((p) => p.courseId === courseId && p.userId === userId);
      let updated: StudentProgress;
      let nextState: StudentProgress[];

      if (existing) {
        const isCompleted = existing.completedLessons.includes(lessonId);
        const updatedLessons = isCompleted
          ? existing.completedLessons.filter((id) => id !== lessonId)
          : [...existing.completedLessons, lessonId];
        updated = { ...existing, completedLessons: updatedLessons };
        nextState = prev.map((p) => (p.courseId === courseId && p.userId === userId ? updated : p));
      } else {
        updated = {
          userId,
          studentName: activeUser.name,
          courseId,
          completedLessons: [lessonId],
          attendedLiveSessions: []
        };
        nextState = [...prev, updated];
      }

      postProgressUpdate(updated);

      return nextState;
    });
  };

  const attendLiveSession = (courseId: string, liveSessionId: string) => {
    const userId = activeUser.id;
    setProgress((prev) => {
      const existing = prev.find((p) => p.courseId === courseId && p.userId === userId);
      let updated: StudentProgress;
      let nextState: StudentProgress[];

      if (existing) {
        if (existing.attendedLiveSessions.includes(liveSessionId)) return prev;

        updated = {
          ...existing,
          attendedLiveSessions: [...existing.attendedLiveSessions, liveSessionId]
        };
        nextState = prev.map((p) => (p.courseId === courseId && p.userId === userId ? updated : p));
      } else {
        updated = {
          userId,
          studentName: activeUser.name,
          courseId,
          completedLessons: [],
          attendedLiveSessions: [liveSessionId]
        };
        nextState = [...prev, updated];
      }

      postProgressUpdate(updated);

      return nextState;
    });
  };

  const addCourse = (newCourse: Course) => {
    setCourses((prev) => [...prev, newCourse]);
    authFetch('/api/courses', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newCourse)
    }).catch(err => console.error(err));
  };

  const deleteCourse = (courseId: string) => {
    setCourses((prev) => prev.filter((c) => c.id !== courseId));
    authFetch(`/api/courses/${courseId}`, { method: 'DELETE' }).catch(err => console.error(err));
  };

  // Autoria por instructorId (ADR 10) — o servidor deriva o display do User;
  // o instructorName local é só eco otimista até a próxima hidratação.
  const updateCourseInstructor = (courseId: string, instructorId: string) => {
    const instructorName = professorsList.find((p) => p.id === instructorId)?.name;
    setCourses((prev) =>
      prev.map((course) =>
        course.id === courseId
          ? { ...course, instructorId, instructorName: instructorName ?? course.instructorName }
          : course
      )
    );
    authFetch(`/api/courses/${courseId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ instructorId })
    }).catch(err => console.error(err));
  };

  const updateCourseProps = (courseId: string, updates: Partial<Course>) => {
    setCourses((prev) =>
      prev.map((course) =>
        course.id === courseId ? { ...course, ...updates } : course
      )
    );
    authFetch(`/api/courses/${courseId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(updates)
    }).catch(err => console.error(err));
  };

  const addLessonToCourse = (courseId: string, lessonTitle: string, duration: string, content: string, videoUrl?: string) => {
    setCourses((prev) =>
      prev.map((course) => {
        if (course.id === courseId) {
          const newOrder = course.lessons.length + 1;
          const newLesson = {
            id: `lesson-${courseId}-${Date.now()}`,
            courseId,
            title: lessonTitle,
            duration,
            content,
            videoUrl,
            order: newOrder
          };
          const updatedLessons = [...course.lessons, newLesson];
          authFetch(`/api/courses/${courseId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lessons: updatedLessons })
          }).catch(err => console.error(err));
          return { ...course, lessons: updatedLessons };
        }
        return course;
      })
    );
  };
  
  const updateLesson = (courseId: string, lessonId: string, updates: Partial<Lesson>) => {
    setCourses((prev) =>
      prev.map((course) => {
        if (course.id === courseId) {
          const updatedLessons = course.lessons.map((l) => (l.id === lessonId ? { ...l, ...updates } : l));
          authFetch(`/api/courses/${courseId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lessons: updatedLessons })
          }).catch(err => console.error(err));
          return {
            ...course,
            lessons: updatedLessons
          };
        }
        return course;
      })
    );
  };

  const deleteLesson = (courseId: string, lessonId: string) => {
    setCourses((prev) =>
      prev.map((course) => {
        if (course.id === courseId) {
          const updatedLessons = course.lessons.filter((l) => l.id !== lessonId);
          authFetch(`/api/courses/${courseId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lessons: updatedLessons })
          }).catch(err => console.error(err));
          return {
            ...course,
            lessons: updatedLessons
          };
        }
        return course;
      })
    );
  };

  const addLiveSessionToCourse = (
    courseId: string,
    title: string,
    scheduledAt: string,
    durationMinutes: number,
    meetingLink: string,
    isLive: boolean
  ) => {
    setCourses((prev) =>
      prev.map((course) => {
        if (course.id === courseId) {
          const newSession = {
            id: `live-${courseId}-${Date.now()}`,
            courseId,
            title,
            scheduledAt,
            durationMinutes,
            meetingLink,
            isLive
          };
          const updatedSessions = [...course.liveSessions, newSession];
          authFetch(`/api/courses/${courseId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ liveSessions: updatedSessions })
          }).catch(err => console.error(err));
          return { ...course, liveSessions: updatedSessions };
        }
        return course;
      })
    );
  };

  const removeLiveSession = (courseId: string, sessionId: string) => {
    setCourses((prev) =>
      prev.map((course) => {
        if (course.id === courseId) {
          const updatedSessions = course.liveSessions.filter((s) => s.id !== sessionId);
          authFetch(`/api/courses/${courseId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ liveSessions: updatedSessions })
          }).catch(err => console.error(err));
          return {
            ...course,
            liveSessions: updatedSessions
          };
        }
        return course;
      })
    );
  };

  const setLiveSessionStatus = (courseId: string, sessionId: string, isLive: boolean) => {
    setCourses((prev) =>
      prev.map((course) => {
        if (course.id === courseId) {
          const updatedSessions = course.liveSessions.map((session) =>
            session.id === sessionId ? { ...session, isLive } : session
          );
          authFetch(`/api/courses/${courseId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ liveSessions: updatedSessions })
          }).catch(err => console.error(err));
          return {
            ...course,
            liveSessions: updatedSessions
          };
        }
        return course;
      })
    );
  };

  const sendLiveChatMessage = (sessionId: string, text: string) => {
    const newMessage: ChatMessage = {
      id: `msg-${Date.now()}`,
      sessionId,
      senderName: activeUser.name,
      senderRole: activeUser.role,
      text,
      timestamp: new Date().toISOString()
    };
    setChatMessages((prev) => [...prev, newMessage]);
    authFetch('/api/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newMessage)
    }).catch(err => console.error(err));
  };

  const sendDirectMessage = (studentUserId: string, text: string) => {
    const threadOwnerName = studentUserId === activeUser.id
      ? activeUser.name
      : studentsList.find((s) => s.id === studentUserId)?.name ?? '';
    const newDM: DirectMessage = {
      id: `dm-${Date.now()}`,
      studentUserId,
      studentName: threadOwnerName,
      senderUserId: activeUser.id,
      senderName: activeUser.name,
      senderRole: activeUser.role === 'admin' ? 'instructor' : activeUser.role,
      text,
      timestamp: new Date().toISOString()
    };
    setDirectMessages((prev) => [...prev, newDM]);
    authFetch('/api/dms', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ studentUserId, text })
    }).catch(err => console.error(err));
  };

  /**
   * Grava uma avaliação (nova ou existente) e devolve o desfecho.
   *
   * `POST /api/quizzes` é upsert pela chave `id` no servidor: com um id que já
   * existe ele atualiza título/curso e reconcilia as questões (apaga as que não
   * vieram, atualiza as que vieram com id, cria as novas). Por isso criar e
   * editar usam a MESMA rota — e por isso a edição precisa reenviar o id de cada
   * questão que deve sobreviver, senão o servidor a apaga e recria, o que
   * quebraria a ligação com as respostas já entregues pelos alunos.
   *
   * Diferente do padrão antigo, o estado só muda com o que o servidor devolveu:
   * a versão otimista deixava a avaliação na tela mesmo quando a gravação era
   * recusada, e o professor só descobria no recarregamento.
   */
  const gravaQuiz = async (quiz: Quiz): Promise<QuizWriteResult> => {
    const r = await escreveApi(
      '/api/quizzes',
      'POST',
      quiz,
      'Recurso de avaliações indisponível nesta instalação.'
    );
    if (!r.ok) return { ok: false, error: r.error };

    // O servidor devolve a avaliação já reconciliada (ids definitivos das
    // questões). Usar a resposta evita divergir do banco no primeiro clique.
    const salvo = (r.data && typeof r.data === 'object' ? r.data : quiz) as Quiz;
    setQuizzes((prev) => {
      const jaExiste = prev.some((q) => q.id === salvo.id);

      return jaExiste ? prev.map((q) => (q.id === salvo.id ? salvo : q)) : [...prev, salvo];
    });

    return { ok: true, quiz: salvo };
  };

  const addQuiz = (courseId: string, title: string, questions: QuizQuestion[]) =>
    gravaQuiz({ id: `quiz-${Date.now()}`, courseId, title, questions });

  const updateQuiz = (
    quizId: string,
    courseId: string,
    title: string,
    questions: QuizQuestion[]
  ) => gravaQuiz({ id: quizId, courseId, title, questions });

  /**
   * Apagar avaliação apaga TAMBÉM as respostas já entregues (o servidor remove
   * as QuizSubmission do quiz). Quem chama tem de confirmar com a pessoa antes;
   * aqui só se garante que a tela não finja sucesso quando o servidor recusa.
   */
  const deleteQuiz = async (quizId: string): Promise<QuizWriteResult> => {
    const r = await escreveApi(
      `/api/quizzes/${quizId}`,
      'DELETE',
      undefined,
      'Recurso de avaliações indisponível nesta instalação.'
    );
    if (!r.ok) return { ok: false, error: r.error };

    setQuizzes((prev) => prev.filter((q) => q.id !== quizId));
    setQuizSubmissions((prev) => prev.filter((qs) => qs.quizId !== quizId));

    return { ok: true };
  };

  // Sempre ação do próprio aluno — identidade sai do token; o estado otimista usa activeUser.
  const submitQuiz = async (
    courseId: string,
    quizId: string,
    scorePercent: number,
    passed: boolean,
    answers: Record<string, number>
  ): Promise<QuizResult> => {
    // scorePercent/passed aqui são só otimistas para o feedback imediato na tela.
    // A nota REAL é recalculada no servidor a partir de `answers` (o backend ignora
    // qualquer nota vinda do cliente) — ver LearningService::submitQuiz.
    const otimista: QuizSubmission = {
      id: `sub-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      userId: activeUser.id,
      studentName: activeUser.name,
      courseId,
      quizId,
      scorePercent,
      passed,
      submittedAt: new Date().toLocaleDateString('pt-BR') + ' às ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    };
    setQuizSubmissions((prev) => {
      // Substitui tentativas anteriores do mesmo aluno no mesmo quiz (permite refazer).
      const limpo = prev.filter((sub) => !(sub.userId === activeUser.id && sub.quizId === quizId));
      return [...limpo, otimista];
    });

    try {
      const res = await authFetch('/api/quiz-submissions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quizId, answers })
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        // A tentativa otimista sai da lista: deixá-la ali mostraria ao aluno uma
        // nota que o servidor não registrou. Antes o erro era só console.error.
        setQuizSubmissions((prev) => prev.filter((sub) => sub.id !== otimista.id));
        return { ok: false, error: data.message || 'O servidor não registrou sua tentativa.' };
      }
      const salva = await res.json();
      if (salva && typeof salva.scorePercent === 'number') {
        // Reconcilia com a nota autoritativa do servidor.
        setQuizSubmissions((prev) => prev.map((sub) =>
          sub.id === otimista.id
            ? { ...sub, scorePercent: salva.scorePercent, passed: !!salva.passed, id: salva.id ?? sub.id }
            : sub
        ));
        return { ok: true, scorePercent: salva.scorePercent, passed: !!salva.passed };
      }
      return { ok: true, scorePercent, passed };
    } catch (err) {
      console.error('Erro ao enviar avaliação:', err);
      setQuizSubmissions((prev) => prev.filter((sub) => sub.id !== otimista.id));
      return { ok: false, error: 'Servidor indisponível. Sua tentativa não foi registrada.' };
    }
  };

  const addProfessor = (name: string, password?: string) => {
    // Sem senha explícita: gera uma aleatória (nunca um padrão compartilhado como '5678',
    // que somado ao login por nome permitiria adivinhar credenciais de contas reais).
    const finalPassword = password && password.trim() ? password.trim() : generateInitialPassword();
    setProfessorsList((prev) => {
      if (prev.some((p) => p.name === name)) return prev;
      // Id provisório até a hidratação trazer o id real criado pelo backend.
      return [...prev, { id: `temp-${Date.now()}`, name }];
    });
    // Cria a conta real no backend (hash bcrypt) para o professor poder fazer login de verdade.
    const email = `${name.toLowerCase().replace(/\s+/g, '.')}@avasec.local`;
    registerUser(name, email, finalPassword, 'instructor').catch((err) => console.error('Erro ao registrar professor:', err));
  };

  const deleteProfessor = (name: string) => {
    setProfessorsList((prev) => prev.filter((p) => p.name !== name));
  };

  const addStudent = async (
    name: string,
    email: string,
    password: string | undefined,
    municipio: string | undefined,
    uf: string | undefined,
    areaInteresse: string | undefined,
    dataCadastro: string | undefined,
    cpf: string
  ): Promise<{ ok: boolean; error?: string }> => {
    const finalPassword = password && password.trim() ? password.trim() : generateInitialPassword();
    const finalMunicipio = municipio?.trim() || 'São Paulo';
    const finalUf = uf?.trim() || 'SP';
    const finalArea = areaInteresse?.trim() || 'Tecnologia';
    const finalData = dataCadastro?.trim() || new Date().toISOString().split('T')[0];

    // A conta real vem PRIMEIRO. A inserção otimista na lista local ficava antes
    // e nunca era desfeita: quando a API recusava o cadastro (por exemplo, sem
    // CPF, que ela exige para aluno), o aluno aparecia na tabela da gestão sem
    // existir no banco — e ninguém conseguia logar com ele.
    try {
      const res = await authFetch('/api/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name, email, cpf, password: finalPassword, role: 'student',
          municipio: finalMunicipio, uf: finalUf, areaInteresse: finalArea, dataCadastro: finalData
        })
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        return { ok: false, error: data.message || 'O servidor recusou o cadastro do aluno.' };
      }
    } catch (err) {
      console.error('Erro ao registrar aluno:', err);
      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }

    setStudentsList((prev) => {
      if (prev.some((s) => s.name.toLowerCase() === name.toLowerCase() || s.email.toLowerCase() === email.toLowerCase())) return prev;
      // A senha NÃO entra nesta lista: ela é persistida em localStorage ('ava_students'),
      // e ninguém a lia — era credencial em texto plano guardada no navegador de quem
      // cadastra. O servidor guarda só o hash; para trocar, há o fluxo de redefinição.
      return [...prev, {
        name,
        email,
        municipio: finalMunicipio,
        uf: finalUf,
        areaInteresse: finalArea,
        dataCadastro: finalData
      }];
    });

    return { ok: true };
  };

  const deleteStudent = (name: string) => {
    setStudentsList((prev) => {
      const target = prev.find((s) => s.name === name);
      if (target?.id) {
        authFetch(`/api/auth/users/${target.id}`, { method: 'DELETE' }).catch((err) => console.error(err));
      }
      return prev.filter((s) => s.name !== name);
    });
  };

  // Aluno abre para si (userId omitido → activeUser); staff pode abrir em nome de um
  // aluno passando userId. O POST segue o contrato: identidade extra só quando staff.
  const addAcademicRequest = (req: Omit<AcademicRequest, 'id' | 'status' | 'submittedAt' | 'userId' | 'studentName'> & { userId?: string }) => {
    const userId = req.userId ?? activeUser.id;
    const studentName = userId === activeUser.id
      ? activeUser.name
      : studentsList.find((s) => s.id === userId)?.name ?? '';
    const newRequest: AcademicRequest = {
      type: req.type,
      description: req.description,
      courseTitle: req.courseTitle,
      userId,
      studentName,
      id: `req-${Date.now()}`,
      status: 'pending',
      submittedAt: new Date().toLocaleDateString('pt-BR')
    };
    setAcademicRequests((prev) => [newRequest, ...prev]);
    authFetch('/api/academic-requests', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        type: req.type,
        description: req.description,
        courseTitle: req.courseTitle,
        ...(userId !== activeUser.id ? { userId } : {})
      })
    }).catch(err => console.error(err));
  };

  const updateRequestStatus = (reqId: string, status: 'approved' | 'rejected') => {
    setAcademicRequests((prev) =>
      prev.map((req) => (req.id === reqId ? { ...req, status } : req))
    );
    authFetch(`/api/academic-requests/${reqId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status })
    }).catch(err => console.error(err));
  };

  const [admissionRequests, setAdmissionRequests] = useState<AdmissionRequest[]>(() => {
    const saved = localStorage.getItem('ava_admission_requests');
    let parsed: AdmissionRequest[] | null = null;
    if (saved) {
      try {
        parsed = JSON.parse(saved);
      } catch (e) {
        console.error("Error parsing ava_admission_requests:", e);
      }
    }
    const defaults: AdmissionRequest[] = [
      { id: 'adm-1', userId: MOCK_IDS.lucas, studentName: 'Lucas Santana', courseId: 'course-1', status: 'pending', submittedAt: '03/06/2026' },
      { id: 'adm-2', userId: MOCK_IDS.carolina, studentName: 'Carolina Mendes', courseId: 'course-1', status: 'pending', submittedAt: '03/06/2026' },
      { id: 'adm-3', userId: MOCK_IDS.ana, studentName: 'Ana Souza', courseId: 'course-2', status: 'pending', submittedAt: '03/06/2026' },
    ];

    const source = parsed && Array.isArray(parsed) ? parsed : defaults;
    const seen = new Set<string>();
    const deduped: AdmissionRequest[] = [];
    for (const req of source) {
      if (req && req.id && !seen.has(req.id)) {
        seen.add(req.id);
        deduped.push(req);
      }
    }
    return deduped;
  });

  useEffect(() => {
    localStorage.setItem('ava_admission_requests', JSON.stringify(admissionRequests));
  }, [admissionRequests]);

  const updateAdmissionStatus = (reqId: string, status: 'approved' | 'rejected') => {
    setAdmissionRequests((prev) =>
      prev.map((req) => (req.id === reqId ? { ...req, status } : req))
    );
    authFetch(`/api/admissions/${reqId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status })
    }).catch(err => console.error(err));
  };

  // Aluno solicita para si (userId === activeUser.id → body só com courseId);
  // admin solicita em nome do aluno enviando também o userId.
  const addAdmissionRequest = (userId: string, courseId: string, status: 'pending' | 'approved' | 'rejected' = 'pending') => {
    const studentName = userId === activeUser.id
      ? activeUser.name
      : studentsList.find((s) => s.id === userId)?.name ?? '';
    const newReq: AdmissionRequest = {
      id: `adm-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      userId,
      studentName,
      courseId,
      status,
      submittedAt: new Date().toLocaleDateString('pt-BR')
    };
    setAdmissionRequests(prev => [...prev, newReq]);
    // Sem userId conhecido (ex.: aluno recém-criado, cadastro assíncrono) fica só o eco local.
    if (!userId) return;
    authFetch('/api/admissions', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(userId !== activeUser.id ? { userId, courseId } : { courseId })
    }).catch(err => console.error(err));
  };

  const syncEnrollment = (userId: string, updated: StudentEnrollment) => {
    authFetch(`/api/enrollments/${encodeURIComponent(userId)}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(updated)
    }).catch(err => console.error('Erro ao sincronizar matrícula:', err));
  };

  // Ações de matrícula do PRÓPRIO aluno: a regra (penalidade, critério de conclusão, duplicidade)
  // é decidida pelo SERVIDOR nos endpoints /api/enrollments/self/*. O estado local só reflete a
  // resposta (já indexada pelo userId que ela mesma traz) — nada de calcular no navegador.
  const applySelfEnrollmentResponse = (enrollment: StudentEnrollment) => {
    setStudentEnrollments(prev => ({ ...prev, [enrollment.userId]: enrollment }));
    // O catálogo entrega o material de estudo apenas dos cursos a que o aluno
    // pertence. Matricular/cancelar muda esse conjunto, e a sincronização geral só
    // reage a login/logout — sem este refetch, o aluno acabaria de se matricular e
    // veria a aula sem texto, vídeo nem documentos até recarregar a página.
    void refreshCourses();
  };

  /** Rebusca só o catálogo, para refletir mudança de acesso ao material. */
  const refreshCourses = async () => {
    if (!features.catalogoCursos) return;
    try {
      const res = await authFetch('/api/courses');
      if (res.ok) setCourses(await res.json());
    } catch (err) {
      console.error('Erro ao atualizar catálogo:', err);
    }
  };

  const enrollStudentInCourse = async (_userId: string, courseId: string): Promise<{ ok: boolean; error?: string }> => {
    try {
      const res = await authFetch('/api/enrollments/self/enroll', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ courseId })
      });
      const data = await res.json();
      if (!res.ok) return { ok: false, error: data.message || 'Não foi possível efetuar a matrícula.' };
      applySelfEnrollmentResponse(data.enrollment);
      return { ok: true };
    } catch (err) {
      console.error('Erro ao matricular:', err);
      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  const dropStudentFromCourse = async (_userId: string, courseId: string): Promise<{ ok: boolean; penaltyApplied: boolean; error?: string }> => {
    try {
      const res = await authFetch('/api/enrollments/self/drop', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ courseId })
      });
      const data = await res.json();
      if (!res.ok) return { ok: false, penaltyApplied: false, error: data.message || 'Não foi possível cancelar a matrícula.' };
      applySelfEnrollmentResponse(data.enrollment);
      return { ok: true, penaltyApplied: !!data.penaltyApplied };
    } catch (err) {
      console.error('Erro ao cancelar matrícula:', err);
      return { ok: false, penaltyApplied: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  const completeStudentCourse = async (_userId: string, courseId: string): Promise<{ ok: boolean; error?: string }> => {
    try {
      const res = await authFetch('/api/enrollments/self/complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ courseId })
      });
      const data = await res.json();
      if (!res.ok) return { ok: false, error: data.message || 'Critério de conclusão ainda não atingido.' };
      applySelfEnrollmentResponse(data.enrollment);
      return { ok: true };
    } catch (err) {
      console.error('Erro ao concluir curso:', err);
      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  const clearStudentPenalty = (userId: string) => {
    setStudentEnrollments(prev => {
      const current = prev[userId] || {
        userId,
        studentName: studentsList.find((s) => s.id === userId)?.name ?? '',
        enrolledCourseId: null,
        enrolledAt: null,
        completedCourseIds: [],
        dropOutPenaltyUntil: null,
        canMultiEnroll: false,
        extraCourseIds: []
      };
      const updated = { ...current, dropOutPenaltyUntil: null };
      syncEnrollment(userId, updated);
      return { ...prev, [userId]: updated };
    });
  };

  // Concessão da permissão de matrícula múltipla — só o Admin Superior pode
  // chamar isto; o backend também recusa (403) se o requisitante não for admin.
  const setStudentMultiEnrollPermission = (userId: string, allowed: boolean) => {
    setStudentEnrollments(prev => {
      const current = prev[userId] || {
        userId,
        studentName: studentsList.find((s) => s.id === userId)?.name ?? '',
        enrolledCourseId: null,
        enrolledAt: null,
        completedCourseIds: [],
        dropOutPenaltyUntil: null,
        canMultiEnroll: false,
        extraCourseIds: []
      };
      const updated = { ...current, canMultiEnroll: allowed };
      syncEnrollment(userId, updated);
      return { ...prev, [userId]: updated };
    });
  };

  // Área de gerenciamento de templates de documentos — só Admin Superior lê/edita.
  // Sem estado global: usados só pela tela de edição e pela prévia do certificado.
  const getDocumentTemplate = async (type: DocumentTemplate['type']): Promise<{ ok: boolean; template?: DocumentTemplate; error?: string }> => {
    try {
      const res = await authFetch(`/api/document-templates/${encodeURIComponent(type)}`);
      const data = await res.json();
      if (!res.ok) return { ok: false, error: data.message || 'Não foi possível carregar o template.' };
      return { ok: true, template: data as DocumentTemplate };
    } catch (err) {
      console.error('Erro ao carregar template de documento:', err);
      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  const updateDocumentTemplate = async (
    type: DocumentTemplate['type'],
    updates: Partial<Pick<DocumentTemplate, 'institutionName' | 'institutionLogoPath' | 'signatories' | 'footerText' | 'customHtml'>>
  ): Promise<{ ok: boolean; template?: DocumentTemplate; error?: string }> => {
    try {
      const res = await authFetch(`/api/document-templates/${encodeURIComponent(type)}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updates)
      });
      const data = await res.json();
      if (!res.ok) return { ok: false, error: data.message || 'Não foi possível salvar o template.' };
      return { ok: true, template: data as DocumentTemplate };
    } catch (err) {
      console.error('Erro ao salvar template de documento:', err);
      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  const addForumMessage = (courseId: string, text: string) => {
    if (!text.trim()) return;
    const newMessage: ForumMessage = {
      id: `forum-msg-${Date.now()}`,
      courseId,
      senderUserId: activeUser.id,
      senderName: activeUser.name,
      senderRole: activeUser.role,
      text: text.trim(),
      timestamp: new Date().toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
      likes: 0,
      likedBy: []
    };
    setForumMessages(prev => [...prev, newMessage]);
    authFetch('/api/forum', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newMessage)
    }).catch(err => console.error(err));
  };

  const toggleForumMessageLike = (messageId: string) => {
    const userId = activeUser.id;
    setForumMessages(prev => prev.map(msg => {
      if (msg.id === messageId) {
        const hasLiked = msg.likedBy.includes(userId);
        const newLikedBy = hasLiked
          ? msg.likedBy.filter(u => u !== userId)
          : [...msg.likedBy, userId];
        return { ...msg, likedBy: newLikedBy, likes: newLikedBy.length };
      }
      return msg;
    }));
    // O servidor decide a autoria do "curtir" pela sessão autenticada, não pelo corpo enviado.
    authFetch(`/api/forum/${messageId}/like`, { method: 'PUT' }).catch(err => console.error(err));
  };

  const deleteForumMessage = (messageId: string) => {
    setForumMessages(prev => prev.filter(msg => msg.id !== messageId));
    authFetch(`/api/forum/${messageId}`, { method: 'DELETE' }).catch(err => console.error(err));
  };

  /**
   * Fala com a API e só depois mexe no estado local.
   *
   * O padrão anterior era o inverso — estado otimista primeiro, `.catch()`
   * depois — e `.catch()` não dispara em 4xx. Com a flag desligada TODA chamada
   * daqui voltava 404 e nada disso aparecia: exercício, entrega e nota viviam
   * apenas no localStorage de quem clicou.
   */
  /**
   * Escrita autenticada que DEVOLVE o desfecho em vez de engoli-lo.
   *
   * O padrão antigo (`authFetch(...).catch(console.error)`) não fecha o buraco:
   * `.catch` só dispara em falha de rede, então 403/404/422 seguiam silenciosos
   * enquanto o estado otimista já tinha mudado a tela. Quem chama aqui tem de
   * decidir o que fazer com `ok: false`.
   *
   * `rotulo404` existe porque nas rotas atrás de feature flag um 404 quer dizer
   * "recurso desligado nesta instalação", não "não existe".
   */
  const escreveApi = async (
    url: string,
    method: 'POST' | 'PUT' | 'DELETE',
    body?: unknown,
    rotulo404 = 'Recurso indisponível nesta instalação.'
    // Forma única em vez de união discriminada: este tsconfig não liga
    // `strictNullChecks`, e sem ele o TypeScript não estreita `{ok:true}|{ok:false}`.
  ): Promise<{ ok: boolean; data?: unknown; error?: string }> => {
    try {
      const res = await authFetch(url, {
        method,
        ...(body === undefined ? {} : {
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        }),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        const padrao = res.status === 404 ? rotulo404 : 'O servidor recusou a operação.';
        return { ok: false, error: data.message || padrao };
      }
      return { ok: true, data: await res.json().catch(() => null) };
    } catch (err) {
      console.error(`Erro em ${method} ${url}:`, err);
      return { ok: false, error: 'Servidor indisponível. Tente novamente em instantes.' };
    }
  };

  const escreveExercicio = (
    url: string,
    method: 'POST' | 'PUT' | 'DELETE',
    body?: unknown
  ) => escreveApi(url, method, body, 'Recurso de exercícios práticos indisponível nesta instalação.');

  const addPracticalExercise = async (
    courseId: string,
    title: string,
    description: string,
    instructions: string,
    maxPoints: number,
    dueDate?: string
  ): Promise<ExerciseResult> => {
    const novo: PracticalExercise = {
      id: `exercise-${Date.now()}`, courseId, title, description, instructions, maxPoints, dueDate,
    };
    const res = await escreveExercicio('/api/exercises', 'POST', novo);
    if (!res.ok) return { ok: false, error: res.error };

    // O servidor é a autoridade sobre o registro gravado (id, normalizações).
    const criado = (res.data as PracticalExercise | null) ?? novo;
    setPracticalExercises((prev) => [...prev, criado]);
    return { ok: true };
  };

  const updatePracticalExercise = async (
    exerciseId: string,
    updates: Partial<PracticalExercise>
  ): Promise<ExerciseResult> => {
    const atual = practicalExercises.find((ex) => ex.id === exerciseId);
    if (!atual) return { ok: false, error: 'Exercício não encontrado.' };

    // PUT /exercises/{id}. Antes ia um POST na rota de criação — com id repetido,
    // o servidor tratava como criação e a edição não acontecia.
    const res = await escreveExercicio(`/api/exercises/${exerciseId}`, 'PUT', { ...atual, ...updates });
    if (!res.ok) return { ok: false, error: res.error };

    const salvo = (res.data as PracticalExercise | null) ?? { ...atual, ...updates };
    setPracticalExercises((prev) => prev.map((ex) => (ex.id === exerciseId ? salvo : ex)));
    return { ok: true };
  };

  const deletePracticalExercise = async (exerciseId: string): Promise<ExerciseResult> => {
    const res = await escreveExercicio(`/api/exercises/${exerciseId}`, 'DELETE');
    if (!res.ok) return { ok: false, error: res.error };

    setPracticalExercises((prev) => prev.filter((ex) => ex.id !== exerciseId));
    setExerciseSubmissions((prev) => prev.filter((sub) => sub.exerciseId !== exerciseId));
    return { ok: true };
  };

  /** Sempre ação do próprio aluno — a identidade sai do token, não do corpo. */
  const submitExercise = async (
    exerciseId: string,
    submissionText: string,
    fileUrl?: string,
    fileName?: string
  ): Promise<ExerciseResult> => {
    const res = await escreveExercicio('/api/exercise-submissions', 'POST', {
      exerciseId, submissionText, fileUrl, fileName,
    });
    if (!res.ok) return { ok: false, error: res.error };

    // `submittedAt` e `status` vêm do servidor: data de entrega é registro
    // acadêmico e não pode ser o relógio do navegador do aluno.
    const salva = res.data as ExerciseSubmission | null;
    if (salva) {
      setExerciseSubmissions((prev) => {
        const i = prev.findIndex((sub) => sub.id === salva.id
          || (sub.exerciseId === salva.exerciseId && sub.userId === salva.userId));
        if (i < 0) return [...prev, salva];
        return prev.map((sub, idx) => (idx === i ? salva : sub));
      });
    }
    return { ok: true };
  };

  const gradeSubmission = async (
    submissionId: string,
    score: number,
    feedback: string,
    status: 'approved' | 'rejected' | 'revision'
  ): Promise<ExerciseResult> => {
    // PUT /exercise-submissions/{id}/grade. Antes ia um POST em
    // /exercise-submissions, que é a rota de ENTREGA e só aceita role:student —
    // lançar nota respondia 403 e a nota nunca saía do navegador do professor.
    const res = await escreveExercicio(`/api/exercise-submissions/${submissionId}/grade`, 'PUT', {
      score, feedback, status,
    });
    if (!res.ok) return { ok: false, error: res.error };

    const salva = res.data as ExerciseSubmission | null;
    if (salva) {
      setExerciseSubmissions((prev) => prev.map((sub) => (sub.id === submissionId ? salva : sub)));
    }
    return { ok: true };
  };

  return (
    <LMSContext.Provider
      value={{
        courses,
        activeUser,
        authUser,
        loginWithPassword,
        registerUser,
        logoutAuth,
        changePassword,
        adminResetPassword,
        progress,
        certificates,
        chatMessages,
        directMessages,
        quizzes,
        quizSubmissions,
        professorsList,
        studentsList,
        academicRequests,
        categoriesList,
        libraryItems,
        webinarEvents,
        accessibilitySettings,
        updateUserName,
        toggleLessonCompletion,
        attendLiveSession,
        calculateAttendancePercent,
        addCourse,
        deleteCourse,
        updateCourseInstructor,
        updateCourseProps,
        addLessonToCourse,
        updateLesson,
        deleteLesson,
        addLiveSessionToCourse,
        removeLiveSession,
        sendLiveChatMessage,
        setLiveSessionStatus,
        sendDirectMessage,
        addQuiz,
        updateQuiz,
        deleteQuiz,
        submitQuiz,
        addProfessor,
        deleteProfessor,
        addStudent,
        deleteStudent,
        addAcademicRequest,
        updateRequestStatus,
        addCategory,
        updateAccessibilitySettings,
        addLibraryItem,
        addWebinarEvent,
        deleteWebinarEvent,
        isSpeechEnabled,
        setIsSpeechEnabled,
        currentLang,
        setCurrentLang,
        textSizeMultiplier,
        setTextSizeMultiplier,
        systemSettings,
        updateSystemSettings,
        activeDashboardTab,
        setActiveDashboardTab,
        admissionRequests,
        addAdmissionRequest,
        updateAdmissionStatus,
        securityLogs,
        addSecurityLog,
        clearSecurityLogs,
        studentEnrollments,
        enrollStudentInCourse,
        dropStudentFromCourse,
        completeStudentCourse,
        clearStudentPenalty,
        setStudentMultiEnrollPermission,
        sitePageContent,
        sitePageSchema,
        updateSitePageContent,
        getDocumentTemplate,
        updateDocumentTemplate,
        forumMessages,
        addForumMessage,
        toggleForumMessageLike,
        deleteForumMessage,
        practicalExercises,
        exerciseSubmissions,
        addPracticalExercise,
        updatePracticalExercise,
        deletePracticalExercise,
        submitExercise,
        gradeSubmission,
      }}
    >
      {children}
    </LMSContext.Provider>
  );
};

export const useLMS = () => {
  const context = useContext(LMSContext);
  if (!context) {
    throw new Error('useLMS deve ser usado dentro de um LMSProvider');
  }
  return context;
};
