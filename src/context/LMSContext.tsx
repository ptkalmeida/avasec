/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { createContext, useContext, useState, useEffect } from 'react';
import { Course, LMSState, StudentProgress, Certificate, ChatMessage, DirectMessage, Quiz, QuizQuestion, QuizSubmission, AcademicRequest, LibraryItem, WebinarEvent, AccessibilitySettings, AdmissionRequest, SecurityLog, StudentEnrollment, ForumMessage, Lesson } from '../types';
import { INITIAL_COURSES, INITIAL_LIBRARY, INITIAL_WEBINARS } from '../data/mockData';

interface LMSContextProps {
  courses: Course[];
  activeUser: { name: string; role: 'student' | 'instructor' | 'admin' };
  progress: StudentProgress[];
  certificates: Certificate[];
  chatMessages: ChatMessage[];
  directMessages: DirectMessage[];
  quizzes: Quiz[];
  quizSubmissions: QuizSubmission[];
  professorsList: string[];
  studentsList: { name: string; email: string; password?: string }[];
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
  toggleUserRole: () => void;
  updateUserName: (newName: string) => void;
  toggleLessonCompletion: (courseId: string, lessonId: string) => void;
  attendLiveSession: (courseId: string, liveSessionId: string) => void;
  calculateAttendancePercent: (courseId: string) => number;
  addCourse: (course: Course) => void;
  deleteCourse: (courseId: string) => void;
  updateCourseInstructor: (courseId: string, instructorName: string) => void;
  updateCourseProps: (courseId: string, updates: Partial<Course>) => void;
  addLessonToCourse: (courseId: string, lessonTitle: string, duration: string, content: string, videoUrl?: string) => void;
  updateLesson: (courseId: string, lessonId: string, updates: Partial<Lesson>) => void;
  deleteLesson: (courseId: string, lessonId: string) => void;
  addLiveSessionToCourse: (courseId: string, title: string, scheduledAt: string, durationMinutes: number, meetingLink: string, isLive: boolean) => void;
  removeLiveSession: (courseId: string, sessionId: string) => void;
  sendLiveChatMessage: (sessionId: string, text: string) => void;
  setLiveSessionStatus: (courseId: string, sessionId: string, isLive: boolean) => void;
  sendDirectMessage: (studentName: string, text: string) => void;
  addQuiz: (courseId: string, title: string, questions: QuizQuestion[]) => void;
  deleteQuiz: (quizId: string) => void;
  submitQuiz: (studentName: string, courseId: string, quizId: string, scorePercent: number, passed: boolean) => QuizSubmission;
  addProfessor: (name: string, password?: string) => void;
  deleteProfessor: (name: string) => void;
  addStudent: (name: string, email: string, password?: string) => void;
  deleteStudent: (name: string) => void;
  setUserProfile: (name: string, role: 'student' | 'instructor' | 'admin') => void;
  addAcademicRequest: (req: Omit<AcademicRequest, 'id' | 'status' | 'submittedAt'>) => void;
  updateRequestStatus: (reqId: string, status: 'approved' | 'rejected') => void;
  addCategory: (categoryName: string) => void;
  updateAccessibilitySettings: (updates: Partial<AccessibilitySettings>) => void;
  addLibraryItem: (item: Omit<LibraryItem, 'id'>) => void;
  addWebinarEvent: (webinar: Omit<WebinarEvent, 'id'>) => void;
  systemSettings: {
    allowDirectMessages: boolean;
    allowGlobalChat: boolean;
    openEnrollment: boolean;
    autoCertify: boolean;
    autoArchiveDuration: string;
    liveClassRecording: boolean;
  };
  updateSystemSettings: (updates: Partial<LMSContextProps['systemSettings']>) => void;
  activeDashboardTab: 'general' | 'messages' | 'certificates' | 'documents' | 'library' | 'events' | 'settings' | 'curriculum' | 'students';
  setActiveDashboardTab: (tab: 'general' | 'messages' | 'certificates' | 'documents' | 'library' | 'events' | 'settings' | 'curriculum' | 'students') => void;
  getYouTubeEmbedUrl: (url: string) => string | null;
  admissionRequests: AdmissionRequest[];
  addAdmissionRequest: (studentName: string, courseId: string, status?: 'pending' | 'approved' | 'rejected') => void;
  updateAdmissionStatus: (reqId: string, status: 'approved' | 'rejected') => void;
  securityLogs: SecurityLog[];
  addSecurityLog: (action: string, details: string, status?: 'SUCCESS' | 'WARNING' | 'FAILED') => void;
  clearSecurityLogs: () => void;
  studentEnrollments: { [studentName: string]: StudentEnrollment };
  enrollStudentInCourse: (studentName: string, courseId: string, customEnrolledAt?: string) => void;
  dropStudentFromCourse: (studentName: string, courseId: string, simulatedDaysElapsed?: number) => boolean;
  completeStudentCourse: (studentName: string, courseId: string) => void;
  clearStudentPenalty: (studentName: string) => void;
  forumMessages: ForumMessage[];
  addForumMessage: (courseId: string, text: string) => void;
  toggleForumMessageLike: (messageId: string, userName: string) => void;
  deleteForumMessage: (messageId: string) => void;
}

