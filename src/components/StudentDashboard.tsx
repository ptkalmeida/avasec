/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'motion/react';
import {
  BookOpen, Calendar, CheckCircle, Award, Video, Clock, ChevronRight,
  TrendingUp, FileCheck, ArrowRight, ArrowLeft, User, Settings, Sparkles, BookMarked, Monitor, Linkedin, Download, Globe, PlayCircle,
  Lock, MessageSquare, Send, ChevronDown, Check, Play, FileText, Notebook, Layers, HelpCircle, CheckSquare, ExternalLink, Archive, Library, Info,
  Bell, Shield, Smartphone, X
} from 'lucide-react';
import { useLMS, authFetch } from '../context/LMSContext';
import { VideoPlayer } from './shared/VideoPlayer';
import { downloadSubmissionFile, downloadCertificatePdf } from '../utils/fileDownload';
import { courseMinAttendance, DROPOUT_PENALTY_FREE_DAYS } from '../config/constants';
import { Course, Lesson, LiveSession, Certificate, isCourseExpired, Quiz, QuizQuestion } from '../types';
import { CertificateTemplate } from './CertificateTemplate';
import { LiveClassroom } from './LiveClassroom';
import { CourseForum } from './CourseForum';
import { StudentLibraryPanel } from './student/StudentLibraryPanel';
import { StudentEventsPanel } from './student/StudentEventsPanel';
import { features } from '../config/features';

interface ModuleGroup {
  name: string;
  description: string;
  lessons: Lesson[];
}

const getCourseModules = (course: Course): ModuleGroup[] => {
  const lessons = [...course.lessons].sort((a, b) => a.order - b.order);
  const modules: ModuleGroup[] = [];

  if (lessons.length === 0) return modules;

  if (course.id === 'course-1') {
    modules.push({
      name: 'Módulo 1: Conceitos e Fundamentos UX',
      description: 'Entenda os pilares primários da experiência do usuário e arquiteturas de navegação ricas.',
      lessons: lessons.slice(0, 2)
    });
    modules.push({
      name: 'Módulo 2: Interfaces Gráficas & Design System',
      description: 'Aprenda a criar grids de alta performance e bibliotecas reutilizáveis eficientes no Figma.',
      lessons: lessons.slice(2, 4)
    });
    modules.push({
      name: 'Módulo 3: Métricas & Teste com Usuários',
      description: 'Como medir a performance, conduzir análises heurísticas e testar com usuários reais.',
      lessons: lessons.slice(4)
    });
  } else if (course.id === 'course-2') {
    modules.push({
      name: 'Módulo 1: Fundamentos de Frontend (React)',
      description: 'Aprenda virtualização, componentização e controle de estado reativo pelo ecossistema Vite.',
      lessons: lessons.slice(0, 1)
    });
    modules.push({
      name: 'Módulo 2: Rest APIs & Express Backend',
      description: 'Desenvolvimento do servidor de alta performance, manipulação de CORS e payloads das requisições.',
      lessons: lessons.slice(1, 3)
    });
    modules.push({
      name: 'Módulo 3: Bancos de Dados & Integração Segura',
      description: 'Modelagem persistente estruturada, noções gerais de PostgreSQL e segurança de conexões.',
      lessons: lessons.slice(3)
    });
  } else {
    // Dynamic fallback division for newly created/custom courses
    const splitIndex = Math.max(1, Math.ceil(lessons.length / 2));
    modules.push({
      name: 'Módulo 1: Introdução Básica',
      description: 'Conceitos básicos e primeiros passos estruturais da trilha teórica ativa.',
      lessons: lessons.slice(0, splitIndex)
    });
    if (lessons.length > splitIndex) {
      modules.push({
        name: 'Módulo 2: Aprofundamento Prático',
        description: 'Tópicos avançados, exercícios de fixação assistida e material complementar.',
        lessons: lessons.slice(splitIndex)
      });
    }
  }

  return modules;
};

interface StudentDashboardProps {
  onBackToLanding?: () => void;
  speakText: (text: string) => void;
}

