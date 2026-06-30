/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect, useRef } from 'react';
import { LMSProvider, useLMS } from './context/LMSContext';
import { StudentDashboard } from './components/StudentDashboard';
import { InstructorDashboard } from './components/InstructorDashboard';
import { AdminDashboard } from './components/AdminDashboard';
import { ProfileView } from './components/ProfileView';
import { 
  GraduationCap, Users, User, Award, Video, CheckSquare, 
  ArrowRight, ArrowLeft, ShieldCheck, Flame, LogOut, Lock, 
  Shield, Activity, Settings, HelpCircle, BookOpen, Palette, Globe,
  Search, Menu, ChevronLeft, ChevronRight, Star, Play, FileText,
  Mail, ExternalLink, X, Sparkles, Calendar, MapPin, Info,
  Printer, Download, Monitor, CheckCircle, Instagram, Youtube, Facebook, Twitter, Home, Bell, MessageSquare,
  Fingerprint, Clock
} from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
// @ts-ignore
import pauloFreirePortrait from './assets/images/paulo_freire_portrait_1779991080618.png';

// Avasec Logo Component representing the abstract artistic head profile in primary colors
function AvasecLogo() {
  return (
    <div className="flex items-center gap-2 select-none">
      <svg className="h-10 w-10 shrink-0" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
        {/* Background art circle */}
        <circle cx="50" cy="50" r="46" fill="#f8fafc" stroke="#e2e8f0" strokeWidth="2" />
        {/* Colorful Abstract Face Segments representing Escola da Cultura Modernist Art */}
        <path d="M35 25h12v40H35z" fill="#3BCEAC" className="opacity-90" /> {/* Green left column */}
        <path d="M47 30h12v25H47z" fill="#EE4266" className="opacity-95" /> {/* Red center bar */}
        <path d="M59 20h12v50H59z" fill="#540D6E" className="opacity-95" /> {/* Blue right column */}
        <path d="M35 65h36v8H35z" fill="#FFD23F" className="opacity-95" />  {/* Yellow base bar */}
        {/* Overlapping eye circles / creative geometric features */}
        <circle cx="41" cy="40" r="6" fill="#FFD23F" />
        <circle cx="65" cy="45" r="7" fill="#EE4266" />
        <circle cx="53" cy="35" r="5" fill="#540D6E" />
        {/* Artistic curved nose sector */}
        <path d="M47 55 a6 6 0 0 1 12 0" fill="none" stroke="#1e293b" strokeWidth="4" strokeLinecap="round" />
      </svg>
      <div className="leading-none text-left">
        <span className="font-sans font-black text-2xl tracking-tighter text-[#540D6E] block">AVASEC</span>
        <span className="text-[7.5px] uppercase tracking-widest text-slate-500 font-bold block mt-0.5">Escola da Cultura</span>
      </div>
    </div>
  );
}



