/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect, useRef } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import {
  PortalView, pathFromView, viewFromPath, caminhoConhecido, trilhaDaView,
  ehCaminhoAutenticado, caminhoBateComPapel, raizDoPapel,
} from './router/portalRoutes';
import { LMSProvider, useLMS } from './context/LMSContext';
import { StudentDashboard } from './components/StudentDashboard';
import { InstructorDashboard } from './components/InstructorDashboard';
import { AdminDashboard } from './components/AdminDashboard';
import { ProfileView } from './components/ProfileView';
import { PageShell } from './components/pages/PageShell';
import { AvaPage } from './components/pages/AvaPage';
import { ProjetoPage } from './components/pages/ProjetoPage';
import { NoticiasPage } from './components/pages/NoticiasPage';
import { DuvidasPage } from './components/pages/DuvidasPage';
import { CalendarioPage } from './components/pages/CalendarioPage';
import { OrientacoesPage } from './components/pages/OrientacoesPage';
import { DEFAULT_NEWS_ITEMS, NEWS_SEARCH_FIELDS } from './components/pages/NoticiasPage';
import { pageField, pageItems, filterSiteItems } from './utils/sitePageContent';
import { canalDeMensagensAberto } from './utils/canalDeMensagens';
import { origemDoPerfil, destinoDoVoltar, rotuloDoVoltar } from './utils/voltarParaOrigem';
import { maskCpf, maskCep, maskCelular, isValidCpf, passwordProblem, PASSWORD_MIN_LENGTH } from './utils/cpf';
import { 
  GraduationCap, User, Award, Video, CheckSquare,
  ArrowRight, ArrowLeft, ShieldCheck, Flame, LogOut, Lock,
  Shield, Activity, Settings, HelpCircle, BookOpen, Palette,
  Search, Menu, Star, Play, FileText, Users, Lightbulb, ChartColumn, MousePointer2, MessagesSquare,
  Mail, ExternalLink, X, Sparkles, Calendar, Info,
  Printer, Download, Monitor, CheckCircle, Instagram, Youtube, Facebook, Twitter, Home, Bell, MessageSquare,
  Fingerprint, AlertTriangle, Check, Eye, EyeOff
} from 'lucide-react';
import { motion, AnimatePresence, useReducedMotion } from 'motion/react';
import { features } from './config/features';
import { NavegacaoPublica } from './components/portal/NavegacaoPublica';
import { MENU_PUBLICO } from './config/menuPublico';
import { Breadcrumb } from './components/shared/Breadcrumb';
import { demoProfiles } from './dev/demoProfiles';
// @ts-ignore

// Avasec Logo Component representing the abstract artistic head profile in primary colors
function AvasecLogo() {
  return (
    <div className="flex items-center gap-2 select-none">
      <svg className="h-10 w-10 shrink-0" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
        {/* Background art circle */}
        <circle cx="50" cy="50" r="46" fill="#f8fafc" stroke="#e2e8f0" strokeWidth="2" />
        {/* Segmentos abstratos da identidade visual da Escola Estadual da Cultura */}
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
      {/*
        O descritor quebrava em QUATRO linhas e saía do esquadro do cabeçalho.

        A causa não era o texto: era o contêiner. O cabeçalho é um flex com
        `justify-between`, e o bloco do logotipo não tinha `shrink-0` — então
        menu, busca e os dois botões o comprimiam até sobrar largura de uma
        palavra. Com `text-sobretitulo` (caixa alta e entreletra `0.12em`),
        "ESCOLA ESTADUAL DA CULTURA" ocupa perto de 260px: espremido, viravam
        quatro linhas desalinhadas do símbolo.

        Duas linhas DELIBERADAS, com `whitespace-nowrap`, em vez de quebra por
        acidente: a assinatura passa a ter largura previsível (~120px, próxima
        da do logotipo "AVASEC") e o conjunto fica alinhado em qualquer largura.
      */}
      <div className="leading-tight text-left">
        <span className="font-sans font-black text-2xl tracking-tight text-ava-acao block">AVASEC</span>
        <span className="text-nota font-semibold uppercase tracking-[0.08em] text-escult-ink-2 mt-1 hidden whitespace-nowrap sm:block">
          Escola Estadual
          <br />
          da Cultura
        </span>
      </div>
    </div>
  );
}

/**
 * Views do portal. O projeto não usa router: a navegação do site público é
 * feita por este estado (o mesmo padrão já adotado pela página `cursos`).
 */
/*
 * `PortalView` mudou de casa para src/router/portalRoutes.ts, onde vive junto do
 * caminho de cada tela. Duas listas — uma de telas, outra de endereços — sairiam
 * de sincronia no primeiro acréscimo.
 */

