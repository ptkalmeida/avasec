/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { createContext, useContext, useState, useEffect } from 'react';
import { Course, LMSState, StudentProgress, Certificate, ChatMessage, DirectMessage, Quiz, QuizQuestion, QuizSubmission, AcademicRequest, LibraryItem, WebinarEvent, AccessibilitySettings, AdmissionRequest } from '../types';
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
  studentsList: { name: string; email: string }[];
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
  updateLesson: (courseId: string, lessonId: string, updates: Partial<{ title: string; duration: string; content: string; videoUrl: string }>) => void;
  deleteLesson: (courseId: string, lessonId: string) => void;
  addLiveSessionToCourse: (courseId: string, title: string, scheduledAt: string, durationMinutes: number, meetingLink: string, isLive: boolean) => void;
  removeLiveSession: (courseId: string, sessionId: string) => void;
  sendLiveChatMessage: (sessionId: string, text: string) => void;
  setLiveSessionStatus: (courseId: string, sessionId: string, isLive: boolean) => void;
  sendDirectMessage: (studentName: string, text: string) => void;
  addQuiz: (courseId: string, title: string, questions: QuizQuestion[]) => void;
  deleteQuiz: (quizId: string) => void;
  submitQuiz: (studentName: string, courseId: string, quizId: string, scorePercent: number, passed: boolean) => QuizSubmission;
  addProfessor: (name: string) => void;
  deleteProfessor: (name: string) => void;
  addStudent: (name: string, email: string) => void;
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
  updateAdmissionStatus: (reqId: string, status: 'approved' | 'rejected') => void;
}

const LMSContext = createContext<LMSContextProps | undefined>(undefined);