function DashboardSwitcher() {
  const { 
    activeUser, 
    setUserProfile, 
    professorsList,
    studentsList,
    courses,
    directMessages,
    systemSettings,
    setActiveDashboardTab,
    accessibilitySettings,
    updateAccessibilitySettings,
    isSpeechEnabled,
    setIsSpeechEnabled,
    currentLang,
    setCurrentLang,
    textSizeMultiplier,
    setTextSizeMultiplier,
    addSecurityLog,
    securityLogs,
    addStudent
  } = useLMS();
  
  const isUserLoggedIn = activeUser && activeUser.name !== '';
  
  // List of students that are simulated in the system
  const studentsWithMessages = studentsList.map(s => s.name);
  const unrepliedStudents = studentsWithMessages.filter(studentName => {
    const studentDMs = directMessages.filter(m => m.studentName === studentName);
    if (studentDMs.length === 0) return false;
    const latestMsg = studentDMs[studentDMs.length - 1];
    return latestMsg.senderRole === 'student'; // Unanswered by the instructor
  });
  
  // Navigation & UI States
  const [currentView, setCurrentView] = useState<'landing' | 'active_app' | 'perfil'>('landing');
  const [previousView, setPreviousView] = useState<'landing' | 'active_app'>('landing');
  const [isLoginModalOpen, setIsLoginModalOpen] = useState(false);
  
  // Registration and external validator integration states
  const [isRegisterModalOpen, setIsRegisterModalOpen] = useState(false);
  const [registerName, setRegisterName] = useState('');
  const [registerEmail, setRegisterEmail] = useState('');
  const [registerCpf, setRegisterCpf] = useState('');
  const [registerPassword, setRegisterPassword] = useState('');
  const [validationStep, setValidationStep] = useState<'idle' | 'matching' | 'verifying' | 'syncing' | 'completed'>('idle');
  const [validationProgress, setValidationProgress] = useState(0);
  const [isExternalLinkClicked, setIsExternalLinkClicked] = useState(false);
  const [validationError, setValidationError] = useState<string | null>(null);

  const [dismissedNotice, setDismissedNotice] = useState(false);
  const [loginRoleTab, setLoginRoleTab] = useState<'student' | 'instructor' | 'admin'>('student');
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const [globalSearchQuery, setGlobalSearchQuery] = useState('');

  const [searchQuery, setSearchQuery] = useState('');

  // Accessibility & Multi-language Internationalization (Transient UI states only)
  const [isAccessibilityOpen, setIsAccessibilityOpen] = useState(false);
  const [isSiteMapOpen, setIsSiteMapOpen] = useState(false);

  // PIN Verification Flow Security States
  const [pendingLogin, setPendingLogin] = useState<{ name: string; role: 'student' | 'instructor' | 'admin' } | null>(null);
  const [pinInput, setPinInput] = useState<string>('');
  const [pinError, setPinError] = useState<string | null>(null);
  const [isPinSuccess, setIsPinSuccess] = useState<boolean>(false);

  useEffect(() => {
    const isLocked = localStorage.getItem('ava_session_locked') === 'true';
    if (isLocked) {
      setIsLoginModalOpen(true);
      setCurrentView('landing');
    }
  }, []);

  const speakText = (text: string) => {
    if (!isSpeechEnabled) return;
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.lang = currentLang === 'pt' ? 'pt-BR' : currentLang === 'en' ? 'en-US' : 'es-ES';
      window.speechSynthesis.speak(utterance);
    }
  };

  const translations = {
    pt: {
      heroBadge: "Inscrições Abertas — Cursos Livres de Qualificação",
      heroTitleLine1: "Escola da Cultura",
      heroTitleLine2: "de Cultura e Economia Criativa",
      heroDesc: "A AVASEC é uma plataforma de cursos on-line destinada à formação e qualificação profissional em Cultura e Economia Criativa exclusivamente por meio da oferta de Cursos Livres 100% gratuitos. Cadastre-se e comece já!",
      btnDiscover: "Descubra mais",
      btnStart: "Comece a estudar",
      gradeTitle: "Pilares do Aprendizado",
      gradeSubtitle: "Diferenciais do Ensino Livre",
      gradeCard1: "Alta Aplicabilidade",
      gradeCard1Desc: "Atividades pontuais de curta duração e alta aplicabilidade prática, ideais para sintonização rápida a novos processos tecnológicos e criativos do cenário nacional.",
      gradeCard2: "Certificação Gratuita",
      gradeCard2Desc: "Conclua as trilhas de estudo e emita seu certificado oficial digital gratuitamente, válido para comprovação de competência, editais e horas curriculares.",
      gradeCard3: "Ritmo Flexível",
      gradeCard3Desc: "Estude de qualquer lugar, no seu tempo e estilo de vida, com nossa plataforma dinâmica de cursos livres projetada para seu sucesso profissional.",
      coursesTitle: "Investimento Formativo",
      coursesSubtitle: "Nossos Cursos Livres Disponíveis",
      newsTitle: "Últimas Notícias",
      newsSubtitle: "Informativos Recentes",
      faqTitle: "Faq e Suporte ao Estudante",
      faqSubtitle: "Dúvidas Frequentes",
    },
    en: {
      heroBadge: "Open Applications — Free Qualification Courses",
      heroTitleLine1: "Culture School",
      heroTitleLine2: "of Culture and Creative Economy",
      heroDesc: "AVASEC is an online course platform dedicated to professional qualification in Culture and Creative Economy exclusively through the offer of 100% free Courses.",
      btnDiscover: "Discover more",
      btnStart: "Start Studying",
      gradeTitle: "Learning Pillars",
      gradeSubtitle: "Free Course Benefits",
      gradeCard1: "High Applicability",
      gradeCard1Desc: "Short-term classes with immediate practical applicability, perfect for quickly tuning into new technological and creative workflows in the cultural market.",
      gradeCard2: "Free Certification",
      gradeCard2Desc: "Complete your study paths and issue your official digital certificate for free, fully valid for cultural grants, bids, and academic credentials.",
      gradeCard3: "Flexible Pace",
      gradeCard3Desc: "Learn from anywhere, at your own pace and schedule, with our responsive online platform designed to fit your creative career.",
      coursesTitle: "Interactive Training",
      coursesSubtitle: "Our Available Free Courses",
      newsTitle: "Latest News",
      newsSubtitle: "Recent Notices",
      faqTitle: "Faq & Student Support",
      faqSubtitle: "Frequently Asked Questions",
    },
    es: {
      heroBadge: "Inscripciones Abiertas — Cursos Libres de Calificación",
      heroTitleLine1: "Escuela de la Cultura",
      heroTitleLine2: "de Cultura y Economía Creativa",
      heroDesc: "AVASEC es una plataforma de cursos en línea dedicada a la capacitación profesional en Cultura y Economía Creativa exclusivamente mediante la oferta de Cursos Libres 100% gratuitos.",
      btnDiscover: "Descubre más",
      btnStart: "Comience a estudiar",
      gradeTitle: "Pilares del Aprendizaje",
      gradeSubtitle: "Beneficios de los Cursos Libres",
      gradeCard1: "Alta Aplicación",
      gradeCard1Desc: "Sesiones formativas cortas y de alta aplicación práctica, ideales para sintonizar rápidamente con nuevos flujos creativos y tecnológicos en el mercado.",
      gradeCard2: "Certificado Gratuito",
      gradeCard2Desc: "Complete las rutas de estudio y emita su certificado oficial digital de forma gratuita, ideal para convocatorias de incentivo y créditos curriculares.",
      gradeCard3: "Ritmo Flexible",
      gradeCard3Desc: "Estudie desde cualquier lugar, a su propio ritmo y horario, con nuestra plataforma móvil dinámica diseñada para el éxito de su carrera creativa.",
      coursesTitle: "Inversión Formativa",
      coursesSubtitle: "Nuestros Cursos Livres Disponibles",
      newsTitle: "Últimas Noticias",
      newsSubtitle: "Boletines Recientes",
      faqTitle: "Faq y Soporte Estudiantil",
      faqSubtitle: "Preguntas Frecuentes",
    }
  };

  // Slider States
  const [activeCourseIndex, setActiveCourseIndex] = useState(0);
  const [activeNewsIndex, setActiveNewsIndex] = useState(0);

  // Hero Mouse Follow State
  const heroRef = useRef<HTMLDivElement>(null);
  const [mousePos, setMousePos] = useState({ x: 0, y: 0 });
  const [isMouseInHero, setIsMouseInHero] = useState(false);

  const handleHeroMouseMove = (e: React.MouseEvent) => {
    if (heroRef.current) {
      const rect = heroRef.current.getBoundingClientRect();
      setMousePos({
        x: e.clientX - rect.left,
        y: e.clientY - rect.top,
      });
    }
  };

  // Suggested Course State
  const [suggestedCourseName, setSuggestedCourseName] = useState('');
  const [suggestionSubmitted, setSuggestionSubmitted] = useState(false);

  // FAQ Expand state
  const [expandedFaqIndex, setExpandedFaqIndex] = useState<number | null>(null);

  // Mock Students
  const mockStudentProfiles = studentsList;

  // News dataset inspired by Image 8
  const newsData = [
    {
      id: 1,
      date: '20 mai. 2026',
      tag: 'Video Mapping',
      title: 'AVASEC abre inscrições para curso gratuito de Video Mapping focado no cenário cultural',
      image: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&auto=format&fit=crop&q=70',
      description: 'Uma imersão completa na arte digital e projeção mapeada para fachadas históricas, palcos e intervenções urbanas de impacto.'
    },
    {
      id: 2,
      date: '16 mai. 2026',
      tag: 'Prestação de Contas',
      title: 'Nova turma do Curso de Prestação de Contas de Propostas Simplificadas',
      image: 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=600&auto=format&fit=crop&q=70',
      description: 'Domine sem mistérios as exigências contábeis e fiscais das leis de fomento, garantindo a aprovação tranquila do seu relatório.'
    },
    {
      id: 3,
      date: '14 mai. 2026',
      tag: 'Propostas Simplificadas',
      title: 'Abertas as inscrições para a nova turma do curso de Submissão de Propostas',
      image: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=600&auto=format&fit=crop&q=70',
      description: 'Como transformar as suas ideias artísticas em formatos de propostas campeãs para concorrer a editais públicos e privados.'
    }
  ];

  // Highlighted Featured Courses dataset inspired by Image 5
  const featuredCoursesData = [
    {
      title: 'Inteligência Artificial e Cultura 2ª Oferta',
      category: 'Economia Criativa & IA',
      instructor: 'Gestor de Cursos',
      iconType: 'mic',
      iconBg: 'bg-[#540D6E]',
      image: 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=500&auto=format&fit=crop&q=60',
      description: 'Aprenda a aplicar ferramentas de Inteligência Artificial generativa no fomento, roteirização e design de projetos de artes integradas.'
    },
    {
      title: 'Produção Audiovisual 2ª Oferta',
      category: 'Áreas Técnicas',
      instructor: 'Gestor de Cursos',
      iconType: 'video',
      iconBg: 'bg-[#540D6E]',
      image: 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?w=500&auto=format&fit=crop&q=60',
      description: 'Da captação de áudio e iluminação até as técnicas de edição e publicação. Um guia prático para criadores independentes.'
    },
    {
      title: 'Submissão de Propostas Simplificadas 2ª Oferta',
      category: 'Políticas e Gestão Culturais',
      instructor: 'Gestor de Cursos',
      iconType: 'building',
      iconBg: 'bg-[#FFD23F]',
      image: 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?w=500&auto=format&fit=crop&q=60',
      description: 'Inscreva sua proposta cultural sem complicação. Compreenda leis de incentivo e preenchimento técnico de formulários oficiais.'
    },
    {
      title: 'Prestação de Contas de Propostas Simplificadas 2ª Oferta',
      category: 'Políticas e Gestão Culturais',
      instructor: 'Gestor de Cursos',
      iconType: 'columns',
      iconBg: 'bg-[#FFD23F]',
      image: 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=500&auto=format&fit=crop&q=60',
      description: 'Como organizar recibos, despesas e relatórios de atividades para certificar que os fundos recebidos foram devidamente executados.'
    }
  ];

  // Creative sectors category metadata inspired by Image 6
  const categoriesData = [
    {
      title: 'Áreas Técnicas',
      description: 'Oferecemos qualificação e certificação profissional nas diversas áreas que dão suporte ao fazer artístico e à Economia Criativa.',
      coursesCount: 5,
      icon: (
        <svg className="h-16 w-16 mx-auto" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="50" cy="50" r="40" fill="#540D6E" className="fill-blue-600 opacity-10" />
          <path d="M30 35 h40 v25 h-40 z" fill="#540D6E" />
          <circle cx="40" cy="70" r="8" fill="#FFD23F" />
          <circle cx="60" cy="70" r="8" fill="#EE4266" />
          <path d="M70 42 l12-8 v18 l-12-6 z" fill="#3BCEAC" />
        </svg>
      )
    },
    {
      title: 'Políticas e Gestão Culturais',
      description: 'Especialize-se nas melhores práticas para o desenvolvimento, execução, gestão e análise de projetos culturais e na compreensão das leis de incentivo.',
      coursesCount: 3,
      icon: (
        <svg className="h-16 w-16 mx-auto" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="50" cy="50" r="40" fill="#FFD23F" className="fill-amber-500 opacity-10" />
          {/* Semicircle colonnade columns */}
          <path d="M25 65 h50 v6 h-50 z" fill="#FFD23F" />
          <path d="M28 35 a22 22 0 0 1 44 0 z" fill="#EE4266" />
          <path d="M32 40 h6 v25 h-6 z" fill="#3BCEAC" />
          <path d="M47 40 h6 v25 h-6 z" fill="#540D6E" />
          <path d="M62 40 h6 v25 h-6 z" fill="#FFD23F" />
        </svg>
      )
    },
    {
      title: 'Linguagens Artísticas',
      description: 'Aqui você encontrará formação inicial, continuada e de especialização para música, artes visuais, teatro, dança e demais linguagens artísticas.',
      coursesCount: 6,
      icon: (
        <svg className="h-16 w-16 mx-auto" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="50" cy="50" r="40" fill="#EE4266" className="fill-red-600 opacity-10" />
          {/* Play/Comedy tragedy mask segments */}
          <path d="M30 45 a15 15 0 0 1 30 0 v10 a15 15 0 0 1-30 0 z" fill="#EE4266" />
          <path d="M50 45 a15 15 0 0 1 30 0 v10 a15 15 0 0 1-30 0 z" fill="#540D6E" />
          <circle cx="38" cy="45" r="3" fill="#FFD23F" />
          <circle cx="52" cy="45" r="3" fill="#FFD23F" />
          <circle cx="62" cy="45" r="3" fill="#3BCEAC" />
          <circle cx="72" cy="45" r="3" fill="#3BCEAC" />
          <path d="M40 54 q5 4 10 0" stroke="#fff" strokeWidth="2" fill="none" />
          <path d="M62 54 q5-4 10 0" stroke="#fff" strokeWidth="2" fill="none" />
        </svg>
      )
    },
    {
      title: 'Economia Criativa',
      description: 'Aprenda a transformar criatividade em negócio. Explore estratégias de empreendedorismo, inovação e novos modelos de gestão para fortalecer a cadeia produtiva.',
      coursesCount: 4,
      icon: (
        <svg className="h-16 w-16 mx-auto" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="50" cy="50" r="40" fill="#3BCEAC" className="fill-green-600 opacity-10" />
          <circle cx="50" cy="50" r="24" fill="#3BCEAC" />
          <circle cx="45" cy="40" r="5" fill="#EE4266" />
          <circle cx="60" cy="45" r="5" fill="#FFD23F" />
          <circle cx="52" cy="58" r="5" fill="#540D6E" />
          <circle cx="38" cy="52" r="5" fill="#ffffff" />
        </svg>
      )
    }
  ];

  // Dynamic matched searches
  const matchedCourses = searchQuery.trim() === '' 
    ? [] 
    : featuredCoursesData.filter(course => 
        course.title.toLowerCase().includes(searchQuery.toLowerCase()) || 
        course.description.toLowerCase().includes(searchQuery.toLowerCase())
      );

  const matchedNews = searchQuery.trim() === '' 
    ? [] 
    : newsData.filter(news => 
        news.title.toLowerCase().includes(searchQuery.toLowerCase()) || 
        news.description.toLowerCase().includes(searchQuery.toLowerCase())
      );

  // Dynamic sliders transitions
  const handleCourseNext = () => {
    setActiveCourseIndex((prev) => (prev + 1) % featuredCoursesData.length);
  };
  const handleCoursePrev = () => {
    setActiveCourseIndex((prev) => (prev - 1 + featuredCoursesData.length) % featuredCoursesData.length);
  };

  const handleNewsNext = () => {
    setActiveNewsIndex((prev) => (prev + 1) % newsData.length);
  };
  const handleNewsPrev = () => {
    setActiveNewsIndex((prev) => (prev - 1 + newsData.length) % newsData.length);
  };

  const handleProfileLogin = (name: string, role: 'student' | 'instructor' | 'admin') => {
    // Intercept with security PIN prompt
    setPendingLogin({ name, role });
    setPinInput('');
    setPinError(null);
    setIsPinSuccess(false);
    speakText(`Verificação de segurança requerida para o perfil de ${name}. Digite o PIN de acesso.`);
  };

  const executeProfileLogin = (name: string, role: 'student' | 'instructor' | 'admin') => {
    setUserProfile(name, role);
    addSecurityLog('Autenticação de Fluxo', `Login efetuado com PIN para o perfil: ${name}.`, 'SUCCESS');
    setCurrentView('active_app');
    setIsLoginModalOpen(false);
    setPendingLogin(null);
    localStorage.removeItem('ava_session_locked');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    speakText(`Acesso liberado com sucesso. Bem vindo de volta, ${name}.`);
  };

  const handleLogout = () => {
    setUserProfile('', 'student');
    setCurrentView('landing');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    speakText("Você desconectou do sistema com sucesso.");
  };

  const handlePinKeyClick = (val: string) => {
    if (pinInput.length < 8) {
      setPinInput(p => p + val);
      setPinError(null);
    }
  };

  const verifyPinAndLogin = () => {
    if (!pendingLogin) return;
    
    let isCorrect = false;
    const inputVal = pinInput;
    
    // Check custom password if set in localStorage
    const savedPassword = localStorage.getItem(`ava_active_password_${pendingLogin.name}`);
    
    if (savedPassword && inputVal === savedPassword) {
      isCorrect = true;
    } else {
      // Default fallback pins
      if (pendingLogin.role === 'admin' && (inputVal === '9999' || inputVal === 'admin2026')) {
        isCorrect = true;
      } else if (pendingLogin.role === 'instructor' && (inputVal === '5678' || inputVal === '1234')) {
        isCorrect = true;
      } else if (pendingLogin.role === 'student' && inputVal === '1234') {
        isCorrect = true;
      }
    }

    if (isCorrect) {
      setIsPinSuccess(true);
      setTimeout(() => {
        executeProfileLogin(pendingLogin.name, pendingLogin.role);
      }, 700);
    } else {
      setPinError('PIN ou Senha de segurança inválida!');
      setPinInput('');
      const failedSound = `Falha de verificação. PIN incorreto para o perfil ${pendingLogin.name}.`;
      speakText(failedSound);
      addSecurityLog('Tentativa Fracassada', `Código PIN incorreto inserido para o perfil: ${pendingLogin.name}.`, 'FAILED');
    }
  };

  const handleSubmitSuggestion = (e: React.FormEvent) => {
    e.preventDefault();
    if (suggestedCourseName.trim()) {
      setSuggestionSubmitted(true);
      setTimeout(() => {
        setSuggestionSubmitted(false);
        setSuggestedCourseName('');
      }, 5000);
    }
  };

  const handleSmoothScroll = (elementId: string) => {
    const el = document.getElementById(elementId);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  const toggleFaq = (index: number) => {
    setExpandedFaqIndex(expandedFaqIndex === index ? null : index);
  };

  return (
    <div className={`min-h-screen bg-white flex flex-col justify-between text-slate-800 font-sans selection:bg-slate-900 selection:text-white transition-colors duration-300 ${accessibilitySettings.highContrast ? 'high-contrast-active' : ''} ${textSizeMultiplier !== 1.0 ? 'text-scaled-active' : ''}`}>
      
      {/* Dynamic Style Injections for High Contrast and Text Scaler */}
      {accessibilitySettings.highContrast && (
        <style dangerouslySetInnerHTML={{ __html: `
          .high-contrast-active, .high-contrast-active * {
            background-color: #000000 !important;
            color: #ffffff !important;
            border-color: #ffff00 !important;
            text-shadow: none !important;
            box-shadow: none !important;
          }
          .high-contrast-active a, .high-contrast-active button, .high-contrast-active [role="button"] {
            background-color: #000000 !important;
            color: #ffff00 !important;
            border: 2px solid #ffff00 !important;
            text-decoration: underline !important;
          }
          .high-contrast-active a:hover, .high-contrast-active button:hover {
            background-color: #ffff00 !important;
            color: #000000 !important;
          }
          .high-contrast-active input, .high-contrast-active textarea, .high-contrast-active select {
            background-color: #000000 !important;
            color: #ffffff !important;
            border: 2.5px solid #ffff00 !important;
          }
          .high-contrast-active svg, .high-contrast-active svg * {
            stroke: #ffff00 !important;
            fill: none !important;
          }
        ` }} />
      )}
      {textSizeMultiplier !== 1.0 && (
        <style dangerouslySetInnerHTML={{ __html: `
          .text-scaled-active p, 
          .text-scaled-active span, 
          .text-scaled-active label, 
          .text-scaled-active li, 
          .text-scaled-active h1, 
          .text-scaled-active h2, 
          .text-scaled-active h3, 
          .text-scaled-active h4, 
          .text-scaled-active a, 
          .text-scaled-active button {
            font-size: calc(100% * ${textSizeMultiplier}) !important;
            line-height: 1.4 !important;
          }
        ` }} />
      )}
      {accessibilitySettings.dyslexicFont && (
        <style dangerouslySetInnerHTML={{ __html: `
          * {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
            letter-spacing: 0.05em !important;
            word-spacing: 0.1em !important;
          }
        ` }} />
      )}







      {/* 4. AVASEC Branded Header Section */}
      <header className="sticky top-0 z-40 bg-white/95 border-b border-slate-200/80 backdrop-blur-md shadow-3xs">
        <div className="mx-auto max-w-7xl px-4 h-20 md:px-6 flex items-center justify-between">
          
          {/* Logo & Brand title */}
          <div 
            onClick={() => {
              setCurrentView('landing');
              window.scrollTo({ top: 0, behavior: 'smooth' });
              speakText("Voltando para a Página Inicial.");
            }}
            title="Voltar ao Portal Inicial"
            className="cursor-pointer hover:opacity-95 transition-all"
          >
            <AvasecLogo />
          </div>

          {/* Desktop Navigation Links */}
          {currentView === 'landing' && !isSearchOpen && (
            <nav className="hidden lg:flex items-center gap-6 text-xs font-bold text-slate-650 uppercase tracking-widest">
              <button onClick={() => { 
                window.scrollTo({ top: 0, behavior: 'smooth' }); 
                speakText("Voltando para o topo da Página Inicial.");
              }} className="hover:text-[#540D6E] transition-colors border-b-2 border-[#540D6E] pb-1 cursor-pointer">
                Página Inicial
              </button>
              <button onClick={() => { handleSmoothScroll('cursos'); speakText("Cursos"); }} className="hover:text-[#540D6E] transition-colors pb-1 cursor-pointer flex items-center gap-1.5">
                <span>Cursos</span>
                <span className="bg-[#FFD23F] text-[8px] font-mono tracking-normal font-extrabold px-1.5 py-0.2 rounded-full text-slate-950 animate-bounce">NOVO!</span>
              </button>
              <button onClick={() => { handleSmoothScroll('noticias'); speakText("Notícias"); }} className="hover:text-[#540D6E] transition-colors pb-1 cursor-pointer">
                Notícias
              </button>
              <button onClick={() => { handleSmoothScroll('duvidas'); speakText("Dúvidas"); }} className="hover:text-[#540D6E] transition-colors pb-1 cursor-pointer">
                Dúvidas
              </button>
              <button onClick={() => { handleSmoothScroll('quem-somos'); speakText("Sobre"); }} className="hover:text-[#540D6E] transition-colors pb-1 cursor-pointer">
                Sobre
              </button>
            </nav>
          )}

          {/* Right Header Controls / Sign In or Sign Out buttons */}
          <div className="flex items-center gap-3">
            {/* Interactive collapsible search inside the header */}
            {currentView === 'landing' && (
              <div className="relative z-50">
                {isSearchOpen ? (
                  <div className="flex items-center bg-slate-100 hover:bg-slate-150 rounded-xl px-2.5 py-1.5 transition-all text-slate-800 w-44 sm:w-60 border border-slate-200">
                    <Search className="h-3.5 w-3.5 text-slate-400 shrink-0 mr-1.5" />
                    <input 
                      type="text"
                      placeholder="Buscar no portal..."
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      autoFocus
                      className="bg-transparent border-0 outline-none text-xs w-full font-medium text-slate-850"
                      onKeyDown={(e) => {
                        if (e.key === 'Escape') {
                          setIsSearchOpen(false);
                          setSearchQuery('');
                        }
                      }}
                    />
                    <button 
                      onClick={() => { setIsSearchOpen(false); setSearchQuery(''); }}
                      className="p-0.5 text-slate-400 hover:text-slate-850 rounded-full hover:bg-slate-200 cursor-pointer shrink-0"
                    >
                      <X className="h-3 w-3" />
                    </button>

                    {/* Search Results Dropdown Popover */}
                    {searchQuery.trim().length > 0 && (
                      <div className="absolute top-[calc(100%+8px)] right-0 w-80 bg-white border border-slate-250 shadow-xl rounded-2xl p-4.5 z-[100] text-slate-800 text-left space-y-3.5 animate-in fade-in slide-in-from-top-3 duration-205 max-h-[350px] overflow-y-auto">
                        <span className="text-[9px] uppercase font-bold text-slate-400 tracking-wider block">Resultados da Busca</span>
                        
                        {matchedCourses.length === 0 && matchedNews.length === 0 && (
                          <p className="text-xs text-slate-400 py-2">Nenhum resultado encontrado para "{searchQuery}"</p>
                        )}

                        {matchedCourses.length > 0 && (
                          <div className="space-y-1.5">
                            <span className="text-[9px] font-bold text-[#540D6E] block uppercase tracking-wide">Cursos ({matchedCourses.length})</span>
                            {matchedCourses.map((c, i) => (
                              <div 
                                key={i}
                                onClick={() => {
                                  handleSmoothScroll('cursos');
                                  setIsSearchOpen(false);
                                  setSearchQuery('');
                                  speakText(`Navegando para o curso ${c.title}`);
                                }}
                                className="hover:bg-slate-50 p-2 rounded-xl transition-all cursor-pointer border border-transparent hover:border-slate-100 flex items-center gap-2"
                              >
                                <div className="h-8 w-8 rounded-lg bg-[#540D6E]/5 text-[#540D6E] text-xs font-bold font-serif flex items-center justify-center shrink-0">
                                  C
                                </div>
                                <div className="min-w-0">
                                  <span className="block text-xs font-bold text-slate-800 truncate leading-snug">{c.title}</span>
                                  <span className="block text-[10px] text-slate-450 truncate">{c.category}</span>
                                </div>
                              </div>
                            ))}
                          </div>
                        )}

                        {matchedNews.length > 0 && (
                          <div className="space-y-1.5 pt-2 border-t border-slate-105">
                            <span className="text-[9px] font-bold text-[#EE4266] block uppercase tracking-wide">Notícias ({matchedNews.length})</span>
                            {matchedNews.map((n, i) => (
                              <div 
                                key={i}
                                onClick={() => {
                                  handleSmoothScroll('noticias');
                                  setIsSearchOpen(false);
                                  setSearchQuery('');
                                  speakText(`Navegando para notícia: ${n.title}`);
                                }}
                                className="hover:bg-slate-50 p-2 rounded-xl transition-all cursor-pointer border border-transparent hover:border-slate-100 flex items-center gap-2"
                              >
                                <div className="h-8 w-8 rounded-lg bg-[#EE4266]/5 text-[#EE4266] text-xs font-bold font-serif flex items-center justify-center shrink-0">
                                  N
                                </div>
                                <div className="min-w-0">
                                  <span className="block text-xs font-bold text-slate-800 truncate leading-snug">{n.title}</span>
                                  <span className="block text-[10px] text-slate-450 truncate">{n.tag}</span>
                                </div>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                ) : (
                  <div className="flex items-center gap-3 mr-1 text-slate-400">
                    <Search className="h-4.5 w-4.5 hover:text-slate-800 cursor-pointer transition-colors" title="Buscar no Site" onClick={() => setIsSearchOpen(true)} />
                  </div>
                )}
              </div>
            )}

            {currentView === 'active_app' ? (
              <div className="flex items-center gap-3">
                {/* Back to landing portal link (extremely robust navigational flow) */}
                <button
                  onClick={() => {
                    setCurrentView('landing');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    speakText("Retornando ao Portal Institucional.");
                  }}
                  className="rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-650 px-3 py-2 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-3xs"
                  title="Ir para a Página Inicial do Portal"
                >
                  <Home className="h-3.5 w-3.5 text-[#540D6E]" />
                  <span className="hidden sm:inline">Página Inicial</span>
                </button>

                {(activeUser.role === 'instructor' || (activeUser.role === 'student' && systemSettings.allowDirectMessages)) && (
                  <div className="relative">
                    {activeUser.role === 'instructor' ? (
                      unrepliedStudents.length > 0 ? (
                        <button 
                          type="button"
                          onClick={() => {
                            if (currentView !== 'active_app') {
                              setCurrentView('active_app');
                            }
                            setActiveDashboardTab('messages');
                            speakText("Carregando conversas pendentes dos estudantes.");
                            setTimeout(() => {
                              const chatSection = document.getElementById('chat-portal-section');
                              if (chatSection) {
                                chatSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                chatSection.classList.add('ring-4', 'ring-[#540D6E]/30');
                                setTimeout(() => {
                                  chatSection.classList.remove('ring-4', 'ring-[#540D6E]/30');
                                }, 2500);
                              }
                            }, 150);
                          }}
                          className="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-150 hover:border-rose-300 text-rose-600 flex items-center justify-center animate-pulse transition-all cursor-pointer relative"
                          title={`${unrepliedStudents.length} conversa(s) aguardando resposta dos alunos: ${unrepliedStudents.join(', ')}`}
                        >
                          <Bell className="h-4 w-4 text-rose-500 fill-rose-200 animate-bounce" />
                          <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-600 text-[9px] font-black text-white ring-2 ring-rose-100">
                            {unrepliedStudents.length}
                          </span>
                        </button>
                      ) : (
                        <button 
                          type="button"
                          onClick={() => {
                            if (currentView !== 'active_app') {
                              setCurrentView('active_app');
                            }
                            setActiveDashboardTab('messages');
                            speakText("Navegando até o portal de mensagens.");
                            setTimeout(() => {
                              const chatSection = document.getElementById('chat-portal-section');
                              if (chatSection) {
                                chatSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                chatSection.classList.add('ring-4', 'ring-[#540D6E]/20');
                                setTimeout(() => {
                                  chatSection.classList.remove('ring-4', 'ring-[#540D6E]/20');
                                }, 2500);
                              }
                            }, 150);
                          }}
                          className="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-all cursor-pointer"
                          title="Sem novas mensagens de alunos. Clique para acessar o canal."
                        >
                          <Bell className="h-4 w-4" />
                        </button>
                      )
                    ) : (
                      <button 
                        type="button"
                        onClick={() => {
                          if (currentView !== 'active_app') {
                            setCurrentView('active_app');
                          }
                          setActiveDashboardTab('messages');
                          speakText("Navegando até o seu portal de comunicação.");
                          setTimeout(() => {
                            const chatSection = document.getElementById('chat-portal-section');
                            if (chatSection) {
                              chatSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                              chatSection.classList.add('ring-4', 'ring-teal-500/25');
                              setTimeout(() => {
                                chatSection.classList.remove('ring-4', 'ring-teal-500/25');
                              }, 2500);
                            }
                          }, 150);
                        }}
                        className="p-2 rounded-xl bg-teal-50 hover:bg-teal-100 border border-teal-150 text-teal-600 hover:text-teal-800 flex items-center justify-center transition-all cursor-pointer"
                        title="Canal de Dúvidas e Feedbacks com os Professores"
                      >
                        <MessageSquare className="h-4 w-4 text-teal-600" />
                      </button>
                    )}
                  </div>
                )}

                <button
                  onClick={handleLogout}
                  className="rounded-lg border border-rose-150 bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-2 text-xs font-bold font-mono tracking-wider transition-all flex items-center gap-1.5 cursor-pointer shadow-3xs hover:border-rose-300 uppercase"
                  title="Sair do Portal e encerrar sessão"
                >
                  <LogOut className="h-3.5 w-3.5 text-rose-500" />
                  <span>Sair</span>
                </button>

                <div 
                  onClick={() => {
                    setPreviousView(currentView === 'perfil' ? previousView : currentView);
                    setCurrentView('perfil');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    speakText("Carregando o seu perfil.");
                  }}
                  className="hidden md:flex items-center gap-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-xl cursor-pointer transition-all"
                  title="Visualizar meu Perfil Actions"
                >
                  <div className="text-right">
                    <span className="block text-xs font-bold text-slate-800 leading-none">{activeUser.name}</span>
                    <span className="text-[9px] text-[#540D6E] font-bold block mt-0.5">
                      {activeUser.role === 'student' && 'Aluno Credenciado'}
                      {activeUser.role === 'instructor' && 'Gestor de Cursos'}
                      {activeUser.role === 'admin' && 'Moderação Coordenação'}
                    </span>
                  </div>
                  <span className={`h-2.5 w-2.5 rounded-full ${
                    activeUser.role === 'admin' ? 'bg-amber-400' : activeUser.role === 'instructor' ? 'bg-emerald-400' : 'bg-blue-500'
                  }`} />
                </div>
              </div>
            ) : (
              <div className="flex items-center gap-2">
                {/* Padlock and User icons (already interactive login triggers) */}
                {!isSearchOpen && (
                  <div className="hidden sm:flex items-center gap-3.5 mr-2 text-slate-400 border-r border-slate-200 pr-4">
                    <Lock className="h-4.5 w-4.5 hover:text-slate-800 cursor-pointer transition-colors" title="Simular Conexão" onClick={() => setIsLoginModalOpen(true)} />
                    <User 
                      className="h-4.5 w-4.5 hover:text-slate-800 cursor-pointer transition-colors" 
                      title={isUserLoggedIn ? "Visualizar meu Perfil" : "Identidade Aluno/Professor"} 
                      onClick={() => {
                        if (isUserLoggedIn) {
                          setPreviousView(currentView === 'perfil' ? previousView : currentView);
                          setCurrentView('perfil');
                          window.scrollTo({ top: 0, behavior: 'smooth' });
                          speakText("Carregando o seu perfil.");
                        } else {
                          setIsLoginModalOpen(true);
                        }
                      }} 
                    />
                  </div>
                )}

                {/* If the user is technically logged in but on the landing view, provide an instant "Ir p/ Painel" button */}
                {isUserLoggedIn ? (
                  <div className="flex items-center gap-2 animate-in fade-in transition-all">
                    <button
                      onClick={handleLogout}
                      className="rounded-lg border border-rose-150 bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-2 text-xs font-bold font-mono tracking-wider transition-all flex items-center gap-1.5 cursor-pointer shadow-3xs hover:border-rose-300 uppercase"
                      title="Sair do Portal e encerrar sessão"
                    >
                      <LogOut className="h-3.5 w-3.5 text-rose-500" />
                      <span>Sair</span>
                    </button>
                    {activeUser.role === 'student' ? (
                      <button 
                        onClick={() => {
                          setCurrentView('active_app');
                          window.scrollTo({ top: 0, behavior: 'smooth' });
                          speakText("Acessando o seu Ambiente de Estudos.");
                        }}
                        className="rounded-lg bg-[#FFD23F] hover:bg-amber-400 text-slate-900 border border-amber-300 font-extrabold px-3.5 py-2 text-xs uppercase tracking-wider transition-all cursor-pointer shadow-3xs flex items-center gap-1.5"
                        title="Ir para seu Ambiente de Estudos"
                      >
                        <BookOpen className="h-4 w-4 text-slate-900 shrink-0" />
                        <span className="hidden sm:inline">Ambiente de Estudos</span>
                        <span className="inline sm:hidden">Estudos</span>
                      </button>
                    ) : (
                      <button 
                        onClick={() => {
                          setCurrentView('active_app');
                          window.scrollTo({ top: 0, behavior: 'smooth' });
                          if (activeUser.role === 'admin') {
                            speakText("Acessando a sua Gestão da Plataforma.");
                          } else {
                            speakText("Acessando a sua Gestão de Cursos.");
                          }
                        }}
                        className="rounded-lg bg-[#FFD23F] hover:bg-amber-400 text-slate-900 border border-amber-300 font-extrabold px-3.5 py-2 text-xs uppercase tracking-wider transition-all cursor-pointer shadow-3xs flex items-center gap-1.5"
                        title={activeUser.role === 'admin' ? "Acessar Coordenação / Gestão da Plataforma" : "Acessar Gestão de Cursos e Conteúdos"}
                      >
                        <GraduationCap className="h-4 w-4 text-slate-900 shrink-0" />
                        <span className="hidden sm:inline">
                          {activeUser.role === 'admin' ? "Gestão da Plataforma" : "Gestão de Cursos/Alunos"}
                        </span>
                        <span className="inline sm:hidden">
                          {activeUser.role === 'admin' ? "Plataforma" : "Gestão"}
                        </span>
                      </button>
                    )}
                  </div>
                ) : (
                  <>
                    <button 
                      onClick={() => setIsLoginModalOpen(true)}
                      className="rounded-lg border border-[#540D6E] bg-transparent hover:bg-blue-50 text-[#540D6E] px-4.5 py-2 text-xs font-bold uppercase tracking-wider transition-all cursor-pointer"
                    >
                      Entrar
                    </button>
                    <button 
                      onClick={() => {
                        setIsRegisterModalOpen(true);
                        speakText("Portal de direcionamento e validação de cadastro externo aberto.");
                      }}
                      className="rounded-lg bg-[#540D6E] hover:bg-blue-700 text-white px-4.5 py-2 text-xs font-bold uppercase tracking-wider transition-all cursor-pointer shadow-xs"
                    >
                      Cadastre-se
                    </button>
                  </>
                )}
              </div>
            )}

            {/* Mobile Hamburger Menu Toggle Button (shown only on md/sm screens when in landing) */}
            {currentView === 'landing' && (
              <button
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                className="lg:hidden p-2 text-slate-650 hover:text-[#540D6E] hover:bg-slate-100 rounded-lg transition-colors cursor-pointer shrink-0 ml-1"
                aria-label="Abrir menu de navegação"
                title="Menu de Seções"
              >
                <Menu className="h-5 w-5" />
              </button>
            )}
          </div>

        </div>

        {/* Mobile Navigation Dropdown Box */}
        <AnimatePresence>
          {isMobileMenuOpen && currentView === 'landing' && (
            <motion.div 
              initial={{ height: 0, opacity: 0 }}
              animate={{ height: 'auto', opacity: 1 }}
              exit={{ height: 0, opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="lg:hidden bg-white border-t border-slate-150 shadow-lg overflow-hidden"
            >
              <div className="px-5 py-4.5 flex flex-col gap-3.5 text-left text-xs font-bold text-slate-650 uppercase tracking-widest bg-slate-50/50">
                <button 
                  onClick={() => { 
                    window.scrollTo({ top: 0, behavior: 'smooth' }); 
                    speakText("Voltando para o topo da Página Inicial.");
                    setIsMobileMenuOpen(false); 
                  }} 
                  className="hover:text-[#540D6E] text-left transition-colors cursor-pointer py-1 block"
                >
                  Página Inicial
                </button>
                <button 
                  onClick={() => { 
                    handleSmoothScroll('cursos'); 
                    setIsMobileMenuOpen(false); 
                    speakText("Cursos");
                  }} 
                  className="hover:text-[#540D6E] text-left transition-colors cursor-pointer py-1 flex items-center gap-2"
                >
                  <span>Cursos</span>
                  <span className="bg-[#FFD23F] text-[8px] font-mono tracking-normal font-extrabold px-1.5 py-0.2 rounded-full text-slate-950">NOVO!</span>
                </button>
                <button 
                  onClick={() => { 
                    handleSmoothScroll('noticias'); 
                    setIsMobileMenuOpen(false); 
                    speakText("Notícias");
                  }} 
                  className="hover:text-[#540D6E] text-left transition-colors cursor-pointer py-1 block"
                >
                  Notícias
                </button>
                <button 
                  onClick={() => { 
                    handleSmoothScroll('duvidas'); 
                    setIsMobileMenuOpen(false); 
                    speakText("Dúvidas");
                  }} 
                  className="hover:text-[#540D6E] text-left transition-colors cursor-pointer py-1 block"
                >
                  Dúvidas
                </button>
                <button 
                  onClick={() => { 
                    handleSmoothScroll('quem-somos'); 
                    setIsMobileMenuOpen(false); 
                    speakText("Sobre");
                  }} 
                  className="hover:text-[#540D6E] text-left transition-colors cursor-pointer py-1 block"
                >
                  Sobre
                </button>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </header>

      {/* Main Content Body Routing */}
      <main className="flex-1">
        {currentView === 'landing' ? (
          /* COMPLETE 9-SECTION LANDING PAGE */
          <div className="animate-in fade-in duration-300">
            
            {/* HERO SECTION 1 & 2: Epic Cultural Banner & Character Geometric Graphics (Image 1 & 2) */}
            <section 
              id="hero-section" 
              ref={heroRef}
              onMouseMove={handleHeroMouseMove}
              onMouseEnter={() => setIsMouseInHero(true)}
              onMouseLeave={() => setIsMouseInHero(false)}
              className="bg-[#540D6E] text-white py-14 lg:py-24 relative overflow-hidden flex flex-col items-center"
            >
              {/* Ambient Mouse-following background glow */}
              <motion.div
                className="absolute top-0 left-0 pointer-events-none z-0"
                animate={{
                  x: mousePos.x,
                  y: mousePos.y,
                  opacity: isMouseInHero ? 1 : 0,
                }}
                transition={{ type: 'spring', damping: 35, stiffness: 100, mass: 0.6 }}
              >
                <div className="h-80 w-80 -translate-x-1/2 -translate-y-1/2 bg-[#FFD23F]/12 rounded-full blur-3xl" />
              </motion.div>

              {/* Multi-particle trailing effect - Multiple small yellow balls trailing with spring physics */}
              {[
                { id: 1, size: 7, stiffness: 220, damping: 22, mass: 0.2, maxOpacity: 0.95 },
                { id: 2, size: 11, stiffness: 180, damping: 24, mass: 0.4, maxOpacity: 0.95 },
                { id: 3, size: 8, stiffness: 150, damping: 26, mass: 0.6, maxOpacity: 0.9 },
                { id: 4, size: 12, stiffness: 120, damping: 28, mass: 0.8, maxOpacity: 0.85 },
                { id: 5, size: 5, stiffness: 100, damping: 30, mass: 1.0, maxOpacity: 0.8 },
                { id: 6, size: 9, stiffness: 85, damping: 32, mass: 1.2, maxOpacity: 0.7 },
                { id: 7, size: 6, stiffness: 70, damping: 34, mass: 1.4, maxOpacity: 0.6 },
                { id: 8, size: 10, stiffness: 60, damping: 36, mass: 1.6, maxOpacity: 0.5 },
                { id: 9, size: 4, stiffness: 50, damping: 38, mass: 1.8, maxOpacity: 0.4 },
                { id: 10, size: 8, stiffness: 40, damping: 40, mass: 2.0, maxOpacity: 0.3 },
              ].map((p) => (
                <motion.div
                  key={p.id}
                  className="absolute top-0 left-0 pointer-events-none z-10"
                  animate={{
                    x: mousePos.x,
                    y: mousePos.y,
                    opacity: isMouseInHero ? p.maxOpacity : 0,
                    scale: isMouseInHero ? 1 : 0,
                  }}
                  transition={{ 
                    x: { type: 'spring', stiffness: p.stiffness, damping: p.damping, mass: p.mass },
                    y: { type: 'spring', stiffness: p.stiffness, damping: p.damping, mass: p.mass },
                    opacity: { duration: 0.3 },
                    scale: { duration: 0.3 }
                  }}
                  style={{
                    width: p.size,
                    height: p.size,
                    borderRadius: '50%',
                    backgroundColor: '#FFD23F',
                    boxShadow: '0 0 10px #FFD23F, 0 0 4px #FFD23F',
                    marginLeft: -p.size / 2,
                    marginTop: -p.size / 2,
                  }}
                />
              ))}

              {/* Decorative design layers */}
              <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-white/[0.04] rounded-full filter blur-3xl pointer-events-none" />
              <div className="absolute bottom-0 left-0 w-[400px] h-[400px] bg-[#FFD23F]/[0.05] rounded-full filter blur-2xl pointer-events-none" />
              
              <div className="mx-auto max-w-7xl px-4 md:px-8 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center text-left w-full">
                
                {/* Hero Left Content */}
                <div className="lg:col-span-7 space-y-6 relative z-10">
                  <div className="inline-flex items-center gap-2 rounded-full bg-white/10 border border-white/20 text-[#FFD23F] text-[9px] uppercase tracking-widest font-extrabold px-3.5 py-1.5 font-mono">
                    <Sparkles className="h-3 w-3 animate-spin duration-1000" />
                    <span>{translations[currentLang].heroBadge}</span>
                  </div>

                  <h2 className="text-3xl md:text-5xl lg:text-[54px] font-extrabold tracking-tight leading-[1.08] font-serif">
                    {translations[currentLang].heroTitleLine1} <br className="hidden sm:inline" />
                    <span className="text-[#FFD23F]">{translations[currentLang].heroTitleLine2}</span>
                  </h2>

                  <p className="text-slate-100 text-sm md:text-base leading-relaxed max-w-2xl font-light">
                    {translations[currentLang].heroDesc}
                  </p>

                  <div className="flex flex-col sm:flex-row gap-3 pt-2">
                    <button 
                      onClick={() => { handleSmoothScroll('cursos'); speakText(translations[currentLang].btnDiscover); }}
                      className="rounded-full bg-[#EE4266] hover:bg-red-700 text-white px-7 py-3 text-xs font-black uppercase tracking-widest transition-all cursor-pointer flex items-center justify-center gap-2 group shadow-lg"
                    >
                      <span>{translations[currentLang].btnDiscover}</span>
                      <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                    </button>
                    <button 
                      onClick={() => { setIsLoginModalOpen(true); speakText(translations[currentLang].btnStart); }}
                      className="rounded-full border-2 border-[#FFD23F] bg-transparent hover:bg-[#FFD23F]/10 text-white px-7 py-3 text-xs font-black uppercase tracking-widest transition-all cursor-pointer flex items-center justify-center gap-2 shadow-md"
                    >
                      <Star className="h-4 w-4 text-[#FFD23F] fill-[#FFD23F]" />
                      <span>{translations[currentLang].btnStart}</span>
                    </button>
                  </div>
                </div>

                {/* Hero Right Content: High-fidelity image and custom abstract vector graphics wrapper (Image 2) */}
                <div className="lg:col-span-5 relative flex items-center justify-center">
                  <div className="relative w-80 h-80 sm:w-96 sm:h-96 shrink-0 z-10 flex items-center justify-center">
                    
                    {/* Creative colorful concentric vectors matching AVASEC artwork */}
                    <div className="absolute top-4 left-4 w-full h-full border-4 border-[#3BCEAC]/30 rounded-full pointer-events-none" />
                    <div className="absolute -bottom-2 -right-2 w-[110%] h-[110%] border-2 border-dashed border-[#FFD23F]/25 rounded-full pointer-events-none animate-spin" style={{ animationDuration: '40s' }} />
                    
                    {/* Multicolored shape geometries surrounding head */}
                    <div className="absolute -top-6 -left-6 w-24 h-24 rounded-full bg-[#FFD23F] opacity-85 z-20 flex items-center justify-center font-bold text-slate-900 text-[10px] uppercase tracking-wider shadow-md">
                      ✦ Criatividade
                    </div>
                    <div className="absolute -bottom-4 right-10 w-20 h-20 rounded-xl bg-[#EE4266] opacity-85 z-20 flex items-center justify-center text-white text-[10px] uppercase tracking-wider rotate-12 shadow-md">
                      ▲ Inovação
                    </div>
                    <div className="absolute -right-6 top-1/4 w-16 h-16 rounded-full bg-[#3BCEAC] opacity-90 z-20 flex items-center justify-center text-white text-[10px] rotate-45 shadow-md">
                      ● Arte
                    </div>

                    {/* Central Image container representing the cultural leader portrait */}
                    <div className="w-72 h-72 sm:w-85 sm:h-85 rounded-full overflow-hidden border-8 border-white shadow-2xl relative z-10 bg-slate-900">
                      <img 
                        src={pauloFreirePortrait} 
                        alt="Paulo Freire Tribute Portrait" 
                        className="w-full h-full object-cover filter contrast-110 sepia-[10%] saturate-120"
                        referrerPolicy="no-referrer"
                      />
                      {/* Geometric grid overlay mimicking the grid art style in Solano tribute face */}
                      <div className="absolute inset-0 bg-gradient-to-t from-blue-900/60 to-transparent mix-blend-multiply" />
                    </div>

                  </div>
                </div>

              </div>
            </section>

            {/* CURRICULAR MODAL MODES: Benefits & Pillars of Free Courses */}
            <section className="bg-slate-50 py-16 px-4 border-b border-slate-150">
              <div className="mx-auto max-w-7xl text-center space-y-12">
                <div className="space-y-3">
                  <span className="text-[10px] font-extrabold text-[#540D6E] uppercase tracking-widest block font-mono">
                    {translations[currentLang].gradeTitle}
                  </span>
                  <h3 className="text-2xl md:text-3.5xl font-black text-slate-900 uppercase tracking-tight font-serif text-slate-900">
                    {translations[currentLang].gradeSubtitle}
                  </h3>
                  <div className="h-1.5 w-16 bg-[#540D6E] mx-auto rounded-full" />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5.5xl mx-auto">
                  
                  {/* Card 1: Alta Aplicabilidade */}
                  <div className="bg-white rounded-3xl overflow-hidden shadow-sm hover:shadow-xl transition-all border border-slate-100 flex flex-col justify-between group">
                    <div className="arch-card-[#540D6E] h-36 bg-[#540D6E]/5 relative flex items-end justify-center pb-6">
                      <div className="absolute -bottom-10 h-20 w-20 rounded-full bg-[#EE4266] border-4 border-white flex items-center justify-center text-white shadow-md">
                        <Activity className="h-8 w-8 text-white" />
                      </div>
                    </div>
                    <div className="p-6 pt-14 space-y-4 text-center flex-1 flex flex-col justify-between">
                      <div className="space-y-2">
                        <h4 className="text-xl font-extrabold text-slate-900 font-serif">
                          {translations[currentLang].gradeCard1}
                        </h4>
                        <p className="text-xs text-slate-500 leading-relaxed">
                          {translations[currentLang].gradeCard1Desc}
                        </p>
                      </div>
                      <button 
                        onClick={() => handleSmoothScroll('cursos')} 
                        className="w-full mt-4 py-2.5 rounded-xl border border-[#540D6E] hover:bg-[#540D6E] hover:text-white text-[#540D6E] text-xs font-black uppercase tracking-wider transition-all cursor-pointer"
                      >
                        {currentLang === 'pt' ? 'Conhecer' : currentLang === 'en' ? 'Learn More' : 'Conocer'}
                      </button>
                    </div>
                  </div>

                  {/* Card 2: Certificação Gratuita */}
                  <div className="bg-white rounded-3xl overflow-hidden shadow-sm hover:shadow-xl transition-all border border-slate-100 flex flex-col justify-between group">
                    <div className="arch-card-[#3BCEAC] h-36 bg-[#3BCEAC]/5 relative flex items-end justify-center pb-6">
                      <div className="absolute -bottom-10 h-20 w-20 rounded-full bg-[#540D6E] border-4 border-white flex items-center justify-center text-white shadow-md">
                        <Award className="h-8 w-8 text-white" />
                      </div>
                    </div>
                    <div className="p-6 pt-14 space-y-4 text-center flex-1 flex flex-col justify-between">
                      <div className="space-y-2">
                        <h4 className="text-xl font-extrabold text-slate-900 font-serif">
                          {translations[currentLang].gradeCard2}
                        </h4>
                        <p className="text-xs text-slate-500 leading-relaxed">
                          {translations[currentLang].gradeCard2Desc}
                        </p>
                      </div>
                      <button 
                        onClick={() => handleSmoothScroll('cursos')} 
                        className="w-full mt-4 py-2.5 rounded-xl border border-[#3BCEAC] hover:bg-[#3BCEAC] hover:text-white text-[#3BCEAC] text-xs font-black uppercase tracking-wider transition-all cursor-pointer relative"
                      >
                        {currentLang === 'pt' ? 'Conhecer' : currentLang === 'en' ? 'Learn More' : 'Conocer'}
                      </button>
                    </div>
                  </div>

                  {/* Card 3: Ritmo Flexível */}
                  <div className="bg-white rounded-3xl overflow-hidden shadow-sm hover:shadow-xl transition-all border border-slate-100 flex flex-col justify-between group">
                    <div className="arch-card-[#FFD23F] h-36 bg-[#FFD23F]/5 relative flex items-end justify-center pb-6">
                      <div className="absolute -bottom-10 h-20 w-20 rounded-full bg-[#FFD23F] border-4 border-white flex items-center justify-center text-slate-900 shadow-md">
                        <BookOpen className="h-8 w-8 text-slate-900" />
                      </div>
                    </div>
                    <div className="p-6 pt-14 space-y-4 text-center flex-1 flex flex-col justify-between">
                      <div className="space-y-2">
                        <h4 className="text-xl font-extrabold text-slate-900 font-serif">
                          {translations[currentLang].gradeCard3}
                        </h4>
                        <p className="text-xs text-slate-500 leading-relaxed">
                          {translations[currentLang].gradeCard3Desc}
                        </p>
                      </div>
                      <button 
                        onClick={() => handleSmoothScroll('cursos')} 
                        className="w-full mt-4 py-2.5 rounded-xl border border-[#EE4266] hover:bg-[#EE4266] hover:text-white text-[#EE4266] text-xs font-black uppercase tracking-wider transition-all cursor-pointer"
                      >
                        {currentLang === 'pt' ? 'Conhecer' : currentLang === 'en' ? 'Learn More' : 'Conocer'}
                      </button>
                    </div>
                  </div>

                </div>
              </div>
            </section>

            {/* STATS SECTION: Escult em Dados (Image 4) */}
            <section className="bg-white py-16 px-4 overflow-hidden relative border-y border-slate-100">
              <div className="mx-auto max-w-7xl grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                {/* Stats Text & Indicators (Left) */}
                <div className="lg:col-span-6 space-y-6 text-left">
                  <div className="space-y-2">
                    <h3 className="text-3xl font-black text-slate-900 tracking-tight font-serif flex items-center gap-1.5">
                      <span>AVASEC em dados</span>
                    </h3>
                    <div className="h-1.5 w-12 bg-[#540D6E] rounded-full" />
                  </div>

                  <p className="text-xs md:text-[13px] text-slate-500 leading-relaxed font-light">
                    A Escola da Cultura de Cultura e Economia Criativa foi lançada em janeiro de 2024 e é promovida pela Diretoria de Políticas para Trabalhadores da Cultura e da Economia Criativa por meio da Coordenação de Capacitação e Qualificação Profissional. Veja aqui os resultados já alcançados de nossa rede:
                  </p>

                  <div className="grid grid-cols-2 gap-6 pt-4">
                    <div className="p-5 bg-blue-50/50 border border-blue-100 rounded-2xl">
                      <strong className="text-3xl font-black text-[#540D6E] font-mono block">188K</strong>
                      <span className="text-[10px] text-slate-500 font-extrabold uppercase mt-1 block">Estudantes cadastrados</span>
                    </div>
                    <div className="p-5 bg-green-50/50 border border-green-100 rounded-2xl">
                      <strong className="text-3xl font-black text-[#3BCEAC] font-mono block">300K</strong>
                      <span className="text-[10px] text-slate-500 font-extrabold uppercase mt-1 block">Inscrições nos cursos</span>
                    </div>
                    <div className="p-5 bg-red-50/50 border border-red-100 rounded-2xl">
                      <strong className="text-3xl font-black text-[#EE4266] font-mono block">66K</strong>
                      <span className="text-[10px] text-slate-500 font-extrabold uppercase mt-1 block">Mil concluintes</span>
                    </div>
                    <div className="p-5 bg-amber-50/50 border border-amber-105 rounded-2xl">
                      <strong className="text-3xl font-black text-amber-500 font-mono block">4M+</strong>
                      <span className="text-[10px] text-slate-500 font-extrabold uppercase mt-1 block">Visitas à plataforma</span>
                    </div>
                  </div>
                </div>

                {/* Arts Showcase (Right) featuring traditional circus performer and graphic alignments */}
                <div className="lg:col-span-6 relative flex justify-center items-center">
                  <div className="relative w-full max-w-md h-96 bg-slate-50 rounded-3xl overflow-hidden border border-slate-200/60 p-6 flex flex-col justify-end">
                    
                    {/* Abstract colorful design backgrounds replicating the image */}
                    <div className="absolute top-10 left-10 w-28 h-28 rounded-full bg-[#FFD23F] opacity-90 mix-blend-multiply filter blur-xs pointer-events-none" />
                    <div className="absolute top-4 right-10 w-24 h-24 rounded-full bg-[#3BCEAC] opacity-80 mix-blend-multiply filter blur-3xs pointer-events-none" />
                    
                    {/* Rainbow arc representation */}
                    <svg className="absolute right-0 bottom-0 w-48 h-48 opacity-20 pointer-events-none" viewBox="0 0 100 100">
                      <circle cx="100" cy="100" r="80" fill="none" stroke="#EE4266" strokeWidth="12" />
                      <circle cx="100" cy="100" r="60" fill="none" stroke="#FFD23F" strokeWidth="12" />
                      <circle cx="100" cy="100" r="40" fill="none" stroke="#3BCEAC" strokeWidth="12" />
                      <circle cx="100" cy="100" r="20" fill="none" stroke="#540D6E" strokeWidth="12" />
                    </svg>

                    {/* Grayscale/colored performer image simulating theater circus characters */}
                    <div className="absolute inset-0 flex items-center justify-center p-4">
                      <img 
                        src="https://images.unsplash.com/photo-1514306191717-452ec28c7814?w=600&auto=format&fit=crop&q=70" 
                        alt="Prática de Educação Libertadora Paulo Freire" 
                        className="max-h-80 object-contain rounded-2xl hover:scale-105 transition-transform duration-500"
                        referrerPolicy="no-referrer"
                      />
                    </div>

                    <div className="bg-slate-900/90 text-white p-4.5 rounded-2xl relative z-10 text-left space-y-1">
                      <span className="text-[9px] uppercase tracking-widest text-[#FFD23F] font-bold">Patrimônio Vivo</span>
                      <strong className="text-xs font-bold block">Fazer Artístico Decolonial</strong>
                      <p className="text-[10px] text-slate-300">Oficinas ministradas de maneira autônoma com apoio das comunidades locais e certificadas em nossa rede.</p>
                    </div>

                  </div>
                </div>

              </div>
            </section>

            {/* FEATURED COURSES SECTION: Image 5 Grid Slider */}
            <section id="cursos" className="bg-slate-50 py-16 px-4">
              <div className="mx-auto max-w-7xl space-y-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-6">
                  <div className="text-left space-y-1">
                    <span className="text-[10px] font-extrabold text-[#540D6E] uppercase tracking-widest block font-mono">Destaques Letivos</span>
                    <h3 className="text-2xl md:text-3.5xl font-black text-slate-900 uppercase tracking-tight font-serif">Cursos em Destaque</h3>
                  </div>

                  {/* Navigation controls next to titles */}
                  <div className="flex gap-2.5 items-center self-stretch sm:self-auto justify-between w-full sm:w-auto">
                    <button 
                      onClick={() => handleSmoothScroll('cursos')}
                      className="rounded-full border border-slate-300 bg-white hover:bg-slate-100 px-4 py-2 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-3xs text-slate-700"
                    >
                      <span>Ver todos os cursos</span>
                    </button>
                    <div className="flex gap-1.5">
                      <button 
                        onClick={handleCoursePrev}
                        className="h-9 w-9 rounded-full bg-white border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-700 transition-colors shadow-3xs cursor-pointer"
                        title="Anterior"
                      >
                        <ChevronLeft className="h-5 w-5" />
                      </button>
                      <button 
                        onClick={handleCourseNext}
                        className="h-9 w-9 rounded-full bg-white border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-700 transition-colors shadow-3xs cursor-pointer"
                        title="Próximo"
                      >
                        <ChevronRight className="h-5 w-5" />
                      </button>
                    </div>
                  </div>
                </div>

                {/* Grid slider list containing real items and custom primary badge elements */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-left">
                  {(() => {
                    const displayedCourses = searchQuery.trim() === '' ? featuredCoursesData : matchedCourses;
                    if (displayedCourses.length === 0) {
                      return (
                        <div className="col-span-full rounded-2xl border border-dashed border-slate-300 p-12 text-center bg-white shadow-3xs">
                          <h4 className="text-[#540D6E] font-black text-sm uppercase tracking-wider mb-2 font-mono">Sem Resultados</h4>
                          <p className="text-slate-500 text-xs leading-relaxed max-w-md mx-auto">Nenhum curso em destaque coincide com a sua busca por "<strong className="text-slate-800 font-bold">{searchQuery}</strong>".</p>
                          <button 
                            onClick={() => setSearchQuery('')}
                            className="mt-4 px-4 py-2 bg-[#540D6E] text-white text-xs font-bold rounded-xl hover:bg-purple-950 transition-colors uppercase tracking-wider cursor-pointer font-sans"
                          >
                            Limpar Filtro de Busca
                          </button>
                        </div>
                      );
                    }
                    return displayedCourses.map((course, idx) => {
                      const isFocus = idx === (activeCourseIndex % displayedCourses.length);

                      return (
                        <div 
                          key={`${course.title}-${idx}`}
                          className={`bg-white rounded-2xl border transition-all duration-300 flex flex-col justify-between overflow-hidden shadow-2xs hover:shadow-md ${
                            isFocus ? 'border-[#540D6E] ring-1.5 ring-[#540D6E]' : 'border-slate-200'
                          }`}
                        >
                        <div className="h-44 overflow-hidden relative bg-slate-800">
                          <img 
                            src={course.image} 
                            alt={course.title} 
                            className="w-full h-full object-cover filter grayscale contrast-125 saturate-50 hover:grayscale-0 transition-all duration-500"
                            referrerPolicy="no-referrer"
                          />
                          <div className="absolute inset-0 bg-gradient-to-t from-slate-950/80 to-transparent" />
                          
                          {/* Colored circular overlay badge representing icons (mic/camera/columns/parliament) in upper section */}
                          <div className="absolute top-4 right-4 h-11 w-11 rounded-xl bg-slate-900/90 text-white flex items-center justify-center shadow-md">
                            {course.iconType === 'mic' && <Award className="h-5 w-5 text-[#FFD23F]" />}
                            {course.iconType === 'video' && <Video className="h-5 w-5 text-[#FFD23F]" />}
                            {course.iconType === 'building' && <Play className="h-5 w-5 text-[#FFD23F] translate-x-[1px]" />}
                            {course.iconType === 'columns' && <BookOpen className="h-5 w-5 text-[#FFD23F]" />}
                          </div>

                          <span className="absolute bottom-3 left-3 text-[9px] font-black uppercase tracking-widest bg-white/10 backdrop-blur-md text-white border border-white/20 py-0.8 px-2 rounded-md">
                            {course.category}
                          </span>
                        </div>

                        <div className="p-4.5 space-y-3 flex-1 flex flex-col justify-between">
                          <div className="space-y-1.5">
                            <h4 className="text-xs font-bold text-slate-900 leading-snug group-hover:text-[#540D6E] transition-colors line-clamp-2 h-9 font-serif">
                              {course.title}
                            </h4>
                            <p className="text-[10px] text-slate-400 font-semibold">Tutorado por: Prof. {course.instructor}</p>
                            <p className="text-[10.5px] text-slate-500 leading-relaxed font-light line-clamp-3">
                              {course.description}
                            </p>
                          </div>

                          <button 
                            onClick={() => setIsLoginModalOpen(true)}
                            className="w-full text-center mt-3 py-2 rounded-xl bg-slate-50 hover:bg-[#540D6E] hover:text-white transition-all text-slate-600 border border-slate-150 text-[10.5px] font-black uppercase tracking-wider cursor-pointer flex items-center justify-center gap-1"
                          >
                            <span>Increver-se</span>
                            <ArrowRight className="h-3 w-3" />
                          </button>
                        </div>
                      </div>
                    );
                  });
                })()}
                </div>
              </div>
            </section>

            {/* COURSE CATEGORIES SECTION: Image 6 Vectors */}
            <section className="bg-white py-16 px-4">
              <div className="mx-auto max-w-7xl text-center space-y-12">
                
                <div className="space-y-3 max-w-2xl mx-auto">
                  <span className="text-[10px] font-extrabold text-[#540D6E] uppercase tracking-widest block font-mono">Nossos Quadrantes</span>
                  <h3 className="text-2xl md:text-3.5xl font-black text-slate-900 uppercase tracking-tight font-serif">Categorias de Cursos</h3>
                  <p className="text-xs md:text-[11px] text-slate-500 leading-relaxed">
                    Explore um universo de possibilidades com as categorias de cursos que oferecemos. Cada categoria é uma porta de entrada para novos conhecimentos e habilidades, refletindo a diversidade e a riqueza das artes e da cultura.
                  </p>
                  <div className="h-1.5 w-16 bg-[#EE4266] mx-auto rounded-full mt-2" />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto text-center">
                  {categoriesData.map((cat) => (
                    <div 
                      key={cat.title}
                      className="bg-slate-50/50 hover:bg-slate-50 border border-slate-200/60 hover:border-slate-300 p-6.5 rounded-3xl transition-all hover:shadow-xs flex flex-col justify-between"
                    >
                      <div className="space-y-4">
                        <div className="transform hover:scale-105 transition-transform">
                          {cat.icon}
                        </div>
                        <h4 className="text-[15px] font-extrabold text-[#111] font-serif">{cat.title}</h4>
                        <p className="text-[10.5px] text-slate-500 leading-relaxed font-light">{cat.description}</p>
                      </div>
                      
                      <div className="mt-6 pt-4 border-t border-slate-150 flex justify-between items-center text-[10px] font-mono font-bold text-slate-450 uppercase">
                        <span>{cat.coursesCount} disciplinas</span>
                        <span className="text-[#540D6E] hover:underline cursor-pointer flex items-center gap-0.5" onClick={() => setIsLoginModalOpen(true)}>
                          Ver ➔
                        </span>
                      </div>
                    </div>
                  ))}
                </div>

              </div>
            </section>

            {/* SUGGESTION BANNER & MEMORIAL SECTION: Celebrating Solano Trindade (Image 7) */}
            <section className="bg-[#FFD23F] py-4 px-4 text-center">
              <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 font-sans py-2">
                <div className="flex items-center gap-3">
                  <div className="h-10 w-10 rounded-full bg-slate-905 text-white flex items-center justify-center shrink-0">
                    <HelpCircle className="h-5 w-5 text-amber-300" />
                  </div>
                  <div className="text-left leading-tight">
                    <strong className="text-slate-950 font-sans tracking-wide font-black block">Sentiu falta de algum curso?</strong>
                    <span className="text-xs text-slate-800 font-medium">ENVIE A SUA SUGESTÃO PARA NÓS!</span>
                  </div>
                </div>

                <form onSubmit={handleSubmitSuggestion} className="flex gap-2 w-full md:w-auto shrink-0">
                  <input
                    type="text"
                    required
                    maxLength={100}
                    placeholder="Sugira um tema ou trilha..."
                    value={suggestedCourseName}
                    onChange={(e) => setSuggestedCourseName(e.target.value)}
                    className="bg-white/90 border border-amber-500/30 text-slate-900 rounded-lg px-4.5 py-2.5 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-[#540D6E]"
                  />
                  <button 
                    type="submit"
                    className="rounded-lg bg-[#540D6E] hover:bg-blue-700 text-white font-extrabold text-xs px-5 py-2.5 uppercase tracking-wider transition-colors shadow-xs shrink-0 cursor-pointer"
                  >
                    {suggestionSubmitted ? 'Enviado!' : 'Sugerir'}
                  </button>
                </form>
              </div>
            </section>

            {/* Paulo Freire Tribute Section */}
            <section id="quem-somos" className="bg-[#111622] text-white py-16 px-4">
              <div className="mx-auto max-w-7xl grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                {/* Paulo Freire portrait oval container (Left) */}
                <div className="lg:col-span-6 flex justify-center">
                  <div className="relative w-80 h-96 sm:w-96 sm:h-[450px] shrink-0">
                    
                    {/* Modernist borders representing educational/theoretical grids */}
                    <div className="absolute inset-0 border-2 border-dashed border-[#EE4266]/30 rounded-[100px] transform rotate-3 pointer-events-none" />
                    <div className="absolute inset-2 border-2 border-dashed border-[#FFD23F]/25 rounded-[120px] transform -rotate-3 pointer-events-none" />

                    {/* Background colorful elements of face collage overlay */}
                    <div className="absolute inset-0 bg-blue-900/10 rounded-[120px] scale-95" />

                    {/* Oval container with face vector alignment matching Paulo Freire */}
                    <div className="w-full h-full rounded-[120px] overflow-hidden border-4 border-slate-800 shadow-2xl relative bg-slate-950">
                      <img 
                        src={pauloFreirePortrait} 
                        alt="Paulo Freire Tribute Oval" 
                        className="w-full h-full object-cover filter contrast-125 brightness-100"
                        referrerPolicy="no-referrer"
                      />
                      
                      {/* CSS Abstract overlay grid lines represent colorful geometric blocking of face */}
                      <div className="absolute inset-0 grid grid-cols-3 grid-rows-3 opacity-20 mix-blend-color-burn">
                        <div className="bg-[#EE4266]" />
                        <div className="bg-[#540D6E]" />
                        <div className="bg-[#3BCEAC]" />
                        <div className="bg-[#FFD23F]" />
                        <div className="bg-[#540D6E]" />
                        <div className="bg-[#EE4266]" />
                      </div>
                      
                      <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent" />
                      
                      {/* Name Card */}
                      <div className="absolute bottom-10 left-6 right-6 text-center space-y-1">
                        <strong className="text-lg font-black tracking-tight text-[#FFD23F]">Paulo Freire</strong>
                        <span className="text-[10px] uppercase font-mono tracking-widest text-slate-300 block">1921 — 1997</span>
                      </div>
                    </div>

                  </div>
                </div>

                {/* Tribute Narrative (Right) */}
                <div className="lg:col-span-6 space-y-6 text-left">
                  <div className="space-y-2">
                    <span className="text-[10px] font-extrabold text-[#FFD23F] uppercase tracking-widest block font-mono">Nosso Grande Patrono</span>
                    <h3 className="text-3xl md:text-3.5xl font-black uppercase tracking-tight font-serif text-[#FFD23F]">Celebrando Paulo Freire</h3>
                    <p className="text-slate-400 font-sans tracking-wide text-xs">Patrono da Educação Brasileira</p>
                    <div className="h-1 w-20 bg-[#EE4266] mt-2" />
                  </div>

                  <p className="text-slate-300 text-xs md:text-[12.5px] leading-relaxed font-light">
                    Paulo Freire, um dos pensadores mais célebres e influentes da história da pedagogia mundial, dedicou sua trajetória a construir uma educação conscientizadora e dialógica. Defendendo que "a educação é a prática da liberdade", seu inovador método de alfabetização partia do contexto e do vocabulário do próprio educando, capacitando os sujeitos a ler a palavra e, de forma reflexiva, reler e transformar o próprio mundo.
                  </p>

                  <div className="space-y-3.5 pt-2">
                    <div className="flex gap-3">
                      <span className="text-[#EE4266] text-xs mt-0.5">●</span>
                      <div>
                        <strong className="text-xs text-white uppercase block tracking-wider">Pedagogia Centrada no Educando:</strong>
                        <p className="text-slate-400 text-[10.5px] mt-0.5">O processo educativo fundamentado na amorosidade, no respeito e na bagagem prévia do discente.</p>
                      </div>
                    </div>

                    <div className="flex gap-3">
                      <span className="text-[#EE4266] text-xs mt-0.5">●</span>
                      <div>
                        <strong className="text-xs text-white uppercase block tracking-wider">Diálogo e Libertação:</strong>
                        <p className="text-slate-400 text-[10.5px] mt-0.5">O aprendizado como via horizontal de mão dupla, quebrando a clássica "Educação Bancária".</p>
                      </div>
                    </div>

                    <div className="flex gap-3">
                      <span className="text-[#EE4266] text-xs mt-0.5">●</span>
                      <div>
                        <strong className="text-xs text-white uppercase block tracking-wider">Conscientização Crítica:</strong>
                        <p className="text-slate-400 text-[10.5px] mt-0.5">Educar para a autonomia e cidadania ativa, estimulando a reflexão profunda sobre a realidade.</p>
                      </div>
                    </div>
                  </div>

                  <div className="pt-4">
                    <button 
                      onClick={() => handleSmoothScroll('duvidas')}
                      className="rounded-full border border-white hover:bg-white hover:text-slate-950 text-white px-6 py-2.5 text-xs font-black uppercase tracking-widest transition-all cursor-pointer flex items-center gap-2 shadow-sm"
                    >
                      <User className="h-4 w-4 text-[#FFD23F] fill-[#FFD23F]" />
                      <span>Conheça Paulo Freire</span>
                    </button>
                  </div>
                </div>

              </div>
            </section>

            {/* LATEST NEWS: Image 8 Grids Slider */}
            <section id="noticias" className="bg-slate-50 py-16 px-4">
              <div className="mx-auto max-w-7xl space-y-8">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-6">
                  <div className="text-left space-y-1">
                    <span className="text-[10px] font-extrabold text-[#EE4266] uppercase tracking-widest block font-mono">Informativos Recentes</span>
                    <h3 className="text-2xl md:text-3.5xl font-black text-slate-900 uppercase tracking-tight font-serif">Últimas Notícias</h3>
                  </div>

                  <div className="flex gap-2.5 items-center self-stretch sm:self-auto justify-between w-full sm:w-auto">
                    <span className="text-[10px] text-slate-400 font-mono font-bold">Portal de Notícias AVASEC</span>
                    <div className="flex gap-1.5">
                      <button 
                        onClick={handleNewsPrev}
                        className="h-9 w-9 rounded-full bg-white border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-700 transition-colors shadow-3xs cursor-pointer"
                        title="Anterior"
                      >
                        <ChevronLeft className="h-5 w-5" />
                      </button>
                      <button 
                        onClick={handleNewsNext}
                        className="h-9 w-9 rounded-full bg-white border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-700 transition-colors shadow-3xs cursor-pointer"
                        title="Próximo"
                      >
                        <ChevronRight className="h-5 w-5" />
                      </button>
                    </div>
                  </div>
                </div>

                {/* Grid slider list representing news blocks */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-8 text-left">
                  {(() => {
                    const displayedNews = searchQuery.trim() === '' ? newsData : matchedNews;
                    if (displayedNews.length === 0) {
                      return (
                        <div className="col-span-full rounded-2xl border border-dashed border-slate-300 p-12 text-center bg-white shadow-3xs">
                          <h4 className="text-[#EE4266] font-black text-sm uppercase tracking-wider mb-2 font-mono">Sem Resultados</h4>
                          <p className="text-slate-500 text-xs leading-relaxed max-w-md mx-auto">Nenhuma notícia coincide com a sua busca por "<strong className="text-slate-800 font-bold">{searchQuery}</strong>".</p>
                          <button 
                            onClick={() => setSearchQuery('')}
                            className="mt-4 px-4 py-2 bg-[#EE4266] text-white text-xs font-bold rounded-xl hover:bg-rose-700 transition-colors uppercase tracking-wider cursor-pointer font-sans"
                          >
                            Limpar Filtro de Busca
                          </button>
                        </div>
                      );
                    }
                    return displayedNews.map((news, idx) => {
                      const isFocus = idx === (activeNewsIndex % displayedNews.length);

                      return (
                        <div 
                          key={news.id}
                          className={`bg-white rounded-2xl border transition-all duration-300 overflow-hidden shadow-2xs hover:shadow-md flex flex-col justify-between ${
                            isFocus ? 'border-[#EE4266] ring-1 ring-[#EE4266]' : 'border-slate-200'
                          }`}
                        >
                        <div className="h-44 overflow-hidden relative">
                          <img 
                            src={news.image} 
                            alt={news.title} 
                            className="w-full h-full object-cover filter contrast-110 sepia-[5%] hover:scale-105 transition-transform duration-500"
                            referrerPolicy="no-referrer"
                          />
                          <div className="absolute top-3 left-3 bg-[#EE4266] text-white text-[9px] font-mono uppercase tracking-wider px-2 py-0.5 rounded font-bold">
                            {news.tag}
                          </div>
                        </div>

                        <div className="p-5 flex-1 flex flex-col justify-between space-y-4">
                          <div className="space-y-2">
                            <span className="text-[10px] text-slate-400 font-mono font-bold block">{news.date}</span>
                            <h4 className="text-xs font-bold text-slate-900 leading-snug line-clamp-2 h-9 font-serif">
                              {news.title}
                            </h4>
                            <p className="text-[10.5px] text-slate-500 leading-relaxed font-light line-clamp-3">
                              {news.description}
                            </p>
                          </div>

                          <button 
                            onClick={() => setIsLoginModalOpen(true)}
                            className="w-full text-center py-2.5 rounded-xl bg-slate-50 hover:bg-slate-900 hover:text-white transition-colors text-slate-650 border border-slate-150 text-[10px] font-black uppercase tracking-wider cursor-pointer"
                          >
                            Ler Mais Informações
                          </button>
                        </div>
                      </div>
                    );
                  });
                })()}
                </div>
              </div>
            </section>

            {/* DÚVIDAS / FAQ SECTION: Simple interactive block */}
            <section id="duvidas" className="bg-white py-16 px-4 border-t border-slate-200/50">
              <div className="mx-auto max-w-4xl space-y-8">
                <div className="text-center space-y-2">
                  <span className="text-[10px] font-extrabold text-[#540D6E] uppercase tracking-widest block font-mono">Suporte ao Aluno</span>
                  <h3 className="text-2xl md:text-3.5xl font-black text-slate-900 uppercase tracking-tight font-serif">Dúvidas Frequentes</h3>
                  <p className="text-xs text-slate-500">Tem dúvidas sobre como utilizar o Portal AVA? Acesse nosso FAQ rápido:</p>
                  <div className="h-1 w-12 bg-[#FFD23F] mx-auto rounded-full mt-2" />
                </div>

                <div className="space-y-3.5 max-w-2xl mx-auto text-left">
                  {[
                    {
                      q: "Como faço para emitir o meu certificado homologado?",
                      a: "O certificado é gerado automaticamente na plataforma assim que você atingir um mínimo de 70% de frequência de participação letiva (somadas as visualizações de aulas teóricas gravadas, tarefas e presenças síncronas de mentoria)."
                    },
                    {
                      q: "Sou aluno novo, como faço para ingressar no Ambient Virtual de Estudo?",
                      a: "Você só precisa clicar no botão 'Entrar' no topo direito e selecionar o seu perfil correspondente (Student João Silva ou Ana Souza para simular o LMS) e clicar em Conectar."
                    },
                    {
                      q: "O que é o fomento das trilhas de Economia Criativa?",
                      a: "As trilhas auxiliam profissionais a gerenciarem portfólios, captarem recursos em editais de fomento públicos (Lei Paulo Gustavo, Aldir Blanc) e gerarem declarações contábeis de forma simplificada."
                    }
                  ].map((faq, i) => (
                    <div key={i} className="border border-slate-200/70 rounded-2xl bg-slate-55/30 transition-all">
                      <button
                        onClick={() => toggleFaq(i)}
                        className="w-full flex justify-between items-center p-4.5 text-xs font-black text-slate-800 hover:text-[#540D6E] text-left cursor-pointer"
                      >
                        <span>{faq.q}</span>
                        <span className="text-[#540D6E] font-mono text-xs">{expandedFaqIndex === i ? '▲' : '▼'}</span>
                      </button>
                      {expandedFaqIndex === i && (
                        <div className="p-4.5 pt-0 border-t border-slate-100 text-xs text-slate-500 leading-relaxed font-light animate-in fade-in duration-200">
                          {faq.a}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            </section>

          </div>
        ) : currentView === 'perfil' ? (
          /* NEW PROFILE VIEW */
          <ProfileView 
            onBack={() => {
              setCurrentView(previousView);
            }}
            onLogout={handleLogout}
            speakText={speakText}
          />
        ) : (
          /* ACTIVE APP VIEW - ROUTING WITH ABSOLUTE BOUNDARIES EXPLICITLY RESPECTING THE GUIDELINES */
          <div className="bg-slate-50/20">
            {activeUser.role === 'student' && (
              <StudentDashboard onBackToLanding={() => setCurrentView('landing')} speakText={speakText} />
            )}
            
            {activeUser.role === 'instructor' && (
              <InstructorDashboard onBackToLanding={() => setCurrentView('landing')} speakText={speakText} />
            )}

            {activeUser.role === 'admin' && (
              <AdminDashboard onBackToLanding={() => setCurrentView('landing')} speakText={speakText} />
            )}
          </div>
        )}
      </main>

      {/* 1. Official Bottom Accessibility Bar (Moved to footer area for a more discrete look) */}
      <div className="bg-slate-950 text-slate-450 py-4 px-4 md:px-6 border-t border-b border-slate-900 select-none">
        <div className="mx-auto max-w-7xl flex flex-col sm:flex-row justify-between items-center gap-4 font-mono">
          {/* Skip-links */}
          <div className="flex flex-wrap justify-center gap-4 text-[11px] text-slate-450">
            <a 
              href="#hero-section" 
              onClick={(e) => { e.preventDefault(); handleSmoothScroll('hero-section'); speakText("Ir para conteúdo"); }} 
              className="hover:underline hover:text-slate-200 transition-all uppercase tracking-wider font-bold text-slate-400"
            >
              Ir para o conteúdo [1]
            </a>
            <a 
              href="#cursos" 
              onClick={(e) => { e.preventDefault(); handleSmoothScroll('cursos'); speakText("Ir para cursos"); }} 
              className="hover:underline hover:text-slate-200 transition-all uppercase tracking-wider font-bold text-slate-400"
            >
              Ir para o menu [2]
            </a>
          </div>

          {/* Interactive Accessibility Settings Buttons */}
          <div className="flex flex-wrap justify-center gap-4 items-center text-[11px] text-slate-450">
            <button 
              onClick={() => { setIsAccessibilityOpen(true); speakText("Janela de acessibilidade aberta"); }}
              className="cursor-pointer hover:underline text-slate-400 font-extrabold hover:text-teal-400 flex items-center gap-1.5 transition-all bg-transparent border-0 outline-hidden py-1 px-2 rounded-md hover:bg-white/5"
              title="Ajustar tamanho da fonte, leitor e preferências"
            >
              <Settings className="w-3.5 h-3.5 text-teal-400 animate-spin" style={{ animationDuration: '8s' }} />
              <span className="uppercase tracking-wide font-black">Acessibilidade</span>
            </button>
            
            <button 
              onClick={() => {
                const next = !accessibilitySettings.highContrast;
                updateAccessibilitySettings({ highContrast: next });
                speakText(next ? "Alto contraste ativado" : "Alto contraste desativado");
              }}
              className={`cursor-pointer hover:underline font-extrabold flex items-center gap-1.5 transition-all bg-transparent border-0 outline-hidden py-1 px-2 rounded-md hover:bg-white/5 ${accessibilitySettings.highContrast ? 'text-yellow-400 underline' : 'text-slate-400 hover:text-yellow-400'}`}
              title="Ativar/Desativar cores de alto contraste para baixa visão"
            >
              <Monitor className="w-3.5 h-3.5 text-yellow-400" />
              <span className="uppercase tracking-wide font-black">Alto Contraste</span>
            </button>
            
            <button 
              onClick={() => { setIsSiteMapOpen(true); speakText("Mapa de seções do site aberto"); }}
              className="cursor-pointer hover:underline text-slate-400 font-extrabold hover:text-teal-400 flex items-center gap-1.5 transition-all bg-transparent border-0 outline-hidden py-1 px-2 rounded-md hover:bg-white/5"
              title="Exibir mapa do site"
            >
              <BookOpen className="w-3.5 h-3.5 text-teal-400" />
              <span className="uppercase tracking-wide font-black">Mapa do Site</span>
            </button>

            {/* Language Selector */}
            <div className="flex items-center gap-2 border-l border-slate-800 pl-4 text-slate-450">
              <button 
                onClick={() => { setCurrentLang('pt'); speakText("Idioma alterado para Português"); }}
                className={`font-black uppercase text-[10px] tracking-wider transition-all cursor-pointer px-2 py-0.5 rounded ${currentLang === 'pt' ? 'bg-teal-500 text-slate-950 font-black scale-105' : 'hover:text-slate-200 font-bold'}`}
                title="Português (Brasil)"
              >
                PT-BR
              </button>
              <span className="text-slate-700 font-light">|</span>
              <button 
                onClick={() => { setCurrentLang('en'); speakText("Language changed to English"); }}
                className={`font-black uppercase text-[10px] tracking-wider transition-all cursor-pointer px-2 py-0.5 rounded ${currentLang === 'en' ? 'bg-teal-500 text-slate-950 font-black scale-105' : 'hover:text-slate-200 font-bold'}`}
                title="English"
              >
                EN
              </button>
              <span className="text-slate-700 font-light">|</span>
              <button 
                onClick={() => { setCurrentLang('es'); speakText("Idioma cambiado a Español"); }}
                className={`font-black uppercase text-[10px] tracking-wider transition-all cursor-pointer px-2 py-0.5 rounded ${currentLang === 'es' ? 'bg-teal-500 text-slate-950 font-black scale-105' : 'hover:text-slate-200 font-bold'}`}
                title="Español"
              >
                ES
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Footer minimalista e elegante coerente com a remoção solicitada */}
      <footer className="bg-slate-950 text-slate-500 text-center py-8 text-xs border-t border-slate-900 font-sans">
        <p>© 2026 Portal Escult — Escola Paulo Freire. Todos os direitos reservados.</p>
      </footer>

      {/* 6. MODAL DIALOG CONTAINERS: Accessibility preferences, Site Map & Conexão Acadêmica */}
      <AnimatePresence>
        {isAccessibilityOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center px-4 select-none">
            
            {/* Backdrop */}
            <motion.div 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setIsAccessibilityOpen(false)}
              className="absolute inset-0 bg-slate-950/70 backdrop-blur-xs"
            />

            {/* Content panel */}
            <motion.div 
              initial={{ scale: 0.95, opacity: 0, y: 15 }}
              animate={{ scale: 1, opacity: 1, y: 0 }}
              exit={{ scale: 0.95, opacity: 0, y: 15 }}
              className="bg-white border-2 border-teal-500 rounded-2xl shadow-2xl p-6 w-full max-w-lg z-10 text-left space-y-6 relative overflow-hidden text-slate-800"
            >
              {/* Header */}
              <div className="flex justify-between items-start">
                <div className="space-y-1">
                  <span className="text-[10px] uppercase tracking-widest bg-teal-50 text-teal-700 px-2 py-0.5 rounded-md font-mono font-bold w-fit block">PREFERÊNCIAS</span>
                  <h3 className="font-black text-slate-900 text-lg font-serif flex items-center gap-2">
                    <Settings className="h-5 w-5 text-teal-600" />
                    <span>Painel de Acessibilidade da Escola</span>
                  </h3>
                </div>
                <button 
                  onClick={() => setIsAccessibilityOpen(false)}
                  className="text-slate-400 hover:text-slate-800 p-1.5 rounded-full hover:bg-slate-100 transition-all cursor-pointer"
                  title="Fechar"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Preference options list */}
              <div className="space-y-4">
                
                {/* Text Sizing Block */}
                <div className="space-y-2 border-b border-slate-100 pb-4">
                  <span className="text-xs font-black text-slate-700 block uppercase tracking-wider font-mono">Tamanho do Texto [Magnification]</span>
                  <div className="grid grid-cols-3 gap-2">
                    {[
                      { label: "Padrão (100%)", value: 1.0 },
                      { label: "Grande (115%)", value: 1.15 },
                      { label: "Extra G. (130%)", value: 1.30 }
                    ].map((option) => (
                      <button
                        key={option.value}
                        onClick={() => { setTextSizeMultiplier(option.value); speakText(`Tamanho da fonte definido para ${option.label}`); }}
                        className={`py-2 px-3 rounded-xl border text-xs font-extrabold transition-all cursor-pointer text-center ${
                          textSizeMultiplier === option.value
                            ? 'bg-teal-500 text-slate-950 border-teal-600 shadow-2xs'
                            : 'bg-slate-50 border-slate-200 text-slate-650 hover:bg-slate-100'
                        }`}
                      >
                        {option.label}
                      </button>
                    ))}
                  </div>
                </div>

                {/* High contrast switch */}
                <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                  <div className="space-y-0.5">
                    <span className="text-xs font-black text-slate-700 block uppercase tracking-wider font-mono">Alto Contraste Visual</span>
                    <p className="text-[10px] text-slate-400 leading-tight">Melhora distinção cromática com fundo opaco preto e texto luminoso amarelo/branco.</p>
                  </div>
                  <button
                    onClick={() => {
                      const next = !accessibilitySettings.highContrast;
                      updateAccessibilitySettings({ highContrast: next });
                      speakText(next ? "Alto contraste ativado" : "Alto contraste desativado");
                    }}
                    className={`py-2 px-4 rounded-xl border text-xs font-black transition-all cursor-pointer uppercase ${
                      accessibilitySettings.highContrast 
                        ? 'bg-yellow-400 border-yellow-500 text-slate-950 font-black' 
                        : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100 font-bold'
                    }`}
                  >
                    {accessibilitySettings.highContrast ? 'Ativo' : 'Inativo'}
                  </button>
                </div>

                {/* Dyslexia-friendly Font Switch */}
                <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                  <div className="space-y-0.5">
                    <span className="text-xs font-black text-slate-700 block uppercase tracking-wider font-mono font-sans font-extrabold">Fonte de Alta Legibilidade</span>
                    <p className="text-[10px] text-slate-400 leading-tight">Altera toda a tipografia do portal para mono-espaçada estruturada, auxiliando leitura seletiva.</p>
                  </div>
                  <button
                    onClick={() => {
                      const next = !accessibilitySettings.dyslexicFont;
                      updateAccessibilitySettings({ dyslexicFont: next });
                      speakText(next ? "Fonte legibilidade desativada" : "Fonte de alta legibilidade ativada");
                    }}
                    className={`py-2 px-4 rounded-xl border text-xs font-black transition-all cursor-pointer uppercase ${
                      accessibilitySettings.dyslexicFont 
                        ? 'bg-teal-500 border-teal-600 text-slate-950 font-black' 
                        : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100 font-bold'
                    }`}
                  >
                    {accessibilitySettings.dyslexicFont ? 'Ativo' : 'Inativo'}
                  </button>
                </div>

                {/* Sound description switch */}
                <div className="flex items-center justify-between pb-2">
                  <div className="space-y-0.5">
                    <span className="text-xs font-black text-slate-700 block uppercase tracking-wider font-mono">Feedback Sonoro e Vocalizador</span>
                    <p className="text-[10px] text-slate-400 leading-tight">Ativa narração falada automática de botões, tags escolares e menus ao interagir.</p>
                  </div>
                  <button
                    onClick={() => { 
                      const nextState = !isSpeechEnabled;
                      setIsSpeechEnabled(nextState);
                      if (nextState) {
                        setTimeout(() => {
                          if ('speechSynthesis' in window) {
                            window.speechSynthesis.cancel();
                            const utterance = new SpeechSynthesisUtterance("Leitor de tela simulado ativado com sucesso.");
                            utterance.lang = 'pt-BR';
                            window.speechSynthesis.speak(utterance);
                          }
                        }, 200);
                      }
                    }}
                    className={`py-2 px-4 rounded-xl border text-xs font-black transition-all cursor-pointer uppercase ${
                      isSpeechEnabled 
                        ? 'bg-teal-500 border-teal-600 text-slate-950 font-black' 
                        : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100 font-bold'
                    }`}
                  >
                    {isSpeechEnabled ? 'Ativo' : 'Inativo'}
                  </button>
                </div>

              </div>
              
              <div className="bg-slate-50/50 border border-slate-150 p-3.5 rounded-xl text-center">
                <span className="text-[9.5px] text-slate-400 block font-mono">PORTAL HOMOLOGADO CONFORME A PORTARIA DE ACESSIBILIDADE DIGITAL EM LIBRAS E LEITOR</span>
              </div>

            </motion.div>
          </div>
        )}

        {isSiteMapOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center px-4 select-none">
            
            {/* Backdrop */}
            <motion.div 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setIsSiteMapOpen(false)}
              className="absolute inset-0 bg-slate-950/70 backdrop-blur-xs"
            />

            {/* Content panel */}
            <motion.div 
              initial={{ scale: 0.95, opacity: 0, y: 15 }}
              animate={{ scale: 1, opacity: 1, y: 0 }}
              exit={{ scale: 0.95, opacity: 0, y: 15 }}
              className="bg-white border-2 border-teal-500 rounded-2xl shadow-2xl p-6 w-full max-w-2xl z-10 text-left space-y-5.5 relative overflow-hidden text-slate-800"
            >
              {/* Header */}
              <div className="flex justify-between items-start">
                <div className="space-y-1">
                  <span className="text-[10px] uppercase tracking-widest bg-blue-50 text-blue-700 px-2 py-0.5 rounded-md font-mono font-bold w-fit block">NAVEGAÇÃO COMPLETA</span>
                  <h3 className="font-black text-slate-900 text-sm md:text-base font-serif flex items-center gap-2">
                    <BookOpen className="h-5 w-5 text-blue-600" />
                    <span>Mapa do Site — Escola Paulo Freire</span>
                  </h3>
                </div>
                <button 
                  onClick={() => setIsSiteMapOpen(false)}
                  className="text-slate-400 hover:text-slate-800 p-1.5 rounded-full hover:bg-slate-100 transition-all cursor-pointer"
                  title="Fechar"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Nested site navigation items */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[380px] overflow-y-auto pr-1">
                
                {/* Segment 1: Landing Areas */}
                <div className="bg-slate-50 p-4 rounded-xl space-y-3">
                  <strong className="text-xs uppercase tracking-wider font-mono text-slate-700 flex items-center gap-1.5 border-b border-slate-200 pb-1.5">
                    <Sparkles className="h-4 w-4 text-[#FFD23F]" />
                    <span>Seções Principais</span>
                  </strong>
                  
                  <nav className="flex flex-col gap-2 text-xs">
                    <button 
                      onClick={() => { setIsSiteMapOpen(false); handleSmoothScroll('hero-section'); speakText("Ir para Banner Principal"); }} 
                      className="text-left py-1 hover:text-[#540D6E] transition-colors hover:underline block cursor-pointer bg-transparent border-0 outline-hidden font-bold"
                    >
                      • Banner Principal (Hero Portal)
                    </button>
                    <button 
                      onClick={() => { setIsSiteMapOpen(false); handleSmoothScroll('cursos'); speakText("Ir para Cursos em Destaque"); }} 
                      className="text-left py-1 hover:text-[#540D6E] transition-colors hover:underline block cursor-pointer bg-transparent border-0 outline-hidden font-bold"
                    >
                      • Lista de Cursos em Destaque (LMS Slider)
                    </button>
                    <button 
                      onClick={() => { setIsSiteMapOpen(false); handleSmoothScroll('noticias'); speakText("Ir para Informativos Recentes"); }} 
                      className="text-left py-1 hover:text-[#540D6E] transition-colors hover:underline block cursor-pointer bg-transparent border-0 outline-hidden font-bold"
                    >
                      • Painel Informativo de Notícias Escut
                    </button>
                    <button 
                      onClick={() => { setIsSiteMapOpen(false); handleSmoothScroll('duvidas'); speakText("Ir para FAQ"); }} 
                      className="text-left py-1 hover:text-[#540D6E] transition-colors hover:underline block cursor-pointer bg-transparent border-0 outline-hidden font-bold"
                    >
                      • Central de Dúvidas (Faq de Alunos)
                    </button>
                  </nav>
                </div>

                {/* Segment 2: Simulation Areas */}
                <div className="bg-slate-50 p-4 rounded-xl space-y-3">
                  <strong className="text-xs uppercase tracking-wider font-mono text-slate-700 flex items-center gap-1.5 border-b border-slate-200 pb-1.5">
                    <ShieldCheck className="h-4 w-4 text-[#540D6E]" />
                    <span>Acesso ao Portal Acadêmico (AVA)</span>
                  </strong>
                  
                  <div className="flex flex-col gap-2 text-xs">
                    <button 
                      onClick={() => { setIsSiteMapOpen(false); handleProfileLogin('João Silva', 'student'); speakText("Acesso de Aluno Homologado"); }}
                      className="text-left py-1.5 px-2 hover:bg-blue-50 rounded text-[#540D6E] transition-all font-black flex items-center justify-between bg-transparent border border-transparent cursor-pointer"
                    >
                      <span>• Dashboard do Aluno</span>
                      <span className="text-[8px] bg-blue-100 text-blue-800 px-1.5 py-0.2 rounded font-mono uppercase">Mapeado</span>
                    </button>
                    <button 
                      onClick={() => { setIsSiteMapOpen(false); handleProfileLogin('Gestor de Cursos', 'instructor'); speakText("Acesso de Gestão Homologado"); }}
                      className="text-left py-1.5 px-2 hover:bg-blue-50 rounded text-teal-700 transition-all font-black flex items-center justify-between bg-transparent border border-transparent cursor-pointer"
                    >
                      <span>• Dashboard de Gestão</span>
                      <span className="text-[8px] bg-teal-100 text-teal-800 px-1.5 py-0.2 rounded font-mono uppercase">Mapeado</span>
                    </button>
                    <button 
                      onClick={() => { setIsSiteMapOpen(false); handleProfileLogin('Admin Superior', 'admin'); speakText("Acesso de Administrador Homologado"); }}
                      className="text-left py-1.5 px-2 hover:bg-amber-50 rounded text-amber-700 transition-all font-black flex items-center justify-between bg-transparent border border-transparent cursor-pointer"
                    >
                      <span>• Moderação de Coordenação</span>
                      <span className="text-[8px] bg-amber-100 text-amber-800 px-1.5 py-0.2 rounded font-mono uppercase">Mapeado</span>
                    </button>
                  </div>
                </div>

              </div>

              <div className="bg-slate-50/50 border border-slate-150 p-3.5 rounded-xl text-center">
                <span className="text-[9.5px] text-slate-400 block font-mono">PORTAL DE CRIAÇÃO E QUALIFICAÇÃO INTEGRADO</span>
              </div>

            </motion.div>
          </div>
        )}

        {isLoginModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center px-4">
            
            {/* Dark glass backdrop with exit gesture close */}
            <motion.div 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setIsLoginModalOpen(false)}
              className="absolute inset-0 bg-slate-950/70 backdrop-blur-xs"
            />

            {/* Center modular dialogue wrap styled perfectly identical to image profile selector */}
            <motion.div 
              initial={{ scale: 0.95, opacity: 0, y: 15 }}
              animate={{ scale: 1, opacity: 1, y: 0 }}
              exit={{ scale: 0.95, opacity: 0, y: 15 }}
              className="bg-white border border-slate-200/90 rounded-2xl shadow-2xl p-6.5 w-full max-w-lg z-10 text-left space-y-5.5 relative overflow-hidden"
            >
              {/* Close corner control */}
              <button 
                onClick={() => setIsLoginModalOpen(false)}
                className="absolute top-4.5 right-4.5 text-slate-400 hover:text-slate-800 p-1 rounded-full hover:bg-slate-100 transition-all cursor-pointer"
                title="Fechar"
              >
                <X className="h-4.5 w-4.5" />
              </button>

              {/* Title Section */}
              <div className="space-y-1">
                <h3 className="font-extrabold text-[#111] text-base font-serif flex items-center gap-2">
                  <ShieldCheck className="h-5 w-5 text-[#540D6E]" />
                  <span>Portal de Conexão Acadêmica</span>
                </h3>
                <p className="text-[11px] text-slate-400">Escolha uma identidade acadêmica simulada para acessar e avaliar as ferramentas de dashboards:</p>
              </div>

              {/* Role Select tab alignment */}
              <div className="grid grid-cols-3 gap-1 bg-slate-105 p-1 rounded-xl text-center border border-slate-200">
                <button
                  onClick={() => setLoginRoleTab('student')}
                  className={`py-2 rounded-lg text-xs font-black uppercase tracking-wider transition-all cursor-pointer ${
                    loginRoleTab === 'student'
                      ? 'bg-[#540D6E] text-white shadow-3xs'
                      : 'text-slate-500 hover:text-slate-700'
                  }`}
                >
                  Aluno
                </button>
                <button
                  onClick={() => setLoginRoleTab('instructor')}
                  className={`py-2 rounded-lg text-xs font-black uppercase tracking-wider transition-all cursor-pointer ${
                    loginRoleTab === 'instructor'
                      ? 'bg-[#540D6E] text-white shadow-2xs'
                      : 'text-slate-500 hover:text-slate-700'
                  }`}
                >
                  Gestão
                </button>
                <button
                  onClick={() => setLoginRoleTab('admin')}
                  className={`py-2 rounded-lg text-xs font-black uppercase tracking-wider transition-all cursor-pointer ${
                    loginRoleTab === 'admin'
                      ? 'bg-[#540D6E] text-white shadow-2xs'
                      : 'text-slate-500 hover:text-slate-700'
                  }`}
                >
                  Admin
                </button>
              </div>

              {/* Dynamic Profiles Content spacing matching images */}
              <div className="space-y-3.5 max-h-[300px] overflow-y-auto pr-1">
                
                {/* 1. Student choices list */}
                {loginRoleTab === 'student' && (
                  <div className="space-y-2.5 animate-in fade-in duration-200">
                    <span className="text-[9px] uppercase font-bold text-slate-400 tracking-wider block">Estudantes Cadastrados ativos:</span>
                    <div className="grid grid-cols-1 gap-2.5">
                      {mockStudentProfiles.map((student) => (
                        <div
                          key={student.email}
                          onClick={() => handleProfileLogin(student.name, 'student')}
                          className="group border border-slate-200 hover:border-slate-350 bg-slate-50/40 hover:bg-slate-50 p-3 rounded-xl cursor-pointer flex justify-between items-center transition-all"
                        >
                          <div className="flex items-center gap-3">
                            <div className="h-8 w-8 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center font-extrabold text-xs text-[#540D6E]">
                              {student.name.charAt(0)}
                            </div>
                            <div className="text-left leading-normal">
                              <strong className="text-slate-900 text-xs font-bold block">{student.name}</strong>
                              <span className="text-[10px] text-slate-400 block">{student.email}</span>
                            </div>
                          </div>
                          <span className="rounded-lg bg-white border border-slate-200 text-slate-650 font-black text-[9px] px-3 py-1.5 uppercase tracking-wide group-hover:bg-[#540D6E] group-hover:text-white transition-all flex items-center gap-1">
                            <span>Conectar</span>
                            <ArrowRight className="h-3 w-3" />
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* 2. Professor choices list */}
                {loginRoleTab === 'instructor' && (
                  <div className="space-y-2.5 animate-in fade-in duration-200">
                    <span className="text-[9px] uppercase font-bold text-slate-400 tracking-wider block">Gestor de Cursos ({professorsList.length}):</span>
                    <div className="grid grid-cols-1 gap-2.5">
                      {professorsList.map((prof) => (
                        <div
                          key={prof}
                          onClick={() => handleProfileLogin(prof, 'instructor')}
                          className="group border border-slate-200 hover:border-slate-350 bg-slate-50/40 hover:bg-slate-50 p-3 rounded-xl cursor-pointer flex justify-between items-center transition-all"
                        >
                          <div className="flex items-center gap-3">
                            <div className="h-8 w-8 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center font-extrabold text-xs text-emerald-500">
                              G
                            </div>
                            <div className="text-left leading-normal">
                              <strong className="text-slate-900 text-xs font-bold block">{prof}</strong>
                              <span className="text-[9px] text-slate-400 block font-sans">Gestor de Cursos</span>
                            </div>
                          </div>
                          <span className="rounded-lg bg-white border border-slate-200 text-slate-650 font-black text-[9px] px-3 py-1.5 uppercase tracking-wide group-hover:bg-[#540D6E] group-hover:text-white transition-all flex items-center gap-1">
                            <span>Acessar</span>
                            <ArrowRight className="h-3 w-3" />
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* 3. Admin credentials superior block */}
                {loginRoleTab === 'admin' && (
                  <div className="space-y-4 animate-in fade-in duration-200 text-left">
                    <div className="bg-amber-50/40 border border-amber-200 p-3.5 rounded-xl text-slate-700 text-[10px] leading-relaxed">
                      <strong className="text-xs text-amber-800 block mb-0.5">Coordenação Federal Superior</strong>
                      O perfil do administrador possui visualizações abrangentes para a monitoração pedagógica e a provisão das matrizes curriculares de novas disciplinas. No entanto, por questões éticas e de LGPD, as mensagens trocadas no chat direto entre alunos permanecem ocultas dele.
                    </div>

                    <div
                      onClick={() => handleProfileLogin('Admin Superior', 'admin')}
                      className="group border border-amber-250 hover:border-amber-400 bg-amber-50/20 hover:bg-amber-50/45 p-4 rounded-xl cursor-pointer flex justify-between items-center transition-all"
                    >
                      <div className="flex items-center gap-3">
                        <div className="h-9 w-9 rounded-xl bg-slate-950 text-[#FFD23F] flex items-center justify-center">
                          <Shield className="h-4 w-4" />
                        </div>
                        <div>
                          <strong className="text-slate-900 text-xs font-black block">Administrador Superior</strong>
                          <span className="text-[10px] text-slate-400 block mt-0.5">Provedor Geral de Segurança Letiva</span>
                        </div>
                      </div>
                      <span className="rounded-lg bg-slate-900 text-white font-extrabold text-[9px] px-3 py-1.5 uppercase transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>Ingressar</span>
                        <ArrowRight className="h-3.5 w-3.5 text-[#FFD23F]" />
                      </span>
                    </div>
                  </div>
                )}

              </div>

              {/* Notice text in bottom of login block */}
              <div className="pt-2 text-center">
                <span className="text-[9.5px] text-slate-400 block font-mono">AUTENTICAÇÃO SEGURA DE ACORDO COM A LGPD • PORTAL ESCULT</span>
              </div>

            </motion.div>
          </div>
        )}

        {isRegisterModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            
            {/* Dark glass backdrop with exit gesture close */}
            <motion.div 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => {
                if (validationStep === 'idle' || validationStep === 'completed') {
                  setIsRegisterModalOpen(false);
                }
              }}
              className="absolute inset-0 bg-slate-950/70 backdrop-blur-xs"
            />

            {/* Center modular dialogue wrap */}
            <motion.div 
              initial={{ scale: 0.95, opacity: 0, y: 30 }}
              animate={{ scale: 1, opacity: 1, y: 0 }}
              exit={{ scale: 0.95, opacity: 0, y: 30 }}
              transition={{ type: "spring", duration: 0.5 }}
              className="relative w-full max-w-lg bg-white border border-slate-200/90 rounded-3xl shadow-2xl p-6 sm:p-8 overflow-hidden z-50 text-left"
            >
              {/* Close Button */}
              {(validationStep === 'idle' || validationStep === 'completed') && (
                <button
                  onClick={() => setIsRegisterModalOpen(false)}
                  className="absolute top-4 right-4 p-1.5 text-slate-400 hover:text-slate-650 hover:bg-slate-100 rounded-lg transition-all cursor-pointer border-none bg-transparent"
                  title="Fechar"
                  id="btn-close-register-modal"
                >
                  <X className="h-5 w-5" />
                </button>
              )}

              {/* Title Section */}
              <div className="space-y-1.5 pr-6 mb-5">
                <span className="text-[9px] uppercase tracking-widest bg-[#540D6E]/10 text-[#540D6E] px-2 py-0.5 rounded-md font-mono font-bold w-fit block">
                  Célula de Integração Governamental
                </span>
                <h3 className="font-extrabold text-[#111] text-base sm:text-lg flex items-center gap-2">
                  <ShieldCheck className="h-5 w-5 text-[#540D6E]" />
                  <span>Integração de Cadastro • AVASEC</span>
                </h3>
                <p className="text-xs text-slate-505 leading-relaxed">
                  Conforme solicitado: Você será direcionado para outro site externo onde irá se cadastrar. Preencha seus dados lá e, após validados, esse mesmo cadastro será homologado e usado de forma integrada no <strong className="text-[#540D6E] font-bold">AVASEC</strong> como sua credencial oficial de estudos.
                </p>
              </div>

              {validationStep === 'idle' && (
                <div className="space-y-5">
                  {/* Passo 1 block */}
                  <div className="relative border border-slate-200 hover:border-[#540D6E]/35 rounded-2xl p-4 bg-slate-50/50 hover:bg-white transition-all space-y-3">
                    <div className="flex items-start gap-3">
                      <div className="h-7 w-7 rounded-lg bg-[#540D6E]/5 text-[#540D6E] flex items-center justify-center font-bold text-xs uppercase tracking-wide">
                        01
                      </div>
                      <div className="space-y-1 flex-1">
                        <h4 className="text-xs font-extrabold text-slate-800 uppercase tracking-wide">Direcionamento para Portal Externo</h4>
                        <p className="text-[11px] text-slate-500 leading-relaxed">
                          Crie ou obtenha suas informações no Portal Externo de Inscrição onde você realiza o cadastro de sua Identidade Digital antes de usá-la aqui.
                        </p>
                      </div>
                    </div>
                    
                    <div className="pl-10">
                      <a 
                        href="https://cadastro.escoladacultura.gov.br" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        onClick={() => {
                          setIsExternalLinkClicked(true);
                          speakText("Redirecionando para o Portal Externo de Inscrição.");
                        }}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-[#540D6E]/5 hover:bg-[#540D6E]/10 text-[#540D6E] border border-[#540D6E]/15 px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-wider transition-all cursor-pointer no-underline"
                        id="lnk-external-cadastro"
                      >
                        <span>Ir para o Portal de Cadastro</span>
                        <ExternalLink className="h-3 w-3" />
                      </a>
                      
                      {isExternalLinkClicked && (
                        <span className="text-[9.5px] text-emerald-600 font-bold block mt-1.5">
                          ✓ Conexão externa simulada. Preencha e valide suas informações abaixo para liberá-la no AVASEC.
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Passo 2 form */}
                  <div className="border border-slate-200 rounded-2xl p-4 bg-white space-y-4">
                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
                      <div className="flex items-start gap-3">
                        <div className="h-7 w-7 rounded-lg bg-[#540D6E]/5 text-[#540D6E] flex items-center justify-center font-bold text-xs uppercase tracking-wide">
                          02
                        </div>
                        <div className="space-y-1">
                          <h4 className="text-xs font-extrabold text-slate-800 uppercase tracking-wide">Validação & Sincronização no AVASEC</h4>
                          <p className="text-[11px] text-slate-500 leading-relaxed">
                            Insira abaixo os dados cadastrados no portal externo para simular a autenticação unificada sob os padrões de conformidade da LGPD.
                          </p>
                        </div>
                      </div>
                      
                      {(isExternalLinkClicked || validationStep !== 'idle') && (
                        <button
                          onClick={() => {
                            setIsExternalLinkClicked(false);
                            setValidationStep('idle');
                            speakText("Voltando para o passo inicial de consulta externa.");
                          }}
                          className="flex items-center gap-1.5 px-3 py-1 rounded-lg bg-slate-50 text-slate-400 hover:text-[#540D6E] transition-all text-[9.5px] font-bold uppercase tracking-widest cursor-pointer border border-slate-200 group"
                        >
                          <ArrowLeft className="h-3 w-3 group-hover:-translate-x-0.5 transition-transform" />
                          <span>Mudar Método</span>
                        </button>
                      )}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pb-1">
                      <div>
                        <label className="block text-[10px] uppercase font-bold text-slate-400 mb-1">Nome Completo</label>
                        <input 
                          type="text" 
                          required
                          value={registerName}
                          onChange={(e) => {
                            setRegisterName(e.target.value);
                            setValidationError(null);
                          }}
                          placeholder="Ex: João Silva da Silva"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-[#540D6E] focus:ring-1 focus:ring-[#540D6E] transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-name"
                        />
                      </div>

                      <div>
                        <label className="block text-[10px] uppercase font-bold text-slate-400 mb-1">C.P.F. ou R.G.</label>
                        <input 
                          type="text" 
                          value={registerCpf}
                          onChange={(e) => {
                            setRegisterCpf(e.target.value);
                            setValidationError(null);
                          }}
                          placeholder="000.000.000-00"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-[#540D6E] focus:ring-1 focus:ring-[#540D6E] transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-cpf"
                        />
                      </div>

                      <div className="sm:col-span-2">
                        <label className="block text-[10px] uppercase font-bold text-slate-400 mb-1">Endereço de E-mail</label>
                        <input 
                          type="email" 
                          required
                          value={registerEmail}
                          onChange={(e) => {
                            setRegisterEmail(e.target.value);
                            setValidationError(null);
                          }}
                          placeholder="seu.email@lms.edu"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-[#540D6E] focus:ring-1 focus:ring-[#540D6E] transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-email"
                        />
                      </div>

                      <div className="sm:col-span-2">
                        <div className="flex justify-between items-center mb-1">
                          <label className="block text-[10px] uppercase font-bold text-slate-400">PIN / Senha de Acesso (Exclusivamente Números)</label>
                          <span className="text-[8.5px] text-slate-400 font-mono">Usado para se conectar</span>
                        </div>
                        <input 
                          type="password" 
                          maxLength={8}
                          required
                          value={registerPassword}
                          onChange={(e) => {
                            const val = e.target.value.replace(/\D/g, ''); // numeric PIN
                            setRegisterPassword(val);
                            setValidationError(null);
                          }}
                          placeholder="De 4 a 8 dígitos numéricos"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-[#540D6E] focus:ring-1 focus:ring-[#540D6E] transition-all bg-slate-50/20 text-slate-800 font-mono tracking-widest"
                          id="inp-register-password"
                        />
                      </div>
                    </div>

                    {validationError && (
                      <div className="text-[10px] font-bold text-rose-600 bg-rose-50 border border-rose-200 rounded-lg p-2.5 text-center leading-relaxed">
                        ⚠️ Erro de Registro: {validationError}
                      </div>
                    )}

                    <button
                      onClick={() => {
                        if (!registerName.trim() || !registerEmail.trim() || !registerPassword.trim()) {
                          setValidationError("Por favor, preencha todos os campos obrigatórios (Nome, E-mail e Senha PIN).");
                          speakText("Por favor, preencha todos os campos obrigatórios.");
                          return;
                        }
                        if (registerPassword.length < 4) {
                          setValidationError("Sua senha PIN deve possuir no mínimo 4 dígitos.");
                          speakText("Sua senha deve ter no mínimo 4 dígitos.");
                          return;
                        }
                        
                        // Start animated check simulation
                        setValidationStep('matching');
                        setValidationProgress(0);
                        speakText("Integrando com o Portal Externo. Verificando as credenciais informadas...");
                        
                        let progressVal = 0;
                        const interval = setInterval(() => {
                          progressVal += 5;
                          setValidationProgress(progressVal);
                          if (progressVal === 30) {
                            setValidationStep('verifying');
                          } else if (progressVal === 70) {
                            setValidationStep('syncing');
                          } else if (progressVal >= 100) {
                            clearInterval(interval);
                            setValidationStep('completed');
                            speakText("Validação biométrica e cruzamento cadastral realizados com sucesso.");
                          }
                        }, 120);
                      }}
                      className="w-full rounded-xl bg-[#540D6E] hover:bg-[#340845] text-white font-extrabold text-xs py-3.5 uppercase tracking-wider transition-all cursor-pointer shadow-md flex items-center justify-center gap-1.5 border-none"
                      id="btn-trigger-validation"
                    >
                      <ShieldCheck className="h-4.5 w-4.5" />
                      <span>Validar Cadastro e Integrar com AVASEC</span>
                    </button>
                  </div>
                </div>
              )}

              {/* Validation Progress Animations */}
              {(validationStep === 'matching' || validationStep === 'verifying' || validationStep === 'syncing') && (
                <div className="py-12 flex flex-col items-center justify-center text-center space-y-6">
                  <div className="relative h-20 w-20 flex items-center justify-center">
                    <div className="absolute inset-0 rounded-full border-4 border-slate-100 border-t-[#540D6E] animate-spin" />
                    <Fingerprint className="h-10 w-10 text-[#540D6E] animate-pulse" />
                  </div>

                  <div className="space-y-1.5 max-w-sm">
                    <h4 className="text-xs font-black text-slate-800 uppercase tracking-wide">
                      {validationStep === 'matching' && "Buscando Registro na Rede do Ministério..."}
                      {validationStep === 'verifying' && "Verificando Autenticidade e CPF do Titular..."}
                      {validationStep === 'syncing' && "Homologando Documento Digital Governamental..."}
                    </h4>
                    <p className="text-[11px] text-slate-400 leading-relaxed">
                      {validationStep === 'matching' && "Localizando cadastros sob a infraestrutura do Portal da Cultura e Economia Criativa."}
                      {validationStep === 'verifying' && `Submetendo credencial biométrica do CPF ${registerCpf || "Federal"} aos órgãos de validação.`}
                      {validationStep === 'syncing' && `Sucesso na assinatura digital! Gravando acesso estudantil no AVASEC de ${registerName}.`}
                    </p>
                  </div>

                  <div className="w-full max-w-xs space-y-1.5">
                    <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                      <div 
                        style={{ width: `${validationProgress}%` }}
                        className="h-full bg-gradient-to-r from-teal-400 via-indigo-500 to-[#540D6E] transition-all duration-100"
                      />
                    </div>
                    <span className="text-[9.5px] font-mono text-slate-450 font-bold block">{validationProgress}% CONCLUÍDO</span>
                  </div>
                </div>
              )}

              {/* Completion Block */}
              {validationStep === 'completed' && (
                <div className="py-6 flex flex-col items-center justify-center text-center space-y-5 animate-in zoom-in-95 duration-200">
                  <div className="h-14 w-14 bg-emerald-50 border border-emerald-250 rounded-full flex items-center justify-center">
                    <CheckCircle className="h-8 w-8 text-emerald-600" />
                  </div>

                  <div className="space-y-1 max-w-sm">
                    <h4 className="text-base font-black text-slate-900 uppercase tracking-wide leading-tight">
                      Integração Sincronizada!
                    </h4>
                    <p className="text-[11.5px] text-slate-500 leading-relaxed">
                      Seu cadastro foi homologado externamente. Use a senha numérica <span className="font-bold text-[#540D6E] font-mono">{registerPassword}</span> para reconectores futuros.
                    </p>
                  </div>

                  {/* Summary Box */}
                  <div className="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-left space-y-2 max-w-md">
                    <span className="text-[9px] uppercase font-extrabold text-[#540D6E] font-mono block border-b border-slate-150 pb-1.5">
                      Ficha de Aluno no AVASEC
                    </span>
                    <div className="grid grid-cols-2 gap-2 text-[9.5px] font-mono text-slate-600 leading-relaxed">
                      <div>
                        <strong>NOME ID:</strong> <span className="block text-slate-800 font-sans font-bold">{registerName}</span>
                      </div>
                      <div>
                        <strong>EMAIL ACC:</strong> <span className="block text-slate-800 font-sans">{registerEmail}</span>
                      </div>
                      <div>
                        <strong>CONEXÃO CPF:</strong> <span className="block text-slate-800">{registerCpf || "NÃO CADASTRADO"}</span>
                      </div>
                      <div>
                        <strong>STATUS:</strong> <span className="block text-emerald-600 font-bold uppercase">ATIVO E INTEGRADO</span>
                      </div>
                    </div>
                  </div>

                  <button
                    onClick={() => {
                      // Save record to database
                      addStudent(registerName, registerEmail, registerPassword);
                      // Instantly log in matching their credential
                      executeProfileLogin(registerName, 'student');
                      
                      setIsRegisterModalOpen(false);
                      // Clear values
                      setRegisterName('');
                      setRegisterEmail('');
                      setRegisterPassword('');
                      setRegisterCpf('');
                      setValidationStep('idle');
                      setValidationProgress(0);
                      setIsExternalLinkClicked(false);
                    }}
                    className="w-full rounded-xl bg-slate-950 hover:bg-[#540D6E] text-white font-extrabold text-xs py-3.5 uppercase tracking-wider transition-all cursor-pointer shadow-md border-none"
                    id="btn-finish-integration"
                  >
                    Ingressar no Meu Painel de Estudos
                  </button>
                </div>
              )}

            </motion.div>
          </div>
        )}

        {pendingLogin && (
          <div className="fixed inset-0 z-55 flex items-center justify-center p-4">
            {/* Matte dark backdrop */}
            <motion.div 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setPendingLogin(null)}
              className="absolute inset-0 bg-slate-950/85 backdrop-blur-md"
            />

            {/* Tactile secure card container */}
            <motion.div 
              initial={{ scale: 0.95, opacity: 0, y: 30 }}
              animate={{ scale: 1, opacity: 1, y: 0 }}
              exit={{ scale: 0.95, opacity: 0, y: 30 }}
              transition={{ type: "spring", duration: 0.5 }}
              className="relative w-full max-w-sm bg-white border border-slate-200/90 rounded-2xl shadow-2xl p-6 overflow-hidden text-center z-50 text-left"
            >
              {/* Top security header shield badge */}
              <div className="mx-auto h-12 w-12 bg-indigo-50 border border-indigo-150 rounded-full flex items-center justify-center mb-3">
                <Fingerprint className="h-6 w-6 text-indigo-600 animate-pulse" />
              </div>

              <h3 className="font-extrabold text-slate-900 text-sm text-center uppercase tracking-wider">
                Controle de Acesso AVA
              </h3>
              <p className="text-[11px] text-slate-400 text-center mt-0.5">
                Validação de Fluxo de Segurança LGPD
              </p>

              {/* Account details */}
              <div className="mt-4 p-3 bg-slate-50 border border-slate-200/80 rounded-xl flex items-center gap-2.5 mx-auto max-w-[280px]">
                <div className={`h-6 w-6 shrink-0 rounded-full flex items-center justify-center font-bold text-[10px] ${
                  pendingLogin.role === 'admin' 
                    ? 'bg-amber-100 text-amber-800' 
                    : pendingLogin.role === 'instructor' 
                      ? 'bg-emerald-50 text-emerald-700' 
                      : 'bg-[#540D6E]/10 text-[#540D6E]'
                }`}>
                  {pendingLogin.name.charAt(0)}
                </div>
                <div className="text-left">
                  <span className="text-[11px] font-black text-slate-800 block leading-tight">{pendingLogin.name}</span>
                  <span className="text-[8.5px] uppercase text-slate-450 font-mono tracking-wide block">Identidade: {pendingLogin.role.toUpperCase()}</span>
                </div>
              </div>

              {/* Password dot-display indicators */}
              <div className="my-5 space-y-2 text-center">
                <div className="flex justify-center gap-3.5 h-6 items-center">
                  {[...Array(Math.max(4, pinInput.length))].map((_, idx) => (
                    <motion.div 
                      key={idx}
                      animate={idx < pinInput.length ? { scale: [1, 1.2, 1], backgroundColor: '#4f46e5' } : { scale: 1, backgroundColor: '#cbd5e1' }}
                      transition={{ duration: 0.15 }}
                      className="h-3 w-3 rounded-full shadow-3xs"
                    />
                  ))}
                </div>
                
                {pinError && (
                  <span className="text-[10px] text-rose-600 font-bold bg-rose-50 border border-rose-200/60 rounded px-2 py-0.5 inline-block animate-bounce text-center">
                    {pinError}
                  </span>
                )}
                
                {isPinSuccess && (
                  <span className="text-[10px] text-emerald-700 font-bold bg-emerald-50 border border-emerald-250 rounded px-2 py-0.5 inline-block text-center">
                    ✓ Credencial Homologada!
                  </span>
                )}
              </div>

              {/* Tactical 10-key PIN numerical keyboard Pad */}
              <div className="grid grid-cols-3 gap-2.5 max-w-[240px] mx-auto text-center justify-items-center">
                {['1', '2', '3', '4', '5', '6', '7', '8', '9'].map((num) => (
                  <button
                    key={num}
                    onClick={() => handlePinKeyClick(num)}
                    disabled={isPinSuccess}
                    className="h-11 w-11 rounded-full font-mono font-bold text-slate-800 hover:text-indigo-650 bg-slate-50 hover:bg-slate-100 border border-slate-150 flex items-center justify-center cursor-pointer text-sm shadow-3xs active:scale-95 transition-all duration-100"
                  >
                    {num}
                  </button>
                ))}
                
                {/* Clear (backspace equivalent) */}
                <button
                  onClick={() => {
                    setPinInput('');
                    setPinError(null);
                  }}
                  disabled={isPinSuccess}
                  className="h-11 w-full min-w-[44px] rounded-xl font-sans font-bold text-slate-400 hover:text-rose-600 bg-slate-50 hover:bg-slate-100 border border-slate-150 flex items-center justify-center cursor-pointer text-[9px] uppercase shadow-3xs transition-all active:scale-95 px-1"
                >
                  Limpar
                </button>

                {/* Number 0 */}
                <button
                  onClick={() => handlePinKeyClick('0')}
                  disabled={isPinSuccess}
                  className="h-11 w-11 rounded-full font-mono font-bold text-slate-800 hover:text-[#540D6E] bg-slate-50 hover:bg-slate-100 border border-slate-150 flex items-center justify-center cursor-pointer text-sm shadow-3xs hover:border-[#540D6E]/40 active:scale-95 transition-all"
                >
                  0
                </button>

                {/* Confirm key */}
                <button
                  onClick={verifyPinAndLogin}
                  disabled={isPinSuccess || pinInput.length === 0}
                  className={`h-11 w-full min-w-[44px] rounded-xl font-sans font-black flex items-center justify-center cursor-pointer text-[9.5px] uppercase shadow-3xs transition-all active:scale-95 ${
                    pinInput.length > 0 
                      ? 'bg-[#540D6E] hover:bg-[#6e118f] text-white' 
                      : 'bg-slate-100 border border-slate-200 text-slate-300 pointer-events-none'
                  }`}
                >
                  Entrar
                </button>
              </div>

              {/* Demo PIN code guideline disclaimer */}
              <div className="mt-5 p-3 bg-slate-50 border border-slate-200/80 rounded-xl text-left text-[9.5px] text-slate-500 leading-relaxed font-mono">
                <span className="font-bold text-indigo-700 block mb-0.5 uppercase tracking-wide">Dica para Avaliação do Fluxo:</span>
                • Aluno: <code className="font-extrabold text-slate-800">1234</code><br />
                • Gestão: <code className="font-extrabold text-slate-800">5678</code><br />
                • Admin Superior: <code className="font-extrabold text-[#540D6E]">9999</code><br />
                <span className="text-[8.5px] text-slate-400 block mt-1 leading-normal">
                  (Senhas customizadas no perfil também servem para desbloqueio do aluno).
                </span>
              </div>

              {/* Cancel button */}
              <div className="text-center mt-3.5">
                <button
                  onClick={() => setPendingLogin(null)}
                  disabled={isPinSuccess}
                  className="text-[10px] font-black text-slate-400 hover:text-slate-600 uppercase tracking-widest ease-in-out transition-colors cursor-pointer bg-transparent border-none py-1"
                >
                  Voltar ao Portal
                </button>
              </div>

            </motion.div>
          </div>
        )}
      </AnimatePresence>

    </div>
  );
}

export default function App() {
  return (
    <LMSProvider>
      <DashboardSwitcher />
    </LMSProvider>
  );
}