const LMSContext = createContext<LMSContextProps | undefined>(undefined);

export const LMSProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
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

  const [activeUser, setActiveUser] = useState<{ name: string; role: 'student' | 'instructor' | 'admin' }>(() => {
    const saved = localStorage.getItem('ava_active_user');
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        if (parsed.role === 'instructor') {
          parsed.name = 'Gestor de Cursos';
        }
        return parsed;
      } catch (e) {}
    }
    return { name: 'João Silva', role: 'student' };
  });

  const [professorsList, setProfessorsList] = useState<string[]>(() => {
    return ['Gestor de Cursos'];
  });

  const [studentsList, setStudentsList] = useState<{ name: string; email: string; password?: string }[]>(() => {
    const defaultStudents = [
      { name: 'João Silva', email: 'joao.silva@lms.edu', password: '1234' },
      { name: 'Gabriel Rodrigues', email: 'gabriel.rodrigues@lms.edu', password: '1234' },
      { name: 'Beatriz Costa', email: 'beatriz.c@lms.edu', password: '1234' },
      { name: 'Sofia Rocha', email: 'sofia.rocha@lms.edu', password: '1234' },
      { name: 'Ana Souza', email: 'ana.souza@lms.edu', password: '1234' },
      { name: 'Lucas Santana', email: 'lucas.santana@lms.edu', password: '1234' },
      { name: 'Carolina Mendes', email: 'carol.mendes@lms.edu', password: '1234' }
    ];
    const saved = localStorage.getItem('ava_students');
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        if (Array.isArray(parsed)) {
          const seenEmails = new Set<string>();
          const dedupedParsed: { name: string; email: string; password?: string }[] = [];
          
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
          courseId: 'course-1',
          completedLessons: ['lesson-1-1', 'lesson-1-2', 'lesson-1-3'], // 3 out of 5 lessons completed initially (60% lesson prog)
          attendedLiveSessions: ['live-1-2'], // Attended 1 out of 2 live sessions
        },
        {
          courseId: 'course-2',
          completedLessons: ['lesson-2-1'],
          attendedLiveSessions: [],
        }
      ];
    }

    const seen = new Set<string>();
    const deduped: StudentProgress[] = [];
    for (const p of parsed) {
      if (!seen.has(p.courseId)) {
        seen.add(p.courseId);
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
      const key = `${cert.courseId}-${cert.studentName}`;
      if (!seenKeys.has(key) && !seenIds.has(cert.id)) {
        seenKeys.add(key);
        seenIds.add(cert.id);
        deduped.push(cert);
      }
    }
    return deduped;
  });

  const [studentEnrollments, setStudentEnrollments] = useState<{[studentName: string]: StudentEnrollment}>(() => {
    const saved = localStorage.getItem('ava_student_enrollments');
    if (saved) {
      try {
        return JSON.parse(saved);
      } catch (e) {
        console.error(e);
      }
    }
    return {
      'João Silva': {
        enrolledCourseId: 'course-1',
        enrolledAt: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString(),
        completedCourseIds: [],
        dropOutPenaltyUntil: null
      },
      'Gabriel Rodrigues': {
        enrolledCourseId: 'course-2',
        enrolledAt: new Date(Date.now() - 10 * 24 * 60 * 60 * 1000).toISOString(),
        completedCourseIds: [],
        dropOutPenaltyUntil: null
      },
      'Beatriz Costa': {
        enrolledCourseId: null,
        enrolledAt: null,
        completedCourseIds: ['course-1'],
        dropOutPenaltyUntil: null
      },
      'Sofia Rocha': {
        enrolledCourseId: null,
        enrolledAt: null,
        completedCourseIds: [],
        dropOutPenaltyUntil: new Date(Date.now() + 15 * 24 * 60 * 60 * 1000).toISOString()
      }
    };
  });

  useEffect(() => {
    localStorage.setItem('ava_student_enrollments', JSON.stringify(studentEnrollments));
  }, [studentEnrollments]);

  const [quizzes, setQuizzes] = useState<Quiz[]>(() => {
    const saved = localStorage.getItem('ava_quizzes');
    if (saved) return JSON.parse(saved);
    return [
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
            correctOptionIndex: 0
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
            correctOptionIndex: 1
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
            correctOptionIndex: 2
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
            correctOptionIndex: 2
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
            correctOptionIndex: 1
          }
        ]
      }
    ];
  });

  const [activeDashboardTab, setActiveDashboardTab] = useState<'general' | 'messages' | 'certificates' | 'documents' | 'library' | 'events' | 'settings' | 'curriculum' | 'students'>('general');
  const [libraryItems, setLibraryItems] = useState<LibraryItem[]>(() => {
    const saved = localStorage.getItem('ava_library_items');
    return saved ? JSON.parse(saved) : INITIAL_LIBRARY;
  });
  const [webinarEvents, setWebinarEvents] = useState<WebinarEvent[]>(() => {
    const saved = localStorage.getItem('ava_webinar_events');
    return saved ? JSON.parse(saved) : INITIAL_WEBINARS;
  });

  const addLibraryItem = (item: Omit<LibraryItem, 'id'>) => {
    const newItem = { ...item, id: `lib-${Date.now()}` };
    setLibraryItems(prev => [newItem, ...prev]);
    fetch('/api/library', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newItem)
    }).catch(err => console.error(err));
  };

  const addWebinarEvent = (webinar: Omit<WebinarEvent, 'id'>) => {
    const newWebinar = { ...webinar, id: `web-${Date.now()}` };
    setWebinarEvents(prev => [newWebinar, ...prev]);
    fetch('/api/webinars', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newWebinar)
    }).catch(err => console.error(err));
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
    return saved ? JSON.parse(saved) : [];
  });

  const [forumMessages, setForumMessages] = useState<ForumMessage[]>(() => {
    const saved = localStorage.getItem('ava_forum_messages');
    if (saved) {
      try {
        return JSON.parse(saved);
      } catch (e) {
        console.error(e);
      }
    }
    return [
      {
        id: 'forum-msg-1',
        courseId: 'course-1',
        senderName: 'Sofia Rocha',
        senderRole: 'student',
        text: 'Oi pessoal! Alguém tem dicas sobre como aplicar a heurística de Prevenção de Erros em formulários longos em nossa aplicação?',
        timestamp: '15/06/2026, 14:32',
        likes: 3,
        likedBy: ['João Silva', 'Gabriel Rodrigues']
      },
      {
        id: 'forum-msg-2',
        courseId: 'course-1',
        senderName: 'João Silva',
        senderRole: 'student',
        text: 'Oi Sofia! Geralmente desabilitar o botão de continuar até que os campos de inputs obrigatórios estejam com formatos válidos ajuda imensamente, além de exibir feedback visual imediato.',
        timestamp: '15/06/2026, 14:48',
        likes: 5,
        likedBy: ['Sofia Rocha', 'Gabriel Rodrigues', 'Beatriz Costa']
      },
      {
        id: 'forum-msg-3',
        courseId: 'course-1',
        senderName: 'Gestor de Cursos',
        senderRole: 'instructor',
        text: 'Excelente discussão e fomento de ideias! Lembrem-se também de detalhar os erros de forma humanizada ao invés de usar códigos enigmáticos como "Error 412: Campo Requerido" (Heurística de Diagnóstico e Recuperação de Erros).',
        timestamp: '15/06/2026, 16:10',
        likes: 8,
        likedBy: ['Sofia Rocha', 'João Silva', 'Gabriel Rodrigues', 'Beatriz Costa']
      },
      {
        id: 'forum-msg-4',
        courseId: 'course-2',
        senderName: 'Gabriel Rodrigues',
        senderRole: 'student',
        text: 'Fala galera de Vídeo Mapping! Alguém que já trabalha na área indica algum projetor bacana para início de carreira ou instalações domésticas em paredes brancas simples?',
        timestamp: '16/06/2026, 10:15',
        likes: 2,
        likedBy: ['João Silva']
      },
      {
        id: 'forum-msg-5',
        courseId: 'course-2',
        senderName: 'Gestor de Cursos',
        senderRole: 'instructor',
        text: 'Olá Gabriel! Para superfícies brancas internas convencionais de baixa iluminação, projetores Epson de curta distância (Short Throw) com pelo menos 3000 ANSI Lumens atendem o alinhamento com folga. Desative o HMR e aproveite o alinhamento de canais!',
        timestamp: '16/06/2026, 11:02',
        likes: 4,
        likedBy: ['Gabriel Rodrigues', 'João Silva']
      }
    ];
  });

  useEffect(() => {
    localStorage.setItem('ava_forum_messages', JSON.stringify(forumMessages));
  }, [forumMessages]);

  const [academicRequests, setAcademicRequests] = useState<AcademicRequest[]>(() => {
    const saved = localStorage.getItem('ava_academic_requests');
    if (saved) return JSON.parse(saved);
    return [
      {
        id: 'req-1',
        studentName: 'João Silva',
        type: 'certificado',
        description: 'Solicito a emissão do certificado prioritário do curso de Design de Interfaces de Alta Performance para comprovação de horas complementares na graduação.',
        status: 'pending',
        submittedAt: '24/05/2026',
        courseTitle: 'Design de Interfaces de Alta Performance'
      },
      {
        id: 'req-2',
        studentName: 'Ana Souza',
        type: 'historico',
        description: 'Necessito do envio do meu Histórico Escolar Acadêmico oficial em PDF referente ao meu progresso acumulado na plataforma para validação de estágio obrigatório.',
        status: 'pending',
        submittedAt: '25/05/2026'
      },
      {
        id: 'req-3',
        studentName: 'Lucas Santana',
        type: 'matricula',
        description: 'Não consigo acessar as aulas do curso de Desenvolvimento de Servidores com Node.js e Express. Solicito liberação manual da coordenação.',
        status: 'approved',
        submittedAt: '26/05/2026',
        courseTitle: 'Desenvolvimento de Servidores com Node.js e Express'
      }
    ];
  });

  const [chatMessages, setChatMessages] = useState<ChatMessage[]>(() => {
    const saved = localStorage.getItem('ava_chat_messages');
    if (saved) return JSON.parse(saved);
    return [
      {
        id: 'msg-1',
        sessionId: 'live-1-1',
        senderName: 'Gestor de Cursos',
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
        senderName: 'Gestor de Cursos',
        senderRole: 'instructor',
        text: 'Iniciando em breve nossa aula prática de Express APIs!',
        timestamp: new Date().toISOString()
      }
    ];
  });

  const [directMessages, setDirectMessages] = useState<DirectMessage[]>(() => {
    const defaultDMs = [
      {
        id: 'dm-1',
        studentName: 'João Silva',
        senderName: 'João Silva',
        senderRole: 'student',
        text: 'Olá Gestor, tudo bem? Estou gostando muito do curso de UX! Quando teremos o próximo feedback de portfólios?',
        timestamp: new Date(Date.now() - 3600000 * 5).toISOString() // 5h ago
      },
      {
        id: 'dm-2',
        studentName: 'João Silva',
        senderName: 'Gestor de Cursos',
        senderRole: 'instructor',
        text: 'Olá João! Que ótimo que está curtindo. Teremos uma mentoria sobre isso hoje mesmo às 19:30, mas você pode também agendar um horário direto comigo se precisar!',
        timestamp: new Date(Date.now() - 3600000 * 4).toISOString() // 4h ago
      },
      {
        id: 'dm-3',
        studentName: 'Gabriel Rodrigues',
        senderName: 'Gabriel Rodrigues',
        senderRole: 'student',
        text: 'Olá tutor Gestor! Enviei o link do meu protótipo no Figma para avaliação. Poderia dar uma olhada no fluxo de navegação?',
        timestamp: new Date(Date.now() - 3600000 * 3).toISOString() // 3h ago
      },
      {
        id: 'dm-4',
        studentName: 'Beatriz Costa',
        senderName: 'Beatriz Costa',
        senderRole: 'student',
        text: 'Professor, tenho uma dúvida conceitual sobre a prestação de contas de nosso coletivo para editais da Lei Paulo Gustavo. Existe algum modelo de planilha que possamos seguir?',
        timestamp: new Date(Date.now() - 3600000 * 2.5).toISOString()
      },
      {
        id: 'dm-5',
        studentName: 'Sofia Rocha',
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
          const merged = [...parsed];
          defaultDMs.forEach(item => {
            if (!merged.some(m => m.id === item.id)) {
              merged.push(item);
            }
          });
          return merged;
        }
      } catch (e) {
        console.error(e);
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
      fetch('/api/system-settings', {
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

  const [securityLogs, setSecurityLogs] = useState<SecurityLog[]>(() => {
    const saved = localStorage.getItem('ava_security_logs');
    if (saved) return JSON.parse(saved);
    return [
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
        user: 'Gestor de Cursos',
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
  });

  const addSecurityLog = (action: string, details: string, status: 'SUCCESS' | 'WARNING' | 'FAILED' = 'SUCCESS') => {
    const currentFormattedTime = new Date().toLocaleTimeString('pt-BR') + ' ' + new Date().toLocaleDateString('pt-BR');
    const randomIp = '192.168.42.' + Math.floor(Math.random() * 254 + 1);
    const simulatedDevice = navigator.userAgent?.includes('Mobile') ? 'Chrome / iOS (Dispositivos Móveis)' : 'Chrome / Windows (Terminal Homologado)';
    
    // Fallback names based on role
    const userName = activeUser ? activeUser.name : 'Visitante Anônimo';
    const userRole = activeUser ? activeUser.role : 'student';

    const newLog: SecurityLog = {
      id: `log-${Date.now()}`,
      timestamp: currentFormattedTime,
      user: userName,
      role: userRole,
      ipAddress: randomIp,
      device: simulatedDevice,
      action,
      details,
      status
    };
    
    setSecurityLogs((prev) => [newLog, ...prev].slice(0, 50));
    fetch('/api/security-logs', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newLog)
    }).catch(err => console.error("Erro ao sincronizar log com backend:", err));
  };

  const clearSecurityLogs = () => {
    setSecurityLogs([]);
    fetch('/api/security-logs', { method: 'DELETE' })
      .catch((err) => console.error("Erro ao limpar logs com backend:", err));
  };

  useEffect(() => {
    localStorage.setItem('ava_security_logs', JSON.stringify(securityLogs));
  }, [securityLogs]);

  // Sincronização inicial do aplicativo com o backend Express ao montar o contexto
  useEffect(() => {
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
          settingsRes
        ] = await Promise.all([
          fetch('/api/courses'),
          fetch('/api/library'),
          fetch('/api/webinars'),
          fetch('/api/progress'),
          fetch('/api/certificates'),
          fetch('/api/chat'),
          fetch('/api/dms'),
          fetch('/api/academic-requests'),
          fetch('/api/admissions'),
          fetch('/api/security-logs'),
          fetch('/api/system-settings')
        ]);

        if (coursesRes.ok) setCourses(await coursesRes.json());
        if (libraryRes.ok) setLibraryItems(await libraryRes.json());
        if (webinarsRes.ok) setWebinarEvents(await webinarsRes.json());
        if (progressRes.ok) setProgress(await progressRes.json());
        if (certificatesRes.ok) setCertificates(await certificatesRes.json());
        if (chatRes.ok) setChatMessages(await chatRes.json());
        if (dmsRes.ok) setDirectMessages(await dmsRes.json());
        if (requestsRes.ok) setAcademicRequests(await requestsRes.json());
        if (admissionsRes.ok) setAdmissionRequests(await admissionsRes.json());
        if (logsRes.ok) setSecurityLogs(await logsRes.json());
        if (settingsRes.ok) setSystemSettings(await settingsRes.json());
      } catch (err) {
        console.warn("Servidor Backend Express offline. Inicializado no modo de fallback offline:", err);
      }
    };
    fetchBackendState();
  }, []);

  // Save changes to localStorage on any state changes
  useEffect(() => {
    localStorage.setItem('ava_courses', JSON.stringify(courses));
  }, [courses]);

  useEffect(() => {
    localStorage.setItem('ava_active_user', JSON.stringify(activeUser));
  }, [activeUser]);

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

  const toggleUserRole = () => {
    setActiveUser((prev) => {
      const newRole = prev.role === 'student' ? 'instructor' : 'student';
      const newName = newRole === 'student' ? 'João Silva' : 'Gestor de Cursos';
      return { name: newName, role: newRole };
    });
  };

  const updateUserName = (newName: string) => {
    setActiveUser((prev) => ({ ...prev, name: newName }));
  };

  const calculateAttendancePercent = (courseId: string): number => {
    const course = courses.find((c) => c.id === courseId);
    if (!course) return 0;

    const totalLessonsCount = course.lessons.length;
    const totalLiveCount = course.liveSessions.length;
    const totalActivities = totalLessonsCount + totalLiveCount;

    if (totalActivities === 0) return 0;

    const userProgress = progress.find((p) => p.courseId === courseId);
    if (!userProgress) return 0;

    // A lesson completion acts as "attendance" of lessons, and attending live sessions accounts for meetings
    const completedCount = userProgress.completedLessons.length;
    const attendedLiveCount = userProgress.attendedLiveSessions.length;

    const totalAttended = completedCount + attendedLiveCount;
    const percent = Math.min(100, Math.round((totalAttended / totalActivities) * 100));
    return percent;
  };

  // Automatic Certificate Issuance Logic when attendance hits the custom required or default 70% minimum!
  useEffect(() => {
    if (activeUser.role !== 'student') return;

    courses.forEach((course) => {
      const attendance = calculateAttendancePercent(course.id);
      const minAttendance = course.minAttendance !== undefined ? course.minAttendance : 70;
      
      // If student has at least required minimum attendance and doesn't have a certificate for this course yet, issue it automatically!
      if (attendance >= minAttendance) {
        setCertificates((prev) => {
          const alreadyIssued = prev.some(
            (cert) => cert.courseId === course.id && cert.studentName === activeUser.name
          );

          if (alreadyIssued) return prev;

          const hashHex = Array.from({ length: 16 }, () =>
            Math.floor(Math.random() * 16).toString(16)
          ).join('').toUpperCase();

          const newCertificate: Certificate = {
            id: `cert-${course.id}-${hashHex}`,
            studentName: activeUser.name,
            courseId: course.id,
            courseTitle: course.title,
            issueDate: new Date().toLocaleDateString('pt-BR'),
            attendancePercent: attendance,
            verificationHash: `AVA-${hashHex}`
          };

          fetch('/api/certificates', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(newCertificate)
          }).catch(err => console.error("Erro ao sincronizar certificado:", err));

          return [...prev, newCertificate];
        });
      } else {
        // If they became un-qualified (unselected lesson and went below minAttendance), remove certificate
        // to stay dynamically accurate in state simulation, unless they like it
        setCertificates((prev) => {
          const alreadyIssued = prev.some(
            (cert) => cert.courseId === course.id && cert.studentName === activeUser.name
          );
          if (alreadyIssued) {
            return prev.filter((cert) => !(cert.courseId === course.id && cert.studentName === activeUser.name));
          }
          return prev;
        });
      }
    });
  }, [progress, activeUser.name, courses, activeUser.role]);

  const toggleLessonCompletion = (courseId: string, lessonId: string) => {
    setProgress((prev) => {
      const existing = prev.find((p) => p.courseId === courseId);
      let updated: StudentProgress;
      let nextState: StudentProgress[];

      if (existing) {
        const isCompleted = existing.completedLessons.includes(lessonId);
        const updatedLessons = isCompleted
          ? existing.completedLessons.filter((id) => id !== lessonId)
          : [...existing.completedLessons, lessonId];
        updated = { ...existing, completedLessons: updatedLessons };
        nextState = prev.map((p) => (p.courseId === courseId ? updated : p));
      } else {
        updated = {
          courseId,
          completedLessons: [lessonId],
          attendedLiveSessions: []
        };
        nextState = [...prev, updated];
      }

      fetch('/api/progress', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updated)
      }).catch(err => console.error("Erro ao atualizar progresso:", err));

      return nextState;
    });
  };

  const attendLiveSession = (courseId: string, liveSessionId: string) => {
    setProgress((prev) => {
      const existing = prev.find((p) => p.courseId === courseId);
      let updated: StudentProgress;
      let nextState: StudentProgress[];

      if (existing) {
        if (existing.attendedLiveSessions.includes(liveSessionId)) return prev;

        updated = {
          ...existing,
          attendedLiveSessions: [...existing.attendedLiveSessions, liveSessionId]
        };
        nextState = prev.map((p) => (p.courseId === courseId ? updated : p));
      } else {
        updated = {
          courseId,
          completedLessons: [],
          attendedLiveSessions: [liveSessionId]
        };
        nextState = [...prev, updated];
      }

      fetch('/api/progress', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updated)
      }).catch(err => console.error("Erro ao atualizar presenca em live:", err));

      return nextState;
    });
  };

  const addCourse = (newCourse: Course) => {
    setCourses((prev) => [...prev, newCourse]);
    fetch('/api/courses', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newCourse)
    }).catch(err => console.error(err));
  };

  const deleteCourse = (courseId: string) => {
    setCourses((prev) => prev.filter((c) => c.id !== courseId));
  };

  const updateCourseInstructor = (courseId: string, instructorName: string) => {
    setCourses((prev) =>
      prev.map((course) =>
        course.id === courseId ? { ...course, instructorName } : course
      )
    );
    fetch(`/api/courses/${courseId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ instructorName })
    }).catch(err => console.error(err));
  };

  const updateCourseProps = (courseId: string, updates: Partial<Course>) => {
    setCourses((prev) =>
      prev.map((course) =>
        course.id === courseId ? { ...course, ...updates } : course
      )
    );
    fetch(`/api/courses/${courseId}`, {
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
          return { ...course, lessons: [...course.lessons, newLesson] };
        }
        return course;
      })
    );
  };
  
  const updateLesson = (courseId: string, lessonId: string, updates: Partial<Lesson>) => {
    setCourses((prev) =>
      prev.map((course) => {
        if (course.id === courseId) {
          return {
            ...course,
            lessons: course.lessons.map((l) => (l.id === lessonId ? { ...l, ...updates } : l))
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
          return {
            ...course,
            lessons: course.lessons.filter((l) => l.id !== lessonId)
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
          return { ...course, liveSessions: [...course.liveSessions, newSession] };
        }
        return course;
      })
    );
  };

  const removeLiveSession = (courseId: string, sessionId: string) => {
    setCourses((prev) =>
      prev.map((course) => {
        if (course.id === courseId) {
          return {
            ...course,
            liveSessions: course.liveSessions.filter((s) => s.id !== sessionId)
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
          return {
            ...course,
            liveSessions: course.liveSessions.map((session) =>
              session.id === sessionId ? { ...session, isLive } : session
            )
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
    fetch('/api/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newMessage)
    }).catch(err => console.error(err));
  };

  const sendDirectMessage = (studentName: string, text: string) => {
    const newDM: DirectMessage = {
      id: `dm-${Date.now()}`,
      studentName,
      senderName: activeUser.name,
      senderRole: activeUser.role,
      text,
      timestamp: new Date().toISOString()
    };
    setDirectMessages((prev) => [...prev, newDM]);
    fetch('/api/dms', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newDM)
    }).catch(err => console.error(err));
  };

  const addQuiz = (courseId: string, title: string, questions: QuizQuestion[]) => {
    const newQuiz: Quiz = {
      id: `quiz-${Date.now()}`,
      courseId,
      title,
      questions
    };
    setQuizzes((prev) => [...prev, newQuiz]);
  };

  const deleteQuiz = (quizId: string) => {
    setQuizzes((prev) => prev.filter((q) => q.id !== quizId));
    setQuizSubmissions((prev) => prev.filter((qs) => qs.quizId !== quizId));
  };

  const submitQuiz = (
    studentName: string,
    courseId: string,
    quizId: string,
    scorePercent: number,
    passed: boolean
  ): QuizSubmission => {
    const newSubmission: QuizSubmission = {
      id: `sub-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      studentName,
      courseId,
      quizId,
      scorePercent,
      passed,
      submittedAt: new Date().toLocaleDateString('pt-BR') + ' às ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    };
    setQuizSubmissions((prev) => {
      // replace previous submissions of the same student for the same quiz to allow retries
      const cleaned = prev.filter((sub) => !(sub.studentName === studentName && sub.quizId === quizId));
      return [...cleaned, newSubmission];
    });
    return newSubmission;
  };

  const addProfessor = (name: string, password?: string) => {
    setProfessorsList((prev) => {
      if (prev.includes(name)) return prev;
      const finalPassword = password && password.trim() ? password.trim() : '5678';
      localStorage.setItem(`ava_active_password_${name}`, finalPassword);
      return [...prev, name];
    });
  };

  const deleteProfessor = (name: string) => {
    setProfessorsList((prev) => prev.filter((p) => p !== name));
  };

  const addStudent = (name: string, email: string, password?: string) => {
    setStudentsList((prev) => {
      if (prev.some((s) => s.name.toLowerCase() === name.toLowerCase() || s.email.toLowerCase() === email.toLowerCase())) return prev;
      const finalPassword = password && password.trim() ? password.trim() : '1234';
      // Store in localStorage so the login overlay / ProfileView immediately picks it up
      localStorage.setItem(`ava_active_password_${name}`, finalPassword);
      return [...prev, { name, email, password: finalPassword }];
    });
  };

  const deleteStudent = (name: string) => {
    setStudentsList((prev) => prev.filter((s) => s.name !== name));
  };

  const setUserProfile = (name: string, role: 'student' | 'instructor' | 'admin') => {
    setActiveUser({ name, role });
  };

  const addAcademicRequest = (req: Omit<AcademicRequest, 'id' | 'status' | 'submittedAt'>) => {
    const newRequest: AcademicRequest = {
      ...req,
      id: `req-${Date.now()}`,
      status: 'pending',
      submittedAt: new Date().toLocaleDateString('pt-BR')
    };
    setAcademicRequests((prev) => [newRequest, ...prev]);
    fetch('/api/academic-requests', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newRequest)
    }).catch(err => console.error(err));
  };

  const updateRequestStatus = (reqId: string, status: 'approved' | 'rejected') => {
    setAcademicRequests((prev) =>
      prev.map((req) => (req.id === reqId ? { ...req, status } : req))
    );
    fetch(`/api/academic-requests/${reqId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status })
    }).catch(err => console.error(err));
  };

  const [admissionRequests, setAdmissionRequests] = useState<AdmissionRequest[]>(() => {
    const saved = localStorage.getItem('ava_admission_requests');
    if (saved) return JSON.parse(saved);
    return [
      { id: 'adm-1', studentName: 'Lucas Santana', courseId: 'course-1', status: 'pending', submittedAt: '03/06/2026' },
      { id: 'adm-2', studentName: 'Carolina Mendes', courseId: 'course-1', status: 'pending', submittedAt: '03/06/2026' },
      { id: 'adm-3', studentName: 'Ana Souza', courseId: 'course-2', status: 'pending', submittedAt: '03/06/2026' },
    ];
  });

  useEffect(() => {
    localStorage.setItem('ava_admission_requests', JSON.stringify(admissionRequests));
  }, [admissionRequests]);

  const updateAdmissionStatus = (reqId: string, status: 'approved' | 'rejected') => {
    setAdmissionRequests((prev) =>
      prev.map((req) => (req.id === reqId ? { ...req, status } : req))
    );
    fetch(`/api/admissions/${reqId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status })
    }).catch(err => console.error(err));
  };

  const addAdmissionRequest = (studentName: string, courseId: string, status: 'pending' | 'approved' | 'rejected' = 'pending') => {
    const newReq: AdmissionRequest = {
      id: `adm-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      studentName,
      courseId,
      status,
      submittedAt: new Date().toLocaleDateString('pt-BR')
    };
    setAdmissionRequests(prev => [...prev, newReq]);
  };

  const enrollStudentInCourse = (studentName: string, courseId: string, customEnrolledAt?: string) => {
    setStudentEnrollments(prev => {
      const current = prev[studentName] || { enrolledCourseId: null, enrolledAt: null, completedCourseIds: [], dropOutPenaltyUntil: null };
      return {
        ...prev,
        [studentName]: {
          ...current,
          enrolledCourseId: courseId,
          enrolledAt: customEnrolledAt || new Date().toISOString(),
          dropOutPenaltyUntil: null
        }
      };
    });
  };

  const dropStudentFromCourse = (studentName: string, courseId: string, simulatedDaysElapsed?: number): boolean => {
    let penalty = false;
    setStudentEnrollments(prev => {
      const current = prev[studentName] || { enrolledCourseId: null, enrolledAt: null, completedCourseIds: [], dropOutPenaltyUntil: null };
      let days = 0;
      if (simulatedDaysElapsed !== undefined) {
        days = simulatedDaysElapsed;
      } else if (current.enrolledAt) {
        const diffTime = Math.abs(Date.now() - new Date(current.enrolledAt).getTime());
        days = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      }
      
      const isPastLimit = days > 5;
      let penaltyDate: string | null = null;
      if (isPastLimit) {
        penalty = true;
        const d = new Date();
        d.setMonth(d.getMonth() + 1);
        penaltyDate = d.toISOString();
      }
      
      return {
        ...prev,
        [studentName]: {
          ...current,
          enrolledCourseId: null,
          enrolledAt: null,
          dropOutPenaltyUntil: penaltyDate
        }
      };
    });
    return penalty;
  };

  const completeStudentCourse = (studentName: string, courseId: string) => {
    setStudentEnrollments(prev => {
      const current = prev[studentName] || { enrolledCourseId: null, enrolledAt: null, completedCourseIds: [], dropOutPenaltyUntil: null };
      const completed = Array.from(new Set([...current.completedCourseIds, courseId]));
      return {
        ...prev,
        [studentName]: {
          ...current,
          enrolledCourseId: null,
          enrolledAt: null,
          completedCourseIds: completed
        }
      };
    });
  };

  const clearStudentPenalty = (studentName: string) => {
    setStudentEnrollments(prev => {
      const current = prev[studentName] || { enrolledCourseId: null, enrolledAt: null, completedCourseIds: [], dropOutPenaltyUntil: null };
      return {
        ...prev,
        [studentName]: {
          ...current,
          dropOutPenaltyUntil: null
        }
      };
    });
  };

  const addForumMessage = (courseId: string, text: string) => {
    if (!text.trim()) return;
    const newMessage: ForumMessage = {
      id: `forum-msg-${Date.now()}`,
      courseId,
      senderName: activeUser.name,
      senderRole: activeUser.role,
      text: text.trim(),
      timestamp: new Date().toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
      likes: 0,
      likedBy: []
    };
    setForumMessages(prev => [...prev, newMessage]);
  };

  const toggleForumMessageLike = (messageId: string, userName: string) => {
    setForumMessages(prev => prev.map(msg => {
      if (msg.id === messageId) {
        const hasLiked = msg.likedBy.includes(userName);
        const newLikedBy = hasLiked 
          ? msg.likedBy.filter(u => u !== userName)
          : [...msg.likedBy, userName];
        return {
          ...msg,
          likedBy: newLikedBy,
          likes: newLikedBy.length
        };
      }
      return msg;
    }));
  };

  const deleteForumMessage = (messageId: string) => {
    setForumMessages(prev => prev.filter(msg => msg.id !== messageId));
  };

  const getYouTubeEmbedUrl = (url: string): string | null => {
    if (!url) return null;
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=|shorts\/)([^#&?]*).*/;
    const match = url.match(regExp);
    if (match && match[2].length === 11) {
      return `https://www.youtube.com/embed/${match[2]}?autoplay=0&rel=0&modestbranding=1`;
    }
    return null;
  };

  return (
    <LMSContext.Provider
      value={{
        courses,
        activeUser,
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
        toggleUserRole,
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
        deleteQuiz,
        submitQuiz,
        addProfessor,
        deleteProfessor,
        addStudent,
        deleteStudent,
        setUserProfile,
        addAcademicRequest,
        updateRequestStatus,
        addCategory,
        updateAccessibilitySettings,
        addLibraryItem,
        addWebinarEvent,
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
        getYouTubeEmbedUrl,
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
        forumMessages,
        addForumMessage,
        toggleForumMessageLike,
        deleteForumMessage,
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
