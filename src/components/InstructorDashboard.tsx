/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import {
  BookOpen, Calendar, CheckCircle, Award, Video, Plus, Trash2, Edit3, Users,
  Globe, Clock, Grid, ChevronRight, TrendingUp, Sparkles, Send, Info, Check, Link, Play, ArrowLeft,
  MessageSquare, CheckSquare, Bell, FileText, Layout, BarChart3, Archive, ShieldCheck, ExternalLink,
  ChevronDown, ChevronUp, ArrowUp, ArrowDown, Eye, EyeOff, File, Download
} from 'lucide-react';
import { useLMS } from '../context/LMSContext';
import { Course, Lesson, LiveSession } from '../types';

interface InstructorDashboardProps {
  onBackToLanding?: () => void;
  speakText: (text: string) => void;
}

export const InstructorDashboard: React.FC<InstructorDashboardProps> = ({ onBackToLanding, speakText }) => {
  const {
    courses,
    addCourse,
    addLessonToCourse,
    addLiveSessionToCourse,
    setLiveSessionStatus,
    progress,
    certificates,
    activeUser,
    directMessages,
    sendDirectMessage,
    quizzes,
    quizSubmissions,
    addQuiz,
    deleteQuiz,
    studentsList,
    systemSettings,
    activeDashboardTab,
    setActiveDashboardTab,
    addLibraryItem,
    addWebinarEvent,
    updateCourseProps,
    updateLesson,
    deleteLesson,
    removeLiveSession,
    calculateAttendancePercent,
    admissionRequests,
    updateAdmissionStatus
  } = useLMS();

  // Active form sections
  const [selectedCourseId, setSelectedCourseId] = useState<string>(courses[0]?.id || '');
  
  // Advanced Tools States
  const [isEditingCourse, setIsEditingCourse] = useState(false);
  const [isCreatingLibraryItem, setIsCreatingLibraryItem] = useState(false);
  const [isCreatingWebinar, setIsCreatingWebinar] = useState(false);

  // Edit Course Meta
  const [editTitle, setEditTitle] = useState('');
  const [editDesc, setEditDesc] = useState('');
  const [editCategory, setEditCategory] = useState('');

  // Library Item Meta
  const [libTitle, setLibTitle] = useState('');
  const [libCategory, setLibCategory] = useState('Documentação');
  const [libType, setLibType] = useState<'pdf' | 'link' | 'video' | 'zip'>('pdf');
  const [libUrl, setLibUrl] = useState('');
  const [libFile, setLibFile] = useState<File | null>(null);
  const [libDragging, setLibDragging] = useState(false);
  const [libUploadMethod, setLibUploadMethod] = useState<'upload' | 'url'>('upload');

  // Webinar Meta
  const [webTitle, setWebTitle] = useState('');
  const [webHost, setWebHost] = useState('Alessandro Pinto');
  const [webDate, setWebDate] = useState('');
  const [webTime, setWebTime] = useState('');
  const [webLink, setWebLink] = useState('');
  
  // Custom Category State
  const [customCategory, setCustomCategory] = useState('');
  const [selectedStudentName, setSelectedStudentName] = useState('João Silva');

  // List of students that are simulated in the dropdown
  const studentsWithMessages = studentsList.map(s => s.name);
  const unrepliedStudents = studentsWithMessages.filter(studentName => {
    const studentDMs = directMessages.filter(m => m.studentName === studentName);
    if (studentDMs.length === 0) return false;
    const latestMsg = studentDMs[studentDMs.length - 1];
    return latestMsg.senderRole === 'student'; // Unanswered by the instructor
  });

  const handleBack = () => {
    if (isEditingCourse) {
      setIsEditingCourse(false);
    } else if (isCreatingCourse) {
      setIsCreatingCourse(false);
    } else if (isCreatingLesson) {
      setIsCreatingLesson(false);
    } else if (isCreatingQuiz) {
      setIsCreatingQuiz(false);
    } else if (isCreatingWebinar) {
      setIsCreatingWebinar(false);
    } else if (isCreatingLive) {
      setIsCreatingLive(false);
    } else if (activeDashboardTab !== 'general') {
      setActiveDashboardTab('general');
    } else if (onBackToLanding) {
      onBackToLanding();
    }
  };

  const getBackLabel = () => {
    if (isEditingCourse || isCreatingCourse || isCreatingLesson || isCreatingQuiz || isCreatingWebinar || isCreatingLive) {
      return "Voltar p/ Gestão";
    }
    if (activeDashboardTab !== 'general') {
      return "Voltar p/ Gestão";
    }
    return "Sair p/ Portal";
  };

  // Quiz Builder State
  const [isCreatingQuiz, setIsCreatingQuiz] = useState(false);
  const [quizTitle, setQuizTitle] = useState('');
  const [quizQuestions, setQuizQuestions] = useState<{ id: string; questionText: string; options: string[]; correctOptionIndex: number }[]>([]);
  const [tempQuestionText, setTempQuestionText] = useState('');
  const [tempOption0, setTempOption0] = useState('');
  const [tempOption1, setTempOption1] = useState('');
  const [tempOption2, setTempOption2] = useState('');
  const [tempOption3, setTempOption3] = useState('');
  const [tempCorrectIndex, setTempCorrectIndex] = useState(0);

  // Create Course State
  const [isCreatingCourse, setIsCreatingCourse] = useState(false);
  const [newCourseTitle, setNewCourseTitle] = useState('');
  const [newCourseDesc, setNewCourseDesc] = useState('');
  const [newCourseCategory, setNewCourseCategory] = useState('Programação');
  const [newCourseInstructor, setNewCourseInstructor] = useState(activeUser.name);

  // Create Lesson State
  const [isCreatingLesson, setIsCreatingLesson] = useState(false);
  const [lessonTitle, setLessonTitle] = useState('');
  const [lessonDuration, setLessonDuration] = useState('15 min');
  const [lessonContent, setLessonContent] = useState('');
  const [lessonVideoUrl, setLessonVideoUrl] = useState('');

  // Lesson Management (Edit)
  const [isEditingLesson, setIsEditingLesson] = useState(false);
  const [editingLessonId, setEditingLessonId] = useState('');
  const [editLessonTitle, setEditLessonTitle] = useState('');
  const [editLessonDuration, setEditLessonDuration] = useState('');
  const [editLessonContent, setEditLessonContent] = useState('');
  const [editLessonVideoUrl, setEditLessonVideoUrl] = useState('');

  // Expanded & Documents state inside curriculum
  const [expandedLessonId, setExpandedLessonId] = useState<string | null>(null);
  const [newDocTitle, setNewDocTitle] = useState('');
  const [newDocType, setNewDocType] = useState<'pdf' | 'doc' | 'url' | 'drive' | 'outro'>('pdf');
  const [newDocUrl, setNewDocUrl] = useState('');
  const [newDocSize, setNewDocSize] = useState('1.2 MB');

  // Create Live Session State
  const [isCreatingLive, setIsCreatingLive] = useState(false);
  const [liveTitle, setLiveTitle] = useState('');
  const [liveDate, setLiveDate] = useState('Hoje, às 20:00');
  const [liveDuration, setLiveDuration] = useState(60);
  const [liveMeetingLink, setLiveMeetingLink] = useState('https://meet.google.com/abc-defg-hij');

  // Interactive Toast
  const [toastMsg, setToastMsg] = useState('');

  const handleCreateCourse = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newCourseTitle.trim() || !newCourseDesc.trim()) return;

    const finalCategory = newCourseCategory === "Escrever Outra..." ? customCategory.trim() : newCourseCategory;
    if (!finalCategory) {
      showToast('Por favor, informe a categoria.');
      return;
    }

    const newCourseObj: Course = {
      id: `course-${Date.now()}`,
      title: newCourseTitle.trim(),
      description: newCourseDesc.trim(),
      category: finalCategory,
      thumbnail: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=60',
      instructorName: newCourseInstructor.trim(),
      lessons: [],
      liveSessions: []
    };

    addCourse(newCourseObj);
    setSelectedCourseId(newCourseObj.id);
    
    // Reset form
    setNewCourseTitle('');
    setNewCourseDesc('');
    setCustomCategory('');
    setIsCreatingCourse(false);
    showToast('Novo curso cadastrado com sucesso!');
  };

  const handleCreateLesson = (e: React.FormEvent) => {
    e.preventDefault();
    if (!lessonTitle.trim() || !lessonContent.trim()) return;

    addLessonToCourse(selectedCourseId, lessonTitle.trim(), lessonDuration, lessonContent.trim(), lessonVideoUrl.trim());
    
    // Reset form
    setLessonTitle('');
    setLessonContent('');
    setLessonVideoUrl('');
    setIsCreatingLesson(false);
    showToast('Nova aula de fixação adicionada ao curso!');
  };
  
  const handleUpdateLesson = (e: React.FormEvent) => {
    e.preventDefault();
    updateLesson(selectedCourseId, editingLessonId, {
      title: editLessonTitle.trim(),
      duration: editLessonDuration.trim(),
      content: editLessonContent.trim(),
      videoUrl: editLessonVideoUrl.trim()
    });
    setIsEditingLesson(false);
    showToast('Aula atualizada com sucesso!');
  };

  const handleDeleteLesson = (lessonId: string, title: string) => {
    if (confirm(`Deseja realmente excluir a aula "${title}"?`)) {
      deleteLesson(selectedCourseId, lessonId);
      showToast('Aula removida do currículo.');
    }
  };

  const handleAddDocument = (lessonId: string) => {
    if (!newDocTitle.trim() || !newDocUrl.trim()) {
      showToast("Preencha o título e link do documento!");
      return;
    }

    const currentCourse = courses.find(c => c.id === selectedCourseId);
    if (!currentCourse) return;

    const lesson = currentCourse.lessons.find(l => l.id === lessonId);
    if (lesson) {
      const docs = lesson.documents || [];
      const newDoc = {
        id: `doc-${Date.now()}`,
        title: newDocTitle.trim(),
        type: newDocType,
        url: newDocUrl.trim(),
        size: newDocType === 'pdf' ? (newDocSize.trim() || '1.2 MB') : undefined
      };
      const updatedDocs = [...docs, newDoc];
      
      updateLesson(selectedCourseId, lessonId, { documents: updatedDocs });
      showToast("Documento anexado com sucesso!");
      
      // reset
      setNewDocTitle('');
      setNewDocUrl('');
      setNewDocSize('1.2 MB');
    }
  };

  const handleDeleteDocument = (lessonId: string, docId: string) => {
    const currentCourse = courses.find(c => c.id === selectedCourseId);
    if (!currentCourse) return;

    const lesson = currentCourse.lessons.find(l => l.id === lessonId);
    if (lesson) {
      const docs = lesson.documents || [];
      const updatedDocs = docs.filter(d => d.id !== docId);
      updateLesson(selectedCourseId, lessonId, { documents: updatedDocs });
      showToast("Documento removido.");
    }
  };

  const handleMoveLesson = (index: number, direction: 'up' | 'down') => {
    const currentCourse = courses.find(c => c.id === selectedCourseId);
    if (!currentCourse) return;

    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= currentCourse.lessons.length) return;
    
    const updatedLessons = [...currentCourse.lessons];
    const [removed] = updatedLessons.splice(index, 1);
    updatedLessons.splice(newIndex, 0, removed);
    
    // Update all orders
    const sortedLessons = updatedLessons.map((l, i) => ({ ...l, order: i + 1 }));
    updateCourseProps(selectedCourseId, { lessons: sortedLessons });
    showToast("Ordem das aulas atualizada!");
  };

  const handleRemoveLiveSession = (sessionId: string, title: string) => {
    if (confirm(`Deseja realmente excluir a transmissão "${title}"?`)) {
      removeLiveSession(selectedCourseId, sessionId);
      showToast('Transmissão removida com sucesso!');
    }
  };

  const handleCreateLive = (e: React.FormEvent) => {
    e.preventDefault();
    if (!liveTitle.trim() || !liveMeetingLink.trim()) return;

    addLiveSessionToCourse(
      selectedCourseId,
      liveTitle.trim(),
      liveDate.trim(),
      Number(liveDuration),
      liveMeetingLink.trim(),
      false // default not live yet
    );

    // Reset form
    setLiveTitle('');
    setLiveMeetingLink('');
    setIsCreatingLive(false);
    showToast('E-encontro ao vivo agendado com sucesso!');
  };

  const handleEditCourse = (e: React.FormEvent) => {
    e.preventDefault();
    updateCourseProps(selectedCourseId, {
      title: editTitle,
      description: editDesc,
      category: editCategory
    });
    setIsEditingCourse(false);
    showToast('Dados do curso atualizados com sucesso!');
  };

  const handleAddLibraryItem = (e: React.FormEvent) => {
    e.preventDefault();
    
    let finalUrl = libUrl;
    let finalType: 'pdf' | 'video' | 'link' = 'link';
    if (libType === 'pdf') finalType = 'pdf';
    else if (libType === 'video') finalType = 'video';

    if (libUploadMethod === 'upload') {
      if (!libFile) {
        showToast('Por favor, faça o upload de um documento ou insira um link.');
        return;
      }
      finalUrl = URL.createObjectURL(libFile);
      if (libFile.name.toLowerCase().endsWith('.pdf')) {
        finalType = 'pdf';
      } else {
        finalType = 'link'; // standard doc file representation
      }
    } else {
      if (!libUrl.trim()) {
        showToast('Por favor, preencha o link/URL do recurso!');
        return;
      }
    }

    addLibraryItem({
      title: libTitle.trim() || (libFile ? libFile.name : 'Recurso'),
      category: libCategory.trim() || 'Documentação',
      type: finalType,
      url: finalUrl,
      courseId: selectedCourseId,
      date: new Date().toLocaleDateString('pt-BR')
    } as any);

    setLibTitle('');
    setLibUrl('');
    setLibFile(null);
    setIsCreatingLibraryItem(false);
    showToast('Recurso adicionado à biblioteca digital!');
  };

  const handleAddWebinar = (e: React.FormEvent) => {
    e.preventDefault();
    addWebinarEvent({
      title: webTitle,
      host: webHost,
      date: webDate,
      time: webTime,
      type: 'aula-especial',
      link: webLink
    });
    setWebTitle('');
    setWebLink('');
    setIsCreatingWebinar(false);
    showToast('Webinar global agendado na agenda da escola!');
  };

  const toggleLiveTransmit = (courseId: string, sessionId: string, currentlyLive: boolean) => {
    setLiveSessionStatus(courseId, sessionId, !currentlyLive);
    showToast(!currentlyLive ? 'Transmissão marcada como ATIVA! Alunos podem entrar na sala.' : 'Transmissão encerrada.');
  };

  const handleAddQuestionToQuiz = () => {
    if (!tempQuestionText.trim()) {
      showToast('Por favor, informe o texto da pergunta.');
      return;
    }
    if (!tempOption0.trim() || !tempOption1.trim() || !tempOption2.trim() || !tempOption3.trim()) {
      showToast('Por favor, preencha as 4 opções de resposta.');
      return;
    }
    const newQuestion = {
      id: `q-${Date.now()}-${Math.random().toString(36).substring(2, 6)}`,
      questionText: tempQuestionText.trim(),
      options: [tempOption0.trim(), tempOption1.trim(), tempOption2.trim(), tempOption3.trim()],
      correctOptionIndex: tempCorrectIndex
    };
    setQuizQuestions((prev) => [...prev, newQuestion]);

    // reset fields
    setTempQuestionText('');
    setTempOption0('');
    setTempOption1('');
    setTempOption2('');
    setTempOption3('');
    setTempCorrectIndex(0);
    showToast('Questão adicionada ao rascunho do teste!');
  };

  const handlePublishQuiz = (e: React.FormEvent) => {
    e.preventDefault();
    if (!quizTitle.trim()) {
      showToast('Por favor, informe o título do teste.');
      return;
    }
    if (quizQuestions.length === 0) {
      showToast('Adicione pelo menos 1 questão antes de publicar.');
      return;
    }

    addQuiz(selectedCourseId, quizTitle.trim(), quizQuestions);

    setQuizTitle('');
    setQuizQuestions([]);
    setIsCreatingQuiz(false);
    showToast('Novo teste gerado com sucesso para os alunos!');
  };

  const showToast = (msg: string) => {
    setToastMsg(msg);
    setTimeout(() => setToastMsg(''), 4000);
  };

  const activeCourse = courses.find((c) => c.id === selectedCourseId) || courses[0];

  // Simulated Student Directory tracking data
  const simulatedStudents = studentsList.map(item => ({
    name: item.name,
    email: item.email,
    hasCertificate: certificates.some(cer => cer.courseId === selectedCourseId && cer.studentName === item.name)
  }));

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 md:px-6">
      
      {/* Instructor Dashboard Welcome Banner */}
      <div className="mb-8 rounded-2xl bg-linear-to-r from-slate-900 to-teal-950 p-6 border border-slate-800 text-left flex flex-col justify-between gap-6 md:flex-row md:items-center shadow-lg">
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-6">
          {onBackToLanding && (
            <button
              onClick={() => {
                const label = getBackLabel();
                speakText(`${label}. Voltando um nível na gestão.`);
                handleBack();
              }}
              className="group flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-400 hover:text-white hover:bg-slate-750 transition-all cursor-pointer shadow-3xs"
              title={getBackLabel()}
            >
              <ArrowLeft className="h-4 w-4 group-hover:-translate-x-0.5 transition-transform" />
              <span className="text-[10px] font-bold uppercase tracking-tighter">{getBackLabel()}</span>
            </button>
          )}

          <div>
            <span className="rounded-full bg-teal-500/10 px-2.5 py-1 text-[11px] font-bold text-teal-400 border border-teal-500/20">
              Painel do Instrutor-Gestor
            </span>
            <h2 className="text-xl md:text-2xl font-black text-slate-100 mt-2">
              Gestão Pedagógica do AVA
            </h2>
            <p className="text-xs text-slate-400 mt-1">
              Gestor de Cursos • Controle de conteúdos, encontros e acompanhamento acadêmico.
            </p>
          </div>
        </div>

        <button
          onClick={() => setIsCreatingCourse(true)}
          className="shrink-0 bg-teal-600 hover:bg-teal-500 text-white font-bold px-4 py-2.5 rounded-lg text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-md"
        >
          <Plus className="h-4 w-4" />
          <span>Cadastrar Novo Curso</span>
        </button>
      </div>

      {/* Dynamic Tab Navigation System */}
      {systemSettings.allowDirectMessages && (
        <div className="flex bg-slate-100 p-1.5 rounded-2xl gap-1.5 mb-10 overflow-x-auto scrollbar-hide md:justify-center w-full max-w-4xl mx-auto shadow-3xs border border-slate-200">
          <button
            onClick={() => setActiveDashboardTab('general')}
            className={`px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 cursor-pointer ${
              activeDashboardTab === 'general'
                ? 'bg-[#540D6E] text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50 font-bold'
            }`}
          >
            <Layout className="h-4 w-4" />
            <span>Gestão do Curso</span>
          </button>
          <button
            onClick={() => setActiveDashboardTab('curriculum')}
            className={`px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 cursor-pointer ${
              activeDashboardTab === 'curriculum'
                ? 'bg-[#540D6E] text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50 font-bold'
            }`}
          >
            <BookOpen className="h-4 w-4" />
            <span>Grade Curricular</span>
          </button>
          <button
            onClick={() => setActiveDashboardTab('messages')}
            className={`px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 cursor-pointer relative ${
              activeDashboardTab === 'messages'
                ? 'bg-[#540D6E] text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50 font-bold'
            }`}
          >
            <MessageSquare className="h-4 w-4" />
            <span>Mensagens Recebidas</span>
            {unrepliedStudents.length > 0 && (
              <span className="absolute -top-1 -right-1 flex h-4.5 w-4.5 items-center justify-center rounded-full bg-rose-600 text-[9px] font-black text-white ring-2 ring-white">
                {unrepliedStudents.length}
              </span>
            )}
          </button>
          <button
            onClick={() => setActiveDashboardTab('students')}
            className={`px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 cursor-pointer ${
              activeDashboardTab === 'students'
                ? 'bg-[#540D6E] text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50 font-bold'
            }`}
          >
            <Users className="h-4 w-4" />
            <span>Gestão de Alunos</span>
          </button>
        </div>
      )}

      {activeDashboardTab === 'general' && (
        /* Main split row layout */
        <div className="grid grid-cols-1 gap-8 lg:grid-cols-3 animate-in fade-in duration-300">
        
        {/* Course details, lessons manager, webinar creator list */}
        <div className="lg:col-span-2 space-y-6 text-left">
          
          {/* Active Course Selector block */}
          <div className="rounded-2xl border border-slate-200 bg-white p-5">
            <label className="block text-xs font-bold text-slate-500 uppercase mb-2">Selecione o Curso a Gerenciar</label>
            <select
              value={selectedCourseId}
              onChange={(e) => setSelectedCourseId(e.target.value)}
              className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm font-semibold text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-teal-500 transition-colors"
            >
              {courses.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.category} • {c.title}
                </option>
              ))}
            </select>
          </div>

          {activeCourse && (
            <div className="rounded-2xl border border-slate-250 bg-white p-6 shadow-xs space-y-6">
              
              {/* Course Title metadata header */}
              <div className="border-b border-slate-100 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                  <span className="rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-semibold text-teal-600">
                    {activeCourse.category}
                  </span>
                  <h3 className="text-xl font-bold text-slate-900 mt-2">{activeCourse.title}</h3>
                  <p className="text-xs text-slate-500 mt-1">Instrutor ativo: {activeCourse.instructorName}</p>
                </div>

                <div className="flex gap-2 flex-wrap sm:flex-nowrap">
                  <button
                    onClick={() => {
                      setEditTitle(activeCourse.title);
                      setEditDesc(activeCourse.description);
                      setEditCategory(activeCourse.category);
                      setIsEditingCourse(true);
                    }}
                    className="rounded-lg bg-indigo-50 border border-indigo-100 hover:bg-indigo-100 px-2 sm:px-3 py-2 text-[11px] font-bold text-indigo-700 transition-colors flex items-center gap-1 cursor-pointer"
                  >
                    <Edit3 className="h-3.5 w-3.5" />
                    <span>Editar Props</span>
                  </button>
                  <button
                    onClick={() => setActiveDashboardTab('curriculum')}
                    className="rounded-lg bg-slate-900 border border-slate-800 hover:bg-slate-800 px-2 sm:px-3 py-2 text-[11px] font-bold text-white transition-colors flex items-center gap-1 cursor-pointer"
                  >
                    <BookOpen className="h-3.5 w-3.5" />
                    <span>Editar Aulas</span>
                  </button>
                  <button
                    onClick={() => setIsCreatingLive(true)}
                    className="rounded-lg bg-teal-50 border border-teal-100 hover:bg-teal-100 px-2 sm:px-3 py-2 text-[11px] font-bold text-teal-700 transition-colors flex items-center gap-1 cursor-pointer"
                  >
                    <Video className="h-3.5 w-3.5" />
                    <span>Agendar Encontro</span>
                  </button>
                  <button
                    onClick={() => {
                      setQuizTitle('');
                      setQuizQuestions([]);
                      setIsCreatingQuiz(true);
                    }}
                    className="rounded-lg bg-amber-50 border border-amber-100 hover:bg-amber-100 px-2 sm:px-3 py-2 text-[11px] font-bold text-amber-700 transition-colors flex items-center gap-1 cursor-pointer"
                  >
                    <CheckSquare className="h-3.5 w-3.5" />
                    <span>Criar Avaliação</span>
                  </button>
                </div>
              </div>

              {/* Course Syllabus outline summary (Reduced Version) */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                {/* Simplified Lesson View */}
                <div>
                  <h4 className="font-bold text-slate-800 text-xs uppercase tracking-wide mb-3 flex items-center gap-1.5">
                    <BookOpen className="h-4 w-4 text-teal-500" />
                    <span>Currículo atual ({activeCourse.lessons.length})</span>
                  </h4>
                  <div className="space-y-1.5">
                    {activeCourse.lessons.slice(0, 4).map(l => (
                      <div key={l.id} className="text-[11px] font-semibold text-slate-600 flex items-center gap-2">
                        <CheckCircle className="h-3 w-3 text-teal-400" />
                        <span className="truncate">{l.title}</span>
                      </div>
                    ))}
                    {activeCourse.lessons.length > 4 && (
                      <button 
                        onClick={() => setActiveDashboardTab('curriculum')}
                        className="text-[10px] font-bold text-teal-600 hover:underline mt-1"
                      >
                        + ver mais {activeCourse.lessons.length - 4} aulas na aba Grade
                      </button>
                    )}
                  </div>
                </div>

                {/* 2. Live broadcasts management */}
                <div>
                  <h4 className="font-bold text-slate-800 text-xs uppercase tracking-wide mb-3 flex items-center gap-1.5">
                    <Video className="h-4 w-4 text-emerald-500" />
                    <span>Transmissões ao Vivo ({activeCourse.liveSessions.length})</span>
                  </h4>

                  <div className="space-y-2">
                    {activeCourse.liveSessions.length === 0 ? (
                      <div className="rounded-xl border border-dashed border-slate-200 p-6 text-center text-xs text-slate-400">
                        Nenhum encontro agendado.
                      </div>
                    ) : (
                      activeCourse.liveSessions.map((session) => (
                        <div key={session.id} className="p-3 rounded-lg border border-slate-100 bg-slate-50 text-xs">
                          <div className="flex items-start justify-between gap-2">
                            <div className="text-left min-w-0 flex-1">
                              <span className="font-semibold text-slate-800 block truncate">{session.title}</span>
                              <span className="text-[9px] text-slate-400 block mt-1">{session.scheduledAt} ({session.durationMinutes} min)</span>
                            </div>

                            <div className="flex items-center gap-2">
                              <button
                                onClick={() => handleRemoveLiveSession(session.id, session.title)}
                                className="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-md transition-colors cursor-pointer"
                                title="Excluir Transmissão"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </button>
                              {session.isLive ? (
                                <span className="bg-red-500 text-white text-[8px] font-bold px-1.5 py-0.5 rounded animate-pulse">
                                  Ativa ao Vivo
                                </span>
                              ) : (
                                <span className="bg-slate-200 text-slate-600 text-[8px] font-semibold px-1.5 py-0.5 rounded">
                                  Agendada
                                </span>
                              )}
                            </div>
                          </div>

                          <div className="flex items-center justify-between mt-3 pt-3 border-t border-slate-150">
                            <span className="text-[9px] text-slate-500 flex items-center gap-1 font-mono truncate max-w-[120px]">
                              <Link className="h-3 w-3 inline" />
                              {session.meetingLink}
                            </span>
                            
                            <button
                              onClick={() => toggleLiveTransmit(activeCourse.id, session.id, session.isLive)}
                              className={`px-3 py-1 rounded text-[10px] font-bold transition-all flex items-center gap-1 cursor-pointer ${
                                session.isLive 
                                  ? 'bg-amber-600 hover:bg-amber-500 text-white' 
                                  : 'bg-emerald-600 hover:bg-emerald-500 text-white'
                              }`}
                            >
                              <Play className="h-3 w-3 shrink-0" />
                              <span>{session.isLive ? 'Finalizar' : 'Iniciar'}</span>
                            </button>
                          </div>
                        </div>
                      ))
                    )}
                  </div>
                </div>

              </div>

              {/* Course Quizzes Section */}
              <div className="border-t border-slate-100 pt-5">
                <h4 className="font-bold text-slate-800 text-xs uppercase tracking-wide mb-4 flex items-center gap-1.5">
                  <CheckSquare className="h-4 w-4 text-amber-500" />
                  <span>Avaliações Elaboradas ({quizzes.filter(q => q.courseId === activeCourse.id).length})</span>
                </h4>

                {quizzes.filter(q => q.courseId === activeCourse.id).length > 0 && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {quizzes.filter(q => q.courseId === activeCourse.id).map(quiz => (
                      <div key={quiz.id} className="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                        <div className="min-w-0">
                          <span className="block font-bold text-slate-900 truncate text-[11px]">{quiz.title}</span>
                          <span className="text-[9px] text-slate-500 uppercase font-bold">{quiz.questions.length} Questões</span>
                        </div>
                        <button
                          onClick={() => deleteQuiz(quiz.id)}
                          className="p-1.5 text-slate-400 hover:text-red-600 rounded-md transition-colors"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    ))}
                  </div>
                )}
              </div>

            </div>
          )}
        </div>

        {/* Right column: Advanced Toolbox */}
        <div className="space-y-6 text-left">
          {/* Reuse the Metrics/Tools sections from original general tab */}
          <section className="space-y-4">
            <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2 px-1">
              <Grid className="h-3.5 w-3.5" />
              <span>Ferramentas de Extensão</span>
            </h4>
            
            <div className="grid grid-cols-1 gap-3">
              <button 
                onClick={() => setIsCreatingLibraryItem(true)}
                className="w-full p-4 rounded-2xl border border-slate-200 bg-white hover:border-teal-300 hover:shadow-sm transition-all text-left flex items-center gap-4 group cursor-pointer"
              >
                <div className="p-3 rounded-xl bg-teal-50 text-teal-600 group-hover:bg-teal-100 transition-colors">
                  <Archive className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <span className="block font-bold text-slate-900 text-xs">Biblioteca Digital</span>
                  <p className="text-[10px] text-slate-500 leading-tight mt-0.5">Gestão de arquivos.</p>
                </div>
              </button>
              <button 
                onClick={() => setIsCreatingWebinar(true)}
                className="w-full p-4 rounded-2xl border border-slate-200 bg-white hover:border-indigo-300 hover:shadow-sm transition-all text-left flex items-center gap-4 group cursor-pointer"
              >
                <div className="p-3 rounded-xl bg-indigo-50 text-indigo-600 group-hover:bg-indigo-100 transition-colors">
                  <Calendar className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                  <span className="block font-bold text-slate-900 text-xs">Agendar Webinar</span>
                  <p className="text-[10px] text-slate-500 leading-tight mt-0.5">Workshops globais.</p>
                </div>
              </button>
            </div>
          </section>

          <div className="rounded-2xl border border-slate-200 bg-slate-900 text-slate-100 p-5 shadow-sm space-y-4">
            <h4 className="font-bold text-sm uppercase tracking-wider flex items-center gap-2 text-teal-400">
              <TrendingUp className="h-4 w-4" />
              <span>Métricas AVA</span>
            </h4>
            <div className="grid grid-cols-1 gap-3">
              <div className="bg-slate-800/60 p-3 rounded-xl border border-slate-850">
                <span className="text-[9px] text-slate-400 uppercase font-black block mb-1">Engajamento de Grade</span>
                <div className="flex items-end gap-2">
                  <strong className="text-xl font-bold font-mono text-emerald-400 leading-none">82%</strong>
                  <span className="text-[9px] text-slate-500">Média de Fixação</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      )}

      {/* NEW: Dedicated Curriculum Tab Content */}
      {activeDashboardTab === 'curriculum' && (
        <div className="animate-in slide-in-from-bottom-2 duration-300 space-y-6">
          <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 bg-white border border-slate-200 rounded-2xl p-6 shadow-xs text-left">
            <div>
              <span className="text-[10px] font-black text-teal-600 uppercase tracking-widest bg-teal-50 px-2 py-0.5 rounded-full mb-2 inline-block">Módulo de Edição Total</span>
              <h3 className="text-2xl font-black text-slate-900 tracking-tight leading-none">Grade Curricular: {activeCourse.title}</h3>
              <p className="text-sm text-slate-500 mt-2 font-medium">Gerencie toda a jornada de aprendizado teórico. Arraste para reordenar, edite conteúdos ou remova aulas obsoletas.</p>
            </div>
            
            <div className="flex items-center gap-3 shrink-0">
              <button
                onClick={() => setActiveDashboardTab('general')}
                className="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 px-5 py-3 rounded-xl font-bold text-sm flex items-center gap-2 transition-all cursor-pointer"
              >
                <ArrowLeft className="h-4 w-4" />
                <span>Voltar</span>
              </button>
              
              <button
                onClick={() => setIsCreatingLesson(true)}
                className="bg-[#540D6E] hover:bg-[#430a58] text-white px-6 py-3 rounded-xl font-bold text-sm shadow-lg shadow-purple-900/10 flex items-center gap-2 transition-all cursor-pointer"
              >
                <Plus className="h-5 w-5" />
                <span>Criar Nova Aula</span>
              </button>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4">
            {activeCourse.lessons.length === 0 ? (
              <div className="bg-slate-50 border-2 border-dashed border-slate-200 rounded-3xl p-16 text-center">
                <div className="bg-white p-4 rounded-full w-fit mx-auto mb-4 shadow-xs">
                  <BookOpen className="h-10 w-10 text-slate-300" />
                </div>
                <h4 className="text-lg font-bold text-slate-800">Seu currículo está vazio</h4>
                <p className="text-sm text-slate-500 max-w-sm mx-auto mt-2">Dê o primeiro passo e adicione uma aula de fixação para que seus alunos possam começar a pontuar.</p>
              </div>
            ) : (
              activeCourse.lessons.map((lesson, index) => {
                const isExpanded = expandedLessonId === lesson.id;
                const docs = lesson.documents || [];
                
                return (
                  <div 
                    key={lesson.id} 
                    className={`bg-white border rounded-2xl transition-all duration-200 shadow-3xs overflow-hidden ${
                      isExpanded ? 'border-teal-500 ring-1 ring-teal-400/30' : 'border-slate-205 hover:border-teal-300'
                    }`}
                    style={{ animationDelay: `${index * 50}ms` }}
                  >
                    {/* Header Row */}
                    <div className="p-5 flex items-center justify-between gap-4 text-left">
                      <div className="flex items-center gap-4 flex-1 min-w-0">
                        {/* Number Indicator & Reordering Buttons */}
                        <div className="flex flex-col items-center gap-1">
                          <button
                            disabled={index === 0}
                            onClick={() => handleMoveLesson(index, 'up')}
                            className="p-1 text-slate-400 hover:text-[#540D6E] disabled:opacity-30 disabled:hover:text-slate-400 cursor-pointer"
                            title="Mover para cima"
                          >
                            <ArrowUp className="h-3.5 w-3.5" />
                          </button>
                          <div className="h-7 w-7 bg-slate-50 rounded-full flex items-center justify-center text-slate-500 font-mono font-black text-xs border border-slate-100">
                            {index + 1}
                          </div>
                          <button
                            disabled={index === activeCourse.lessons.length - 1}
                            onClick={() => handleMoveLesson(index, 'down')}
                            className="p-1 text-slate-400 hover:text-[#540D6E] disabled:opacity-30 disabled:hover:text-slate-400 cursor-pointer"
                            title="Mover para baixo"
                          >
                            <ArrowDown className="h-3.5 w-3.5" />
                          </button>
                        </div>

                        <div className="min-w-0 flex-1">
                          <div className="flex flex-wrap items-center gap-2 mb-1">
                            <h4 className="font-extrabold text-slate-900 text-sm md:text-base leading-tight">{lesson.title}</h4>
                            <div className="flex items-center gap-1.5">
                              <span className="px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 text-[8px] font-mono font-bold tracking-tight">ID: {lesson.id.slice(0, 8)}</span>
                              {lesson.videoUrl && (
                                <span className="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 text-[8px] font-bold uppercase tracking-wider flex items-center gap-1 border border-amber-100/40">
                                  <Video className="h-2 w-2" /> Vídeo
                                </span>
                              )}
                              {lesson.isOptional ? (
                                <span className="px-1.5 py-0.5 rounded bg-slate-150 text-slate-600 text-[8px] font-bold uppercase tracking-wider">Opcional</span>
                              ) : (
                                <span className="px-1.5 py-0.5 rounded bg-teal-50 text-teal-700 text-[8px] font-bold uppercase tracking-wider border border-teal-100/30">Obrigatória</span>
                              )}
                            </div>
                          </div>
                          <div className="flex flex-wrap items-center gap-4 text-xs font-semibold text-slate-400">
                            <div className="flex items-center gap-1">
                              <Clock className="h-3 w-3 text-teal-500" />
                              <span>{lesson.duration} de carga</span>
                            </div>
                            <div className="flex items-center gap-1">
                              <FileText className="h-3 w-3 text-teal-500" />
                              <span>{docs.length} documento{docs.length !== 1 ? 's' : ''} relacionado{docs.length !== 1 ? 's' : ''}</span>
                            </div>
                          </div>
                        </div>
                      </div>

                      {/* Header Actions */}
                      <div className="flex items-center gap-1.5 shrink-0">
                        {/* Expand Details Button */}
                        <button
                          onClick={() => setExpandedLessonId(isExpanded ? null : lesson.id)}
                          className={`p-2 rounded-xl transition-all font-bold text-xs flex items-center gap-1 cursor-pointer ${
                            isExpanded ? 'bg-teal-50 text-teal-700 border border-teal-100' : 'bg-slate-50 text-slate-700 border border-slate-100 hover:bg-slate-100'
                          }`}
                          title={isExpanded ? "Ocultar Documentos" : "Gerenciar Documentos & Recursos"}
                        >
                          <Archive className="h-4 w-4" />
                          <span className="hidden md:inline">{isExpanded ? "Fechar Detalhes" : "Gerenciar Aula"}</span>
                          {isExpanded ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
                        </button>

                        <button
                          onClick={() => {
                            setEditingLessonId(lesson.id);
                            setEditLessonTitle(lesson.title);
                            setEditLessonDuration(lesson.duration);
                            setEditLessonContent(lesson.content || '');
                            setEditLessonVideoUrl(lesson.videoUrl || '');
                            setIsEditingLesson(true);
                          }}
                          className="p-2 bg-slate-50 hover:bg-slate-900 hover:text-white rounded-xl text-slate-600 border border-slate-150 transition-all cursor-pointer"
                          title="Editar Conteúdo"
                        >
                          <Edit3 className="h-4 w-4" />
                        </button>
                        <button
                          onClick={() => handleDeleteLesson(lesson.id, lesson.title)}
                          className="p-2 bg-slate-50 hover:bg-rose-600 hover:text-white rounded-xl text-slate-600 border border-slate-150 transition-all cursor-pointer"
                          title="Remover Aula"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </div>

                    {/* Expandable Panel */}
                    {isExpanded && (
                      <div className="bg-slate-50/50 p-5 border-t border-slate-150 space-y-6 text-left animate-in slide-in-from-top-1 duration-200">
                        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                          
                          {/* Col 1: Document & Attachment management */}
                          <div className="lg:col-span-7 space-y-4">
                            <div className="flex items-center justify-between">
                              <span className="text-xs font-black text-slate-800 uppercase tracking-widest flex items-center gap-1.5">
                                <FileText className="h-4 w-4 text-teal-600" />
                                Documentos Vinculados ({docs.length})
                              </span>
                              <span className="text-[10px] text-slate-400 font-medium">Os alunos podem abrir esses arquivos na seção de aula</span>
                            </div>

                            {/* Documents List */}
                            {docs.length === 0 ? (
                              <div className="bg-white border rounded-xl p-6 text-center shadow-3xs">
                                <FileText className="h-8 w-8 text-slate-300 mx-auto mb-2" />
                                <p className="text-xs font-bold text-slate-600">Nenhum documento relacionado</p>
                                <p className="text-[10px] text-slate-400 max-w-xs mx-auto mt-0.5">Use o painel lateral para associar apostilas, links, slides ou documentos do Google Drive a esta aula.</p>
                              </div>
                            ) : (
                              <div className="bg-white border border-slate-150 rounded-xl divide-y divide-slate-100 shadow-3xs overflow-hidden">
                                {docs.map((doc) => {
                                  let typeColor = 'bg-slate-100 text-slate-700';
                                  if (doc.type === 'pdf') typeColor = 'bg-rose-50 text-rose-700 border border-rose-100/40';
                                  if (doc.type === 'doc') typeColor = 'bg-blue-50 text-blue-700 border border-blue-100/40';
                                  if (doc.type === 'url') typeColor = 'bg-amber-50 text-amber-700 border border-amber-100/40';
                                  if (doc.type === 'drive') typeColor = 'bg-emerald-50 text-emerald-700 border border-emerald-100/40';
                                  
                                  return (
                                    <div key={doc.id} className="p-3 flex items-center justify-between text-xs hover:bg-slate-50 transition-colors">
                                      <div className="flex items-center gap-3 min-w-0">
                                        <div className={`px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider ${typeColor}`}>
                                          {doc.type}
                                        </div>
                                        <div className="min-w-0">
                                          <p className="font-bold text-slate-800 truncate">{doc.title}</p>
                                          <p className="font-mono text-[9px] text-slate-400 truncate max-w-[280px]">
                                            {doc.size ? `${doc.size} • ` : ''}{doc.url}
                                          </p>
                                        </div>
                                      </div>
                                      <div className="flex items-center gap-1 ml-4 shadow-3xs rounded-lg overflow-hidden bg-white border border-slate-200">
                                        <a 
                                          href={doc.url} 
                                          target="_blank" 
                                          referrerPolicy="no-referrer"
                                          rel="noopener noreferrer" 
                                          className="p-1.5 text-slate-600 hover:bg-slate-50 hover:text-teal-600 transition-colors"
                                          title="Testar Link Externo"
                                        >
                                          <ExternalLink className="h-3.5 w-3.5" />
                                        </a>
                                        <button 
                                          onClick={() => handleDeleteDocument(lesson.id, doc.id)}
                                          className="p-1.5 text-slate-400 hover:bg-slate-50 hover:text-rose-600 transition-colors cursor-pointer border-l border-slate-150"
                                          title="Desvincular Documento"
                                        >
                                          <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                      </div>
                                    </div>
                                  );
                                })}
                              </div>
                            )}

                            {/* Live student preview shortcut */}
                            <div className="bg-gradient-to-r from-[#540D6E]/5 to-teal-500/5 rounded-xl p-3 border border-[#540D6E]/10 flex items-center justify-between text-xs">
                              <div className="flex items-center gap-2">
                                <Sparkles className="h-4 w-4 text-[#540D6E]" />
                                <div>
                                  <p className="font-bold text-[#540D6E]">Pré-visualização do Aluno</p>
                                  <p className="text-[10px] text-slate-500">Veja exatamente como o aluno visualizará o material de estudos.</p>
                                </div>
                              </div>
                              <button 
                                onClick={() => {
                                  alert(`Pré-visualização da Aula:\n\nTítulo: ${lesson.title}\nDuração: ${lesson.duration}\nVídeo: ${lesson.videoUrl || 'Sem vídeo cadastrado'}\nDocumentos: ${docs.length} arquivo(s) anexado(s).\n\nConteúdo Markdown:\n${lesson.content ? lesson.content.substring(0, 180) + '...' : 'Vazio'}`);
                                }}
                                className="bg-white text-[#540D6E] font-extrabold hover:bg-slate-100 px-3 py-1.5 rounded-lg border border-purple-200/50 text-[10px] cursor-pointer"
                              >
                                Olhar Prévia
                              </button>
                            </div>
                          </div>

                          {/* Col 2: Formulation input to attach new document */}
                          <div className="lg:col-span-5 bg-white border border-slate-200/80 rounded-2xl p-4 shadow-3xs space-y-3">
                            <span className="text-[10px] font-black text-[#540D6E] uppercase tracking-wider block mb-1">Anexar Novo Arquivo / Link</span>
                            
                            <div className="space-y-2 text-xs">
                              <div>
                                <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Título do Recurso</label>
                                <input
                                  type="text"
                                  placeholder="Ex: Slides Primeiros Passos.pdf, Exercício 1"
                                  value={newDocTitle}
                                  onChange={(e) => setNewDocTitle(e.target.value)}
                                  className="w-full bg-slate-50 hover:bg-white focus:bg-white text-slate-800 font-medium px-3 py-2 rounded-xl border border-slate-200 focus:border-teal-500 focus:outline-none transition-all placeholder:text-slate-400"
                                />
                              </div>

                              <div className="grid grid-cols-2 gap-2">
                                <div>
                                  <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Tipo de Link</label>
                                  <select
                                    value={newDocType}
                                    onChange={(e) => setNewDocType(e.target.value as any)}
                                    className="w-full bg-slate-50 hover:bg-white focus:bg-white text-slate-800 font-semibold px-3 py-2 rounded-xl border border-slate-200 focus:border-teal-500 focus:outline-none transition-all cursor-pointer"
                                  >
                                    <option value="pdf">Apostila (.pdf)</option>
                                    <option value="doc">Anotações (.doc)</option>
                                    <option value="url">Link Externo</option>
                                    <option value="drive">Google Drive</option>
                                    <option value="outro">Outro Tipo</option>
                                  </select>
                                </div>
                                <div>
                                  <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Tamanho aproximado</label>
                                  <input
                                    type="text"
                                    placeholder="Ex: 2.1 MB / Opcional"
                                    disabled={newDocType !== 'pdf' && newDocType !== 'doc'}
                                    value={newDocSize}
                                    onChange={(e) => setNewDocSize(e.target.value)}
                                    className="w-full bg-slate-50 hover:bg-white focus:bg-white text-slate-800 font-mono text-center px-3 py-2 rounded-xl border border-slate-200 focus:border-teal-500 focus:outline-none transition-all disabled:opacity-40"
                                  />
                                </div>
                              </div>

                              <div>
                                <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Enderço URL do Conteúdo</label>
                                <input
                                  type="text"
                                  placeholder="https://exemplo.com/material-aula-1"
                                  value={newDocUrl}
                                  onChange={(e) => setNewDocUrl(e.target.value)}
                                  className="w-full bg-slate-50 hover:bg-white focus:bg-white text-slate-800 font-medium px-3 py-2 rounded-xl border border-slate-200 focus:border-teal-500 focus:outline-none transition-all placeholder:text-slate-400"
                                />
                              </div>

                              <div className="pt-2">
                                <button
                                  type="button"
                                  onClick={() => handleAddDocument(lesson.id)}
                                  className="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 rounded-xl transition-all shadow-md shadow-teal-950/5 flex items-center justify-center gap-1.5 cursor-pointer text-xs"
                                >
                                  <Plus className="h-4 w-4" />
                                  <span>Vincular Documento</span>
                                </button>
                              </div>
                            </div>

                            {/* Additional functional suggestions inside layout */}
                            <div className="pt-2 border-t border-slate-100 flex flex-col gap-1.5">
                              <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wide block">Configurações Avançadas de Aula</span>
                              <div className="flex items-center justify-between">
                                <span className="text-[10px] text-slate-600 font-semibold">Tornar Aula Opcional</span>
                                <button
                                  onClick={() => {
                                    updateLesson(selectedCourseId, lesson.id, { isOptional: !lesson.isOptional });
                                    showToast(`${lesson.title} agora é ${!lesson.isOptional ? 'opcional' : 'obrigatória'}!`);
                                  }}
                                  className={`px-3 py-1 rounded-full text-[9px] font-black uppercase cursor-pointer transition-all ${
                                    lesson.isOptional 
                                      ? 'bg-slate-200 text-slate-700' 
                                      : 'bg-[#540D6E]/10 text-[#540D6E] border border-purple-300/30'
                                  }`}
                                >
                                  {lesson.isOptional ? "Desativar" : "Ativar"}
                                </button>
                              </div>
                            </div>

                          </div>
                        </div>
                      </div>
                    )}
                  </div>
                );
              })
            )}
          </div>

          {/* Quick Stats for Curriculum */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-4">
             <div className="bg-slate-900 rounded-2xl p-6 text-left border border-slate-800">
                <span className="text-[10px] font-black text-teal-400 uppercase tracking-widest block mb-1">Tempo Total Estimado</span>
                <div className="text-2xl font-black text-white">~12.4 horas</div>
             </div>
             <div className="bg-white rounded-2xl p-6 text-left border border-slate-200">
                <span className="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Aulas Dinâmicas</span>
                <div className="text-2xl font-black text-slate-900">{activeCourse.lessons.length} Módulos</div>
             </div>
             <div className="bg-white rounded-2xl p-6 text-left border border-slate-200">
                <span className="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Status de Publicação</span>
                <div className="text-2xl font-black text-emerald-600 flex items-center gap-2">
                  <CheckCircle className="h-6 w-6" />
                  <span>Publicado</span>
                </div>
             </div>
          </div>
        </div>
      )}
      {activeDashboardTab === 'messages' && (
        <div className="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-xs space-y-6 text-left animate-in fade-in duration-300">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-5">
            <div className="space-y-1">
              <span className="text-[10px] font-extrabold text-[#540D6E] uppercase tracking-wider font-mono">Central Pedagógica de Comunicação</span>
              <h3 className="text-xl md:text-2xl font-black text-slate-900 uppercase tracking-tight">Atendimento a Estudantes</h3>
              <p className="text-xs text-slate-500 font-medium">Responda a dúvidas, valide exercícios práticos e controle o engajamento individual de cada estudante.</p>
            </div>
            
            <div className="flex gap-2 shrink-0">
              <div className="bg-slate-50 border border-slate-200 px-3 py-2 rounded-xl flex items-center gap-2 text-xs text-slate-700">
                <Bell className="h-4 w-4 text-amber-500 shrink-0" />
                <div>
                  <span className="block text-[8px] text-slate-400 font-bold uppercase leading-none">Aguardando Resposta</span>
                  <span className="font-bold text-[10.5px] text-amber-700">{unrepliedStudents.length} Aluno(s)</span>
                </div>
              </div>
              <div className="bg-slate-50 border border-slate-200 px-3 py-2 rounded-xl flex items-center gap-2 text-xs text-slate-700">
                <Users className="h-4 w-4 text-teal-500 shrink-0" />
                <div>
                  <span className="block text-[8px] text-slate-400 font-bold uppercase leading-none">Total de Alunos</span>
                  <span className="font-bold text-[10.5px]">{studentsList.length} Ativos</span>
                </div>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {/* Left Column: Active Students List (4 cols) */}
            <div className="lg:col-span-4 rounded-2xl border border-slate-200 p-4 bg-slate-50/50 space-y-3">
              <span className="text-[10px] font-black text-slate-400 uppercase tracking-widest block font-mono pl-1">Selecione o Estudante</span>
              
              <div className="space-y-1.5 max-h-[460px] overflow-y-auto pr-1">
                {studentsList.map((student) => {
                  const isSelected = selectedStudentName === student.name;
                  const isUnreplied = unrepliedStudents.includes(student.name);
                  const studentDMs = directMessages.filter(m => m.studentName === student.name);
                  const hasHistory = studentDMs.length > 0;
                  const latestMsgText = hasHistory ? studentDMs[studentDMs.length - 1].text : "Sem mensagens";
                  
                  return (
                    <button
                      key={student.name}
                      onClick={() => setSelectedStudentName(student.name)}
                      className={`w-full p-3.5 rounded-xl border text-left transition-all duration-150 flex items-start gap-3 cursor-pointer ${
                        isSelected 
                          ? 'bg-[#540D6E] text-white border-[#540D6E] shadow-sm' 
                          : 'bg-white border-slate-200 hover:bg-slate-50 hover:border-slate-300 text-slate-700'
                      }`}
                    >
                      <div className="relative shrink-0 mt-0.5">
                        <div className={`h-8 w-8 rounded-full flex items-center justify-center font-bold text-xs ${
                          isSelected ? 'bg-white/20 text-white' : 'bg-slate-100 text-[#540D6E]'
                        }`}>
                          {student.name.substring(0, 2).toUpperCase()}
                        </div>
                        {isUnreplied && (
                          <span className="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-rose-600 ring-2 ring-white animate-pulse" />
                        )}
                      </div>

                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between gap-1">
                          <span className="font-bold text-xs truncate">{student.name}</span>
                          {isUnreplied && (
                            <span className={`text-[8px] font-extrabold px-1 rounded uppercase shrink-0 ${
                              isSelected ? 'bg-white/25 text-white' : 'bg-red-50 text-red-650 text-red-600'
                            }`}>
                              Pendente
                            </span>
                          )}
                        </div>
                        <p className={`text-[9.5px] truncate mt-0.5 ${isSelected ? 'text-white/70' : 'text-slate-400 font-mono'}`}>
                          {student.email || `${student.name.toLowerCase().replace(' ', '.')}@escola.dev.br`}
                        </p>
                        <p className={`text-[10px] truncate mt-1 italic ${isSelected ? 'text-white/80' : 'text-slate-500'}`}>
                          {latestMsgText}
                        </p>
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Right Column: Dynamic Roomy Message Panel (8 cols) */}
            <div className="lg:col-span-8 space-y-4">
              {(() => {
                const studentDMs = directMessages.filter(m => m.studentName === selectedStudentName);
                const isJOAO = selectedStudentName === 'João Silva';
                const attendanceLookup: Record<string, number> = {
                  'João Silva': activeCourse ? calculateAttendancePercent(activeCourse.id) : 80,
                  'Gabriel Rodrigues': 85,
                  'Beatriz Costa': 60,
                  'Sofia Rocha': 45,
                  'Ana Souza': 92,
                  'Lucas Santana': 78,
                  'Carolina Mendes': 88
                };
                const attendance = attendanceLookup[selectedStudentName] || 75;
                const isQualified = attendance >= 70;
                const hasCert = certificates.some(cer => cer.studentName === selectedStudentName);
                
                return (
                  <div className="rounded-2xl border border-slate-205 bg-slate-50/50 p-5 flex flex-col h-[520px] transition-all justify-between">
                    
                    {/* Active Student Header Info Banner */}
                    <div className="flex items-center justify-between border-b border-slate-200 pb-3 mb-4">
                      <div>
                        <strong className="text-sm font-black text-slate-900 block">{selectedStudentName}</strong>
                        <span className="text-[10px] text-slate-400 font-mono">Aluno Regular • {selectedStudentName.toLowerCase().replace(' ', '.')}@escola.dev.br</span>
                      </div>

                      <div className="flex gap-2.5">
                        <div className="bg-white border border-slate-200 rounded-lg px-2.5 py-1 text-right">
                          <span className="block text-[8px] text-slate-400 font-bold uppercase leading-none">Presença</span>
                          <span className={`font-mono text-xs font-black ${isQualified ? 'text-emerald-600' : 'text-amber-600'}`}>{attendance}%</span>
                        </div>
                        <div className="bg-white border border-slate-200 rounded-lg px-2.5 py-1 text-right">
                          <span className="block text-[8px] text-slate-400 font-bold uppercase leading-none">Curso Certificado</span>
                          <span className="text-xs font-black text-teal-600">{hasCert ? '✓ Emitido' : '✕ Pendente'}</span>
                        </div>
                      </div>
                    </div>

                    {/* Chat Messages Log */}
                    <div className="flex-1 space-y-3 overflow-y-auto pr-1 mb-4 flex flex-col gap-1.5 scrollbar-thin">
                      {studentDMs.length === 0 ? (
                        <div className="flex-1 flex flex-col items-center justify-center text-slate-400 py-10 space-y-2">
                          <MessageSquare className="h-8 w-8 text-slate-300 animate-pulse" />
                          <p className="text-xs font-bold text-slate-500">Nenhuma conversa anterior registrada com {selectedStudentName}.</p>
                          <p className="text-[10pt] text-[10px] text-slate-400">Comece enviando uma mensagem instrutiva de feedback abaixo!</p>
                        </div>
                      ) : (
                        studentDMs.map((msg) => {
                          const isInstructor = msg.senderRole === 'instructor';
                          return (
                            <div key={msg.id} className={`flex flex-col ${isInstructor ? 'items-end' : 'items-start'} animate-in fade-in slide-in-from-bottom-1 duration-200`}>
                              <div className={`max-w-[80%] rounded-2xl px-4 py-2.5 text-xs leading-normal ${
                                isInstructor 
                                  ? 'bg-teal-600 text-white rounded-tr-none shadow-3xs' 
                                  : 'bg-white border border-slate-200 text-slate-800 rounded-tl-none shadow-3xs'
                              }`}>
                                <div className="flex items-center gap-1.5 mb-1 opacity-75">
                                  <span className="font-extrabold text-[9px] uppercase tracking-wide">{msg.senderName}</span>
                                  <span className="text-[8px] font-mono">• {msg.senderRole === 'instructor' ? 'Gestor' : 'Estudante'}</span>
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

                    {/* Typing Interface Reply Form */}
                    <form onSubmit={(e) => {
                      e.preventDefault();
                      const input = (e.currentTarget.elements.namedItem('replyText') as HTMLInputElement);
                      const text = input.value.trim();
                      if (text) {
                        sendDirectMessage(selectedStudentName, text);
                        input.value = '';
                        showToast(`Boletim e resposta técnica gravada para ${selectedStudentName}!`);
                      }
                    }} className="flex gap-2 border-t border-slate-200/60 pt-3">
                      <input
                        name="replyText"
                        type="text"
                        required
                        placeholder={`Digite as orientações para o aluno ${selectedStudentName}...`}
                        className="flex-1 rounded-xl border border-slate-205 bg-white px-4 py-3 text-xs text-slate-700 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all outline-none"
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
                );
              })()}
            </div>
          </div>
        </div>
      )}

      {activeDashboardTab === 'students' && (
        <div className="space-y-6 text-left animate-in fade-in duration-300">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-5">
            <div className="space-y-1">
              <h1 className="text-2xl font-black text-slate-900 tracking-tight">Gestão de Alunos</h1>
              <p className="text-xs text-slate-500 font-medium tracking-wide">Controle de admissão, matrículas e acompanhamento de turmas.</p>
            </div>
            
            <div className="bg-amber-50 border border-amber-100 rounded-xl px-4 py-2 flex items-center gap-3">
              <Info className="h-4 w-4 text-amber-600" />
              <p className="text-[10px] text-amber-800 font-bold leading-tight">
                Matrículas pendentes aguardam sua aprovação técnica antes da liberação de acesso.
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {/* 1. Pending Admission Requests */}
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                  <Clock className="h-4 w-4 text-[#540D6E]" />
                  <span>Solicitações de Matrícula</span>
                </h3>
                <span className="bg-[#540D6E] text-white text-[10px] font-black px-2 py-0.5 rounded-full">
                  {admissionRequests.filter(r => r.status === 'pending' && courses.find(c => c.id === r.courseId)?.instructorName === activeUser.name).length} Pendentes
                </span>
              </div>

              <div className="space-y-3">
                {admissionRequests.filter(r => r.status === 'pending' && courses.find(c => c.id === r.courseId)?.instructorName === activeUser.name).length === 0 ? (
                  <div className="bg-slate-50 border border-slate-200 border-dashed rounded-2xl p-8 flex flex-col items-center justify-center text-center space-y-2">
                    <CheckCircle className="h-8 w-8 text-slate-300" />
                    <p className="text-xs font-bold text-slate-500">Nenhuma solicitação pendente.</p>
                  </div>
                ) : (
                  admissionRequests
                    .filter(req => req.status === 'pending')
                    .map((req) => {
                      const course = courses.find(c => c.id === req.courseId);
                      const isMyCourse = course?.instructorName === activeUser.name;
                      
                      if (!isMyCourse) return null;

                      return (
                        <div key={req.id} className="bg-white border border-slate-200 rounded-2xl p-4 shadow-3xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-slate-300 transition-all">
                          <div className="flex items-center gap-4">
                            <div className="h-10 w-10 bg-indigo-50 rounded-full flex items-center justify-center text-indigo-700 font-black">
                              {req.studentName.charAt(0)}
                            </div>
                            <div>
                              <p className="text-sm font-bold text-slate-900">{req.studentName}</p>
                              <p className="text-[11px] text-slate-500">
                                Deseja cursar: <span className="font-bold text-[#540D6E]">{course?.title}</span>
                              </p>
                              <p className="text-[9px] text-slate-400 mt-0.5 uppercase font-mono">{req.submittedAt}</p>
                            </div>
                          </div>
                          <div className="flex gap-2">
                            <button 
                              onClick={() => {
                                updateAdmissionStatus(req.id, 'rejected');
                                showToast(`Matrícula de ${req.studentName} reprovada.`);
                              }}
                              className="px-3 py-1.5 rounded-lg border border-slate-200 text-[10px] font-black text-slate-500 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-100 transition-all cursor-pointer"
                            >
                              Reprovar
                            </button>
                            <button 
                              onClick={() => {
                                updateAdmissionStatus(req.id, 'approved');
                                showToast(`Matrícula de ${req.studentName} aprovada com sucesso!`);
                              }}
                              className="px-3 py-1.5 rounded-lg bg-teal-600 text-[10px] font-black text-white hover:bg-teal-700 shadow-3xs transition-all cursor-pointer"
                            >
                              Aprovar Acesso
                            </button>
                          </div>
                        </div>
                      );
                    })
                )}
              </div>
            </div>

            {/* 2. Global Student Directory / Assignment */}
            <div className="space-y-4">
              <h3 className="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                <Users className="h-4 w-4 text-[#540D6E]" />
                <span>Diretório Global de Alunos (Inseridos pelo Admin)</span>
              </h3>
              
              <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-3xs space-y-4">
                <p className="text-[11px] text-slate-500 leading-relaxed italic">
                  Abaixo estão os alunos registrados no sistema pelo Administrador Super. Você pode matriculá-los diretamente em qualquer uma de suas disciplinas.
                </p>

                <div className="space-y-3 max-h-[400px] overflow-y-auto pr-2 scrollbar-thin">
                  {studentsList.map((student, idx) => (
                    <div key={idx} className="p-3 bg-slate-50 border border-slate-100 rounded-xl flex items-center justify-between gap-3">
                      <div className="flex items-center gap-3">
                        <div className="h-8 w-8 bg-slate-200 rounded-lg flex items-center justify-center text-slate-600 text-xs font-bold">
                          {student.name.charAt(0)}
                        </div>
                        <div>
                          <p className="text-xs font-bold text-slate-800">{student.name}</p>
                          <p className="text-[10px] text-slate-500">{student.email}</p>
                        </div>
                      </div>
                      
                      <div className="flex items-center gap-2">
                        <select 
                          className="bg-white border border-slate-200 rounded-lg px-2 py-1 text-[10px] font-bold text-slate-700 focus:ring-1 focus:ring-teal-500 outline-none cursor-pointer"
                          onChange={(e) => {
                            if (e.target.value) {
                              const courseTitle = courses.find(c => c.id === e.target.value)?.title;
                              showToast(`${student.name} matriculado em ${courseTitle}!`);
                              e.target.value = "";
                            }
                          }}
                        >
                          <option value="">Matricular em...</option>
                          {courses.filter(c => c.instructorName === activeUser.name).map(c => (
                            <option key={c.id} value={c.id}>{c.title}</option>
                          ))}
                        </select>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* 1. Modal: Cadastrar Novo Curso */}
      {isCreatingCourse && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <form
            onSubmit={handleCreateCourse}
            className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl relative text-left animate-in fade-in duration-200"
          >
            <h3 className="text-lg font-bold text-slate-900 mb-4">Cadastrar Novo Curso no AVA</h3>
            
            <div className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Título do Curso</label>
                <input
                  type="text"
                  required
                  value={newCourseTitle}
                  onChange={(e) => setNewCourseTitle(e.target.value)}
                  placeholder="Ex: Desenvolvimento Web com React"
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Breve Descrição</label>
                <textarea
                  required
                  rows={3}
                  value={newCourseDesc}
                  onChange={(e) => setNewCourseDesc(e.target.value)}
                  placeholder="Escreva um descritivo completo em poucas palavras..."
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Categoria</label>
                  <div className="space-y-1.5">
                    <select
                      value={newCourseCategory}
                      onChange={(e) => {
                        setNewCourseCategory(e.target.value);
                        if (e.target.value !== "Escrever Outra...") {
                          setCustomCategory("");
                        }
                      }}
                      className="w-full rounded-lg border border-slate-200 p-2.5 text-xs font-semibold text-slate-800"
                    >
                      {/* Extract categories dynamically */}
                      {Array.from(new Set([...courses.map(c => c.category), 'Programação', 'Design Digital', 'Organização & Gestão', 'Marketing'])).map(cat => (
                        <option key={cat} value={cat}>{cat}</option>
                      ))}
                      <option value="Escrever Outra...">+ Criar outra categoria...</option>
                    </select>

                    {newCourseCategory === "Escrever Outra..." && (
                      <input
                        type="text"
                        placeholder="Nome da categoria..."
                        required
                        value={customCategory}
                        onChange={(e) => setCustomCategory(e.target.value)}
                        className="w-full rounded-lg border border-teal-200 bg-teal-50/20 p-2 text-xs font-semibold text-slate-800"
                      />
                    )}
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Instrutor</label>
                  <input
                    type="text"
                    required
                    value={newCourseInstructor}
                    onChange={(e) => setNewCourseInstructor(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                  />
                </div>
              </div>
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setIsCreatingCourse(false)}
                className="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-650 hover:bg-slate-200"
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="rounded-lg bg-teal-600 hover:bg-teal-500 px-4 py-2 text-xs font-bold text-white shadow-xs"
              >
                Cadastrar Curso
              </button>
            </div>
          </form>
        </div>
      )}

      {/* 2. Modal: Adicionar Aula Teorica */}
      {isCreatingLesson && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <form
            onSubmit={handleCreateLesson}
            className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl relative text-left animate-in fade-in duration-200"
          >
            <h3 className="text-lg font-bold text-slate-900 mb-4">Nova Aula de Fixação</h3>
            
            <div className="space-y-4">
              <div className="grid grid-cols-3 gap-4">
                <div className="col-span-2">
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Título da Aula</label>
                  <input
                    type="text"
                    required
                    value={lessonTitle}
                    onChange={(e) => setLessonTitle(e.target.value)}
                    placeholder="Ex: Usando State e Effect Hooks"
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-xs font-semibold text-slate-800"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Duração</label>
                  <input
                    type="text"
                    required
                    value={lessonDuration}
                    onChange={(e) => setLessonDuration(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-xs font-semibold text-slate-800"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">URL do Vídeo (Opcional)</label>
                <input
                  type="url"
                  value={lessonVideoUrl}
                  onChange={(e) => setLessonVideoUrl(e.target.value)}
                  placeholder="Ex: https://www.youtube.com/watch?v=..."
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-xs font-semibold text-slate-800"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Conteúdo Teórico / Exercícios (Markdown)</label>
                <textarea
                  required
                  rows={8}
                  value={lessonContent}
                  onChange={(e) => setLessonContent(e.target.value)}
                  placeholder="#### Introdução à aula... use cabeçalhos e listas."
                  className="w-full rounded-lg border border-slate-200 p-2.5 font-mono text-xs text-slate-700"
                />
              </div>
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setIsCreatingLesson(false)}
                className="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-650 hover:bg-slate-200"
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="rounded-lg bg-teal-600 hover:bg-teal-500 px-4 py-2 text-xs font-bold text-white shadow-xs"
              >
                Adicionar Aula
              </button>
            </div>
          </form>
        </div>
      )}

      {/* 3. Modal: Agendar Transmissão ao vivo */}
      {isCreatingLive && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <form
            onSubmit={handleCreateLive}
            className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl relative text-left animate-in fade-in duration-200"
          >
            <h3 className="text-lg font-bold text-slate-900 mb-4">Agendar Encontro Ao Vivo</h3>
            
            <div className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Tema da Transmissão</label>
                <input
                  type="text"
                  required
                  value={liveTitle}
                  onChange={(e) => setLiveTitle(e.target.value)}
                  placeholder="Ex: Tira-dúvidas de Programação"
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-xs font-semibold text-slate-800"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Data/Hora Agendado</label>
                  <input
                    type="text"
                    required
                    value={liveDate}
                    onChange={(e) => setLiveDate(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-xs font-semibold text-slate-800"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Duração (Minutos)</label>
                  <input
                    type="number"
                    required
                    value={liveDuration}
                    onChange={(e) => setLiveDuration(Number(e.target.value))}
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-xs font-semibold text-slate-800"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Link da Videoconferência (Google Meet/Zoom)</label>
                <input
                  type="url"
                  required
                  value={liveMeetingLink}
                  onChange={(e) => setLiveMeetingLink(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-xs font-mono text-slate-800"
                />
              </div>
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setIsCreatingLive(false)}
                className="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-650 hover:bg-slate-200"
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="rounded-lg bg-teal-600 hover:bg-teal-500 px-4 py-2 text-xs font-bold text-white shadow-xs"
              >
                Agendar Encontro
              </button>
            </div>
          </form>
        </div>
      )}

      {/* 4. Modal: Criar Avaliação / Gerar Teste */}
      {isCreatingQuiz && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs overflow-y-auto">
          <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl relative text-left animate-in fade-in duration-200 my-8 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
              <h3 className="text-md font-black text-slate-900 flex items-center gap-2">
                <CheckSquare className="h-5 w-5 text-amber-500" />
                <span>Elaborar Nova Avaliação</span>
              </h3>
              <span className="rounded-full bg-slate-100 text-[10px] font-bold text-slate-500 px-2 py-0.5 uppercase">
                {activeCourse.category}
              </span>
            </div>

            <form onSubmit={handlePublishQuiz} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Título do Teste / Questionário</label>
                <input
                  type="text"
                  required
                  placeholder="Ex: Teste Prático de Fixação ou Quiz de Revisão"
                  value={quizTitle}
                  onChange={(e) => setQuizTitle(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 p-2 text-xs font-semibold text-slate-800 focus:ring-1 focus:ring-amber-500 focus:outline-hidden"
                />
              </div>

              {/* Added questions draft visual list */}
              <div className="bg-slate-50 p-3 rounded-xl border border-slate-200">
                <span className="text-[10px] font-bold text-slate-500 uppercase block mb-2">Questões no Rascunho ({quizQuestions.length})</span>
                {quizQuestions.length === 0 ? (
                  <p className="text-[10px] text-slate-400 italic">Nenhuma questão adicionada. Preencha os campos abaixo e clique em "Salvar Questão" para acrescentar ao teste.</p>
                ) : (
                  <div className="space-y-2 max-h-[140px] overflow-y-auto pr-1">
                    {quizQuestions.map((q, idx) => (
                      <div key={q.id} className="p-2 border border-slate-150 bg-white rounded-lg text-[10px]">
                        <div className="flex items-start justify-between gap-1">
                          <strong className="text-slate-900 text-xs block truncate leading-tight">Q{idx + 1}: {q.questionText}</strong>
                          <button
                            type="button"
                            onClick={() => setQuizQuestions(prev => prev.filter(item => item.id !== q.id))}
                            className="text-red-500 hover:text-red-700 font-bold"
                          >
                            Excluir
                          </button>
                        </div>
                        <span className="text-[9px] text-emerald-600 font-semibold block mt-1">Resposta correta: Opção {q.correctOptionIndex + 1} ({q.options[q.correctOptionIndex]})</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Form to compose a question */}
              <div className="border border-amber-100/60 bg-amber-50/10 p-4 rounded-xl space-y-3">
                <span className="text-[10px] font-black text-amber-700 uppercase tracking-wide block border-b border-amber-100/50 pb-1">Preencher Nova Questão</span>
                
                <div>
                  <label className="block text-[9px] font-bold text-slate-500 uppercase mb-1">Enunciado / Pergunta</label>
                  <input
                    type="text"
                    placeholder="Escreva a pergunta claramente..."
                    value={tempQuestionText}
                    onChange={(e) => setTempQuestionText(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 p-2 text-[11px] text-slate-800"
                  />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[10px]">
                  <div>
                    <label className="block text-[8px] font-bold text-slate-400 uppercase mb-0.5">Opção 1</label>
                    <input
                      type="text"
                      placeholder="Primeira alternativa..."
                      value={tempOption0}
                      onChange={(e) => setTempOption0(e.target.value)}
                      className="w-full rounded-lg border border-slate-200 p-1.5 text-[11px]"
                    />
                  </div>
                  <div>
                    <label className="block text-[8px] font-bold text-slate-400 uppercase mb-0.5">Opção 2</label>
                    <input
                      type="text"
                      placeholder="Segunda alternativa..."
                      value={tempOption1}
                      onChange={(e) => setTempOption1(e.target.value)}
                      className="w-full rounded-lg border border-slate-200 p-1.5 text-[11px]"
                    />
                  </div>
                  <div>
                    <label className="block text-[8px] font-bold text-slate-400 uppercase mb-0.5">Opção 3</label>
                    <input
                      type="text"
                      placeholder="Terceira alternativa..."
                      value={tempOption2}
                      onChange={(e) => setTempOption2(e.target.value)}
                      className="w-full rounded-lg border border-slate-200 p-1.5 text-[11px]"
                    />
                  </div>
                  <div>
                    <label className="block text-[8px] font-bold text-slate-400 uppercase mb-0.5">Opção 4</label>
                    <input
                      type="text"
                      placeholder="Quarta alternativa..."
                      value={tempOption3}
                      onChange={(e) => setTempOption3(e.target.value)}
                      className="w-full rounded-lg border border-slate-200 p-1.5 text-[11px]"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                  <div>
                    <label className="block text-[9px] font-bold text-slate-500 uppercase mb-1">Qual alternativa está correta?</label>
                    <select
                      value={tempCorrectIndex}
                      onChange={(e) => setTempCorrectIndex(Number(e.target.value))}
                      className="w-full rounded-lg border border-slate-200 p-1.5 text-[11px] font-semibold text-slate-700 bg-white"
                    >
                      <option value={0}>Opção 1 (A)</option>
                      <option value={1}>Opção 2 (B)</option>
                      <option value={2}>Opção 3 (C)</option>
                      <option value={3}>Opção 4 (D)</option>
                    </select>
                  </div>

                  <div className="flex items-end justify-end">
                    <button
                      type="button"
                      onClick={handleAddQuestionToQuiz}
                      className="w-full bg-slate-800 hover:bg-slate-700 text-white font-bold p-2 rounded-lg text-[10px] transition-colors cursor-pointer"
                    >
                      + Salvar Questão no Rascunho
                    </button>
                  </div>
                </div>
              </div>

              <div className="mt-6 flex justify-end gap-2 pt-3 border-t border-slate-150">
                <button
                  type="button"
                  onClick={() => setIsCreatingQuiz(false)}
                  className="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  disabled={quizQuestions.length === 0}
                  className={`rounded-lg px-4 py-2 text-xs font-bold text-white shadow-xs ${
                    quizQuestions.length === 0 ? 'bg-amber-300 cursor-not-allowed' : 'bg-amber-600 hover:bg-amber-500'
                  }`}
                >
                  Publicar Teste Geral
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 4. Modal: Editar Dados do Curso */}
      {isEditingCourse && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <form
            onSubmit={handleEditCourse}
            className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl relative text-left animate-in fade-in duration-200"
          >
            <h3 className="text-lg font-bold text-slate-900 mb-4">Editar Propriedades do Curso</h3>
            
            <div className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Título</label>
                <input
                  type="text"
                  required
                  value={editTitle}
                  onChange={(e) => setEditTitle(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Categoria</label>
                <input
                  type="text"
                  required
                  value={editCategory}
                  onChange={(e) => setEditCategory(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Descrição</label>
                <textarea
                  required
                  rows={4}
                  value={editDesc}
                  onChange={(e) => setEditDesc(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                />
              </div>
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setIsEditingCourse(false)}
                className="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-650"
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="rounded-lg bg-indigo-600 hover:bg-indigo-500 px-4 py-2 text-xs font-bold text-white shadow-xs"
              >
                Salvar Alterações
              </button>
            </div>
          </form>
        </div>
      )}

      {/* 4.5. Modal: Editar Aula */}
      {isEditingLesson && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <form
            onSubmit={handleUpdateLesson}
            className="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl relative text-left animate-in fade-in duration-200"
          >
            <h3 className="text-lg font-bold text-slate-900 mb-4">Editar Aula de Fixação</h3>
            
            <div className="space-y-4">
              <div className="grid grid-cols-3 gap-4">
                <div className="col-span-2">
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1 tracking-wider">Título da Aula</label>
                  <input
                    type="text"
                    required
                    value={editLessonTitle}
                    onChange={(e) => setEditLessonTitle(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-sm font-semibold"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1 tracking-wider">Carga (Minutos)</label>
                  <input
                    type="text"
                    required
                    value={editLessonDuration}
                    onChange={(e) => setEditLessonDuration(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-sm font-mono"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1 tracking-wider">URL do Vídeo (Opcional)</label>
                <input
                  type="url"
                  value={editLessonVideoUrl}
                  onChange={(e) => setEditLessonVideoUrl(e.target.value)}
                  placeholder="Ex: https://www.youtube.com/watch?v=..."
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-sm font-semibold"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1 tracking-wider">Conteúdo (Markdown/Instruções)</label>
                <textarea
                  required
                  rows={10}
                  value={editLessonContent}
                  onChange={(e) => setEditLessonContent(e.target.value)}
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-sm font-mono text-slate-700 bg-slate-50"
                  placeholder="### Desafio Prático..."
                />
              </div>
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setIsEditingLesson(false)}
                className="rounded-lg bg-slate-50 border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="rounded-lg bg-[#540D6E] hover:bg-[#430a58] px-5 py-2 text-xs font-black text-white shadow-md transition-all cursor-pointer"
              >
                Atualizar Aula
              </button>
            </div>
          </form>
        </div>
      )}

      {/* 5. Modal: Adicionar Item na Biblioteca */}
      {isCreatingLibraryItem && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <form
            onSubmit={handleAddLibraryItem}
            className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl relative text-left animate-in fade-in duration-200"
          >
            <h3 className="text-lg font-bold text-slate-900 mb-1">Adicionar Recurso à Biblioteca</h3>
            <p className="text-[10px] text-slate-400 mb-4 leading-tight">Cadastre arquivos didáticos ou links úteis para a biblioteca geral da escola.</p>
            
            {/* Método de Anexo (Tabs) */}
            <div className="flex border-b border-slate-100 mb-4 text-xs font-bold">
              <button
                type="button"
                onClick={() => setLibUploadMethod('upload')}
                className={`flex-1 pb-2 text-center transition-all cursor-pointer ${
                  libUploadMethod === 'upload'
                    ? 'text-teal-600 border-b-2 border-teal-600 font-extrabold'
                    : 'text-slate-400 hover:text-slate-700'
                }`}
              >
                Subir Arquivo (PDF, DOC)
              </button>
              <button
                type="button"
                onClick={() => setLibUploadMethod('url')}
                className={`flex-1 pb-2 text-center transition-all cursor-pointer ${
                  libUploadMethod === 'url'
                    ? 'text-teal-600 border-b-2 border-teal-600 font-extrabold'
                    : 'text-slate-400 hover:text-slate-700'
                }`}
              >
                Link Externo (URL)
              </button>
            </div>

            <div className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Título do Recurso</label>
                <input
                  type="text"
                  required
                  value={libTitle}
                  onChange={(e) => setLibTitle(e.target.value)}
                  placeholder="Ex: Guia de Boas Práticas..."
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Tipo</label>
                  <select
                    value={libType}
                    disabled={libUploadMethod === 'upload'}
                    onChange={(e) => setLibType(e.target.value as any)}
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-xs font-semibold disabled:opacity-50"
                  >
                    <option value="pdf">Arquivo PDF</option>
                    <option value="link">Link Externo</option>
                    <option value="video">Vídeo Aula</option>
                    <option value="zip">Pacote ZIP</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Categoria</label>
                  <input
                    type="text"
                    required
                    value={libCategory}
                    onChange={(e) => setLibCategory(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                  />
                </div>
              </div>

              {libUploadMethod === 'upload' ? (
                <div className="space-y-3">
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Upload do Arquivo</label>
                  
                  {/* Drag-and-drop zone */}
                  {!libFile ? (
                    <div
                      onDragOver={(e) => { e.preventDefault(); setLibDragging(true); }}
                      onDragLeave={() => setLibDragging(false)}
                      onDrop={(e) => {
                        e.preventDefault();
                        setLibDragging(false);
                        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                          const file = e.dataTransfer.files[0];
                          setLibFile(file);
                          if (!libTitle) setLibTitle(file.name.replace(/\.[^/.]+$/, ""));
                        }
                      }}
                      onClick={() => document.getElementById('lib-file-uploader')?.click()}
                      className={`border-2 border-dashed rounded-xl p-5 text-center cursor-pointer transition-all ${
                        libDragging
                          ? 'border-teal-500 bg-teal-50/40'
                          : 'border-slate-200 bg-slate-50 hover:bg-slate-100/50 hover:border-slate-350'
                      }`}
                    >
                      <input
                        type="file"
                        id="lib-file-uploader"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt"
                        className="hidden"
                        onChange={(e) => {
                          if (e.target.files && e.target.files[0]) {
                            const file = e.target.files[0];
                            setLibFile(file);
                            if (!libTitle) setLibTitle(file.name.replace(/\.[^/.]+$/, ""));
                          }
                        }}
                      />
                      <File className="h-7 w-7 text-teal-500 mx-auto mb-1 animate-pulse" />
                      <p className="text-xs font-bold text-slate-700">Arraste seu arquivo aqui ou clique para selecionar</p>
                      <p className="text-[9px] text-slate-400 mt-0.5">Formatos suportados: PDF, DOCX, PPTX ou ZIP (Máx. 50MB)</p>
                    </div>
                  ) : (
                    <div className="bg-teal-50/50 border border-teal-200/50 rounded-xl p-3 flex items-center justify-between text-xs">
                      <div className="flex items-center gap-3 min-w-0">
                        <div className="p-2 rounded-lg bg-teal-100 text-teal-800 shrink-0">
                          <FileText className="h-4.5 w-4.5" />
                        </div>
                        <div className="min-w-0">
                          <p className="font-extrabold text-slate-800 truncate">{libFile.name}</p>
                          <p className="text-[10px] font-mono text-slate-400 mt-0.5">{(libFile.size / (1024 * 1024)).toFixed(2)} MB</p>
                        </div>
                      </div>
                      <button
                        type="button"
                        onClick={() => {
                          setLibFile(null);
                          setLibTitle('');
                        }}
                        className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                        title="Remover arquivo"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  )}
                </div>
              ) : (
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">URL / Caminho</label>
                  <input
                    type="text"
                    required={libUploadMethod === 'url'}
                    value={libUrl}
                    onChange={(e) => setLibUrl(e.target.value)}
                    placeholder="https://exemplo.com/recurso"
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                  />
                </div>
              )}
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => {
                  setLibFile(null);
                  setIsCreatingLibraryItem(false);
                }}
                className="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-650 cursor-pointer hover:bg-slate-200 transition-colors"
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="rounded-lg bg-teal-600 hover:bg-teal-500 px-4 py-2 text-xs font-bold text-white shadow-xs cursor-pointer transition-colors"
              >
                Publicar Recurso
              </button>
            </div>
          </form>
        </div>
      )}

      {/* 6. Modal: Agendar Webinar Global */}
      {isCreatingWebinar && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <form
            onSubmit={handleAddWebinar}
            className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl relative text-left animate-in fade-in duration-200"
          >
            <h3 className="text-lg font-bold text-slate-900 mb-4">Agendar Novo Webinar Global</h3>
            
            <div className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Título do Evento</label>
                <input
                  type="text"
                  required
                  value={webTitle}
                  onChange={(e) => setWebTitle(e.target.value)}
                  placeholder="Ex: Masterclass de Portfolio..."
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Data</label>
                  <input
                    type="text"
                    required
                    value={webDate}
                    onChange={(e) => setWebDate(e.target.value)}
                    placeholder="25 de Junho"
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Horário</label>
                  <input
                    type="text"
                    required
                    value={webTime}
                    onChange={(e) => setWebTime(e.target.value)}
                    placeholder="19:00"
                    className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Link da Sala</label>
                <input
                  type="text"
                  required
                  value={webLink}
                  onChange={(e) => setWebLink(e.target.value)}
                  placeholder="Link do Meet/Zoom"
                  className="w-full rounded-lg border border-slate-200 p-2.5 text-sm"
                />
              </div>
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setIsCreatingWebinar(false)}
                className="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-650"
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="rounded-lg bg-indigo-600 hover:bg-indigo-500 px-4 py-2 text-xs font-bold text-white shadow-xs"
              >
                Confirmar Agendamento
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Floating Success Toast Notification */}
      {toastMsg && (
        <div className="fixed bottom-6 right-6 z-50 rounded-lg bg-slate-900 border border-slate-700 text-slate-100 px-5 py-3 shadow-2xl flex items-center gap-3 animate-in fade-in slide-in-from-bottom-2 duration-300 max-w-sm">
          <Check className="h-4.5 w-4.5 text-emerald-400 shrink-0" />
          <span className="text-xs font-semibold">{toastMsg}</span>
        </div>
      )}
    </div>
  );
};