function DashboardSwitcher() {
  const {
    activeUser,
    loginWithPassword,
    registerUser,
    logoutAuth,
    professorsList,
    studentsList,
    courses,
    webinarEvents,
    directMessages,
    systemSettings,
    sitePageContent,
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
    certificates,
    studentEnrollments
  } = useLMS();
  
const isUserLoggedIn = activeUser && activeUser.name !== '';
  
  // List of students that are simulated in the system
  const unrepliedStudents = studentsList
    .filter(student => {
      const studentDMs = directMessages.filter(m => m.studentUserId === student.id);
      if (studentDMs.length === 0) return false;
      const latestMsg = studentDMs[studentDMs.length - 1];
      return latestMsg.senderRole === 'student'; // Unanswered by the instructor
    })
    .map(s => s.name);
  
  // Navigation & UI States
  /*
   * A URL e a fonte da verdade da navegacao.
   *
   * `currentView` era `useState('landing')`: a URL ficava sempre em `/`, então
   * recarregar jogava a pessoa na landing, não havia link para mandar a alguém e
   * o Voltar do navegador saía do sistema inteiro.
   *
   * A ASSINATURA continua idêntica de propósito. Os 12 pontos que chamam
   * `setCurrentView` seguem como estão — o que mudou é que agora aquilo empurra
   * uma entrada no histórico em vez de trocar um estado invisível.
   */
  const navigate = useNavigate();
  const location = useLocation();
  const currentView: PortalView = viewFromPath(location.pathname) ?? 'landing';
  const setCurrentView = (view: PortalView): void => {
    /*
     * `active_app` vai direto para a raiz do papel. O efeito abaixo também
     * corrigiria `/app`, mas por redirect — e aí seriam duas navegações para um
     * clique, com um piscar da tela errada no meio.
     */
    if (view === 'active_app' && isUserLoggedIn) {
      navigate(raizDoPapel(activeUser.role));

      return;
    }
    navigate(pathFromView(view));
  };

  // Endereço que não é de nenhuma tela vai para a raiz, sem deixar entrada no
  // histórico: senão o Voltar devolveria a pessoa ao endereço quebrado.
  useEffect(() => {
    if (!caminhoConhecido(location.pathname)) {
      navigate(pathFromView('landing'), { replace: true });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location.pathname]);

  /*
   * A área autenticada tem uma raiz por papel, e `/app` era provisório.
   *
   * Isto não é só arrumação de endereço: quem renderiza o painel é o PAPEL, não
   * o caminho. Sem esta correção, um aluno que digitasse `/admin` veria o painel
   * do aluno sob um endereço dizendo "admin" — nada vazava, mas o endereço
   * mentia, e endereço que mente é o que a pessoa manda para outra.
   */
  useEffect(() => {
    if (!isUserLoggedIn || !ehCaminhoAutenticado(location.pathname)) return;

    if (!caminhoBateComPapel(location.pathname, activeUser.role)) {
      navigate(raizDoPapel(activeUser.role), { replace: true });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location.pathname, isUserLoggedIn, activeUser.role]);
  const [isLoginModalOpen, setIsLoginModalOpen] = useState(false);
  
  // Registration and external validator integration states
  const [isRegisterModalOpen, setIsRegisterModalOpen] = useState(false);
  const [registerName, setRegisterName] = useState('');
  const [registerEmail, setRegisterEmail] = useState('');
  const [registerCpf, setRegisterCpf] = useState('');
  const [registerPassword, setRegisterPassword] = useState('');
  // Dados cadastrais completos do aluno (ADR 11).
  const [registerCelular, setRegisterCelular] = useState('');
  const [registerCep, setRegisterCep] = useState('');
  const [registerEndereco, setRegisterEndereco] = useState('');
  const [registerNomeSocial, setRegisterNomeSocial] = useState('');
  const [registerIdentidade, setRegisterIdentidade] = useState('');
  const [validationStep, setValidationStep] = useState<'idle' | 'matching' | 'verifying' | 'syncing' | 'completed'>('idle');
  const [validationProgress, setValidationProgress] = useState(0);
  const [isExternalLinkClicked, setIsExternalLinkClicked] = useState(false);
  const [validationError, setValidationError] = useState<string | null>(null);

  const [dismissedNotice, setDismissedNotice] = useState(false);
  const [loginRoleTab, setLoginRoleTab] = useState<'student' | 'instructor' | 'admin'>('student');
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [globalSearchQuery, setGlobalSearchQuery] = useState('');

  const [searchQuery, setSearchQuery] = useState('');

  // Certificate lookup state
  const [certQuery, setCertQuery] = useState('');
  const [certSearchClicked, setCertSearchClicked] = useState(false);
  const [certLookupResult, setCertLookupResult] = useState<any | null>(null);

  // Accessibility & Multi-language Internationalization (Transient UI states only)
  const [isAccessibilityOpen, setIsAccessibilityOpen] = useState(false);
  const [isSiteMapOpen, setIsSiteMapOpen] = useState(false);
  /*
    Sub-aba em que o Perfil abre. Existe porque os certificados vivem numa
    sub-aba do Perfil, sem endereco proprio: quem clica em "Certificados"
    no painel precisa cair nela, e nao na aba de dados pessoais.
  */
  const [perfilAbaInicial, setPerfilAbaInicial] = useState<'profile' | 'password' | 'certificates'>('profile');
  /*
    De onde o Perfil foi aberto, para o Voltar levar de volta para la — e nao
    para a raiz do painel, que obrigava o aluno a achar de novo o curso e a
    aula em que estava. Ver utils/voltarParaOrigem.
  */
  const [perfilOrigem, setPerfilOrigem] = useState<string | null>(null);
  /*
    Ultimo endereco do painel em que a pessoa esteve. "Ambiente de Estudos" e
    "Gestao", no cabecalho do portal, levavam sempre para a raiz do painel —
    quem saia de uma aula para ver uma noticia voltava para a escolha de curso.
  */
  const [ultimoPainel, setUltimoPainel] = useState<string | null>(null);
  useEffect(() => {
    // Sair da conta apaga a volta: o proximo login nao herda o painel de outra pessoa.
    if (!isUserLoggedIn) {
      setUltimoPainel(null);
    } else if (ehCaminhoAutenticado(location.pathname)) {
      setUltimoPainel(`${location.pathname}${location.search}`);
    }
  }, [location.pathname, location.search, isUserLoggedIn]);
  const voltarAoPainel = (): void => {
    navigate(destinoDoVoltar(ultimoPainel, isUserLoggedIn ? activeUser.role : null, raizDoPapel(activeUser.role)));
  };
  const abrirPerfil = (aba: 'profile' | 'password' | 'certificates'): void => {
    // Clicar em "Perfil" estando no Perfil nao apaga o caminho de volta.
    if (currentView !== 'perfil') setPerfilOrigem(origemDoPerfil(location.pathname, location.search));
    setPerfilAbaInicial(aba);
    setCurrentView('perfil');
  };

  // PIN Verification Flow Security States
  const [pendingLogin, setPendingLogin] = useState<{ name: string; role: 'student' | 'instructor' | 'admin' } | null>(null);
  /*
   * SENHA, não PIN. Era um teclado de 10 dígitos com `replace(/\D/g,'')` e
   * `maxLength={8}`: dois limites independentes que tornavam senha alfanumérica
   * impossível de digitar. A política real (ADR 11) sempre exigiu letra e
   * dígito com no mínimo 8 caracteres, então a tela contradizia a regra do
   * backend — e no dia em que as senhas de demonstração foram rotacionadas o
   * gestor e o admin ficaram sem forma de entrar.
   *
   * Os nomes foram trocados junto com o campo: `pinInput` guardando uma senha
   * é o tipo de nome que faz a próxima pessoa reintroduzir o limite numérico.
   */
  const [senhaInput, setSenhaInput] = useState<string>('');
  const [senhaErro, setSenhaErro] = useState<string | null>(null);
  const [senhaOk, setSenhaOk] = useState<boolean>(false);
  const [senhaVisivel, setSenhaVisivel] = useState<boolean>(false);
  const senhaInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (pendingLogin) {
      setSenhaInput('');
      setSenhaErro(null);
      setSenhaOk(false);
      // A senha volta oculta a cada abertura: "mostrar" é para conferir o que
      // se digitou agora, não uma preferência que persiste na próxima entrada.
      setSenhaVisivel(false);
      const timer = setTimeout(() => {
        senhaInputRef.current?.focus();
      }, 350);
      return () => clearTimeout(timer);
    }
  }, [pendingLogin]);

  useEffect(() => {
    const isLocked = localStorage.getItem('ava_session_locked') === 'true';
    if (isLocked) {
      setIsLoginModalOpen(true);
      setCurrentView('landing');
    }
  }, []);

  const speakText = (text: string) => {
    if (!isSpeechEnabled) return;
    if ('speechSynthesis' in window && 'SpeechSynthesisUtterance' in window) {
      try {
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = currentLang === 'pt' ? 'pt-BR' : currentLang === 'en' ? 'en-US' : 'es-ES';
        window.speechSynthesis.speak(utterance);
      } catch (err) {
        console.warn("Speech synthesis error:", err);
      }
    }
  };

  const translations = {
    pt: {
      heroBadge: "Inscrições Abertas — Cursos Livres de Qualificação",
      heroTitleLine1: "Escola Estadual da Cultura",
      heroTitleLine2: "de Cultura e Economia Criativa",
      heroDesc: "A AVASEC é uma plataforma de cursos on-line destinada à formação e qualificação profissional em Cultura e Economia Criativa por meio da oferta de Cursos Livres. Cadastre-se e comece já!",
      btnDiscover: "Descubra mais",
      btnStart: "Comece a estudar",
      gradeTitle: "Pilares do Aprendizado",
      gradeSubtitle: "Diferenciais do Ensino Livre",
      gradeCard1: "Alta Aplicabilidade",
      gradeCard1Desc: "Atividades pontuais de curta duração e alta aplicabilidade prática, ideais para sintonização rápida a novos processos tecnológicos e criativos do cenário nacional.",
      gradeCard2: "Certificação Oficial",
      gradeCard2Desc: "Conclua as trilhas de estudo e emita seu certificado oficial digital, válido para comprovação de competência, editais e horas curriculares.",
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
      heroBadge: "Open Applications — Qualification Courses",
      heroTitleLine1: "Escola Estadual da Cultura",
      heroTitleLine2: "of Culture and Creative Economy",
      heroDesc: "AVASEC is an online course platform dedicated to professional qualification in Culture and Creative Economy through the offer of Courses.",
      btnDiscover: "Discover more",
      btnStart: "Start Studying",
      gradeTitle: "Learning Pillars",
      gradeSubtitle: "Course Benefits",
      gradeCard1: "High Applicability",
      gradeCard1Desc: "Short-term classes with immediate practical applicability, perfect for quickly tuning into new technological and creative workflows in the cultural market.",
      gradeCard2: "Official Certification",
      gradeCard2Desc: "Complete your study paths and issue your official digital certificate, fully valid for cultural grants, bids, and academic credentials.",
      gradeCard3: "Flexible Pace",
      gradeCard3Desc: "Learn from anywhere, at your own pace and schedule, with our responsive online platform designed to fit your creative career.",
      coursesTitle: "Interactive Training",
      coursesSubtitle: "Our Available Courses",
      newsTitle: "Latest News",
      newsSubtitle: "Recent Notices",
      faqTitle: "Faq & Student Support",
      faqSubtitle: "Frequently Asked Questions",
    },
    es: {
      heroBadge: "Inscripciones Abiertas — Cursos Libres de Calificación",
      heroTitleLine1: "Escola Estadual da Cultura",
      heroTitleLine2: "de Cultura y Economía Creativa",
      heroDesc: "AVASEC es una plataforma de cursos en línea dedicada a la capacitación profesional en Cultura y Economía Creativa mediante la oferta de Cursos Libres.",
      btnDiscover: "Descubre más",
      btnStart: "Comience a estudiar",
      gradeTitle: "Pilares del Aprendizaje",
      gradeSubtitle: "Beneficios de los Cursos Libres",
      gradeCard1: "Alta Aplicación",
      gradeCard1Desc: "Sesiones formativas cortas y de alta aplicación práctica, ideales para sintonizar rápidamente con nuevos flujos creativos y tecnológicos en el mercado.",
      gradeCard2: "Certificación Oficial",
      gradeCard2Desc: "Complete las rutas de estudio y emita su certificado oficial digital, ideal para convocatorias de incentivo y créditos curriculares.",
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

  // Filtros do catálogo unificado de cursos (busca + categoria).
  const [courseCategory, setCourseCategory] = useState<string>('all');
  const [courseSearch, setCourseSearch] = useState('');

  /*
   * Brilho que segue o cursor no heroi.
   *
   * `useReducedMotion` porque isto e movimento em JAVASCRIPT: a `@media
   * (prefers-reduced-motion)` do index.css alcanca animacao e transicao de CSS,
   * e nao um `left/top` recalculado a cada `mousemove`. Sem esta guarda, quem
   * pede menos movimento no sistema continuaria com um brilho perseguindo o
   * cursor pela tela.
   */
  const movimentoReduzido = useReducedMotion();
  const heroRef = useRef<HTMLDivElement>(null);
  const [mousePos, setMousePos] = useState({ x: 0, y: 0 });
  const [isMouseInHero, setIsMouseInHero] = useState(false);

  const handleHeroMouseMove = (e: React.MouseEvent) => {
    if (movimentoReduzido) return;
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

  // Mock Students
  const mockStudentProfiles = studentsList;

  // Highlighted Featured Courses dataset inspired by Image 5
  const featuredCoursesData = [
    {
      title: 'Inteligência Artificial e Cultura 2ª Oferta',
      category: 'Economia Criativa & IA',
      instructor: 'Gestor de Conteúdos',
      iconType: 'mic',
      iconBg: 'bg-ava-acao',
      image: 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=500&auto=format&fit=crop&q=60',
      description: 'Aprenda a aplicar ferramentas de Inteligência Artificial generativa no fomento, roteirização e design de projetos de artes integradas.'
    },
    {
      title: 'Produção Audiovisual 2ª Oferta',
      category: 'Áreas Técnicas',
      instructor: 'Gestor de Conteúdos',
      iconType: 'video',
      iconBg: 'bg-ava-acao',
      image: 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?w=500&auto=format&fit=crop&q=60',
      description: 'Da captação de áudio e iluminação até as técnicas de edição e publicação. Um guia prático para criadores independentes.'
    },
    {
      title: 'Submissão de Propostas Simplificadas 2ª Oferta',
      category: 'Políticas e Gestão Culturais',
      instructor: 'Gestor de Conteúdos',
      iconType: 'building',
      iconBg: 'bg-[#FFD23F]',
      image: 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?w=500&auto=format&fit=crop&q=60',
      description: 'Inscreva sua proposta cultural sem complicação. Compreenda leis de incentivo e preenchimento técnico de formulários oficiais.'
    },
    {
      title: 'Prestação de Contas de Propostas Simplificadas 2ª Oferta',
      category: 'Políticas e Gestão Culturais',
      instructor: 'Gestor de Conteúdos',
      iconType: 'columns',
      iconBg: 'bg-[#FFD23F]',
      image: 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=500&auto=format&fit=crop&q=60',
      description: 'Como organizar recibos, despesas e relatórios de atividades para certificar que os fundos recebidos foram devidamente executados.'
    },
    {
      title: 'UX/UI Design: Interfaces de Alta Performance',
      category: 'Economia Criativa & IA',
      instructor: 'Gestor de Conteúdos',
      iconType: 'mic',
      iconBg: 'bg-ava-acao',
      image: 'https://images.unsplash.com/photo-1559028006-448665bd7c7f?w=500&auto=format&fit=crop&q=60',
      description: 'Aprenda do zero ao avançado como planejar, estruturar e prototipar sistemas complexos utilizando as melhores práticas do Figma, Design Systems e testes de usabilidade.'
    },
    {
      title: 'Fotografia Cultural e Patrimônio Histórico',
      category: 'Áreas Técnicas',
      instructor: 'Gestor de Conteúdos',
      iconType: 'video',
      iconBg: 'bg-ava-acao',
      image: 'https://images.unsplash.com/photo-1495707902641-75cac588d2e9?w=500&auto=format&fit=crop&q=60',
      description: 'Técnicas de composição, luz natural e pós-produção para registrar acervos, monumentos e manifestações culturais com qualidade profissional.'
    },
    {
      title: 'Gestão de Editais e Leis de Incentivo à Cultura',
      category: 'Políticas e Gestão Culturais',
      instructor: 'Gestor de Conteúdos',
      iconType: 'building',
      iconBg: 'bg-[#FFD23F]',
      image: 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=500&auto=format&fit=crop&q=60',
      description: 'Panorama completo das principais leis de fomento cultural (Rouanet, ICMS Cultural, editais municipais) e como estruturar propostas competitivas.'
    },
    {
      title: 'Empreendedorismo Criativo e Modelos de Negócio',
      category: 'Economia Criativa & IA',
      instructor: 'Gestor de Conteúdos',
      iconType: 'columns',
      iconBg: 'bg-ava-acao',
      image: 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=500&auto=format&fit=crop&q=60',
      description: 'Do plano de negócios à precificação: como transformar talento artístico e cultural em iniciativas sustentáveis e escaláveis.'
    },
    {
      title: 'Produção de Podcast e Narrativas Sonoras',
      category: 'Áreas Técnicas',
      instructor: 'Gestor de Conteúdos',
      iconType: 'mic',
      iconBg: 'bg-[#FFD23F]',
      image: 'https://images.unsplash.com/photo-1478737270239-2f02b77fc618?w=500&auto=format&fit=crop&q=60',
      description: 'Roteiro, captação, edição e distribuição de podcasts — um guia prático para criadores que querem contar histórias em áudio.'
    }
  ];

  // Catálogo unificado: categorias disponíveis (derivadas dos cursos) + lista filtrada
  // por categoria e por texto de busca. Fonte única = featuredCoursesData.
  const courseCategories = ['all', ...Array.from(new Set(featuredCoursesData.map((c) => c.category)))];
  const filteredCourses = featuredCoursesData.filter((course) => {
    const matchesCategory = courseCategory === 'all' || course.category === courseCategory;
    const q = courseSearch.trim().toLowerCase();
    const matchesText =
      q === '' ||
      course.title.toLowerCase().includes(q) ||
      course.description.toLowerCase().includes(q);
    return matchesCategory && matchesText;
  });

  // Dynamic matched searches
  const matchedCourses = searchQuery.trim() === '' 
    ? [] 
    : featuredCoursesData.filter(course => 
        course.title.toLowerCase().includes(searchQuery.toLowerCase()) || 
        course.description.toLowerCase().includes(searchQuery.toLowerCase())
      );

  // Notícias da busca do topo: usa o que o admin publicou, caindo no padrão
  // embutido enquanto a API não respondeu.
  const newsForSearch = pageItems(sitePageContent?.['noticias'], DEFAULT_NEWS_ITEMS);
  const matchedNews = filterSiteItems(newsForSearch, searchQuery, NEWS_SEARCH_FIELDS);

  // Página de certificados: o texto é editável pelo admin, mas a lógica de
  // verificação segue no código (só o conteúdo institucional vem do banco).
  const certContent = sitePageContent?.['certificados'];
  const certCriteria = pageItems(certContent, [
    {
      id: 'criterio-1',
      title: '70% de Frequência Mínima:',
      description: 'Calculada automaticamente pelas videoaulas assistidas por completo e presenças nas mentorias síncronas do Calendário.',
    },
    {
      id: 'criterio-2',
      title: 'Nota no Questionário Final:',
      description: 'Atingir nota igual ou superior a 70% de acertos nos questionários avaliativos de cada módulo do curso.',
    },
    {
      id: 'criterio-3',
      title: 'Emissão Sem Complicações:',
      description: 'O botão de download do certificado em PDF ficará visível na aba "Certificados" do seu Painel de Estudos assim que as metas forem cumpridas.',
    },
  ]);

  const handleProfileLogin = (name: string, role: 'student' | 'instructor' | 'admin') => {
    // Intercept with security PIN prompt
    setPendingLogin({ name, role });
    setSenhaInput('');
    setSenhaErro(null);
    setSenhaOk(false);
    setSenhaVisivel(false);
    speakText(`Verificação de segurança requerida para o perfil de ${name}. Digite o PIN de acesso.`);
  };

  const [isPinVerifying, setIsPinVerifying] = useState(false);

  // Login de aluno por CPF + senha (ADR 11) — substitui a lista de perfis demo.
  const [studentLoginCpf, setStudentLoginCpf] = useState('');
  const [studentLoginPassword, setStudentLoginPassword] = useState('');
  const [studentLoginError, setStudentLoginError] = useState<string | null>(null);
  const [isStudentLoggingIn, setIsStudentLoggingIn] = useState(false);

  const submitStudentLogin = async () => {
    if (isStudentLoggingIn) return;
    setStudentLoginError(null);

    if (!isValidCpf(studentLoginCpf)) {
      setStudentLoginError('Informe um CPF válido.');
      speakText('Informe um CPF válido.');
      return;
    }
    if (!studentLoginPassword) {
      setStudentLoginError('Informe sua senha.');
      return;
    }

    setIsStudentLoggingIn(true);
    const result = await loginWithPassword(studentLoginCpf, studentLoginPassword);
    setIsStudentLoggingIn(false);

    if (result.ok && result.user) {
      addSecurityLog('Autenticação de Fluxo', `Login por CPF efetuado: ${result.user.name}.`, 'SUCCESS');
      setStudentLoginCpf('');
      setStudentLoginPassword('');
      setCurrentView('active_app');
      setIsLoginModalOpen(false);
      localStorage.removeItem('ava_session_locked');
      window.scrollTo({ top: 0, behavior: 'smooth' });
      speakText(`Acesso liberado. Bem-vindo, ${result.user.name}.`);
    } else {
      setStudentLoginError(result.error || 'CPF ou senha inválidos.');
      setStudentLoginPassword('');
      speakText(result.error || 'CPF ou senha inválidos.');
      addSecurityLog('Tentativa Fracassada', 'Falha de login por CPF.', 'FAILED');
    }
  };

  const executeProfileLogin = (name: string, _role: 'student' | 'instructor' | 'admin') => {
    // A identidade ativa deriva de authUser (setado pelo loginWithPassword) — ADR 10.
    addSecurityLog('Autenticação de Fluxo', `Login efetuado com PIN para o perfil: ${name}.`, 'SUCCESS');
    setCurrentView('active_app');
    setIsLoginModalOpen(false);
    setPendingLogin(null);
    localStorage.removeItem('ava_session_locked');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    speakText(`Acesso liberado com sucesso. Bem vindo de volta, ${name}.`);
  };

  const handleLogout = () => {
    logoutAuth();
    setCurrentView('landing');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    speakText("Você desconectou do sistema com sucesso.");
  };

  const verificarSenhaEEntrar = async () => {
    if (!pendingLogin || isPinVerifying || senhaInput === '') return;
    setIsPinVerifying(true);

    // Autentica de verdade contra o backend (bcrypt + JWT) — sem PINs fixos de fallback.
    // O papel do cartão vai junto: o servidor só aceita uma conta desse papel.
    const result = await loginWithPassword(pendingLogin.name, senhaInput, pendingLogin.role);
    setIsPinVerifying(false);

    /*
      Segunda trava, no cliente: conta de outro papel que o cartão clicado NUNCA
      entra. O cartão de "Gestão" chegou a autenticar um aluno homônimo, e a
      tela aceitava o papel devolvido. A sessão aberta por engano é encerrada
      na hora, com a mesma mensagem genérica de senha errada.
    */
    if (result.ok && result.user && result.user.role !== pendingLogin.role) {
      logoutAuth();
      setSenhaErro('Usuário ou senha inválidos.');
      setSenhaInput('');
      senhaInputRef.current?.focus();
      addSecurityLog('Tentativa Fracassada', `Papel da conta diferente do cartão (${pendingLogin.role}) para: ${pendingLogin.name}.`, 'FAILED');

      return;
    }

    if (result.ok && result.user) {
      setSenhaOk(true);
      const confirmedRole = result.user.role;
      setTimeout(() => {
        executeProfileLogin(pendingLogin.name, confirmedRole);
      }, 700);

      return;
    }

    /*
     * A mensagem do servidor é genérica de propósito (não revela se a conta
     * existe) e é ela que aparece. O texto antigo dizia "PIN ou Senha de
     * segurança inválida!", o que descrevia um campo que já não existe — e
     * mensagem que fala de PIN manda a pessoa procurar um teclado numérico.
     */
    setSenhaErro(result.error ?? 'Usuário ou senha inválidos.');
    setSenhaInput('');
    senhaInputRef.current?.focus();
    speakText(`Falha de verificação. Senha incorreta para o perfil ${pendingLogin.name}.`);
    addSecurityLog('Tentativa Fracassada', `Senha incorreta inserida para o perfil: ${pendingLogin.name}.`, 'FAILED');
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

  // Consulta pública dedicada (não exige login) — o servidor busca no banco completo,
  // não apenas nos certificados já carregados na sessão do visitante.
  const runCertLookup = async (query: string) => {
    setCertSearchClicked(true);
    if (!query.trim()) {
      setCertLookupResult(null);
      return;
    }

    try {
      const res = await fetch('/api/certificates/verify?q=' + encodeURIComponent(query.trim()));
      const found = res.ok ? await res.json() : null;

      if (found) {
        setCertLookupResult(found);
        speakText(found.revogado === true
          ? `Atenção: o certificado de ${found.studentName} foi revogado e não tem validade.`
          : `Certificado encontrado para o aluno ${found.studentName}.`);
      } else {
        setCertLookupResult(null);
        speakText("Nenhum certificado correspondente a esta busca foi encontrado.");
      }
    } catch (err) {
      console.error('Erro ao consultar certificado:', err);
      setCertLookupResult(null);
      speakText("Não foi possível consultar o certificado agora. Tente novamente em instantes.");
    }
  };

  const handleCertLookup = async (e: React.FormEvent) => {
    e.preventDefault();
    await runCertLookup(certQuery);
  };

  // Deep-link do QR impresso no PDF do certificado (ADR 09): /?verify=AVA-...
  // pré-preenche o autenticador, dispara a busca e abre a página de
  // certificados (que deixou de ser uma seção da landing).
  useEffect(() => {
    const hash = new URLSearchParams(window.location.search).get('verify');
    if (!hash) return;
    setCertQuery(hash);
    runCertLookup(hash);
    /*
     * O `?verify=` VIAJA junto para /certificados, e isto não é detalhe: há
     * certificado impresso em circulação com o QR apontando para `/?verify=...`.
     * Levar só o caminho deixaria a URL sem o código, e recarregar a página
     * (ou mandar o endereço adiante) perderia a consulta que a pessoa veio fazer.
     *
     * `replace` para o Voltar não devolver ao endereço antigo e disparar a busca
     * de novo.
     */
    navigate(`${pathFromView('certificados')}?verify=${encodeURIComponent(hash)}`, { replace: true });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Navega para uma página dedicada do portal público, sempre a partir do topo.
  const goToPage = (view: PortalView, spokenLabel: string) => {
    setCurrentView(view);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    speakText(spokenLabel);
  };

  // Cabeçalho de navegação/busca aparece em toda a área pública, não só na
  // landing — é assim que se alcança as páginas dedicadas.
  const isPublicPage = currentView !== 'active_app' && currentView !== 'perfil';

  // Fonte única do menu público (usada no desktop e no dropdown mobile).
  /*
   * `publicNavItems` (nove entradas) foi REMOVIDO. A estrutura do menu publico
   * vive em `src/config/menuPublico.ts`, com quatro entradas — dois links e dois
   * grupos — e a regra de qual delas acende em cada pagina, que e testada.
   */

  /*
   * Tema do portal (planejamento 12): vale nas paginas publicas e fica de fora
   * do painel de cada perfil e do Perfil, que o admin tambem usa. O admin nao
   * muda; aluno e instrutor mudam em fase propria, de uma vez, para o painel
   * nao ficar metade em cada paleta.
   */
  const temaPortal = currentView !== 'active_app' && currentView !== 'perfil';

  return (
    <div className={`min-h-screen bg-white flex flex-col justify-between text-slate-800 font-sans selection:bg-slate-900 selection:text-white transition-colors duration-300 ${temaPortal ? 'tema-ava' : ''} ${accessibilitySettings.highContrast ? 'high-contrast-active' : ''} ${textSizeMultiplier !== 1.0 ? 'text-scaled-active' : ''}`}>
      
      {/* Dynamic Style Injections for High Contrast and Text Scaler */}
      {accessibilitySettings.highContrast && (
        <style dangerouslySetInnerHTML={{ __html: `
          /*
            Alto contraste como TEMA DE TOKENS, e nao como sobrescrita cega.
            Ha um limite real, e ele esta escrito aqui porque volta a morder
            quem tentar "so remapear a paleta":

            1. O Tailwind 4 emite 'var(--color-*)', entao redefinir token cascateia
               para toda classe da paleta. Mas o MESMO passo serve papeis opostos
               neste codigo: 'text-slate-300' e TEXTO em 37 lugares e
               'bg-slate-900' e FUNDO em 84. Mapear "tom claro = fundo" inverteria
               os dois e apagaria o texto.
            2. Ha 337 cores arbitrarias ('bg-ava-acao'), que sao valor literal e
               nao passam por token nenhum.

            Por isso o fundo e o texto ainda precisam de uma regra ampla — mas
            agora ela tem excecoes nomeadas, os valores vivem em tokens (um lugar
            para mudar) e o foco existe, o que antes nao acontecia.
          */
          .high-contrast-active {
            --hc-fundo: #000000;
            --hc-texto: #ffffff;
            --hc-borda: #ffff00;
            --hc-acao: #ffff00;
            --hc-foco: #ffffff;
          }

          /*
            ':not(svg):not(svg *)' e o que impede o estrago antigo: a regra
            universal pintava o interior dos icones e do logotipo de preto.
          */
          .high-contrast-active,
          .high-contrast-active *:not(svg):not(svg *):not(img):not(video):not(canvas) {
            background-color: var(--hc-fundo) !important;
            color: var(--hc-texto) !important;
            border-color: var(--hc-borda) !important;
            text-shadow: none !important;
            box-shadow: none !important;
            background-image: none !important;
          }

          .high-contrast-active a,
          .high-contrast-active button,
          .high-contrast-active [role="button"] {
            color: var(--hc-acao) !important;
            border: 2px solid var(--hc-borda) !important;
            text-decoration: underline !important;
          }

          .high-contrast-active a:hover,
          .high-contrast-active button:hover {
            background-color: var(--hc-acao) !important;
            color: var(--hc-fundo) !important;
          }

          /*
            Foco visivel: nao existia nenhuma regra de foco aqui. Num tema que
            zera sombra e fundo, o anel de foco padrao do navegador desaparece —
            e quem usa alto contraste com frequencia navega por teclado.
          */
          .high-contrast-active :focus-visible {
            outline: 3px solid var(--hc-foco) !important;
            outline-offset: 2px !important;
          }

          .high-contrast-active input,
          .high-contrast-active textarea,
          .high-contrast-active select {
            background-color: var(--hc-fundo) !important;
            color: var(--hc-texto) !important;
            border: 2.5px solid var(--hc-borda) !important;
          }

          /*
            A regra que existia aqui era:
              svg, svg * { stroke:#ffff00 !important; fill:none !important }
            e ela APAGAVA o logotipo (desenhado com 'fill'), os icones dos cartoes
            e qualquer grafico. Os icones do lucide-react desenham com
            'stroke=currentColor', entao definir a COR do svg basta.
          */
          .high-contrast-active svg {
            color: var(--hc-acao) !important;
          }

          /*
            Imagem e video ficam FORA da regra ampla acima (e nao apenas com uma
            regra propria depois): o :not() dela soma tres seletores de elemento,
            entao '.high-contrast-active img' perdia a disputa de especificidade
            e a figura continuava com fundo preto.
          */
          .high-contrast-active img,
          .high-contrast-active video,
          .high-contrast-active canvas {
            background-color: transparent !important;
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
      {/*
        Bloco 2 do handoff. O `backdrop-blur` com `/95` saiu: sobre um heroi
        colorido o texto do cabecalho ficava com contraste variavel conforme o
        que rolava atras. Fundo solido resolve, e a altura vai a 84px para caber
        o menu de 15px sem aperto.
      */}
      <header className="sticky top-0 z-40 bg-white border-b border-[#e4e1dc] ava:border-ava-borda shadow-3xs">
        {/*
          Celular com a pessoa logada (planejamento 13): logotipo e menu numa
          linha, "Sair" e "Ambiente de Estudos" na de baixo, com o nome inteiro.
          Antes os tres disputavam 375px e a pagina rolava para o lado. So no
          tema do portal: no painel do admin o cabecalho segue como era.
        */}
        <div className={`mx-auto max-w-7xl px-4 h-[84px] md:px-6 flex items-center justify-between gap-4 ${
          isUserLoggedIn ? 'ava:max-sm:h-auto ava:max-sm:min-h-[72px] ava:max-sm:flex-wrap ava:max-sm:py-3 ava:max-sm:gap-y-3' : ''
        }`}>
          
          {/*
            Logo & Brand title.

            `shrink-0`: a assinatura da escola não é o que cede espaço quando o
            cabeçalho aperta. Era ela a única peça compressível da barra, e por
            isso o descritor se esmagava em quatro linhas fora do esquadro.
          */}
          <div
            onClick={() => {
              setCurrentView('landing');
              window.scrollTo({ top: 0, behavior: 'smooth' });
              speakText("Voltando para a Página Inicial.");
            }}
            title="Voltar ao Portal Inicial"
            className="shrink-0 cursor-pointer hover:opacity-95 transition-all"
          >
            <AvasecLogo />
          </div>

          {/*
            O menu nao desaparece mais quando a busca abre: a busca deixou de ser
            uma lupa que expande e virou campo permanente, entao nao ha mais o
            que esconder. `!isSearchOpen` estava aqui por causa daquele arranjo.
          */}
          {isPublicPage && <NavegacaoPublica view={currentView} irPara={goToPage} />}

          {/* Right Header Controls / Sign In or Sign Out buttons */}
          <div className={`flex items-center gap-3 ${isUserLoggedIn ? 'ava:max-sm:contents' : ''}`}>
            {/*
              Busca PERMANENTE, e nao uma lupa de 18px que expande um campo.

              A lupa custava dois cliques e escondia a propria existencia da
              busca; ao abrir, ela ainda ocultava o menu inteiro (`!isSearchOpen`
              na nav). Campo visivel resolve os dois de uma vez.

              E o clique num resultado de curso deixou de mentir: chamava
              `goToPage('cursos')` anunciando "Navegando para o curso X" e
              entregava o catalogo inteiro. Nao existe pagina publica de detalhe
              do curso (o handoff registra isso como tela que falta), entao aqui
              se faz o proximo passo honesto: abrir o catalogo JA FILTRADO
              naquele curso, escrevendo o titulo no filtro que o catalogo usa.
            */}
            {/*
              Busca a partir de 1280px, e não de 768px.

              Medido no navegador em 1024: os filhos do cabeçalho somavam
              1170px numa barra de 1009, e `document.scrollWidth` dava 1226 —
              a PÁGINA inteira rolava na horizontal. O logotipo vinha
              absorvendo o excesso por ser o único elemento compressível, e era
              isso que o quebrava em quatro linhas fora do esquadro.

              Entre os três candidatos a ceder espaço, a busca é o de menor
              custo: o menu dá acesso a tudo que ela alcança, e nenhuma outra
              tela manda usá-la. "Cadastre-se" foi descartado porque o modal de
              login diz, com todas as letras, "feche esta janela e clique em
              Cadastre-se" — escondê-lo quebraria essa instrução.
            */}
            {isPublicPage && (
              <div className="relative z-50 hidden xl:block">
                <div className="flex items-center bg-[#f4f2ef] ava:bg-ava-faixa rounded-[10px] border border-[#e4e1dc] ava:border-ava-borda w-[230px] pl-3 pr-2 py-2.5 focus-within:border-ava-acao transition-colors">
                  <Search className="h-4 w-4 text-[#6b7385] shrink-0 mr-2" aria-hidden="true" />
                  <input
                    type="text"
                    placeholder="Buscar cursos e notícias"
                    aria-label="Buscar cursos e notícias no portal"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="bg-transparent border-0 outline-none text-apoio w-full font-medium text-[#1d2432] placeholder:text-[#6b7385]"
                    onKeyDown={(e) => { if (e.key === 'Escape') setSearchQuery(''); }}
                  />
                  {searchQuery.trim().length > 0 && (
                    <button
                      onClick={() => setSearchQuery('')}
                      title="Limpar busca"
                      aria-label="Limpar busca"
                      className="p-0.5 text-[#6b7385] hover:text-[#1d2432] rounded-full hover:bg-[#e4e1dc] cursor-pointer shrink-0"
                    >
                      <X className="h-3.5 w-3.5" />
                    </button>
                  )}
                </div>

                {searchQuery.trim().length > 0 && (
                  <div className="absolute top-[calc(100%+8px)] right-0 w-80 bg-white border border-[#e4e1dc] shadow-xl rounded-[14px] p-4 z-[100] text-[#1d2432] text-left space-y-3.5 animate-in fade-in slide-in-from-top-2 duration-200 max-h-[350px] overflow-y-auto">
                    <span className="text-apoio font-semibold text-[#6b7385] block">Resultados da busca</span>

                    {matchedCourses.length === 0 && matchedNews.length === 0 && (
                      <p className="text-apoio text-[#4a5468] py-2">Nada encontrado para "{searchQuery}".</p>
                    )}

                    {matchedCourses.length > 0 && (
                      <div className="space-y-1.5">
                        <span className="text-apoio font-semibold text-ava-acao block">Cursos ({matchedCourses.length})</span>
                        {matchedCourses.map((c, i) => (
                          <button
                            key={i}
                            onClick={() => {
                              setCourseSearch(c.title);
                              setSearchQuery('');
                              goToPage('cursos', `Catálogo filtrado no curso ${c.title}`);
                            }}
                            className="w-full text-left hover:bg-[#f4f2ef] p-2 rounded-[10px] transition-all cursor-pointer flex items-center gap-2"
                          >
                            <div className="h-8 w-8 rounded-lg bg-ava-acao/5 text-ava-acao text-xs font-bold font-titulo flex items-center justify-center shrink-0">C</div>
                            <div className="min-w-0">
                              <span className="block text-apoio font-semibold text-[#1d2432] truncate leading-snug">{c.title}</span>
                              <span className="block text-apoio text-[#6b7385] truncate">{c.category}</span>
                            </div>
                          </button>
                        ))}
                      </div>
                    )}

                    {matchedNews.length > 0 && (
                      <div className="space-y-1.5 pt-2 border-t border-[#e4e1dc]">
                        <span className="text-apoio font-semibold text-[#EE4266] ava:text-ava-acao block">Notícias ({matchedNews.length})</span>
                        {matchedNews.map((n, i) => (
                          <button
                            key={i}
                            onClick={() => {
                              // Mantem `searchQuery` para a pagina abrir filtrada.
                              goToPage('noticias', `Notícias filtradas em: ${n.title}`);
                            }}
                            className="w-full text-left hover:bg-[#f4f2ef] p-2 rounded-[10px] transition-all cursor-pointer flex items-center gap-2"
                          >
                            <div className="h-8 w-8 rounded-lg bg-[#EE4266]/5 text-[#EE4266] ava:bg-ava-acao/5 ava:text-ava-acao text-xs font-bold font-titulo flex items-center justify-center shrink-0">N</div>
                            <div className="min-w-0">
                              <span className="block text-apoio font-semibold text-[#1d2432] truncate leading-snug">{n.title}</span>
                              <span className="block text-apoio text-[#6b7385] truncate">{n.tag}</span>
                            </div>
                          </button>
                        ))}
                      </div>
                    )}
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
                  <Home className="h-3.5 w-3.5 text-ava-acao" />
                  <span className="hidden sm:inline">Página Inicial</span>
                </button>

                {/*
                  O sino exigia so `features.mensagensDiretas`, enquanto a aba de
                  mensagens exige `forum && mensagensDiretas && allowDirectMessages`.
                  Com `forum: false` — a configuracao de hoje — havia sino ativo e
                  piscando levando a uma aba que nao existe, e a rolagem procurava
                  `#chat-portal-section`, que so o painel do ALUNO renderiza.
                  Agora sino e aba leem a mesma funcao.
                */}
                {canalDeMensagensAberto(activeUser.role, features, systemSettings) && (
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
                                chatSection.classList.add('ring-4', 'ring-ava-acao/30');
                                setTimeout(() => {
                                  chatSection.classList.remove('ring-4', 'ring-ava-acao/30');
                                }, 2500);
                              }
                            }, 150);
                          }}
                          className="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-150 hover:border-rose-300 text-rose-600 flex items-center justify-center animate-pulse transition-all cursor-pointer relative"
                          title={`${unrepliedStudents.length} conversa(s) aguardando resposta dos alunos: ${unrepliedStudents.join(', ')}`}
                        >
                          <Bell className="h-4 w-4 text-rose-500 fill-rose-200 animate-bounce" />
                          <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-600 text-apoio font-black text-white ring-2 ring-rose-100">
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
                                chatSection.classList.add('ring-4', 'ring-ava-acao/20');
                                setTimeout(() => {
                                  chatSection.classList.remove('ring-4', 'ring-ava-acao/20');
                                }, 2500);
                              }
                            }, 150);
                          }}
                          className="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-escult-ink-2 hover:text-slate-600 flex items-center justify-center transition-all cursor-pointer"
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
                        className="p-2 rounded-xl bg-teal-50 hover:bg-teal-100 border border-teal-150 text-teal-700 hover:text-teal-800 flex items-center justify-center transition-all cursor-pointer"
                        title="Canal de Dúvidas e Feedbacks com os Professores"
                      >
                        <MessageSquare className="h-4 w-4 text-teal-700" />
                      </button>
                    )}
                  </div>
                )}

                <button
                  onClick={handleLogout}
                  className="rounded-lg border border-rose-150 bg-rose-50 hover:bg-rose-100 text-rose-700 px-3 py-2 text-sobretitulo transition-all flex items-center gap-1.5 cursor-pointer shadow-3xs hover:border-rose-300 uppercase"
                  title="Sair do Portal e encerrar sessão"
                >
                  <LogOut className="h-3.5 w-3.5 text-rose-500" />
                  <span>Sair</span>
                </button>

                <div 
                  onClick={() => {
                    abrirPerfil('profile');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    speakText("Carregando o seu perfil.");
                  }}
                  className="hidden md:flex items-center gap-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-xl cursor-pointer transition-all"
                  title="Visualizar meu Perfil"
                >
                  <div className="text-right">
                    <span className="block text-xs font-bold text-slate-800 leading-none">{activeUser.name}</span>
                    <span className="text-apoio text-ava-acao font-bold block mt-0.5">
                      {activeUser.role === 'student' && 'Aluno Credenciado'}
                      {activeUser.role === 'instructor' && 'Gestor de Conteúdos'}
                      {activeUser.role === 'admin' && 'Moderação Coordenação'}
                    </span>
                  </div>
                  <span className={`h-2.5 w-2.5 rounded-full ${
                    activeUser.role === 'admin' ? 'bg-amber-400' : activeUser.role === 'instructor' ? 'bg-emerald-400' : 'bg-ava-acao'
                  }`} />
                </div>
              </div>
            ) : (
              <div className={`flex items-center gap-2 ${isUserLoggedIn ? 'ava:max-sm:order-last ava:max-sm:w-full' : ''}`}>
                {/* Padlock and User icons (already interactive login triggers) */}
                {(
                  <div className="hidden sm:flex items-center gap-3.5 mr-2 text-escult-ink-2 border-r border-slate-200 pr-4">
                    <span title="Simular Conexão" onClick={() => setIsLoginModalOpen(true)} className="cursor-pointer"><Lock className="h-4.5 w-4.5 hover:text-slate-800 transition-colors" /></span>
                    <span
                      className="cursor-pointer"
                      title={isUserLoggedIn ? "Visualizar meu Perfil" : "Identidade Aluno/Professor"}
                      onClick={() => {
                        if (isUserLoggedIn) {
                          abrirPerfil('profile');
                          window.scrollTo({ top: 0, behavior: 'smooth' });
                          speakText("Carregando o seu perfil.");
                        } else {
                          setIsLoginModalOpen(true);
                        }
                      }}
                    >
                      <User className="h-4.5 w-4.5 hover:text-slate-800 transition-colors" />
                    </span>
                  </div>
                )}

                {/* If the user is technically logged in but on the landing view, provide an instant "Ir p/ Painel" button */}
                {isUserLoggedIn ? (
                  <div className="flex items-center gap-2 animate-in fade-in transition-all ava:max-sm:w-full">
                    <button
                      onClick={handleLogout}
                      className="rounded-lg border border-rose-150 bg-rose-50 hover:bg-rose-100 text-rose-700 px-3 py-2 text-sobretitulo transition-all flex items-center ava:max-sm:justify-center gap-1.5 ava:max-sm:flex-1 cursor-pointer shadow-3xs hover:border-rose-300 uppercase"
                      title="Sair do Portal e encerrar sessão"
                    >
                      <LogOut className="h-3.5 w-3.5 text-rose-500" />
                      <span>Sair</span>
                    </button>
                    {activeUser.role === 'student' ? (
                      <button 
                        onClick={() => {
                          voltarAoPainel();
                          window.scrollTo({ top: 0, behavior: 'smooth' });
                          speakText("Acessando o seu Ambiente de Estudos.");
                        }}
                        className="rounded-lg bg-[#FFD23F] hover:bg-amber-400 text-slate-900 border border-amber-300 ava:bg-ava-acao ava:hover:bg-ava-acao-escuro ava:text-white ava:border-ava-acao ava:max-sm:flex-[2] ava:max-sm:justify-center ava:whitespace-nowrap ava:normal-case ava:tracking-normal ava:text-rotulo ava:font-semibold px-3.5 py-2 text-sobretitulo uppercase transition-all cursor-pointer shadow-3xs flex items-center gap-1.5"
                        title="Ir para seu Ambiente de Estudos"
                      >
                        <BookOpen className="h-4 w-4 text-slate-900 ava:text-white shrink-0" />
                        <span className="hidden sm:inline">Ambiente de Estudos</span>
                        <span className="inline sm:hidden">Estudos</span>
                      </button>
                    ) : (
                      <button 
                        onClick={() => {
                          voltarAoPainel();
                          window.scrollTo({ top: 0, behavior: 'smooth' });
                          if (activeUser.role === 'admin') {
                            speakText("Acessando a sua Gestão da Plataforma.");
                          } else {
                            speakText("Acessando a sua Gestão de Cursos.");
                          }
                        }}
                        className="rounded-lg bg-[#FFD23F] hover:bg-amber-400 text-slate-900 border border-amber-300 ava:bg-ava-acao ava:hover:bg-ava-acao-escuro ava:text-white ava:border-ava-acao ava:max-sm:flex-[2] ava:max-sm:justify-center ava:whitespace-nowrap ava:normal-case ava:tracking-normal ava:text-rotulo ava:font-semibold px-3.5 py-2 text-sobretitulo uppercase transition-all cursor-pointer shadow-3xs flex items-center gap-1.5"
                        title={activeUser.role === 'admin' ? "Acessar Coordenação / Gestão da Plataforma" : "Acessar Gestão de Cursos e Conteúdos"}
                      >
                        <GraduationCap className="h-4 w-4 text-slate-900 ava:text-white shrink-0" />
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
                    {/*
                      Entrar deixou de ser contorno e virou o botao cheio: e a
                      acao principal do portal para quem nao esta logado. Caixa
                      mista e 15px, como o resto do cabecalho.
                    */}
                    <button
                      onClick={() => setIsLoginModalOpen(true)}
                      className="rounded-[10px] bg-ava-acao hover:bg-ava-acao-escuro text-white px-[22px] py-3 text-rotulo font-semibold transition-all cursor-pointer whitespace-nowrap"
                    >
                      Entrar
                    </button>
                    <button 
                      onClick={() => {
                        setIsRegisterModalOpen(true);
                        speakText("Portal de direcionamento e validação de cadastro externo aberto.");
                      }}
                      className="rounded-[10px] border border-ava-acao bg-white hover:bg-[#f4f2ef] text-ava-acao px-[18px] py-3 text-rotulo font-semibold transition-all cursor-pointer whitespace-nowrap hidden sm:block"
                    >
                      Cadastre-se
                    </button>
                  </>
                )}
              </div>
            )}

            {/* Mobile Hamburger Menu Toggle Button (shown only on md/sm screens in the public area) */}
            {isPublicPage && (
              <button
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                className="lg:hidden p-2 text-slate-650 hover:text-ava-acao hover:bg-slate-100 rounded-lg transition-colors cursor-pointer shrink-0 ml-1"
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
          {isMobileMenuOpen && isPublicPage && (
            <motion.div 
              initial={{ height: 0, opacity: 0 }}
              animate={{ height: 'auto', opacity: 1 }}
              exit={{ height: 0, opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="lg:hidden bg-white border-t border-slate-150 shadow-lg overflow-hidden"
            >
              {/*
                A gaveta do celular sai da MESMA estrutura do menu do desktop
                (`MENU_PUBLICO`). Antes era a lista de nove itens, ja aposentada
                no desktop — duas navegacoes divergindo e como um dos dois lugares
                fica desatualizado sem ninguem notar.

                Aqui os grupos aparecem abertos, com titulo: numa gaveta nao ha
                custo de espaco, e um suspenso dentro de outro suspenso e pior que
                a lista.
              */}
              <div className="px-5 py-4.5 flex flex-col gap-1 text-left bg-white">
                {MENU_PUBLICO.map((entrada) => (
                  entrada.tipo === 'link' ? (
                    <button
                      key={entrada.rotulo}
                      onClick={() => {
                        goToPage(entrada.view, entrada.rotulo);
                        setIsMobileMenuOpen(false);
                      }}
                      className="text-left text-rotulo font-semibold text-[#1d2432] hover:bg-[#f4f2ef] rounded-[10px] px-3 py-3 transition-colors cursor-pointer"
                    >
                      {entrada.rotulo}
                    </button>
                  ) : (
                    <div key={entrada.rotulo} className="pt-2">
                      <span className="block px-3 pb-1 text-apoio font-semibold uppercase tracking-[0.12em] text-[#6b7385]">
                        {entrada.rotulo}
                      </span>
                      {entrada.itens.map((item) => (
                        <button
                          key={item.rotulo}
                          onClick={() => {
                            goToPage(item.view, item.rotulo);
                            setIsMobileMenuOpen(false);
                          }}
                          className="w-full text-left text-rotulo font-semibold text-[#1d2432] hover:bg-[#f4f2ef] rounded-[10px] px-3 py-3 transition-colors cursor-pointer"
                        >
                          {item.rotulo}
                        </button>
                      ))}
                    </div>
                  )
                ))}
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </header>

      {/*
        Bloco 1 do handoff: a trilha de navegacao, no lugar dos botoes "Voltar".

        Renderizada UMA vez aqui, e nao dentro de cada pagina: a especificacao
        pede "sempre no mesmo lugar", e dentro do `PageShell` ela ficaria dentro
        do respiro de cada tela, mudando de posicao conforme a pagina.

        A trilha vem de `trilhaDaView`, derivada da rota — nao de prop escrita a
        mao em cada chamador. Era assim que o portal chegou a cinco desenhos
        diferentes do mesmo botao, cada um com seu texto e seu destino.

        Na pagina inicial e na area autenticada nao aparece: `trilhaDaView`
        devolve lista vazia e o componente nao desenha a barra. Os paineis tem
        trilha propria, derivada da hierarquia de cada um.
      */}
      <Breadcrumb
        items={trilhaDaView(currentView).map((degrau) => ({
          rotulo: degrau.rotulo,
          onClick: degrau.view === undefined
            ? undefined
            : () => goToPage(degrau.view as PortalView, degrau.rotulo),
        }))}
        onHome={() => goToPage('landing', 'Página Inicial')}
      />

      {/* Main Content Body Routing */}
      <main className="flex-1">
        {currentView === 'landing' ? (
          /* COMPLETE 9-SECTION LANDING PAGE */
          <div className="animate-in fade-in duration-300">
            
            {/*
              Heroi no desenho da tela 13 da skill ava-frontend-redesign
              (planejamento 13): superficie clara, sobretitulo com filete
              dourado, titulo marinho com a segunda linha no azul de acao. Os
              textos, os dois botoes e o que eles fazem sao os de antes.
            */}
            <section
              id="hero-section"
              ref={heroRef}
              onMouseMove={handleHeroMouseMove}
              onMouseEnter={() => { if (!movimentoReduzido) setIsMouseInHero(true); }}
              onMouseLeave={() => setIsMouseInHero(false)}
              className="bg-white text-ava-tinta py-12 lg:py-20 relative overflow-hidden flex flex-col items-center"
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
                <div className="h-80 w-80 -translate-x-1/2 -translate-y-1/2 bg-ava-acao/[0.06] rounded-full blur-3xl" />
              </motion.div>

              <div className="mx-auto max-w-7xl px-4 md:px-8 grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center text-left w-full">

                {/* Hero Left Content */}
                <div className="lg:col-span-7 space-y-6 relative z-10 min-w-0">
                  <p className="filete text-sobretitulo uppercase text-ava-tinta">
                    <span>{translations[currentLang].heroBadge}</span>
                  </p>

                  <h1 className="text-[2rem] sm:text-5xl lg:text-[54px] tracking-tight leading-[1.08] font-titulo text-ava-tinta break-words">
                    Escola Estadual da Cultura <br className="hidden sm:inline" />
                    <span className="text-ava-acao">Ambiente Virtual de Aprendizagem (AVASEC)</span>
                  </h1>

                  <p className="text-escult-ink-2 text-corpo leading-relaxed max-w-2xl">
                    A AVASEC é o portal de capacitação e qualificação profissional da Escola Estadual da Cultura. Oferecemos cursos livres e de excelência em Cultura, Gestão Cultural, Economia Criativa e Linguagens Artísticas com certificação digital homologada.
                  </p>

                  <div className="flex flex-col sm:flex-row gap-3 pt-2">
                    <button
                      onClick={() => goToPage('cursos', translations[currentLang].btnDiscover)}
                      className="rounded-[10px] bg-ava-acao hover:bg-ava-acao-escuro text-white px-7 py-3 text-rotulo font-semibold transition-all cursor-pointer flex items-center justify-center gap-2 group shadow-sm"
                    >
                      <span>{translations[currentLang].btnDiscover}</span>
                      <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
                    </button>
                    <button
                      onClick={() => { setIsLoginModalOpen(true); speakText(translations[currentLang].btnStart); }}
                      className="rounded-[10px] border border-ava-acao bg-white hover:bg-ava-faixa text-ava-acao px-7 py-3 text-rotulo font-semibold transition-all cursor-pointer flex items-center justify-center gap-2"
                    >
                      <Play className="h-4 w-4" />
                      <span>{translations[currentLang].btnStart}</span>
                    </button>
                  </div>
                </div>

                {/*
                  Composicao geometrica da tela 13 (meio-circulo marinho, meio
                  disco dourado, quadrados rosa e azul), SEM a foto da tela.

                  Aqui havia o retrato de um educador homenageado. A mencao
                  nominal saiu de todo o site por decisao da coordenacao
                  (10/09/2026), e no lugar entra uma referencia a educacao,
                  sem pessoa nenhuma. Sem imagem, e nao com uma foto de banco
                  de imagens: uma foto de estudantes que nao sao desta escola
                  seria outra afirmacao falsa, do mesmo tipo que a que acabou
                  de sair. Por isso o lugar da foto e um cartao com o texto.
                */}
                <div className="lg:col-span-5 relative flex items-center justify-center min-w-0">
                  <div className="relative w-full max-w-[420px] h-[340px] sm:h-[380px] shrink-0">
                    <div aria-hidden="true" className="absolute left-6 top-0 h-36 w-[72px] rounded-r-full bg-ava-marinho" />
                    <div aria-hidden="true" className="absolute left-0 top-10 h-20 w-10 rounded-l-full bg-ava-dourado" />
                    <div aria-hidden="true" className="absolute right-4 top-2 h-20 w-20 bg-ava-rosa" />
                    <div aria-hidden="true" className="absolute right-0 bottom-0 h-28 w-28 rounded-tl-full bg-ava-faixa" />
                    <div aria-hidden="true" className="absolute right-20 bottom-4 h-16 w-16 bg-ava-acao" />

                    <div className="absolute inset-x-6 sm:inset-x-10 top-12 bottom-12 z-10 rounded-3xl bg-white border border-ava-borda shadow-xl flex flex-col items-center justify-center gap-3 text-center px-6">
                      <div className="h-14 w-14 rounded-full bg-ava-icone-fundo flex items-center justify-center">
                        <GraduationCap className="h-7 w-7 text-ava-acao" aria-hidden="true" />
                      </div>
                      <span className="text-ava-tinta font-titulo text-lg leading-tight">
                        Educação pública, gratuita e de qualidade
                      </span>
                      <span className="text-escult-ink-2 text-nota">
                        Escola Estadual da Cultura
                      </span>
                      <div className="flex flex-wrap justify-center gap-1.5 pt-1">
                        <span className="rounded-full border border-ava-acao/30 text-ava-acao text-nota font-semibold px-2.5 py-0.5">✦ Criatividade</span>
                        <span className="rounded-full bg-ava-marinho text-white text-nota font-semibold px-2.5 py-0.5">▲ Inovação</span>
                        <span className="rounded-full border border-ava-rosa/30 text-ava-rosa text-nota font-semibold px-2.5 py-0.5">● Arte</span>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </section>

            {/* SECTION: MINHA APRENDIZAGEM (Condicional quando logado) — cartao da tela 13 */}
            {isUserLoggedIn && activeUser.role === 'student' && (
              <section id="minha-aprendizagem" className="bg-white pb-10 px-4">
                <div className="mx-auto max-w-7xl animate-in fade-in duration-200">
                  <div className="bg-ava-faixa/60 rounded-3xl border border-ava-borda p-5 md:p-8 space-y-5">
                    <div className="flex flex-col md:flex-row justify-between md:items-center gap-5">
                      <div className="space-y-2 text-left min-w-0">
                        <p className="filete text-sobretitulo uppercase text-ava-tinta">
                          <span>Sua Área de Estudos</span>
                        </p>
                        <h3 className="text-xl md:text-2xl text-ava-tinta font-titulo break-words">
                          Olá, {activeUser.name}!
                        </h3>
                        <p className="text-apoio text-escult-ink-2 max-w-2xl">
                          Continue de onde você parou. Acesse seu curso ativo ou acompanhe suas notas, presenças síncronas de mentoria e certificados homologados.
                        </p>
                      </div>

                      <div className="grid grid-cols-1 min-[400px]:grid-cols-2 gap-3 w-full md:w-[26rem] shrink-0">
                        <div className="p-3.5 bg-white rounded-2xl border border-ava-borda flex items-center gap-3">
                          <div className="h-10 w-10 rounded-full bg-ava-icone-fundo flex items-center justify-center shrink-0">
                            <GraduationCap className="h-5 w-5 text-ava-acao" aria-hidden="true" />
                          </div>
                          <div className="min-w-0">
                            <strong className="text-2xl font-black text-ava-acao block leading-none">
                              {studentEnrollments[activeUser.id]?.completedCourseIds?.length || 0}
                            </strong>
                            <span className="text-nota font-semibold text-escult-ink-2 uppercase tracking-wide mt-1 block">Cursos Concluídos</span>
                          </div>
                        </div>
                        <div className="p-3.5 bg-white rounded-2xl border border-ava-borda flex items-center gap-3">
                          <div className="h-10 w-10 rounded-full bg-ava-icone-fundo flex items-center justify-center shrink-0">
                            <Award className="h-5 w-5 text-ava-acao" aria-hidden="true" />
                          </div>
                          <div className="min-w-0">
                            <strong className="text-2xl font-black text-ava-acao block leading-none">
                              {certificates.filter(c => c.userId === activeUser.id).length || 0}
                            </strong>
                            <span className="text-nota font-semibold text-escult-ink-2 uppercase tracking-wide mt-1 block">Certificados Emitidos</span>
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Display current active course if there is one */}
                    {(() => {
                      const enrollment = studentEnrollments[activeUser.id];
                      const activeCourseId = enrollment?.enrolledCourseId;
                      const activeCourse = activeCourseId ? courses.find(c => c.id === activeCourseId) : null;

                      if (activeCourse) {
                        return (
                          <div className="bg-white p-4.5 rounded-2xl border border-ava-borda flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div className="space-y-1 min-w-0">
                              <span className="text-sobretitulo uppercase text-escult-ink-2 block">CURSO ATIVO</span>
                              <strong className="text-base text-ava-tinta block font-titulo break-words">{activeCourse.title}</strong>
                              <span className="text-apoio text-escult-ink-2 block">Ministrado por: Prof. {activeCourse.instructorName}</span>
                            </div>
                            <button
                              onClick={() => { setCurrentView('active_app'); speakText(`Iniciando estudos no curso ${activeCourse.title}`); }}
                              className="w-full sm:w-auto shrink-0 bg-ava-acao hover:bg-ava-acao-escuro text-white text-rotulo font-semibold py-3 px-6 rounded-[10px] transition-colors cursor-pointer flex items-center justify-center gap-1.5"
                            >
                              <span>Continuar Aula</span>
                              <ArrowRight className="h-4 w-4" />
                            </button>
                          </div>
                        );
                      } else {
                        return (
                          <div className="p-4 bg-white rounded-2xl border border-ava-borda text-apoio text-escult-ink-2 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <p>Você não tem nenhuma matrícula ativa de curso no momento. Explore nosso catálogo e matricule-se!</p>
                            <button
                              onClick={() => goToPage('cursos', "Cursos disponíveis")}
                              className="shrink-0 text-rotulo font-semibold text-ava-acao hover:underline cursor-pointer"
                            >
                              <span className="inline-flex items-center gap-1">Ver Cursos <ArrowRight className="h-3.5 w-3.5" /></span>
                            </button>
                          </div>
                        );
                      }
                    })()}
                  </div>
                </div>
              </section>
            )}

            {/* STATS SECTION: AVASEC em dados (tela 13) */}
            <section className="bg-white py-14 px-4 overflow-hidden relative border-t border-ava-borda">
              <div className="mx-auto max-w-7xl grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center">

                <div className="lg:col-span-6 space-y-6 text-left min-w-0">
                  <div className="space-y-3">
                    <div className="h-[3px] w-7 rounded-full bg-ava-dourado" aria-hidden="true" />
                    <h3 className="text-3xl text-ava-tinta tracking-tight font-titulo">
                      <span>AVASEC em dados</span>
                    </h3>
                  </div>

                  <p className="text-apoio text-escult-ink-2 leading-relaxed">
                    A Escola Estadual da Cultura foi lançada em janeiro de 2024 e é promovida pela Diretoria de Políticas para Trabalhadores da Cultura e da Economia Criativa por meio da Coordenação de Capacitação e Qualificação Profissional. Veja aqui os resultados já alcançados de nossa rede:
                  </p>

                  <div className="grid grid-cols-1 min-[400px]:grid-cols-2 gap-4 pt-2">
                    {([
                      { Icone: Users, numero: '188K', rotulo: 'Estudantes cadastrados' },
                      { Icone: FileText, numero: '300K', rotulo: 'Inscrições nos cursos' },
                      { Icone: ChartColumn, numero: '66K', rotulo: 'Mil concluintes' },
                      { Icone: MousePointer2, numero: '4M+', rotulo: 'Visitas à plataforma' },
                    ] as const).map(({ Icone, numero, rotulo }) => (
                      <div key={rotulo} className="p-4 bg-white border border-ava-borda rounded-2xl shadow-3xs flex items-center gap-3.5">
                        <div className="h-11 w-11 rounded-full bg-ava-icone-fundo flex items-center justify-center shrink-0">
                          <Icone className="h-5 w-5 text-ava-acao" aria-hidden="true" />
                        </div>
                        <div className="min-w-0">
                          <strong className="text-2xl font-black text-ava-acao block leading-none">{numero}</strong>
                          <span className="text-nota font-semibold text-escult-ink-2 uppercase tracking-wide mt-1.5 block">{rotulo}</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Destaque escuro da tela 13: texto a esquerda, imagem a direita sob um degrade marinho. */}
                <div className="lg:col-span-6 min-w-0">
                  <div className="relative w-full min-h-[300px] rounded-2xl overflow-hidden bg-ava-marinho text-white flex items-end">
                    <img
                      src="https://images.unsplash.com/photo-1514306191717-452ec28c7814?w=600&auto=format&fit=crop&q=70"
                      alt="Estudantes em atividade prática de arte e cultura"
                      className="absolute inset-y-0 right-0 h-full w-full sm:w-3/5 object-cover"
                      referrerPolicy="no-referrer"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t sm:bg-gradient-to-r from-ava-marinho via-ava-marinho/90 to-ava-marinho/10" aria-hidden="true" />
                    <div className="relative z-10 p-6 sm:p-8 text-left space-y-2 sm:max-w-[60%]">
                      <span className="text-sobretitulo uppercase text-ava-dourado block">Patrimônio Vivo</span>
                      <strong className="text-lg font-titulo block">Fazer Artístico Decolonial</strong>
                      <p className="text-apoio text-white/85">Oficinas ministradas de maneira autônoma com apoio das comunidades locais e certificadas em nossa rede.</p>
                    </div>
                  </div>
                </div>

              </div>
            </section>


            {/* Faixa de sugestao (tela 13) */}
            <section className="bg-ava-faixa border-y border-ava-borda py-5 px-4">
              <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 font-sans">
                <div className="flex items-center gap-3 text-left">
                  <div className="h-12 w-12 rounded-full bg-white text-ava-acao flex items-center justify-center shrink-0">
                    <Lightbulb className="h-5 w-5" aria-hidden="true" />
                  </div>
                  <div className="leading-tight min-w-0">
                    <strong className="text-ava-tinta font-sans font-black block">Sentiu falta de algum curso?</strong>
                    <span className="text-apoio text-escult-ink-2">Envie a sua sugestão para nós.</span>
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
                    className="min-w-0 flex-1 md:flex-none md:w-72 bg-white border border-ava-borda text-ava-tinta rounded-[10px] px-4.5 py-2.5 text-corpo focus:outline-none focus:ring-2 focus:ring-ava-acao"
                  />
                  <button
                    type="submit"
                    className="rounded-[10px] bg-ava-acao hover:bg-ava-acao-escuro text-white text-rotulo font-semibold px-5 py-2.5 transition-colors shadow-xs shrink-0 cursor-pointer"
                  >
                    {suggestionSubmitted ? 'Enviado!' : 'Sugerir'}
                  </button>
                </form>
              </div>
            </section>

            {/*
              Aqui havia uma secao de homenagem a um educador: retrato, datas
              de nascimento e morte, o rotulo de patrono e um botao que prometia
              a biografia e levava para as Duvidas Frequentes.

              A mencao nominal saiu de todo o site por decisao da coordenacao
              (10/09/2026), substituida por uma referencia a educacao como um
              todo. Os tres principios continuam — eles descrevem uma concepcao
              pedagogica, nao uma biografia — reescritos sem nomear ninguem.

              O botao passou a apontar para "O Projeto", que e a pagina que fala
              da concepcao pedagogica. Isso tambem corrige o defeito de rotulo:
              antes o botao prometia um assunto e entregava outro.
            */}
            <section id="quem-somos" className="bg-white text-ava-tinta py-16 px-4">
              <div className="mx-auto max-w-5xl space-y-10 text-left">

                <div className="space-y-3 text-center">
                  <p className="filete text-sobretitulo text-ava-tinta uppercase">Nossa concepção</p>
                  <h2 className="text-3xl md:text-secao tracking-tight font-titulo text-ava-tinta">A educação que orienta esta escola</h2>
                  <p className="text-escult-ink-2 font-sans tracking-wide text-nota">Escola Estadual da Cultura</p>
                </div>

                <p className="text-escult-ink-2 text-apoio leading-relaxed max-w-2xl mx-auto text-center">
                  Ensinar é uma prática que começa pelo que o estudante já sabe. Nesta escola,
                  arte, cultura e economia criativa são o caminho para desenvolver leitura crítica
                  da realidade, autonomia e capacidade de transformar o próprio contexto — dentro e
                  fora da sala de aula.
                </p>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-8 sm:gap-0 sm:divide-x sm:divide-ava-borda">
                  {([
                    { Icone: Users, titulo: 'Centrada em quem aprende', texto: 'O processo parte do respeito e da bagagem que cada estudante já traz consigo.' },
                    { Icone: MessagesSquare, titulo: 'Diálogo, não transmissão', texto: 'Aprender é via de mão dupla: quem ensina também aprende com a turma.' },
                    { Icone: BookOpen, titulo: 'Leitura crítica', texto: 'Educar para a autonomia e a cidadania ativa, com reflexão sobre a realidade.' },
                  ] as const).map(({ Icone, titulo, texto }) => (
                    <div key={titulo} className="space-y-2 sm:px-8 sm:first:pl-0 sm:last:pr-0">
                      <Icone className="h-7 w-7 text-ava-acao" aria-hidden="true" />
                      <strong className="text-cartao font-titulo text-ava-tinta block">{titulo}</strong>
                      <p className="text-escult-ink-2 text-apoio leading-relaxed">{texto}</p>
                    </div>
                  ))}
                </div>

                <div className="pt-2 flex justify-center">
                  <button
                    onClick={() => goToPage('o-projeto', "O Projeto")}
                    className="rounded-[10px] border border-ava-acao hover:bg-ava-acao hover:text-white text-ava-acao px-6 py-2.5 text-rotulo font-semibold transition-all cursor-pointer flex items-center gap-2"
                  >
                    <BookOpen className="h-4 w-4" aria-hidden="true" />
                    <span>Conheça o projeto</span>
                  </button>
                </div>

              </div>
            </section>


          </div>
        ) : currentView === 'perfil' ? (
          /* NEW PROFILE VIEW */
          <ProfileView
            abaInicial={perfilAbaInicial}
            rotuloVoltar={rotuloDoVoltar(perfilOrigem)}
            onBack={() => {
              const raiz = isUserLoggedIn ? raizDoPapel(activeUser.role) : pathFromView('landing');
              navigate(destinoDoVoltar(perfilOrigem, isUserLoggedIn ? activeUser.role : null, raiz));
            }}
            onLogout={handleLogout}
            speakText={speakText}
          />
        ) : currentView === 'o-ava' ? (
          <AvaPage
            content={sitePageContent?.['o-ava']}
          />
        ) : currentView === 'o-projeto' ? (
          <ProjetoPage
            content={sitePageContent?.['o-projeto']}
          />
        ) : currentView === 'noticias' ? (
          <NoticiasPage
            searchQuery={searchQuery}
            onClearSearch={() => setSearchQuery('')}
            onRequireLogin={() => setIsLoginModalOpen(true)}
            content={sitePageContent?.['noticias']}
          />
        ) : currentView === 'duvidas' ? (
          <DuvidasPage
            content={sitePageContent?.['duvidas']}
          />
        ) : currentView === 'calendario' ? (
          <CalendarioPage
            isUserLoggedIn={!!isUserLoggedIn}
            onRequireLogin={() => setIsLoginModalOpen(true)}
            speakText={speakText}
            content={sitePageContent?.['calendario']}
            courses={courses}
            webinars={webinarEvents}
          />
        ) : currentView === 'orientacoes' ? (
          <OrientacoesPage
            content={sitePageContent?.['orientacoes']}
          />
        ) : currentView === 'certificados' ? (
          /* Autenticador público de certificados. Também é o destino do
             deep-link /?verify=... do QR impresso no PDF (ADR 09). */
          <PageShell
            eyebrow={pageField(certContent, 'eyebrow', 'Qualificação Oficial Homologada')}
            title={pageField(certContent, 'title', 'Certificados e Emissão')}
            description={pageField(certContent, 'description', 'Todos os cursos da Escola Estadual da Cultura dão direito a certificados de conclusão oficiais. Entenda os critérios necessários para emissão e valide certificados existentes abaixo.')}
            align="center"
            background="bg-white"
          >
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            {/* Left Column: Guidelines */}
            <div className="lg:col-span-6 space-y-6 text-left">
              <div className="bg-white p-6.5 rounded-2xl border border-ava-borda shadow-3xs space-y-4">
                <h4 className="text-cartao text-ava-tinta font-titulo flex items-center gap-2.5">
                  <Award className="h-5 w-5 text-ava-acao" />
                  <span>{pageField(certContent, 'criteriaTitle', 'Orientações de Aprovação & Emissão')}</span>
                </h4>
                <p className="text-xs text-escult-ink-2 leading-relaxed">
                  {pageField(certContent, 'criteriaIntro', 'Para estar elegível à geração do seu certificado digital, você deve atender aos seguintes critérios letivos na plataforma:')}
                </p>

                <div className="space-y-4 pt-2">
                  {certCriteria.map((criterio) => (
                    <div key={criterio.id} className="flex gap-3 items-start">
                      <CheckCircle className="h-4.5 w-4.5 text-ava-acao shrink-0 mt-0.5" />
                      <div className="text-xs">
                        <strong className="text-ava-tinta block">{criterio.title}</strong>
                        <span className="text-escult-ink-2 text-rotulo leading-normal block">{criterio.description}</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div className="bg-ava-faixa border border-ava-borda p-5 rounded-2xl flex gap-3.5 items-start">
                <ShieldCheck className="h-6 w-6 text-ava-marinho shrink-0 mt-0.5" aria-hidden="true" />
                <p className="text-rotulo text-ava-tinta leading-relaxed">
                  {pageField(certContent, 'noticeText', 'Validação por Terceiros: Qualquer instituição pública ou parceira pode validar os certificados emitidos utilizando o nosso autenticador ao lado com o código de registro ou nome completo.')}
                </p>
              </div>
            </div>

            {/* Right Column: Autenticador form */}
            <div className="lg:col-span-6 bg-white p-6.5 rounded-2xl border border-ava-borda shadow-3xs space-y-6 text-left">
              <div className="space-y-1.5">
                <h4 className="text-cartao text-ava-tinta font-titulo flex items-center gap-2.5">
                  <ShieldCheck className="h-5 w-5 text-ava-acao" />
                  <span>{pageField(certContent, 'authenticatorTitle', 'Autenticador de Certificados')}</span>
                </h4>
                <p className="text-xs text-escult-ink-2">
                  {pageField(certContent, 'authenticatorDescription', 'Insira o código de validação de 10 dígitos ou o nome completo do aluno para verificar sua autenticidade.')}
                </p>
              </div>

              <form onSubmit={handleCertLookup} className="flex gap-2.5">
                <input
                  type="text"
                  placeholder="Ex: CERT-JOAO-123 ou João Silva..."
                  value={certQuery}
                  onChange={(e) => setCertQuery(e.target.value)}
                  className="flex-1 min-w-0 bg-white border border-ava-borda text-ava-tinta rounded-[10px] px-4 py-3 text-apoio font-medium focus:outline-none focus:ring-2 focus:ring-ava-acao/30 focus:border-ava-acao placeholder:text-escult-ink-3"
                />
                <button
                  type="submit"
                  className="rounded-[10px] bg-ava-acao hover:bg-ava-acao-escuro text-white text-rotulo font-semibold px-5 py-3 transition-all shadow-xs cursor-pointer shrink-0"
                >
                  Verificar
                </button>
              </form>

              <AnimatePresence mode="wait">
                {certSearchClicked && (
                  <motion.div
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: 10 }}
                    className="pt-4 border-t border-slate-100 font-sans"
                  >
                    {certLookupResult ? (
                      /*
                       * O documento revogado APARECE aqui, e essa é a razão de a
                       * consulta pública existir. Some do banco significaria
                       * responder "não existe" sobre um papel que está impresso e
                       * pode estar anexado a um processo — quem confere precisa da
                       * resposta "foi revogado", que é outra coisa (ADR 12).
                       */
                      <div className={`rounded-2xl border p-4.5 space-y-3.5 ${
                        certLookupResult.revogado === true
                          ? 'bg-rose-50 border-rose-200'
                          : 'bg-emerald-50 border-emerald-200'
                      }`}>
                        {certLookupResult.revogado === true ? (
                          <div className="flex items-center gap-2.5 text-rose-800">
                            <AlertTriangle className="h-5 w-5 text-rose-600 shrink-0" />
                            <strong className="text-sobretitulo uppercase">Certificado Revogado — Sem Validade</strong>
                          </div>
                        ) : (
                          <div className="flex items-center gap-2.5 text-emerald-800">
                            <CheckCircle className="h-5 w-5 text-emerald-600 shrink-0" />
                            <strong className="text-sobretitulo uppercase">Certificado Válido e Homologado</strong>
                          </div>
                        )}

                        {certLookupResult.revogado === true && (
                          <div className="space-y-1 rounded-lg border border-rose-100 bg-white/60 p-2.5 text-rotulo leading-relaxed text-rose-900">
                            <p className="font-bold">
                              Este documento foi revogado pela instituição
                              {typeof certLookupResult.revogadoEm === 'string' && certLookupResult.revogadoEm !== ''
                                ? ` em ${certLookupResult.revogadoEm}`
                                : ''}
                              {' '}e não comprova conclusão de curso.
                            </p>
                            {typeof certLookupResult.motivoRevogacao === 'string' && certLookupResult.motivoRevogacao !== '' && (
                              <p className="font-medium text-rose-700">Motivo registrado: {certLookupResult.motivoRevogacao}</p>
                            )}
                            <p className="text-rose-700">
                              Os dados abaixo são os do documento como foi emitido, para conferência com o papel em mãos.
                            </p>
                          </div>
                        )}

                        <div className={`grid grid-cols-2 gap-3 text-apoio leading-relaxed border-t pt-3 ${
                          certLookupResult.revogado === true ? 'border-rose-100' : 'border-emerald-100'
                        }`}>
                          <div>
                            <span className="text-emerald-600 block text-sobretitulo uppercase">Aluno</span>
                            <span className="text-slate-800 font-bold block">{certLookupResult.studentName}</span>
                          </div>
                          <div>
                            <span className="text-emerald-600 block text-sobretitulo uppercase">Curso</span>
                            <span className="text-slate-800 font-bold block">{certLookupResult.courseTitle}</span>
                          </div>
                          <div>
                            <span className="text-emerald-600 block text-sobretitulo uppercase">Data de Emissão</span>
                            {/* issueDate já é string d/m/Y — new Date() não parseia esse formato */}
                            <span className="text-slate-800 font-medium block">{certLookupResult.issueDate}</span>
                          </div>
                          <div>
                            <span className="text-emerald-600 block text-sobretitulo uppercase">Registro de Autenticidade</span>
                            <span className="text-slate-850 font-bold block select-all">{certLookupResult.verificationHash}</span>
                          </div>
                        </div>
                        
                        {certLookupResult.revogado !== true && (
                          <p className="text-apoio text-emerald-700 leading-normal font-medium bg-white/50 p-2.5 rounded-lg border border-emerald-100/50">
                            Certificado emitido em conformidade com as diretrizes do AVA da Escola Estadual da Cultura. Registro de presença homologado: {certLookupResult.attendancePercent}%.
                          </p>
                        )}
                      </div>
                    ) : (
                      <div className="bg-rose-50 border border-rose-200 rounded-2xl p-4.5 flex gap-3 items-start">
                        <X className="h-5 w-5 text-rose-600 shrink-0 mt-0.5" />
                        <div className="text-xs space-y-1">
                          <strong className="text-rose-900 uppercase block tracking-wider font-extrabold">Código Não Encontrado</strong>
                          <p className="text-rose-700 leading-relaxed">
                            Nenhum registro correspondente ao termo "{certQuery}" foi encontrado em nosso banco de dados. Verifique a grafia do nome ou o hash de verificação de 10 dígitos impresso no verso do documento.
                          </p>
                        </div>
                      </div>
                    )}
                  </motion.div>
                )}
              </AnimatePresence>

            </div>
          </div>
          </PageShell>
        ) : currentView === 'cursos' ? (
          /*
            Catalogo no desenho da tela 04 da skill ava-frontend-redesign
            (planejamento 13). A busca e a MESMA de antes (`courseSearch`, filtra
            ao digitar) e as categorias sao as mesmas; nao entram contagem por
            categoria, "Ordenar por", carga horaria nem nivel — os cartoes vem de
            `featuredCoursesData`, que nao tem esses campos.

            Aqui havia o botao "Voltar" do PageShell COPIADO A MAO — mesmo
            markup, mesmo texto, destino fixo na Inicio. Era o quinto dos
            cinco desenhos do mesmo botao. Quem diz onde a pessoa esta e a
            trilha, logo abaixo do cabecalho.
          */
          <div className="bg-white min-h-[70vh] animate-in fade-in duration-300">
            <section className="relative overflow-hidden border-b border-ava-borda bg-gradient-to-b from-white to-ava-faixa/60 px-4 py-10 md:py-14">
              <div aria-hidden="true" className="hidden xl:block absolute right-24 top-8 h-40 w-20 rounded-r-full bg-ava-marinho" />
              <div aria-hidden="true" className="hidden xl:block absolute right-[11.5rem] top-16 h-20 w-10 rounded-l-full bg-ava-dourado" />
              <div aria-hidden="true" className="hidden xl:block absolute right-8 top-6 h-16 w-16 bg-ava-rosa" />
              <div aria-hidden="true" className="hidden xl:block absolute right-10 bottom-8 h-20 w-20 bg-ava-acao" />

              <div className="relative mx-auto max-w-7xl text-left space-y-4">
                <p className="filete text-sobretitulo text-ava-tinta uppercase">Catálogo</p>
                <h2 className="text-3xl md:text-pagina text-ava-tinta tracking-tight font-titulo max-w-2xl">Cursos Disponíveis</h2>
                <p className="text-corpo text-escult-ink-2 leading-relaxed max-w-2xl">
                  Conheça os cursos oferecidos pela Escola Estadual da Cultura. Use os filtros para encontrar por área ou por nome.
                </p>

                <div className="relative w-full max-w-xl pt-2">
                  <Search className="absolute left-4 top-1/2 translate-y-[calc(-50%+4px)] h-5 w-5 text-escult-ink-2" aria-hidden="true" />
                  <input
                    type="text"
                    value={courseSearch}
                    onChange={(e) => setCourseSearch(e.target.value)}
                    placeholder="Buscar curso pelo nome..."
                    aria-label="Buscar curso"
                    className="w-full bg-white border border-ava-borda focus:ring-2 focus:ring-ava-acao/20 focus:border-ava-acao focus:outline-none rounded-2xl py-3.5 pl-12 pr-4 text-corpo text-ava-tinta shadow-sm"
                  />
                </div>
              </div>
            </section>

            <div className="mx-auto max-w-7xl px-4 py-8 md:py-10 space-y-8">
              {/* Categorias: as mesmas de antes, como cartoes-filtro da tela 04. */}
              <div className="flex gap-2.5 flex-wrap" role="group" aria-label="Filtrar por categoria">
                {courseCategories.map((cat) => (
                  <button
                    key={cat}
                    onClick={() => setCourseCategory(cat)}
                    aria-pressed={courseCategory === cat}
                    className={`px-4 py-2.5 rounded-xl text-rotulo font-semibold transition-all cursor-pointer border flex items-center gap-2 ${
                      courseCategory === cat
                        ? 'bg-ava-acao text-white border-ava-acao shadow-sm'
                        : 'bg-white text-ava-tinta border-ava-borda hover:border-ava-acao/40 hover:bg-ava-faixa'
                    }`}
                  >
                    {cat === 'all' && <BookOpen className="h-4 w-4" aria-hidden="true" />}
                    {cat === 'all' ? 'Todas' : cat}
                  </button>
                ))}
              </div>

              {/* Lista de cursos disponíveis em cards */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 text-left">
                {filteredCourses.length === 0 ? (
                  <div className="col-span-full rounded-2xl border border-dashed border-ava-borda p-12 text-center bg-ava-faixa/50">
                    <h4 className="text-ava-tinta font-titulo text-cartao mb-2">Sem Resultados</h4>
                    <p className="text-escult-ink-2 text-apoio leading-relaxed max-w-md mx-auto">Nenhum curso encontrado com os filtros atuais.</p>
                    <button
                      onClick={() => { setCourseSearch(''); setCourseCategory('all'); }}
                      className="mt-4 px-5 py-2.5 bg-ava-acao text-white text-rotulo font-semibold rounded-[10px] hover:bg-ava-acao-escuro transition-colors cursor-pointer font-sans"
                    >
                      Limpar Filtros
                    </button>
                  </div>
                ) : (
                  filteredCourses.map((course, idx) => (
                    <div
                      key={`${course.title}-${idx}`}
                      className="bg-white rounded-2xl border border-ava-borda transition-all duration-300 flex flex-col justify-between overflow-hidden shadow-3xs hover:shadow-md hover:border-ava-acao/40"
                    >
                      <div className="h-44 overflow-hidden relative bg-ava-marinho">
                        <img
                          src={course.image}
                          alt={course.title}
                          className="w-full h-full object-cover"
                          referrerPolicy="no-referrer"
                        />
                        <span className="absolute top-3 left-3 text-nota font-semibold uppercase tracking-wide bg-white text-ava-acao py-1 px-2.5 rounded-full shadow-sm max-w-[calc(100%-1.5rem)] truncate">
                          {course.category}
                        </span>
                      </div>

                      <div className="p-5 space-y-3 flex-1 flex flex-col justify-between">
                        <div className="space-y-2">
                          <h4 className="text-cartao text-ava-tinta leading-snug line-clamp-2 font-titulo">
                            {course.title}
                          </h4>
                          <p className="text-apoio text-escult-ink-2 font-semibold">Tutorado por: Prof. {course.instructor}</p>
                          <p className="text-apoio text-escult-ink-2 leading-relaxed line-clamp-3">
                            {course.description}
                          </p>
                        </div>

                        <button
                          onClick={() => setIsLoginModalOpen(true)}
                          className="w-full text-center mt-3 py-2.5 rounded-[10px] bg-ava-acao hover:bg-ava-acao-escuro text-white transition-all text-rotulo font-semibold cursor-pointer flex items-center justify-center gap-1.5"
                        >
                          <span>Inscrever-se</span>
                          <ArrowRight className="h-4 w-4" />
                        </button>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        ) : (
          /* ACTIVE APP VIEW - ROUTING WITH ABSOLUTE BOUNDARIES EXPLICITLY RESPECTING THE GUIDELINES */
          <div className="bg-slate-50/20">
            {activeUser.role === 'student' && (
              <StudentDashboard
                onBackToLanding={() => setCurrentView('landing')}
                onNavigateToProfile={() => abrirPerfil('profile')}
                speakText={speakText}
              />
            )}
            
            {activeUser.role === 'instructor' && (
              <InstructorDashboard onBackToLanding={() => setCurrentView('landing')} speakText={speakText} />
            )}

            {activeUser.role === 'admin' && (
              <AdminDashboard
                onBackToLanding={() => setCurrentView('landing')}
                speakText={speakText}
                onPreviewPage={(pageKey) => goToPage(pageKey, "Abrindo a página no site.")}
              />
            )}
          </div>
        )}
      </main>

      {/*
        Bloco 5 do handoff: o rodape era UMA linha de copyright.

        Num portal com nove paginas institucionais, o rodape e o segundo lugar
        onde se procura o mapa do site — o primeiro e o menu, que tinha nove
        itens de 11px sem indicacao de pagina atual. Agora ele repete a
        organizacao do menu novo (Estudar / A Escola / Ajuda), para as duas
        navegacoes dizerem a mesma coisa.

        Os alvos sao os mesmos de `PORTAL_PATHS`; nada aqui inventa destino.
      */}
      <footer className="bg-[#1d2432] text-[#c3c8d2] ava:bg-white ava:text-escult-ink-2 ava:border-t ava:border-ava-linha font-sans">
        {/* Alturas enxugadas: o rodapé é mapa do site, não seção de conteúdo. */}
        <div className="mx-auto max-w-7xl px-8 pt-9 pb-5">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr] gap-x-10 gap-y-7">

            <div className="space-y-3">
              <strong className="block text-[22px] font-extrabold text-white ava:text-ava-tinta tracking-tight">AVASEC</strong>
              <p className="text-rotulo leading-relaxed text-[#c3c8d2] ava:text-escult-ink-2 max-w-xs">
                Ambiente virtual de aprendizagem da Escola Estadual da Cultura.
              </p>
            </div>

            {[
              {
                titulo: 'Estudar',
                itens: [
                  { rotulo: 'Cursos', acao: () => goToPage('cursos', 'Cursos') },
                  { rotulo: 'Certificados', acao: () => goToPage('certificados', 'Certificados') },
                  { rotulo: 'Calendário', acao: () => goToPage('calendario', 'Calendário') },
                ],
              },
              {
                titulo: 'A Escola',
                itens: [
                  { rotulo: 'O que é o AVA', acao: () => goToPage('o-ava', 'O AVA') },
                  { rotulo: 'O Projeto', acao: () => goToPage('o-projeto', 'O Projeto') },
                  { rotulo: 'Notícias', acao: () => goToPage('noticias', 'Notícias') },
                ],
              },
              {
                titulo: 'Ajuda',
                itens: [
                  { rotulo: 'Dúvidas frequentes', acao: () => goToPage('duvidas', 'Dúvidas') },
                  { rotulo: 'Orientações ao estudante', acao: () => goToPage('orientacoes', 'Orientações') },
                  // Acessibilidade e janela, nao pagina: abre o mesmo painel do cabecalho.
                  { rotulo: 'Acessibilidade', acao: () => { setIsAccessibilityOpen(true); speakText('Janela de acessibilidade aberta'); } },
                ],
              },
            ].map((coluna) => (
              <nav key={coluna.titulo} aria-label={coluna.titulo} className="space-y-3">
                <strong className="block text-rotulo font-semibold text-white ava:text-ava-tinta">{coluna.titulo}</strong>
                <ul className="space-y-2">
                  {coluna.itens.map((item) => (
                    <li key={item.rotulo}>
                      <button
                        onClick={item.acao}
                        className="text-rotulo text-[#c3c8d2] ava:text-escult-ink-2 hover:text-white ava:hover:text-ava-acao hover:underline underline-offset-[3px] transition-colors cursor-pointer text-left"
                      >
                        {item.rotulo}
                      </button>
                    </li>
                  ))}
                </ul>
              </nav>
            ))}

          </div>

          {/*
            A barra de acessibilidade passou a viver AQUI, na linha do copyright.

            Ela era uma faixa propria de `py-4` em `bg-slate-950`: uma TERCEIRA
            cor de fundo espremida entre o conteudo e o rodape `#1d2432`, com
            rotulos em caixa alta `font-black`, icones em teal e amarelo e uma
            engrenagem em rotacao infinita. Pesava mais que o conteudo da pagina
            — e barra de acessibilidade e ferramenta de apoio, nao anuncio.

            Nenhuma funcao saiu: acessibilidade, alto contraste, mapa do site e
            os tres idiomas continuam, agora em caixa mista, no `text-apoio` e no
            mesmo cinza que o copyright ja usava (5,28:1 sobre `#1d2432`).
          */}
          <div className="mt-7 pt-4 border-t border-[#313a4a] ava:border-ava-borda flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-apoio text-[#8b93a3] ava:text-escult-ink-2">
              © 2026 AVASEC — Escola Estadual da Cultura. Todos os direitos reservados.
            </p>

            <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-apoio text-[#8b93a3] ava:text-escult-ink-2">
              <button
                onClick={() => { setIsAccessibilityOpen(true); speakText('Janela de acessibilidade aberta'); }}
                className="flex items-center gap-1.5 cursor-pointer transition-colors hover:text-white ava:hover:text-ava-acao hover:underline underline-offset-[3px]"
                title="Ajustar tamanho da fonte, leitor e preferências"
              >
                <Settings className="h-3.5 w-3.5" />
                <span>Acessibilidade</span>
              </button>

              <button
                onClick={() => {
                  const next = !accessibilitySettings.highContrast;
                  updateAccessibilitySettings({ highContrast: next });
                  speakText(next ? 'Alto contraste ativado' : 'Alto contraste desativado');
                }}
                aria-pressed={accessibilitySettings.highContrast}
                className={`flex items-center gap-1.5 cursor-pointer transition-colors hover:text-white ava:hover:text-ava-acao hover:underline underline-offset-[3px] ${
                  accessibilitySettings.highContrast ? 'text-white underline ava:text-ava-acao' : ''
                }`}
                title="Ativar/Desativar cores de alto contraste para baixa visão"
              >
                <Monitor className="h-3.5 w-3.5" />
                <span>Alto contraste</span>
              </button>

              <button
                onClick={() => { setIsSiteMapOpen(true); speakText('Mapa de seções do site aberto'); }}
                className="flex items-center gap-1.5 cursor-pointer transition-colors hover:text-white ava:hover:text-ava-acao hover:underline underline-offset-[3px]"
                title="Exibir mapa do site"
              >
                <BookOpen className="h-3.5 w-3.5" />
                <span>Mapa do site</span>
              </button>

              {/*
                Idioma e UMA escolha entre tres, nao tres acoes soltas: o `role`
                diz isso a quem navega por leitor de tela, e `aria-current` marca
                o que esta em uso — antes so a cor de fundo marcava, e cor
                sozinha nao chega a quem nao a enxerga.
              */}
              <div
                role="group"
                aria-label="Idioma do portal"
                className="flex items-center gap-3 border-l border-[#313a4a] ava:border-ava-borda pl-5"
              >
                {([
                  { id: 'pt', rotulo: 'PT-BR', titulo: 'Português (Brasil)', fala: 'Idioma alterado para Português' },
                  { id: 'en', rotulo: 'EN', titulo: 'English', fala: 'Language changed to English' },
                  { id: 'es', rotulo: 'ES', titulo: 'Español', fala: 'Idioma cambiado a Español' },
                ] as const).map((idioma) => (
                  <button
                    key={idioma.id}
                    onClick={() => { setCurrentLang(idioma.id); speakText(idioma.fala); }}
                    aria-current={currentLang === idioma.id ? 'true' : undefined}
                    className={`cursor-pointer transition-colors underline-offset-[3px] hover:text-white ava:hover:text-ava-acao hover:underline ${
                      currentLang === idioma.id ? 'text-white font-semibold underline ava:text-ava-acao' : ''
                    }`}
                    title={idioma.titulo}
                  >
                    {idioma.rotulo}
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>
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
                  <span className="text-sobretitulo uppercase bg-teal-50 text-teal-700 px-2 py-0.5 rounded-md w-fit block">PREFERÊNCIAS</span>
                  <h3 className="font-black text-slate-900 text-lg font-titulo flex items-center gap-2">
                    <Settings className="h-5 w-5 text-teal-700" />
                    <span>Painel de Acessibilidade da Escola</span>
                  </h3>
                </div>
                <button 
                  onClick={() => setIsAccessibilityOpen(false)}
                  className="text-escult-ink-2 hover:text-slate-800 p-1.5 rounded-full hover:bg-slate-100 transition-all cursor-pointer"
                  title="Fechar"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Preference options list */}
              <div className="space-y-4">
                
                {/* Text Sizing Block */}
                <div className="space-y-2 border-b border-slate-100 pb-4">
                  <span className="text-sobretitulo text-slate-700 block uppercase">Tamanho do Texto [Ampliação]</span>
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
                    <span className="text-sobretitulo text-slate-700 block uppercase">Alto Contraste Visual</span>
                    <p className="text-apoio text-escult-ink-2 leading-tight">Melhora distinção cromática com fundo opaco preto e texto luminoso amarelo/branco.</p>
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
                    <span className="text-sobretitulo text-slate-700 block uppercase font-sans">Fonte de Alta Legibilidade</span>
                    <p className="text-apoio text-escult-ink-2 leading-tight">Altera toda a tipografia do portal para mono-espaçada estruturada, auxiliando leitura seletiva.</p>
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
                    <span className="text-sobretitulo text-slate-700 block uppercase">Feedback Sonoro e Vocalizador</span>
                    <p className="text-apoio text-escult-ink-2 leading-tight">Ativa narração falada automática de botões, tags escolares e menus ao interagir.</p>
                  </div>
                  <button
                    onClick={() => { 
                      const nextState = !isSpeechEnabled;
                      setIsSpeechEnabled(nextState);
                      if (nextState) {
                        setTimeout(() => {
                          if ('speechSynthesis' in window && 'SpeechSynthesisUtterance' in window) {
                            try {
                              window.speechSynthesis.cancel();
                              const utterance = new SpeechSynthesisUtterance("Leitor de tela simulado ativado com sucesso.");
                              utterance.lang = 'pt-BR';
                              window.speechSynthesis.speak(utterance);
                            } catch (err) {
                              console.warn("Speech synthesis error:", err);
                            }
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
                <span className="text-apoio text-escult-ink-2 block">PORTAL HOMOLOGADO CONFORME A PORTARIA DE ACESSIBILIDADE DIGITAL EM LIBRAS E LEITOR</span>
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
                  <span className="text-sobretitulo uppercase bg-escult-surface text-ava-acao px-2 py-0.5 rounded-md w-fit block">NAVEGAÇÃO COMPLETA</span>
                  <h3 className="font-black text-slate-900 text-sm md:text-base font-titulo flex items-center gap-2">
                    <BookOpen className="h-5 w-5 text-ava-acao" />
                    <span>Mapa do Site — Escola Estadual da Cultura</span>
                  </h3>
                </div>
                <button 
                  onClick={() => setIsSiteMapOpen(false)}
                  className="text-escult-ink-2 hover:text-slate-800 p-1.5 rounded-full hover:bg-slate-100 transition-all cursor-pointer"
                  title="Fechar"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Nested site navigation items */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[380px] overflow-y-auto pr-1">
                
                {/* Segment 1: Landing Areas */}
                <div className="bg-slate-50 p-4 rounded-xl space-y-3">
                  <strong className="text-sobretitulo uppercase text-slate-700 flex items-center gap-1.5 border-b border-slate-200 pb-1.5">
                    <Sparkles className="h-4 w-4 text-[#FFD23F]" />
                    <span>Seções Principais</span>
                  </strong>
                  
                  <nav className="flex flex-col gap-2 text-xs">
                    {([
                      { view: 'landing', label: 'Página Inicial (Abertura do Portal)', spoken: 'Ir para a Página Inicial' },
                      { view: 'o-ava', label: 'O que é o AVA (visão geral)', spoken: 'Ir para O AVA' },
                      { view: 'o-projeto', label: 'O Projeto Pedagógico', spoken: 'Ir para O Projeto' },
                      { view: 'cursos', label: 'Cursos Disponíveis (catálogo com filtros)', spoken: 'Ir para Cursos Disponíveis' },
                      { view: 'certificados', label: 'Certificados e Autenticador', spoken: 'Ir para Certificados' },
                      { view: 'calendario', label: 'Calendário de Aulas ao Vivo', spoken: 'Ir para o Calendário' },
                      { view: 'noticias', label: 'Painel Informativo de Notícias', spoken: 'Ir para Informativos Recentes' },
                      { view: 'duvidas', label: 'Central de Dúvidas (FAQ de Alunos)', spoken: 'Ir para FAQ' },
                      { view: 'orientacoes', label: 'Orientações Gerais (Manual do Estudante)', spoken: 'Ir para Orientações' },
                    ] as { view: PortalView; label: string; spoken: string }[]).map((entry) => (
                      <button
                        key={entry.view}
                        onClick={() => { setIsSiteMapOpen(false); goToPage(entry.view, entry.spoken); }}
                        className="text-left py-1 hover:text-ava-acao transition-colors hover:underline block cursor-pointer bg-transparent border-0 outline-hidden font-bold"
                      >
                        • {entry.label}
                      </button>
                    ))}
                  </nav>
                </div>

                {/* Segment 2: Simulation Areas */}
                <div className="bg-slate-50 p-4 rounded-xl space-y-3">
                  <strong className="text-sobretitulo uppercase text-slate-700 flex items-center gap-1.5 border-b border-slate-200 pb-1.5">
                    <ShieldCheck className="h-4 w-4 text-ava-acao" />
                    <span>Acesso ao Portal Acadêmico (AVA)</span>
                  </strong>
                  
                  <div className="flex flex-col gap-2 text-xs">
                    <button 
                      onClick={() => { setIsSiteMapOpen(false); handleProfileLogin('João Silva', 'student'); speakText("Acesso de Aluno Homologado"); }}
                      className="text-left py-1.5 px-2 hover:bg-escult-surface rounded text-ava-acao transition-all font-black flex items-center justify-between bg-transparent border border-transparent cursor-pointer"
                    >
                      <span>• Dashboard do Aluno</span>
                      <span className="text-sobretitulo bg-escult-surface text-ava-acao px-1.5 py-0.2 rounded uppercase">Mapeado</span>
                    </button>
                    <button 
                      onClick={() => { setIsSiteMapOpen(false); handleProfileLogin('Gestor de Conteúdos', 'instructor'); speakText("Acesso de Gestão Homologado"); }}
                      className="text-left py-1.5 px-2 hover:bg-escult-surface rounded text-teal-700 transition-all font-black flex items-center justify-between bg-transparent border border-transparent cursor-pointer"
                    >
                      <span>• Dashboard de Gestão</span>
                      <span className="text-sobretitulo bg-teal-100 text-teal-800 px-1.5 py-0.2 rounded uppercase">Mapeado</span>
                    </button>
                    <button 
                      onClick={() => { setIsSiteMapOpen(false); handleProfileLogin('Admin Superior', 'admin'); speakText("Acesso de Administrador Homologado"); }}
                      className="text-left py-1.5 px-2 hover:bg-amber-50 rounded text-amber-700 transition-all font-black flex items-center justify-between bg-transparent border border-transparent cursor-pointer"
                    >
                      <span>• Moderação de Coordenação</span>
                      <span className="text-sobretitulo bg-amber-100 text-amber-800 px-1.5 py-0.2 rounded uppercase">Mapeado</span>
                    </button>
                  </div>
                </div>

              </div>

              <div className="bg-slate-50/50 border border-slate-150 p-3.5 rounded-xl text-center">
                <span className="text-apoio text-escult-ink-2 block">PORTAL DE CRIAÇÃO E QUALIFICAÇÃO INTEGRADO</span>
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
                className="absolute top-4.5 right-4.5 text-escult-ink-2 hover:text-slate-800 p-1 rounded-full hover:bg-slate-100 transition-all cursor-pointer"
                title="Fechar"
              >
                <X className="h-4.5 w-4.5" />
              </button>

              {/* Title Section */}
              <div className="space-y-1">
                <h3 className="font-extrabold text-[#111] text-base font-titulo flex items-center gap-2">
                  <ShieldCheck className="h-5 w-5 text-ava-acao" />
                  <span>Portal de Conexão Acadêmica</span>
                </h3>
                <p className="text-rotulo text-escult-ink-3">Escolha uma identidade acadêmica simulada para acessar e avaliar as ferramentas de dashboards:</p>
              </div>

              {/* Role Select tab alignment */}
              <div className="grid grid-cols-3 gap-1 bg-slate-100 p-1 rounded-xl text-center border border-slate-200">
                <button
                  onClick={() => setLoginRoleTab('student')}
                  className={`py-2 rounded-lg text-xs font-black uppercase tracking-wider transition-all cursor-pointer ${
                    loginRoleTab === 'student'
                      ? 'bg-ava-acao text-white shadow-3xs'
                      : 'text-escult-ink-2 hover:text-slate-700'
                  }`}
                >
                  Aluno
                </button>
                <button
                  onClick={() => setLoginRoleTab('instructor')}
                  className={`py-2 rounded-lg text-xs font-black uppercase tracking-wider transition-all cursor-pointer ${
                    loginRoleTab === 'instructor'
                      ? 'bg-ava-acao text-white shadow-2xs'
                      : 'text-escult-ink-2 hover:text-slate-700'
                  }`}
                >
                  Gestão
                </button>
                <button
                  onClick={() => setLoginRoleTab('admin')}
                  className={`py-2 rounded-lg text-xs font-black uppercase tracking-wider transition-all cursor-pointer ${
                    loginRoleTab === 'admin'
                      ? 'bg-ava-acao text-white shadow-2xs'
                      : 'text-escult-ink-2 hover:text-slate-700'
                  }`}
                >
                  Admin
                </button>
              </div>

              {/* Dynamic Profiles Content spacing matching images */}
              <div className="space-y-3.5 max-h-[300px] overflow-y-auto pr-1">
                
                {/* 1. Student choices list */}
                {loginRoleTab === 'student' && (
                  <form
                    className="space-y-3.5 animate-in fade-in duration-200 text-left"
                    onSubmit={(e) => { e.preventDefault(); submitStudentLogin(); }}
                  >
                    <span className="text-sobretitulo uppercase text-escult-ink-2 block">
                      Acesso do aluno — entre com o seu CPF
                    </span>

                    <div>
                      <label className="block text-sobretitulo uppercase text-escult-ink-2 mb-1" htmlFor="inp-login-cpf">C.P.F.</label>
                      <input
                        id="inp-login-cpf"
                        type="text"
                        inputMode="numeric"
                        autoComplete="username"
                        value={studentLoginCpf}
                        onChange={(e) => { setStudentLoginCpf(maskCpf(e.target.value)); setStudentLoginError(null); }}
                        placeholder="000.000.000-00"
                        className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-ava-acao focus:ring-1 focus:ring-ava-acao transition-all bg-slate-50/20 text-slate-800"
                      />
                    </div>

                    <div>
                      <label className="block text-sobretitulo uppercase text-escult-ink-2 mb-1" htmlFor="inp-login-password">Senha</label>
                      <input
                        id="inp-login-password"
                        type="password"
                        autoComplete="current-password"
                        value={studentLoginPassword}
                        onChange={(e) => { setStudentLoginPassword(e.target.value); setStudentLoginError(null); }}
                        placeholder="Sua senha de acesso"
                        className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-ava-acao focus:ring-1 focus:ring-ava-acao transition-all bg-slate-50/20 text-slate-800"
                      />
                    </div>

                    {studentLoginError && (
                      <div className="text-apoio font-bold text-rose-600 bg-rose-50 border border-rose-200 rounded-lg p-2.5 leading-relaxed">
                        <span className="flex items-start gap-1.5"><AlertTriangle className="h-3.5 w-3.5 mt-px shrink-0" />{studentLoginError}</span>
                      </div>
                    )}

                    <button
                      type="submit"
                      disabled={isStudentLoggingIn}
                      className="w-full rounded-xl bg-ava-acao hover:bg-purple-950 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sobretitulo px-5 py-3 uppercase transition-all shadow-xs cursor-pointer flex items-center justify-center gap-2"
                    >
                      <span>{isStudentLoggingIn ? 'Verificando...' : 'Entrar'}</span>
                      {!isStudentLoggingIn && <ArrowRight className="h-3.5 w-3.5" />}
                    </button>

                    <p className="text-apoio text-escult-ink-2 leading-relaxed">
                      Ainda não tem cadastro? Feche esta janela e clique em <strong className="text-slate-600">Cadastre-se</strong>.
                      Seu acesso é liberado após a confirmação da coordenação.
                    </p>
                  </form>
                )}

                {/* 2. Professor choices list */}
                {loginRoleTab === 'instructor' && (
                  <div className="space-y-2.5 animate-in fade-in duration-200">
                    <span className="text-sobretitulo uppercase text-escult-ink-2 block">Gestor de Conteúdos ({professorsList.length}):</span>
                    <div className="grid grid-cols-1 gap-2.5">
                      {professorsList.map((prof, idx) => (
                        <div
                          key={`${prof.id}-${idx}`}
                          onClick={() => handleProfileLogin(prof.name, 'instructor')}
                          className="group border border-slate-200 hover:border-slate-350 bg-slate-50/40 hover:bg-slate-50 p-3 rounded-xl cursor-pointer flex justify-between items-center transition-all"
                        >
                          <div className="flex items-center gap-3">
                            <div className="h-8 w-8 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center font-extrabold text-xs text-emerald-500">
                              G
                            </div>
                            <div className="text-left leading-normal">
                              <strong className="text-slate-900 text-xs font-bold block">{prof.name}</strong>
                              <span className="text-apoio text-escult-ink-2 block font-sans">Gestor de Conteúdos</span>
                            </div>
                          </div>
                          <span className="rounded-lg bg-white border border-slate-200 text-slate-650 text-sobretitulo px-3 py-1.5 uppercase group-hover:bg-ava-acao group-hover:text-white transition-all flex items-center gap-1">
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
                    <div className="bg-amber-50/40 border border-amber-200 p-3.5 rounded-xl text-slate-700 text-apoio leading-relaxed">
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
                          <span className="text-apoio text-escult-ink-2 block mt-0.5">Provedor Geral de Segurança Letiva</span>
                        </div>
                      </div>
                      <span className="rounded-lg bg-slate-900 text-white text-sobretitulo px-3 py-1.5 uppercase transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>Ingressar</span>
                        <ArrowRight className="h-3.5 w-3.5 text-[#FFD23F]" />
                      </span>
                    </div>
                  </div>
                )}

              </div>

              {/* Notice text in bottom of login block */}
              <div className="pt-2 text-center">
                <span className="text-apoio text-escult-ink-2 block">AUTENTICAÇÃO SEGURA DE ACORDO COM A LGPD • PORTAL ESCULT</span>
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
                  className="absolute top-4 right-4 p-1.5 text-escult-ink-2 hover:text-slate-650 hover:bg-slate-100 rounded-lg transition-all cursor-pointer border-none bg-transparent"
                  title="Fechar"
                  id="btn-close-register-modal"
                >
                  <X className="h-5 w-5" />
                </button>
              )}

              {/* Title Section */}
              <div className="space-y-1.5 pr-6 mb-5">
                <span className="text-sobretitulo uppercase bg-ava-acao/10 text-ava-acao px-2 py-0.5 rounded-md w-fit block">
                  Célula de Integração Governamental
                </span>
                <h3 className="font-extrabold text-[#111] text-base sm:text-lg flex items-center gap-2">
                  <ShieldCheck className="h-5 w-5 text-ava-acao" />
                  <span>Integração de Cadastro • AVASEC</span>
                </h3>
                <p className="text-xs text-escult-ink-2 leading-relaxed">
                  Conforme solicitado: Você será direcionado para outro site externo onde irá se cadastrar. Preencha seus dados lá e, após validados, esse mesmo cadastro será homologado e usado de forma integrada no <strong className="text-ava-acao font-bold">AVASEC</strong> como sua credencial oficial de estudos.
                </p>
              </div>

              {validationStep === 'idle' && (
                <div className="space-y-5">
                  {/* Passo 1 block */}
                  <div className="relative border border-slate-200 hover:border-ava-acao/35 rounded-2xl p-4 bg-slate-50/50 hover:bg-white transition-all space-y-3">
                    <div className="flex items-start gap-3">
                      <div className="h-7 w-7 rounded-lg bg-ava-acao/5 text-ava-acao flex items-center justify-center text-sobretitulo uppercase">
                        01
                      </div>
                      <div className="space-y-1 flex-1">
                        <h4 className="text-sobretitulo text-slate-800 uppercase">Direcionamento para Portal Externo</h4>
                        <p className="text-rotulo text-escult-ink-2 leading-relaxed">
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
                        className="inline-flex items-center gap-1.5 rounded-lg bg-ava-acao/5 hover:bg-ava-acao/10 text-ava-acao border border-ava-acao/15 px-3 py-1.5 text-sobretitulo uppercase transition-all cursor-pointer no-underline"
                        id="lnk-external-cadastro"
                      >
                        <span>Ir para o Portal de Cadastro</span>
                        <ExternalLink className="h-3 w-3" />
                      </a>
                      
                      {isExternalLinkClicked && (
                        <span className="text-apoio text-emerald-600 font-bold block mt-1.5">
                          <Check className="h-3 w-3 inline-block mr-1 -mt-px" />Conexão externa simulada. Preencha e valide suas informações abaixo para liberá-la no AVASEC.
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Passo 2 form */}
                  <div className="border border-slate-200 rounded-2xl p-4 bg-white space-y-4">
                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
                      <div className="flex items-start gap-3">
                        <div className="h-7 w-7 rounded-lg bg-ava-acao/5 text-ava-acao flex items-center justify-center text-sobretitulo uppercase">
                          02
                        </div>
                        <div className="space-y-1">
                          <h4 className="text-sobretitulo text-slate-800 uppercase">Validação & Sincronização no AVASEC</h4>
                          <p className="text-rotulo text-escult-ink-2 leading-relaxed">
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
                          className="flex items-center gap-1.5 px-3 py-1 rounded-lg bg-slate-50 text-escult-ink-2 hover:text-ava-acao transition-all text-sobretitulo uppercase cursor-pointer border border-slate-200 group"
                        >
                          <ArrowLeft className="h-3 w-3 group-hover:-translate-x-0.5 transition-transform" />
                          <span>Mudar Método</span>
                        </button>
                      )}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pb-1">
                      <div>
                        <label className="block text-sobretitulo uppercase text-escult-ink-2 mb-1">Nome Completo</label>
                        <input 
                          type="text" 
                          required
                          value={registerName}
                          onChange={(e) => {
                            setRegisterName(e.target.value);
                            setValidationError(null);
                          }}
                          placeholder="Ex: João Silva da Silva"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-ava-acao focus:ring-1 focus:ring-ava-acao transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-name"
                        />
                      </div>

                      <div>
                        <div className="flex justify-between items-center mb-1">
                          <label className="block text-sobretitulo uppercase text-escult-ink-2">C.P.F.</label>
                          <span className="text-apoio text-escult-ink-2">Seu login de acesso</span>
                        </div>
                        <input
                          type="text"
                          required
                          inputMode="numeric"
                          value={registerCpf}
                          onChange={(e) => {
                            setRegisterCpf(maskCpf(e.target.value));
                            setValidationError(null);
                          }}
                          placeholder="000.000.000-00"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-ava-acao focus:ring-1 focus:ring-ava-acao transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-cpf"
                        />
                      </div>

                      <div>
                        <label className="block text-sobretitulo uppercase text-escult-ink-2 mb-1">Nome Social (opcional)</label>
                        <input
                          type="text"
                          value={registerNomeSocial}
                          onChange={(e) => setRegisterNomeSocial(e.target.value)}
                          placeholder="Como prefere ser chamado(a)"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-ava-acao focus:ring-1 focus:ring-ava-acao transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-nome-social"
                        />
                      </div>

                      <div>
                        <label className="block text-sobretitulo uppercase text-escult-ink-2 mb-1">Identidade / R.G. (opcional)</label>
                        <input
                          type="text"
                          value={registerIdentidade}
                          onChange={(e) => setRegisterIdentidade(e.target.value)}
                          placeholder="00.000.000-0"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-ava-acao focus:ring-1 focus:ring-ava-acao transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-identidade"
                        />
                      </div>

                      <div>
                        <label className="block text-sobretitulo uppercase text-escult-ink-2 mb-1">Celular</label>
                        <input
                          type="tel"
                          inputMode="numeric"
                          value={registerCelular}
                          onChange={(e) => setRegisterCelular(maskCelular(e.target.value))}
                          placeholder="(00) 00000-0000"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-ava-acao focus:ring-1 focus:ring-ava-acao transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-celular"
                        />
                      </div>

                      <div>
                        <label className="block text-sobretitulo uppercase text-escult-ink-2 mb-1">C.E.P.</label>
                        <input
                          type="text"
                          inputMode="numeric"
                          value={registerCep}
                          onChange={(e) => {
                            setRegisterCep(maskCep(e.target.value));
                            setValidationError(null);
                          }}
                          placeholder="00000-000"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-ava-acao focus:ring-1 focus:ring-ava-acao transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-cep"
                        />
                      </div>

                      <div className="sm:col-span-2">
                        <label className="block text-sobretitulo uppercase text-escult-ink-2 mb-1">Endereço</label>
                        <input
                          type="text"
                          value={registerEndereco}
                          onChange={(e) => setRegisterEndereco(e.target.value)}
                          placeholder="Rua, número, complemento e bairro"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-ava-acao focus:ring-1 focus:ring-ava-acao transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-endereco"
                        />
                      </div>

                      <div className="sm:col-span-2">
                        <label className="block text-sobretitulo uppercase text-escult-ink-2 mb-1">Endereço de E-mail</label>
                        <input 
                          type="email" 
                          required
                          value={registerEmail}
                          onChange={(e) => {
                            setRegisterEmail(e.target.value);
                            setValidationError(null);
                          }}
                          placeholder="seu.email@lms.edu"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-ava-acao focus:ring-1 focus:ring-ava-acao transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-email"
                        />
                      </div>

                      <div className="sm:col-span-2">
                        <div className="flex justify-between items-center mb-1">
                          <label className="block text-sobretitulo uppercase text-escult-ink-2">Senha de Acesso</label>
                          <span className="text-apoio text-escult-ink-2">Mínimo {PASSWORD_MIN_LENGTH} caracteres, com letra e número</span>
                        </div>
                        <input
                          type="password"
                          maxLength={128}
                          required
                          value={registerPassword}
                          onChange={(e) => {
                            setRegisterPassword(e.target.value);
                            setValidationError(null);
                          }}
                          placeholder="Ex: cultura2026"
                          className="w-full text-xs border border-slate-200 rounded-xl px-3 py-2.5 focus:border-ava-acao focus:ring-1 focus:ring-ava-acao transition-all bg-slate-50/20 text-slate-800"
                          id="inp-register-password"
                        />
                      </div>
                    </div>

                    {validationError && (
                      <div className="text-apoio font-bold text-rose-600 bg-rose-50 border border-rose-200 rounded-lg p-2.5 text-center leading-relaxed">
                        <span className="inline-flex items-start gap-1.5"><AlertTriangle className="h-3.5 w-3.5 mt-px shrink-0" />Erro de Registro: {validationError}</span>
                      </div>
                    )}

                    <button
                      onClick={() => {
                        if (!registerName.trim() || !registerEmail.trim() || !registerCpf.trim() || !registerPassword.trim()) {
                          setValidationError("Por favor, preencha os campos obrigatórios: Nome, CPF, E-mail e Senha.");
                          speakText("Por favor, preencha todos os campos obrigatórios.");
                          return;
                        }
                        // CPF é o login do aluno (ADR 11) — checagem local só para
                        // feedback imediato; a autoridade é o backend.
                        if (!isValidCpf(registerCpf)) {
                          setValidationError("O CPF informado não é válido. Confira os dígitos digitados.");
                          speakText("O CPF informado não é válido.");
                          return;
                        }
                        const senhaInvalida = passwordProblem(registerPassword);
                        if (senhaInvalida) {
                          setValidationError(senhaInvalida);
                          speakText(senhaInvalida);
                          return;
                        }
                        if (registerCep.trim() && registerCep.replace(/\D/g, '').length !== 8) {
                          setValidationError("O CEP informado deve ter 8 dígitos.");
                          speakText("O CEP informado é inválido.");
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
                      className="w-full rounded-xl bg-ava-acao hover:bg-[#340845] text-white text-sobretitulo py-3.5 uppercase transition-all cursor-pointer shadow-md flex items-center justify-center gap-1.5 border-none"
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
                    <div className="absolute inset-0 rounded-full border-4 border-slate-100 border-t-ava-acao animate-spin" />
                    <Fingerprint className="h-10 w-10 text-ava-acao animate-pulse" />
                  </div>

                  <div className="space-y-1.5 max-w-sm">
                    <h4 className="text-sobretitulo text-slate-800 uppercase">
                      {validationStep === 'matching' && "Buscando Registro na Rede do Ministério..."}
                      {validationStep === 'verifying' && "Verificando Autenticidade e CPF do Titular..."}
                      {validationStep === 'syncing' && "Homologando Documento Digital Governamental..."}
                    </h4>
                    <p className="text-rotulo text-escult-ink-3 leading-relaxed">
                      {validationStep === 'matching' && "Localizando cadastros sob a infraestrutura do Portal da Cultura e Economia Criativa."}
                      {validationStep === 'verifying' && `Submetendo credencial biométrica do CPF ${registerCpf || "Federal"} aos órgãos de validação.`}
                      {validationStep === 'syncing' && `Sucesso no registro digital! Gravando acesso estudantil no AVASEC de ${registerName}.`}
                    </p>
                  </div>

                  <div className="w-full max-w-xs space-y-1.5">
                    <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                      <div 
                        style={{ width: `${validationProgress}%` }}
                        className="h-full bg-gradient-to-r from-teal-400 via-indigo-500 to-ava-acao transition-all duration-100"
                      />
                    </div>
                    <span className="text-apoio text-escult-ink-2 font-bold block">{validationProgress}% CONCLUÍDO</span>
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
                    <p className="text-rotulo text-escult-ink-2 leading-relaxed">
                      Seu cadastro foi homologado externamente. Use a senha numérica <span className="font-bold text-ava-acao">{registerPassword}</span> para reconectores futuros.
                    </p>
                  </div>

                  {/* Summary Box */}
                  <div className="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-left space-y-2 max-w-md">
                    <span className="text-sobretitulo uppercase text-ava-acao block border-b border-slate-150 pb-1.5">
                      Ficha de Aluno no AVASEC
                    </span>
                    <div className="grid grid-cols-2 gap-2 text-apoio text-slate-600 leading-relaxed">
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
                    onClick={async () => {
                      // Cria a conta real no backend (hash bcrypt). Cadastro público nasce
                      // com status pending_confirmation e NÃO recebe access token — o acesso
                      // só é liberado após homologação pela coordenação.
                      const result = await registerUser(registerName, registerEmail, registerPassword, 'student', {
                        cpf: registerCpf,
                        celular: registerCelular,
                        cep: registerCep,
                        endereco: registerEndereco,
                        nomeSocial: registerNomeSocial,
                        identidade: registerIdentidade,
                      });
                      if (!result.ok) {
                        setValidationError(result.error || 'Não foi possível concluir o cadastro.');
                        return;
                      }
                      // Nada de inserir na lista local aqui: `registerUser` acima já
                      // criou a conta, e a lista da gestão é hidratada de
                      // /api/users?role=student. A chamada que existia aqui era um
                      // SEGUNDO cadastro do mesmo aluno, que o servidor recusava por
                      // e-mail duplicado — falha engolida, sem efeito nenhum.

                      if (result.pending) {
                        speakText('Cadastro recebido. Seu acesso será liberado após a confirmação da coordenação.');
                        window.alert(
                          'Cadastro recebido com sucesso!\n\nSeu acesso será liberado assim que a coordenação da Escola Estadual da Cultura confirmar sua matrícula. Depois da confirmação, entre normalmente com seu e-mail e senha.'
                        );
                      } else {
                        // Conta já ativa (fluxos administrativos): entra direto.
                        executeProfileLogin(registerName, 'student');
                      }
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
                    className="w-full rounded-xl bg-slate-950 hover:bg-ava-acao text-white text-sobretitulo py-3.5 uppercase transition-all cursor-pointer shadow-md border-none"
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
              <div className="mx-auto h-12 w-12 bg-escult-surface border border-escult-line rounded-full flex items-center justify-center mb-3">
                <Fingerprint className="h-6 w-6 text-ava-acao" />
              </div>

              <h3 className="font-extrabold text-slate-900 text-sm text-center uppercase tracking-wider">
                Controle de Acesso AVA
              </h3>
              <p className="text-rotulo text-escult-ink-3 text-center mt-0.5">
                Validação de Fluxo de Segurança LGPD
              </p>

              {/* Account details */}
              <div className="mt-4 p-3 bg-slate-50 border border-slate-200/80 rounded-xl flex items-center gap-2.5 mx-auto max-w-[280px]">
                <div className={`h-6 w-6 shrink-0 rounded-full flex items-center justify-center font-bold text-apoio ${
                  pendingLogin.role === 'admin' 
                    ? 'bg-amber-100 text-amber-800' 
                    : pendingLogin.role === 'instructor' 
                      ? 'bg-emerald-50 text-emerald-700' 
                      : 'bg-ava-acao/10 text-ava-acao'
                }`}>
                  {pendingLogin.name.charAt(0)}
                </div>
                <div className="text-left">
                  <span className="text-rotulo font-black text-slate-800 block leading-tight">{pendingLogin.name}</span>
                  <span className="text-sobretitulo uppercase text-escult-ink-2 block">Identidade: {pendingLogin.role === 'student' ? 'Aluno' : pendingLogin.role === 'instructor' ? 'Instrutor' : 'Administrador'}</span>
                </div>
              </div>

              {/* Campo de senha. Era um teclado de 10 dígitos com o valor
                  filtrado por `replace(/\D/g,'')` e limitado a 8 caracteres —
                  senha alfanumérica não passava por nenhum dos dois limites. */}
              <div className="my-5 space-y-2 text-left">
                <label
                  htmlFor="inp-login-senha"
                  className="block text-sobretitulo uppercase text-escult-ink-2"
                >
                  Senha de acesso
                </label>

                <div className="relative">
                  <input
                    ref={senhaInputRef}
                    id="inp-login-senha"
                    name="senha"
                    type={senhaVisivel ? 'text' : 'password'}
                    autoComplete="current-password"
                    maxLength={128}
                    value={senhaInput}
                    onChange={(e) => {
                      setSenhaInput(e.target.value);
                      setSenhaErro(null);
                    }}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' && senhaInput !== '' && !senhaOk) {
                        verificarSenhaEEntrar();
                      }
                    }}
                    disabled={senhaOk}
                    placeholder="Digite a sua senha"
                    className="w-full rounded-xl border border-slate-200 bg-slate-50/60 py-2.5 pl-3 pr-10 text-sm text-slate-800 placeholder:text-slate-350 focus:border-ava-acao focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-ava-acao disabled:opacity-60"
                  />

                  <button
                    type="button"
                    onClick={() => setSenhaVisivel((v) => !v)}
                    disabled={senhaOk}
                    title={senhaVisivel ? 'Ocultar senha' : 'Mostrar senha'}
                    aria-label={senhaVisivel ? 'Ocultar senha' : 'Mostrar senha'}
                    className="absolute right-1 top-1/2 -translate-y-1/2 cursor-pointer rounded-lg p-1.5 text-escult-ink-2 hover:bg-slate-100 hover:text-ava-acao"
                  >
                    {senhaVisivel ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>

                {senhaErro && (
                  <span className="inline-block rounded border border-rose-200/60 bg-rose-50 px-2 py-0.5 text-apoio font-bold text-rose-600">
                    {senhaErro}
                  </span>
                )}

                {senhaOk && (
                  <span className="inline-block rounded border border-emerald-250 bg-emerald-50 px-2 py-0.5 text-apoio font-bold text-emerald-700">
                    <Check className="mr-1 -mt-px inline-block h-3 w-3" />Credencial Homologada!
                  </span>
                )}
              </div>

              <button
                type="button"
                onClick={verificarSenhaEEntrar}
                disabled={senhaOk || senhaInput === '' || isPinVerifying}
                className={`w-full rounded-xl py-2.5 text-sobretitulo uppercase shadow-3xs transition-all active:scale-[0.99] ${
                  senhaInput !== '' && !senhaOk && !isPinVerifying
                    ? 'cursor-pointer bg-ava-acao text-white hover:bg-[#6e118f]'
                    : 'pointer-events-none border border-slate-200 bg-slate-100 text-slate-350'
                }`}
              >
                {isPinVerifying ? 'Verificando...' : 'Entrar'}
              </button>

              {/* Dica de PINs — SOMENTE desenvolvimento. Os valores NÃO ficam escritos
                  aqui: vêm de VITE_DEMO_PINS (ver src/dev/demoProfiles.ts), porque texto
                  no JSX vai para o pacote mesmo atrás de um guard de dev. Sem a variável
                  a lista é vazia e o bloco não existe. Expor senha padrão nesta tela,
                  somado ao login por nome, é tomada de conta real. */}
              {import.meta.env.DEV && demoProfiles.length > 0 && (
                <div className="mt-5 p-3 bg-slate-50 border border-slate-200/80 rounded-xl text-left text-apoio text-escult-ink-2 leading-relaxed">
                  <span className="font-bold text-ava-acao block mb-0.5 uppercase tracking-wide">Dica para Avaliação do Fluxo (dev):</span>
                  {demoProfiles.map(p => (
                    <span key={p.name} className="block">
                      • {p.label}: <code className="font-extrabold text-slate-800">{p.pin}</code>
                    </span>
                  ))}
                  <span className="text-apoio text-escult-ink-2 block mt-1 leading-normal">
                    (Senhas customizadas no perfil também servem para desbloqueio do aluno).
                  </span>
                </div>
              )}

              {/* Cancel button */}
              <div className="text-center mt-3.5">
                <button
                  onClick={() => setPendingLogin(null)}
                  disabled={senhaOk}
                  className="text-sobretitulo text-escult-ink-2 hover:text-slate-600 uppercase ease-in-out transition-colors cursor-pointer bg-transparent border-none py-1"
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