export const StudentDashboard: React.FC<StudentDashboardProps> = ({ onBackToLanding, speakText }) => {
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
    activeDashboardTab,
    setActiveDashboardTab,
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

  const enrollmentRecord = studentEnrollments[activeUser.id] || { enrolledCourseId: null, completedCourseIds: [], dropOutPenaltyUntil: null };

  // Presença do gestor responsável pelo curso ativo — a chave de presença é por userId (ADR 10).
  const enrolledCourseInstructorId = courses.find(c => c.id === enrollmentRecord.enrolledCourseId)?.instructorId ?? '';

  const handleBack = () => {
    if (activeLesson) {
      setActiveLesson(null);
    } else if (activeQuizTaking) {
      setActiveQuizTaking(null);
    } else if (selectedCourse) {
      setSelectedCourse(null);
    } else if (activeDashboardTab !== 'general') {
      setActiveDashboardTab('general');
    } else if (onBackToLanding) {
      onBackToLanding();
    }
  };

  const getBackLabel = () => {
    if (activeLesson) return "Voltar ao Curso";
    if (activeQuizTaking) return "Voltar ao Curso";
    if (selectedCourse) return "Voltar p/ Meus Cursos";
    if (activeDashboardTab !== 'general') return "Voltar ao Ambiente de Estudos";
    return "Sair p/ Portal";
  };

  // Active state selections
  const [selectedCourse, setSelectedCourse] = useState<Course | null>(null);
  const [activeLesson, setActiveLesson] = useState<Lesson | null>(null);
  const [activeLiveSession, setActiveLiveSession] = useState<LiveSession | null>(null);
  const [selectedCertificate, setSelectedCertificate] = useState<Certificate | null>(null);
  
  // Interactive Quiz Taking States
  const [activeQuizTaking, setActiveQuizTaking] = useState<Quiz | null>(null);
  const [currentQuestionIdx, setCurrentQuestionIdx] = useState<number>(0);
  const [answeredQuestions, setAnsweredQuestions] = useState<{[key: string]: boolean}>({});
  const [currentAnswers, setCurrentAnswers] = useState<{[key: string]: number}>({});
  const [quizResult, setQuizResult] = useState<{ scorePercent: number; passed: boolean } | null>(null);
  const [hasSubmitted, setHasSubmitted] = useState(false);

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
  const [viewingCatalogCourse, setViewingCatalogCourse] = useState<Course | null>(null);
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

  // Certificates Area States
  const [activeCertificatesTab, setActiveCertificatesTab] = useState<'available' | 'in_progress' | 'validation'>('available');
  const [validationCode, setValidationCode] = useState('');
  const [isValidating, setIsValidating] = useState(false);
  const [validationResult, setValidationResult] = useState<{
    valid: boolean;
    message: string;
    studentName?: string;
    courseTitle?: string;
    cargaHoraria?: number | null;
    issueDate?: string;
  } | null>(null);

  // Validação usa a rota pública real (mesma do autenticador da landing) —
  // certificados de terceiros nunca estão no array em memória do aluno.
  const handleValidateCertificate = async () => {
    const code = validationCode.trim();
    if (!code || isValidating) return;
    setIsValidating(true);
    try {
      const res = await fetch(`/api/certificates/verify?q=${encodeURIComponent(code)}`);
      const data = res.ok ? await res.json() : null;
      if (data && data.verificationHash) {
        setValidationResult({
          valid: true,
          message: 'Certificado válido',
          studentName: data.studentName,
          courseTitle: data.courseTitle,
          cargaHoraria: data.cargaHoraria,
          issueDate: data.issueDate
        });
      } else {
        setValidationResult({
          valid: false,
          message: 'Certificado não encontrado. Confira se o código foi digitado corretamente.'
        });
      }
    } catch {
      setValidationResult({
        valid: false,
        message: 'Servidor indisponível para validação. Tente novamente em instantes.'
      });
    } finally {
      setIsValidating(false);
    }
  };

  // Module sidebar accordion expansion states
  const [expandedModules, setExpandedModules] = useState<{[key: string]: boolean}>({
    'Módulo 1: Conceitos e Fundamentos UX': true,
    'Módulo 1: Fundamentos de Frontend (React)': true,
    'Módulo 1: Introdução Básica': true,
    'Módulo 2: Interfaces Gráficas & Design System': false,
    'Módulo 3: Métricas & Teste com Usuários': false,
    'Módulo 2: Rest APIs & Express Backend': false,
    'Módulo 3: Bancos de Dados & Integração Segura': false,
    'Módulo 2: Aprofundamento Prático': false
  });

  const [selectedModulePageName, setSelectedModulePageName] = useState<string | null>(null);

  // Parse deep links on mount
  useEffect(() => {
    if (courses.length > 0) {
      const params = new URLSearchParams(window.location.search);
      const courseIdParam = params.get('courseId');
      const moduleParam = params.get('module');
      const lessonParam = params.get('lesson');

      if (courseIdParam) {
        const course = courses.find(c => c.id === courseIdParam);
        if (course) {
          setSelectedCourse(course);
          if (moduleParam) setSelectedModulePageName(moduleParam);
          if (lessonParam) {
            const lesson = course.lessons.find(l => l.id === lessonParam);
            if (lesson) setActiveLesson(lesson);
          }
        }
      }
    }
  }, [courses]);

  // Listen to the global dashboard reset event to return home immediately
  useEffect(() => {
    const handleResetDashboard = () => {
      setSelectedCourse(null);
      setActiveLesson(null);
      setSelectedModulePageName(null);
      setActiveDashboardTab('general');
    };
    window.addEventListener('reset-dashboard', handleResetDashboard);
    return () => window.removeEventListener('reset-dashboard', handleResetDashboard);
  }, [setActiveDashboardTab]);

  const toggleModuleExpand = (moduleName: string) => {
    setActiveLesson(null); // Deselect current lesson when switching modules or opening a new module detail view
    setSelectedModulePageName(moduleName);
    setExpandedModules(prev => ({
      ...prev,
      [moduleName]: true
    }));
  };

  // Student private notebook states
  const [activeTab, setActiveTab] = useState<'teoria' | 'anotacao' | 'suporte' | 'forum' | 'exercicios'>('teoria');
  
  // Auto-switch away from disabled tabs
  useEffect(() => {
    if (activeTab === 'forum' && !features.forum) {
      setActiveTab('teoria');
    }
    if (activeTab === 'exercicios' && !features.atividadesPraticasAvancadas) {
      setActiveTab('teoria');
    }
  }, [activeTab]);

  const [typedAnswers, setTypedAnswers] = useState<{[exerciseId: string]: string}>({});
  const [typedFiles, setTypedFiles] = useState<{[exerciseId: string]: { name: string, url: string } | null}>({});
  const [simulatedUploading, setSimulatedUploading] = useState<{[exerciseId: string]: boolean}>({});
  
  const [savedNotes, setSavedNotes] = useState<{[key: string]: string}>(() => {
    try {
      const saved = localStorage.getItem('ava_student_lesson_notes');
      return saved ? JSON.parse(saved) : {};
    } catch {
      return {};
    }
  });

  const [lessonNoteText, setLessonNoteText] = useState('');

  // Sync state whenever active lesson switches
  React.useEffect(() => {
    if (activeLesson) {
      setLessonNoteText(savedNotes[activeLesson.id] || '');
    }
  }, [activeLesson, savedNotes]);

  const handleSaveNoteText = () => {
    if (activeLesson) {
      const updated = { ...savedNotes, [activeLesson.id]: lessonNoteText };
      setSavedNotes(updated);
      localStorage.setItem('ava_student_lesson_notes', JSON.stringify(updated));
    }
  };

  // Calculations for summary metrics based on the student's actual enrollment record
  const activeEnrollments = enrollmentRecord.enrolledCourseId ? 1 : 0;
  
  // Average Global Attendance calculated only for the active enrolled course
  const avgGlobalAttendance = enrollmentRecord.enrolledCourseId 
    ? calculateAttendancePercent(enrollmentRecord.enrolledCourseId) 
    : 0;
  
  const totalCertificatesCount = certificates.length;

  // Filter courses based on search
  const filteredCourses = courses.filter((c) =>
    c.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
    c.category.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const currentCourseProgress = selectedCourse
    ? progress.find((p) => p.courseId === selectedCourse.id)
    : null;

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 md:px-6">
      {/* Student Welcome Header */}
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
            <div className="bg-white/60 border border-slate-150 rounded-xl px-4 py-2.5 text-left shadow-3xs hover:bg-white/90 transition-all flex-1 sm:flex-initial min-w-[115px]">
              <span className="block text-xl font-black text-amber-600 font-mono tracking-tight">{totalCertificatesCount}</span>
              <span className="text-[10px] text-slate-500 font-semibold block mt-0.5 whitespace-nowrap">Certificados</span>
            </div>
          </div>
        </div>
      </div>

      {/* Dynamic Tab Navigation System */}
      {features.mensagensDiretas && systemSettings.allowDirectMessages && (
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
          
          {features.certificados && (
            <button
              onClick={() => setActiveDashboardTab('certificates')}
              className={`px-6 py-3 rounded-xl text-[11px] font-black uppercase tracking-widest transition-all flex items-center gap-2.5 cursor-pointer whitespace-nowrap ${
                activeDashboardTab === 'certificates'
                  ? 'bg-[#540D6E] text-white shadow-md transform scale-[1.02]'
                  : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50/50'
              }`}
            >
              <Award className="h-4 w-4" />
              <span>Certificados</span>
            </button>
          )}

          {features.solicitacoesAcademicas && (
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

          {features.forum && (
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

          {features.materiaisComplementares && (
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

          {features.eventosWebinars && (
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

          {features.perfilBasico && (
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
      )}

      {((!features.certificados && activeDashboardTab === 'certificates') ||
        (!features.solicitacoesAcademicas && activeDashboardTab === 'documents') ||
        (!features.forum && activeDashboardTab === 'messages') ||
        (!features.materiaisComplementares && activeDashboardTab === 'library') ||
        (!features.perfilBasico && activeDashboardTab === 'settings')) ? (
        <div className="bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl p-8 text-center max-w-xl mx-auto my-12 shadow-3xs space-y-3">
          <Lock className="h-10 w-10 text-amber-600 mx-auto" />
          <h3 className="font-extrabold text-base">Esta funcionalidade está temporariamente indisponível.</h3>
          <p className="text-xs text-slate-500">Estamos trabalhando em melhorias e atualizações para esta seção. Por favor, tente novamente mais tarde.</p>
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
                  <h3 className="text-xl font-black text-slate-800">⚠️ Vigência de Exibição Encerrada</h3>
                  <p className="text-sm text-slate-600 leading-relaxed text-center">
                    O contrato de licenciamento e exibição deste curso encerrou-se em <strong className="font-bold underline">{selectedCourse.contractExpirationDate}</strong>. 
                    Por razões de conformidade legal e direitos autorais da coordenação, este material foi <strong>arquivado preventivamente</strong> e o acesso às aulas foi suspenso.
                  </p>
                  
                  <div className="bg-white border border-amber-200 rounded-xl p-4 text-xs text-amber-900 text-left space-y-1">
                    <strong className="block text-amber-950 font-bold uppercase text-[10px] tracking-wider mb-1">Proteção Jurídica Ativa:</strong>
                    <p>✓ Reprodução de vídeos suspensa.</p>
                    <p>✓ Download de anexos bloqueado de acordo com a vigência de exibição.</p>
                    <p>✓ Cadastro de novas presenças desativado.</p>
                  </div>

                  <button
                    onClick={() => { setSelectedCourse(null); setActiveLesson(null); setSelectedModulePageName(null); }}
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
                  onClick={() => { setSelectedCourse(null); setActiveLesson(null); setSelectedModulePageName(null); }}
                  className="flex items-center gap-1.5 text-xs font-bold text-teal-600 hover:text-teal-500 transition-colors cursor-pointer"
                >
                  <span>← Sair do Curso</span>
                </button>

                <div className="flex flex-wrap items-center gap-4">
                  {/* Cancel enrollment control — a contagem de dias e a eventual restrição são
                      decididas pelo servidor a partir da data real da matrícula. */}
                  <div className="flex flex-wrap items-center gap-2 bg-slate-50 border border-slate-205 rounded-xl px-3 py-1.5 text-xs">
                    {features.penalidadesCancelamento && (
                      <span className="text-[10px] text-slate-500 font-bold">
                        Cancelamentos após {DROPOUT_PENALTY_FREE_DAYS} dias de matrícula geram restrição temporária.
                      </span>
                    )}

                    <button
                      onClick={async () => {
                        // A decisão de penalidade é do SERVIDOR (dias reais desde a matrícula + flag).
                        const result = await dropStudentFromCourse(activeUser.id, selectedCourse.id);
                        if (!result.ok) {
                          showAlert(result.error || 'Não foi possível cancelar a matrícula.');
                          return;
                        }
                        if (result.penaltyApplied) {
                          speakText("Matrícula cancelada. Você desistiu deste curso após o limite de 5 dias letivos. Seu acesso agora está sob regime de restrição temporária de nova matrícula.");
                        } else {
                          speakText("Matrícula desfeita com sucesso.");
                        }
                        setSelectedCourse(null);
                        setActiveLesson(null);
                        setSelectedModulePageName(null);
                      }}
                      className="bg-rose-600 hover:bg-rose-500 text-white font-black text-[9.5px] uppercase tracking-wide px-3 py-1.5 rounded-lg transition-all cursor-pointer"
                    >
                      Solicitar Saída do Curso
                    </button>
                  </div>

                  <div className="flex items-center gap-2">
                    <span className="text-[10px] uppercase font-bold text-slate-400">Trilha de Estudos:</span>
                    <span className="rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-xs font-bold text-slate-600">
                      {selectedCourse.category}
                    </span>
                  </div>
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

                <div className="flex items-center gap-3 bg-white px-3 py-2 rounded-lg border border-slate-250">
                  <div className="text-right">
                    <span className="block text-[9px] uppercase font-semibold text-slate-400 leading-none">Frequência Total</span>
                    <strong className="text-sm font-black text-teal-700 font-mono mt-0.5 block">
                      {calculateAttendancePercent(selectedCourse.id)}% <span className="text-[10px] text-slate-400">/ 70%</span>
                    </strong>
                  </div>
                  <div className="h-8 w-8 rounded-full border-2 border-slate-200 border-t-teal-600/80 animate-spin-slow flex items-center justify-center text-[8px] font-bold text-teal-600">
                    70%
                  </div>
                </div>
              </div>

              {/* Banner de sucesso — só quando a frequência qualifica para certificação */}
              {calculateAttendancePercent(selectedCourse.id) >= 70 && (
                <div className="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-xs text-emerald-800 flex items-center justify-between gap-4">
                  <div className="flex items-center gap-2.5">
                    <CheckCircle className="h-5 w-5 text-emerald-600 shrink-0" />
                    <div>
                      <strong className="block font-bold mb-0.5">Parabéns! Frequência Qualificada para Certificação</strong>
                      Você atingiu {calculateAttendancePercent(selectedCourse.id)}% de presença! Seu certificado acadêmico digital foi emitido e está pronto no painel lateral.
                    </div>
                  </div>
                  <div className="flex flex-col sm:flex-row items-center gap-2">
                    <button
                      onClick={async () => {
                        // O servidor confere o critério de frequência antes de concluir.
                        const result = await completeStudentCourse(activeUser.id, selectedCourse.id);
                        if (!result.ok) {
                          showAlert(result.error || 'Critério de conclusão ainda não atingido.');
                          return;
                        }
                        speakText("Parabéns pela conclusão da disciplina! Agora você pode escolher um novo curso para iniciar seus estudos.");
                        setSelectedCourse(null);
                        setActiveLesson(null);
                        setSelectedModulePageName(null);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                      }}
                      className="shrink-0 rounded-lg bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs px-3.5 py-1.8 transition-colors flex items-center gap-1.5 shadow-xs whitespace-nowrap cursor-pointer"
                    >
                      <Check className="h-3.5 w-3.5" />
                      <span>Concluir Disciplina e Escolher Novo Curso</span>
                    </button>

                    {certificates.find((cert) => cert.courseId === selectedCourse.id && cert.userId === activeUser.id) && (
                      <button
                        onClick={() => {
                          const cert = certificates.find((c) => c.courseId === selectedCourse.id && c.userId === activeUser.id);
                          if (cert) setSelectedCertificate(cert);
                        }}
                        className="shrink-0 rounded-lg bg-emerald-650 hover:bg-emerald-605 text-white font-semibold text-xs px-3.5 py-1.8 transition-colors flex items-center gap-1.5 shadow-xs whitespace-nowrap cursor-pointer"
                      >
                        <Award className="h-3.5 w-3.5" />
                        <span>Emitir Certificado</span>
                      </button>
                    )}
                  </div>
                </div>
              )}

              {/* Full Page Module View (hides the Grid) */}
              {selectedModulePageName && !activeLesson && !activeQuizTaking ? (
                (() => {
                  const module = getCourseModules(selectedCourse).find(m => m.name === selectedModulePageName);
                  if (!module) return null;

                  const completedInModule = module.lessons.filter(l => currentCourseProgress?.completedLessons.includes(l.id)).length;
                  const hasCompletedAll = completedInModule === module.lessons.length && module.lessons.length > 0;

                  return (
                    <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs animate-in fade-in duration-300">
                      {/* Module Billboard Header */}
                      <div className="bg-slate-950 p-8 sm:p-10 text-left relative overflow-hidden">
                        <div className="absolute right-0 top-0 translate-x-10 -translate-y-10 opacity-[0.03] pointer-events-none">
                          <Layers className="h-64 w-64 text-teal-500" />
                        </div>
                        
                        <div className="flex items-center justify-between mb-4">
                          <span className="text-[10px] font-black uppercase tracking-widest text-teal-400 bg-teal-500/10 px-2.5 py-1 rounded inline-block font-mono border border-teal-500/20">
                            Detalhes do Módulo
                          </span>
                          <button
                            onClick={() => setSelectedModulePageName(null)}
                            className="text-xs font-bold text-slate-400 hover:text-white transition-colors flex items-center gap-1 cursor-pointer"
                          >
                            <ArrowRight className="h-3.5 w-3.5 rotate-180" />
                            <span>Voltar ao Curso</span>
                          </button>
                        </div>
                        
                        <h3 className="font-black text-white text-2xl sm:text-3xl leading-tight mb-3">
                          {module.name}
                        </h3>
                        
                        <p className="text-sm text-slate-400 leading-relaxed max-w-2xl">
                          {module.description}
                        </p>
                        
                        <div className="mt-8 flex items-center gap-6 border-t border-slate-800/60 pt-6">
                          <div className="flex flex-col gap-1">
                            <span className="text-[10px] uppercase font-bold text-slate-500 font-mono tracking-wider">Aulas do Módulo</span>
                            <span className="text-white font-bold text-sm flex items-center gap-2">
                              <BookOpen className="h-4 w-4 text-teal-500" />
                              {module.lessons.length} aulas
                            </span>
                          </div>

                          <div className="flex flex-col gap-1">
                            <span className="text-[10px] uppercase font-bold text-slate-500 font-mono tracking-wider">Seu Progresso</span>
                            <span className="text-white font-bold text-sm flex items-center gap-2">
                              {hasCompletedAll ? (
                                <CheckCircle className="h-4 w-4 text-emerald-500" />
                              ) : (
                                <TrendingUp className="h-4 w-4 text-amber-500" />
                              )}
                              {completedInModule} concluídas
                            </span>
                          </div>
                        </div>
                      </div>

                      {/* Chapter List Area */}
                      <div className="p-6 sm:p-12 bg-slate-50/50">
                        <h4 className="font-extrabold text-slate-900 text-sm uppercase tracking-wider mb-5 flex items-center gap-2">
                          <Layers className="h-4 w-4 text-teal-600" />
                          Capítulos Disponíveis
                        </h4>

                        <div className="space-y-3">
                          {module.lessons.map((lesson, idx) => {
                            const isDone = currentCourseProgress?.completedLessons.includes(lesson.id) || false;

                            return (
                              <div
                                key={`${lesson.id}-${idx}`}
                                onClick={() => {
                                  setActiveLesson(lesson);
                                }}
                                className="group flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-xl text-left cursor-pointer transition-all border border-slate-200 bg-white hover:border-teal-400 hover:shadow-sm"
                              >
                                <div className="flex-1 flex items-start sm:items-center gap-3.5">
                                  <button
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      toggleLessonCompletion(selectedCourse.id, lesson.id);
                                    }}
                                    className={`rounded-full p-0.5 border shrink-0 transition-colors mt-0.5 sm:mt-0 ${
                                      isDone 
                                        ? 'bg-emerald-500 border-emerald-500 text-white' 
                                        : 'border-slate-300 text-transparent hover:bg-slate-100 hover:text-slate-400'
                                    }`}
                                  >
                                    <Check className="h-4 w-4 animate-none" />
                                  </button>
                                  
                                  <div className="text-left">
                                    <span className="block text-sm font-bold text-slate-800 group-hover:text-teal-700 transition-colors">
                                      {lesson.title}
                                    </span>
                                    <span className="text-[10px] font-mono text-slate-500 block mt-1">
                                      Tempo estimado: {lesson.duration}
                                    </span>
                                  </div>
                                </div>

                                <div className="shrink-0 mt-3 sm:mt-0 sm:pl-4">
                                  <span className="text-[10px] font-bold uppercase tracking-wider text-teal-600 border border-teal-200 bg-teal-50 px-3 py-1.5 rounded-lg group-hover:bg-teal-600 group-hover:text-white transition-colors flex items-center gap-1.5 focus:outline-hidden">
                                    Acessar <ArrowRight className="h-3 w-3" />
                                  </span>
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      </div>
                    </div>
                  );
                })()
              ) : (
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                {/* 1. Play Station & Notebook Center */}
                <div className={`${(activeLesson) ? "lg:col-span-12" : "lg:col-span-8"} space-y-5 animate-in fade-in duration-300`}>
                  
                  {activeLesson ? (
                    /* Lesson Player Station active */
                    <div className="space-y-5 flex flex-col items-center">
                      
                      <div className="flex items-center justify-end w-full mb-2">
                        <button
                          onClick={() => setActiveLesson(null)}
                          className="text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors flex items-center gap-1 cursor-pointer"
                        >
                          <ArrowRight className="h-3.5 w-3.5 rotate-180" />
                          <span>Voltar ao Módulo</span>
                        </button>
                      </div>

                      {/* Premium Simulated Video Canvas Player Board */}
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

                      {/* Lesson Controls: Mark as Complete, Previous & Next lessons */}
                      <div className="bg-slate-50 p-4 rounded-xl border border-slate-200/65 flex items-center justify-between gap-4">
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => {
                              const order = activeLesson.order;
                              if (order > 1) {
                                const prev = selectedCourse.lessons.find(l => l.order === order - 1);
                                if (prev) setActiveLesson(prev);
                              }
                            }}
                            disabled={activeLesson.order === 1}
                            className={`px-3 py-1.5 rounded-lg border text-xs font-semibold ${
                              activeLesson.order === 1
                                ? 'border-slate-200 text-slate-305 cursor-not-allowed text-slate-300'
                                : 'border-slate-300 text-slate-700 hover:bg-slate-100 cursor-pointer'
                            }`}
                          >
                            ← Anterior
                          </button>
                          
                          <button
                            onClick={() => {
                              const order = activeLesson.order;
                              if (order < selectedCourse.lessons.length) {
                                const next = selectedCourse.lessons.find(l => l.order === order + 1);
                                if (next) setActiveLesson(next);
                              }
                            }}
                            disabled={activeLesson.order === selectedCourse.lessons.length}
                            className={`px-3 py-1.5 rounded-lg border text-xs font-semibold ${
                              activeLesson.order === selectedCourse.lessons.length
                                ? 'border-slate-200 text-slate-305 cursor-not-allowed text-slate-300'
                                : 'border-slate-300 text-slate-700 hover:bg-slate-100 cursor-pointer'
                            }`}
                          >
                            Próxima →
                          </button>
                        </div>

                        {/* Complete checking in the classroom */}
                        <button
                          onClick={() => toggleLessonCompletion(selectedCourse.id, activeLesson.id)}
                          className={`flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                            currentCourseProgress?.completedLessons.includes(activeLesson.id)
                              ? 'bg-slate-200 text-slate-700 hover:bg-slate-300'
                              : 'bg-teal-600 hover:bg-teal-500 text-white shadow-xs'
                          }`}
                        >
                          <CheckCircle className="h-4 w-4" />
                          <span>
                            {currentCourseProgress?.completedLessons.includes(activeLesson.id)
                              ? 'Marcar como Pendente'
                              : 'Concluir esta Aula de Fixação'}
                          </span>
                        </button>
                      </div>

                      {/* Modular Details Hub: Tabs system under Lesson */}
                      <div className="border border-slate-200 rounded-2xl overflow-hidden bg-white">
                        
                        {/* Tab trigger anchors with design visual borders */}
                        <div className="flex border-b border-slate-200 bg-slate-50/50">
                          <button
                            onClick={() => setActiveTab('teoria')}
                            className={`flex-1 py-3 px-4 text-xs font-bold text-slate-700 border-b-2 transition-colors flex items-center justify-center gap-1.5 cursor-pointer ${
                              activeTab === 'teoria' ? 'border-teal-600 text-teal-600 bg-white' : 'border-transparent hover:text-teal-500'
                            }`}
                          >
                            <FileText className="h-4 w-4" />
                            <span>Material Didático</span>
                          </button>
                          
                          <button
                            onClick={() => setActiveTab('anotacao')}
                            className={`flex-1 py-3 px-4 text-xs font-bold text-slate-700 border-b-2 transition-colors flex items-center justify-center gap-1.5 cursor-pointer ${
                              activeTab === 'anotacao' ? 'border-teal-600 text-teal-600 bg-white' : 'border-transparent hover:text-teal-500'
                            }`}
                          >
                            <Notebook className="h-4 w-4" />
                            <span>Anotações Privadas</span>
                            {savedNotes[activeLesson.id] && (
                              <span className="w-1.5 h-1.5 bg-teal-600 rounded-full inline-block" />
                            )}
                          </button>

                          <button
                            onClick={() => setActiveTab('suporte')}
                            className={`flex-1 py-3 px-4 text-xs font-bold text-slate-700 border-b-2 transition-colors flex items-center justify-center gap-1.5 cursor-pointer ${
                              activeTab === 'suporte' ? 'border-teal-600 text-teal-600 bg-white' : 'border-transparent hover:text-teal-500'
                            }`}
                          >
                            <HelpCircle className="h-4 w-4" />
                            <span>Suporte Técnico</span>
                          </button>

                          {features.forum && (
                            <button
                              onClick={() => setActiveTab('forum')}
                              className={`flex-1 py-3 px-4 text-xs font-bold text-slate-700 border-b-2 transition-colors flex items-center justify-center gap-1.5 cursor-pointer ${
                                activeTab === 'forum' ? 'border-teal-600 text-teal-600 bg-white' : 'border-transparent hover:text-teal-500'
                              }`}
                            >
                              <MessageSquare className="h-4 w-4 text-teal-650" />
                              <span className="flex items-center gap-1">
                                Fórum Interativo
                                <span className="bg-teal-100 text-teal-800 text-[8px] font-black uppercase px-2 py-0.5 rounded-full animate-pulse shrink-0">Comunidade</span>
                              </span>
                            </button>
                          )}

                          {features.atividadesPraticasAvancadas && (
                            <button
                              onClick={() => setActiveTab('exercicios')}
                              className={`flex-1 py-3 px-4 text-xs font-bold text-slate-700 border-b-2 transition-colors flex items-center justify-center gap-1.5 cursor-pointer ${
                                activeTab === 'exercicios' ? 'border-teal-600 text-teal-600 bg-white' : 'border-transparent hover:text-teal-500'
                              }`}
                            >
                              <FileCheck className="h-4 w-4 text-teal-650" />
                              <span className="flex items-center gap-1">
                                Exercícios Práticos
                                <span className="bg-teal-100 text-teal-800 text-[8px] font-bold px-2 py-0.5 rounded-full shrink-0">Novo</span>
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
                              <div className="whitespace-pre-line text-[13px] text-slate-700 font-sans leading-relaxed mb-6">
                                {activeLesson.content}
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
                                          href={doc.url}
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
                                <p className="text-[10px] text-slate-400 leading-none mt-1">Eles são gravados e persistidos localmente no seu navegador para consultas futuras.</p>
                              </div>

                              <textarea
                                value={lessonNoteText}
                                onChange={(e) => setLessonNoteText(e.target.value)}
                                placeholder="Grave observações importantes, trechos de código ou anotações teóricas desta aula aqui..."
                                className="w-full rounded-xl border border-slate-200 bg-slate-50/50 p-3 h-32 focus:outline-hidden focus:ring-1 focus:ring-teal-500 text-xs font-sans text-slate-800"
                              />

                              <div className="flex justify-end gap-2 text-right">
                                <button
                                  onClick={handleSaveNoteText}
                                  className="rounded-lg bg-teal-600 hover:bg-teal-500 text-white font-semibold text-xs px-4 py-1.8 shadow-xs transition-transform hover:scale-[1.02] cursor-pointer animate-none"
                                >
                                  Salvar Anotação
                                </button>
                              </div>
                            </div>
                          )}

                          {activeTab === 'suporte' && (
                            <div className="space-y-4">
                              <h4 className="font-bold text-slate-900 flex items-center gap-1.5 pb-2 border-b border-slate-100">
                                <HelpCircle className="h-4 w-4 text-teal-500" />
                                <span>Suporte Técnico & Pedagógico</span>
                              </h4>
                              <p className="text-[11px] text-slate-500 leading-relaxed">
                                Tem dúvidas sobre as regras de arquitetura abordadas ou sobre um bug estrito na aula? Envie seu questionamento diretamente ao instrutor pelo painel de comunicação na coluna da direita! O Gestor de Conteúdos responderá em sua conta no portal de canais em instantes!
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
                                        : 'text-slate-505 text-slate-500 bg-slate-100 px-1 py-0.5 rounded border border-slate-200'
                                    }`}>
                                      {(localStorage.getItem(`ava_presence_status_${selectedCourse.instructorId ?? ''}`) || 'online') === 'online' ? 'ONLINE' : 'OFFLINE'}
                                    </span>
                                  </strong>
                                  <span className="text-[10px] text-slate-450 block">Tempo de resposta esperado: &lt; 2 horas</span>
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

                          {activeTab === 'exercicios' && (
                            <div className="space-y-6">
                              <div className="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-3 gap-2">
                                <div>
                                  <h4 className="font-bold text-slate-900 text-sm flex items-center gap-1.5">
                                    <FileCheck className="h-4.5 w-4.5 text-teal-600" />
                                    <span>Workspace de Exercícios Práticos</span>
                                  </h4>
                                  <p className="text-[11px] text-slate-500 mt-0.5">
                                    Entregue seus trabalhos práticos para revisão personalizada do instrutor do curso.
                                  </p>
                                </div>
                                <span className="bg-slate-100 text-slate-700 text-[10px] font-mono px-2 py-1 rounded border border-slate-200 uppercase self-start sm:self-auto font-bold">
                                  {practicalExercises.filter(ex => ex.courseId === selectedCourse.id).length} tarefas
                                </span>
                              </div>

                              <div className="space-y-5">
                                {practicalExercises.filter(ex => ex.courseId === selectedCourse.id).length === 0 ? (
                                  <div className="text-center p-8 bg-slate-50 border border-slate-250 rounded-xl">
                                    <FileText className="h-10 w-10 text-slate-350 mx-auto stroke-1" />
                                    <p className="text-[11px] text-slate-450 mt-2 font-medium">Nenhum exercício prático registrado para este curso até o momento.</p>
                                  </div>
                                ) : (
                                  practicalExercises.filter(ex => ex.courseId === selectedCourse.id).map((ex, idx) => {
                                    const sub = exerciseSubmissions.find(s => s.exerciseId === ex.id && s.userId === activeUser.id);
                                    const isUploading = simulatedUploading[ex.id];
                                    const currentTyped = typedAnswers[ex.id] || '';
                                    const attachedFile = typedFiles[ex.id];

                                    return (
                                      <div key={`${ex.id}-${idx}`} className="border border-slate-200 rounded-xl bg-slate-50/25 p-4 leading-relaxed text-left space-y-4 shadow-2xs">
                                        
                                        {/* Exercise Details Card Header */}
                                        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-slate-100 pb-3">
                                          <div>
                                            <span className="inline-block bg-teal-50 text-teal-855 text-[9px] font-extrabold uppercase tracking-wide px-2 py-0.5 rounded border border-teal-200/50 mb-1">
                                              Atividade Prática
                                            </span>
                                            <h5 className="font-bold text-slate-900 text-sm leading-tight">{ex.title}</h5>
                                            <p className="text-[11px] text-slate-600 mt-1">{ex.description}</p>
                                          </div>

                                          <div className="flex flex-row sm:flex-col items-center sm:items-end gap-2 sm:gap-1 shrink-0 text-right">
                                            <span className="bg-amber-50 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded border border-amber-200">
                                              Max: {ex.maxPoints} pts
                                            </span>
                                            {ex.dueDate && (
                                              <span className="text-[10px] text-slate-450">Prazo: {ex.dueDate}</span>
                                            )}
                                          </div>
                                        </div>

                                        {/* Specific Instructions for Doing the Exercise */}
                                        <div className="bg-teal-50/30 border border-teal-100/50 rounded-xl p-3 text-[11px] text-slate-700 space-y-1">
                                          <strong className="text-teal-800 font-semibold block flex items-center gap-1">
                                            <Info className="h-3.5 w-3.5" /> Instruções de Entrega:
                                          </strong>
                                          <p className="leading-normal">{ex.instructions}</p>
                                        </div>

                                        {/* Submission status banner */}
                                        {sub && (
                                          <div className={`rounded-xl p-3 border text-[11px] leading-relaxed ${
                                            sub.status === 'approved' 
                                              ? 'bg-emerald-50/55 border-emerald-200 text-emerald-950' 
                                              : sub.status === 'rejected'
                                              ? 'bg-rose-50/50 border-rose-200 text-rose-950'
                                              : sub.status === 'revision'
                                              ? 'bg-amber-50/50 border-amber-200 text-amber-950'
                                              : 'bg-indigo-50/40 border-indigo-250 text-indigo-950'
                                          }`}>
                                            <div className="flex items-center justify-between font-bold mb-1 border-b pb-1.5 border-slate-200/40">
                                              <span className="flex items-center gap-1.5 uppercase tracking-wide text-[10px]">
                                                {sub.status === 'approved' && <CheckCircle className="h-4 w-4 text-emerald-600" />}
                                                {sub.status === 'rejected' && <HelpCircle className="h-4 w-4 text-rose-600" />}
                                                {sub.status === 'revision' && <HelpCircle className="h-4 w-4 text-amber-600" />}
                                                {sub.status === 'pending' && <Clock className="h-4 w-4 text-indigo-600 animate-pulse" />}
                                                Status: {
                                                  sub.status === 'approved' ? 'Aprovado / Corrigido' :
                                                  sub.status === 'rejected' ? 'Reprovado / Necessita Ajustes' :
                                                  sub.status === 'revision' ? 'Revisão Solicitada' : 'Aguardando Correção'
                                                }
                                              </span>
                                              
                                              {sub.status === 'approved' && (
                                                <span className="bg-emerald-600 text-white font-extrabold px-2 py-0.5 rounded font-mono text-[10px]">
                                                  Nota: {sub.score} / {ex.maxPoints}
                                                </span>
                                              )}
                                            </div>

                                            {/* Submitted Text */}
                                            <div className="space-y-1 mt-2">
                                              <span className="text-[10px] font-bold text-slate-500">Seu texto enviado em {sub.submittedAt}:</span>
                                              <p className="bg-white/80 p-2.5 rounded-lg border border-slate-200/50 whitespace-pre-wrap font-mono text-[10px] text-slate-700 leading-normal max-h-40 overflow-y-auto">
                                                {sub.submissionText}
                                              </p>
                                            </div>

                                            {/* Attached Document */}
                                            {sub.fileName && (
                                              <div className="flex items-center gap-1.5 mt-2 text-[10px] bg-white/40 p-1.5 rounded-md border border-dashed border-slate-200">
                                                <FileText className="h-3.5 w-3.5 text-slate-500" />
                                                <span>Documento anexado: <strong className="text-slate-800 font-bold">{sub.fileName}</strong></span>
                                                <button
                                                  onClick={async () => {
                                                    const err = await downloadSubmissionFile(sub.fileUrl || '', sub.fileName);
                                                    if (err) showAlert(err);
                                                  }}
                                                  className="text-teal-650 hover:underline flex items-center gap-0.5 ml-auto font-bold cursor-pointer"
                                                >
                                                  Baixar <ExternalLink className="h-2.5 w-2.5" />
                                                </button>
                                              </div>
                                            )}

                                            {/* Grader Feedback Interaction */}
                                            {sub.feedback && (
                                              <div className="mt-3 bg-white p-3 rounded-xl border border-slate-200 space-y-1 shadow-2xs">
                                                <strong className="text-slate-900 font-bold block flex items-center gap-1 text-[11px]">
                                                  <User className="h-3.5 w-3.5 text-teal-600" /> Feedback do Professor ({sub.gradedBy} em {sub.gradedAt}):
                                                </strong>
                                                <p className="text-slate-650 italic leading-relaxed text-[10.5px] whitespace-pre-line">{sub.feedback}</p>
                                              </div>
                                            )}
                                          </div>
                                        )}

                                        {/* Input fields to submit response */}
                                        {(!sub || sub.status === 'revision' || sub.status === 'rejected' || sub.status === 'pending') && (
                                          <div className="space-y-3.5 pt-1 border-t border-slate-100">
                                            <div className="space-y-1">
                                              <label className="text-[10.5px] font-bold text-slate-700 block">
                                                {sub ? 'Atualizar Texto da Resposta:' : 'Digite sua Resposta:'}
                                              </label>
                                              <textarea
                                                value={currentTyped !== '' ? currentTyped : (sub ? sub.submissionText : '')}
                                                onChange={(e) => setTypedAnswers(prev => ({ ...prev, [ex.id]: e.target.value }))}
                                                placeholder="Insira sua justificativa, roteiro ou resposta detalhada para que o professor possa avaliar..."
                                                rows={5}
                                                className="w-full text-xs font-sans rounded-xl border border-slate-300 p-3 text-slate-800 focus:outline-hidden focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 bg-white"
                                              />
                                            </div>

                                            {/* Upload REAL de anexo: enviado ao servidor como arquivo PRIVADO
                                                (acessível somente ao próprio aluno, instrutores e coordenação). */}
                                            {features.uploadArquivos && (
                                            <div className="space-y-1.5">
                                              <label className="text-[10.5px] font-bold text-slate-700 block">
                                                Anexar Documento (.pdf, .docx, imagens):
                                              </label>

                                              {attachedFile || (sub && sub.fileName) ? (
                                                <div className="flex items-center justify-between bg-slate-100 rounded-xl p-2.5 border border-slate-200 text-[10.5px]">
                                                  <div className="flex items-center gap-1.5 text-slate-700">
                                                    <FileText className="h-4 w-4 text-teal-600" />
                                                    <span>Anexo carregado: <strong className="font-bold text-slate-900">{attachedFile ? attachedFile.name : sub?.fileName}</strong></span>
                                                  </div>
                                                  <button
                                                    onClick={() => {
                                                      setTypedFiles(prev => ({ ...prev, [ex.id]: null }));
                                                      if (sub) sub.fileName = undefined;
                                                    }}
                                                    className="text-rose-600 hover:text-rose-700 font-extrabold uppercase text-[9px] cursor-pointer"
                                                  >
                                                    Remover anexo
                                                  </button>
                                                </div>
                                              ) : (
                                                <div className="flex gap-2 items-center">
                                                  <label className={`bg-white hover:bg-slate-50 text-slate-700 font-semibold px-3 py-1.5 rounded-xl border border-slate-200 text-[10px] transition-colors flex items-center gap-1.5 cursor-pointer ${isUploading ? 'opacity-50 pointer-events-none' : ''}`}>
                                                    <Download className="h-3.5 w-3.5 text-slate-500 rotate-180" />
                                                    <span>{isUploading ? 'Enviando arquivo...' : 'Selecionar arquivo (PDF / DOCX)'}</span>
                                                    <input
                                                      type="file"
                                                      accept=".pdf,.docx,.png,.jpg,.jpeg,.webp,.gif"
                                                      className="hidden"
                                                      disabled={isUploading}
                                                      onChange={async (e) => {
                                                        const file = e.target.files?.[0];
                                                        e.target.value = '';
                                                        if (!file) return;
                                                        setSimulatedUploading(prev => ({ ...prev, [ex.id]: true }));
                                                        try {
                                                          const formData = new FormData();
                                                          formData.append('file', file);
                                                          const res = await authFetch('/api/upload?visibility=private', { method: 'POST', body: formData });
                                                          if (!res.ok) {
                                                            const err = await res.json().catch(() => ({} as any));
                                                            showAlert(err.message || 'Falha ao enviar o arquivo. Verifique o formato e o tamanho.');
                                                            return;
                                                          }
                                                          const data = await res.json();
                                                          setTypedFiles(prev => ({ ...prev, [ex.id]: { name: data.fileName, url: data.url } }));
                                                        } catch {
                                                          showAlert('Servidor indisponível para envio de arquivos.');
                                                        } finally {
                                                          setSimulatedUploading(prev => ({ ...prev, [ex.id]: false }));
                                                        }
                                                      }}
                                                    />
                                                  </label>
                                                  <span className="text-[10px] text-slate-400 self-center">Opcional. O arquivo fica visível apenas para você e para os professores.</span>
                                                </div>
                                              )}
                                            </div>
                                            )}

                                            {/* Submit trigger button */}
                                            <button
                                              onClick={() => {
                                                const textToSubmit = currentTyped !== '' ? currentTyped : (sub ? sub.submissionText : '');
                                                if (!textToSubmit.trim()) {
                                                  showAlert('Por favor, digite o texto de sua resposta antes de enviar.');
                                                  return;
                                                }
                                                const finalFileName = attachedFile ? attachedFile.name : (sub ? sub.fileName : undefined);
                                                const finalFileUrl = attachedFile ? attachedFile.url : (sub ? sub.fileUrl : undefined);
                                                submitExercise(ex.id, textToSubmit.trim(), finalFileUrl, finalFileName);
                                                
                                                // Clear local draft state
                                                setTypedAnswers(prev => ({ ...prev, [ex.id]: '' }));
                                                setTypedFiles(prev => ({ ...prev, [ex.id]: null }));
                                                showAlert('Exercício enviado com sucesso para a avaliação dos professores!');
                                              }}
                                              className="bg-teal-600 hover:bg-teal-500 text-white font-bold px-4 py-2 rounded-xl text-xs transition-colors flex items-center gap-1.5 cursor-pointer ml-auto shadow-sm"
                                            >
                                              <Send className="h-3.5 w-3.5" />
                                              <span>{sub ? 'Reenviar Resposta Atualizada' : 'Enviar Atividade para Correção'}</span>
                                            </button>
                                          </div>
                                        )}
                                      </div>
                                    );
                                  })
                                )}
                              </div>
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  ) : (
                    /* Initial Welcome course billboard if no active lesson selected */
                    <div className="rounded-2xl border border-dashed border-teal-200 bg-teal-50/5 p-8 text-center text-slate-600 space-y-5 animate-in fade-in duration-300">
                      <div className="inline-flex rounded-full bg-teal-100 text-teal-600 p-4 shrink-0 shadow-xs border border-teal-200/55">
                        <Monitor className="h-10 w-10 animate-pulse" />
                      </div>
                      
                      <div className="max-w-md mx-auto space-y-2">
                        <h4 className="font-black text-slate-900 text-lg">Área do Aluno: Módulos de {selectedCourse.title}</h4>
                        <p className="text-xs text-slate-500 leading-relaxed">
                          Bem-vindo(a) à centralizadora oficial do curso! Aqui você tem acesso aos módulos sequenciais, tarefas de fixação e encontros ao vivo. Selecione uma aula de qualquer módulo na barra lateral para carregar a estação de aprendizagem.
                        </p>
                      </div>

                      <div className="flex justify-center flex-col sm:flex-row gap-3 pt-3">
                        <button
                          onClick={() => {
                            // Find first lesson to auto start
                            if (selectedCourse.lessons.length > 0) {
                              setActiveLesson(selectedCourse.lessons[0]);
                              const modules = getCourseModules(selectedCourse);
                              if (modules.length > 0) {
                                setSelectedModulePageName(modules[0].name);
                              }
                            }
                          }}
                          className="rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs px-5 py-2.5 shadow-sm hover:scale-[1.01] transition-transform flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                          <Play className="h-3.5 w-3.5 fill-white" />
                          <span>Iniciar Módulo 1 (Aula 1)</span>
                        </button>
                        
                        <button
                          onClick={() => { setSelectedCourse(null); setSelectedModulePageName(null); }}
                          className="rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 font-bold text-xs px-5 py-2.5 transition-colors cursor-pointer"
                        >
                          Trocar de Curso
                        </button>
                      </div>
                    </div>
                  )}

                  {/* Fórum de Discussão do Curso (controlado pela feature flag) */}
                  {features.forum && !activeLesson && !activeQuizTaking && (
                    <div className="mt-6 animate-in fade-in duration-300">
                      <CourseForum selectedCourse={selectedCourse} />
                    </div>
                  )}
                </div>

                {/* 2. Structured Syllabus Selector Sidebar Accordion Grid (lg:col-span-4) */}
                {(!activeLesson && !activeQuizTaking) && (
                <div className="lg:col-span-4 space-y-4">
                  
                  {/* Sidebar title */}
                  <div className="flex items-center justify-between">
                    <h4 className="font-black text-slate-900 flex items-center gap-1.5 text-xs uppercase tracking-wider">
                      <Layers className="h-4 w-4 text-teal-600" />
                      <span>Módulos do Curso</span>
                    </h4>

                    <span className="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                      {currentCourseProgress?.completedLessons.length || 0} / {selectedCourse.lessons.length} Aulas
                    </span>
                  </div>

                  {/* Syllabus Modules Container Accordion list */}
                  <div className="space-y-2.5">
                    {getCourseModules(selectedCourse).map((module) => {
                      const completedInModule = module.lessons.filter(l => currentCourseProgress?.completedLessons.includes(l.id)).length;
                      const hasCompletedAll = completedInModule === module.lessons.length && module.lessons.length > 0;
                      const isSelectedModule = selectedModulePageName === module.name;

                      return (
                        <div 
                          key={module.name} 
                          onClick={() => {
                            setSelectedModulePageName(module.name);
                          }}
                          className={`border rounded-lg overflow-hidden transition-all cursor-pointer p-3 flex items-center justify-between gap-3 group text-left relative ${
                            isSelectedModule 
                              ? 'bg-teal-50/50 border-teal-400 shadow-xs' 
                              : 'bg-white border-slate-200 hover:border-teal-300'
                          }`}
                        >
                          <div className="flex-1 text-left min-w-0">
                            <span className={`block text-[11px] font-bold leading-tight transition-colors ${
                              isSelectedModule ? 'text-teal-700' : 'text-slate-800 group-hover:text-teal-600'
                            }`}>
                              {module.name}
                            </span>
                            <div className="flex items-center gap-1 mt-1.5 opacity-70 group-hover:opacity-100 transition-opacity">
                              <span className={`text-[9px] font-bold uppercase tracking-wider transition-colors ${
                                isSelectedModule ? 'text-teal-600' : 'text-slate-500 group-hover:text-teal-600'
                              }`}>
                                {isSelectedModule ? 'Módulo Aberto' : 'Acessar Capítulos'}
                              </span>
                              {!isSelectedModule && (
                                <ChevronRight className="h-2.5 w-2.5 text-slate-400 group-hover:text-teal-500 group-hover:translate-x-0.5 transition-transform" />
                              )}
                            </div>
                          </div>

                          <div className="flex items-center shrink-0">
                            {hasCompletedAll ? (
                              <span className="text-emerald-600 flex items-center justify-center p-1 bg-emerald-50 rounded-full">
                                <CheckCircle className="h-3.5 w-3.5" />
                              </span>
                            ) : (
                              <span className={`text-[9.5px] font-bold font-mono px-1.5 py-0.5 rounded border ${
                                isSelectedModule 
                                  ? 'bg-teal-100/50 border-teal-200 text-teal-700' 
                                  : 'bg-slate-100 border-slate-200 text-slate-500'
                              }`}>
                                {completedInModule}/{module.lessons.length}
                              </span>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>

                  {/* 3. Live Mentoring Sessions styled as another module item */}
                  <div className="border border-teal-100 bg-teal-50/15 rounded-xl p-3 text-left space-y-2.5">
                    <h5 className="font-bold text-slate-900 text-[10px] uppercase tracking-wider flex items-center gap-1.5">
                      <Video className="h-3.5 w-3.5 text-teal-600" />
                      <span>Transmissões ao Vivo</span>
                    </h5>

                    <div className="space-y-2">
                      {selectedCourse.liveSessions.map((session, idx) => {
                        const isAttended = currentCourseProgress?.attendedLiveSessions.includes(session.id) || false;
                        return (
                          <div key={`${session.id}-${idx}`} className="bg-white rounded-lg border border-teal-100/40 p-2.5 leading-relaxed text-left text-[11px]">
                            
                            <div className="flex items-start justify-between gap-1.5">
                              <div>
                                <strong className="font-bold text-slate-900">{session.title}</strong>
                                <span className="text-[9px] text-slate-400 block mt-0.5">{session.scheduledAt} ({session.durationMinutes} min)</span>
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
                                    session.isLive
                                      ? 'bg-red-600 hover:bg-red-500 text-white animate-pulse'
                                      : 'bg-teal-600 hover:bg-teal-500 text-white'
                                  }`}
                                >
                                  <Video className="h-4 w-4 fill-white shrink-0" />
                                  <span>{session.isLive ? 'Entrar ao Vivo' : 'Entrar na Sala'}</span>
                                </button>
                                
                                <a
                                  href={session.meetingLink}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/60 font-bold py-2 px-3 rounded-lg text-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer text-center"
                                >
                                  <ExternalLink className="h-4 w-4 text-slate-500 shrink-0" />
                                  <span>Google Meet</span>
                                </a>
                              </div>
                              {!session.isLive && (
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

                  {/* 4. Interactive Quizzes / Tests Block */}
                  <div className="border border-amber-100 bg-amber-50/10 rounded-xl p-3.5 text-left space-y-3 shadow-2xs">
                    <h5 className="font-bold text-slate-900 text-[10px] uppercase tracking-wider flex items-center gap-1.5">
                      <CheckSquare className="h-3.5 w-3.5 text-amber-600" />
                      <span>Testes e Avaliações</span>
                    </h5>

                    <div className="space-y-2.5">
                      {quizzes.filter(q => q.courseId === selectedCourse.id).length === 0 ? (
                        <div className="bg-white rounded-lg border border-slate-200 p-4.5 text-center text-xs text-slate-400">
                          Nenhum teste elaborado para este curso no momento.
                        </div>
                      ) : (
                        quizzes.filter(q => q.courseId === selectedCourse.id).map((quiz) => {
                          const userSub = quizSubmissions.find(s => s.quizId === quiz.id && s.userId === activeUser.id);
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
                                    Último envio: {userSub.submittedAt}
                                  </div>
                                  <button
                                    onClick={() => {
                                      setCurrentAnswers({});
                                      setAnsweredQuestions({});
                                      setCurrentQuestionIdx(0);
                                      setQuizResult(null);
                                      setHasSubmitted(false);
                                      setActiveQuizTaking(quiz);
                                    }}
                                    className="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 font-bold py-2 px-4 rounded-lg text-xs transition-colors flex items-center justify-center cursor-pointer shadow-2xs"
                                  >
                                    Refazer Avaliação
                                  </button>
                                </div>
                              ) : (
                                <button
                                  onClick={() => {
                                    setCurrentAnswers({});
                                    setAnsweredQuestions({});
                                    setCurrentQuestionIdx(0);
                                    setQuizResult(null);
                                    setHasSubmitted(false);
                                    setActiveQuizTaking(quiz);
                                  }}
                                  className="w-full bg-amber-600 hover:bg-amber-500 text-white font-bold py-2 px-4 rounded-lg text-xs uppercase tracking-wider transition-all flex items-center justify-center cursor-pointer shadow-xs"
                                >
                                  Começar
                                </button>
                              )}
                            </div>
                          );
                        })
                      )}
                    </div>
                  </div>

                  {/* 5. Practical Exercises Sidebar Block */}
                  <div className="border border-teal-100 bg-teal-50/10 rounded-xl p-3.5 text-left space-y-3 shadow-2xs">
                    <h5 className="font-bold text-slate-900 text-[10px] uppercase tracking-wider flex items-center gap-1.5">
                      <FileCheck className="h-3.5 w-3.5 text-teal-600" />
                      <span>Exercícios de Fixação</span>
                    </h5>

                    <div className="space-y-2.5">
                      {practicalExercises.filter(ex => ex.courseId === selectedCourse.id).length === 0 ? (
                        <div className="bg-white rounded-lg border border-slate-200 p-4.5 text-center text-xs text-slate-400">
                          Nenhum exercício prático lançado neste curso.
                        </div>
                      ) : (
                        <div className="space-y-2.5">
                          {practicalExercises.filter(ex => ex.courseId === selectedCourse.id).map((ex, idx) => {
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

                          <button
                            onClick={() => {
                              if (selectedCourse.lessons.length > 0) {
                                setActiveLesson(selectedCourse.lessons[0]);
                                setActiveTab('exercicios');
                              } else {
                                showAlert('Este curso ainda não possui aulas cadastradas.');
                              }
                            }}
                            className="w-full bg-teal-600 hover:bg-teal-500 text-white font-bold py-2 px-4 rounded-lg text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 cursor-pointer shadow-xs"
                          >
                            <FileCheck className="h-4 w-4" />
                            <span>Abrir Atividades Práticas</span>
                          </button>
                        </div>
                      )}
                    </div>
                  </div>

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
                      {courses.flatMap(c => c.liveSessions).filter(s => !s.isLive).map((session, idx) => (
                        <div key={`${session.id}-${idx}`} className="flex items-center gap-4 p-3 rounded-xl border border-slate-100 bg-slate-50 hover:bg-white transition-colors group">
                           <div className="bg-white p-2.5 rounded-lg border border-slate-200 text-center min-w-[70px] group-hover:border-teal-200 group-hover:bg-teal-50 transition-all">
                              <span className="block text-[10px] font-black text-slate-400 uppercase leading-none mb-1">DATA</span>
                              <span className="text-sm font-black text-slate-700 leading-none">{session.scheduledAt.split(',')[0]}</span>
                           </div>
                           <div className="flex-1 min-w-0">
                              <span className="text-[9px] font-bold text-teal-600 bg-teal-50 px-1.5 py-0.5 rounded uppercase tracking-wide">
                                {courses.find(c => c.id === session.courseId)?.title}
                              </span>
                              <h5 className="text-[11px] font-bold text-slate-900 mt-1 truncate">{session.title}</h5>
                              <div className="flex items-center gap-3 mt-1.5 text-[9px] text-slate-500 font-medium">
                                <span className="flex items-center gap-1"><Clock className="h-2.5 w-2.5" />{session.scheduledAt.split('às')[1] || 'TBD'}</span>
                                <span className="flex items-center gap-1"><Globe className="h-2.5 w-2.5" />Horário de Brasília (Local)</span>
                              </div>
                           </div>
                           <a href={session.meetingLink} target="_blank" rel="noreferrer" className="bg-slate-950 text-white p-2 rounded-lg hover:bg-slate-800 transition-colors">
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
                    <div className="flex flex-wrap items-center gap-2 text-[11px] text-amber-600 font-bold">
                      <span className="bg-amber-50 border border-amber-200/60 px-2 py-0.5 rounded-md">★ 4.9 de Avaliação Acadêmica Geral</span>
                      <span className="text-slate-300">•</span>
                      <span>Mais de 320 alunos formados e certificados neste curso</span>
                    </div>
                    <h2 className="text-2xl md:text-3xl font-black text-slate-900 leading-tight tracking-tight">
                      {viewingCatalogCourse.title}
                    </h2>
                    <p className="text-xs md:text-sm text-slate-600 leading-relaxed max-w-4xl">
                      {viewingCatalogCourse.description}
                    </p>
                  </div>

                  {/* Churn Prevention Metric Pillars - Compact & Sleek */}
                  <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-2">
                    <div className="bg-emerald-50/40 border border-emerald-100/70 rounded-xl p-3 flex items-center gap-2.5 transition-all hover:bg-emerald-50/70">
                      <span className="p-2 bg-emerald-100 text-emerald-700 rounded-lg shrink-0">
                        <Award className="h-4 w-4" />
                      </span>
                      <div className="min-w-0">
                        <strong className="block text-[11px] font-black text-slate-800 leading-none">Certificado Garantido</strong>
                        <span className="text-[9.5px] text-slate-500 mt-1 block truncate">Frequência mínima de {courseMinAttendance(viewingCatalogCourse)}%</span>
                      </div>
                    </div>

                    <div className="bg-teal-50/40 border border-teal-100/70 rounded-xl p-3 flex items-center gap-2.5 transition-all hover:bg-teal-50/70">
                      <span className="p-2 bg-teal-100 text-teal-700 rounded-lg shrink-0">
                        <Layers className="h-4 w-4" />
                      </span>
                      <div className="min-w-0">
                        <strong className="block text-[11px] font-black text-slate-800 leading-none">Fixação Rápida</strong>
                        <span className="text-[9.5px] text-slate-500 mt-1 block truncate">Quizzes & leituras dinâmicas</span>
                      </div>
                    </div>

                    <div className="bg-sky-50/40 border border-sky-100/70 rounded-xl p-3 flex items-center gap-2.5 transition-all hover:bg-sky-50/70">
                      <span className="p-2 bg-sky-100 text-sky-700 rounded-lg shrink-0">
                        <Calendar className="h-4 w-4" />
                      </span>
                      <div className="min-w-0">
                        <strong className="block text-[11px] font-black text-slate-800 leading-none">Aulas ao Vivo</strong>
                        <span className="text-[9.5px] text-slate-500 mt-1 block truncate">Plantões semanais c/ Professor</span>
                      </div>
                    </div>

                    <div className="bg-blue-50/40 border border-blue-100/70 rounded-xl p-3 flex items-center gap-2.5 transition-all hover:bg-blue-50/70">
                      <span className="p-2 bg-blue-100 text-blue-700 rounded-lg shrink-0">
                        <Shield className="h-4 w-4" />
                      </span>
                      <div className="min-w-0">
                        <strong className="block text-[11px] font-black text-slate-800 leading-none">Regras de Participação</strong>
                        <span className="text-[9.5px] text-slate-500 mt-1 block truncate">Período de ajuste de 5 dias</span>
                      </div>
                    </div>
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
                            <span className="text-[10px] text-slate-400 font-bold block mt-0.5">Conheça os módulos principais antes de iniciar sua matrícula.</span>
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

                              const descriptionsPerIndex = [
                                "Introdução Básica: Alinhamento das diretrizes curriculares do AVA, glossário fundamental e primeiras leituras acadêmicas.",
                                "Aprofundamento Prático: Atividades com exemplos de mercado passo a passo no sandbox de simulação e consolidação conceitual.",
                                "Avaliação Teórica de Meio-Termo: Métricas, boas práticas de resolução rápida e exercícios adaptativos de fixação imediata.",
                                "Trabalho Final Integrado: Casos corporativos práticos reais e orientação direta para postagem e validação do portfólio."
                              ];
                              const customizedDesc = descriptionsPerIndex[idx % descriptionsPerIndex.length];

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
                                      <span className="font-extrabold text-[#540D6E] block text-[9.5px] uppercase tracking-wider mb-1">Destaques do Módulo:</span>
                                      {customizedDesc} {lesson.content ? "Este bloco traz também material teórico detalhado composto por textos formatados em Markdown e testes simulados de prática." : ""}
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
                            <span className="inline-flex items-center gap-1.5 bg-emerald-50 border border-emerald-100 rounded px-1.5 py-0.5 text-[8.5px] font-black text-emerald-700 uppercase tracking-widest mt-0.5">
                              <span className="h-1 w-1 bg-emerald-500 rounded-full animate-pulse" />
                              <span>ON-LINE NO CHAT</span>
                            </span>
                          </div>
                        </div>

                        {/* Quality Specifications - Simple list with light dividers and less heavy boxes */}
                        <div className="space-y-2.5 pt-2 text-xs font-medium text-slate-600 border-t border-slate-100">
                          <div className="flex justify-between items-center py-1.5 border-b border-slate-100">
                            <span>Módulos:</span>
                            <strong className="text-slate-800 font-bold font-mono text-[11px]">
                              {viewingCatalogCourse.lessons ? viewingCatalogCourse.lessons.length : 0}
                            </strong>
                          </div>
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
                  {/* Certificados Resumo Widget */}
                  <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-in fade-in duration-300">
                    <div className="flex items-center gap-4">
                      <div className="p-3 bg-teal-50 text-teal-600 rounded-xl">
                        <Award className="h-6 w-6" />
                      </div>
                      <div>
                        <h3 className="font-black text-slate-800 text-sm uppercase tracking-wider">Meus Certificados</h3>
                        <div className="flex flex-wrap items-center gap-3 mt-1 text-xs text-slate-500">
                          <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-emerald-500"></span>{certificates.filter(c => c.userId === activeUser.id).length} disponíveis</span>
                          <span className="flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-blue-500"></span>{enrollmentRecord.enrolledCourseId ? 1 : 0} em andamento</span>
                        </div>
                      </div>
                    </div>
                    <button onClick={() => setActiveDashboardTab('certificates')} className="shrink-0 px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer border border-slate-200">
                      Ver Certificados
                    </button>
                  </div>

                  {/* Scenario 1: Active Enrolled Course Card */}
                  {enrollmentRecord.enrolledCourseId ? (
                    (() => {
                      const activeCourse = courses.find(c => c.id === enrollmentRecord.enrolledCourseId);
                      if (!activeCourse) return null;
                      const attendance = calculateAttendancePercent(activeCourse.id);
                      const minAttendance = courseMinAttendance(activeCourse);
                      const expired = isCourseExpired(activeCourse.contractExpirationDate);
                      
                      if (expired) {
                        return (
                          <div className="rounded-2xl border border-amber-250 bg-amber-50/20 p-5 md:p-6 shadow-xs animate-in fade-in duration-300">
                            <div className="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-6">
                              <div className="space-y-2 max-w-xl">
                                <span className="bg-amber-100 text-amber-800 border border-amber-200 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest font-mono">⚠️ Vigência de Exibição Encerrada (Arquivado)</span>
                                <h3 className="text-base md:text-lg font-black text-slate-850 leading-tight">{activeCourse.title}</h3>
                                <p className="text-xs text-slate-500 leading-relaxed">
                                  Este curso foi <strong>arquivado preventivamente</strong> e o acesso letivo foi suspenso, pois o prazo contratual de exibição encerrou em <strong>{activeCourse.contractExpirationDate}</strong>.
                                </p>
                                <p className="text-[11px] text-amber-900 bg-amber-100/40 p-3 rounded-xl border border-amber-200/50 leading-relaxed mt-2.5">
                                  💡 <strong>Como estudar outra disciplina?</strong> Para liberar seu cadastro e escolher um novo curso ativo, clique no botão <strong>"Cancelar inscrição"</strong> ao lado. Isso abrirá imediatamente o catálogo de disciplinas disponíveis para você se matricular e começar a estudar!
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
                        <div className="rounded-2xl border border-teal-200 bg-teal-50/20 p-5 md:p-6 shadow-xs animate-in fade-in duration-300">
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
                    })()
                  ) : null}

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
                              <span>⚠️ Restrição Temporária de Matrícula - Justificativa Pendente</span>
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

                  {/* Scenario 3: Course Selection Catalog (Available when no active enrollment and not penalized) */}
                  {!enrollmentRecord.enrolledCourseId && !(features.penalidadesCancelamento && enrollmentRecord.dropOutPenaltyUntil && new Date(enrollmentRecord.dropOutPenaltyUntil).getTime() > Date.now()) && (
                    <div className="space-y-5">
                      <div className="bg-teal-50/55 p-4 rounded-2xl border border-teal-150/40 flex items-center gap-3">
                        <Sparkles className="h-4.5 w-4.5 text-teal-600 shrink-0" />
                        <div className="text-left text-xs text-slate-700 leading-relaxed">
                          <span className="font-extrabold text-teal-950 mr-1.5">Início da Jornada:</span>
                          Selecione um curso na lista abaixo para se matricular e iniciar os seus estudos de forma imediata!
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
                                <option value="recent">📅 Mais recentes</option>
                                <option value="alphabetical-asc">🔤 Ordem alfabética (A-Z)</option>
                                <option value="alphabetical-desc">🔤 Ordem alfabética (Z-A)</option>
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
                              const activeCourses = courses.filter(c => !isCourseExpired(c.contractExpirationDate));
                              return (
                                <>
                                  <button
                                    type="button"
                                    onClick={() => {
                                      setSelectedCategory('all');
                                      speakText("Exibindo todas as áreas acadêmicas.");
                                    }}
                                    className={`px-3 py-1.5 rounded-full text-xs font-bold cursor-pointer transition-all ${
                                      selectedCategory === 'all'
                                        ? 'bg-[#540D6E] text-white shadow-xs scale-102 font-black'
                                        : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'
                                    }`}
                                  >
                                    📂 Ver Tudo ({activeCourses.length})
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
                                        className={`px-3 py-1.5 rounded-full text-xs font-bold cursor-pointer transition-all ${
                                          selectedCategory === category
                                            ? 'bg-teal-600 text-white shadow-xs scale-102 font-black'
                                            : 'bg-white border border-slate-200 text-slate-600 hover:bg-teal-50'
                                        }`}
                                      >
                                        🔖 {category} ({count})
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
                        const filtered = courses
                          .filter(c => {
                            if (isCourseExpired(c.contractExpirationDate)) return false;
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
                                const isAlreadyCompleted = enrollmentRecord.completedCourseIds?.includes(course.id);
                                return (
                                  <div key={`${course.id}-${idx}`} className="group relative rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition-with-duration hover:shadow-md hover:border-[#540D6E]/30 flex flex-col justify-between text-left animate-in fade-in zoom-in-95 duration-150">
                                    <div className="space-y-3 ms-0.5">
                                      <div className="flex items-center justify-between">
                                        <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500 border border-slate-200 flex items-center gap-1">
                                          🔖 {course.category}
                                        </span>
                                        {isAlreadyCompleted ? (
                                          <span className="text-[10px] text-emerald-700 font-bold bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-full flex items-center gap-1 shadow-2xs">
                                            ✓ Concluído
                                          </span>
                                        ) : (
                                          <span className="text-[10px] text-teal-600 font-bold bg-teal-50 border border-teal-100 px-2 py-0.5 rounded-full shadow-2xs">
                                            Meta: {minAtt}% pres.
                                          </span>
                                        )}
                                      </div>
                                      <div>
                                        <h4 className="text-sm font-black text-slate-950 group-hover:text-[#540D6E] transition-colors line-clamp-1">{course.title}</h4>
                                        <p className="mt-1 text-xs text-slate-500 leading-relaxed line-clamp-2">{course.description}</p>
                                        <div className="flex items-center gap-2 mt-2">
                                          <span className="text-[9px] bg-slate-100 text-slate-600 font-bold px-1.5 py-0.5 rounded-md flex items-center gap-1 border border-slate-150">
                                            📚 {course.lessons ? course.lessons.length : 0} {course.lessons && course.lessons.length === 1 ? 'Aula' : 'Aulas'}
                                          </span>
                                          <span className="text-[9px] bg-slate-100 text-slate-600 font-bold px-1.5 py-0.5 rounded-md flex items-center gap-1 border border-slate-150">
                                            🎥 {course.liveSessions ? course.liveSessions.length : 0} {course.liveSessions && course.liveSessions.length === 1 ? 'Sessão Ao Vivo' : 'Sessões'}
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
                                        className="text-xs bg-[#540D6E] hover:bg-[#430a58] text-white font-bold uppercase tracking-wider px-3.5 py-1.8 rounded-lg hover:scale-101 active:scale-98 transition-all cursor-pointer flex items-center gap-1 select-none shadow-2xs"
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
      ) : activeDashboardTab === 'certificates' ? (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-5xl mx-auto space-y-4">
          <div className="text-left mb-2">
            <button
              onClick={() => setActiveDashboardTab('general')}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 transition-all cursor-pointer text-xs font-black uppercase tracking-wider border border-slate-200/65"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
              <span>Voltar ao Meu Painel de Estudos</span>
            </button>
          </div>
          {/* Header */}
          <div className="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-left mb-6">
            <h2 className="text-xl font-black text-slate-900 flex items-center gap-2 mb-2">
              <Award className="h-6 w-6 text-teal-600" />
              Meus Certificados
            </h2>
            <p className="text-xs text-slate-600 bg-white p-3 rounded-lg border border-slate-100 inline-block">
              <span className="font-bold text-teal-700 mr-1">Aviso:</span> O certificado será liberado conforme os critérios de conclusão definidos para este curso.
            </p>
          </div>

          {/* Resumo */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div className="bg-white border border-emerald-100 rounded-2xl p-5 shadow-xs text-left">
              <span className="text-3xl font-black text-emerald-600 block mb-1">{certificates.filter(c => c.userId === activeUser.id).length}</span>
              <span className="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">Certificados Disponíveis</span>
            </div>
            <div className="bg-white border border-blue-100 rounded-2xl p-5 shadow-xs text-left">
              <span className="text-3xl font-black text-blue-600 block mb-1">{enrollmentRecord.enrolledCourseId ? 1 : 0}</span>
              <span className="text-[11px] font-bold text-blue-800 uppercase tracking-wider">Cursos em Andamento</span>
            </div>
            <div className="bg-white border border-amber-100 rounded-2xl p-5 shadow-xs text-left">
              <span className="text-3xl font-black text-amber-600 block mb-1">{
                courses.filter(c => c.id === enrollmentRecord.enrolledCourseId && calculateAttendancePercent(c.id) < courseMinAttendance(c)).length
              }</span>
              <span className="text-[11px] font-bold text-amber-800 uppercase tracking-wider">Certificados Pendentes</span>
            </div>
          </div>

          {/* Tabs Navigation */}
          <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 mb-6 pb-2">
            {[
              { id: 'available', label: 'Disponíveis' },
              { id: 'in_progress', label: 'Em andamento' },
              { id: 'validation', label: 'Validação' },
            ].map((tab) => (
              <button
                key={tab.id}
                onClick={() => {
                  setActiveCertificatesTab(tab.id as 'available' | 'in_progress' | 'validation');
                  setValidationResult(null);
                  setValidationCode('');
                }}
                className={`px-5 py-2.5 rounded-t-lg text-xs font-bold uppercase tracking-wider transition-all cursor-pointer ${
                  activeCertificatesTab === tab.id
                    ? 'bg-slate-800 text-white border-b-2 border-slate-800'
                    : 'bg-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>

          {/* Tab Content */}
          <div className="text-left space-y-4">
            {activeCertificatesTab === 'available' && (
              <div className="space-y-4 animate-in fade-in duration-300">
                {certificates.filter(c => c.userId === activeUser.id).length === 0 ? (
                  <div className="py-12 border-2 border-dashed border-slate-200 bg-slate-50 rounded-2xl flex flex-col items-center justify-center text-slate-500">
                    <Award className="h-10 w-10 mb-3 text-slate-300" />
                    <p className="text-sm font-bold text-slate-700">Você ainda não possui certificados disponíveis.</p>
                    <p className="text-xs mt-1">Conclua um curso para liberar seu primeiro certificado.</p>
                  </div>
                ) : (
                  certificates.filter(c => c.userId === activeUser.id).map((cert, index) => {
                    const course = courses.find(c => c.id === cert.courseId);
                    const workload = course?.cargaHoraria ?? 40;
                    return (
                      <div key={`${cert.id}-${index}`} className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-5">
                        <div className="space-y-2">
                          <div className="flex items-center gap-2">
                            <span className="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded text-[9px] font-black uppercase tracking-wider flex items-center gap-1">
                              <CheckCircle className="h-3 w-3" /> Certificado disponível
                            </span>
                          </div>
                          <h4 className="font-black text-slate-900 text-lg leading-tight">{cert.courseTitle}</h4>
                          <div className="flex flex-wrap items-center gap-4 text-xs text-slate-600 font-medium">
                            <span className="flex items-center gap-1.5"><Calendar className="h-3.5 w-3.5 text-slate-400" /> Concluído em: {cert.issueDate}</span>
                            <span className="flex items-center gap-1.5"><Clock className="h-3.5 w-3.5 text-slate-400" /> Carga Horária: {workload}h</span>
                            <span className="flex items-center gap-1.5"><CheckCircle className="h-3.5 w-3.5 text-slate-400" /> Concluído: 100%</span>
                          </div>
                          <div className="pt-1 flex items-center gap-2 text-[10px] font-mono text-slate-500 bg-slate-50 px-2.5 py-1.5 rounded-lg border border-slate-100 w-fit">
                            <span>Código: <strong>{cert.verificationHash}</strong></span>
                            <button 
                              onClick={() => {
                                navigator.clipboard.writeText(cert.verificationHash);
                                showAlert('Código copiado para a área de transferência!');
                              }}
                              className="text-teal-600 hover:text-teal-700 font-bold ml-2 uppercase tracking-wider transition-colors cursor-pointer"
                            >
                              Copiar código
                            </button>
                          </div>
                        </div>
                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                          <button
                            onClick={async () => {
                              const error = await downloadCertificatePdf(cert.id);
                              if (error) showAlert(error);
                            }}
                            className="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider px-5 py-2.5 rounded-xl transition-all shadow-xs flex items-center justify-center gap-2 cursor-pointer"
                          >
                            <Download className="h-4 w-4" />
                            Baixar PDF
                          </button>
                          <button
                            onClick={() => setSelectedCertificate(cert)}
                            className="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs uppercase tracking-wider px-5 py-2.5 rounded-xl transition-all shadow-xs flex items-center justify-center gap-2 cursor-pointer"
                          >
                            <Award className="h-4 w-4" />
                            Visualizar
                          </button>
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            )}

            {activeCertificatesTab === 'in_progress' && (
              <div className="space-y-4 animate-in fade-in duration-300">
                {!enrollmentRecord.enrolledCourseId ? (
                  <div className="py-12 border-2 border-dashed border-slate-200 bg-slate-50 rounded-2xl flex flex-col items-center justify-center text-slate-500">
                    <BookOpen className="h-10 w-10 mb-3 text-slate-300" />
                    <p className="text-sm font-bold text-slate-700">Você não possui cursos em andamento no momento.</p>
                  </div>
                ) : (
                  (() => {
                    const activeCourse = courses.find(c => c.id === enrollmentRecord.enrolledCourseId);
                    if (!activeCourse) return null;
                    const attendance = calculateAttendancePercent(activeCourse.id);
                    const minAttendance = courseMinAttendance(activeCourse);
                    
                    if (activeCourse.category.includes('Sem Certificado')) {
                      return (
                         <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">
                          <h4 className="font-black text-slate-900 text-lg leading-tight mb-2">{activeCourse.title}</h4>
                          <span className="px-3 py-1 bg-slate-100 text-slate-600 rounded-lg text-[11px] font-bold inline-block">Este curso não possui emissão de certificado.</span>
                        </div>
                      );
                    }

                    return (
                      <div className="bg-white border border-amber-200 rounded-2xl p-5 shadow-xs flex flex-col md:flex-row justify-between gap-6">
                        <div className="space-y-4 w-full max-w-2xl">
                          <div>
                            <h4 className="font-black text-slate-900 text-lg leading-tight mb-1">{activeCourse.title}</h4>
                            <p className="text-xs text-slate-500 font-medium">Você concluiu {attendance}% do curso. Para liberar o certificado, é necessário atingir {minAttendance}%.</p>
                          </div>
                          
                          <div className="space-y-1.5">
                            <div className="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-slate-500">
                              <span>Progresso Atual</span>
                              <span className="text-amber-600 font-black">{attendance}% / {minAttendance}%</span>
                            </div>
                            <div className="w-full bg-slate-100 rounded-full h-2">
                              <div className="bg-amber-500 h-2 rounded-full transition-all duration-500" style={{ width: `${Math.min(100, attendance)}%` }}></div>
                            </div>
                          </div>
                          
                          <div className="bg-amber-50 border border-amber-100 rounded-xl p-3 text-xs text-amber-800">
                            <strong>O que falta?</strong> Continue assistindo as aulas teóricas e conclua os módulos pendentes para atingir o mínimo necessário.
                          </div>
                        </div>
                        <div className="flex items-center shrink-0">
                          <button
                            onClick={() => {
                              setSelectedCourse(activeCourse);
                              setActiveDashboardTab('general');
                              window.scrollTo({ top: 0, behavior: 'smooth' });
                            }}
                            className="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs uppercase tracking-wider px-5 py-2.5 rounded-xl transition-all shadow-xs flex items-center justify-center gap-2 w-full md:w-auto cursor-pointer"
                          >
                            <PlayCircle className="h-4 w-4" />
                            Continuar curso
                          </button>
                        </div>
                      </div>
                    );
                  })()
                )}
              </div>
            )}

            {activeCertificatesTab === 'validation' && (
              <div className="space-y-6 animate-in fade-in duration-300 max-w-2xl">
                <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs">
                  <h3 className="font-black text-slate-800 text-sm uppercase tracking-wider mb-2">Validar um Certificado</h3>
                  <p className="text-xs text-slate-500 mb-6">
                    Insira o código de validação (hash alfanumérico) que consta no certificado para verificar a autenticidade e os dados de emissão.
                  </p>
                  
                  <div className="flex flex-col sm:flex-row gap-3">
                    <input
                      type="text"
                      placeholder="Ex: AVA-1A2B3C4D5E6F7890"
                      value={validationCode}
                      onChange={(e) => setValidationCode(e.target.value)}
                      onKeyDown={(e) => { if (e.key === 'Enter') handleValidateCertificate(); }}
                      className="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 font-mono focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                    />
                    <button
                      onClick={handleValidateCertificate}
                      disabled={isValidating}
                      className="bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs uppercase tracking-wider px-6 py-3 rounded-xl transition-all shadow-xs cursor-pointer disabled:opacity-60 disabled:cursor-wait"
                    >
                      {isValidating ? 'Validando...' : 'Validar'}
                    </button>
                  </div>

                  {validationResult && (
                    <div className={`mt-6 p-5 rounded-xl border animate-in slide-in-from-bottom-2 duration-300 ${
                      validationResult.valid ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'
                    }`}>
                      {validationResult.valid ? (
                        <div className="space-y-3">
                          <div className="flex items-center gap-2 text-emerald-700 mb-2">
                            <CheckCircle className="h-5 w-5" />
                            <strong className="text-sm uppercase tracking-wider">Certificado Válido</strong>
                          </div>
                          <div className="space-y-1.5 text-xs text-slate-700">
                            <p><strong className="text-slate-900 w-24 inline-block">Aluno:</strong> {validationResult.studentName}</p>
                            <p><strong className="text-slate-900 w-24 inline-block">Curso:</strong> {validationResult.courseTitle}</p>
                            {validationResult.cargaHoraria != null && (
                              <p><strong className="text-slate-900 w-24 inline-block">Carga Horária:</strong> {validationResult.cargaHoraria}h</p>
                            )}
                            <p><strong className="text-slate-900 w-24 inline-block">Emissão:</strong> {validationResult.issueDate}</p>
                            <p><strong className="text-slate-900 w-24 inline-block">Código:</strong> <span className="font-mono bg-white px-1.5 py-0.5 rounded border border-slate-100">{validationCode}</span></p>
                          </div>
                        </div>
                      ) : (
                        <div className="flex items-center gap-3 text-amber-800">
                          <Info className="h-5 w-5 shrink-0" />
                          <p className="text-xs font-bold leading-relaxed">{validationResult.message}</p>
                        </div>
                      )}
                    </div>
                  )}
                </div>
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
                  answer: 'O certificado digital oficial chancelado é liberado de forma imediata assim que você atingir o progresso mínimo de 70% de presença ativa no curso. Basta acessar a aba "Certificados" na barra superior para baixá-lo em formato PDF seguro e chancelado com selo eletrônico.'
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
                            <p className="whitespace-pre-line text-[11.5px] font-sans leading-relaxed break-words">{msg.text}</p>
                          </div>
                          <span className="text-[8px] text-slate-400 mt-1 px-1 font-mono">
                            {new Date(msg.timestamp).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                          </span>
                        </div>
                      );
                    })
                )}
              </div>

              {/* Message Typing and send Form */}
              <form onSubmit={(e) => {
                e.preventDefault();
                const input = (e.currentTarget.elements.namedItem('messageText') as HTMLInputElement);
                const text = input.value.trim();
                if (text) {
                  sendDirectMessage(activeUser.id, text);
                  input.value = '';
                  
                  // Smart auto reply simulation representing prompt responses from instructor
                  setTimeout(() => {
                    const matchPhrases = [
                      "Excelente dúvida, João! Analisei seu progresso de presença e recomendo atentar para as próximas aulas ao vivo para consolidarmos isso juntos.",
                      "Olá! Registrei sua colocação acadêmica aqui. Vou abordar exatamente este tópico no encerramento da nossa transmissão de hoje! Conto com você lá.",
                      "Perfeito! Recebi sua mensagem. Já estou revisando e logo te envio um feedback detalhado com indicações extras de leitura técnica."
                    ];
                    const randomPhrase = matchPhrases[Math.floor(Math.random() * matchPhrases.length)];
                    const saved = localStorage.getItem('ava_direct_messages');
                    const currentDMs = saved ? JSON.parse(saved) : [];
                    const tutorResponse = {
                      id: `dm-bot-${Date.now()}`,
                      studentUserId: activeUser.id,
                      studentName: activeUser.name,
                      senderName: 'Gestor de Conteúdos',
                      senderRole: 'instructor',
                      text: randomPhrase,
                      timestamp: new Date().toISOString()
                    };
                    localStorage.setItem('ava_direct_messages', JSON.stringify([...currentDMs, tutorResponse]));
                    let storageEvent;
                    try {
                      storageEvent = new Event('storage');
                    } catch (e) {
                      storageEvent = document.createEvent('Event');
                      storageEvent.initEvent('storage', true, true);
                    }
                    window.dispatchEvent(storageEvent);
                  }, 1800);
                }
              }} className="flex gap-2">
                <input
                  name="messageText"
                  type="text"
                  required
                  placeholder="Digite sua mensagem ao Gestor de Conteúdos..."
                  className="flex-1 rounded-xl border border-slate-205 bg-white px-4 py-3 text-xs text-slate-700 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all shadow-3xs"
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

              <div className="bg-slate-50 border border-slate-200 p-5 rounded-2xl text-left space-y-2.5">
                <span className="text-[9px] uppercase tracking-widest text-[#540D6E] font-extrabold font-mono block">INFO ÚTIL</span>
                <div className="space-y-1 text-[11px] text-slate-500 leading-relaxed">
                  <p><strong>E-mail:</strong> faleconosco@paulo-freire.org.br</p>
                  <p><strong>Certificados:</strong> suporte-digital@freire.com</p>
                </div>
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
                      answer: 'O certificado digital oficial chancelado é liberado de forma imediata assim que você atingir o progresso mínimo de 70% de presença ativa no curso. Basta acessar a aba "Certificados" na barra superior para baixá-lo em formato PDF seguro e chancelado com selo eletrônico.'
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

      {/* Certificate Viewer Modal Overlay */}
      {selectedCertificate && (
        <CertificateTemplate
          certificate={selectedCertificate}
          onClose={() => setSelectedCertificate(null)}
        />
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

              {/* Scrollable Curriculum list */}
              <div className="p-6 overflow-y-auto space-y-5 flex-1 max-h-[60vh] no-scrollbar">
                <div className="bg-teal-50/50 border border-teal-100 rounded-xl p-4 text-xs font-medium text-teal-900 leading-relaxed">
                  💡 <strong>Diretrizes do Curso:</strong> Esta ementa foi planejada para fornecer competências reais de mercado passo a passo. Verifique abaixo todos os módulos e seus requisitos.
                </div>

                <div className="space-y-4">
                  {[
                    {
                      id: 'mod-1',
                      title: 'Módulo 1: Introdução & Conceitos Iniciais',
                      duration: '5 horas',
                      contentType: 'Vídeo-aulas síncronas gravadas, Leituras de suporte e Quiz de fixação',
                      learningGoals: 'Compreender a arquitetura geral do AVA, dominar a terminologia inicial de sistemas e configurar ferramentas fundamentais de desenvolvimento.',
                      prereqs: 'Nenhum.',
                      aulas: [
                        'Aula 1.1: Boas-vindas e Configuração de Perfil',
                        'Aula 1.2: Visão Geral da Tecnologia e Stack',
                        'Aula 1.3: Introdução ao Ambiente de Sandbox',
                        'Aula 1.4: Material de Leitura e Glossário Acadêmico',
                        'Aula 1.5: Quiz Rápido de Nivelamento'
                      ]
                    },
                    {
                      id: 'mod-2',
                      title: 'Módulo 2: Desenvolvimento de Frontend Moderno',
                      duration: '6 horas',
                      contentType: 'Atividades interativas com React, Vite e Tailwind CSS',
                      learningGoals: 'Criar interfaces ricas, reativas e com excelente contraste visual utilizando as melhores práticas do ecossistema React.',
                      prereqs: 'Lógica de programação básica.',
                      aulas: [
                        'Aula 2.1: Estruturando Componentes com React',
                        'Aula 2.2: Estilização Rápida com Tailwind Utility Classes',
                        'Aula 2.3: Estados e Ciclo de Vida do Componente',
                        'Aula 2.4: Construção de Formulários Reativos',
                        'Aula 2.5: Projeto Prático: Primeira Interface SPA'
                      ]
                    },
                    {
                      id: 'mod-3',
                      title: 'Módulo 3: APIs Robustas & Integrações Backend',
                      duration: '5 horas',
                      contentType: 'Aulas práticas guiadas, Exercícios de Live-Coding',
                      learningGoals: 'Projetar e construir APIs RESTful seguras e eficientes, preparadas para conexão fluida com qualquer frontend.',
                      prereqs: 'Conhecimento básico de JS/TS e redes.',
                      aulas: [
                        'Aula 3.1: Servidores Web com Node.js e Express',
                        'Aula 3.2: Definição de Rotas e Verbos HTTP',
                        'Aula 3.3: Middleware de Autenticação e Segurança',
                        'Aula 3.4: Conexão e Comunicação entre Client e Server',
                        'Aula 3.5: Quiz de Validação Backend'
                      ]
                    },
                    {
                      id: 'mod-4',
                      title: 'Módulo 4: Consolidação & Projeto Final Integrador',
                      duration: '4 horas',
                      contentType: 'Mentoria individual gravada e Quiz de encerramento do curso',
                      learningGoals: 'Unificar frontend e backend em um ecossistema produtivo e homologar o portfólio prático.',
                      prereqs: 'Módulos 1, 2 e 3 concluídos.',
                      aulas: [
                        'Aula 4.1: Organizando Arquivos e Boas Práticas',
                        'Aula 4.2: Testes de Integração Ponta a Ponta',
                        'Aula 4.3: Preparação do Ambiente de Produção',
                        'Aula 4.4: Envio de Atividade Avaliativa Final',
                        'Aula 4.5: Liberação Automática do Certificado Oficial'
                      ]
                    }
                  ].map((module, mIdx) => (
                    <div key={module.id} className="border border-slate-200 rounded-xl bg-white p-4.5 space-y-3 shadow-3xs">
                      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                        <h4 className="text-xs font-black text-[#540D6E] uppercase tracking-wide">{module.title}</h4>
                        <span className="text-[9px] font-mono font-bold bg-slate-100 text-slate-600 px-2 py-0.5 rounded border border-slate-150">
                          ⏱️ {module.duration}
                        </span>
                      </div>
                      
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-[10.5px]">
                        <div className="space-y-1">
                          <span className="text-[8.5px] font-black text-slate-400 uppercase tracking-widest block font-bold">Aulas do Módulo</span>
                          <ul className="list-disc pl-4 space-y-0.5 text-slate-600 font-semibold">
                            {module.aulas.map((aula, aIdx) => (
                              <li key={`${module.id}-aula-${aIdx}`}>{aula}</li>
                            ))}
                          </ul>
                        </div>
                        <div className="space-y-2 text-left">
                          <div>
                            <span className="text-[8.5px] font-black text-slate-400 uppercase tracking-widest block font-bold">Objetivo de Aprendizagem</span>
                            <p className="text-slate-600 font-semibold mt-0.5">{module.learningGoals}</p>
                          </div>
                          <div>
                            <span className="text-[8.5px] font-black text-slate-400 uppercase tracking-widest block font-bold">Tipo de Conteúdo</span>
                            <p className="text-slate-500 font-semibold mt-0.5">{module.contentType}</p>
                          </div>
                          <div>
                            <span className="text-[8.5px] font-black text-slate-400 uppercase tracking-widest block font-bold">Pré-requisitos</span>
                            <p className="text-slate-500 font-semibold mt-0.5">{module.prereqs}</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
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
                        "Para emissão do certificado, é necessário manter frequência mínima de 70%.",
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
                        const freshCourse = courses.find(c => c.id === viewingCatalogCourse!.id);
                        setSelectedCourse(freshCourse || viewingCatalogCourse);
                        setViewingCatalogCourse(null);
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
      {activeQuizTaking && (
        <>
          {/* Backdrop with elegant blur */}
          <div 
            className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-[100] transition-opacity animate-in fade-in duration-300"
            onClick={() => {
              if (hasSubmitted) {
                setActiveQuizTaking(null);
              } else {
                showConfirm('Deseja mesmo sair do teste? Suas respostas atuais não serão gravadas.', () => {
                  setActiveQuizTaking(null);
                });
              }
            }}
          />
          
          {/* Modal Container */}
          <div className="fixed inset-0 z-[101] flex items-center justify-center p-4">
            <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[85vh] shadow-2xl flex flex-col overflow-hidden animate-in zoom-in-95 duration-200 text-left">
              {/* Header */}
              <div className="p-5 md:p-6 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/50">
                <div className="flex items-center gap-3">
                  <div className="p-2.5 bg-amber-50 rounded-xl text-amber-700 border border-amber-100">
                    <CheckSquare className="h-5 w-5" />
                  </div>
                  <div>
                    <span className="text-[9px] font-black uppercase tracking-wider text-amber-800 bg-amber-100 px-2.5 py-1 rounded inline-block font-mono border border-amber-200/20">
                      Avaliação e Fixação
                    </span>
                    <h3 className="text-sm md:text-base font-black text-slate-900 mt-1">{activeQuizTaking.title}</h3>
                  </div>
                </div>
                <button
                  onClick={() => {
                    if (hasSubmitted) {
                      setActiveQuizTaking(null);
                    } else {
                      showConfirm('Deseja mesmo sair do teste? Suas respostas atuais não serão gravadas.', () => {
                        setActiveQuizTaking(null);
                      });
                    }
                  }}
                  className="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all cursor-pointer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Scrollable Body */}
              <div className="p-6 overflow-y-auto space-y-6 flex-1 no-scrollbar text-xs">
                {!hasSubmitted ? (
                  /* Single Question Flow */
                  <div className="space-y-6">
                    {/* Header info / progress bar */}
                    <div className="flex items-center justify-between text-[11px] font-semibold text-slate-500">
                      <span>Questão {currentQuestionIdx + 1} de {activeQuizTaking.questions.length}</span>
                      <span className="font-mono">{Math.round((currentQuestionIdx / activeQuizTaking.questions.length) * 100)}% concluído</span>
                    </div>
                    {/* Visual progress bar */}
                    <div className="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                      <div 
                        className="h-full bg-teal-500 transition-all duration-300"
                        style={{ width: `${(currentQuestionIdx / activeQuizTaking.questions.length) * 100}%` }}
                      />
                    </div>

                    {/* Question Card */}
                    {activeQuizTaking.questions[currentQuestionIdx] && (() => {
                      const q = activeQuizTaking.questions[currentQuestionIdx];
                      const selectedOpt = currentAnswers[q.id];
                      const isAnswered = answeredQuestions[q.id] === true;
                      const isCorrect = selectedOpt === q.correctOptionIndex;

                      return (
                        <div className="space-y-5">
                          {/* Question Text */}
                          <div className="p-5 rounded-xl border border-slate-150 bg-slate-50/50 space-y-3 shadow-3xs">
                            <span className="text-[10px] font-black uppercase text-teal-600 tracking-wider">Enunciado</span>
                            <h4 className="font-bold text-slate-800 text-sm leading-relaxed">
                              {q.questionText}
                            </h4>
                          </div>

                          {/* Options */}
                          <div className="space-y-2.5">
                            <span className="text-[10px] font-black uppercase text-slate-400 tracking-wider block mb-1">Alternativas</span>
                            <div className="grid grid-cols-1 gap-2.5">
                              {q.options.map((opt, optIdx) => {
                                const isSelected = selectedOpt === optIdx;
                                
                                // Dynamic classes for answers
                                let optionClasses = "border-slate-200 hover:border-teal-500 hover:bg-slate-50/50 text-slate-700 bg-white";
                                let circleClasses = "border-slate-300 text-slate-400 bg-white";
                                
                                if (isSelected && !isAnswered) {
                                  optionClasses = "border-teal-500 bg-teal-50/10 text-teal-950 font-bold shadow-2xs";
                                  circleClasses = "bg-teal-600 border-teal-600 text-white";
                                } else if (isAnswered) {
                                  // Question answered state coloring
                                  const isOptionCorrect = q.correctOptionIndex === optIdx;
                                  if (isOptionCorrect) {
                                    // Highlighting the correct one in soft green
                                    optionClasses = "border-emerald-500 bg-emerald-50/60 text-emerald-950 font-bold";
                                    circleClasses = "bg-emerald-600 border-emerald-600 text-white";
                                  } else if (isSelected) {
                                    // Selected but incorrect - highlight in soft amber/orange (not heavy red as requested)
                                    optionClasses = "border-amber-400 bg-amber-50/30 text-slate-700 font-bold";
                                    circleClasses = "bg-amber-500 border-amber-500 text-white";
                                  } else {
                                    optionClasses = "border-slate-100 text-slate-400 bg-slate-50/30 cursor-not-allowed";
                                    circleClasses = "border-slate-200 text-slate-300 bg-slate-50";
                                  }
                                }

                                return (
                                  <button
                                    type="button"
                                    key={`${q.id}-opt-${optIdx}`}
                                    disabled={isAnswered}
                                    onClick={() => {
                                      setCurrentAnswers(prev => ({
                                        ...prev,
                                        [q.id]: optIdx
                                      }));
                                    }}
                                    className={`w-full text-left p-3.5 rounded-xl text-xs transition-all border flex items-center gap-3 cursor-pointer ${optionClasses}`}
                                  >
                                    <span className={`w-5 h-5 rounded-full border text-[10px] font-bold flex items-center justify-center shrink-0 ${circleClasses}`}>
                                      {String.fromCharCode(65 + optIdx)}
                                    </span>
                                    <span className="flex-1 leading-snug">{opt}</span>
                                  </button>
                                );
                              })}
                            </div>
                          </div>

                          {/* Action Buttons & Feedback Block */}
                          {!isAnswered ? (
                            <div className="pt-2">
                              <button
                                type="button"
                                disabled={selectedOpt === undefined}
                                onClick={() => {
                                  setAnsweredQuestions(prev => ({
                                    ...prev,
                                    [q.id]: true
                                  }));
                                }}
                                className={`w-full py-3 px-5 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer ${
                                  selectedOpt === undefined
                                    ? "bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed"
                                    : "bg-teal-600 hover:bg-teal-500 text-white shadow-xs"
                                }`}
                              >
                                <span>Responder</span>
                                <ArrowRight className="h-4 w-4" />
                              </button>
                            </div>
                          ) : (
                            /* Feedback Block after answering */
                            <div className="space-y-4 animate-in fade-in slide-in-from-bottom-2 duration-300">
                              <div className={`p-5 rounded-xl border leading-relaxed space-y-3 ${
                                isCorrect
                                  ? "bg-emerald-50 border-emerald-200 text-emerald-950"
                                  : "bg-amber-50/50 border-amber-200 text-amber-950"
                              }`}>
                                <div className="flex items-center gap-2">
                                  <span className="text-lg">
                                    {isCorrect ? "🎉" : "💡"}
                                  </span>
                                  <strong className="font-extrabold text-xs">
                                    {isCorrect ? "Resposta correta" : "Ainda não foi desta vez"}
                                  </strong>
                                </div>
                                
                                <p className="font-medium text-[11px] leading-relaxed">
                                  {isCorrect 
                                    ? "Muito bem! Você compreendeu este conceito."
                                    : "Resposta incorreta, revise o conteúdo indicado."
                                  }
                                </p>

                                {/* Additional diagnostic properties */}
                                <div className="border-t border-slate-200/30 pt-3 mt-1 space-y-2 text-[11px]">
                                  <div>
                                    <span className="font-bold block text-slate-500 uppercase text-[9px] tracking-wider">Gabarito da Questão</span>
                                    <p className="font-semibold text-slate-800 mt-0.5">
                                      A alternativa correta é <span className="font-extrabold text-teal-700">{String.fromCharCode(65 + q.correctOptionIndex)}</span>. {q.explanation || 'Nenhuma explicação adicional fornecida.'}
                                    </p>
                                  </div>

                                  {(q.reviewMessage || q.recommendedModule) && (
                                    <div className="bg-white/40 p-2.5 rounded-lg border border-slate-200/10 mt-2">
                                      <span className="font-bold block text-slate-500 uppercase text-[9px] tracking-wider font-mono">Indicação de Estudo</span>
                                      {q.reviewMessage && (
                                        <p className="text-slate-700 italic mt-0.5">{q.reviewMessage}</p>
                                      )}
                                      {q.recommendedModule && (
                                        <p className="font-bold text-amber-700 mt-1">
                                          Revise: <span className="underline">{q.recommendedModule}</span>
                                        </p>
                                      )}
                                    </div>
                                  )}
                                </div>
                              </div>

                              {/* Navigation / Retry actions */}
                              <div className="flex flex-col sm:flex-row gap-2 pt-1">
                                {!isCorrect && q.allowRetry !== false && (
                                  <button
                                    type="button"
                                    onClick={() => {
                                      // Allow the user to retry this specific question
                                      setAnsweredQuestions(prev => ({
                                        ...prev,
                                        [q.id]: false
                                      }));
                                      setCurrentAnswers(prev => {
                                        const copy = { ...prev };
                                        delete copy[q.id];
                                        return copy;
                                      });
                                    }}
                                    className="flex-1 py-3 px-4 bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 rounded-xl text-xs font-bold border border-slate-200 transition-all cursor-pointer flex items-center justify-center gap-1.5"
                                  >
                                    Tentar novamente
                                  </button>
                                )}
                                
                                <button
                                  type="button"
                                  onClick={() => {
                                    if (currentQuestionIdx < activeQuizTaking.questions.length - 1) {
                                      setCurrentQuestionIdx(prev => prev + 1);
                                    } else {
                                      // End of quiz, submit now!
                                      let correctCount = 0;
                                      activeQuizTaking.questions.forEach((quest) => {
                                        if (currentAnswers[quest.id] === quest.correctOptionIndex) {
                                          correctCount++;
                                        }
                                      });
                                      const scorePercent = Math.round((correctCount / activeQuizTaking.questions.length) * 100);
                                      const passed = scorePercent >= 70;

                                      submitQuiz(selectedCourse.id, activeQuizTaking.id, scorePercent, passed);
                                      setQuizResult({ scorePercent, passed });
                                      setHasSubmitted(true);
                                    }
                                  }}
                                  className="flex-1 py-3 px-5 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 shadow-2xs"
                                >
                                  <span>
                                    {currentQuestionIdx < activeQuizTaking.questions.length - 1 ? "Próxima pergunta" : "Ver Resultado Final"}
                                  </span>
                                  <ArrowRight className="h-4 w-4" />
                                </button>
                              </div>
                            </div>
                          )}
                        </div>
                      );
                    })()}
                  </div>
                ) : (
                  /* Summary / Conclusion Display */
                  <div className="space-y-6 animate-in fade-in duration-300">
                    {(() => {
                      let correctCount = 0;
                      activeQuizTaking.questions.forEach((quest) => {
                        if (currentAnswers[quest.id] === quest.correctOptionIndex) {
                          correctCount++;
                        }
                      });
                      const totalQuestions = activeQuizTaking.questions.length;
                      const scorePercent = quizResult?.scorePercent ?? Math.round((correctCount / totalQuestions) * 100);
                      const passed = quizResult?.passed ?? (scorePercent >= 70);

                      // Filter incorrect questions with revision info to offer customized recommendations
                      const incorrectQuestions = activeQuizTaking.questions.filter(
                        quest => currentAnswers[quest.id] !== quest.correctOptionIndex
                      );

                      return (
                        <div className="space-y-6">
                          {/* Result status block */}
                          <div className={`p-6 rounded-xl border text-center space-y-3 relative overflow-hidden ${
                            passed
                              ? 'bg-emerald-50 border-emerald-200 text-emerald-950'
                              : 'bg-amber-50/50 border-amber-200 text-amber-950'
                          }`}>
                            <span className="text-4xl block">
                              {passed ? '🎉' : '📚'}
                            </span>
                            <h4 className="font-extrabold text-sm uppercase tracking-wide">
                              {passed ? 'Aprovado com Sucesso!' : 'Atividade Concluída — Revisão Recomendada'}
                            </h4>
                            
                            {/* Score info badge */}
                            <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-white/60 rounded-full border border-slate-200/20 text-xs font-black">
                              <span>Acertos: <span className="text-teal-600 font-bold">{correctCount}</span> de {totalQuestions}</span>
                              <span className="text-slate-350">•</span>
                              <span>Rendimento: <span className="text-teal-600 font-bold">{scorePercent}%</span></span>
                            </div>

                            <p className="text-[11px] leading-relaxed max-w-md mx-auto font-medium text-slate-700">
                              {passed 
                                ? `Parabéns! Você compreendeu plenamente a matéria e obteve excelente rendimento de ${scorePercent}% de aproveitamento. Continue assim!` 
                                : `Você obteve ${scorePercent}% de aproveitamento neste teste. A média recomendada para consolidação do conteúdo é de no mínimo 70%. Veja abaixo os módulos recomendados para revisão.`}
                            </p>
                          </div>

                          {/* Suggested revision topics list */}
                          {incorrectQuestions.length > 0 && (
                            <div className="p-4 rounded-xl border border-amber-200/50 bg-amber-50/10 space-y-3">
                              <h5 className="font-bold text-amber-800 text-[10px] uppercase tracking-wider flex items-center gap-1.5">
                                <Info className="h-4 w-4" />
                                <span>Tópicos recomendados para revisão:</span>
                              </h5>
                              <div className="space-y-2.5">
                                {incorrectQuestions.map((quest, idx) => (
                                  <div key={quest.id} className="p-3 bg-white rounded-lg border border-slate-200 text-[11px] space-y-1 text-left">
                                    <span className="font-bold text-slate-800 block">
                                      Questão {activeQuizTaking.questions.indexOf(quest) + 1}: {quest.questionText}
                                    </span>
                                    {quest.recommendedModule && (
                                      <div className="flex items-center gap-1.5 mt-1.5 text-xs">
                                        <span className="px-2 py-0.5 bg-amber-100 text-amber-800 rounded font-bold text-[9px] uppercase tracking-wider font-mono">
                                          Módulo Recomendado
                                        </span>
                                        <strong className="text-amber-750 font-semibold">{quest.recommendedModule}</strong>
                                      </div>
                                    )}
                                  </div>
                                ))}
                              </div>
                            </div>
                          )}

                          {/* Message of successful completion */}
                          {passed && incorrectQuestions.length === 0 && (
                            <div className="p-4 rounded-xl border border-emerald-100 bg-emerald-50/20 text-center text-[11px] text-slate-650">
                              🌟 Você acertou todas as questões! Excelente desempenho teórico.
                            </div>
                          )}
                        </div>
                      );
                    })()}
                  </div>
                )}
              </div>

              {/* Sticky Footer */}
              <div className="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5 shrink-0">
                {!hasSubmitted ? (
                  <>
                    <button
                      type="button"
                      onClick={() => {
                        showConfirm('Deseja mesmo sair do teste? Suas respostas atuais não serão salvas.', () => {
                          setActiveQuizTaking(null);
                        });
                      }}
                      className="px-6 py-3 bg-white hover:bg-slate-100 text-slate-500 hover:text-slate-800 rounded-xl text-xs font-bold border border-slate-200 transition-all cursor-pointer"
                    >
                      Sair do Teste
                    </button>
                  </>
                ) : (
                  <>
                    {!quizResult?.passed && (
                      <button
                        onClick={() => {
                          setCurrentAnswers({});
                          setAnsweredQuestions({});
                          setCurrentQuestionIdx(0);
                          setQuizResult(null);
                          setHasSubmitted(false);
                        }}
                        className="px-6 py-3 rounded-xl text-xs font-black uppercase tracking-wider transition-all bg-amber-600 hover:bg-amber-500 text-white shadow-md cursor-pointer"
                      >
                        Tentar Novamente
                      </button>
                    )}
                    <button
                      onClick={() => {
                        setActiveQuizTaking(null);
                      }}
                      className="px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all cursor-pointer"
                    >
                      Concluir e Fechar
                    </button>
                  </>
                )}
              </div>
            </div>
          </div>
        </>
      )}

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
