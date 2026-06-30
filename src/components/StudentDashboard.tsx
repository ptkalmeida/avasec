/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import {
  BookOpen, Calendar, CheckCircle, Award, Video, Clock, ChevronRight,
  TrendingUp, FileCheck, ArrowRight, ArrowLeft, User, Settings, Sparkles, BookMarked, Monitor, Linkedin, Download, Globe, PlayCircle,
  Lock, MessageSquare, Send, ChevronDown, Check, Play, FileText, Notebook, Layers, HelpCircle, CheckSquare, ExternalLink, Archive, Library, Info,
  Bell, Shield, Smartphone
} from 'lucide-react';
import { useLMS } from '../context/LMSContext';
import { Course, Lesson, LiveSession, Certificate } from '../types';
import { CertificateTemplate } from './CertificateTemplate';
import { LiveClassroom } from './LiveClassroom';
import { CourseForum } from './CourseForum';

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
    updateUserName,
    toggleLessonCompletion,
    calculateAttendancePercent,
    sendDirectMessage,
    quizzes,
    quizSubmissions,
    submitQuiz,
    academicRequests,
    addAcademicRequest,
    systemSettings,
    libraryItems,
    webinarEvents,
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
    getYouTubeEmbedUrl,
    studentEnrollments,
    enrollStudentInCourse,
    dropStudentFromCourse,
    completeStudentCourse,
    clearStudentPenalty,
  } = useLMS();

  const enrollmentRecord = studentEnrollments[activeUser.name] || { enrolledCourseId: null, completedCourseIds: [], dropOutPenaltyUntil: null };

  const handleBack = () => {
    if (activeLesson) {
      setActiveLesson(null);
    } else if (activeQuizTaking) {
      setActiveQuizTaking(null);
    } else if (selectedCourse) {
      setSelectedCourse(null);
    } else if (onBackToLanding) {
      onBackToLanding();
    }
  };

  const getBackLabel = () => {
    if (activeLesson) return "Voltar ao Curso";
    if (activeQuizTaking) return "Voltar ao Curso";
    if (selectedCourse) return "Voltar p/ Meus Cursos";
    return "Sair p/ Portal";
  };

  // Active state selections
  const [selectedCourse, setSelectedCourse] = useState<Course | null>(null);
  const [activeLesson, setActiveLesson] = useState<Lesson | null>(null);
  const [activeLiveSession, setActiveLiveSession] = useState<LiveSession | null>(null);
  const [selectedCertificate, setSelectedCertificate] = useState<Certificate | null>(null);
  
  // Interactive Quiz Taking States
  const [activeQuizTaking, setActiveQuizTaking] = useState<{ id: string; courseId: string; title: string; questions: { id: string; questionText: string; options: string[]; correctOptionIndex: number }[] } | null>(null);
  const [currentAnswers, setCurrentAnswers] = useState<{[key: string]: number}>({});
  const [quizResult, setQuizResult] = useState<{ scorePercent: number; passed: boolean } | null>(null);
  const [hasSubmitted, setHasSubmitted] = useState(false);

  // Custom states
  const [searchQuery, setSearchQuery] = useState('');
  const [sortType, setSortType] = useState<'alphabetical-asc' | 'alphabetical-desc' | 'recent'>('recent');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [viewingCatalogCourse, setViewingCatalogCourse] = useState<Course | null>(null);
  const [simulatedDaysForCancel, setSimulatedDaysForCancel] = useState<number>(3);
  const [lockedCourseWarning, setLockedCourseWarning] = useState<string | null>(null);
  const [showUpcomingCalendar, setShowUpcomingCalendar] = useState(false);
  const [showKnowledgeBase, setShowKnowledgeBase] = useState(false);
  const [notifications, setNotifications] = useState({ email: true, push: true, sms: false });
  const [twoFactor, setTwoFactor] = useState(false);
  const [language, setLanguage] = useState('Português (BR)');

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
  const [activeTab, setActiveTab] = useState<'teoria' | 'anotacao' | 'suporte' | 'forum'>('teoria');
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
      setIsPlaying(false); // Reset player state on class switch
    }
  }, [activeLesson, savedNotes]);

  const handleSaveNoteText = () => {
    if (activeLesson) {
      const updated = { ...savedNotes, [activeLesson.id]: lessonNoteText };
      setSavedNotes(updated);
      localStorage.setItem('ava_student_lesson_notes', JSON.stringify(updated));
    }
  };

  // Video Simulated States
  const [isPlaying, setIsPlaying] = useState(false);
  const [playbackSpeed, setPlaybackSpeed] = useState('1.0x');
  const [videoTime, setVideoTime] = useState(0);
  const [videoDuration, setVideoDuration] = useState(0);
  const videoRef = React.useRef<HTMLVideoElement>(null);

  // Effect to sync video playback speed
  useEffect(() => {
    if (videoRef.current) {
      const rate = parseFloat(playbackSpeed.replace('x', ''));
      videoRef.current.playbackRate = rate;
    }
  }, [playbackSpeed, isPlaying]);

  // Calculations for summary metrics
  const activeEnrollments = courses.length;
  
  // Average Global Attendance
  const totalAttendanceSum = courses.reduce((acc, c) => acc + calculateAttendancePercent(c.id), 0);
  const avgGlobalAttendance = courses.length > 0 ? Math.round(totalAttendanceSum / courses.length) : 0;
  
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
      <div className="mb-8 flex flex-col justify-between gap-6 rounded-2xl bg-gradient-to-r from-purple-50/70 via-white to-teal-50/40 border border-purple-100/60 p-6 shadow-sm md:flex-row md:items-center relative overflow-hidden">
        {/* Ambient subtle light glows */}
        <div className="absolute top-0 right-0 -mt-10 -mr-10 w-48 h-48 bg-[#540D6E]/5 rounded-full blur-3xl pointer-events-none" />
        <div className="absolute bottom-0 left-1/4 -mb-10 w-44 h-44 bg-teal-400/5 rounded-full blur-3xl pointer-events-none" />
        
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-6 relative z-10">
          {onBackToLanding && (
            <button
               onClick={() => {
                 const label = getBackLabel();
                 speakText(`${label}. Voltando um nível no fluxo.`);
                 handleBack();
               }}
              className="group flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 hover:text-slate-950 transition-all cursor-pointer shadow-2xs"
              title={getBackLabel()}
            >
              <ArrowLeft className="h-4 w-4 group-hover:-translate-x-0.5 transition-transform text-[#540D6E]" />
              <span className="text-[10px] font-black uppercase tracking-wider">{getBackLabel()}</span>
            </button>
          )}
          
          <div className="flex items-center gap-4 text-left">
            <div className="rounded-full bg-teal-50 p-3.5 border border-teal-200 shadow-3xs">
              <User className="h-8 w-8 text-teal-605" />
            </div>
            <div>
              <span className="text-[10px] uppercase font-black tracking-wider text-teal-800 bg-teal-100/50 border border-teal-200/80 px-2.5 py-1 rounded-full inline-flex items-center gap-1.5 shadow-3xs">
                <span className="inline-block w-1.5 h-1.5 bg-teal-500 rounded-full animate-ping"></span>
                Painel de Estudos AVASEC
              </span>
              
              <h2 className="text-xl md:text-2xl font-black text-slate-900 flex items-center gap-3 mt-1.5">
                <span>Olá, {activeUser.name}</span>
              </h2>
              <p className="text-xs text-slate-550 font-medium">Pronto para acelerar os seus conhecimentos profissionais hoje?</p>
            </div>
          </div>
        </div>

        {/* Dynamic global analytics wheels summary */}
        <div className="grid grid-cols-3 gap-6 md:gap-10 relative z-10">
          <div className="text-center text-left">
            <span className="block text-2xl font-black text-slate-900 font-mono tracking-tight">{activeEnrollments}</span>
            <span className="text-[9px] uppercase text-slate-500 font-black tracking-wider block mt-0.5">Cursos</span>
          </div>
          <div className="text-center border-l border-slate-200 pl-6 text-left">
            <span className="block text-2xl font-black text-teal-600 font-mono tracking-tight">{avgGlobalAttendance}%</span>
            <span className="text-[9px] uppercase text-slate-500 font-black tracking-wider block mt-0.5">Presença</span>
          </div>
          <div className="text-center border-l border-slate-200 pl-6 text-left">
            <span className="block text-2xl font-black text-amber-600 font-mono tracking-tight">{totalCertificatesCount}</span>
            <span className="text-[9px] uppercase text-slate-500 font-black tracking-wider block mt-0.5">Diploma</span>
          </div>
        </div>
      </div>

      {/* Dynamic Tab Navigation System */}
      {systemSettings.allowDirectMessages && (
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
        </div>
      )}

      {activeDashboardTab === 'general' ? (
        /* Main split: left courses or detail / right certificates tracking */
        <div className="grid grid-cols-1 gap-8 lg:grid-cols-1">
        
        {/* Course Directory Columns */}
        <div className="space-y-6">
          
          {selectedCourse ? (
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
                  {/* Cancel enrollment control */}
                  <div className="flex flex-wrap items-center gap-2 bg-slate-50 border border-slate-205 rounded-xl px-3 py-1.5 text-xs">
                    <span className="text-[10px] text-slate-500 font-bold">Simular Dias Matriculado:</span>
                    <select
                      value={simulatedDaysForCancel}
                      onChange={(e) => setSimulatedDaysForCancel(Number(e.target.value))}
                      className="bg-white border border-slate-300 rounded font-bold font-mono text-[11px] text-slate-700 px-1.5 py-0.5 outline-hidden"
                    >
                      {[1, 2, 3, 4, 5, 6, 7, 10, 15, 30].map(d => (
                        <option key={d} value={d}>{d} dias {d <= 5 ? '(sem inadimplência ✓)' : '(com inadimplência ⚠️)'}</option>
                      ))}
                    </select>
                    
                    <button
                      onClick={() => {
                        const hasPenalty = dropStudentFromCourse(activeUser.name, selectedCourse.id, simulatedDaysForCancel);
                        if (hasPenalty) {
                          speakText("Matrícula cancelada. Você desistiu deste curso após o limite de 5 dias letivos. Seu acesso agora está sob regime de Inadimplência temporária.");
                        } else {
                          speakText("Matrícula desfeita com sucesso. Saída realizada dentro do prazo de tolerância escolar de 5 dias.");
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
                        (localStorage.getItem(`ava_presence_status_${selectedCourse.instructorName}`) || 'online') === 'online'
                          ? 'bg-emerald-500 animate-pulse'
                          : 'bg-slate-400'
                      }`} />
                      <span className={(localStorage.getItem(`ava_presence_status_${selectedCourse.instructorName}`) || 'online') === 'online' ? 'text-emerald-600' : 'text-slate-500'}>
                        {(localStorage.getItem(`ava_presence_status_${selectedCourse.instructorName}`) || 'online') === 'online' ? 'Online' : 'Offline'}
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

              {/* Attendance Indicator Warning or Success banner */}
              {calculateAttendancePercent(selectedCourse.id) < 70 ? (
                <div className="mb-6 rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900 flex items-start gap-2.5">
                  <Clock className="h-5 w-5 shrink-0 text-amber-600 mt-0.5 animate-pulse" />
                  <div>
                    <strong className="block font-bold mb-0.5">Módulo de Presença Pendente (&lt; 70%)</strong>
                    Complete more lessons or active live video sessions. Currently, you need at least <strong>70% total presence</strong> to be eligible for automated certification. Mark lesson cards as completed inside modules to increment your progress.
                  </div>
                </div>
              ) : (
                <div className="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-xs text-emerald-800 flex items-center justify-between gap-4">
                  <div className="flex items-center gap-2.5">
                    <CheckCircle className="h-5 w-5 text-emerald-600 shrink-0" />
                    <div>
                      <strong className="block font-bold mb-0.5">Parabéns! Frequência Qualificada para Certificação</strong>
                      Você atingiu {calculateAttendancePercent(selectedCourse.id)}% de presença! Seu diploma acadêmico digital foi emitido e está pronto no painel lateral.
                    </div>
                  </div>
                  <div className="flex flex-col sm:flex-row items-center gap-2">
                    <button
                      onClick={() => {
                        completeStudentCourse(activeUser.name, selectedCourse.id);
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

                    {certificates.find((cert) => cert.courseId === selectedCourse.id && cert.studentName === activeUser.name) && (
                      <button
                        onClick={() => {
                          const cert = certificates.find((c) => c.courseId === selectedCourse.id && c.studentName === activeUser.name);
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
                          {module.lessons.map((lesson) => {
                            const isDone = currentCourseProgress?.completedLessons.includes(lesson.id) || false;

                            return (
                              <div
                                key={lesson.id}
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
                <div className={`${(activeLesson || activeQuizTaking) ? "lg:col-span-12" : "lg:col-span-8"} space-y-5 animate-in fade-in duration-300`}>
                  
                  {activeQuizTaking ? (
                    /* Active Quiz Player Workspace */
                    <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs text-left space-y-5">
                      <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                          <span className="text-[10px] font-bold uppercase bg-amber-55 text-amber-800 bg-amber-50 px-2.5 py-0.5 rounded font-mono">
                            Avaliação e Fixação
                          </span>
                          <h3 className="text-base font-black text-slate-900 mt-1">{activeQuizTaking.title}</h3>
                        </div>
                        <button
                          onClick={() => {
                            setActiveQuizTaking(null);
                            if (selectedCourse.lessons.length > 0) {
                              setActiveLesson(selectedCourse.lessons[0]);
                            }
                          }}
                          className="text-xs font-semibold text-slate-500 hover:text-slate-800"
                        >
                          Voltar para Aulas
                        </button>
                      </div>

                      {!hasSubmitted ? (
                        <form onSubmit={(e) => {
                          e.preventDefault();
                          
                          // Count answered questions
                          const unanswered = activeQuizTaking.questions.filter(q => currentAnswers[q.id] === undefined);
                          if (unanswered.length > 0) {
                            alert('Por favor, responda todas as questões antes de enviar.');
                            return;
                          }

                          let correctCount = 0;
                          activeQuizTaking.questions.forEach((q) => {
                            if (currentAnswers[q.id] === q.correctOptionIndex) {
                              correctCount++;
                            }
                          });

                          const scorePercent = Math.round((correctCount / activeQuizTaking.questions.length) * 100);
                          const passed = scorePercent >= 70;

                          submitQuiz(activeQuizTaking.id, activeUser.name, scorePercent, passed);
                          setQuizResult({ scorePercent, passed });
                          setHasSubmitted(true);
                        }} className="space-y-6">
                          <p className="text-xs text-slate-500 leading-relaxed bg-slate-50 p-3 rounded-lg border border-slate-100">
                            <strong>Instruções de envio:</strong> Marque a alternativa que julgar correta em cada uma das perguntas abaixo. É necessário atingir o aproveitamento mínimo de <strong>70% de acertos</strong> para obter a aprovação na atividade e validar sua certificação.
                          </p>

                          <div className="space-y-5">
                            {activeQuizTaking.questions.map((q, qIdx) => (
                              <div key={q.id} className="p-4 rounded-xl border border-slate-150 space-y-3">
                                <strong className="font-bold text-xs text-slate-800 block">
                                  Questão {qIdx + 1}: {q.questionText}
                                </strong>

                                <div className="grid grid-cols-1 gap-2">
                                  {q.options.map((opt, optIdx) => {
                                    const isSelected = currentAnswers[q.id] === optIdx;
                                    return (
                                      <button
                                        type="button"
                                        key={optIdx}
                                        onClick={() => {
                                          setCurrentAnswers(prev => ({
                                            ...prev,
                                            [q.id]: optIdx
                                          }));
                                        }}
                                        className={`w-full text-left p-3 rounded-lg text-xs transition-all border flex items-center gap-2 cursor-pointer ${
                                          isSelected
                                            ? 'border-amber-500 bg-amber-50/20 text-slate-900 font-bold'
                                            : 'border-slate-200 hover:border-slate-300 text-slate-650 bg-white'
                                        }`}
                                      >
                                        <span className={`w-4 h-4 rounded-full border text-[9px] font-bold flex items-center justify-center shrink-0 ${
                                          isSelected
                                            ? 'bg-amber-600 border-amber-600 text-white'
                                            : 'border-slate-300 text-slate-400'
                                        }`}>
                                          {String.fromCharCode(65 + optIdx)}
                                        </span>
                                        <span>{opt}</span>
                                      </button>
                                    );
                                  })}
                                </div>
                              </div>
                            ))}
                          </div>

                          <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
                            <button
                              type="button"
                              onClick={() => {
                                setActiveQuizTaking(null);
                                if (selectedCourse.lessons.length > 0) {
                                  setActiveLesson(selectedCourse.lessons[0]);
                                }
                              }}
                              className="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200"
                            >
                              Sair do Teste
                            </button>
                            <button
                              type="submit"
                              className="rounded-lg bg-amber-600 hover:bg-amber-500 px-5 py-2 text-xs font-bold text-white shadow-xs"
                            >
                              Finalizar e Entregar Avaliação
                            </button>
                          </div>
                        </form>
                      ) : (
                        /* Results display */
                        <div className="space-y-6">
                          <div className={`p-5 rounded-xl border text-center space-y-2 relative overflow-hidden ${
                            quizResult?.passed
                              ? 'bg-emerald-50 border-emerald-200 text-emerald-900'
                              : 'bg-rose-50 border-rose-200 text-rose-900'
                          }`}>
                            <span className="text-3xl block">
                              {quizResult?.passed ? '🎉' : '✍️'}
                            </span>
                            <h4 className="font-extrabold text-md">
                              {quizResult?.passed ? 'Aprovado com Sucesso!' : 'Abaixo do Rendimento Esperado'}
                            </h4>
                            <p className="text-xs leading-relaxed max-w-md mx-auto">
                              {quizResult?.passed 
                                ? `Parabéns, você alcançou ${quizResult.scorePercent}% de aproveitamento. Sua nota foi registrada na folha de controle de presença e rendimento da disciplina.` 
                                : `Você obteve ${quizResult?.scorePercent}% de aproveitamento. A média necessária para aprovação é de no mínimo 70%. Estude as aulas teóricas e tente novamente.`}
                            </p>
                          </div>

                          <div className="space-y-4">
                            <h5 className="font-bold text-slate-800 text-xs uppercase tracking-wider">Detalhamento do seu Desempenho:</h5>
                            <div className="space-y-4">
                              {activeQuizTaking.questions.map((q, qIdx) => {
                                const studAns = currentAnswers[q.id];
                                const isCorrect = studAns === q.correctOptionIndex;
                                return (
                                  <div key={q.id} className="p-3.5 rounded-lg border border-slate-150 bg-slate-50/50 text-xs text-left">
                                    <span className="font-bold text-slate-800 block mb-2 leading-snug">Q{qIdx + 1}: {q.questionText}</span>
                                    
                                    <div className="space-y-1.5">
                                      {q.options.map((opt, oIdx) => {
                                        const isStudChoice = studAns === oIdx;
                                        const isCorrectAns = q.correctOptionIndex === oIdx;
                                        
                                        let optionStyle = 'border-slate-150 text-slate-600 bg-white';
                                        if (isStudChoice && isCorrect) {
                                          optionStyle = 'border-emerald-500 bg-emerald-50/50 text-emerald-900 font-bold';
                                        } else if (isStudChoice && !isCorrect) {
                                          optionStyle = 'border-rose-500 bg-rose-50/50 text-rose-900 font-bold';
                                        } else if (isCorrectAns) {
                                          optionStyle = 'border-emerald-500 bg-emerald-50/10 text-emerald-800 font-semibold';
                                        }

                                        return (
                                          <div key={oIdx} className={`p-2 rounded border text-[11px] flex items-center gap-2 ${optionStyle}`}>
                                            <span className="text-[10px] uppercase font-bold text-slate-400 shrink-0">
                                              {String.fromCharCode(65 + oIdx)})
                                            </span>
                                            <span className="flex-1">{opt}</span>
                                            {isStudChoice && (
                                              <span className="text-[9px] uppercase tracking-wider font-extrabold px-1 rounded shrink-0">
                                                {isCorrect ? '✓ Correto' : '✕ Errado'}
                                              </span>
                                            )}
                                          </div>
                                        );
                                      })}
                                    </div>
                                  </div>
                                );
                              })}
                            </div>
                          </div>

                          <div className="flex justify-end gap-2 pt-4 border-t border-slate-150">
                            {!quizResult?.passed && (
                              <button
                                onClick={() => {
                                  setCurrentAnswers({});
                                  setQuizResult(null);
                                  setHasSubmitted(false);
                                }}
                                className="rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold px-4 py-2 text-xs shadow-xs"
                              >
                                Tentar Novamente
                              </button>
                            )}
                            <button
                              onClick={() => {
                                setActiveQuizTaking(null);
                                if (selectedCourse.lessons.length > 0) {
                                  setActiveLesson(selectedCourse.lessons[0]);
                                }
                              }}
                              className="rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-semibold px-4 py-2 text-xs"
                            >
                              Finalizar Visualização de Nota
                            </button>
                          </div>
                        </div>
                      )}
                    </div>
                  ) : activeLesson ? (
                    /* Lesson Player Station active */
                    <div className="space-y-5 flex flex-col items-center">
                      
                      <div className="flex items-center justify-between w-full mb-2">
                        <span className="text-[10px] font-black uppercase tracking-widest text-teal-600 bg-teal-50 px-2.5 py-1 rounded inline-block font-mono border border-teal-200">
                          Aula em Foco
                        </span>
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
                        <div className="aspect-video w-full max-h-[50vh] flex flex-col justify-between p-4 relative">
                          
                          {/* Real Video Player implementation for testing */}
                          <div className="absolute inset-0 bg-slate-900 border border-slate-850 overflow-hidden group">
                           {getYouTubeEmbedUrl(activeLesson.videoUrl || '') ? (
                             <iframe 
                               className="w-full h-full"
                               src={getYouTubeEmbedUrl(activeLesson.videoUrl || '')!}
                               title="YouTube video player"
                               frameBorder="0"
                               allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                               allowFullScreen
                             ></iframe>
                           ) : isPlaying ? (
                             <video 
                               ref={videoRef}
                               key={activeLesson.id}
                               autoPlay 
                               playsInline
                               className="w-full h-full object-contain bg-black"
                               src={activeLesson.videoUrl || "https://vjs.zencdn.net/v/oceans.mp4"}
                               onEnded={() => setIsPlaying(false)}
                               onTimeUpdate={(e) => setVideoTime(e.currentTarget.currentTime)}
                               onLoadedMetadata={(e) => {
                                 setVideoDuration(e.currentTarget.duration);
                                 const rate = parseFloat(playbackSpeed.replace('x', ''));
                                 e.currentTarget.playbackRate = rate;
                               }}
                             />
                           ) : (
                             <div className="absolute inset-0 bg-slate-900/80 flex items-center justify-center z-5">
                               <button
                                 onClick={() => setIsPlaying(true)}
                                 className="rounded-full bg-white/10 hover:bg-white/15 text-white p-5 border border-white/20 transition-all scale-100 hover:scale-110 shadow-lg flex items-center justify-center cursor-pointer"
                               >
                                 <Play className="h-10 w-10 fill-white translate-x-0.5" />
                               </button>
                             </div>
                           )}
                          </div>

                          {/* Top video player hud bar */}
                          <div className="z-10 flex items-center justify-between text-white/90">
                            <span className="text-[10px] font-bold uppercase bg-teal-600 px-2 py-0.5 rounded font-mono">
                              AULA {activeLesson.order}
                            </span>
                            <div className="flex items-center gap-3">
                              <span className="text-[11px] font-medium font-mono bg-slate-900/80 px-2 py-0.5 rounded backdrop-blur-xs">
                                {playbackSpeed} de Velocidade
                              </span>
                              <button 
                                onClick={() => {
                                  if (videoRef.current) {
                                    if (videoRef.current.requestFullscreen) videoRef.current.requestFullscreen();
                                  }
                                }}
                                className="bg-white/10 hover:bg-white/20 p-1.5 rounded-lg transition-colors cursor-pointer"
                                title="Tela Cheia"
                              >
                                <Monitor className="h-3.5 w-3.5 text-white" />
                              </button>
                            </div>
                          </div>

                          {/* Bottom video interface bar */}
                          <div className="z-10 mt-auto flex flex-col gap-2">
                             {/* Custom progress bar */}
                             <div 
                               className="w-full h-1 bg-white/20 rounded-full overflow-hidden cursor-pointer group/progress"
                               onClick={(e) => {
                                 if (videoRef.current && videoDuration) {
                                   const rect = e.currentTarget.getBoundingClientRect();
                                   const pos = (e.clientX - rect.left) / rect.width;
                                   videoRef.current.currentTime = pos * videoDuration;
                                 }
                               }}
                             >
                               <div 
                                 className="h-full bg-teal-500 transition-all"
                                 style={{ width: `${(videoTime / videoDuration) * 100 || 0}%` }}
                               />
                             </div>

                             <div className="bg-slate-900/95 p-3 rounded-xl border border-white/10 backdrop-blur-xs flex items-center justify-between gap-4">
                              <div className="flex items-center gap-3">
                                <button
                                  onClick={() => setIsPlaying(!isPlaying)}
                                  className="bg-teal-600 hover:bg-teal-500 text-white rounded-lg p-1.5 transition-colors cursor-pointer"
                                >
                                  {isPlaying ? (
                                    <span className="font-mono font-black text-[10px] uppercase px-1">Pausar</span>
                                  ) : (
                                    <Play className="h-3.5 w-3.5 fill-white" />
                                  )}
                                </button>
                                
                                <div className="text-left leading-none">
                                  <span className="block text-[11px] font-bold text-white leading-tight">
                                    {activeLesson.title}
                                  </span>
                                  <span className="text-[9px] text-slate-400 font-mono">Duração: {activeLesson.duration}</span>
                                </div>
                              </div>

                              {/* Speed selector controls */}
                              <div className="flex items-center gap-1.5">
                                {['1.0x', '1.5x', '2.0x'].map((speed) => (
                                  <button
                                    key={speed}
                                    onClick={() => setPlaybackSpeed(speed)}
                                    className={`rounded px-1.8 py-0.8 text-[10px] font-black transition-colors cursor-pointer ${
                                      playbackSpeed === speed 
                                        ? 'bg-teal-600 text-white' 
                                        : 'bg-slate-800 text-slate-400 hover:bg-slate-700'
                                    }`}
                                  >
                                    {speed}
                                  </button>
                                ))}
                              </div>
                            </div>
                          </div>
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
                                    {activeLesson.documents.map((doc) => {
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
                                          key={doc.id}
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
                                Tem dúvidas sobre as regras de arquitetura abordadas ou sobre um bug estrito na aula? Envie seu questionamento diretamente ao instrutor pelo painel de comunicação na coluna da direita! O Gestor de Cursos responderá em sua conta no portal de canais em instantes!
                              </p>

                              <div className="bg-slate-50 border border-slate-150 rounded-xl p-3 flex items-start gap-3 mt-2">
                                <div className="relative">
                                  <User className="h-8 w-8 text-slate-400 p-1 bg-slate-200 rounded-full" />
                                  <span className={`absolute -bottom-0.5 -right-0.5 block h-2.5 w-2.5 rounded-full border border-white ${
                                    (localStorage.getItem(`ava_presence_status_${selectedCourse.instructorName}`) || 'online') === 'online'
                                      ? 'bg-emerald-500 animate-pulse'
                                      : 'bg-slate-400'
                                  }`} />
                                </div>
                                <div className="space-y-1">
                                  <strong className="text-slate-900 block font-bold leading-tight flex items-center gap-1.5">
                                    <span>Prof. {selectedCourse.instructorName}</span>
                                    <span className={`text-[9px] font-black leading-none ${
                                      (localStorage.getItem(`ava_presence_status_${selectedCourse.instructorName}`) || 'online') === 'online'
                                        ? 'text-emerald-600 bg-emerald-50 px-1 py-0.5 rounded border border-emerald-500/10'
                                        : 'text-slate-505 text-slate-500 bg-slate-100 px-1 py-0.5 rounded border border-slate-200'
                                    }`}>
                                      {(localStorage.getItem(`ava_presence_status_${selectedCourse.instructorName}`) || 'online') === 'online' ? 'ONLINE' : 'OFFLINE'}
                                    </span>
                                  </strong>
                                  <span className="text-[10px] text-slate-450 block">Tempo de resposta esperado: &lt; 2 horas</span>
                                </div>
                              </div>
                            </div>
                          )}

                          {activeTab === 'forum' && (
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

                  {/* Fórum de Discussão do Curso */}
                  {!activeLesson && !activeQuizTaking && (
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
                      {selectedCourse.liveSessions.map((session) => {
                        const isAttended = currentCourseProgress?.attendedLiveSessions.includes(session.id) || false;
                        return (
                          <div key={session.id} className="bg-white rounded-lg border border-teal-100/40 p-2.5 leading-relaxed text-left text-[11px]">
                            
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

                            {session.isLive ? (
                              <button
                                onClick={() => setActiveLiveSession(session)}
                                className="w-full bg-red-600 hover:bg-red-500 text-white font-bold py-1.5 rounded-md text-[10px] transition-colors flex items-center justify-center gap-1 mt-2 cursor-pointer"
                              >
                                <Video className="h-3 w-3 fill-white" />
                                <span>Participar Encontro</span>
                              </button>
                            ) : (
                              <div className="mt-1.5 pt-1.5 border-t border-slate-100 text-[9px] text-slate-400 leading-relaxed">
                                Link de transmissão ativo na hora marcada.
                              </div>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  {/* 4. Interactive Quizzes / Tests Block */}
                  <div className="border border-amber-100 bg-amber-50/10 rounded-xl p-3 text-left space-y-2.5">
                    <h5 className="font-bold text-slate-900 text-[10px] uppercase tracking-wider flex items-center gap-1.5">
                      <CheckSquare className="h-3.5 w-3.5 text-amber-600" />
                      <span>Testes e Avaliações</span>
                    </h5>

                    <div className="space-y-2">
                      {quizzes.filter(q => q.courseId === selectedCourse.id).length === 0 ? (
                        <div className="bg-white rounded-lg border border-slate-200 p-4 text-center text-[10px] text-slate-400">
                          Nenhum teste elaborado para este curso no momento.
                        </div>
                      ) : (
                        quizzes.filter(q => q.courseId === selectedCourse.id).map((quiz) => {
                          const userSub = quizSubmissions.find(s => s.quizId === quiz.id && s.studentName === activeUser.name);
                          return (
                            <div key={quiz.id} className="bg-white rounded-lg border border-slate-150 p-3 leading-relaxed text-left text-[11px] space-y-2">
                              <div className="flex items-start justify-between gap-1">
                                <div>
                                  <strong className="font-bold text-slate-900 block">{quiz.title}</strong>
                                  <span className="text-[9px] text-slate-400 block mt-0.5">{quiz.questions.length} questões</span>
                                </div>
                                {userSub && (
                                  <span className={`text-[8px] font-bold px-1.5 py-0.5 rounded uppercase shrink-0 ${
                                    userSub.passed ? 'bg-emerald-50 text-emerald-700 font-extrabold' : 'bg-amber-50 text-amber-700'
                                  }`}>
                                    {userSub.passed ? `Nota: ${userSub.scorePercent}%` : `${userSub.scorePercent}%`}
                                  </span>
                                )}
                              </div>

                              {userSub ? (
                                <div className="space-y-1.5">
                                  <div className="text-[9px] font-semibold text-slate-500">
                                    Último envio: {userSub.submittedAt}
                                  </div>
                                  <button
                                    onClick={() => {
                                      setCurrentAnswers({});
                                      setQuizResult(null);
                                      setHasSubmitted(false);
                                      setActiveQuizTaking(quiz);
                                      // Clear lesson focus
                                      setActiveLesson(null);
                                      setActiveLiveSession(null);
                                    }}
                                    className="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-1 rounded text-[10px] transition-colors flex items-center justify-center cursor-pointer"
                                  >
                                    Refazer Avaliação
                                  </button>
                                </div>
                              ) : (
                                <button
                                  onClick={() => {
                                    setCurrentAnswers({});
                                    setQuizResult(null);
                                    setHasSubmitted(false);
                                    setActiveQuizTaking(quiz);
                                    // Clear lesson focus
                                    setActiveLesson(null);
                                    setActiveLiveSession(null);
                                  }}
                                  className="w-full bg-amber-600 hover:bg-amber-500 text-white font-bold py-1.2 rounded-md text-[10px] transition-colors flex items-center justify-center cursor-pointer"
                                >
                                  Iniciar Teste de Prática
                                </button>
                              )}
                            </div>
                          );
                        })
                      )}
                    </div>
                  </div>

                </div>
                )}

              </div>
              )}

            </div>
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
                      {courses.flatMap(c => c.liveSessions).filter(s => !s.isLive).map(session => (
                        <div key={session.id} className="flex items-center gap-4 p-3 rounded-xl border border-slate-100 bg-slate-50 hover:bg-white transition-colors group">
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

                  {/* Churn Prevention Metric Pillars */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 pt-2">
                    <div className="bg-emerald-50/40 border border-emerald-100 rounded-xl p-4 space-y-2">
                      <span className="p-2 bg-emerald-100 text-emerald-700 rounded-lg inline-block">
                        <Award className="h-4 w-4" />
                      </span>
                      <strong className="block text-xs font-black text-slate-800">Certificado Oficial Garantido</strong>
                      <p className="text-[10.5px] text-slate-500 leading-normal">
                        Ao participar ativamente e assegurar <span className="font-extrabold text-slate-700">{viewingCatalogCourse.minAttendance || 70}%</span> de presença mínima, você garante seu certificado digital chancelado.
                      </p>
                    </div>

                    <div className="bg-teal-50/40 border border-teal-100 rounded-xl p-4 space-y-2">
                      <span className="p-2 bg-teal-100 text-teal-700 rounded-lg inline-block">
                        <Layers className="h-4 w-4" />
                      </span>
                      <strong className="block text-xs font-black text-slate-800">Módulos de Fixação Rápida</strong>
                      <p className="text-[10.5px] text-slate-500 leading-normal">
                        Tire dúvidas usando mini-quizzes integrados e leituras de base científica projetadas para aprendizagem acelerada.
                      </p>
                    </div>

                    <div className="bg-sky-50/50 border border-sky-100 rounded-xl p-4 space-y-2">
                      <span className="p-2 bg-sky-100 text-sky-700 rounded-lg inline-block">
                        <Calendar className="h-4 w-4" />
                      </span>
                      <strong className="block text-xs font-black text-slate-800">Aulas ao Vivo & Reuniões</strong>
                      <p className="text-[10.5px] text-slate-500 leading-normal">
                        Participe de discussões interativas semanais e veja plantões síncronos com seu professor e colegas de turma do AVA.
                      </p>
                    </div>

                    <div className="bg-rose-50/40 border border-rose-100 rounded-xl p-4 space-y-2">
                      <span className="p-2 bg-rose-100 text-rose-700 rounded-lg inline-block">
                        <Shield className="h-4 w-4" />
                      </span>
                      <strong className="block text-xs font-black text-slate-800">Tolerância Acadêmica de 5 Dias</strong>
                      <p className="text-[10.5px] text-slate-500 leading-normal">
                        Política de Saída Limpa: Desista ou mude de curso em até 5 dias sem qualquer penalidade ou registro de inadimplência escolar.
                      </p>
                    </div>
                  </div>

                  {/* Syllabus on Left, Course Details / CTA on Right */}
                  <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 pt-2">
                    
                    {/* Course syllabus / Curriculum grade details */}
                    <div className="lg:col-span-2 space-y-4">
                      
                      <div className="border border-slate-200 rounded-xl bg-slate-50/40 p-5 space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                          <div>
                            <h4 className="text-xs uppercase font-black text-slate-500 tracking-wider">Estrutura Curricular Detalhada & Cronograma</h4>
                            <span className="text-[10px] text-slate-400 font-bold block mt-0.5">Explore exatamente o que será apresentado em cada etapa letiva</span>
                          </div>
                          <span className="text-[10px] uppercase font-mono font-black bg-white px-2.5 py-1 border border-slate-200 rounded-lg text-slate-700 whitespace-nowrap">
                            {viewingCatalogCourse.lessons ? viewingCatalogCourse.lessons.length : 0} Lições Cadastradas
                          </span>
                        </div>

                        {/* Lessons syllabus list */}
                        <div className="space-y-3.5">
                          {viewingCatalogCourse.lessons && viewingCatalogCourse.lessons.length > 0 ? (
                            viewingCatalogCourse.lessons.map((lesson, idx) => {
                              // Churn prevention details: We formulate highly custom contextual highlights
                              const descriptionsPerIndex = [
                                "Introdução Básica: Alinhamento das diretrizes curriculares do AVA, glossário fundamental e primeiras leituras acadêmicas.",
                                "Aprofundamento Prático: Atividades com exemplos de mercado passo a passo no sandbox de simulação e consolidação conceitual.",
                                "Avaliação Teórica de Meio-Termo: Métricas, boas práticas de resolução rápida e exercícios adaptativos de fixação imediata.",
                                "Trabalho Final Integrado: Casos corporativos práticos reais e orientação direta para postagem e validação do portfólio."
                              ];
                              const customizedDesc = descriptionsPerIndex[idx % descriptionsPerIndex.length];

                              return (
                                <div key={lesson.id} className="bg-white p-4 rounded-xl border border-slate-150 transition-all hover:border-teal-200/75 flex flex-col gap-2.5">
                                  <div className="flex items-center justify-between gap-4">
                                    <div className="flex items-center gap-2.5 min-w-0">
                                      <span className="flex items-center justify-center h-6 w-6 rounded-lg bg-teal-50 border border-teal-200 text-[11px] font-black text-teal-850 shrink-0">
                                        {idx + 1}
                                      </span>
                                      <strong className="text-xs font-black text-slate-800 leading-snug truncate">{lesson.title}</strong>
                                    </div>
                                    <span className="text-slate-500 text-[9px] font-mono font-bold bg-slate-50 px-2 py-0.5 rounded border border-slate-150 shrink-0 uppercase">
                                      ⏰ Duração: {lesson.duration || `${lesson.durationMinutes || 45} min`}
                                    </span>
                                  </div>
                                  
                                  {/* Explanatory text */}
                                  <div className="text-[11px] text-slate-500 leading-relaxed font-medium bg-slate-50/50 p-3 rounded-lg border border-slate-100">
                                    <span className="font-extrabold text-[#540D6E] block text-[9.5px] uppercase tracking-wider mb-1">Destaques do Módulo:</span>
                                    {customizedDesc} {lesson.content ? "Este bloco traz também material teórico detalhado composto por textos formatados em Markdown e testes simulados de prática." : ""}
                                  </div>
                                </div>
                              );
                            })
                          ) : (
                            <p className="text-xs text-slate-400 italic text-center py-6">Nenhuma aula cadastrada ainda nesta disciplina.</p>
                          )}
                        </div>
                      </div>

                      {/* Interactive Student Agreement to Prevent Procrastination and Drifting */}
                      <div className="bg-[#540D6E]/5 rounded-xl border border-[#540D6E]/10 p-5 space-y-3 text-left">
                        <h4 className="text-xs font-black uppercase text-[#540D6E] tracking-wider flex items-center gap-1.5">
                          <CheckSquare className="h-4 w-4" />
                          <span>Pacto de Compromisso & Foco Estudantil (Garantia de Sucesso)</span>
                        </h4>
                        <p className="text-[11px] text-slate-600 leading-relaxed">
                          Sabe-se que <span className="font-bold">85% dos alunos</span> que assinam um compromisso visual de aprendizagem completam os cursos até a obtenção do certificado. Declare o seu foco antes de matricular-se:
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 text-[10px] text-slate-600 font-bold">
                          <label className="flex items-center gap-2 bg-white/70 border border-slate-150 rounded-lg p-2.5 select-none cursor-pointer hover:bg-white transition-colors">
                            <input defaultChecked type="checkbox" className="accent-[#540D6E] h-3.5 w-3.5 cursor-pointer" />
                            <span>Vou focar em {viewingCatalogCourse.minAttendance || 70}% de presença</span>
                          </label>
                          <label className="flex items-center gap-2 bg-white/70 border border-slate-150 rounded-lg p-2.5 select-none cursor-pointer hover:bg-white transition-colors">
                            <input defaultChecked type="checkbox" className="accent-[#540D6E] h-3.5 w-3.5 cursor-pointer" />
                            <span>Entregar exercícios práticos sem pressa</span>
                          </label>
                          <label className="flex items-center gap-2 bg-white/70 border border-slate-150 rounded-lg p-2.5 select-none cursor-pointer hover:bg-white transition-colors">
                            <input defaultChecked type="checkbox" className="accent-[#540D6E] h-3.5 w-3.5 cursor-pointer" />
                            <span>Consultar o Prof. em caso de bloqueio</span>
                          </label>
                          <label className="flex items-center gap-2 bg-white/70 border border-slate-150 rounded-lg p-2.5 select-none cursor-pointer hover:bg-white transition-colors">
                            <input defaultChecked type="checkbox" className="accent-[#540D6E] h-3.5 w-3.5 cursor-pointer" />
                            <span>Respeitar a tolerância letiva de 5 dias</span>
                          </label>
                        </div>
                      </div>

                    </div>

                    {/* Enrollment CTA Panel Sidebar */}
                    <div className="space-y-4">
                      
                      {/* Teacher Profile & Direct Availability Details */}
                      <div className="bg-slate-50 border border-slate-200 rounded-xl p-5 space-y-4">
                        <span className="text-[9.5px] uppercase font-black text-slate-400 font-mono tracking-wider block">Professor Designado</span>
                        <div className="flex items-center gap-2.5">
                          <div className="h-10 w-10 rounded-full bg-[#540D6E] text-white flex items-center justify-center text-xs font-black shrink-0 shadow-xs">
                            {viewingCatalogCourse.instructorName ? viewingCatalogCourse.instructorName.charAt(0) : 'P'}
                          </div>
                          <div className="min-w-0">
                            <strong className="text-xs font-black text-slate-800 block truncate">Prof. {viewingCatalogCourse.instructorName || 'Acadêmico'}</strong>
                            <span className="inline-flex items-center gap-1.5 bg-emerald-50 border border-emerald-100 rounded px-1.5 py-0.5 text-[8.5px] font-black text-emerald-700 uppercase tracking-widest mt-0.5">
                              <span className="h-1 w-1 bg-emerald-500 rounded-full animate-pulse" />
                              <span>ON-LINE NO CHAT</span>
                            </span>
                          </div>
                        </div>

                        {/* Quality Specifications */}
                        <div className="space-y-2 font-medium text-xs text-slate-650 pt-2 border-t border-slate-100">
                          <div className="flex justify-between items-center bg-white p-2.5 rounded-lg border border-slate-150">
                            <span>Quantidade de Aulas:</span>
                            <strong className="text-slate-800 font-mono text-[11px] font-black">
                              {viewingCatalogCourse.lessons ? viewingCatalogCourse.lessons.length : 0} Aulas
                            </strong>
                          </div>
                          <div className="flex justify-between items-center bg-white p-2.5 rounded-lg border border-slate-150">
                            <span>Mínimo de Frequência:</span>
                            <strong className="text-emerald-700 font-mono text-[11px] font-black">
                              {viewingCatalogCourse.minAttendance || 70}%
                            </strong>
                          </div>
                          <div className="flex justify-between items-center bg-white p-2.5 rounded-lg border border-slate-150">
                            <span>Modalidade Acadêmica:</span>
                            <strong className="text-slate-700 font-sans text-[11px] font-black">
                              EAD Autoinstrucional
                            </strong>
                          </div>
                          <div className="flex justify-between items-center bg-white p-2.5 rounded-lg border border-slate-150">
                            <span>Idioma das Aulas:</span>
                            <strong className="text-slate-700 font-sans text-[11px] font-black">
                              Português (Brasil)
                            </strong>
                          </div>
                        </div>

                        {/* CTA button to confirm enrollment */}
                        <div className="pt-3 border-t border-slate-200">
                          <button
                            id="btn-confirm-enroll"
                            onClick={() => {
                              enrollStudentInCourse(activeUser.name, viewingCatalogCourse.id);
                              speakText(`Matrícula concluída com sucesso no curso de ${viewingCatalogCourse.title}. Bons estudos!`);
                              const freshCourse = courses.find(c => c.id === viewingCatalogCourse.id);
                              setSelectedCourse(freshCourse || viewingCatalogCourse);
                              setViewingCatalogCourse(null);
                              window.scrollTo({ top: 0, behavior: 'smooth' });
                            }}
                            className="w-full bg-teal-600 hover:bg-teal-500 text-white font-black text-xs uppercase tracking-wide py-3.5 rounded-xl text-center transition-all cursor-pointer shadow-md hover:scale-[1.01] flex items-center justify-center gap-1.5"
                          >
                            <BookOpen className="h-4.5 w-4.5" />
                            <span>Confirmar Inscrição Gratuita</span>
                          </button>
                          <p className="text-[9.5px] text-slate-400 font-black text-center mt-2.5 uppercase tracking-wide">
                            ✓ Começar Estudos Imediatamente
                          </p>
                        </div>
                      </div>

                      {/* Informative Frequently Asked Questions (FAQ) explicitly targeting drop-out prevention */}
                      <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-2.5 text-xs text-slate-600">
                        <strong className="block text-slate-800 font-black text-[10px] uppercase tracking-wider flex items-center gap-1">
                          <Info className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                          <span>Perguntas e Respostas Úteis</span>
                        </strong>
                        <div className="space-y-3">
                          <div className="space-y-0.5">
                            <h5 className="font-extrabold text-slate-900 text-[10.5px]">Se eu perder o prazo letivo o que acontece?</h5>
                            <p className="text-[10px] text-slate-500 leading-normal">
                              Caso queira sair do curso, solicite a saída em até <strong className="font-bold text-slate-600">5 dias letivos</strong> para liberar sua ficha comercial. Após 5 dias letivos, você entra em regime temporário de inadimplência escolar (penalidade acadêmica ajustável pela coordenação).
                            </p>
                          </div>
                          <div className="space-y-0.5">
                            <h5 className="font-extrabold text-slate-900 text-[10.5px]">Como funciona a emissão de diploma?</h5>
                            <p className="text-[10px] text-slate-500 leading-normal">
                              O diploma fica disponível para download imediatamente em formato PDF autenticado após a conclusão do progresso mínimo (quizzes e presença síncrona/leitura). No AVA, não há custo extra!
                            </p>
                          </div>
                        </div>
                      </div>

                    </div>
                  </div>
                </div>
              ) : (
                <div className="space-y-6 text-left">
                  {/* Scenario 1: Active Enrolled Course Card */}
                  {enrollmentRecord.enrolledCourseId ? (
                    (() => {
                      const activeCourse = courses.find(c => c.id === enrollmentRecord.enrolledCourseId);
                      if (!activeCourse) return null;
                      const attendance = calculateAttendancePercent(activeCourse.id);
                      const minAttendance = activeCourse.minAttendance !== undefined ? activeCourse.minAttendance : 70;
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
                  {enrollmentRecord.dropOutPenaltyUntil && new Date(enrollmentRecord.dropOutPenaltyUntil).getTime() > Date.now() ? (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 shadow-xs animate-in fade-in duration-300">
                      <div className="flex flex-col sm:flex-row items-start justify-between gap-5">
                        <div className="space-y-1.5 max-w-2xl">
                          <div className="flex items-center gap-2 text-rose-800 font-bold text-sm">
                            <Lock className="h-5 w-5 text-rose-600 animate-pulse" />
                            <span>⚠️ Bloqueio temporário de Matrícula - Inadimplência</span>
                          </div>
                          <p className="text-xs text-rose-900/85 leading-relaxed">
                            Como você cancelou sua inscrição em uma matéria fora do prazo limite regulamentar de <strong className="text-rose-600 font-bold">5 dias letivos</strong>, você foi classificado como <strong>Inadimplente</strong>. Novas matrículas estão suspensas pelo prazo regulamentar de 1 mês.
                          </p>
                          <div className="text-[11px] text-rose-700 font-semibold pt-1">
                            Sua penalidade termina em: <span className="underline font-bold font-mono bg-rose-100 px-1.5 py-0.5 rounded">{new Date(enrollmentRecord.dropOutPenaltyUntil).toLocaleDateString('pt-BR')}</span>
                          </div>
                        </div>
                        
                        <button
                          onClick={() => {
                            clearStudentPenalty(activeUser.name);
                            speakText("Restrição escolar removida com sucesso. Sinta-se livre para escolher novos cursos!");
                          }}
                          className="shrink-0 bg-slate-900 hover:bg-slate-800 text-white font-bold text-[9px] uppercase tracking-wider px-3.5 py-2 rounded-xl transition-all border border-slate-700 cursor-pointer"
                          title="Remover inadimplência para continuar testando à vontade!"
                        >
                          Remover Penalidade (Simulador)
                        </button>
                      </div>
                    </div>
                  ) : null}

                  {/* Scenario 3: Course Selection Catalog (Available when no active enrollment and not penalized) */}
                  {!enrollmentRecord.enrolledCourseId && !(enrollmentRecord.dropOutPenaltyUntil && new Date(enrollmentRecord.dropOutPenaltyUntil).getTime() > Date.now()) && (
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
                              📂 Ver Tudo ({courses.length})
                            </button>
                            {Array.from(new Set(courses.map(c => c.category))).map(category => {
                              const count = courses.filter(c => c.category === category).length;
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
                          </div>
                        </div>
                      </div>

                      {/* Displaying match counts dynamically */}
                      {(() => {
                        const filtered = courses
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
                              {filtered.map((course) => {
                                const minAtt = course.minAttendance !== undefined ? course.minAttendance : 70;
                                const isAlreadyCompleted = enrollmentRecord.completedCourseIds?.includes(course.id);
                                return (
                                  <div key={course.id} className="group relative rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition-with-duration hover:shadow-md hover:border-[#540D6E]/30 flex flex-col justify-between text-left animate-in fade-in zoom-in-95 duration-150">
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
                          .map(course => (
                            <div 
                              key={course.id}
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
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="md:col-span-2 space-y-6">
              {/* Certificate listing block */}
              <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs text-left">
                <div className="flex items-center gap-2 mb-4">
                  <Award className="h-5 w-5 text-teal-600" />
                  <h3 className="font-black text-slate-800 text-sm uppercase tracking-wider">Certificados Obtidos ({certificates.filter(c => c.studentName === activeUser.name).length})</h3>
                </div>
                
                <div className="space-y-3">
                  {certificates.filter((cert) => cert.studentName === activeUser.name).length === 0 ? (
                    <div className="py-12 border-2 border-dashed border-slate-100 rounded-xl flex flex-col items-center justify-center text-slate-400 grayscale opacity-40">
                      <Award className="h-10 w-10 mb-2" />
                      <p className="text-xs font-bold">Nenhum certificado emitido ainda.</p>
                    </div>
                  ) : (
                    certificates.filter((cert) => cert.studentName === activeUser.name).map((cert) => (
                      <div 
                        key={cert.id} 
                        onClick={() => setSelectedCertificate(cert)}
                        className="group p-4 bg-amber-50/20 border border-amber-200/60 rounded-xl flex items-center justify-between cursor-pointer hover:bg-amber-50 transition-colors"
                      >
                        <div className="flex items-center gap-3">
                          <div className="p-2.5 bg-amber-100 text-amber-700 rounded-full border border-amber-200/50">
                            <Award className="h-5 w-5" />
                          </div>
                          <div>
                            <span className="block text-sm font-black text-slate-900 leading-tight group-hover:text-[#540D6E] transition-colors">{cert.courseTitle}</span>
                            <span className="block text-[10px] font-mono text-slate-500 uppercase mt-0.5">Código: {cert.verificationHash}</span>
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          <button 
                            className="p-1.5 bg-slate-100 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors cursor-pointer"
                            title="Compartilhar no LinkedIn"
                            onClick={(e) => { e.stopPropagation(); alert('Compartilhando no LinkedIn...'); }}
                          >
                            <Linkedin className="h-4 w-4" />
                          </button>
                          <button 
                            className="p-1.5 bg-slate-100 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                            title="Baixar PDF"
                            onClick={(e) => { e.stopPropagation(); alert('Preparando seu certificado em PDF para impressão...'); }}
                          >
                            <Download className="h-4 w-4" />
                          </button>
                          <ChevronRight className="h-4 w-4 text-amber-400 group-hover:translate-x-0.5 transition-transform" />
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </div>
            </div>

            <div className="space-y-6">
              <div className="rounded-2xl bg-white border border-slate-100 p-6 shadow-xs text-left">
                <h4 className="font-black text-slate-800 text-xs uppercase tracking-wider mb-5 flex items-center gap-2">
                  <Settings className="h-4 w-4 text-teal-600" />
                  Dúvidas do Certificado?
                </h4>
                <ol className="space-y-4">
                  <li className="text-[11px] leading-relaxed text-slate-500 flex gap-3">
                    <span className="font-black text-teal-600">1.</span>
                    Cada aula de fixação marcada como visualizada computa progresso de conteúdo teórico.
                  </li>
                  <li className="text-[11px] leading-relaxed text-slate-500 flex gap-3">
                    <span className="font-black text-teal-600">2.</span>
                    Aulas ao vivo computam progresso quando você confirma presença ao entrar na videoconferência integrada.
                  </li>
                  <li className="text-[11px] leading-relaxed text-slate-500 flex gap-3">
                    <span className="font-black text-teal-600">3.</span>
                    A média de freqüência calcula a soma ponderada de todas as aulas e presenciais listados naquele curso. Somados se deve ter no mínimo <strong>70%</strong>.
                  </li>
                  <li className="text-[11px] leading-relaxed text-slate-500 flex gap-3">
                    <span className="font-black text-teal-600">4.</span>
                    Mude seu nome de aluno no topo da página para que o certificado conste seu nome civil oficial!
                  </li>
                </ol>
              </div>
            </div>
          </div>
        </div>
      ) : activeDashboardTab === 'documents' ? (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500">
          <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs text-left max-w-2xl">
            <div className="flex items-center gap-2 mb-2">
              <FileCheck className="h-5 w-5 text-teal-600" />
              <h3 className="font-black text-slate-800 text-sm uppercase tracking-wider">Solicitações de Documentos</h3>
            </div>
            <p className="text-xs text-slate-500 leading-relaxed mb-6">
              Precisa de um documento acadêmico ou comprovante? Abra um requerimento e acompanhe o parecer digital homologado pela coordenação.
            </p>
            
            <div className="space-y-4 mb-8">
              {academicRequests.filter(r => r.studentName === activeUser.name).map(req => (
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
                      studentName: activeUser.name,
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
                        {courses.map(c => <option key={c.id} value={c.title}>{c.title}</option>)}
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
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-6 text-left">
           <div className="flex items-center justify-between">
              <div>
                 <h3 className="text-xl font-black text-slate-900 tracking-tight">📚 Biblioteca Digital</h3>
                 <p className="text-xs text-slate-500">Repositório centralizado de materiais complementares e e-books.</p>
              </div>
           </div>
           <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
              {libraryItems.map(item => (
                <div key={item.id} className="bg-white border border-slate-200 rounded-2xl p-5 hover:border-teal-200 hover:shadow-lg transition-all group">
                   <div className="flex items-start justify-between mb-4">
                      <div className={`p-2.5 rounded-xl ${item.type === 'pdf' ? 'bg-rose-50 text-rose-600' : 'bg-blue-50 text-blue-600'}`}>
                         {item.type === 'pdf' ? <FileText className="h-6 w-6" /> : <Globe className="h-6 w-6" />}
                      </div>
                      <span className="text-[9px] font-black uppercase text-slate-400 bg-slate-50 px-2 py-0.5 rounded border border-slate-100">{item.category}</span>
                   </div>
                   <h4 className="font-bold text-sm text-slate-900 leading-tight group-hover:text-teal-600 transition-colors">{item.title}</h4>
                   <p className="text-[11px] text-slate-500 mt-2 leading-relaxed line-clamp-2">{item.description}</p>
                   <a 
                     href={item.url} 
                     target="_blank" 
                     rel="noreferrer" 
                     className="mt-4 w-full bg-slate-50 hover:bg-teal-600 hover:text-white text-slate-600 font-bold py-2 rounded-xl text-[10px] transition-all flex items-center justify-center gap-2 border border-slate-100"
                   >
                     {item.type === 'pdf' ? <Download className="h-3 w-3" /> : <ExternalLink className="h-3 w-3" />}
                     <span>{item.type === 'pdf' ? 'Baixar Arquivo' : 'Acessar Link'}</span>
                   </a>
                </div>
              ))}
           </div>
        </div>
      ) : activeDashboardTab === 'events' ? (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-6 text-left">
           <div className="flex items-center justify-between">
              <div>
                 <h3 className="text-xl font-black text-slate-900 tracking-tight">📅 Eventos & Webinars</h3>
                 <p className="text-xs text-slate-500">Aulas magnas, workshops e eventos extracurriculares exclusivos.</p>
              </div>
           </div>
           <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {webinarEvents.map(event => (
                <div key={event.id} className="bg-white border border-slate-200 rounded-3xl overflow-hidden hover:shadow-xl transition-all group flex flex-col sm:flex-row">
                   <div className="sm:w-40 h-40 sm:h-full relative shrink-0 overflow-hidden">
                      <img src={event.image} alt={event.title} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" referrerPolicy="no-referrer" />
                      <div className="absolute inset-0 bg-[#540D6E]/20" />
                   </div>
                   <div className="p-6 flex-1 flex flex-col justify-between">
                      <div>
                         <div className="flex items-center gap-3 text-[10px] font-black text-teal-600 uppercase tracking-widest mb-2">
                            <span className="flex items-center gap-1"><Calendar className="h-3 w-3" /> {event.date}</span>
                            <span className="flex items-center gap-1"><Clock className="h-3 w-3" /> {event.time}</span>
                         </div>
                         <h4 className="font-black text-lg text-slate-900 leading-tight mb-2">{event.title}</h4>
                         <p className="text-xs text-slate-500 leading-relaxed line-clamp-2">{event.description}</p>
                      </div>
                      <button className="mt-4 w-full bg-[#540D6E] hover:bg-slate-900 text-white font-black py-2.5 rounded-xl text-[10px] uppercase tracking-widest transition-all cursor-pointer">
                         Inscrição Gratuita
                      </button>
                   </div>
                </div>
              ))}
           </div>
        </div>
      ) : activeDashboardTab === 'settings' ? (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-6 text-left max-w-4xl mx-auto">
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
          <div className="flex justify-end">
             <button 
               onClick={() => setShowKnowledgeBase(true)}
               className="flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-xl text-xs font-bold text-teal-600 hover:bg-teal-50 transition-all shadow-xs cursor-pointer"
             >
                <HelpCircle className="h-4 w-4" />
                <span>Base de Conhecimento (Tutoriais)</span>
             </button>
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
                    (localStorage.getItem('ava_presence_status_Gestor de Cursos') || 'online') === 'online'
                      ? 'bg-emerald-500 animate-pulse'
                      : 'bg-slate-400'
                  }`} />
                </div>
                <div>
                  <span className="block text-[8px] text-slate-400 font-bold uppercase leading-none">Gestor Responsável</span>
                  <span className="font-bold text-[10.5px] flex items-center gap-1.5 leading-none mt-0.5">
                    <span>Gestor de Cursos</span>
                    <span className={`text-[9px] font-black ${
                      (localStorage.getItem('ava_presence_status_Gestor de Cursos') || 'online') === 'online'
                        ? 'text-emerald-600'
                        : 'text-slate-500'
                    }`}>
                      ({(localStorage.getItem('ava_presence_status_Gestor de Cursos') || 'online') === 'online' ? 'Online' : 'Offline'})
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
                {directMessages.filter(m => m.studentName === activeUser.name).length === 0 ? (
                  <div className="flex-1 flex flex-col items-center justify-center text-slate-400 space-y-2 py-10">
                    <MessageSquare className="h-10 w-10 text-slate-300 animate-pulse" />
                    <p className="text-xs font-bold text-slate-500">Nenhuma conversa ativa no momento.</p>
                    <p className="text-[10px] text-slate-400 max-w-[280px] text-center leading-relaxed">Envie uma mensagem abaixo para abrir seu canal direto de tutoria acadêmica!</p>
                  </div>
                ) : (
                  directMessages
                    .filter(m => m.studentName === activeUser.name)
                    .map((msg) => {
                      const isStudent = msg.senderRole === 'student';
                      return (
                        <div key={msg.id} className={`flex flex-col ${isStudent ? 'items-end' : 'items-start'} animate-in fade-in slide-in-from-bottom-1 duration-200`}>
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
                  sendDirectMessage(activeUser.name, text);
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
                      studentName: activeUser.name,
                      senderName: 'Gestor de Cursos',
                      senderRole: 'instructor',
                      text: randomPhrase,
                      timestamp: new Date().toISOString()
                    };
                    localStorage.setItem('ava_direct_messages', JSON.stringify([...currentDMs, tutorResponse]));
                    window.dispatchEvent(new Event('storage'));
                  }, 1800);
                }
              }} className="flex gap-2">
                <input
                  name="messageText"
                  type="text"
                  required
                  placeholder="Digite sua mensagem ao Gestor de Cursos..."
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

      {/* Certificate Viewer Modal Overlay */}
      {selectedCertificate && (
        <CertificateTemplate
          certificate={selectedCertificate}
          onClose={() => setSelectedCertificate(null)}
        />
      )}

      {/* Live Classroom modal overlay */}
      {selectedCourse && activeLiveSession && (
        <LiveClassroom
          course={selectedCourse}
          session={activeLiveSession}
          onClose={() => {
            setActiveLiveSession(null);
          }}
        />
      )}
    </div>
  );
};
