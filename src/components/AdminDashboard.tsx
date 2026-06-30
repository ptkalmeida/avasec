/**
 * @license
 * SPDX-License-Identifier: Apache-2.5
 */

import React, { useState } from 'react';
import { useLMS } from '../context/LMSContext';
import { 
  ShieldCheck, Users, User, BookOpen, Award, CheckSquare, Plus, ArrowLeft,
  Trash2, Lock, Settings, Activity, FileText, Search, Shield, Filter,
  FileCheck, Printer, Download, Check, X, Layers, Save,
  ArrowUpRight, ArrowDownRight, TrendingUp, Eye, EyeOff, Key
} from 'lucide-react';
import { 
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, 
  PieChart, Pie, Cell, LineChart, Line, AreaChart, Area 
} from 'recharts';

interface AdminDashboardProps {
  onBackToLanding?: () => void;
  speakText: (text: string) => void;
}

export function AdminDashboard({ onBackToLanding, speakText }: AdminDashboardProps) {
  const {
    courses,
    progress,
    certificates,
    quizzes,
    quizSubmissions,
    professorsList,
    studentsList,
    academicRequests,
    addProfessor,
    deleteProfessor,
    addStudent,
    deleteStudent,
    addCourse,
    deleteCourse,
    updateCourseInstructor,
    updateCourseProps,
    updateRequestStatus,
    addAdmissionRequest,
    admissionRequests,
    categoriesList,
    addCategory,
    systemSettings,
    updateSystemSettings,
    studentEnrollments,
    clearStudentPenalty,
  } = useLMS();

  // Selected Section State: 'analytics' | 'professors' | 'courses' | 'students' | 'requests' | 'settings'
  const [activeTab, setActiveTab] = useState<'analytics' | 'professors' | 'courses' | 'students' | 'requests' | 'settings'>('analytics');

  // New report active sub-filters: 'consolidado' | 'alunos' | 'professores' | 'cursos' | 'inscricoes'
  const [activeReportSubTab, setActiveReportSubTab] = useState<'consolidado' | 'alunos' | 'professores' | 'cursos' | 'inscricoes'>('consolidado');

  // Document generation helper state
  const [activeDocViewer, setActiveDocViewer] = useState<{
    studentName: string;
    type: 'historico' | 'certificado' | 'matricula';
    courseTitle?: string;
  } | null>(null);

  // Form State for Instructor creation
  const [newProfName, setNewProfName] = useState('');
  const [newProfPassword, setNewProfPassword] = useState('');
  const [showProfPassword, setShowProfPassword] = useState(false);
  const [newProfSpecialty, setNewProfSpecialty] = useState('Design de Interfaces');
  const [newStudentName, setNewStudentName] = useState('');
  const [newStudentEmail, setNewStudentEmail] = useState('');
  const [newStudentPassword, setNewStudentPassword] = useState('');
  const [showStudentPassword, setShowStudentPassword] = useState(false);
  const [selectedEnrollCourseId, setSelectedEnrollCourseId] = useState<string | null>(null);
  const [showCoursePickerModal, setShowCoursePickerModal] = useState(false);
  const [toastMsg, setToastMsg] = useState('');

  const handleBack = () => {
    if (activeDocViewer) {
      setActiveDocViewer(null);
    } else if (expandedCourseStudentsId) {
      setExpandedCourseStudentsId(null);
    } else if (onBackToLanding) {
      onBackToLanding();
    }
  };

  const getBackLabel = () => {
    if (activeDocViewer) return "Fechar Documento";
    if (expandedCourseStudentsId) return "Voltar p/ Gestão de Alunos";
    return "Sair p/ Portal";
  };

  // Course configuration parameters
  const [newCourseTitle, setNewCourseTitle] = useState('');
  const [newCourseCategory, setNewCourseCategory] = useState(categoriesList[0] || 'Tecnologia');
  const [newCourseTeacher, setNewCourseTeacher] = useState(professorsList[0] || 'Alessandro Pinto');
  const [newCourseDesc, setNewCourseDesc] = useState('');
  const [newCourseType, setNewCourseType] = useState<'fixo' | 'ao_vivo'>('fixo');
  const [newCourseHasChat, setNewCourseHasChat] = useState(true);
  const [newCategoryName, setNewCategoryName] = useState('');
  
  // Changing course instructor state
  const [editingInstructorCourseId, setEditingInstructorCourseId] = useState<string | null>(null);
  const [tempInstructorName, setTempInstructorName] = useState('');
  const [expandedCourseStudentsId, setExpandedCourseStudentsId] = useState<string | null>(null);

  // Minimal Search states
  const [studentSearchQuery, setStudentSearchQuery] = useState('');
  const [courseSearchQuery, setCourseSearchQuery] = useState('');
  const [professorSearchQuery, setProfessorSearchQuery] = useState('');

  // Customizable certificate attendance barrier state simulation (defaults 70)
  const [attendanceBarrier, setAttendanceBarrier] = useState(70);

  const showToast = (msg: string) => {
    setToastMsg(msg);
    setTimeout(() => {
      setToastMsg('');
    }, 4000);
  };

  const handleCreateProfessor = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newProfName.trim()) {
      showToast('Por favor, digite o nome completo do professor.');
      return;
    }

    addProfessor(newProfName.trim(), newProfPassword.trim());
    showToast(`Professor(a) ${newProfName.trim()} foi registrado(a) com sucesso com cargo Letrado!`);
    setNewProfName('');
    setNewProfPassword('');
  };

  const handleCreateStudent = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newStudentName.trim() || !newStudentEmail.trim()) {
      showToast('Por favor, digite o nome e e-mail do discente.');
      return;
    }

    if (!selectedEnrollCourseId) {
      showToast('Por favor, selecione o curso de matrícula.');
      return;
    }

    const pass = newStudentPassword.trim() || '1234';
    const studentName = newStudentName.trim();
    const studentEmail = newStudentEmail.trim();

    addStudent(studentName, studentEmail, pass);
    
    // Auto-enroll in the selected course with approved status
    if (selectedEnrollCourseId) {
      addAdmissionRequest(studentName, selectedEnrollCourseId, 'approved');
      const course = courses.find(c => c.id === selectedEnrollCourseId);
      if (course) {
        showToast(`Aluno ${studentName} matriculado(a) em ${course.title} sob supervisão de ${course.instructorName}!`);
      }
    } else {
      showToast(`Aluno ${studentName} matriculado(a) com sucesso!`);
    }

    setNewStudentName('');
    setNewStudentEmail('');
    setNewStudentPassword('');
    setSelectedEnrollCourseId(null);
  };

  const handleCreateCourse = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newCourseTitle.trim()) {
      showToast('Por favor, informe o título do novo curso.');
      return;
    }

    const randomId = `course-${Date.now()}`;
    const randPhoto = [
      'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=60',
      'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=800&auto=format&fit=crop&q=60',
      'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=800&auto=format&fit=crop&q=60',
      'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=800&auto=format&fit=crop&q=60'
    ][Math.floor(Math.random() * 4)];

    addCourse({
      id: randomId,
      title: newCourseTitle.trim(),
      description: newCourseDesc.trim() || 'Este é um curso recém-provido pelo suporte administrativo da instituição.',
      category: newCourseCategory,
      thumbnail: randPhoto,
      instructorName: newCourseTeacher,
      courseType: newCourseType,
      hasChat: newCourseHasChat,
      lessons: [],
      liveSessions: []
    });

    showToast(`Curso de "${newCourseTitle.trim()}" provido e atribuído para ${newCourseTeacher}!`);
    setNewCourseTitle('');
    setNewCourseDesc('');
  };

  // List of registered student accounts for master academic academic progress tracking
  const mockStudents = studentsList;

  const calculateStudentOverallAttendance = (studentName: string) => {
    if (courses.length === 0) return 0;
    let totals = 0;
    let attended = 0;

    courses.forEach(course => {
      const lessonsInCourse = course.lessons.length;
      const livesInCourse = course.liveSessions.length;
      const activities = lessonsInCourse + livesInCourse;
      totals += activities;

      const userProg = progress.find(p => p.courseId === course.id);
      if (userProg) {
        attended += userProg.completedLessons.length;
        attended += userProg.attendedLiveSessions.length;
      }
    });

    if (totals === 0) return 0;
    return Math.round((attended / totals) * 100);
  };

  const getGlobalAverageQuizScore = () => {
    if (quizSubmissions.length === 0) return 0;
    const total = quizSubmissions.reduce((sum, s) => sum + s.scorePercent, 0);
    return Math.round(total / quizSubmissions.length);
  };

  return (
    <div className="max-w-7xl mx-auto px-4 py-8 animate-in fade-in duration-300">
      
      {/* Top Welcome Panel */}
      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-3xs mb-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-6">
          {onBackToLanding && (
            <button
              onClick={() => {
                const label = getBackLabel();
                speakText(`${label}. Retornando.`);
                handleBack();
              }}
              className="group flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-400 hover:text-slate-800 hover:bg-slate-100 transition-all cursor-pointer shadow-3xs"
              title={getBackLabel()}
            >
              <ArrowLeft className="h-4 w-4 group-hover:-translate-x-0.5 transition-transform" />
              <span className="text-[10px] font-bold uppercase tracking-tighter">{getBackLabel()}</span>
            </button>
          )}

          <div className="flex items-center gap-4 text-left">
            <div className="rounded-2xl bg-slate-900 text-white p-3.5 shadow-xs">
              <ShieldCheck className="h-6 w-6 text-amber-400" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-lg font-black tracking-tight text-slate-900">Portal do Administrador</h2>
                <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[9px] font-bold text-slate-500 uppercase">Master Root</span>
              </div>
              <p className="text-xs text-slate-500 mt-1">Gestão global de professores, alunos, turmas e cursos.</p>
            </div>
          </div>
        </div>

        {/* Data Privacy Disclaimer badge */}
        <div className="rounded-xl bg-slate-50 border border-slate-150 p-3 max-w-sm text-left flex gap-2.5 items-start">
          <Lock className="h-4 w-4 text-amber-600 shrink-0 mt-0.5" />
          <div>
            <span className="text-[10px] font-bold uppercase tracking-wide text-slate-700 block">Privacidade de Conversas</span>
            <span className="text-[10px] text-slate-400 leading-normal block mt-0.5">O administrador não tem acesso de leitura aos chats privados ou DMs de alunos por diretrizes de privacidade de dados.</span>
          </div>
        </div>
      </div>

      {/* Internal Navigation tabs */}
      <div className="flex border-b border-slate-205 mb-8 overflow-x-auto gap-1 scrollbar-hide lg:justify-center">
        {[
          { id: 'analytics', label: 'Dashboard & Relatórios', icon: Activity },
          { id: 'professors', label: 'Gestor de Cursos', icon: User },
          { id: 'students', label: 'Alunos', icon: Award },
          { id: 'courses', label: 'Cursos & Trilhas', icon: BookOpen },
          { id: 'requests', label: 'Documentos', icon: FileCheck },
          { id: 'settings', label: 'Configurações', icon: Settings },
        ].map((tab) => {
          const IconComp = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`px-5 py-3.5 border-b-2 text-xs font-bold transition-all flex items-center gap-2.5 whitespace-nowrap cursor-pointer relative group ${
                isActive
                  ? 'border-slate-800 text-slate-900'
                  : 'border-transparent text-slate-400 hover:text-slate-600 hover:border-slate-200'
              }`}
            >
              <IconComp className={`h-4 w-4 transition-colors ${isActive ? 'text-teal-600' : 'text-slate-400 group-hover:text-slate-500'}`} />
              {tab.label}
              {isActive && (
                <div className="absolute -bottom-0.5 left-0 right-0 h-0.5 bg-teal-500 rounded-full animate-in fade-in zoom-in-50 duration-300" />
              )}
            </button>
          );
        })}
      </div>

      {/* TAB CONTENT SPACES */}
      {activeTab === 'analytics' && (
        <div className="space-y-6 text-left animate-in fade-in duration-300">
          
          {/* Header Info */}
          <div className="bg-white border border-slate-200 rounded-xl p-5 space-y-2">
            <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <Activity className="h-4.5 w-4.5 text-indigo-600" />
              <span>Painel de Controle e Inteligência de Dados (Analytics)</span>
            </h3>
            <p className="text-xs text-slate-500 leading-relaxed">
              Visão consolidada do ecossistema educacional. Monitore métricas de engajamento, rendimento pedagógico e gere relatórios oficiais de auditoria.
            </p>
          </div>

          <div className="border-t border-slate-100 pt-6">
            <div className="mb-4">
              <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <FileText className="h-4 w-4 text-teal-600" />
                <span>Módulos de Relatórios e Auditoria Pedagógica</span>
              </h3>
              <p className="text-[11px] text-slate-500 mt-1">Gere documentos oficiais e estatísticas cruzadas de alunos e professores.</p>
            </div>

            {/* Sub Navigation controls to target specific reports */}
            <div className="flex bg-slate-50 p-1 rounded-xl border border-slate-200 gap-1 overflow-x-auto mb-6 max-w-fit md:mx-auto">
              {[
                { id: 'consolidado', label: 'Estatísticas Gerais', icon: Activity },
                { id: 'alunos', label: 'Alunos', icon: Users },
                { id: 'professores', label: 'Professores', icon: ShieldCheck },
                { id: 'cursos', label: 'Andamento', icon: BookOpen },
                { id: 'inscricoes', label: 'Matrículas', icon: FileCheck },
              ].map((st) => {
                const SubIcon = st.icon;
                const isSubActive = activeReportSubTab === st.id;
                return (
                  <button
                    key={st.id}
                    onClick={() => setActiveReportSubTab(st.id as any)}
                    className={`px-4 py-2 rounded-lg text-[10.5px] font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer ${
                      isSubActive 
                        ? 'bg-white text-slate-900 shadow-sm border border-slate-200/60 font-black' 
                        : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100/50'
                    }`}
                  >
                    <SubIcon className={`h-3.5 w-3.5 ${isSubActive ? 'text-teal-600' : 'text-slate-400'}`} />
                    <span>{st.label}</span>
                  </button>
                );
              })}
            </div>

            <div className="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
              
              {/* Action buttons header */}
              <div className="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-2">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                  Relatório de Rendimento: <span className="text-slate-800 font-extrabold text-xs">{activeReportSubTab.toUpperCase()}</span>
                </span>
                <button
                  onClick={() => {
                    window.print();
                  }}
                  className="text-[10px] bg-slate-900 hover:bg-slate-800 text-white transition-colors font-bold px-3 py-1.8 rounded-lg flex items-center gap-1.5 cursor-pointer"
                >
                  <Printer className="h-3 w-3 text-slate-300" />
                  <span>Imprimir Relatório Oficial</span>
                </button>
              </div>

              {/* RELATÓRIO CONSOLIDADO / DASHBOARD */}
              {activeReportSubTab === 'consolidado' && (
                <div className="space-y-8 animate-in fade-in duration-300">
                  
                  {/* Metric Cards Row - Integrated with main analytics but keeping the sub-view clean */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-3xs space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="p-2 bg-indigo-50 rounded-lg">
                          <Users className="h-4 w-4 text-indigo-600" />
                        </div>
                        <span className="flex items-center gap-0.5 text-[10px] font-black text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full">
                          <ArrowUpRight className="h-3 w-3" />
                          12%
                        </span>
                      </div>
                      <div>
                        <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total de Alunos</p>
                        <p className="text-2xl font-black text-slate-900">{studentsList.length}</p>
                      </div>
                    </div>

                    <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-3xs space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="p-2 bg-teal-50 rounded-lg">
                          <BookOpen className="h-4 w-4 text-teal-600" />
                        </div>
                        <span className="flex items-center gap-0.5 text-[10px] font-black text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded-full">
                          Estável
                        </span>
                      </div>
                      <div>
                        <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Cursos Ativos</p>
                        <p className="text-2xl font-black text-slate-900">{courses.length}</p>
                      </div>
                    </div>

                    <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-3xs space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="p-2 bg-amber-50 rounded-lg">
                          <Award className="h-4 w-4 text-amber-600" />
                        </div>
                        <span className="flex items-center gap-0.5 text-[10px] font-black text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full">
                          <ArrowUpRight className="h-3 w-3" />
                          8%
                        </span>
                      </div>
                      <div>
                        <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Diplomas Emitidos</p>
                        <p className="text-2xl font-black text-slate-900">{certificates.length}</p>
                      </div>
                    </div>

                    <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-3xs space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="p-2 bg-rose-50 rounded-lg">
                          <Activity className="h-4 w-4 text-rose-600" />
                        </div>
                        <span className="flex items-center gap-0.5 text-[10px] font-black text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded-full">
                          <ArrowDownRight className="h-3 w-3" />
                          3%
                        </span>
                      </div>
                      <div>
                        <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Taxa de Evasão</p>
                        <p className="text-2xl font-black text-slate-900">4.2%</p>
                      </div>
                    </div>
                  </div>

                  {/* 2. Charts Section */}
                  <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    {/* Category Distribution (Pie) */}
                    <div className="lg:col-span-1 bg-white border border-slate-200 rounded-2xl p-5 shadow-3xs flex flex-col">
                      <h4 className="text-xs font-black text-slate-900 uppercase tracking-widest mb-6 flex items-center gap-2">
                        <Layers className="h-3.5 w-3.5 text-indigo-600" />
                        Inscrições por Categoria
                      </h4>
                      <div className="h-[240px] w-full">
                        <ResponsiveContainer width="100%" height="100%">
                          <PieChart>
                            <Pie
                              data={categoriesList.map((cat, idx) => ({
                                name: cat,
                                value: courses.filter(c => c.category === cat).length * 15 + Math.floor(Math.random() * 20)
                              }))}
                              cx="50%"
                              cy="50%"
                              innerRadius={60}
                              outerRadius={80}
                              paddingAngle={5}
                              dataKey="value"
                            >
                              {categoriesList.map((_, index) => (
                                <Cell key={`cell-${index}`} fill={[
                                  '#540D6E', '#EE4266', '#FFD23F', '#3BCEAC', '#0EAD69'
                                ][index % 5]} />
                              ))}
                            </Pie>
                            <Tooltip 
                              contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: '11px', fontWeight: 'bold' }}
                            />
                          </PieChart>
                        </ResponsiveContainer>
                      </div>
                    </div>

                    {/* Monthly Trend (Area Chart) */}
                    <div className="lg:col-span-2 bg-white border border-slate-200 rounded-2xl p-5 shadow-3xs">
                      <div className="flex items-center justify-between mb-6">
                        <h4 className="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                          <TrendingUp className="h-3.5 w-3.5 text-teal-600" />
                          Crescimento de Matrículas (Semestral)
                        </h4>
                      </div>
                      <div className="h-[240px] w-full">
                        <ResponsiveContainer width="100%" height="100%">
                          <AreaChart
                            data={[
                              { name: 'Jan', matriculas: 45, conclusoes: 21 },
                              { name: 'Fev', matriculas: 52, conclusoes: 25 },
                              { name: 'Mar', matriculas: 48, conclusoes: 30 },
                              { name: 'Abr', matriculas: 70, conclusoes: 42 },
                              { name: 'Mai', matriculas: 85, conclusoes: 50 },
                              { name: 'Jun', matriculas: 92, conclusoes: 65 },
                            ]}
                            margin={{ top: 10, right: 30, left: 0, bottom: 0 }}
                          >
                            <defs>
                              <linearGradient id="colorMatricula" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="#540D6E" stopOpacity={0.1}/>
                                <stop offset="95%" stopColor="#540D6E" stopOpacity={0}/>
                              </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E2E8F0" />
                            <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: '#64748B', fontWeight: 'bold' }} />
                            <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: '#64748B', fontWeight: 'bold' }} />
                            <Tooltip contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: '11px', fontWeight: 'bold' }} />
                            <Area type="monotone" dataKey="matriculas" stroke="#540D6E" strokeWidth={3} fillOpacity={1} fill="url(#colorMatricula)" />
                          </AreaChart>
                        </ResponsiveContainer>
                      </div>
                    </div>
                  </div>

                  {/* Combined High-Level Counters (Relocated below charts) */}
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-left border-t border-slate-100 pt-6">
                    {[
                      { label: 'Total de Alunos', value: studentsList.length, desc: 'Alunos Ativos', icon: Users, accent: 'bg-indigo-50 border-indigo-100 text-indigo-700' },
                      { label: 'Cursos Ativos', value: courses.length, desc: 'Disciplinas em Catálogo', icon: BookOpen, accent: 'bg-teal-50 border-teal-100 text-teal-700' },
                      { label: 'Gestão', value: '1 Gestor', desc: 'Gestor de Cursos', icon: ShieldCheck, accent: 'bg-emerald-50 border-emerald-100 text-emerald-700' }
                    ].map((stat) => (
                      <div key={stat.label} className="bg-white border border-slate-200 p-4 rounded-xl shadow-3xs flex justify-between items-start">
                        <div className="space-y-1">
                          <header className="text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-tight">{stat.label}</header>
                          <p className="text-xl font-black text-slate-900 font-mono leading-none">{stat.value}</p>
                          <span className="text-[9px] text-slate-500 block leading-tight">{stat.desc}</span>
                        </div>
                        <div className={`p-1.5 rounded-lg border ${stat.accent}`}>
                          <stat.icon className="h-3.5 w-3.5" />
                        </div>
                      </div>
                    ))}
                  </div>

                </div>
              )}

              {/* Alunos list detailed report */}
              {activeReportSubTab === 'alunos' && (
                <div className="space-y-4 animate-in fade-in duration-200">
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-150">
                    <div className="text-left font-sans">
                      <span className="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Total de Alunos Auditados</span>
                      <strong className="text-xl font-black text-slate-900 font-mono">{mockStudents.length} discentes</strong>
                    </div>
                    <div className="text-left">
                      <span className="text-[9px] font-bold text-slate-400 uppercase tracking-widest block font-sans">Média Geral Frequência</span>
                      <strong className="text-xl font-black text-teal-700 font-mono">
                        {Math.round(mockStudents.reduce((sum, s) => sum + calculateStudentOverallAttendance(s.name), 0) / mockStudents.length)}%
                      </strong>
                    </div>
                    <div className="text-left">
                      <span className="text-[9px] font-bold text-slate-400 uppercase tracking-widest block font-sans">Diplomas Autenticados</span>
                      <strong className="text-xl font-black text-emerald-700 font-mono">{certificates.length} emitidos</strong>
                    </div>
                  </div>

                  <div className="overflow-x-auto">
                    <table className="w-full text-xs text-left border-collapse">
                      <thead>
                        <tr className="border-b border-slate-150 text-[10px] font-bold text-slate-500 uppercase bg-slate-50/50">
                          <th className="p-2.5">Nome do Aluno</th>
                          <th className="p-2.5">Email</th>
                          <th className="p-2.5 text-center">Frequência</th>
                          <th className="p-2.5 text-center">Média Testes</th>
                          <th className="p-2.5 text-right">Ação</th>
                        </tr>
                      </thead>
                      <tbody>
                        {mockStudents.map((st) => {
                          const attendance = calculateStudentOverallAttendance(st.name);
                          const submissions = quizSubmissions.filter(s => s.studentName === st.name);
                          const avgScore = submissions.length === 0 ? 0 : Math.round(submissions.reduce((s, x) => s + x.scorePercent, 0) / submissions.length);
                          return (
                            <tr key={st.email} className="border-b border-slate-100 hover:bg-slate-50/25">
                              <td className="p-2.5">
                                <span className="font-extrabold text-slate-900 block">{st.name}</span>
                              </td>
                              <td className="p-2.5 text-slate-500 font-mono">{st.email}</td>
                              <td className="p-2.5 text-center">
                                <span className={`font-bold ${attendance >= attendanceBarrier ? 'text-emerald-600' : 'text-amber-500'}`}>{attendance}%</span>
                              </td>
                              <td className="p-2.5 text-center">
                                <span className="text-slate-705 font-mono">{submissions.length > 0 ? `${avgScore}%` : '-'}</span>
                              </td>
                              <td className="p-2.5 text-right">
                                <select 
                                  onChange={(e) => {
                                    const val = e.target.value;
                                    if (val) {
                                      setActiveDocViewer({ studentName: st.name, type: val as any });
                                      e.target.value = '';
                                    }
                                  }}
                                  className="text-[10px] bg-slate-100 border border-slate-205 rounded px-2 py-1 text-slate-700 font-semibold focus:outline-hidden cursor-pointer"
                                >
                                  <option value="">Emitir...</option>
                                  <option value="historico">Histórico</option>
                                  <option value="certificado">Diploma</option>
                                  <option value="matricula">Matrícula</option>
                                </select>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {/* Professores detailed report */}
              {activeReportSubTab === 'professores' && (
                <div className="space-y-4 animate-in fade-in duration-200">
                  <div className="bg-slate-50 p-4 rounded-xl border border-slate-150 flex items-center justify-between">
                    <div>
                      <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Professores Cadastrados</span>
                      <strong className="text-xl font-black text-slate-900 font-mono">{professorsList.length} professores</strong>
                    </div>
                  </div>

                  <div className="overflow-x-auto">
                    <table className="w-full text-xs text-left border-collapse">
                      <thead>
                        <tr className="border-b border-slate-150 text-[10px] font-bold text-slate-500 uppercase bg-slate-50/50">
                          <th className="p-2.5">Nome do Professor</th>
                          <th className="p-2.5 text-center">Cursos</th>
                          <th className="p-2.5 text-right">Total Aulas</th>
                        </tr>
                      </thead>
                      <tbody>
                        {professorsList.map((prof) => {
                          const assigned = courses.filter(c => c.instructorName === prof);
                          const totalLessons = assigned.reduce((sum, c) => sum + c.lessons.length, 0);
                          return (
                            <tr key={prof} className="border-b border-slate-100">
                              <td className="p-2.5 font-extrabold text-slate-900">{prof}</td>
                              <td className="p-2.5 text-center font-bold text-teal-600">{assigned.length} cursos</td>
                              <td className="p-2.5 text-right font-mono text-slate-500">{totalLessons} aulas</td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {/* Cursos detailed report */}
              {activeReportSubTab === 'cursos' && (
                <div className="space-y-4 animate-in fade-in duration-200">
                  <div className="overflow-x-auto">
                    <table className="w-full text-xs text-left border-collapse">
                      <thead>
                        <tr className="border-b border-slate-150 text-[10px] font-bold text-slate-500 uppercase bg-slate-50/50">
                          <th className="p-2.5">Curso</th>
                          <th className="p-2.5 text-center">Módulos</th>
                          <th className="p-2.5 text-right">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        {courses.map((c) => (
                          <tr key={c.id} className="border-b border-slate-100">
                            <td className="p-2.5">
                              <span className="font-extrabold text-slate-900 block">{c.title}</span>
                              <span className="text-[9px] text-slate-400 block font-mono">{c.category}</span>
                            </td>
                            <td className="p-2.5 text-center font-mono">{c.lessons.length}</td>
                            <td className="p-2.5 text-right">
                              <span className="text-[9px] font-black uppercase bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full">
                                {c.lessons.length > 0 ? "Ativo" : "Planejado"}
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {/* Inscrições detailed reports */}
              {activeReportSubTab === 'inscricoes' && (
                <div className="space-y-4 animate-in fade-in duration-200">
                  <div className="overflow-x-auto">
                    <table className="w-full text-xs text-left border-collapse">
                      <thead>
                        <tr className="border-b border-slate-150 text-[10px] font-bold text-slate-500 uppercase bg-slate-50/50">
                          <th className="p-2.5">Aluno</th>
                          <th className="p-2.5">Disciplina</th>
                          <th className="p-2.5 text-center">Progresso</th>
                        </tr>
                      </thead>
                      <tbody>
                        {mockStudents.flatMap((s, si) => 
                          courses.slice(0, 2).map((c, ci) => {
                            const userProg = progress.find(p => p.courseId === c.id);
                            const comp = userProg ? userProg.completedLessons.length : 0;
                            const ratio = c.lessons.length > 0 ? Math.round((comp / c.lessons.length) * 100) : 0;
                            return (
                              <tr key={`${si}-${ci}`} className="border-b border-slate-100">
                                <td className="p-2.5 font-bold text-slate-900">{s.name}</td>
                                <td className="p-2.5 text-slate-600">{c.title}</td>
                                <td className="p-2.5 text-center font-mono text-teal-600 font-bold">{ratio}%</td>
                              </tr>
                            )
                          })
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

            </div>
          </div>

        </div>
      )}

      {activeTab === 'professors' && (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 text-left">
          
          {/* Gestor de Cursos Configuration Panel */}
          <div className="lg:col-span-4 bg-white border border-slate-200 p-5 rounded-xl text-left h-fit space-y-4">
            <div>
              <span className="rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-0.5 text-[9px] font-bold border border-emerald-100 uppercase tracking-wide">
                Configuração de Perfil
              </span>
              <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider mt-2">Configurações do Gestor</h3>
              <p className="text-[11px] text-slate-500 mt-0.5">Gerencie os dados e acessos de controle de cursos.</p>
            </div>

            <div className="space-y-3 pt-2">
              <div className="bg-slate-50 border border-slate-150 p-3.5 rounded-xl space-y-2.5">
                <div>
                  <span className="text-[9px] text-slate-400 font-bold uppercase block leading-none">Usuário do Gestor</span>
                  <span className="text-xs font-bold text-slate-800 block mt-1">Gestor de Cursos</span>
                </div>
                <div>
                  <span className="text-[9px] text-slate-400 font-bold uppercase block leading-none">Especialidade Principal</span>
                  <span className="text-xs font-semibold text-slate-700 block mt-1">Design de Interfaces & Novas Mídias</span>
                </div>
                <div>
                  <span className="text-[9px] text-slate-400 font-bold uppercase block leading-none">PIN de Acesso</span>
                  <span className="text-xs font-mono font-bold text-[#540D6E] block mt-1">5678 ou 1234</span>
                </div>
              </div>
            </div>
          </div>

          {/* Master Professors list cards */}
          <div className="lg:col-span-8 bg-white border border-slate-200 p-5 rounded-xl space-y-4 professors-list-container">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
              <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider">
                Gestor Ativo no AVA ({professorsList.length})
              </h3>
              <div className="relative w-full sm:w-auto">
                <Search className="absolute left-2.5 top-2 h-3.5 w-3.5 text-slate-400" />
                <input
                  type="text"
                  placeholder="Buscar gestor por nome..."
                  value={professorSearchQuery}
                  onChange={(e) => setProfessorSearchQuery(e.target.value)}
                  className="w-full sm:w-64 pl-8 pr-3 py-1.5 text-[11px] border border-slate-200 rounded-lg max-w-full text-slate-700 bg-slate-50 focus:bg-white transition-colors focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {professorsList.filter(prof => {
                if (!professorSearchQuery) return true;
                return prof.toLowerCase().includes(professorSearchQuery.toLowerCase());
              }).map((prof, idx) => {
                const assignedCourses = courses.filter(c => c.instructorName === prof);
                return (
                  <div key={prof} className="border border-slate-150 p-4 rounded-xl bg-slate-50/40 relative group">
                    <div className="absolute top-4 right-4 flex items-center gap-1.5">
                      <span className="text-[9px] bg-slate-200 font-mono font-bold px-1.5 py-0.5 rounded text-slate-600">
                        ID: GESTOR-01
                      </span>
                    </div>
                    <strong className="block font-black text-slate-900 text-xs pr-24">{prof}</strong>
                    <span className="text-[10px] font-bold text-teal-600 tracking-wide uppercase block mt-1">Coordenação Geral de Conteúdos</span>
                    <span className="text-[10px] text-slate-400 block mt-2">Trilhas sob Gestão: {assignedCourses.length}</span>
                    <div className="mt-2.5 pt-2.5 border-t border-slate-150 flex flex-wrap gap-1">
                      {assignedCourses.length === 0 ? (
                        <span className="text-slate-400 text-[10px] italic">Nenhuma disciplina vinculada</span>
                      ) : (
                        assignedCourses.map(c => (
                          <span key={c.id} className="text-[9px] bg-white border border-slate-150 text-slate-700 px-1.5 py-0.5 rounded">
                            {c.title}
                          </span>
                        ))
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

        </div>
      )}

      {activeTab === 'courses' && (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 text-left">
          
          {/* Create custom Course Block */}
          <div className="lg:col-span-4 bg-white border border-slate-200 p-5 rounded-xl h-fit space-y-4">
            <div>
              <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider">Prover Disciplina</h3>
              <p className="text-[11px] text-slate-500 mt-0.5">Cadastre um novo curso na plataforma letiva.</p>
            </div>

            <form onSubmit={handleCreateCourse} className="space-y-3">
              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Título do Curso</label>
                <input
                  type="text"
                  required
                  placeholder="Ex: Programação Funcional"
                  value={newCourseTitle}
                  onChange={(e) => setNewCourseTitle(e.target.value)}
                  className="w-full border border-slate-200 p-2 text-xs rounded-lg text-slate-800"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Área Acadêmica / Categoria</label>
                <select
                  value={newCourseCategory}
                  onChange={(e) => setNewCourseCategory(e.target.value)}
                  className="w-full border border-slate-200 p-2 text-xs rounded-lg text-slate-800 bg-white"
                >
                  {categoriesList.map((cat) => (
                    <option key={cat} value={cat}>{cat}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Professor Responsável</label>
                <select
                  value={newCourseTeacher}
                  onChange={(e) => setNewCourseTeacher(e.target.value)}
                  className="w-full border border-slate-200 p-2 text-xs rounded-lg text-slate-800 bg-white"
                >
                  {professorsList.map((prof) => (
                    <option key={prof} value={prof}>{prof}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Ementa / Descrição Curta (Opcional)</label>
                <textarea
                  placeholder="Visão abrangente para orientar a admissão dos alunos..."
                  value={newCourseDesc}
                  onChange={(e) => setNewCourseDesc(e.target.value)}
                  className="w-full border border-slate-200 p-2 text-xs rounded-lg text-slate-800 h-16 resize-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tipo de Curso</label>
                  <select
                    value={newCourseType}
                    onChange={(e) => setNewCourseType(e.target.value as 'fixo' | 'ao_vivo')}
                    className="w-full border border-slate-200 p-2 text-xs rounded-lg text-slate-800 bg-white"
                  >
                    <option value="fixo">Gravado (Fixo)</option>
                    <option value="ao_vivo">Síncrono (Ao Vivo)</option>
                  </select>
                </div>

                <div className="flex items-end pb-1.5">
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input 
                      type="checkbox" 
                      className="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-slate-300"
                      checked={newCourseHasChat}
                      onChange={(e) => setNewCourseHasChat(e.target.checked)}
                    />
                    <span className="text-xs font-bold text-slate-700">Ativar Chat do Curso</span>
                  </label>
                </div>
              </div>

              <button
                type="submit"
                className="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2 px-3 rounded-lg text-xs transition-colors cursor-pointer"
              >
                Disponibilizar Curso no Catálogo
              </button>
            </form>

            {/* Habilitar Nova Área de Atuação */}
            <div className="border-t border-slate-100 pt-4 mt-2 space-y-3">
              <div>
                <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                  <Layers className="h-3.5 w-3.5 text-indigo-600" />
                  <span>Habilitar Nova Área de Atuação</span>
                </h3>
                <p className="text-[10px] text-slate-400 mt-0.5">Defina novas áreas de atuação/categorias no catálogo.</p>
              </div>

              <div className="flex gap-1.5">
                <input
                  type="text"
                  placeholder="Ex: Artes Cênicas, Música"
                  value={newCategoryName}
                  onChange={(e) => setNewCategoryName(e.target.value)}
                  className="flex-1 border border-slate-200 p-1.5 text-xs rounded-lg text-slate-800"
                />
                <button
                  type="button"
                  onClick={() => {
                    if (!newCategoryName.trim()) {
                      showToast('Digite um nome válido para a área de atuação!');
                      return;
                    }
                    addCategory(newCategoryName.trim());
                    showToast(`Área de atuação "${newCategoryName.trim()}" habilitada com sucesso!`);
                    setNewCategoryName('');
                  }}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3 text-xs rounded-lg transition-colors cursor-pointer"
                >
                  Criar
                </button>
              </div>

              {/* Badges for active categories */}
              <div className="space-y-1">
                <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Áreas Ativas</span>
                <div className="flex flex-wrap gap-1 max-h-24 overflow-y-auto pr-1">
                  {categoriesList.map(cat => (
                    <span key={cat} className="bg-indigo-50 border border-indigo-100 text-[9px] font-mono font-bold text-indigo-700 px-1.5 py-0.5 rounded-sm">
                      {cat}
                    </span>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* Master Course lists details */}
          <div className="lg:col-span-8 bg-white border border-slate-200 p-5 rounded-xl space-y-4">
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-100 pb-2">
              <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider">
                Cursos Ativos no Catálogo ({courses.length})
              </h3>
              
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-450" />
                <input
                  type="text"
                  placeholder="Pesquisar termo de disciplina..."
                  value={courseSearchQuery}
                  onChange={(e) => setCourseSearchQuery(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-200 pl-8 pr-3 py-1.8 text-[11px] rounded-lg text-slate-700"
                />
              </div>
            </div>

            <div className="space-y-3.5">
              {courses
                .filter(course => {
                  if (!courseSearchQuery) return true;
                  return course.title.toLowerCase().includes(courseSearchQuery.toLowerCase()) || 
                         course.instructorName.toLowerCase().includes(courseSearchQuery.toLowerCase());
                })
                .map((course) => {
                  const testsCount = quizzes.filter(q => q.courseId === course.id).length;
                  
                  // Deterministic active students for this course based on active studentsList
                  const charsSum = course.id.split('').reduce((sum, char) => sum + char.charCodeAt(0), 0);
                  const studentsCount = (charsSum % 3) + 2; // guarantees 2 to 4 active students
                  const activeStudents: typeof studentsList = [];
                  for (let i = 0; i < studentsCount; i++) {
                    const studentIdx = (charsSum + i) % studentsList.length;
                    const student = studentsList[studentIdx];
                    if (student && !activeStudents.some(s => s.name === student.name)) {
                      activeStudents.push(student);
                    }
                  }

                  return (
                    <div key={course.id} className="p-4 border border-slate-150 bg-slate-50/30 rounded-xl text-xs flex flex-col gap-3">
                      <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                        <div className="space-y-1.5 text-left max-w-lg">
                          <div className="flex items-center gap-2">
                            <strong className="text-sm font-extrabold text-slate-900 block">{course.title}</strong>
                            <span className="bg-slate-100 text-[8px] font-black uppercase text-slate-600 px-1.5 rounded">
                              {course.category}
                            </span>
                          </div>
                          
                          <p className="text-[11px] text-slate-500 line-clamp-2">{course.description}</p>
                          
                          {/* Info metrics line & Swap Instructor controls */}
                          <div className="flex flex-col sm:flex-row sm:items-center gap-x-4 gap-y-1.5 text-[10px] text-slate-400 font-medium">
                            <span>Lessons: <strong>{course.lessons.length} aulas</strong></span>
                            <span>Evaluations: <strong>{testsCount} testes</strong></span>
                            <span>Professor Atual: <strong className="text-slate-800">{course.instructorName}</strong></span>
                          </div>

                          {/* Swap instructor dropdown and Course Type/Chat controls */}
                          <div className="flex flex-wrap items-center gap-x-4 gap-y-2 mt-2 text-[10px] text-slate-500">
                            <div className="flex items-center gap-1.5">
                              <span className="font-bold text-slate-450 uppercase tracking-wide">Trocar Professor:</span>
                              <select
                                value={course.instructorName}
                                onChange={(e) => {
                                  updateCourseInstructor(course.id, e.target.value);
                                  showToast(`Professor do curso "${course.title}" modificado com sucesso para ${e.target.value}!`);
                                }}
                                className="border border-slate-200 bg-white p-0.5 px-1.5 text-[10px] rounded-md font-bold text-slate-700 cursor-pointer focus:outline-hidden"
                              >
                                {professorsList.map((prof) => (
                                  <option key={prof} value={prof}>{prof}</option>
                                ))}
                              </select>
                            </div>
                            
                            <div className="flex items-center gap-1.5">
                              <span className="font-bold text-slate-450 uppercase tracking-wide">Tipo:</span>
                              <select
                                value={course.courseType || 'fixo'}
                                onChange={(e) => {
                                  updateCourseProps(course.id, { courseType: e.target.value as 'fixo' | 'ao_vivo' });
                                  showToast(`Tipo do curso "${course.title}" alterado para ${e.target.value === 'fixo' ? 'Gravado (Fixo)' : 'Síncrono (Ao Vivo)'}!`);
                                }}
                                className="border border-slate-200 bg-white p-0.5 px-1.5 text-[10px] rounded-md font-bold text-slate-700 cursor-pointer focus:outline-hidden"
                              >
                                <option value="fixo">Gravado (Fixo)</option>
                                <option value="ao_vivo">Síncrono (Ao Vivo)</option>
                              </select>
                            </div>

                            <div className="flex items-center">
                              <label className="flex items-center gap-1.5 cursor-pointer">
                                <input
                                  type="checkbox"
                                  className="rounded text-indigo-600 focus:ring-indigo-500 h-3 w-3 border-slate-300"
                                  checked={course.hasChat !== false} // default true
                                  onChange={(e) => {
                                    updateCourseProps(course.id, { hasChat: e.target.checked });
                                    showToast(`Chat do curso "${course.title}" ${e.target.checked ? 'ativado' : 'desativado'}!`);
                                  }}
                                />
                                <span className="font-bold text-slate-600 tracking-wide">Ativar Chat</span>
                              </label>
                            </div>
                          </div>
                        </div>

                        {/* Status & Deletion section */}
                        <div className="flex flex-row md:flex-col items-center gap-2 shrink-0 self-start md:self-auto justify-between md:justify-start w-full md:w-auto">
                          <span className="text-[10px] bg-teal-50 text-teal-750 border border-teal-100 font-black px-2.5 py-1 rounded-lg uppercase">
                            Ativo no AVA
                          </span>
                          
                          <button
                            onClick={() => {
                              if (window.confirm(`Tem certeza que deseja excluir permanentemente o curso "${course.title}"?`)) {
                                deleteCourse(course.id);
                                showToast(`Curso "${course.title}" foi excluído com sucesso!`);
                              }
                            }}
                            className="p-1 px-2.5 hover:bg-rose-50 text-rose-600 hover:text-rose-700 border border-slate-200 hover:border-rose-200 rounded-lg transition-all cursor-pointer flex items-center gap-1 font-bold text-[10px]"
                            title="Excluir Disciplina"
                          >
                            <Trash2 className="h-3 w-3" />
                            <span>Excluir</span>
                          </button>
                        </div>
                      </div>

                      {/* Expandable Active Students indicator */}
                      <div className="pt-2 border-t border-slate-100 mt-1">
                        <button
                          type="button"
                          onClick={() => setExpandedCourseStudentsId(expandedCourseStudentsId === course.id ? null : course.id)}
                          className="flex items-center gap-1 text-[11px] text-[#540D6E] font-bold hover:underline cursor-pointer"
                        >
                          <Users className="h-3.5 w-3.5 text-[#540D6E]" />
                          <span>Alunos Ativos ({activeStudents.length}): {activeStudents.map(s => s.name).join(', ')}</span>
                          <span className="text-[9px] text-slate-400 font-normal">({expandedCourseStudentsId === course.id ? 'Ocultar' : 'Ver Detalhes'})</span>
                        </button>
                        
                        {expandedCourseStudentsId === course.id && (
                          <div className="mt-2 bg-slate-50 border border-slate-150 rounded-xl p-3 space-y-2 animate-in fade-in duration-200">
                            <span className="text-[9px] font-extrabold text-slate-450 block uppercase tracking-wider">Identificação dos Alunos Vinculados</span>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                              {activeStudents.map((std, index) => (
                                <div key={index} className="flex items-center gap-2.5 bg-white border border-slate-150 p-2 rounded-lg">
                                  <div className="h-6 w-6 bg-indigo-50 border border-indigo-100 text-indigo-700 text-10 font-bold flex items-center justify-center rounded-full font-sans uppercase">
                                    {std.name[0]}
                                  </div>
                                  <div className="text-left">
                                    <span className="text-[11px] font-bold text-slate-800 block leading-tight">{std.name}</span>
                                    <span className="text-[9px] text-slate-400 block leading-none">{std.email}</span>
                                  </div>
                                </div>
                              ))}
                            </div>
                          </div>
                        )}
                      </div>
                    </div>
                  );
                })}
            </div>
          </div>

        </div>
      )}

      {activeTab === 'students' && (
        <div className="space-y-6 text-left animate-in fade-in duration-300">
          
          {/* Top Title Card */}
          <div className="bg-white border border-slate-200 rounded-xl p-5 space-y-2">
            <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <Users className="h-4.5 w-4.5 text-teal-600" />
              <span>Gerenciamento de Alunos & Usuários Acadêmicos</span>
            </h3>
            <p className="text-xs text-slate-500 leading-relaxed">
              Registre novos alunos e gerencie as contas de estudantes matriculadas. Você também pode acompanhar os índices de frequência e rendimento de quizzes em tempo real.
            </p>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            {/* Enrollment form */}
            <div className="lg:col-span-4 bg-white border border-slate-200 p-5 rounded-xl h-fit space-y-4">
              <div>
                <h4 className="text-xs font-black text-slate-900 uppercase tracking-wider">Matricular Novo Aluno</h4>
                <p className="text-[11px] text-slate-400 mt-0.5">Adicione novos alunos para participarem das turmas da plataforma.</p>
              </div>

              <form onSubmit={handleCreateStudent} className="space-y-3">
                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nome Completo</label>
                  <input
                    type="text"
                    required
                    placeholder="Ex: Clara Ribeiro"
                    value={newStudentName}
                    onChange={(e) => setNewStudentName(e.target.value)}
                    className="w-full border border-slate-200 p-2 text-xs rounded-lg text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-slate-400"
                  />
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">E-mail Acadêmico</label>
                  <input
                    type="email"
                    required
                    placeholder="Ex: clara.ribeiro@lms.edu"
                    value={newStudentEmail}
                    onChange={(e) => setNewStudentEmail(e.target.value)}
                    className="w-full border border-slate-200 p-2 text-xs rounded-lg text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-slate-400"
                  />
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Curso de Matrícula</label>
                  <button
                    type="button"
                    onClick={() => setShowCoursePickerModal(true)}
                    className="w-full border border-slate-200 p-2 text-left text-xs rounded-lg text-slate-700 bg-slate-50/50 hover:bg-slate-100 transition-colors flex items-center justify-between group"
                  >
                    <span>
                      {selectedEnrollCourseId 
                        ? courses.find(c => c.id === selectedEnrollCourseId)?.title 
                        : "Selecionar Disciplina..."}
                    </span>
                    <BookOpen className="h-3.5 w-3.5 text-slate-400 group-hover:text-teal-600 transition-colors" />
                  </button>
                  {selectedEnrollCourseId && (
                    <div className="mt-2 p-2 bg-emerald-50 border border-emerald-100 rounded-lg animate-in fade-in slide-in-from-top-1 duration-200">
                      <div className="flex items-center gap-1.5">
                        <ShieldCheck className="h-3 w-3 text-emerald-600" />
                        <span className="text-[9px] font-bold text-emerald-800 uppercase tracking-tight">Professor Designado:</span>
                      </div>
                      <p className="text-[11px] font-black text-emerald-900 mt-0.5">
                        {courses.find(c => c.id === selectedEnrollCourseId)?.instructorName}
                      </p>
                    </div>
                  )}
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1 flex items-center justify-between">
                    <span>Senha de Acesso</span>
                    <span className="text-[8.5px] text-slate-400 font-normal normal-case">Padrão: 1234</span>
                  </label>
                  <div className="relative">
                    <input
                      type={showStudentPassword ? 'text' : 'password'}
                      placeholder="Senha do aluno (Ex: 1234)"
                      value={newStudentPassword}
                      onChange={(e) => setNewStudentPassword(e.target.value)}
                      className="w-full border border-slate-200 p-2 pr-10 text-xs rounded-lg text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-slate-400 font-mono"
                    />
                    <button
                      type="button"
                      onClick={() => setShowStudentPassword(!showStudentPassword)}
                      className="absolute right-2.5 top-2 text-slate-400 hover:text-indigo-600 transition-colors cursor-pointer"
                    >
                      {showStudentPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>

                <button
                  type="submit"
                  className="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2 px-3 rounded-lg text-xs transition-colors cursor-pointer flex items-center justify-center gap-1.5"
                >
                  <Plus className="h-3.5 w-3.5" />
                  <span>Matricular Aluno</span>
                </button>
              </form>
            </div>

            {/* Students list auditor */}
            <div className="lg:col-span-8 bg-white border border-slate-200 p-5 rounded-xl space-y-4">
              <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-100 pb-3">
                <div>
                  <h4 className="text-xs font-black text-slate-900 uppercase tracking-wider">
                    Alunos Registrados ({mockStudents.length})
                  </h4>
                  <p className="text-[10px] text-slate-500 mt-0.5">Frequência letiva, certificados homologados e médias de testes.</p>
                </div>

                <div className="relative w-full sm:w-56 overflow-hidden">
                  <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                  <input
                    type="text"
                    placeholder="Buscar aluno..."
                    value={studentSearchQuery}
                    onChange={(e) => setStudentSearchQuery(e.target.value)}
                    className="w-full bg-slate-50 border border-slate-200 pl-8 pr-3 py-1.5 text-[11px] rounded-lg text-slate-700 focus:outline-hidden focus:ring-1 focus:ring-slate-400"
                  />
                </div>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse text-xs">
                  <thead>
                    <tr className="border-b border-slate-150 text-[10px] font-bold text-slate-500 uppercase bg-slate-50/65">
                      <th className="p-3">Estudante</th>
                      <th className="p-3">Disciplinas / Tutores</th>
                      <th className="p-3">Frequência Letiva</th>
                      <th className="p-3">Status Certificados</th>
                      <th className="p-3">Quiz</th>
                      <th className="p-3 text-right">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {mockStudents
                      .filter(st => {
                        if (!studentSearchQuery) return true;
                        return st.name.toLowerCase().includes(studentSearchQuery.toLowerCase());
                      })
                      .map((st) => {
                        const studentOverallAttendance = calculateStudentOverallAttendance(st.name);
                        const stuSubmissions = quizSubmissions.filter(sub => sub.studentName === st.name);
                        const stuCertificates = certificates.filter(c => c.studentName === st.name);
                        
                        const activePass = st.password || localStorage.getItem(`ava_active_password_${st.name}`) || '1234';
                        return (
                          <tr key={st.email} className="border-b border-slate-100 hover:bg-slate-50/40 transition-colors">
                            <td className="p-3">
                              <strong className="text-slate-905 block font-bold">{st.name}</strong>
                              <span className="text-[10px] text-slate-400 font-mono block mt-0.5">{st.email}</span>
                              <div className="flex items-center gap-1.5 mt-1 text-[9px]">
                                <span className="text-slate-400 font-mono flex items-center gap-0.5">
                                  <Key className="h-2.5 w-2.5" /> Senha ativa:
                                </span>
                                <span className="font-mono bg-slate-50 hover:bg-slate-150 transition-colors text-slate-700 px-1.5 py-0.5 rounded select-all font-semibold" title="Clique para copiar">
                                  {activePass}
                                </span>
                              </div>
                              {(() => {
                                const enrollRecord = studentEnrollments?.[st.name];
                                const isPenalized = enrollRecord?.dropOutPenaltyUntil && new Date(enrollRecord.dropOutPenaltyUntil).getTime() > Date.now();
                                if (isPenalized) {
                                  return (
                                    <div className="mt-1.5 flex items-center gap-1.5">
                                      <span className="inline-block px-1.8 py-0.4 rounded-md bg-rose-50 text-rose-700 text-[8.5px] font-black uppercase tracking-wider border border-rose-100 animate-pulse">
                                        ⚠️ Inadimplente (Multa)
                                      </span>
                                    </div>
                                  );
                                }
                                return null;
                              })()}
                            </td>
                            <td className="p-3">
                              {(() => {
                                const myAdmissions = admissionRequests.filter(r => r.studentName === st.name && r.status === 'approved');
                                if (myAdmissions.length === 0) return <span className="text-[10px] text-slate-400 italic">Sem matrícula ativa</span>;
                                
                                return (
                                  <div className="flex flex-col gap-1.5">
                                    {myAdmissions.map(adm => {
                                      const course = courses.find(c => c.id === adm.courseId);
                                      return (
                                        <div key={adm.id} className="flex flex-col gap-0.5">
                                          <span className="text-[10px] font-bold text-slate-700 leading-tight">{course?.title || 'Curso Removido'}</span>
                                          <div className="flex items-center gap-1">
                                            <div className="h-1.5 w-1.5 bg-teal-500 rounded-full" />
                                            <span className="text-[9px] text-slate-500 font-semibold uppercase tracking-tighter">Tutor: {course?.instructorName || 'N/A'}</span>
                                          </div>
                                        </div>
                                      );
                                    })}
                                  </div>
                                );
                              })()}
                            </td>
                            <td className="p-3">
                              <div className="flex items-center gap-2">
                                <span className={`font-mono font-black ${studentOverallAttendance >= attendanceBarrier ? 'text-emerald-600' : 'text-amber-600'}`}>
                                  {studentOverallAttendance}%
                                </span>
                                <span className={`text-[8px] font-extrabold px-1.5 py-0.2 rounded uppercase ${
                                  studentOverallAttendance >= attendanceBarrier ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'
                                }`}>
                                  {studentOverallAttendance >= attendanceBarrier ? 'Frequente' : 'Crítico'}
                                </span>
                              </div>
                            </td>
                            <td className="p-3">
                              {stuCertificates.length === 0 ? (
                                <span className="text-[10px] text-slate-400 font-semibold italic">Nenhum</span>
                              ) : (
                                <div className="flex flex-col gap-1 items-start">
                                  {stuCertificates.map(c => (
                                    <span key={c.id} className="text-[9px] bg-sky-50 text-sky-700 border border-sky-100 px-1.5 py-0.5 rounded font-mono font-semibold" title={c.verificationHash}>
                                      ✓ {c.courseTitle.substring(0, 10)}...
                                    </span>
                                  ))}
                                </div>
                              )}
                            </td>
                            <td className="p-3">
                              {stuSubmissions.length === 0 ? (
                                <span className="text-[10px] text-slate-400 italic">Pendente</span>
                              ) : (
                                <div className="flex flex-col gap-0.5">
                                  {stuSubmissions.map(sub => (
                                    <span key={sub.id} className={`text-[9.5px] font-mono leading-none ${sub.passed ? 'text-emerald-600 font-bold' : 'text-rose-500'}`}>
                                      {sub.scorePercent}%
                                    </span>
                                  ))}
                                </div>
                              )}
                            </td>
                            <td className="p-3 text-right flex items-center justify-end gap-1.5">
                              {(() => {
                                const enrollRecord = studentEnrollments?.[st.name];
                                const isPenalized = enrollRecord?.dropOutPenaltyUntil && new Date(enrollRecord.dropOutPenaltyUntil).getTime() > Date.now();
                                if (isPenalized) {
                                  return (
                                    <button
                                      onClick={() => {
                                        clearStudentPenalty(st.name);
                                        showToast(`A restrição de inadimplência de ${st.name} foi removida.`);
                                        speakText(`A restrição de inadimplência de ${st.name} foi revogada com sucesso.`);
                                      }}
                                      className="rounded bg-sky-50 border border-sky-200 text-sky-700 hover:bg-sky-100 font-extrabold text-[9px] uppercase px-2.5 py-1.5 transition-all cursor-pointer"
                                      title="Revogar status de Inadimplente manualmente"
                                    >
                                      Perdoar Multa
                                    </button>
                                  );
                                }
                                return null;
                              })()}
                              <button
                                onClick={() => {
                                  deleteStudent(st.name);
                                  showToast(`Estudante e credenciais de ${st.name} foram removidos.`);
                                }}
                                className="text-rose-600 hover:text-rose-700 hover:bg-rose-50 p-1.5 rounded transition-all cursor-pointer inline-flex items-center"
                                title="Remover Estudante"
                              >
                                <Trash2 className="h-4 w-4" />
                              </button>
                            </td>
                          </tr>
                        );
                      })}
                  </tbody>
                </table>
              </div>
            </div>

          </div>

        </div>
      )}

      {/* Course Picker Modal */}
      {showCoursePickerModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200">
            <header className="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <div className="flex items-center gap-2">
                <BookOpen className="h-4 w-4 text-teal-600" />
                <h3 className="text-sm font-black text-slate-900 uppercase tracking-tight">Vincular Disciplina</h3>
              </div>
              <button 
                onClick={() => setShowCoursePickerModal(false)}
                className="p-1 hover:bg-slate-200 rounded-full transition-colors cursor-pointer text-slate-400"
              >
                <X className="h-4 w-4" />
              </button>
            </header>

            <div className="p-4 space-y-3 max-h-[60vh] overflow-y-auto">
              <p className="text-[10px] text-slate-500 font-medium leading-relaxed mb-4">
                Selecione abaixo a disciplina na qual o aluno será matriculado. O professor correspondente será vinculado automaticamente para acompanhamento pedagógico.
              </p>

              {courses.map(course => (
                <button
                  key={course.id}
                  onClick={() => {
                    setSelectedEnrollCourseId(course.id);
                    setShowCoursePickerModal(false);
                  }}
                  className={`w-full text-left p-3 rounded-xl border transition-all flex items-center justify-between group ${
                    selectedEnrollCourseId === course.id 
                    ? 'border-teal-500 bg-teal-50/50 ring-1 ring-teal-500/20' 
                    : 'border-slate-100 bg-slate-50/30 hover:border-slate-200 hover:bg-slate-50'
                  }`}
                >
                  <div className="space-y-1">
                    <p className="text-xs font-bold text-slate-900 group-hover:text-teal-700 transition-colors">{course.title}</p>
                    <div className="flex items-center gap-1.5">
                      <span className="text-[9px] bg-white border border-slate-200 px-1.5 py-0.5 rounded text-slate-500 font-mono">
                        {course.category}
                      </span>
                      <span className="text-[9px] text-slate-400">
                        Instrutor: <span className="font-bold text-slate-600 underline decoration-slate-300">{course.instructorName}</span>
                      </span>
                    </div>
                  </div>
                  {selectedEnrollCourseId === course.id && <Check className="h-4 w-4 text-teal-600" />}
                </button>
              ))}
            </div>

            <footer className="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
              <button
                onClick={() => setShowCoursePickerModal(false)}
                className="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-900 transition-colors uppercase tracking-widest cursor-pointer"
              >
                Cancelar
              </button>
            </footer>
          </div>
        </div>
      )}

      {activeTab === 'requests' && (
        <div className="space-y-6 text-left animate-in fade-in duration-300">
          
          {/* Header instructions card */}
          <div className="bg-white border border-slate-200 rounded-xl p-5 space-y-2">
            <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <FileCheck className="h-4.5 w-4.5 text-teal-600" />
              <span>Central de Requerimentos Curriculares e Emissões Eletrônicas</span>
            </h3>
            <p className="text-xs text-slate-500 leading-relaxed">
              Analise, autorize ou indefira as solicitações de documentos protocoladas por alunos. Você também pode emitir vias avulsas diretamente utilizando o emissor rápido ao lado.
            </p>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            {/* Direct Issuance fast tool panel */}
            <div className="lg:col-span-4 bg-white border border-slate-200 p-5 rounded-xl h-fit space-y-4">
              <div>
                <h4 className="text-xs font-black text-slate-900 uppercase tracking-wider">Emissor Manual Rápido</h4>
                <p className="text-[11px] text-slate-400 mt-0.5">Emita documentos oficiais avulsos para qualquer discente sem necessidade de pedido prévio.</p>
              </div>

              <form onSubmit={(e) => {
                e.preventDefault();
                const form = e.currentTarget;
                const studentName = (form.elements.namedItem('directStudent') as HTMLSelectElement).value;
                const docType = (form.elements.namedItem('directType') as HTMLSelectElement).value as any;
                const courseTitle = (form.elements.namedItem('directCourse') as HTMLSelectElement).value;

                if (studentName && docType) {
                  setActiveDocViewer({
                    studentName,
                    type: docType,
                    courseTitle: courseTitle || undefined
                  });
                  showToast(`Documento ${docType.toUpperCase()} emitido com sucesso para ${studentName}!`);
                }
              }} className="space-y-3">
                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Escolher Aluno</label>
                  <select name="directStudent" required className="w-full border border-slate-205 p-2 text-xs rounded-lg text-slate-800 bg-white focus:outline-hidden">
                    {mockStudents.map((st) => (
                      <option key={st.email} value={st.name}>{st.name} ({st.email})</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tipo de Documento</label>
                  <select name="directType" required className="w-full border border-slate-205 p-2 text-xs rounded-lg text-slate-800 bg-white focus:outline-hidden">
                    <option value="historico">Histórico Curricular Escolar</option>
                    <option value="certificado">Certificado Oficial</option>
                    <option value="matricula">Declaração de Matrícula Regular</option>
                  </select>
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Vincular a Qual Curso? (Opcional)</label>
                  <select name="directCourse" className="w-full border border-slate-205 p-2 text-xs rounded-lg text-slate-800 bg-white focus:outline-hidden">
                    <option value="">Geral / Integral</option>
                    {courses.map(c => (
                      <option key={c.id} value={c.title}>{c.title}</option>
                    ))}
                  </select>
                </div>

                <button
                  type="submit"
                  className="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2 px-3 rounded-lg text-xs transition-colors cursor-pointer flex items-center justify-center gap-1"
                >
                  <Download className="h-3 w-3" />
                  <span>Gerar e Validar Via Oficial</span>
                </button>
              </form>
            </div>

            {/* List of Incoming Requests */}
            <div className="lg:col-span-8 bg-white border border-slate-200 p-5 rounded-xl space-y-4">
              <h4 className="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
                Requerimentos Registrados por Alunos ({academicRequests?.length || 0})
              </h4>

              {!academicRequests || academicRequests.length === 0 ? (
                <div className="p-8 text-center bg-slate-50 border border-slate-200 border-dashed rounded-xl text-xs text-slate-500">
                  Nenhum requerimento curricular cadastrado na memória local. Adicione solicitações através de contas de alunos para visualizá-los e homologá-los.
                </div>
              ) : (
                <div className="space-y-4">
                  {academicRequests.map((req) => (
                    <div key={req.id} className="p-4 border border-slate-205 rounded-xl bg-slate-50/25 flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div className="space-y-1.5 text-left text-xs leading-relaxed max-w-lg">
                        <div className="flex flex-wrap items-center gap-2">
                          <strong className="font-extrabold text-sm text-slate-900 leading-none">{req.studentName}</strong>
                          <span className={`text-[8.5px] font-black uppercase px-2 py-0.5 rounded-full ${
                            req.status === 'pending' ? 'bg-amber-50 text-amber-600 border border-amber-200' :
                            req.status === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                            'bg-rose-50 text-rose-600 border border-rose-200'
                          }`}>
                            {req.status === 'pending' && 'Aguardando Parecer'}
                            {req.status === 'approved' && 'Deferido / Homologado'}
                            {req.status === 'rejected' && 'Indeferido'}
                          </span>
                        </div>
                        <div className="text-[11px] text-slate-700 flex items-center gap-1.5">
                          <span>Tipo Solicitado:</span>
                          <span className="font-extrabold text-slate-800 uppercase flex items-center gap-1">
                            {req.type === 'certificado' && (
                              <>
                                <Award className="h-3.5 w-3.5 text-teal-600" />
                                <span>Certificado</span>
                              </>
                            )}
                            {req.type === 'historico' && (
                              <>
                                <FileText className="h-3.5 w-3.5 text-blue-600" />
                                <span>Histórico Escolar</span>
                              </>
                            )}
                            {req.type === 'matricula' && (
                              <>
                                <BookOpen className="h-3.5 w-3.5 text-amber-600" />
                                <span>Liberação / Matrícula</span>
                              </>
                            )}
                            {req.type === 'outro' && (
                              <>
                                <Layers className="h-3.5 w-3.5 text-slate-500" />
                                <span>Outro Pedido</span>
                              </>
                            )}
                          </span>
                        </div>
                        {req.courseTitle && (
                          <div className="text-[10px] text-slate-600 font-medium">
                            Curso Vinculado: <span className="font-semibold text-slate-800">{req.courseTitle}</span>
                          </div>
                        )}
                        <p className="text-[10.5px] text-slate-500 italic">"Justificativa: {req.description}"</p>
                        <div className="text-[9.5px] text-slate-400 font-mono">Data de Abertura: {req.submittedAt}</div>
                      </div>

                      {/* Admin action controls */}
                      <div className="flex flex-col sm:flex-row md:flex-col lg:flex-row gap-1.5 shrink-0 self-end md:self-center">
                        {req.status === 'pending' && (
                          <>
                            <button
                              onClick={() => {
                                updateRequestStatus(req.id, 'approved');
                                showToast(`Solicitação de ${req.studentName} DEFERIDA com sucesso!`);
                              }}
                              className="bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold px-2.5 py-1.5 rounded-lg text-[10px] transition-colors flex items-center gap-1 cursor-pointer whitespace-nowrap"
                            >
                              <Check className="h-3.5 w-3.5" />
                              <span>Deferir</span>
                            </button>
                            <button
                              onClick={() => {
                                updateRequestStatus(req.id, 'rejected');
                                showToast(`Solicitação de ${req.studentName} INDEFERIDA.`);
                              }}
                              className="bg-rose-600 hover:bg-rose-500 text-white font-extrabold px-2.5 py-1.5 rounded-lg text-[10px] transition-colors flex items-center gap-1 cursor-pointer whitespace-nowrap"
                            >
                              <X className="h-3.5 w-3.5" />
                              <span>Indeferir</span>
                            </button>
                          </>
                        )}
                        
                        {(req.status === 'approved' || req.status === 'pending') && (
                          <button
                            onClick={() => {
                              setActiveDocViewer({
                                studentName: req.studentName,
                                type: req.type === 'outro' ? 'matricula' : req.type,
                                courseTitle: req.courseTitle
                              });
                            }}
                            className="bg-slate-900 hover:bg-slate-800 text-white font-extrabold px-2.5 py-1.5 rounded-lg text-[10px] transition-colors flex items-center gap-1 cursor-pointer whitespace-nowrap"
                          >
                            <FileText className="h-3.5 w-3.5" />
                            <span>Visualizar / Homologar</span>
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

          </div>

        </div>
      )}


      {activeTab === 'settings' && (
        <div className="bg-white border border-slate-200 rounded-xl p-5 text-left space-y-6 settings-tab-content">
          <div>
            <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 flex items-center gap-1.5">
              <Settings className="h-4 w-4 text-slate-505" />
              <span>Configurações Teóricas e Parâmetros Letivos</span>
            </h3>
            <p className="text-[11px] text-slate-500 mt-1">
              Gerencie a política de emissão automática de atestados do AVA.
            </p>
          </div>

          <div className="max-w-md space-y-4">
            <div className="space-y-2">
              <label className="text-xs font-bold text-slate-600 block">Exigência de Presença Síncrona/Assíncrona para Emissão de Certificados</label>
              <div className="flex items-center gap-3">
                <input
                  type="range"
                  min="50"
                  max="90"
                  step="5"
                  value={attendanceBarrier}
                  onChange={(e) => setAttendanceBarrier(Number(e.target.value))}
                  className="w-full accent-slate-800"
                />
                <span className="font-mono font-black text-sm text-slate-800 shrink-0 bg-slate-100 px-2 py-1 rounded">
                  {attendanceBarrier}%
                </span>
              </div>
              <p className="text-[10px] text-slate-400 leading-normal">
                Modificar esta régua atualiza dinamicamente o gatilho automático que concede as certificações virtuais assinadas criptograficamente aos alunos (Global Attendance Threshold Customizer).
              </p>
            </div>

            <div className="pt-4 border-t border-slate-100 flex flex-col gap-4">
              <h4 className="text-[11px] font-bold text-slate-900 uppercase tracking-wider mb-1">Permissões e Funcionalidades Globais</h4>
              
              <label className="flex items-center gap-3 cursor-pointer">
                <div className="relative">
                  <input type="checkbox" className="sr-only" checked={systemSettings.allowDirectMessages} onChange={() => updateSystemSettings({ allowDirectMessages: !systemSettings.allowDirectMessages })} />
                  <div className={`block w-8 h-4 rounded-full transition-colors ${systemSettings.allowDirectMessages ? 'bg-indigo-500' : 'bg-slate-300'}`}></div>
                  <div className={`dot absolute left-1 top-1 bg-white w-2 h-2 rounded-full transition-transform ${systemSettings.allowDirectMessages ? 'translate-x-4' : ''}`}></div>
                </div>
                <div>
                  <span className="text-xs font-bold text-slate-800 tracking-tight">Habilitar Mensagens Diretas (DMs)</span>
                  <p className="text-[9px] text-slate-400">Permitir que alunos enviem mensagens diretas para professores e administradores.</p>
                </div>
              </label>

              <label className="flex items-center gap-3 cursor-pointer">
                <div className="relative">
                  <input type="checkbox" className="sr-only" checked={systemSettings.allowGlobalChat} onChange={() => updateSystemSettings({ allowGlobalChat: !systemSettings.allowGlobalChat })} />
                  <div className={`block w-8 h-4 rounded-full transition-colors ${systemSettings.allowGlobalChat ? 'bg-indigo-500' : 'bg-slate-300'}`}></div>
                  <div className={`dot absolute left-1 top-1 bg-white w-2 h-2 rounded-full transition-transform ${systemSettings.allowGlobalChat ? 'translate-x-4' : ''}`}></div>
                </div>
                <div>
                  <span className="text-xs font-bold text-slate-800 tracking-tight">Habilitar Chat Global</span>
                  <p className="text-[9px] text-slate-400">Exibe uma sala de bate-papo global onde todos da instituição podem conversar livremente.</p>
                </div>
              </label>

              <label className="flex items-center gap-3 cursor-pointer">
                <div className="relative">
                  <input type="checkbox" className="sr-only" checked={systemSettings.openEnrollment} onChange={() => updateSystemSettings({ openEnrollment: !systemSettings.openEnrollment })} />
                  <div className={`block w-8 h-4 rounded-full transition-colors ${systemSettings.openEnrollment ? 'bg-indigo-500' : 'bg-slate-300'}`}></div>
                  <div className={`dot absolute left-1 top-1 bg-white w-2 h-2 rounded-full transition-transform ${systemSettings.openEnrollment ? 'translate-x-4' : ''}`}></div>
                </div>
                <div>
                  <span className="text-xs font-bold text-slate-800 tracking-tight">Inscrições Abertas p/ Novos Ciclos</span>
                  <p className="text-[9px] text-slate-400">Quando ativado, os alunos podem buscar e se matricular em novos cursos no catálogo.</p>
                </div>
              </label>

              <label className="flex items-center gap-3 cursor-pointer">
                <div className="relative">
                  <input type="checkbox" className="sr-only" checked={systemSettings.autoCertify} onChange={() => updateSystemSettings({ autoCertify: !systemSettings.autoCertify })} />
                  <div className={`block w-8 h-4 rounded-full transition-colors ${systemSettings.autoCertify ? 'bg-indigo-500' : 'bg-slate-300'}`}></div>
                  <div className={`dot absolute left-1 top-1 bg-white w-2 h-2 rounded-full transition-transform ${systemSettings.autoCertify ? 'translate-x-4' : ''}`}></div>
                </div>
                <div>
                  <span className="text-xs font-bold text-slate-800 tracking-tight">Emissão Automática de Certificados</span>
                  <p className="text-[9px] text-slate-400">Permitir que o Document Viewer emita os certificados automaticamente ao atingir os requisitos.</p>
                </div>
              </label>

              <label className="flex items-center gap-3 cursor-pointer">
                <div className="relative">
                  <input type="checkbox" className="sr-only" checked={systemSettings.liveClassRecording} onChange={() => updateSystemSettings({ liveClassRecording: !systemSettings.liveClassRecording })} />
                  <div className={`block w-8 h-4 rounded-full transition-colors ${systemSettings.liveClassRecording ? 'bg-indigo-500' : 'bg-slate-300'}`}></div>
                  <div className={`dot absolute left-1 top-1 bg-white w-2 h-2 rounded-full transition-transform ${systemSettings.liveClassRecording ? 'translate-x-4' : ''}`}></div>
                </div>
                <div>
                  <span className="text-xs font-bold text-slate-800 tracking-tight">Gravação Automática de Aulas Ao Vivo</span>
                  <p className="text-[9px] text-slate-400">Gravar automaticamente os encontros síncronos e arquivar no curso.</p>
                </div>
              </label>

              <div className="pt-2">
                <label className="text-xs font-bold text-slate-800 tracking-tight block mb-1">Duração do Arquivamento Automático (Cursos)</label>
                <select 
                  value={systemSettings.autoArchiveDuration}
                  onChange={(e) => updateSystemSettings({ autoArchiveDuration: e.target.value })}
                  className="w-full border border-slate-200 p-2 text-xs rounded-lg text-slate-800 bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                >
                  <option value="nunca">Nunca arquivar (Manual)</option>
                  <option value="1_mes">1 mês após inatividade</option>
                  <option value="3_meses">3 meses após inatividade</option>
                  <option value="6_meses">6 meses após inatividade</option>
                  <option value="12_meses">1 ano após inatividade</option>
                </select>
                <p className="text-[9px] text-slate-400 mt-1">Defina quando turmas inativas devem ser arquivadas do catálogo.</p>
              </div>
            </div>

            <div className="bg-slate-50/70 rounded-xl p-4 border border-slate-200 border-dashed text-xs text-slate-500 mt-2">
              <strong className="text-slate-800 block mb-1">Nota da Assessoria de T.I:</strong>
              <p className="leading-relaxed">Novas rotas letivas criadas tanto pelo Administrador quanto pelos Professores cadastrados são adicionadas em tempo real em bancos na memória do navegador.</p>
            </div>

            <div className="pt-6 mt-2 border-t border-slate-100">
              <button
                onClick={() => showToast('Configurações salvas com sucesso!')}
                className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-xl text-xs transition-colors flex items-center justify-center gap-2 shadow-sm uppercase tracking-wide"
              >
                <Save className="h-4 w-4" />
                <span>Salvar Configurações</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Official Academic Document Modal PDF Viewer mockup */}
      {activeDocViewer && (
        <div 
          className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 overflow-y-auto flex items-start justify-center p-4 cursor-default"
          onClick={(e) => {
            if (e.target === e.currentTarget) setActiveDocViewer(null);
          }}
        >
          <style>{`
            @media print {
              body * {
                visibility: hidden !important;
              }
              #printable-doc, #printable-doc * {
                visibility: visible !important;
              }
              #printable-doc {
                position: fixed !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                height: auto !important;
                max-height: 100% !important;
                padding: 2.5rem !important;
                background: white !important;
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                border-radius: 0.5rem !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
              }
            }
          `}</style>

          <div className="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-2xl overflow-hidden animate-in zoom-in-95 duration-200 my-8">
            
            {/* Modal Top Controls */}
            <div className="bg-slate-900 text-white px-5 py-3.5 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Shield className="h-4.5 w-4.5 text-amber-500" />
                <div>
                  <span className="text-xs font-black tracking-wider uppercase font-mono block">Emissor de Documentos Oficiais AVA</span>
                  <span className="text-[9px] text-slate-400 block leading-none">Baixe selecionando "Salvar como PDF" no prompt.</span>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button 
                  onClick={() => window.print()}
                  className="text-[10px] bg-teal-600 hover:bg-teal-500 font-extrabold text-white px-2.5 py-1.5 rounded-lg flex items-center gap-1 cursor-pointer transition-colors shadow-xs"
                >
                  <Printer className="h-3 w-3 text-white" />
                  <span>Imprimir / PDF</span>
                </button>
                <button 
                  onClick={() => {
                    showToast(`Salvando ${activeDocViewer.type} de ${activeDocViewer.studentName} no Computador...`);
                  }}
                  className="text-[10px] bg-slate-800 hover:bg-slate-700 font-extrabold text-white px-2.5 py-1.5 rounded-lg flex items-center gap-1 cursor-pointer transition-colors"
                >
                  <Download className="h-3 w-3 text-teal-400" />
                  <span>Descarregar</span>
                </button>
                <button 
                  onClick={() => setActiveDocViewer(null)}
                  className="text-slate-400 hover:text-white transition-colors cursor-pointer"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
            </div>

            {/* Dynamic Printable Area */}
            <div className="p-8 bg-white text-slate-900 overflow-y-auto max-h-[70vh]" id="printable-doc">
              <div className="border border-slate-200 p-8 rounded-xl space-y-6 text-left relative overflow-hidden bg-slate-50/10">
                
                {/* Watermark design background */}
                <div className="absolute inset-x-0 top-1/3 text-center pointer-events-none opacity-[0.03] flex flex-col items-center justify-center">
                  <ShieldCheck className="h-48 w-48 text-slate-900" />
                </div>

                {/* Official Header */}
                <div className="border-b border-double border-slate-300 pb-5 text-center space-y-2">
                  <span className="text-[10px] bg-slate-900 text-white px-2.5 py-0.5 rounded font-mono font-bold uppercase tracking-widest">Via Homologada de Autenticidade</span>
                  <h3 className="text-base font-black uppercase text-slate-950 tracking-tight leading-none mt-1">Unidade de Ensino Superior AVA</h3>
                  <p className="text-[9px] text-slate-550 font-bold uppercase tracking-wider mt-1.5">Secretaria de Registros e Gestão Pedagógica Digital</p>
                </div>

                {/* Document Specific Content */}
                {activeDocViewer.type === 'historico' ? (
                  <div className="space-y-4">
                    <div className="text-center space-y-1">
                      <h4 className="font-extrabold text-sm text-slate-900 uppercase tracking-tight">Histórico Escolar Acadêmico Integral</h4>
                      <p className="text-[9px] text-slate-400 font-mono">Protocolo de Consulta: HIST-{Date.now().toString().substring(6)}</p>
                    </div>

                    {/* Student Info block */}
                    <div className="grid grid-cols-2 gap-4 bg-white border border-slate-150 p-3 rounded-lg text-[11px] leading-relaxed">
                      <div>
                        <span className="text-slate-400 block font-medium">Nome do Aluno(a):</span>
                        <strong className="text-slate-900 text-xs font-bold">{activeDocViewer.studentName}</strong>
                      </div>
                      <div>
                        <span className="text-slate-400 block font-medium">Situação Acadêmica:</span>
                        <strong className="text-emerald-700 font-extrabold flex items-center gap-1 mt-0.5">
                          <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 inline-block animate-pulse" /> Regularmente Matriculado
                        </strong>
                      </div>
                    </div>

                    {/* Grades and attendance table */}
                    <div className="space-y-2 pt-2">
                      <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Disciplinas Cursadas e Frequência</span>
                      <div className="border border-slate-200 rounded-lg overflow-hidden bg-white">
                        <table className="w-full text-left border-collapse text-[11px]">
                          <thead>
                            <tr className="border-b border-slate-200 bg-slate-50 text-[9px] font-bold text-slate-500 uppercase">
                              <th className="p-2.5">Trilha / Curso</th>
                              <th className="p-2.5">Professor Adjunto</th>
                              <th className="p-2.5 text-center">Frequência</th>
                              <th className="p-2.5 text-right">Média Quizzes</th>
                            </tr>
                          </thead>
                          <tbody>
                            {courses.map(course => {
                              // calculate attendance for studentName
                              let studentAttendance = 0;
                              const userProg = progress.find(p => p.courseId === course.id);
                              const totalActs = course.lessons.length + course.liveSessions.length;
                              if (totalActs > 0 && userProg) {
                                const completed = userProg.completedLessons.length + userProg.attendedLiveSessions.length;
                                studentAttendance = Math.round((completed / totalActs) * 100);
                              }

                              // average quiz score
                              const subs = quizSubmissions.filter(s => s.studentName === activeDocViewer.studentName && s.courseId === course.id);
                              const quizScore = subs.length > 0 ? `${subs[0].scorePercent}%` : 'Pendente';

                              return (
                                <tr key={course.id} className="border-b border-slate-100 hover:bg-slate-50/30">
                                  <td className="p-2.5">
                                    <span className="font-bold text-slate-900 block">{course.title}</span>
                                  </td>
                                  <td className="p-2.5 text-slate-600">{course.instructorName}</td>
                                  <td className="p-2.5 text-center font-mono font-bold">
                                    {studentAttendance}%
                                  </td>
                                  <td className="p-2.5 text-right font-mono text-slate-800">
                                    {quizScore}
                                  </td>
                                </tr>
                              );
                            })}
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <div className="text-[10px] text-slate-500 bg-slate-50/80 border border-slate-150 p-2.5 rounded-lg leading-relaxed mt-4">
                      * Este histórico reflete integralmente os registros eletrônicos armazenados na Central AVA em {new Date().toLocaleDateString('pt-BR')}. A presença de 70% ou mais outorga a emissão eletrônica de certificados de habilidade prática.
                    </div>
                  </div>
                ) : activeDocViewer.type === 'certificado' ? (
                  <div className="space-y-6 text-center py-4">
                    <div className="space-y-1">
                      <span className="text-[9px] text-slate-400 font-bold uppercase tracking-widest block">Certificação Profissional</span>
                      <h4 className="font-black text-xl italic text-slate-950 antialiased font-serif">Diploma de Conclusão Técnica</h4>
                    </div>

                    <div className="text-slate-700 text-[13px] leading-relaxed max-w-md mx-auto space-y-4">
                      <p>
                        Certificamos de forma solene para os devidos fins legais, de competências e de complementação acadêmica que o discente
                      </p>
                      <p className="text-lg font-black text-slate-905 border-b border-slate-200 py-1.5 w-fit mx-auto px-4 uppercase tracking-normal">
                        {activeDocViewer.studentName}
                      </p>
                      <p className="text-[11px] leading-normal text-slate-450">
                        concluiu com êxito os requisitos teóricos, testes práticos e obteve aproveitamento curricular superior a <strong className="font-semibold text-slate-850">70% de presença letiva</strong> nas aulas, assessorias síncronas e atividades do curso didático de:
                      </p>
                      <p className="text-sm font-black text-teal-900 uppercase">
                        {activeDocViewer.courseTitle || courses[0]?.title || 'Formação de Profissionais Ativos'}
                      </p>
                    </div>

                    <div className="flex justify-between items-end border-t border-slate-200 pt-8 mt-6">
                      <div className="text-left space-y-1">
                        <span className="text-[9px] text-slate-405 block font-mono">Registro Criptográfico Único:</span>
                        <strong className="text-[10px] text-slate-800 font-mono block uppercase">
                          AVA-CERT-{Date.now().toString().substring(5)}
                        </strong>
                      </div>
                      <div className="text-right flex flex-col items-center">
                        <div className="w-16 h-0.5 bg-slate-300 mb-1" />
                        <span className="text-[9px] text-slate-400 block">Chave Securitária AVA</span>
                        <span className="text-[8px] text-slate-300 block">Homologado e Gravado</span>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="space-y-5">
                    <div className="text-center space-y-1">
                      <h4 className="font-extrabold text-sm text-slate-900 uppercase tracking-tight">Atestado de Matrícula e Frequência Ativa</h4>
                      <p className="text-[9px] text-slate-400 font-mono">Protocolo de Expedição: ADM-{Date.now().toString().substring(7)}</p>
                    </div>

                    <p className="text-[11px] text-slate-705 leading-relaxed text-justify indent-8 pt-2">
                      Declaramos, para os devidos fins de direito e comprovação institucional acadêmica, que o estudante <strong className="font-bold text-slate-950 uppercase">{activeDocViewer.studentName}</strong> encontra-se regularmente cadastrado e ativamente matriculado nos sistemas letivos desta Unidade de Ensino Superior, participando da grade didática atual no ano letivo corrente de 2026.
                    </p>

                    <p className="text-[11px] text-slate-705 leading-normal text-justify">
                      O discente mantém status regular, frequentando as conferências de mentoria de forma remota, e submetendo-se a baterias de testes didáticos sob supervisão dos professores cadastrados.
                    </p>

                    <div className="grid grid-cols-2 gap-4 bg-slate-50 p-3 rounded-lg border border-slate-150 text-[10px] text-slate-500 leading-normal mt-4">
                      <div>
                        <span className="block font-semibold">Data de Expedição:</span>
                        <span>{new Date().toLocaleDateString('pt-BR')} às {new Date().toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'})}</span>
                      </div>
                      <div>
                        <span className="block font-semibold">Garantia Digital:</span>
                        <span>Assinado eletronicamente sob código ID: SEC-{Date.now().toString().substring(8)}</span>
                      </div>
                    </div>

                    <div className="text-center pt-8">
                      <span className="text-[9px] text-slate-300 block">________________________________________________</span>
                      <span className="text-[10px] font-bold text-slate-700 block mt-1">Diretoria de Registros Escolares Secundários</span>
                      <span className="text-[8.5px] text-slate-400 block">Portal do AVA LMS Institucional</span>
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* Modal Footer */}
            <div className="bg-slate-50 border-t border-slate-200 px-5 py-3 flex justify-end">
              <button 
                onClick={() => setActiveDocViewer(null)}
                className="rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 px-4 py-1.5 text-xs font-bold transition-all cursor-pointer"
              >
                Fechar Visualizador
              </button>
            </div>

          </div>
        </div>
      )}

      {/* SUCCESS TOAST NOTIFICATE */}
      {toastMsg && (
        <div className="fixed bottom-6 right-6 z-50 rounded-lg bg-slate-900 border border-slate-700 text-slate-100 px-5 py-3 shadow-2xl flex items-center gap-3 animate-in fade-in slide-in-from-bottom-2 duration-300 max-w-sm">
          <span className="h-2 w-2 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
          <p className="text-xs font-bold leading-normal text-left">{toastMsg}</p>
        </div>
      )}

    </div>
  );
}