export const LMSProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [courses, setCourses] = useState<Course[]>(() => {
    const saved = localStorage.getItem('ava_courses');
    return saved ? JSON.parse(saved) : INITIAL_COURSES;
  });

  const [categoriesList, setCategoriesList] = useState<string[]>(() => {
    const saved = localStorage.getItem('ava_categories');
    if (saved) return JSON.parse(saved);
    return ['Tecnologia', 'Design Digital', 'Ciência de Dados', 'Engenharia de Software', 'Economia Criativa & IA', 'Áreas Técnicas', 'Políticas e Gestão Culturais'];
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
    return saved ? JSON.parse(saved) : { name: 'João Silva', role: 'student' };
  });

  const [professorsList, setProfessorsList] = useState<string[]>(() => {
    const saved = localStorage.getItem('ava_professors');
    if (saved) return JSON.parse(saved);
    return ['Alessandro Pinto', 'Mariana Santos', 'André Lima', 'Juliana Rezende'];
  });

  const [studentsList, setStudentsList] = useState<{ name: string; email: string }[]>(() => {
    const defaultStudents = [
      { name: 'João Silva', email: 'joao.silva@lms.edu' },
      { name: 'Gabriel Rodrigues', email: 'gabriel.rodrigues@lms.edu' },
      { name: 'Beatriz Costa', email: 'beatriz.c@lms.edu' },
      { name: 'Sofia Rocha', email: 'sofia.rocha@lms.edu' },
      { name: 'Ana Souza', email: 'ana.souza@lms.edu' },
      { name: 'Lucas Santana', email: 'lucas.santana@lms.edu' },
      { name: 'Carolina Mendes', email: 'carol.mendes@lms.edu' }
    ];
    const saved = localStorage.getItem('ava_students');
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        if (Array.isArray(parsed)) {
          const merged = [...parsed];
          defaultStudents.forEach(item => {
            if (!merged.some(m => m.name === item.name)) {
              merged.push(item);
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
    // Pre-populate so user has some completed lessons and doesn't start with 0% absolute empty unless they want to
    if (saved) return JSON.parse(saved);
    return [
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
  });

  const [certificates, setCertificates] = useState<Certificate[]>(() => {
    const saved = localStorage.getItem('ava_certificates');
    return saved ? JSON.parse(saved) : [];
  });

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
  };

  const addWebinarEvent = (webinar: Omit<WebinarEvent, 'id'>) => {
    const newWebinar = { ...webinar, id: `web-${Date.now()}` };
    setWebinarEvents(prev => [newWebinar, ...prev]);
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
        senderName: 'Prof. Alessandro Pinto',
        senderRole: 'instructor',
        text: 'Sejam bem-vindos à aula ao vivo sobre UX de Alta Performance! Podem enviar dúvidas aqui no chat.',
        timestamp: new Date().toISOString()
      },
      {
        id: 'msg-2',
        sessionId: 'live-1-1',
        senderName: 'João Silva',
        senderRole: 'student',
        text: 'Olá Professor! Esse grid de 8pt se aplica também para design mobile ou focamos em layouts web no Figma?',
        timestamp: new Date().toISOString()
      },
      {
        id: 'msg-3',
        sessionId: 'live-2-1',
        senderName: 'Profª. Mariana Santos',
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
        text: 'Olá Professor Alessandro, tudo bem? Estou gostando muito do curso de UX! Quando teremos o próximo feedback de portfólios?',
        timestamp: new Date(Date.now() - 3600000 * 5).toISOString() // 5h ago
      },
      {
        id: 'dm-2',
        studentName: 'João Silva',
        senderName: 'Alessandro Pinto',
        senderRole: 'instructor',
        text: 'Olá João! Que ótimo que está curtindo. Teremos uma mentoria sobre isso hoje mesmo às 19:30, mas você pode também agendar um horário direto comigo se precisar!',
        timestamp: new Date(Date.now() - 3600000 * 4).toISOString() // 4h ago
      },
      {
        id: 'dm-3',
        studentName: 'Gabriel Rodrigues',
        senderName: 'Gabriel Rodrigues',
        senderRole: 'student',
        text: 'Olá tutor Alessandro! Enviei o link do meu protótipo no Figma para avaliação. Poderia dar uma olhada no fluxo de navegação?',
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
    setSystemSettings((prev: LMSContextProps['systemSettings']) => ({ ...prev, ...updates }));
  };

  useEffect(() => {
    localStorage.setItem('ava_system_settings', JSON.stringify(systemSettings));
  }, [systemSettings]);

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
      const newName = newRole === 'student' ? 'João Silva' : 'Alessandro Pinto';
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

  // Automatic Certificate Issuance Logic when attendance hits 70% or more!
  useEffect(() => {
    if (activeUser.role !== 'student') return;

    courses.forEach((course) => {
      const attendance = calculateAttendancePercent(course.id);
      
      // If student has at least 70% attendance and doesn't have a certificate for this course yet, issue it automatically!
      if (attendance >= 70) {
        const alreadyIssued = certificates.some(
          (cert) => cert.courseId === course.id && cert.studentName === activeUser.name
        );

        if (!alreadyIssued) {
          const hashHex = Array.from({ length: 16 }, () =>
            Math.floor(Math.random() * 16).toString(16)
          ).join('').toUpperCase();

          const newCertificate: Certificate = {
            id: `cert-${course.id}-${Date.now()}`,
            studentName: activeUser.name,
            courseId: course.id,
            courseTitle: course.title,
            issueDate: new Date().toLocaleDateString('pt-BR'),
            attendancePercent: attendance,
            verificationHash: `AVA-${hashHex}`
          };

          setCertificates((prev) => [...prev, newCertificate]);
        }
      } else {
        // If they became un-qualified (unselected lesson and went below 70%), remove certificate
        // to stay dynamically accurate in state simulation, unless they like it
        const alreadyIssued = certificates.some(
          (cert) => cert.courseId === course.id && cert.studentName === activeUser.name
        );
        if (alreadyIssued && attendance < 70) {
          setCertificates((prev) =>
            prev.filter((cert) => !(cert.courseId === course.id && cert.studentName === activeUser.name))
          );
        }
      }
    });
  }, [progress, activeUser.name, courses, activeUser.role]);

  const toggleLessonCompletion = (courseId: string, lessonId: string) => {
    setProgress((prev) => {
      const existing = prev.find((p) => p.courseId === courseId);
      if (existing) {
        const isCompleted = existing.completedLessons.includes(lessonId);
        const updatedLessons = isCompleted
          ? existing.completedLessons.filter((id) => id !== lessonId)
          : [...existing.completedLessons, lessonId];

        return prev.map((p) =>
          p.courseId === courseId ? { ...p, completedLessons: updatedLessons } : p
        );
      } else {
        return [...prev, { courseId, completedLessons: [lessonId], attendedLiveSessions: [] }];
      }
    });
  };

  const attendLiveSession = (courseId: string, liveSessionId: string) => {
    setProgress((prev) => {
      const existing = prev.find((p) => p.courseId === courseId);
      if (existing) {
        if (existing.attendedLiveSessions.includes(liveSessionId)) return prev; // already recorded

        return prev.map((p) =>
          p.courseId === courseId
            ? { ...p, attendedLiveSessions: [...p.attendedLiveSessions, liveSessionId] }
            : p
        );
      } else {
        return [...prev, { courseId, completedLessons: [], attendedLiveSessions: [liveSessionId] }];
      }
    });
  };

  const addCourse = (newCourse: Course) => {
    setCourses((prev) => [...prev, newCourse]);
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
  };

  const updateCourseProps = (courseId: string, updates: Partial<Course>) => {
    setCourses((prev) =>
      prev.map((course) =>
        course.id === courseId ? { ...course, ...updates } : course
      )
    );
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
  
  const updateLesson = (courseId: string, lessonId: string, updates: Partial<{ title: string; duration: string; content: string; videoUrl: string }>) => {
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
      id: `sub-${Date.now()}`,
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

  const addProfessor = (name: string) => {
    setProfessorsList((prev) => {
      if (prev.includes(name)) return prev;
      return [...prev, name];
    });
  };

  const deleteProfessor = (name: string) => {
    setProfessorsList((prev) => prev.filter((p) => p !== name));
  };

  const addStudent = (name: string, email: string) => {
    setStudentsList((prev) => {
      if (prev.some((s) => s.name.toLowerCase() === name.toLowerCase() || s.email.toLowerCase() === email.toLowerCase())) return prev;
      return [...prev, { name, email }];
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
  };

  const updateRequestStatus = (reqId: string, status: 'approved' | 'rejected') => {
    setAcademicRequests((prev) =>
      prev.map((req) => (req.id === reqId ? { ...req, status } : req))
    );
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
        updateAdmissionStatus,
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
