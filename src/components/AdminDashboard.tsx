/**
 * @license
 * SPDX-License-Identifier: Apache-2.5
 */

import React, { useState } from 'react';
import { useLMS } from '../context/LMSContext';
import { exportAllManagementBases, exportManagementBase, ManagementBase } from '../utils/managementExport';
import { downloadSubmissionFile } from '../utils/fileDownload';
import { courseMinAttendance } from '../config/constants';
import { isCourseExpired, StudentEnrollment } from '../types';
import { BackButton } from './BackButton';
import { 
  ShieldCheck, Users, User, BookOpen, Award, CheckSquare, Plus, ArrowLeft,
  Trash2, Lock, Settings, Activity, FileText, Search, Shield, Filter,
  FileCheck, Printer, Download, Check, X, Layers, Save,
  ArrowUpRight, ArrowDownRight, TrendingUp, Eye, EyeOff, Key,
  MoreVertical, Mail, AlertTriangle, UserCheck, RefreshCw, Unlock, 
  MessageSquare, CheckCircle2, XCircle, ExternalLink, ChevronDown, 
  SlidersHorizontal, Sparkles, Clock, AlertCircle, HelpCircle, Database
} from 'lucide-react';
import { 
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, 
  PieChart, Pie, Cell, LineChart, Line, AreaChart, Area 
} from 'recharts';
import { features } from '../config/features';

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
    addAcademicRequest,
    addAdmissionRequest,
    admissionRequests,
    categoriesList,
    addCategory,
    systemSettings,
    updateSystemSettings,
    studentEnrollments,
    clearStudentPenalty,
    practicalExercises,
    exerciseSubmissions,
    addPracticalExercise,
    updatePracticalExercise,
    deletePracticalExercise,
    gradeSubmission,
  } = useLMS();

  // List of registered student accounts for master academic academic progress tracking
  const mockStudents = studentsList;

  // Selected Section State: 'analytics' | 'professors' | 'courses' | 'students' | 'requests' | 'settings' | 'exercicios' | 'export_bi'
  const [activeTab, setActiveTab] = useState<'analytics' | 'professors' | 'courses' | 'students' | 'requests' | 'settings' | 'exercicios' | 'export_bi'>('analytics');
  const [selectedBiBase, setSelectedBiBase] = useState<'alunos' | 'cursos' | 'matriculas' | 'progresso' | 'certificados'>('alunos');

  // Exercise form states
  const [exCourseId, setExCourseId] = useState('');
  const [exTitle, setExTitle] = useState('');
  const [exDescription, setExDescription] = useState('');
  const [exInstructions, setExInstructions] = useState('');
  const [exMaxPoints, setExMaxPoints] = useState(100);
  const [exDueDate, setExDueDate] = useState('');
  const [editingExId, setEditingExId] = useState<string | null>(null);
  const [showExForm, setShowExForm] = useState(false);

  // Grading states
  const [gradingSubId, setGradingSubId] = useState<string | null>(null);
  const [gradeScore, setGradeScore] = useState(100);
  const [gradeFeedback, setGradeFeedback] = useState('');

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
  const [newStudentMunicipio, setNewStudentMunicipio] = useState('');
  const [newStudentUf, setNewStudentUf] = useState('');
  const [newStudentAreaInteresse, setNewStudentAreaInteresse] = useState('');
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
  // Guarda o ID do professor responsável (ADR 10) — o nome é resolvido para display.
  const [newCourseTeacherId, setNewCourseTeacherId] = useState(professorsList[0]?.id || '');
  const [newCourseDesc, setNewCourseDesc] = useState('');
  const [newCourseType, setNewCourseType] = useState<'fixo' | 'ao_vivo'>('fixo');
  const [newCourseHasChat, setNewCourseHasChat] = useState(true);
  const [newCourseExpiration, setNewCourseExpiration] = useState('');
  const [newCategoryName, setNewCategoryName] = useState('');
  
  // Changing course instructor state
  const [editingInstructorCourseId, setEditingInstructorCourseId] = useState<string | null>(null);
  const [tempInstructorName, setTempInstructorName] = useState('');
  const [expandedCourseStudentsId, setExpandedCourseStudentsId] = useState<string | null>(null);

  // Minimal Search states
  const [studentSearchQuery, setStudentSearchQuery] = useState('');
  const [courseSearchQuery, setCourseSearchQuery] = useState('');
  const [professorSearchQuery, setProfessorSearchQuery] = useState('');

  // Rich student management states
  const [studentOverrides, setStudentOverrides] = useState<Record<string, {
    statusMatricula?: 'Ativa' | 'Sem matrícula' | 'Trancada' | 'Concluída' | 'Cancelada';
    statusConta?: 'Ativa' | 'Bloqueada' | 'Aguardando confirmação';
    pendencias?: string[];
    responsavel?: string;
    progresso?: number;
    horasConcluidas?: number;
    horasTotais?: number;
    password?: string; // reset de senha do simulador (estado local de demonstração)
  }>>({});

  const [activeStudentProfile, setActiveStudentProfile] = useState<string | null>(null);
  
  // Filters for Students
  const [filterCourse, setFilterCourse] = useState('all');
  const [filterTurma, setFilterTurma] = useState('all');
  const [filterStatusMatricula, setFilterStatusMatricula] = useState('all');
  const [filterPendencia, setFilterPendencia] = useState('all');
  const [filterLastAccess, setFilterLastAccess] = useState('all');
  const [filterRisco, setFilterRisco] = useState('all');
  const [activeQuickFilter, setActiveQuickFilter] = useState('all');

  // Sorting
  const [sortBy, setSortBy] = useState<'name' | 'lastAccess' | 'progress' | 'pendency' | 'statusMatricula' | 'risk'>('name');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');

  // Mini interactions
  const [activeStudentMenu, setActiveStudentMenu] = useState<string | null>(null);
  const [resetPassInfo, setResetPassInfo] = useState<{ name: string; email: string } | null>(null);
  const [sendMessageInfo, setSendMessageInfo] = useState<{ name: string; email: string } | null>(null);

  // Customizable certificate attendance barrier state simulation (defaults 70)
  const [attendanceBarrier, setAttendanceBarrier] = useState(70);

  const [selectedRequestStudent, setSelectedRequestStudent] = useState('');

  const getEnrichedStudent = (st: { id?: string; name: string; email: string; password?: string }) => {
    const name = st.name;
    const email = st.email;
    const activePass = st.password || localStorage.getItem(`ava_active_password_${st.name}`) || '1234';

    // Core default mapping as requested by the prompt
    let defaultRA = '1234';
    let defaultCurso = '—';
    let defaultTurma = '—';
    let defaultPolo = '—';
    let defaultStatusMatricula: 'Ativa' | 'Sem matrícula' | 'Trancada' | 'Concluída' | 'Cancelada' = 'Sem matrícula';
    let defaultStatusConta: 'Ativa' | 'Bloqueada' | 'Aguardando confirmação' = 'Ativa';
    let defaultProgresso = 0;
    let defaultHorasConcluidas = 0;
    let defaultHorasTotais = 0;
    let defaultLastAccess = 'Há 1 semana';
    let defaultPendencias: string[] = ['Matrícula pendente'];
    let defaultResponsavel = 'Secretaria';

    if (name === 'João Silva') {
      defaultRA = '1234';
      defaultCurso = 'Gestão Pública';
      defaultTurma = '2025.2';
      defaultPolo = 'Centro';
      defaultStatusMatricula = 'Ativa';
      defaultStatusConta = 'Ativa';
      defaultProgresso = 41;
      defaultHorasConcluidas = 33;
      defaultHorasTotais = 80;
      defaultLastAccess = 'Há 2 dias';
      defaultPendencias = ['Nenhuma'];
      defaultResponsavel = 'Tutor Mariana Alves';
    } else if (name === 'Gabriel Rodrigues') {
      defaultRA = '1235';
      defaultCurso = '—';
      defaultTurma = '—';
      defaultPolo = '—';
      defaultStatusMatricula = 'Sem matrícula';
      defaultStatusConta = 'Ativa';
      defaultProgresso = 0;
      defaultHorasConcluidas = 0;
      defaultHorasTotais = 0;
      defaultLastAccess = 'Há 1 semana';
      defaultPendencias = ['Matrícula pendente'];
      defaultResponsavel = 'Secretaria';
    } else if (name === 'Beatriz Costa' || email.includes('beatriz.c')) {
      defaultRA = '1236';
      defaultCurso = 'Atendimento ao Cidadão';
      defaultTurma = '2025.1';
      defaultPolo = 'Online';
      defaultStatusMatricula = 'Ativa';
      defaultStatusConta = 'Ativa';
      defaultProgresso = 78;
      defaultHorasConcluidas = 62;
      defaultHorasTotais = 80;
      defaultLastAccess = 'Há 2 dias';
      defaultPendencias = ['Atividade atrasada'];
      defaultResponsavel = 'Tutor Carlos Lima';
    } else if (name === 'Sofia Rocha') {
      defaultRA = '1237';
      defaultCurso = 'Gestão Pública';
      defaultTurma = '2025.2';
      defaultPolo = 'Centro';
      defaultStatusMatricula = 'Ativa';
      defaultStatusConta = 'Bloqueada';
      defaultProgresso = 15;
      defaultHorasConcluidas = 12;
      defaultHorasTotais = 80;
      defaultLastAccess = 'Há 15 dias';
      defaultPendencias = ['Termo de Compromisso'];
      defaultResponsavel = 'Consultor Rafael Souza';
    } else if (name === 'Ana Souza') {
      defaultRA = '1238';
      defaultCurso = 'Licitações e Contratos';
      defaultTurma = '2025.2';
      defaultPolo = 'Online';
      defaultStatusMatricula = 'Ativa';
      defaultStatusConta = 'Ativa';
      defaultProgresso = 64;
      defaultHorasConcluidas = 51;
      defaultHorasTotais = 80;
      defaultLastAccess = 'Hoje, às 14:32';
      defaultPendencias = ['Nenhuma'];
      defaultResponsavel = 'Tutor Mariana Alves';
    } else if (name === 'Lucas Santana') {
      defaultRA = '1239';
      defaultCurso = 'Ética no Serviço Público';
      defaultTurma = '2025.1';
      defaultPolo = 'Online';
      defaultStatusMatricula = 'Ativa';
      defaultStatusConta = 'Ativa';
      defaultProgresso = 22;
      defaultHorasConcluidas = 18;
      defaultHorasTotais = 80;
      defaultLastAccess = 'Há 20 dias';
      defaultPendencias = ['Acadêmica'];
      defaultResponsavel = 'Não atribuído';
    } else {
      // Dynamic fallback for newly registered students
      let hash = 0;
      for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
      }
      defaultRA = String(1240 + Math.abs(hash) % 1000);
      
      // Let's check if the student is registered in active admissions
      const myAdmissions = admissionRequests.filter(r => (st.id ? r.userId === st.id : r.studentName === name) && r.status === 'approved');
      if (myAdmissions.length > 0) {
        const course = courses.find(c => c.id === myAdmissions[0].courseId);
        if (course) {
          defaultCurso = course.title;
          defaultTurma = '2025.2';
          defaultPolo = 'Online';
          defaultStatusMatricula = 'Ativa';
          defaultStatusConta = 'Ativa';
          defaultProgresso = 10;
          defaultHorasConcluidas = 8;
          defaultHorasTotais = 80;
          defaultLastAccess = 'Hoje, às 10:15';
          defaultPendencias = ['Nenhuma'];
          defaultResponsavel = course.instructorName || 'Não atribuído';
        }
      } else {
        defaultCurso = '—';
        defaultTurma = '—';
        defaultPolo = '—';
        defaultStatusMatricula = 'Sem matrícula';
        defaultStatusConta = 'Ativa';
        defaultProgresso = 0;
        defaultHorasConcluidas = 0;
        defaultHorasTotais = 0;
        defaultLastAccess = 'Nunca acessou';
        defaultPendencias = ['Matrícula pendente'];
        defaultResponsavel = 'Secretaria';
      }
    }

    // Apply Overrides if they exist
    const override = studentOverrides[name] || {};
    const finalStatusMatricula = override.statusMatricula || defaultStatusMatricula;
    const finalStatusConta = override.statusConta || defaultStatusConta;
    const finalPendencias = override.pendencias || defaultPendencias;
    const finalResponsavel = override.responsavel || defaultResponsavel;
    const finalProgresso = override.progresso !== undefined ? override.progresso : defaultProgresso;
    const finalHorasConcluidas = override.horasConcluidas !== undefined ? override.horasConcluidas : defaultHorasConcluidas;
    const finalHorasTotais = override.horasTotais !== undefined ? override.horasTotais : defaultHorasTotais;

    // Check penalty from standard state if penalized (mapa keyed por userId — ADR 10)
    const enrollRecord = st.id ? studentEnrollments?.[st.id] : undefined;
    const isPenalized = enrollRecord?.dropOutPenaltyUntil && new Date(enrollRecord.dropOutPenaltyUntil).getTime() > Date.now();
    let computedPendencias = [...finalPendencias];
    
    if (isPenalized && !computedPendencias.includes('Termo de Compromisso')) {
      computedPendencias = computedPendencias.filter(p => p !== 'Nenhuma');
      computedPendencias.push('Termo de Compromisso');
    }

    // Determine accessDays for Risk Level mapping
    const getAccessDays = (txt: string) => {
      if (txt.includes('Hoje')) return 0;
      if (txt.includes('Há 2 dias')) return 2;
      if (txt.includes('Há 1 semana') || txt.includes('7 dias')) return 7;
      if (txt.includes('Há 15 dias') || txt.includes('15 dias')) return 15;
      if (txt.includes('Há 20 dias') || txt.includes('20 dias')) return 20;
      if (txt.includes('Nunca')) return 999;
      return 3;
    };
    const accessDays = getAccessDays(defaultLastAccess);
    
    // Risk level
    let riskLevel: 'Normal' | 'Atenção' | 'Risco' | 'Crítico' = 'Normal';
    if (accessDays === 999) riskLevel = 'Crítico';
    else if (accessDays > 15) riskLevel = 'Risco';
    else if (accessDays >= 8) riskLevel = 'Atenção';

    return {
      id: st.id,
      name,
      email,
      ra: defaultRA,
      password: activePass,
      curso: finalStatusMatricula === 'Sem matrícula' ? '—' : defaultCurso,
      turma: finalStatusMatricula === 'Sem matrícula' ? '—' : defaultTurma,
      polo: finalStatusMatricula === 'Sem matrícula' ? '—' : defaultPolo,
      statusMatricula: finalStatusMatricula,
      statusConta: finalStatusConta,
      progresso: finalStatusMatricula === 'Sem matrícula' ? 0 : finalProgresso,
      horasConcluidas: finalStatusMatricula === 'Sem matrícula' ? 0 : finalHorasConcluidas,
      horasTotais: finalStatusMatricula === 'Sem matrícula' ? 0 : finalHorasTotais,
      lastAccess: defaultLastAccess,
      accessDays,
      pendencias: computedPendencias.length === 0 ? ['Nenhuma'] : computedPendencias,
      responsavel: finalResponsavel,
      riskLevel
    };
  };

  // Helper to update student overrides
  const updateOverride = (studentName: string, fields: Partial<typeof studentOverrides[string]>) => {
    setStudentOverrides(prev => ({
      ...prev,
      [studentName]: {
        ...(prev[studentName] || {}),
        ...fields
      }
    }));
  };

  // Enriched students list
  const enrichedStudentsList = mockStudents.map(st => getEnrichedStudent(st));

  // Count states for metrics
  const totalStudentsCount = enrichedStudentsList.length;
  const activeMatriculasCount = enrichedStudentsList.filter(s => s.statusMatricula === 'Ativa').length;
  const semMatriculaCount = enrichedStudentsList.filter(s => s.statusMatricula === 'Sem matrícula').length;
  const comPendenciasCount = enrichedStudentsList.filter(s => s.pendencias.some(p => p !== 'Nenhuma')).length;
  const semAcessoRecenteCount = enrichedStudentsList.filter(s => s.riskLevel !== 'Normal').length;
  const emRiscoEvasaoCount = enrichedStudentsList.filter(s => s.riskLevel === 'Risco' || s.riskLevel === 'Crítico').length;

  // Filter and sort the enriched student list
  const filteredStudents = enrichedStudentsList.filter(st => {
    // 0. Quick Filters (from Top Cards)
    if (activeQuickFilter !== 'all') {
      if (activeQuickFilter === 'ativos' && st.statusMatricula !== 'Ativa') return false;
      if (activeQuickFilter === 'sem_matricula' && st.statusMatricula !== 'Sem matrícula') return false;
      if (activeQuickFilter === 'pendencias' && !st.pendencias.some(p => p !== 'Nenhuma')) return false;
      if (activeQuickFilter === 'ausentes' && st.riskLevel === 'Normal') return false;
      if (activeQuickFilter === 'evasao' && st.riskLevel !== 'Risco' && st.riskLevel !== 'Crítico') return false;
    }

    // 1. Search Query (Name, Email, RA)
    if (studentSearchQuery.trim()) {
      const q = studentSearchQuery.toLowerCase();
      const matches = st.name.toLowerCase().includes(q) || 
                      st.email.toLowerCase().includes(q) || 
                      st.ra.toLowerCase().includes(q);
      if (!matches) return false;
    }

    // 2. Course Filter
    if (filterCourse !== 'all') {
      if (filterCourse === 'none') {
        if (st.curso !== '—') return false;
      } else {
        if (st.curso !== filterCourse) return false;
      }
    }

    // 3. Turma Filter
    if (filterTurma !== 'all') {
      if (filterTurma === 'none') {
        if (st.turma !== '—') return false;
      } else {
        if (st.turma !== filterTurma) return false;
      }
    }

    // 4. Status Matrícula Filter
    if (filterStatusMatricula !== 'all') {
      if (st.statusMatricula !== filterStatusMatricula) return false;
    }

    // 5. Pendência Filter
    if (filterPendencia !== 'all') {
      if (filterPendencia === 'Nenhuma') {
        if (st.pendencias.some(p => p !== 'Nenhuma')) return false;
      } else {
        if (!st.pendencias.some(p => p.toLowerCase().includes(filterPendencia.toLowerCase()))) return false;
      }
    }

    // 7. Risco Filter
    if (filterRisco !== 'all') {
      if (filterRisco === 'Normal' && st.riskLevel !== 'Normal') return false;
      if (filterRisco === 'Atenção' && st.riskLevel !== 'Atenção') return false;
      if (filterRisco === 'Risco' && st.riskLevel !== 'Risco') return false;
      if (filterRisco === 'Crítico' && st.riskLevel !== 'Crítico') return false;
    }

    return true;
  });

  // Sort
  const sortedStudents = [...filteredStudents].sort((a, b) => {
    let comparison = 0;
    if (sortBy === 'name') {
      comparison = a.name.localeCompare(b.name);
    } else if (sortBy === 'lastAccess') {
      comparison = a.accessDays - b.accessDays;
    } else if (sortBy === 'progress') {
      comparison = a.progresso - b.progresso;
    } else if (sortBy === 'pendency') {
      const aHas = a.pendencias.some(p => p !== 'Nenhuma') ? 1 : 0;
      const bHas = b.pendencias.some(p => p !== 'Nenhuma') ? 1 : 0;
      comparison = bHas - aHas; // prioritized pendencies first
    } else if (sortBy === 'statusMatricula') {
      comparison = a.statusMatricula.localeCompare(b.statusMatricula);
    } else if (sortBy === 'risk') {
      const riskScore = (r: string) => {
        if (r === 'Crítico') return 3;
        if (r === 'Risco') return 2;
        if (r === 'Atenção') return 1;
        return 0;
      };
      comparison = riskScore(b.riskLevel) - riskScore(a.riskLevel);
    }

    return sortOrder === 'asc' ? comparison : -comparison;
  });

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
      showToast('Por favor, digite o nome e e-mail do aluno.');
      return;
    }

    if (!selectedEnrollCourseId) {
      showToast('Por favor, selecione o curso de matrícula.');
      return;
    }

    const pass = newStudentPassword.trim() || '1234';
    const studentName = newStudentName.trim();
    const studentEmail = newStudentEmail.trim();
    const studentMunicipio = newStudentMunicipio.trim() || 'São Paulo';
    const studentUf = newStudentUf.trim().toUpperCase() || 'SP';
    const studentArea = newStudentAreaInteresse.trim() || 'Tecnologia';
    const studentDate = new Date().toISOString().split('T')[0];

    addStudent(studentName, studentEmail, pass, studentMunicipio, studentUf, studentArea, studentDate);
    
    // Auto-enroll in the selected course with approved status
    if (selectedEnrollCourseId) {
      // Aluno recém-criado ainda não tem id local (o cadastro no backend é assíncrono);
      // se já existir na lista hidratada, usa o id real.
      const existing = studentsList.find(s => s.email.toLowerCase() === studentEmail.toLowerCase());
      addAdmissionRequest(existing?.id ?? '', selectedEnrollCourseId, 'approved');
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
    setNewStudentMunicipio('');
    setNewStudentUf('');
    setNewStudentAreaInteresse('');
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

    const teacher = professorsList.find(p => p.id === newCourseTeacherId) ?? professorsList[0];

    addCourse({
      id: randomId,
      title: newCourseTitle.trim(),
      description: newCourseDesc.trim() || 'Este é um curso recém-provido pelo suporte administrativo da instituição.',
      category: newCourseCategory,
      thumbnail: randPhoto,
      // instructorName é display; a autoria real é o instructorId (o servidor ignora o nome).
      instructorName: teacher?.name ?? '',
      instructorId: teacher?.id ?? null,
      courseType: newCourseType,
      hasChat: newCourseHasChat,
      lessons: [],
      liveSessions: [],
      contractExpirationDate: newCourseExpiration.trim() || undefined
    });

    showToast(`Curso de "${newCourseTitle.trim()}" provido e atribuído para ${teacher?.name ?? 'a definir'}!`);
    setNewCourseTitle('');
    setNewCourseDesc('');
    setNewCourseExpiration('');
  };

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

  // Itens de navegação do módulo administrativo (esqueleto sidebar+topbar do PC Design System).
  const adminNavItems = [
    { id: 'analytics', label: 'Dashboard & Relatórios', icon: Activity, visible: true },
    { id: 'professors', label: 'Equipe Pedagógica', icon: User, visible: true },
    { id: 'students', label: 'Alunos', icon: Award, visible: true },
    { id: 'courses', label: 'Cursos & Trilhas', icon: BookOpen, visible: features.catalogoCursos },
    { id: 'requests', label: 'Documentos', icon: FileCheck, visible: features.solicitacoesAcademicas },
    { id: 'exercicios', label: 'Exercícios Práticos', icon: CheckSquare, visible: features.atividadesPraticasAvancadas },
    { id: 'export_bi', label: 'Dados Gerenciais', icon: Database, visible: features.dadosGerenciais },
    { id: 'settings', label: 'Configurações', icon: Settings, visible: features.perfilBasico },
  ].filter((t) => t.visible);
  const activeNavItem = adminNavItems.find((t) => t.id === activeTab);

  return (
    <div className="flex min-h-[calc(100vh-5rem)] bg-[#F1F5F9] animate-in fade-in duration-300 text-left">

      {/* ===== SIDEBAR escura fixa (desktop) — PC Design System ===== */}
      <aside className="w-60 bg-[#0F172A] text-slate-300 flex-shrink-0 hidden lg:flex lg:flex-col">
        <div className="p-4 border-b border-white/10 flex items-center gap-2.5">
          <ShieldCheck className="h-5 w-5 text-blue-400 shrink-0" />
          <div className="min-w-0">
            <span className="block text-sm font-bold text-white leading-tight truncate">Administração</span>
            <span className="block text-[10px] text-slate-400">AVASEC · Master Root</span>
          </div>
        </div>

        <nav className="p-3 space-y-1 flex-1 overflow-y-auto">
          <span className="block px-3 pt-1 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Gestão</span>
          {adminNavItems.map((tab) => {
            const IconComp = tab.icon;
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as any)}
                className={`w-full flex items-center gap-2 px-3 py-2 rounded-[6px] text-sm transition-colors cursor-pointer text-left ${
                  isActive ? 'bg-blue-500/15 text-blue-400' : 'text-slate-300 hover:bg-white/5'
                }`}
              >
                <IconComp className="h-4 w-4 shrink-0" />
                <span className="truncate">{tab.label}</span>
              </button>
            );
          })}
        </nav>

        {/* Aviso de privacidade — rodapé da sidebar */}
        <div className="p-4 border-t border-white/10 flex gap-2 items-start">
          <Lock className="h-4 w-4 text-amber-400 shrink-0 mt-0.5" />
          <p className="text-[10px] text-slate-400 leading-normal">
            O administrador não tem acesso de leitura aos chats privados ou DMs de alunos por diretrizes de privacidade de dados.
          </p>
        </div>
      </aside>

      <div className="flex-1 flex flex-col min-w-0">
        {/* ===== TOPBAR branca ===== */}
        <header className="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between gap-4">
          <div className="flex items-center gap-3 min-w-0">
            {onBackToLanding && (
              <button
                onClick={() => {
                  const label = getBackLabel();
                  speakText(`${label}. Retornando.`);
                  handleBack();
                }}
                className="border border-slate-200 text-slate-700 rounded-[6px] font-medium text-sm px-3 py-1.5 hover:bg-slate-50 transition-colors cursor-pointer flex items-center gap-1.5 shrink-0"
                title={getBackLabel()}
              >
                <ArrowLeft className="h-4 w-4" />
                <span className="hidden sm:inline text-xs">{getBackLabel()}</span>
              </button>
            )}
            <div className="min-w-0">
              <h1 className="text-[1.2rem] font-bold text-slate-900 leading-tight truncate">
                {activeNavItem?.label ?? 'Portal do Administrador'}
              </h1>
              <p className="text-xs text-slate-500 truncate">Gestão global de professores, alunos, turmas e cursos.</p>
            </div>
          </div>

          <div className="flex items-center gap-3 shrink-0">
            {/* Navegação mobile (sidebar oculta abaixo de lg) */}
            <select
              value={activeTab}
              onChange={(e) => setActiveTab(e.target.value as any)}
              className="lg:hidden border border-slate-200 rounded-[6px] text-sm text-slate-700 px-2 py-1.5 bg-white"
              aria-label="Seção do painel administrativo"
            >
              {adminNavItems.map((tab) => (
                <option key={tab.id} value={tab.id}>{tab.label}</option>
              ))}
            </select>
            <span className="hidden sm:inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wide bg-blue-50 text-blue-600">
              Master Root
            </span>
          </div>
        </header>

        {/* ===== CONTEÚDO ===== */}
        <main className="flex-1 overflow-x-hidden p-6">

      {/* TAB CONTENT SPACES */}
      {((!features.catalogoCursos && activeTab === 'courses') ||
        (!features.solicitacoesAcademicas && activeTab === 'requests') ||
        (!features.atividadesPraticasAvancadas && activeTab === 'exercicios') ||
        (!features.dadosGerenciais && activeTab === 'export_bi') ||
        (!features.perfilBasico && activeTab === 'settings')) && (
        <div className="bg-amber-50 border border-amber-200 text-amber-900 rounded-[10px] p-8 text-center max-w-xl mx-auto my-12 shadow-sm space-y-3">
          <Lock className="h-10 w-10 text-amber-600 mx-auto" />
          <h3 className="font-extrabold text-base">Esta funcionalidade está temporariamente indisponível.</h3>
          <p className="text-xs text-slate-500">Estamos trabalhando em melhorias e atualizações para esta seção. Por favor, tente novamente mais tarde.</p>
        </div>
      )}

      {activeTab === 'analytics' && (
        <div className="space-y-6 text-left animate-in fade-in duration-300">
          
          {/* Header Info */}
          <div className="bg-white border border-slate-200 rounded-[10px] p-5 space-y-2">
            <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <Activity className="h-4.5 w-4.5 text-blue-600" />
              <span>Painel de Controle e Inteligência de Dados (Analytics)</span>
            </h3>
            <p className="text-xs text-slate-500 leading-relaxed">
              Visão consolidada do ecossistema educacional. Monitore métricas de engajamento, rendimento pedagógico e gere relatórios oficiais de auditoria.
            </p>
          </div>

          <div className="border-t border-slate-100 pt-6">
            <div className="mb-4">
              <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <FileText className="h-4 w-4 text-blue-600" />
                <span>Módulos de Relatórios e Auditoria Pedagógica</span>
              </h3>
              <p className="text-[11px] text-slate-500 mt-1">Gere documentos oficiais e estatísticas cruzadas de alunos e professores.</p>
            </div>

            {/* Sub Navigation controls to target specific reports */}
            <div className="flex bg-slate-50 p-1.5 rounded-[10px] border border-slate-200 gap-1 overflow-x-auto mb-6 w-full max-w-4xl md:mx-auto">
              {[
                { id: 'consolidado', label: 'Visão Geral', icon: Activity },
                { id: 'alunos', label: 'Alunos', icon: Users },
                { id: 'professores', label: 'Equipe Pedagógica', icon: ShieldCheck },
                { id: 'cursos', label: 'Cursos', icon: BookOpen },
                { id: 'inscricoes', label: 'Matrículas', icon: FileCheck },
              ].map((st) => {
                const SubIcon = st.icon;
                const isSubActive = activeReportSubTab === st.id;
                return (
                  <button
                    key={st.id}
                    onClick={() => setActiveReportSubTab(st.id as any)}
                    className={`flex-1 justify-center px-5 py-2.5 rounded-md text-[11px] font-bold transition-all flex items-center gap-2 whitespace-nowrap cursor-pointer ${
                      isSubActive 
                        ? 'bg-white text-slate-900 shadow-sm border border-slate-200/60 font-black' 
                        : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100/50'
                    }`}
                  >
                    <SubIcon className={`h-3.5 w-3.5 ${isSubActive ? 'text-blue-600' : 'text-slate-400'}`} />
                    <span>{st.label}</span>
                  </button>
                );
              })}
            </div>

            <div className="bg-white border border-slate-200 rounded-[10px] p-6 space-y-4">
              
              {/* Action buttons header */}
              <div className="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-2">
                <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                  Relatório de Rendimento: <span className="text-slate-800 font-extrabold text-xs">
                    {[
                      { id: 'consolidado', label: 'Visão Geral' },
                      { id: 'alunos', label: 'Alunos' },
                      { id: 'professores', label: 'Equipe Pedagógica' },
                      { id: 'cursos', label: 'Cursos' },
                      { id: 'inscricoes', label: 'Matrículas' },
                    ].find(t => t.id === activeReportSubTab)?.label.toUpperCase() || activeReportSubTab.toUpperCase()}
                  </span>
                </span>
                <button
                  onClick={() => {
                    window.print();
                  }}
                  className="text-[10px] bg-slate-900 hover:bg-slate-800 text-white transition-colors font-bold px-3 py-1.8 rounded-md flex items-center gap-1.5 cursor-pointer"
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
                    <div className="bg-white border border-slate-200 rounded-[10px] p-4 shadow-sm space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="p-2 bg-blue-50 rounded-md">
                          <Users className="h-4 w-4 text-blue-600" />
                        </div>
                      </div>
                      <div>
                        <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Alunos matriculados</p>
                        <p className="text-2xl font-black text-slate-900">{studentsList.length}</p>
                      </div>
                    </div>

                    <div className="bg-white border border-slate-200 rounded-[10px] p-4 shadow-sm space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="p-2 bg-blue-50 rounded-md">
                          <BookOpen className="h-4 w-4 text-blue-600" />
                        </div>
                      </div>
                      <div>
                        <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Cursos publicados</p>
                        <p className="text-2xl font-black text-slate-900">{courses.length}</p>
                      </div>
                    </div>

                    <div className="bg-white border border-slate-200 rounded-[10px] p-4 shadow-sm space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="p-2 bg-red-50 rounded-md">
                          <Activity className="h-4 w-4 text-red-600" />
                        </div>
                      </div>
                      <div>
                        <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Matrículas ativas</p>
                        <p className="text-2xl font-black text-slate-900">
                          {(Object.values(studentEnrollments || {}) as StudentEnrollment[]).filter(e => e.enrolledCourseId).length}
                        </p>
                      </div>
                    </div>

                    <div className="bg-white border border-slate-200 rounded-[10px] p-4 shadow-sm space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="p-2 bg-amber-50 rounded-md">
                          <Award className="h-4 w-4 text-amber-600" />
                        </div>
                      </div>
                      <div>
                        <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Certificados emitidos</p>
                        <p className="text-2xl font-black text-slate-900">{certificates.length}</p>
                      </div>
                    </div>
                  </div>

                  {/* 2. Charts Section */}
                  {features.graficosAvancados && (
                  <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    {/* Category Distribution (Pie) */}
                    <div className="lg:col-span-1 bg-white border border-slate-200 rounded-[10px] p-5 shadow-sm flex flex-col">
                      <h4 className="text-xs font-black text-slate-900 uppercase tracking-widest mb-6 flex items-center gap-2">
                        <Layers className="h-3.5 w-3.5 text-blue-600" />
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
                                  '#3B82F6', '#EE4266', '#FFD23F', '#3BCEAC', '#0EAD69'
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
                    <div className="lg:col-span-2 bg-white border border-slate-200 rounded-[10px] p-5 shadow-sm">
                      <div className="flex items-center justify-between mb-6">
                        <h4 className="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                          <TrendingUp className="h-3.5 w-3.5 text-blue-600" />
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
                                <stop offset="5%" stopColor="#3B82F6" stopOpacity={0.1}/>
                                <stop offset="95%" stopColor="#3B82F6" stopOpacity={0}/>
                              </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E2E8F0" />
                            <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: '#64748B', fontWeight: 'bold' }} />
                            <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: '#64748B', fontWeight: 'bold' }} />
                            <Tooltip contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)', fontSize: '11px', fontWeight: 'bold' }} />
                            <Area type="monotone" dataKey="matriculas" stroke="#3B82F6" strokeWidth={3} fillOpacity={1} fill="url(#colorMatricula)" />
                          </AreaChart>
                        </ResponsiveContainer>
                      </div>
                    </div>
                  </div>
                  )}

                  {/* Combined High-Level Counters (Relocated below charts) */}
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-left border-t border-slate-100 pt-6">
                    {[
                      { label: 'Alunos matriculados', value: studentsList.length, desc: 'Total no Sistema', icon: Users, accent: 'bg-blue-50 border-blue-100 text-blue-700' },
                      { label: 'Cursos publicados', value: courses.length, desc: 'Disciplinas Ativas', icon: BookOpen, accent: 'bg-blue-50 border-blue-100 text-blue-700' },
                      { label: 'Equipe Pedagógica', value: professorsList.length, desc: 'Membros Ativos', icon: ShieldCheck, accent: 'bg-emerald-50 border-emerald-100 text-emerald-700' }
                    ].map((stat) => (
                      <div key={stat.label} className="bg-white border border-slate-200 p-4 rounded-[10px] shadow-sm flex justify-between items-start">
                        <div className="space-y-1">
                          <header className="text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-tight">{stat.label}</header>
                          <p className="text-xl font-black text-slate-900 font-mono leading-none">{stat.value}</p>
                          <span className="text-[9px] text-slate-500 block leading-tight">{stat.desc}</span>
                        </div>
                        <div className={`p-1.5 rounded-md border ${stat.accent}`}>
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
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-[10px] border border-slate-100">
                    <div className="text-left font-sans">
                      <span className="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Total de Alunos</span>
                      <strong className="text-xl font-black text-slate-900 font-mono">{mockStudents.length} alunos</strong>
                    </div>
                    <div className="text-left">
                      <span className="text-[9px] font-bold text-slate-400 uppercase tracking-widest block font-sans">Certificados Emitidos</span>
                      <strong className="text-xl font-black text-emerald-700 font-mono">{certificates.length} emitidos</strong>
                    </div>
                  </div>

                  <div className="overflow-x-auto">
                    <table className="w-full text-xs text-left border-collapse">
                      <thead>
                        <tr className="border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase bg-slate-50/50">
                          <th className="p-2.5">Nome</th>
                          <th className="p-2.5">E-mail</th>
                          <th className="p-2.5 text-center">Matrículas ativas</th>
                          <th className="p-2.5 text-center">Progresso médio</th>
                          <th className="p-2.5 text-center">Último acesso</th>
                          <th className="p-2.5 text-center">Situação</th>
                          <th className="p-2.5 text-right">Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        {mockStudents.map((st, idx) => {
                          const enrollmentsCount = st.id && studentEnrollments?.[st.id]?.enrolledCourseId ? 1 : 0;
                          const studentProgress = progress.filter(p => p.userId === st.id);
                          const avgProg = studentProgress.length === 0 ? 0 : Math.round(studentProgress.reduce((sum, p) => {
                            const c = courses.find(course => course.id === p.courseId);
                            return sum + (c ? (p.completedLessons.length / c.lessons.length) * 100 : 0);
                          }, 0) / studentProgress.length);
                          
                          return (
                            <tr key={`${st.email}-${idx}`} className="border-b border-slate-100 hover:bg-slate-50/25">
                              <td className="p-2.5 font-extrabold text-slate-900">{st.name}</td>
                              <td className="p-2.5 text-slate-500 font-mono">{st.email}</td>
                              <td className="p-2.5 text-center font-bold text-slate-700">{enrollmentsCount}</td>
                              <td className="p-2.5 text-center font-mono text-blue-600 font-bold">{avgProg}%</td>
                              <td className="p-2.5 text-center text-slate-500 text-[10px]">{st.lastAccess || 'Sem acesso'}</td>
                              <td className="p-2.5 text-center">
                                <span className="text-[9px] font-black uppercase bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full">Regular</span>
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
                                  className="text-[10px] bg-slate-100 border border-slate-200 rounded px-2 py-1 text-slate-700 font-semibold focus:outline-hidden cursor-pointer"
                                >
                                  <option value="">Emitir...</option>
                                  <option value="historico">Histórico</option>
                                  <option value="certificado">Certificado</option>
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

              {/* Equipe Pedagógica detailed report */}
              {activeReportSubTab === 'professores' && (
                <div className="space-y-4 animate-in fade-in duration-200">
                  <div className="bg-slate-50 p-4 rounded-[10px] border border-slate-100 flex items-center justify-between">
                    <div>
                      <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Equipe pedagógica</span>
                      <strong className="text-lg font-black text-slate-900 font-mono block mt-1">
                        {professorsList.length} / {professorsList.length === 1 ? '1 gestor de conteúdos ativo' : `${professorsList.length - 1} ${professorsList.length - 1 === 1 ? 'professor' : 'professores'}, 1 gestor de conteúdos`}
                      </strong>
                    </div>
                  </div>

                  <div className="overflow-x-auto">
                    <table className="w-full text-xs text-left border-collapse">
                      <thead>
                        <tr className="border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase bg-slate-50/50">
                          <th className="p-2.5">Nome</th>
                          <th className="p-2.5">Perfil</th>
                          <th className="p-2.5 text-center">Cursos vinculados</th>
                          <th className="p-2.5 text-center">Aulas/Módulos</th>
                          <th className="p-2.5 text-center">Status</th>
                          <th className="p-2.5 text-right">Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        {professorsList.map((prof, idx) => {
                          const assigned = courses.filter(c => c.instructorId === prof.id);
                          const totalLessons = assigned.reduce((sum, c) => sum + c.lessons.length, 0);
                          const profile = idx === 0 ? "Gestor de Conteúdos" : "Professor";
                          return (
                            <tr key={`${prof.id}-${idx}`} className="border-b border-slate-100">
                              <td className="p-2.5 font-extrabold text-slate-900">{prof.name}</td>
                              <td className="p-2.5">
                                <span className={`px-2 py-0.5 rounded-full text-[9px] font-bold uppercase ${
                                  profile === 'Gestor de Conteúdos' ? 'bg-blue-50 text-blue-700' : 'bg-slate-50 text-slate-700'
                                }`}>
                                  {profile}
                                </span>
                              </td>
                              <td className="p-2.5 text-center font-bold text-blue-600">{assigned.length}</td>
                              <td className="p-2.5 text-center font-mono text-slate-500">{totalLessons}</td>
                              <td className="p-2.5 text-center">
                                <span className="text-[9px] font-black uppercase bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full">Ativo</span>
                              </td>
                              <td className="p-2.5 text-right">
                                <button className="p-1 hover:bg-slate-100 rounded text-slate-400">
                                  <MoreVertical className="h-3 w-3" />
                                </button>
                              </td>
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
                        <tr className="border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase bg-slate-50/50">
                          <th className="p-2.5">Curso</th>
                          <th className="p-2.5">Categoria/Eixo</th>
                          <th className="p-2.5 text-center">Módulos</th>
                          <th className="p-2.5 text-center">Alunos matriculados</th>
                          <th className="p-2.5 text-center">Concluintes</th>
                          <th className="p-2.5 text-center">Progresso médio</th>
                          <th className="p-2.5 text-right">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        {courses.filter(c => c.lessons.length > 0).map((c, idx) => {
                          const enrolled = (Object.values(studentEnrollments || {}) as StudentEnrollment[]).filter(e => e.enrolledCourseId === c.id).length;
                          const courseProgress = progress.filter(p => p.courseId === c.id);
                          const finished = courseProgress.filter(p => p.completedLessons.length === c.lessons.length && c.lessons.length > 0).length;
                          const avgProg = courseProgress.length === 0 ? 0 : Math.round(courseProgress.reduce((sum, p) => sum + (p.completedLessons.length / c.lessons.length) * 100, 0) / courseProgress.length);
                          
                          return (
                            <tr key={`${c.id}-${typeof idx !== "undefined" ? idx : 0}`} className="border-b border-slate-100">
                              <td className="p-2.5">
                                <span className="font-extrabold text-slate-900 block">{c.title}</span>
                              </td>
                              <td className="p-2.5">
                                <span className="text-[10px] text-slate-500 block font-bold uppercase tracking-wider">{c.category}</span>
                              </td>
                              <td className="p-2.5 text-center font-mono">{c.lessons.length}</td>
                              <td className="p-2.5 text-center font-bold text-slate-700">{enrolled}</td>
                              <td className="p-2.5 text-center font-bold text-emerald-600">{finished}</td>
                              <td className="p-2.5 text-center font-mono text-blue-600 font-bold">{avgProg}%</td>
                              <td className="p-2.5 text-right">
                                <span className="text-[9px] font-black uppercase bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full">
                                  Publicado
                                </span>
                              </td>
                            </tr>
                          );
                        })}
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
                        <tr className="border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase bg-slate-50/50">
                          <th className="p-2.5">Aluno</th>
                          <th className="p-2.5">Curso</th>
                          <th className="p-2.5 text-center">Data da matrícula</th>
                          <th className="p-2.5 text-center">Progresso</th>
                          <th className="p-2.5 text-center">Último acesso</th>
                          <th className="p-2.5 text-center">Situação</th>
                          <th className="p-2.5 text-right">Certificado</th>
                        </tr>
                      </thead>
                      <tbody>
                        {Object.entries(studentEnrollments || {}).map(([userId, enrollmentVal], idx) => {
                          const enrollment = enrollmentVal as StudentEnrollment;
                          const courseId = enrollment.enrolledCourseId;
                          if (!courseId) return null;
                          const course = courses.find(c => c.id === courseId);
                          if (!course) return null;

                          // A chave do mapa é o userId (ADR 10); o nome de exibição vem do enrollment.
                          const student = studentsList.find(s => s.id === userId);
                          const studentName = enrollment.studentName || student?.name || userId;
                          const userProg = progress.find(p => p.courseId === course.id && p.userId === userId);
                          const comp = userProg ? userProg.completedLessons.length : 0;
                          const ratio = course.lessons.length > 0 ? Math.round((comp / course.lessons.length) * 100) : 0;
                          const hasCert = certificates.some(cert => cert.courseId === course.id && cert.userId === userId);
                          const formattedDate = enrollment.enrolledAt ? new Date(enrollment.enrolledAt).toLocaleDateString('pt-BR') : '—';

                          return (
                            <tr key={idx} className="border-b border-slate-100">
                              <td className="p-2.5 font-bold text-slate-900">{studentName}</td>
                              <td className="p-2.5 text-slate-600">{course.title}</td>
                              <td className="p-2.5 text-center text-slate-500">{formattedDate}</td>
                              <td className="p-2.5 text-center font-mono text-blue-600 font-bold">{ratio}%</td>
                              <td className="p-2.5 text-center text-slate-500">{student?.lastAccess || 'Sem acesso'}</td>
                              <td className="p-2.5 text-center">
                                <span className="text-[9px] font-black uppercase bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full">Ativa</span>
                              </td>
                              <td className="p-2.5 text-right">
                                {hasCert ? (
                                  <span className="text-emerald-600 flex items-center justify-end gap-1 font-bold text-[10px]">
                                    <CheckCircle2 className="h-3 w-3" /> Emitido
                                  </span>
                                ) : (
                                  <span className="text-slate-400 font-bold text-[10px]">Pendente</span>
                                )}
                              </td>
                            </tr>
                          );
                        })}
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
        <div className="space-y-4 text-left animate-in fade-in duration-300">
          <div>
            <BackButton onClick={() => setActiveTab('analytics')} text="Voltar ao Painel Administrativo" />
          </div>
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          
          {/* Gestor de Conteúdos Configuration Panel */}
          <div className="lg:col-span-4 bg-white border border-slate-200 p-5 rounded-[10px] text-left h-fit space-y-4">
            <div>
              <span className="rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-0.5 text-[9px] font-bold border border-emerald-100 uppercase tracking-wide">
                Configuração de Perfil
              </span>
              <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider mt-2">Configurações do Gestor</h3>
              <p className="text-[11px] text-slate-500 mt-0.5">Gerencie os dados e acessos de controle de conteúdos.</p>
            </div>

            <div className="space-y-3 pt-2">
              <div className="bg-slate-50 border border-slate-100 p-3.5 rounded-[10px] space-y-2.5">
                <div>
                  <span className="text-[9px] text-slate-400 font-bold uppercase block leading-none">Usuário do Gestor</span>
                  <span className="text-xs font-bold text-slate-800 block mt-1">Gestor de Conteúdos</span>
                </div>
                <div>
                  <span className="text-[9px] text-slate-400 font-bold uppercase block leading-none">Especialidade Principal</span>
                  <span className="text-xs font-semibold text-slate-700 block mt-1">Design de Interfaces & Novas Mídias</span>
                </div>
                <div>
                  <span className="text-[9px] text-slate-400 font-bold uppercase block leading-none">PIN de Acesso</span>
                  <span className="text-xs font-mono font-bold text-[#3B82F6] block mt-1">5678 ou 1234</span>
                </div>
              </div>
            </div>
          </div>

          {/* Master Professors list cards */}
          <div className="lg:col-span-8 bg-white border border-slate-200 p-5 rounded-[10px] space-y-4 professors-list-container">
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
                  className="w-full sm:w-64 pl-8 pr-3 py-1.5 text-[11px] border border-slate-200 rounded-md max-w-full text-slate-700 bg-slate-50 focus:bg-white transition-colors focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {professorsList.filter(prof => {
                if (!professorSearchQuery) return true;
                return prof.name.toLowerCase().includes(professorSearchQuery.toLowerCase());
              }).map((prof, idx) => {
                const assignedCourses = courses.filter(c => c.instructorId === prof.id);
                return (
                  <div key={prof.id} className="border border-slate-100 p-4 rounded-[10px] bg-slate-50/40 relative group">
                    <div className="absolute top-4 right-4 flex items-center gap-1.5">
                      <span className="text-[9px] bg-slate-200 font-mono font-bold px-1.5 py-0.5 rounded text-slate-600">
                        ID: GESTOR-01
                      </span>
                    </div>
                    <strong className="block font-black text-slate-900 text-xs pr-24">{prof.name}</strong>
                    <span className="text-[10px] font-bold text-blue-600 tracking-wide uppercase block mt-1">Coordenação Geral de Conteúdos</span>
                    <span className="text-[10px] text-slate-400 block mt-2">Trilhas sob Gestão: {assignedCourses.length}</span>
                    <div className="mt-2.5 pt-2.5 border-t border-slate-100 flex flex-wrap gap-1">
                      {assignedCourses.length === 0 ? (
                        <span className="text-slate-400 text-[10px] italic">Nenhuma disciplina vinculada</span>
                      ) : (
                        assignedCourses.map(c => (
                          <span key={`${c.id}-${idx}`} className="text-[9px] bg-white border border-slate-100 text-slate-700 px-1.5 py-0.5 rounded">
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
        </div>
      )}

      {activeTab === 'courses' && (
        <div className="space-y-4 text-left animate-in fade-in duration-300">
          <div>
            <BackButton onClick={() => setActiveTab('analytics')} text="Voltar ao Painel Administrativo" />
          </div>
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          
          {/* Create custom Course Block */}
          <div className="lg:col-span-4 bg-white border border-slate-200 p-5 rounded-[10px] h-fit space-y-4">
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
                  className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Área Acadêmica / Categoria</label>
                <select
                  value={newCourseCategory}
                  onChange={(e) => setNewCourseCategory(e.target.value)}
                  className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800 bg-white"
                >
                  {categoriesList.map((cat) => (
                    <option key={cat} value={cat}>{cat}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Professor Responsável</label>
                <select
                  value={newCourseTeacherId}
                  onChange={(e) => setNewCourseTeacherId(e.target.value)}
                  className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800 bg-white"
                >
                  {professorsList.map((prof) => (
                    <option key={prof.id} value={prof.id}>{prof.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Ementa / Descrição Curta (Opcional)</label>
                <textarea
                  placeholder="Visão abrangente para orientar a admissão dos alunos..."
                  value={newCourseDesc}
                  onChange={(e) => setNewCourseDesc(e.target.value)}
                  className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800 h-16 resize-none"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1 flex items-center justify-between">
                  <span>Vigência de Exibição / Validade do Contrato</span>
                  <span className="text-[8px] bg-amber-50 text-amber-700 border border-amber-200 px-1 rounded uppercase font-black">Proteção Jurídica</span>
                </label>
                <input
                  type="date"
                  value={newCourseExpiration}
                  onChange={(e) => setNewCourseExpiration(e.target.value)}
                  className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800 bg-white"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tipo de Curso</label>
                  <select
                    value={newCourseType}
                    onChange={(e) => setNewCourseType(e.target.value as 'fixo' | 'ao_vivo')}
                    className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800 bg-white"
                  >
                    <option value="fixo">Gravado (Fixo)</option>
                    <option value="ao_vivo">Síncrono (Ao Vivo)</option>
                  </select>
                </div>

                <div className="flex items-end pb-1.5">
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input 
                      type="checkbox" 
                      className="rounded text-blue-600 focus:ring-blue-500 h-4 w-4 border-slate-300"
                      checked={newCourseHasChat}
                      onChange={(e) => setNewCourseHasChat(e.target.checked)}
                    />
                    <span className="text-xs font-bold text-slate-700">Ativar Chat do Curso</span>
                  </label>
                </div>
              </div>

              <button
                type="submit"
                className="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2 px-3 rounded-md text-xs transition-colors cursor-pointer"
              >
                Disponibilizar Curso no Catálogo
              </button>
            </form>

            {/* Habilitar Nova Área de Atuação */}
            <div className="border-t border-slate-100 pt-4 mt-2 space-y-3">
              <div>
                <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                  <Layers className="h-3.5 w-3.5 text-blue-600" />
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
                  className="flex-1 border border-slate-200 p-1.5 text-xs rounded-md text-slate-800"
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
                  className="bg-blue-600 hover:bg-blue-700 text-white font-bold px-3 text-xs rounded-md transition-colors cursor-pointer"
                >
                  Criar
                </button>
              </div>

              {/* Badges for active categories */}
              <div className="space-y-1">
                <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Áreas Ativas</span>
                <div className="flex flex-wrap gap-1 max-h-24 overflow-y-auto pr-1">
                  {categoriesList.map(cat => (
                    <span key={cat} className="bg-blue-50 border border-blue-100 text-[9px] font-mono font-bold text-blue-700 px-1.5 py-0.5 rounded-sm">
                      {cat}
                    </span>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* Master Course lists details */}
          <div className="lg:col-span-8 bg-white border border-slate-200 p-5 rounded-[10px] space-y-4">
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-100 pb-2">
              <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider">
                Cursos Ativos no Catálogo ({courses.length})
              </h3>
              
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                <input
                  type="text"
                  placeholder="Pesquisar termo de disciplina..."
                  value={courseSearchQuery}
                  onChange={(e) => setCourseSearchQuery(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-200 pl-8 pr-3 py-1.8 text-[11px] rounded-md text-slate-700"
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
                .map((course, idx) => {
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
                    <div key={`${course.id}-${idx}`} className="p-4 border border-slate-100 bg-slate-50/30 rounded-[10px] text-xs flex flex-col gap-3">
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
                              <span className="font-bold text-slate-400 uppercase tracking-wide">Trocar Professor:</span>
                              <select
                                value={course.instructorId ?? ''}
                                onChange={(e) => {
                                  const newTeacher = professorsList.find(p => p.id === e.target.value);
                                  if (!newTeacher) return;
                                  updateCourseInstructor(course.id, newTeacher.id);
                                  showToast(`Professor do curso "${course.title}" modificado com sucesso para ${newTeacher.name}!`);
                                }}
                                className="border border-slate-200 bg-white p-0.5 px-1.5 text-[10px] rounded-md font-bold text-slate-700 cursor-pointer focus:outline-hidden"
                              >
                                <option value="" disabled>Selecionar…</option>
                                {professorsList.map((prof) => (
                                  <option key={prof.id} value={prof.id}>{prof.name}</option>
                                ))}
                              </select>
                            </div>
                            
                            <div className="flex items-center gap-1.5">
                              <span className="font-bold text-slate-400 uppercase tracking-wide">Tipo:</span>
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
                                  className="rounded text-blue-600 focus:ring-blue-500 h-3 w-3 border-slate-300"
                                  checked={course.hasChat !== false} // default true
                                  onChange={(e) => {
                                    updateCourseProps(course.id, { hasChat: e.target.checked });
                                    showToast(`Chat do curso "${course.title}" ${e.target.checked ? 'ativado' : 'desativado'}!`);
                                  }}
                                />
                                <span className="font-bold text-slate-600 tracking-wide">Ativar Chat</span>
                              </label>
                            </div>

                            <div className="flex items-center gap-1 mt-0.5 sm:mt-0">
                              <span className="font-bold text-slate-400 uppercase tracking-wide">Vigência:</span>
                              <input
                                type="date"
                                value={course.contractExpirationDate || ''}
                                onChange={(e) => {
                                  updateCourseProps(course.id, { contractExpirationDate: e.target.value || undefined });
                                  showToast(`Vigência do curso "${course.title}" atualizada para ${e.target.value || 'Sem limite (Permanente)'}!`);
                                }}
                                className="border border-slate-200 bg-white p-0.5 px-1.5 text-[10px] rounded-md font-bold text-slate-700 cursor-pointer focus:outline-hidden"
                              />
                            </div>
                          </div>
                        </div>

                        {/* Status & Deletion section */}
                        <div className="flex flex-row md:flex-col items-center gap-2 shrink-0 self-start md:self-auto justify-between md:justify-start w-full md:w-auto">
                          {isCourseExpired(course.contractExpirationDate) ? (
                            <div className="flex flex-col gap-1.5 items-center shrink-0">
                              <span className="text-[10px] bg-amber-50 text-amber-700 border border-amber-100 font-black px-2.5 py-1 rounded-md uppercase flex items-center gap-1 shrink-0" title="Expirado preventivamente para segurança jurídica">
                                ⚠️ Expirado (Arquivado)
                              </span>
                              <button
                                onClick={() => {
                                  // Extend validity by 1 year
                                  const futureDate = new Date();
                                  futureDate.setFullYear(futureDate.getFullYear() + 1);
                                  const dateStr = futureDate.toISOString().split('T')[0];
                                  updateCourseProps(course.id, { contractExpirationDate: dateStr });
                                  showToast(`Curso "${course.title}" reativado! Nova vigência prorrogada até ${dateStr}.`);
                                  speakText(`Curso reativado com sucesso.`);
                                }}
                                className="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[9px] uppercase rounded-md transition-colors cursor-pointer shadow-sm whitespace-nowrap"
                                title="Renovar vigência por mais 1 ano"
                              >
                                Reativar Curso
                              </button>
                            </div>
                          ) : (
                            <span className="text-[10px] bg-blue-50 text-blue-700 border border-blue-100 font-black px-2.5 py-1 rounded-md uppercase shrink-0">
                              Ativo no AVA
                            </span>
                          )}
                          
                          <button
                            onClick={() => {
                              if (window.confirm(`Tem certeza que deseja excluir permanentemente o curso "${course.title}"?`)) {
                                deleteCourse(course.id);
                                showToast(`Curso "${course.title}" foi excluído com sucesso!`);
                              }
                            }}
                            className="p-1 px-2.5 hover:bg-red-50 text-red-600 hover:text-red-700 border border-slate-200 hover:border-red-200 rounded-md transition-all cursor-pointer flex items-center gap-1 font-bold text-[10px]"
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
                          className="flex items-center gap-1 text-[11px] text-[#3B82F6] font-bold hover:underline cursor-pointer"
                        >
                          <Users className="h-3.5 w-3.5 text-[#3B82F6]" />
                          <span>Alunos Ativos ({activeStudents.length}): {activeStudents.map(s => s.name).join(', ')}</span>
                          <span className="text-[9px] text-slate-400 font-normal">({expandedCourseStudentsId === course.id ? 'Ocultar' : 'Ver Detalhes'})</span>
                        </button>
                        
                        {expandedCourseStudentsId === course.id && (
                          <div className="mt-2 bg-slate-50 border border-slate-100 rounded-[10px] p-3 space-y-2 animate-in fade-in duration-200">
                            <span className="text-[9px] font-extrabold text-slate-400 block uppercase tracking-wider">Identificação dos Alunos Vinculados</span>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                              {activeStudents.map((std, index) => (
                                <div key={index} className="flex items-center gap-2.5 bg-white border border-slate-100 p-2 rounded-md">
                                  <div className="h-6 w-6 bg-blue-50 border border-blue-100 text-blue-700 text-10 font-bold flex items-center justify-center rounded-full font-sans uppercase">
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
        </div>
      )}

      {activeTab === 'students' && (
        <div className="space-y-6 text-left animate-in fade-in duration-300">
          <div>
            <BackButton onClick={() => setActiveTab('analytics')} text="Voltar ao Painel Administrativo" />
          </div>
          
          {/* Top Title Card */}
          <div className="bg-white border border-slate-200/80 rounded-[10px] p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
            <div className="space-y-1">
              <h3 className="text-base font-black text-slate-950 uppercase tracking-wider flex items-center gap-2">
                <Users className="h-5 w-5 text-blue-600" />
                <span>Gestão Estratégica & Acompanhamento de Alunos</span>
              </h3>
              <p className="text-xs text-slate-500 max-w-3xl leading-relaxed">
                Monitore matrículas, evolução de carga horária, níveis de engajamento, pendências documentais e de termos de compromisso para acompanhamento preventivo de forma prática e segura.
              </p>
            </div>
            
            <button
              onClick={() => {
                showToast("Relatório analítico consolidado exportado com sucesso.");
                speakText("Relatório consolidado de alunos gerado com sucesso.");
              }}
              className="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded-[10px] text-xs transition-colors flex items-center gap-2 cursor-pointer w-fit shrink-0 shadow-sm"
            >
              <Download className="h-4 w-4" />
              <span>Exportar Dados Consolidados</span>
            </button>
          </div>

          {/* Cards de Resumo */}
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            {/* Total Alunos */}
            <button 
              onClick={() => setActiveQuickFilter('all')}
              className={`text-left p-4 rounded-[10px] space-y-1 flex flex-col justify-between hover:shadow-sm transition-all border cursor-pointer ${
                activeQuickFilter === 'all' 
                  ? "bg-slate-100 border-slate-400 shadow-sm" 
                  : "bg-slate-50/40 border-slate-200 hover:border-slate-300"
              }`}
            >
              <span className={`text-[9px] font-black uppercase tracking-widest font-mono ${activeQuickFilter === 'all' ? "text-slate-900" : "text-slate-400"}`}>Total Geral</span>
              <div className="flex items-baseline justify-between mt-1">
                <span className="text-2xl font-black text-slate-900 font-mono">{totalStudentsCount}</span>
                <span className={`p-1.5 rounded-md ${activeQuickFilter === 'all' ? "bg-slate-900 text-white" : "bg-slate-100 text-slate-600"}`}>
                  <Users className="h-4 w-4" />
                </span>
              </div>
              <span className="text-[9px] text-slate-400">Alunos cadastrados</span>
            </button>

            {/* Ativos */}
            <button 
              onClick={() => setActiveQuickFilter('ativos')}
              className={`text-left p-4 rounded-[10px] space-y-1 flex flex-col justify-between hover:shadow-sm transition-all border cursor-pointer ${
                activeQuickFilter === 'ativos' 
                  ? "bg-blue-50 border-blue-400 shadow-sm" 
                  : "bg-slate-50/40 border-slate-200 hover:border-slate-300"
              }`}
            >
              <span className={`text-[9px] font-black uppercase tracking-widest font-mono ${activeQuickFilter === 'ativos' ? "text-blue-700" : "text-slate-400"}`}>Matrícula Ativa</span>
              <div className="flex items-baseline justify-between mt-1">
                <span className="text-2xl font-black text-slate-900 font-mono">{activeMatriculasCount}</span>
                <span className={`p-1.5 rounded-md ${activeQuickFilter === 'ativos' ? "bg-blue-600 text-white" : "bg-slate-100 text-slate-500"}`}>
                   <UserCheck className="h-4 w-4" />
                </span>
              </div>
              <span className="text-[9px] text-slate-400">Regulares no AVA</span>
            </button>

            {/* Sem Matrícula */}
            <button 
              onClick={() => setActiveQuickFilter('sem_matricula')}
              className={`text-left p-4 rounded-[10px] space-y-1 flex flex-col justify-between hover:shadow-sm transition-all border cursor-pointer ${
                activeQuickFilter === 'sem_matricula' 
                  ? "bg-slate-100 border-slate-900 shadow-sm" 
                  : "bg-slate-50/40 border-slate-200 hover:border-slate-300"
              }`}
            >
              <span className={`text-[9px] font-black uppercase tracking-widest font-mono ${activeQuickFilter === 'sem_matricula' ? "text-slate-900" : "text-slate-400"}`}>Sem Matrícula</span>
              <div className="flex items-baseline justify-between mt-1">
                <span className="text-2xl font-black text-slate-950 font-mono">{semMatriculaCount}</span>
                <span className={`p-1.5 rounded-md ${activeQuickFilter === 'sem_matricula' ? "bg-slate-900 text-white" : "bg-slate-100 text-slate-500"}`}>
                  <XCircle className="h-4 w-4" />
                </span>
              </div>
              <span className="text-[9px] text-slate-400">Aguardando vínculo</span>
            </button>

            {/* Pendências */}
            <button 
              onClick={() => setActiveQuickFilter('pendencias')}
              className={`text-left p-4 rounded-[10px] space-y-1 flex flex-col justify-between hover:shadow-sm transition-all border cursor-pointer ${
                activeQuickFilter === 'pendencias' 
                  ? "bg-amber-100 border-amber-500 shadow-sm" 
                  : comPendenciasCount > 0 
                    ? "bg-amber-50/40 border-amber-200" 
                    : "bg-slate-50/40 border-slate-200"
              }`}
            >
              <span className={`text-[9px] font-black uppercase tracking-widest font-mono ${
                activeQuickFilter === 'pendencias' ? "text-amber-800" : comPendenciasCount > 0 ? "text-amber-700" : "text-slate-400"
              }`}>Com Pendências</span>
              <div className="flex items-baseline justify-between mt-1">
                <span className={`text-2xl font-black font-mono ${
                  activeQuickFilter === 'pendencias' ? "text-amber-900" : comPendenciasCount > 0 ? "text-amber-800" : "text-slate-900"
                }`}>{comPendenciasCount}</span>
                <span className={`p-1.5 rounded-md ${
                  activeQuickFilter === 'pendencias' ? "bg-amber-600 text-white" : comPendenciasCount > 0 ? "bg-amber-100 text-amber-700" : "bg-slate-100 text-slate-500"
                }`}>
                  <AlertTriangle className="h-4 w-4" />
                </span>
              </div>
              <span className="text-[9px] text-slate-400">Exige regularização</span>
            </button>

            {/* Inativo há > 7 dias */}
            <button 
              onClick={() => setActiveQuickFilter('ausentes')}
              className={`text-left p-4 rounded-[10px] space-y-1 flex flex-col justify-between hover:shadow-sm transition-all border cursor-pointer ${
                activeQuickFilter === 'ausentes' 
                  ? "bg-orange-100 border-orange-500 shadow-sm" 
                  : semAcessoRecenteCount > 0 
                    ? "bg-orange-50/40 border-orange-200" 
                    : "bg-slate-50/40 border-slate-200"
              }`}
            >
              <span className={`text-[9px] font-black uppercase tracking-widest font-mono ${
                activeQuickFilter === 'ausentes' ? "text-orange-800" : semAcessoRecenteCount > 0 ? "text-orange-700" : "text-slate-400"
              }`}>Ausentes &gt; 7d</span>
              <div className="flex items-baseline justify-between mt-1">
                <span className={`text-2xl font-black font-mono ${
                  activeQuickFilter === 'ausentes' ? "text-orange-900" : semAcessoRecenteCount > 0 ? "text-orange-800" : "text-slate-900"
                }`}>{semAcessoRecenteCount}</span>
                <span className={`p-1.5 rounded-md ${
                  activeQuickFilter === 'ausentes' ? "bg-orange-600 text-white" : semAcessoRecenteCount > 0 ? "bg-orange-100 text-orange-700" : "bg-slate-100 text-slate-500"
                }`}>
                  <Clock className="h-4 w-4" />
                </span>
              </div>
              <span className="text-[9px] text-slate-400">Sem acesso recente</span>
            </button>

            {/* Evasão / Crítico */}
            <button 
              onClick={() => setActiveQuickFilter('evasao')}
              className={`text-left p-4 rounded-[10px] space-y-1 flex flex-col justify-between hover:shadow-sm transition-all border cursor-pointer ${
                activeQuickFilter === 'evasao' 
                  ? "bg-red-100 border-red-500 shadow-sm" 
                  : emRiscoEvasaoCount > 0 
                    ? "bg-red-50/40 border-red-200" 
                    : "bg-slate-50/40 border-slate-200"
              }`}
            >
              <span className={`text-[9px] font-black uppercase tracking-widest font-mono ${
                activeQuickFilter === 'evasao' ? "text-red-800" : emRiscoEvasaoCount > 0 ? "text-red-700" : "text-slate-400"
              }`}>Risco Evasão</span>
              <div className="flex items-baseline justify-between mt-1">
                <span className={`text-2xl font-black font-mono ${
                  activeQuickFilter === 'evasao' ? "text-red-900" : emRiscoEvasaoCount > 0 ? "text-red-800" : "text-slate-900"
                }`}>{emRiscoEvasaoCount}</span>
                <span className={`p-1.5 rounded-md ${
                  activeQuickFilter === 'evasao' ? "bg-red-600 text-white" : emRiscoEvasaoCount > 0 ? "bg-red-100 text-red-700 animate-pulse" : "bg-slate-100 text-slate-500"
                }`}>
                  <AlertCircle className="h-4 w-4" />
                </span>
              </div>
              <span className="text-[9px] text-slate-400">Necessita contato</span>
            </button>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            {/* Left Column: Enrollment Form */}
            <div className="lg:col-span-3 bg-white border border-slate-200/80 p-5 rounded-[10px] h-fit space-y-4 shadow-sm">
              <div>
                <h4 className="text-xs font-black text-slate-950 uppercase tracking-wider">Matricular Novo Aluno</h4>
                <p className="text-[11px] text-slate-400 mt-1 leading-normal">Vincule e configure os parâmetros de acesso do aluno com segurança.</p>
              </div>

              <form onSubmit={handleCreateStudent} className="space-y-3.5">
                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Nome Completo</label>
                  <input
                    type="text"
                    required
                    placeholder="Ex: Clara Ribeiro"
                    value={newStudentName}
                    onChange={(e) => setNewStudentName(e.target.value)}
                    className="w-full border border-slate-200 p-2.5 text-xs rounded-md text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-slate-400"
                  />
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">E-mail Acadêmico</label>
                  <input
                    type="email"
                    required
                    placeholder="Ex: clara.ribeiro@lms.edu"
                    value={newStudentEmail}
                    onChange={(e) => setNewStudentEmail(e.target.value)}
                    className="w-full border border-slate-200 p-2.5 text-xs rounded-md text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-slate-400"
                  />
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Curso de Matrícula</label>
                  <button
                    type="button"
                    onClick={() => setShowCoursePickerModal(true)}
                    className="w-full border border-slate-200 p-2.5 text-left text-xs rounded-md text-slate-700 bg-slate-50/50 hover:bg-slate-100 transition-colors flex items-center justify-between group cursor-pointer"
                  >
                    <span className="truncate">
                      {selectedEnrollCourseId 
                        ? courses.find(c => c.id === selectedEnrollCourseId)?.title 
                        : "Selecionar Disciplina..."}
                    </span>
                    <BookOpen className="h-3.5 w-3.5 text-slate-400 group-hover:text-blue-600 transition-colors shrink-0" />
                  </button>
                  {selectedEnrollCourseId && (
                    <div className="mt-2 p-2 bg-emerald-50 border border-emerald-100 rounded-md animate-in fade-in duration-200">
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
                  <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center justify-between">
                    <span>Senha Inicial</span>
                    <span className="text-[8.5px] text-slate-400 font-normal normal-case">Padrão: 1234</span>
                  </label>
                  <div className="relative">
                    <input
                      type={showStudentPassword ? 'text' : 'password'}
                      placeholder="Defina a senha"
                      value={newStudentPassword}
                      onChange={(e) => setNewStudentPassword(e.target.value)}
                      className="w-full border border-slate-200 p-2.5 pr-10 text-xs rounded-md text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-slate-400 font-mono"
                    />
                    <button
                      type="button"
                      onClick={() => setShowStudentPassword(!showStudentPassword)}
                      className="absolute right-2.5 top-2.5 text-slate-400 hover:text-blue-600 transition-colors cursor-pointer"
                    >
                      {showStudentPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-2">
                  <div className="col-span-2">
                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Município</label>
                    <input
                      type="text"
                      placeholder="Ex: Recife"
                      value={newStudentMunicipio}
                      onChange={(e) => setNewStudentMunicipio(e.target.value)}
                      className="w-full border border-slate-200 p-2.5 text-xs rounded-md text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-slate-400"
                    />
                  </div>
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">UF</label>
                    <input
                      type="text"
                      maxLength={2}
                      placeholder="PE"
                      value={newStudentUf}
                      onChange={(e) => setNewStudentUf(e.target.value.toUpperCase())}
                      className="w-full border border-slate-200 p-2.5 text-xs rounded-md text-slate-800 text-center uppercase focus:outline-hidden focus:ring-1 focus:ring-slate-400 font-mono"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Área de Interesse</label>
                  <input
                    type="text"
                    placeholder="Ex: Economia Criativa & IA"
                    value={newStudentAreaInteresse}
                    onChange={(e) => setNewStudentAreaInteresse(e.target.value)}
                    className="w-full border border-slate-200 p-2.5 text-xs rounded-md text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-slate-400"
                  />
                </div>

                <button
                  type="submit"
                  className="w-full bg-slate-900 hover:bg-slate-800 text-white font-extrabold py-3 px-3 rounded-[10px] text-xs transition-colors cursor-pointer flex items-center justify-center gap-1.5 shadow-sm"
                >
                  <Plus className="h-3.5 w-3.5" />
                  <span>Matricular Aluno</span>
                </button>
              </form>

              {/* Quick instructions indicator */}
              <div className="p-3 bg-slate-50 border border-slate-100 rounded-[10px] space-y-1 text-left">
                <span className="text-[9px] font-black text-slate-500 uppercase tracking-widest block">Segurança Integrada</span>
                <p className="text-[10px] text-slate-400 leading-relaxed">
                  Senhas são mantidas ocultas. Utilize redefinições seguras na tabela e link de acesso direto sem exibir credenciais em texto aberto.
                </p>
              </div>
            </div>

            {/* Right Column: Redesigned student panel with filters, search, sort and action menu */}
            <div className="lg:col-span-9 bg-white border border-slate-200/80 p-5 rounded-[10px] space-y-5 shadow-sm">
              
              {/* Search, Filter Bar and Sorting */}
              <div className="space-y-4 bg-slate-50/50 p-5 rounded-[10px] border border-slate-200/60 text-left">
                {/* Header row */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                  <span className="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                    <SlidersHorizontal className="h-4 w-4 text-blue-600" />
                    <span>Filtros Estratégicos & Ordenação</span>
                  </span>
                  <button
                    onClick={() => {
                      setFilterCourse('all');
                      setFilterTurma('all');
                      setFilterStatusMatricula('all');
                      setFilterPendencia('all');
                      setFilterLastAccess('all');
                      setFilterRisco('all');
                      setStudentSearchQuery('');
                      setSortBy('name');
                      setSortOrder('asc');
                      setActiveQuickFilter('all');
                      showToast("Filtros redefinidos para os valores padrão.");
                    }}
                    className="text-[10px] font-bold text-slate-400 hover:text-blue-600 transition-colors flex items-center gap-1 cursor-pointer w-fit"
                  >
                    <RefreshCw className="h-3 w-3" />
                    <span>Limpar Filtros</span>
                  </button>
                </div>

                {/* Primary row: Search & Sorting */}
                <div className="grid grid-cols-1 md:grid-cols-12 gap-3.5">
                  <div className="md:col-span-6 relative">
                    <label className="block text-[9px] font-black text-slate-400 uppercase tracking-wider mb-1">Buscar Aluno</label>
                    <div className="relative">
                      <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                      <input
                        type="text"
                        placeholder="Nome completo, e-mail acadêmico ou RA..."
                        value={studentSearchQuery}
                        onChange={(e) => setStudentSearchQuery(e.target.value)}
                        className="w-full bg-white border border-slate-200 pl-8 pr-3 py-1.5 text-xs rounded-md text-slate-700 focus:outline-hidden focus:ring-1 focus:ring-slate-400 placeholder:text-slate-300"
                      />
                    </div>
                  </div>

                  <div className="md:col-span-4">
                    <label className="block text-[9px] font-black text-slate-400 uppercase tracking-wider mb-1">Ordenar Registros</label>
                    <select
                      value={sortBy}
                      onChange={(e) => setSortBy(e.target.value as any)}
                      className="w-full bg-white border border-slate-200 p-1.5 text-xs rounded-md text-slate-700 focus:outline-hidden"
                    >
                      <option value="name">Nome do Aluno</option>
                      <option value="lastAccess">Último Acesso</option>
                      <option value="progress">Progresso Acadêmico</option>
                      <option value="pendency">Pendências</option>
                      <option value="statusMatricula">Status Matrícula</option>
                      <option value="risk">Risco de Evasão</option>
                    </select>
                  </div>

                  <div className="md:col-span-2 flex items-end">
                    <button
                      onClick={() => setSortOrder(prev => prev === 'asc' ? 'desc' : 'asc')}
                      className="w-full py-1.5 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 rounded-md text-xs transition-colors cursor-pointer shrink-0 flex items-center justify-center gap-1 font-bold"
                      title="Alternar Ordem"
                    >
                      <span>{sortOrder === 'asc' ? 'A-Z' : 'Z-A'}</span>
                      <span className="text-[9px] opacity-70">{sortOrder === 'asc' ? '↑' : '↓'}</span>
                    </button>
                  </div>
                </div>

                {/* Secondary row: Multi-filters */}
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 pt-1 border-t border-slate-100">
                  {/* Course Filter */}
                  <div>
                    <label className="block text-[9px] font-black text-slate-400 uppercase tracking-wider mb-1">Curso</label>
                    <select
                      value={filterCourse}
                      onChange={(e) => setFilterCourse(e.target.value)}
                      className="w-full bg-white border border-slate-200 p-1.5 text-xs rounded-md text-slate-700 focus:outline-hidden"
                    >
                      <option value="all">Todos</option>
                      <option value="none">Sem Matrícula</option>
                      {Array.from(new Set(courses.map(c => c.title))).map(title => (
                        <option key={title} value={title}>{title}</option>
                      ))}
                    </select>
                  </div>

                  {/* Turma Filter */}
                  <div>
                    <label className="block text-[9px] font-black text-slate-400 uppercase tracking-wider mb-1">Turma</label>
                    <select
                      value={filterTurma}
                      onChange={(e) => setFilterTurma(e.target.value)}
                      className="w-full bg-white border border-slate-200 p-1.5 text-xs rounded-md text-slate-700 focus:outline-hidden"
                    >
                      <option value="all">Todas</option>
                      <option value="none">Sem Turma</option>
                      <option value="2025.1">2025.1</option>
                      <option value="2025.2">2025.2</option>
                    </select>
                  </div>

                  {/* Status Matrícula Filter */}
                  <div>
                    <label className="block text-[9px] font-black text-slate-400 uppercase tracking-wider mb-1">Matrícula</label>
                    <select
                      value={filterStatusMatricula}
                      onChange={(e) => setFilterStatusMatricula(e.target.value)}
                      className="w-full bg-white border border-slate-200 p-1.5 text-xs rounded-md text-slate-700 focus:outline-hidden"
                    >
                      <option value="all">Todos</option>
                      <option value="Ativa">Ativa</option>
                      <option value="Sem matrícula">Sem Matrícula</option>
                      <option value="Trancada">Trancada</option>
                      <option value="Concluída">Concluída</option>
                      <option value="Cancelada">Cancelada</option>
                    </select>
                  </div>

                  {/* Pendência Filter */}
                  <div>
                    <label className="block text-[9px] font-black text-slate-400 uppercase tracking-wider mb-1">Pendências</label>
                    <select
                      value={filterPendencia}
                      onChange={(e) => setFilterPendencia(e.target.value)}
                      className="w-full bg-white border border-slate-200 p-1.5 text-xs rounded-md text-slate-700 focus:outline-hidden"
                    >
                      <option value="all">Todas</option>
                      <option value="Nenhuma">Nenhuma</option>
                      <option value="Termo de Compromisso">Termo de Compromisso</option>
                      <option value="Documento">Documental</option>
                      <option value="Atividade">Acadêmica</option>
                      <option value="Matrícula pendente">Matrícula Pendente</option>
                      <option value="Contrato">Contrato Pendente</option>
                    </select>
                  </div>

                  {/* Risco Evasão Filter */}
                  <div>
                    <label className="block text-[9px] font-black text-slate-400 uppercase tracking-wider mb-1">Risco Evasão</label>
                    <select
                      value={filterRisco}
                      onChange={(e) => setFilterRisco(e.target.value)}
                      className="w-full bg-white border border-slate-200 p-1.5 text-xs rounded-md text-slate-700 focus:outline-hidden"
                    >
                      <option value="all">Todos</option>
                      <option value="Normal">Normal (Regular)</option>
                      <option value="Atenção">Atenção</option>
                      <option value="Risco">Risco</option>
                      <option value="Crítico">Crítico</option>
                    </select>
                  </div>
                </div>
              </div>

              {/* Students Desktop Table */}
              <div className="hidden md:block overflow-x-auto border border-slate-200/80 rounded-[10px]">
                <table className="w-full text-left border-collapse text-xs">
                  <thead>
                    <tr className="border-b border-slate-200 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50/70">
                      <th className="p-4 py-3">Aluno / RA</th>
                      <th className="p-4 py-3">Curso / Turma</th>
                      <th className="p-4 py-3">Status Matrícula</th>
                      <th className="p-4 py-3">Progresso Letivo</th>
                      <th className="p-4 py-3">Último Acesso</th>
                      <th className="p-4 py-3">Pendências</th>
                      <th className="p-4 py-3 text-right">Gestão</th>
                    </tr>
                  </thead>
                  <tbody>
                    {sortedStudents.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="text-center p-10 text-slate-400 text-xs font-bold bg-slate-50/20">
                          Nenhum aluno corresponde aos filtros de busca aplicados.
                        </td>
                      </tr>
                    ) : (
                      sortedStudents.map((st, idx) => {
                        const initials = st.name.split(' ').map(x => x[0]).join('').slice(0, 2).toUpperCase();
                        
                        return (
                          <tr key={`${st.email}-${idx}`} className="border-b border-slate-100 hover:bg-slate-50/40 transition-colors duration-150">
                            {/* ALUNO */}
                            <td className="p-4 align-middle">
                              <div className="flex items-center gap-3">
                                <div className="relative shrink-0">
                                  <div className="h-9 w-9 bg-slate-100 border border-slate-200 text-slate-600 text-[11px] font-extrabold flex items-center justify-center rounded-full tracking-tight">
                                    {initials}
                                  </div>
                                  {st.riskLevel === 'Crítico' && (
                                    <span className="absolute -bottom-0.5 -right-0.5 flex h-2.5 w-2.5">
                                      <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                      <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-600 border border-white"></span>
                                    </span>
                                  )}
                                  {st.riskLevel === 'Risco' && (
                                    <span className="absolute -bottom-0.5 -right-0.5 flex h-2.5 w-2.5">
                                      <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-orange-500 border border-white"></span>
                                    </span>
                                  )}
                                  {st.riskLevel === 'Atenção' && (
                                    <span className="absolute -bottom-0.5 -right-0.5 flex h-2.5 w-2.5">
                                      <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-400 border border-white"></span>
                                    </span>
                                  )}
                                </div>
                                <div className="text-left">
                                  <span 
                                    className="font-extrabold text-slate-900 hover:text-blue-600 transition-colors block leading-tight text-sm cursor-pointer" 
                                    onClick={() => setActiveStudentProfile(st.name)}
                                  >
                                    {st.name}
                                  </span>
                                  <span className="text-[10px] text-slate-400 block mt-0.5 font-mono">{st.email} • RA: {st.ra}</span>
                                </div>
                              </div>
                            </td>

                            {/* CURSO / TURMA */}
                            <td className="p-4 align-middle">
                              {st.curso === '—' ? (
                                <span className="inline-block px-2 py-0.5 rounded bg-slate-100 text-slate-400 text-[10px] font-bold">
                                  Sem matrícula ativa
                                </span>
                              ) : (
                                <div className="space-y-0.5">
                                  <span className="text-[11.5px] font-extrabold text-slate-700 block leading-tight">{st.curso}</span>
                                  <span className="text-[10px] text-slate-400 block font-mono">
                                    Turma {st.turma} • {st.polo || 'Digital'}
                                  </span>
                                </div>
                              )}
                            </td>

                            {/* STATUS MATRÍCULA */}
                            <td className="p-4 align-middle">
                              {(() => {
                                let badgeColor = "bg-slate-50 text-slate-500 border-slate-200";
                                if (st.statusMatricula === 'Ativa') badgeColor = "bg-slate-100/85 text-slate-700 border-slate-200/60 font-semibold";
                                else if (st.statusMatricula === 'Trancada') badgeColor = "bg-amber-50 text-amber-850 border-amber-200/70 font-bold";
                                else if (st.statusMatricula === 'Concluída') badgeColor = "bg-blue-50 text-blue-800 border-blue-200/70 font-bold";
                                else if (st.statusMatricula === 'Cancelada') badgeColor = "bg-red-50 text-red-850 border-red-200/70 font-bold";

                                return (
                                  <span className={`inline-flex px-2 py-0.5 rounded-full text-[9px] font-black border uppercase tracking-wider ${badgeColor}`}>
                                    {st.statusMatricula}
                                  </span>
                                );
                              })()}
                            </td>

                            {/* PROGRESSO */}
                            <td className="p-4 align-middle">
                              {st.statusMatricula === 'Sem matrícula' ? (
                                <span className="text-slate-400 text-xs font-bold font-mono">—</span>
                              ) : (
                                <div className="w-28 space-y-1">
                                  <div className="flex items-center justify-between text-[10px] font-bold text-slate-600">
                                    <span className="font-extrabold">{st.progresso}%</span>
                                    <span className="text-[9px] text-slate-400 font-normal">{st.horasConcluidas}h de {st.horasTotais}h</span>
                                  </div>
                                  <div className="w-full bg-slate-100 rounded-full h-1 overflow-hidden">
                                    <div 
                                      className="bg-blue-600 h-1 rounded-full transition-all duration-500"
                                      style={{ width: `${st.progresso}%` }}
                                    />
                                  </div>
                                </div>
                              )}
                            </td>

                            {/* ÚLTIMO ACESSO */}
                            <td className="p-4 align-middle">
                              <div className="space-y-1">
                                <span className="text-[11px] font-bold text-slate-700 block leading-tight">{st.lastAccess}</span>
                                {(() => {
                                  if (st.riskLevel === 'Crítico') {
                                    return (
                                      <span className="inline-flex items-center gap-0.5 text-[8px] font-black text-red-700 bg-red-50 border border-red-100 rounded-md px-1 py-0.5 uppercase tracking-wide">
                                        <AlertCircle className="h-2.5 w-2.5 shrink-0" />
                                        <span>Risco Crítico</span>
                                      </span>
                                    );
                                  }
                                  if (st.riskLevel === 'Risco') {
                                    return (
                                      <span className="inline-flex items-center gap-0.5 text-[8px] font-black text-orange-700 bg-orange-50 border border-orange-100 rounded-md px-1 py-0.5 uppercase tracking-wide">
                                        <AlertTriangle className="h-2.5 w-2.5 shrink-0" />
                                        <span>Risco Evasão</span>
                                      </span>
                                    );
                                  }
                                  if (st.riskLevel === 'Atenção') {
                                    return (
                                      <span className="inline-flex items-center gap-0.5 text-[8px] font-black text-amber-700 bg-amber-50 border border-amber-100 rounded-md px-1 py-0.5 uppercase tracking-wide">
                                        <Clock className="h-2.5 w-2.5 shrink-0" />
                                        <span>Atenção</span>
                                      </span>
                                    );
                                  }
                                  return (
                                    <span className="text-[9px] text-slate-400 font-medium">Regular</span>
                                  );
                                })()}
                              </div>
                            </td>

                            {/* PENDÊNCIAS */}
                            <td className="p-4 align-middle">
                              <div className="flex flex-wrap gap-1 max-w-[130px]">
                                {st.pendencias.map((pend, idx) => {
                                  let style = "bg-slate-50 text-slate-400 border-slate-100 font-normal tracking-tight normal-case";
                                  if (pend === 'Nenhuma') {
                                    style = "bg-slate-50/40 text-slate-400 border-slate-100 font-medium tracking-tight normal-case";
                                  } else if (pend.includes('Termo de Compromisso')) {
                                    style = "bg-red-50 text-red-700 border-red-150 font-black";
                                  } else {
                                    style = "bg-amber-50 text-amber-800 border-amber-100 font-bold";
                                  }

                                  return (
                                    <span 
                                      key={idx} 
                                      className={`px-1.5 py-0.5 rounded-md text-[9px] border uppercase block leading-none ${style}`}
                                    >
                                      {pend}
                                    </span>
                                  );
                                })}
                              </div>
                            </td>

                            {/* AÇÕES */}
                            <td className="p-4 align-middle text-right relative">
                              <div className="flex items-center justify-end gap-1.5">
                                <button
                                  onClick={() => setActiveStudentProfile(st.name)}
                                  className="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 border border-blue-200/50 text-blue-900 font-black rounded-md text-[10px] uppercase transition-colors cursor-pointer flex items-center gap-1"
                                >
                                  <SlidersHorizontal className="h-3 w-3 text-blue-600" />
                                  <span>Gerenciar</span>
                                </button>
                                
                                <div className="relative">
                                  <button
                                    onClick={() => setActiveStudentMenu(activeStudentMenu === st.email ? null : st.email)}
                                    className="p-1.5 hover:bg-slate-100 rounded-md text-slate-400 hover:text-slate-700 transition-all cursor-pointer"
                                    title="Ações Rápidas"
                                  >
                                    <MoreVertical className="h-4 w-4" />
                                  </button>
                                  
                                  {activeStudentMenu === st.email && (
                                    <>
                                      <div 
                                        className="fixed inset-0 z-10" 
                                        onClick={() => setActiveStudentMenu(null)}
                                      />
                                      <div className="absolute right-0 mt-1 w-48 bg-white border border-slate-200 rounded-[10px] shadow-xl py-1.5 z-20 text-left animate-in fade-in slide-in-from-top-1 duration-150">
                                        <button
                                          onClick={() => {
                                            setActiveStudentMenu(null);
                                            setActiveStudentProfile(st.name);
                                          }}
                                          className="w-full px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2 cursor-pointer font-bold"
                                        >
                                          <User className="h-3.5 w-3.5 text-blue-600" />
                                          <span>Perfil & Parâmetros</span>
                                        </button>

                                        <button
                                          onClick={() => {
                                            setActiveStudentMenu(null);
                                            setSendMessageInfo({ name: st.name, email: st.email });
                                          }}
                                          className="w-full px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2 cursor-pointer font-bold"
                                        >
                                          <Mail className="h-3.5 w-3.5 text-blue-600" />
                                          <span>Enviar Notificação</span>
                                        </button>

                                        <button
                                          onClick={() => {
                                            setActiveStudentMenu(null);
                                            setResetPassInfo({ name: st.name, email: st.email });
                                          }}
                                          className="w-full px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2 cursor-pointer font-bold"
                                        >
                                          <Key className="h-3.5 w-3.5 text-orange-600" />
                                          <span>Redefinir Senha</span>
                                        </button>

                                        <button
                                          onClick={() => {
                                            setActiveStudentMenu(null);
                                            const copyLink = `https://ava.lms.edu/auto-login?email=${st.email}&token=magic_token_2025`;
                                            navigator.clipboard.writeText(copyLink);
                                            showToast(`Link de acesso seguro copiado para ${st.name}!`);
                                          }}
                                          className="w-full px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2 cursor-pointer font-semibold"
                                        >
                                          <ExternalLink className="h-3.5 w-3.5 text-blue-500" />
                                          <span>Copiar Link Mágico</span>
                                        </button>

                                        {st.pendencias.some(p => p !== 'Nenhuma') && (
                                          <button
                                            onClick={() => {
                                              setActiveStudentMenu(null);
                                              updateOverride(st.name, { pendencias: ['Nenhuma'] });
                                              showToast(`Todas as pendências acadêmicas de ${st.name} foram regularizadas.`);
                                              speakText(`As pendências de ${st.name} foram resolvidas com sucesso.`);
                                            }}
                                            className="w-full px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2 cursor-pointer font-bold"
                                          >
                                            <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                                            <span>Regularizar Pendências</span>
                                          </button>
                                        )}

                                        <div className="border-t border-slate-100 my-1" />
                                        
                                        <button
                                          onClick={() => {
                                            setActiveStudentMenu(null);
                                            if (confirm(`Tem certeza absoluta que deseja remover o estudante ${st.name} de forma definitiva?`)) {
                                              deleteStudent(st.name);
                                              showToast(`Estudante ${st.name} removido com sucesso.`);
                                            }
                                          }}
                                          className="w-full px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 flex items-center gap-2 cursor-pointer font-bold"
                                        >
                                          <Trash2 className="h-3.5 w-3.5" />
                                          <span>Excluir Aluno</span>
                                        </button>
                                      </div>
                                    </>
                                  )}
                                </div>
                              </div>
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>

              {/* Students Mobile View Cards - Extemely Polished for responsive viewport */}
              <div className="block md:hidden space-y-4">
                {sortedStudents.length === 0 ? (
                  <div className="text-center p-10 text-slate-400 text-xs font-bold bg-slate-50/20 border border-slate-200 rounded-[10px]">
                    Nenhum aluno corresponde aos filtros aplicados.
                  </div>
                ) : (
                  sortedStudents.map((st, idx) => {
                    const initials = st.name.split(' ').map(x => x[0]).join('').slice(0, 2).toUpperCase();
                    const isNormalRisk = st.riskLevel === 'Normal';
                    return (
                      <div key={`${st.email}-${idx}`} className={`bg-white border rounded-[10px] p-4.5 space-y-4 shadow-sm transition-all ${
                        isNormalRisk ? 'border-slate-200' : 'border-amber-200 bg-amber-50/10'
                      }`}>
                        
                        {/* Header: Name, Initials, Quick Control Buttons */}
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                          <div className="flex items-center gap-2.5 text-left">
                            <div className="relative shrink-0">
                              <div className="h-9 w-9 bg-slate-100 border border-slate-200 text-slate-700 text-xs font-extrabold flex items-center justify-center rounded-full uppercase">
                                {initials}
                              </div>
                              {!isNormalRisk && (
                                <span className="absolute -bottom-0.5 -right-0.5 flex h-2.5 w-2.5">
                                  <span className={`relative inline-flex rounded-full h-2.5 w-2.5 border border-white ${
                                    st.riskLevel === 'Crítico' ? 'bg-red-600 animate-pulse' : st.riskLevel === 'Risco' ? 'bg-orange-500' : 'bg-amber-400'
                                  }`}></span>
                                </span>
                              )}
                            </div>
                            <div>
                              <strong 
                                onClick={() => setActiveStudentProfile(st.name)}
                                className="text-slate-900 block font-extrabold leading-tight cursor-pointer hover:underline"
                              >
                                {st.name}
                              </strong>
                              <span className="text-[10px] text-slate-400 block font-mono mt-0.5">RA: {st.ra}</span>
                            </div>
                          </div>
                          
                          <div className="flex items-center gap-1.5">
                            <button
                              onClick={() => setActiveStudentProfile(st.name)}
                              className="p-2 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-[10px] transition-all cursor-pointer"
                              title="Gerenciar Aluno"
                            >
                              <SlidersHorizontal className="h-4 w-4" />
                            </button>
                            <button
                              onClick={() => {
                                if (confirm(`Deseja remover o estudante ${st.name} definitivamente?`)) {
                                  deleteStudent(st.name);
                                  showToast(`Estudante ${st.name} excluído.`);
                                }
                              }}
                              className="p-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-[10px] transition-all cursor-pointer"
                              title="Deletar Aluno"
                            >
                              <Trash2 className="h-4 w-4" />
                            </button>
                          </div>
                        </div>

                        {/* Meta Grid */}
                        <div className="grid grid-cols-2 gap-3.5 text-left text-xs">
                          <div>
                            <span className="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Curso</span>
                            <span className="font-extrabold text-slate-800 block mt-0.5 truncate max-w-[130px]" title={st.curso}>{st.curso}</span>
                            <span className="text-[10px] text-slate-400 block font-mono">Turma {st.turma}</span>
                          </div>

                          <div>
                            <span className="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Último Acesso</span>
                            <span className="font-extrabold text-slate-800 block mt-0.5">{st.lastAccess}</span>
                            {!isNormalRisk && (
                              <span className="inline-block mt-1 text-[8.5px] font-black text-red-700 bg-red-50 border border-red-100 rounded px-1.5 py-0.5 uppercase tracking-wider animate-pulse">
                                Risco: {st.riskLevel}
                              </span>
                            )}
                          </div>

                          <div>
                            <span className="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Situação</span>
                            <span className="font-extrabold text-slate-800 block mt-0.5 uppercase text-[10px] tracking-wide">{st.statusMatricula}</span>
                          </div>
                        </div>

                        {/* Progress Tracker (If Active) */}
                        {st.statusMatricula !== 'Sem matrícula' && (
                          <div className="space-y-1.5 bg-slate-50 p-2.5 rounded-[10px] border border-slate-100">
                            <div className="flex items-center justify-between text-[10px] font-bold text-slate-600">
                              <span>Progresso Letivo:</span>
                              <span className="font-black text-slate-800">{st.progresso}% ({st.horasConcluidas}h / {st.horasTotais}h)</span>
                            </div>
                            <div className="w-full bg-slate-200/70 rounded-full h-1 overflow-hidden">
                              <div className="bg-blue-600 h-1 rounded-full transition-all duration-300" style={{ width: `${st.progresso}%` }} />
                            </div>
                          </div>
                        )}

                        {/* Pendencies section */}
                        <div className="flex flex-wrap items-center gap-1.5 pt-1.5">
                          <span className="text-[9px] font-black text-slate-400 uppercase tracking-widest mr-1">Pendências:</span>
                          {st.pendencias.map((pend, idx) => {
                            let pillStyle = "bg-slate-50 text-slate-400 border-slate-100";
                            if (pend === 'Nenhuma') {
                              pillStyle = "bg-slate-50/55 text-slate-400 border-slate-100";
                            } else if (pend.includes('Termo de Compromisso')) {
                              pillStyle = "bg-red-50 text-red-700 border-red-200 font-bold";
                            } else {
                              pillStyle = "bg-amber-50 text-amber-800 border-amber-200 font-bold";
                            }
                            return (
                              <span key={idx} className={`px-2 py-0.5 border rounded-md text-[9px] uppercase tracking-tight ${pillStyle}`}>
                                {pend}
                              </span>
                            );
                          })}
                        </div>

                        {/* Direct Action Drawer Buttons */}
                        <div className="grid grid-cols-2 gap-2 pt-1 border-t border-slate-100">
                          <button
                            onClick={() => setSendMessageInfo({ name: st.name, email: st.email })}
                            className="w-full py-2 bg-slate-55 hover:bg-slate-100 text-slate-700 rounded-[10px] text-[10px] font-extrabold uppercase transition-colors text-center cursor-pointer border border-slate-200/80"
                          >
                            Mensagem
                          </button>
                          <button
                            onClick={() => setResetPassInfo({ name: st.name, email: st.email })}
                            className="w-full py-2 bg-slate-55 hover:bg-slate-100 text-slate-700 rounded-[10px] text-[10px] font-extrabold uppercase transition-colors text-center cursor-pointer border border-slate-200/80"
                          >
                            Nova Senha
                          </button>
                        </div>
                      </div>
                    );
                  })
                )}
              </div>

            </div>

          </div>

        </div>
      )}

      {/* Course Picker Modal */}
      {showCoursePickerModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="w-full max-w-md bg-white rounded-[10px] shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200">
            <header className="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <div className="flex items-center gap-2">
                <BookOpen className="h-4 w-4 text-blue-600" />
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

              {courses.map((course, idx) => (
                <button
                  key={`${course.id}-${idx}`}
                  onClick={() => {
                    setSelectedEnrollCourseId(course.id);
                    setShowCoursePickerModal(false);
                  }}
                  className={`w-full text-left p-3 rounded-[10px] border transition-all flex items-center justify-between group ${
                    selectedEnrollCourseId === course.id 
                    ? 'border-blue-500 bg-blue-50/50 ring-1 ring-blue-500/20' 
                    : 'border-slate-100 bg-slate-50/30 hover:border-slate-200 hover:bg-slate-50'
                  }`}
                >
                  <div className="space-y-1">
                    <p className="text-xs font-bold text-slate-900 group-hover:text-blue-700 transition-colors">{course.title}</p>
                    <div className="flex items-center gap-1.5">
                      <span className="text-[9px] bg-white border border-slate-200 px-1.5 py-0.5 rounded text-slate-500 font-mono">
                        {course.category}
                      </span>
                      <span className="text-[9px] text-slate-400">
                        Instrutor: <span className="font-bold text-slate-600 underline decoration-slate-300">{course.instructorName}</span>
                      </span>
                    </div>
                  </div>
                  {selectedEnrollCourseId === course.id && <Check className="h-4 w-4 text-blue-600" />}
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
          <div>
            <BackButton onClick={() => setActiveTab('analytics')} text="Voltar ao Painel Administrativo" />
          </div>
          
          {/* Header instructions card */}
          <div className="bg-white border border-slate-200 rounded-[10px] p-5 space-y-2">
            <h3 className="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <FileCheck className="h-4.5 w-4.5 text-blue-600" />
              <span>Central de Requerimentos Curriculares e Emissões Eletrônicas</span>
            </h3>
            <p className="text-xs text-slate-500 leading-relaxed">
              Analise, autorize ou indefira as solicitações de documentos protocoladas por alunos. Você também pode emitir vias avulsas diretamente utilizando o emissor rápido ao lado.
            </p>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            {/* Direct Issuance fast tool panel */}
            <div className="lg:col-span-4 bg-white border border-slate-200 p-5 rounded-[10px] h-fit space-y-4">
              <div>
                <h4 className="text-xs font-black text-slate-900 uppercase tracking-wider">Emissor Manual Rápido</h4>
                <p className="text-[11px] text-slate-400 mt-0.5">Emita documentos oficiais avulsos para qualquer aluno sem necessidade de pedido prévio.</p>
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
                  <select
                    name="directStudent"
                    required
                    value={selectedRequestStudent || (mockStudents[0]?.name || '')}
                    onChange={(e) => setSelectedRequestStudent(e.target.value)}
                    className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800 bg-white focus:outline-hidden"
                  >
                    {mockStudents.map((st, idx) => (
                      <option key={`${st.email}-${idx}`} value={st.name}>{st.name} ({st.email})</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tipo de Documento</label>
                  <select name="directType" required className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800 bg-white focus:outline-hidden">
                    <option value="historico">Histórico Curricular Escolar</option>
                    <option value="certificado">Certificado Oficial</option>
                    <option value="matricula">Declaração de Matrícula Regular</option>
                  </select>
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Vincular a Qual Curso? (Opcional)</label>
                  <select name="directCourse" className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800 bg-white focus:outline-hidden">
                    <option value="">Geral / Integral</option>
                    {courses.map((c, idx) => (
                      <option key={`${c.id}-${idx}`} value={c.title}>{c.title}</option>
                    ))}
                  </select>
                </div>

                <button
                  type="submit"
                  className="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2 px-3 rounded-md text-xs transition-colors cursor-pointer flex items-center justify-center gap-1"
                >
                  <Download className="h-3 w-3" />
                  <span>Gerar e Validar Via Oficial</span>
                </button>
              </form>

              {/* Seção de Geração Rápida de Requerimentos de Exemplo */}
              <div className="border-t border-slate-100 pt-4 mt-4 space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-[10px] font-black uppercase text-blue-600 tracking-wider font-mono">Simulador de Requerimentos</span>
                  <span className="text-[9px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded font-bold">1-Clique</span>
                </div>
                <p className="text-[10px] text-slate-500 leading-normal">
                  Crie requerimentos de exemplo para o aluno selecionado acima para simular a homologação imediatamente.
                </p>
                <div className="grid grid-cols-1 gap-2">
                  <button
                    onClick={() => {
                      const student = selectedRequestStudent || (mockStudents[0]?.name || 'João Silva');
                      addAcademicRequest({
                        userId: mockStudents.find(s => s.name === student)?.id,
                        type: 'historico',
                        description: `Solicito a emissão do Histórico Curricular Escolar oficial e detalhado contendo todas as notas, médias acumuladas e carga horária integralizada até o presente momento letivo para fins de submissão em edital de transferência de instituição pública.`,
                        courseTitle: courses[0]?.title || 'Geral / Integral'
                      });
                      showToast(`Histórico de exemplo gerado para ${student}!`);
                    }}
                    className="w-full text-left bg-blue-50/70 hover:bg-blue-100/80 border border-blue-200/50 hover:border-blue-300 text-blue-900 p-2.5 rounded-md transition-all text-xs flex flex-col gap-0.5 group cursor-pointer"
                  >
                    <div className="flex items-center gap-1.5 font-bold">
                      <FileText className="h-3.5 w-3.5 text-blue-600 shrink-0" />
                      <span>Histórico Escolar</span>
                      <span className="ml-auto text-[9px] font-extrabold text-blue-500 group-hover:translate-x-0.5 transition-transform">+ Criar</span>
                    </div>
                    <span className="text-[9px] text-slate-500 line-clamp-1 font-medium">Histórico acadêmico completo em formato PDF oficial.</span>
                  </button>

                  <button
                    onClick={() => {
                      const student = selectedRequestStudent || (mockStudents[0]?.name || 'João Silva');
                      addAcademicRequest({
                        userId: mockStudents.find(s => s.name === student)?.id,
                        type: 'certificado',
                        description: `Prezados, venho requerer a homologação prioritária e emissão do Certificado Oficial de Conclusão do Curso de capacitação, pois necessito apresentá-lo ao departamento de Recursos Humanos de minha empresa para fins de promoção e progressão salarial até o final desta semana.`,
                        courseTitle: courses[0]?.title || 'Geral / Integral'
                      });
                      showToast(`Certificado de exemplo gerado para ${student}!`);
                    }}
                    className="w-full text-left bg-emerald-50/70 hover:bg-emerald-100/80 border border-emerald-200/50 hover:border-emerald-300 text-emerald-950 p-2.5 rounded-md transition-all text-xs flex flex-col gap-0.5 group cursor-pointer"
                  >
                    <div className="flex items-center gap-1.5 font-bold">
                      <Award className="h-3.5 w-3.5 text-emerald-600 shrink-0" />
                      <span>Certificado Oficial</span>
                      <span className="ml-auto text-[9px] font-extrabold text-emerald-500 group-hover:translate-x-0.5 transition-transform">+ Criar</span>
                    </div>
                    <span className="text-[9px] text-slate-500 line-clamp-1 font-medium">Certificado formal com validação de carga horária.</span>
                  </button>

                  <button
                    onClick={() => {
                      const student = selectedRequestStudent || (mockStudents[0]?.name || 'João Silva');
                      addAcademicRequest({
                        userId: mockStudents.find(s => s.name === student)?.id,
                        type: 'matricula',
                        description: `Solicito emissão de declaração de matrícula regular e frequência letiva correspondente ao período acadêmico vigente, necessária para apresentação junto ao órgão de transporte público para aquisição do passe-estudantil integrado deste semestre.`,
                        courseTitle: courses[1]?.title || courses[0]?.title || 'Geral / Integral'
                      });
                      showToast(`Declaração de exemplo gerada para ${student}!`);
                    }}
                    className="w-full text-left bg-amber-50/70 hover:bg-amber-100/80 border border-amber-200/50 hover:border-amber-300 text-amber-950 p-2.5 rounded-md transition-all text-xs flex flex-col gap-0.5 group cursor-pointer"
                  >
                    <div className="flex items-center gap-1.5 font-bold">
                      <BookOpen className="h-3.5 w-3.5 text-amber-600 shrink-0" />
                      <span>Declaração de Matrícula</span>
                      <span className="ml-auto text-[9px] font-extrabold text-amber-500 group-hover:translate-x-0.5 transition-transform">+ Criar</span>
                    </div>
                    <span className="text-[9px] text-slate-500 line-clamp-1 font-medium">Comprovante de vínculo regular para passe-estudantil.</span>
                  </button>
                </div>
              </div>
            </div>

            {/* List of Incoming Requests */}
            <div className="lg:col-span-8 bg-white border border-slate-200 p-5 rounded-[10px] space-y-4">
              <h4 className="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
                Requerimentos Registrados por Alunos ({academicRequests?.length || 0})
              </h4>

              {!academicRequests || academicRequests.length === 0 ? (
                <div className="p-8 text-center bg-slate-50 border border-slate-200 border-dashed rounded-[10px] text-xs text-slate-500">
                  Nenhum requerimento curricular cadastrado na memória local. Adicione solicitações através de contas de alunos para visualizá-los e homologá-los.
                </div>
              ) : (
                <div className="space-y-4">
                  {academicRequests.map((req, idx) => (
                    <div key={`${req.id}-${idx}`} className="p-4 border border-slate-200 rounded-[10px] bg-slate-50/25 flex flex-col md:flex-row md:items-center justify-between gap-4">
                      <div className="space-y-1.5 text-left text-xs leading-relaxed max-w-lg">
                        <div className="flex flex-wrap items-center gap-2">
                          <strong className="font-extrabold text-sm text-slate-900 leading-none">{req.studentName}</strong>
                          <span className={`text-[8.5px] font-black uppercase px-2 py-0.5 rounded-full ${
                            req.status === 'pending' ? 'bg-amber-50 text-amber-600 border border-amber-200' :
                            req.status === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                            'bg-red-50 text-red-600 border border-red-200'
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
                                <Award className="h-3.5 w-3.5 text-blue-600" />
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
                                if (req.type === 'matricula' || req.description?.includes('Reversão')) {
                                  clearStudentPenalty(req.userId);
                                }
                                showToast(`Solicitação de ${req.studentName} DEFERIDA com sucesso!`);
                              }}
                              className="bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold px-2.5 py-1.5 rounded-md text-[10px] transition-colors flex items-center gap-1 cursor-pointer whitespace-nowrap"
                            >
                              <Check className="h-3.5 w-3.5" />
                              <span>Deferir</span>
                            </button>
                            <button
                              onClick={() => {
                                updateRequestStatus(req.id, 'rejected');
                                showToast(`Solicitação de ${req.studentName} INDEFERIDA.`);
                              }}
                              className="bg-red-600 hover:bg-red-500 text-white font-extrabold px-2.5 py-1.5 rounded-md text-[10px] transition-colors flex items-center gap-1 cursor-pointer whitespace-nowrap"
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
                            className="bg-slate-900 hover:bg-slate-800 text-white font-extrabold px-2.5 py-1.5 rounded-md text-[10px] transition-colors flex items-center gap-1 cursor-pointer whitespace-nowrap"
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


      {activeTab === 'exercicios' && (
        <div className="space-y-6 text-left">
          <div>
            <BackButton onClick={() => setActiveTab('analytics')} text="Voltar ao Painel Administrativo" />
          </div>
          
          {/* Header */}
          <div className="bg-white border border-slate-200 rounded-[10px] p-5 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div>
              <h3 className="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                <CheckSquare className="h-5 w-5 text-blue-600" />
                <span>Gestão de Exercícios Práticos & Avaliação</span>
              </h3>
              <p className="text-[11px] text-slate-505 text-slate-500 mt-1">
                Cadastre tarefas de entrega, consulte arquivos enviados por alunos e realize a correção direta com feedback acadêmico personalizado.
              </p>
            </div>
            <button
              onClick={() => {
                setEditingExId(null);
                setExTitle('');
                setExDescription('');
                setExInstructions('');
                setExMaxPoints(100);
                setExDueDate('');
                if (courses.length > 0) setExCourseId(courses[0].id);
                setShowExForm(!showExForm);
              }}
              className="bg-blue-600 hover:bg-blue-500 text-white font-bold px-3.5 py-2 rounded-[10px] text-xs transition-colors flex items-center gap-1.5 cursor-pointer self-start sm:self-auto shadow-2xs"
            >
              <Plus className="h-4 w-4" />
              <span>{showExForm ? 'Fechar Formulário' : 'Lançar Nova Atividade'}</span>
            </button>
          </div>

          {/* Exercise Form (Create / Edit) */}
          {showExForm && (
            <div className="bg-white border border-slate-200 rounded-[10px] p-5 space-y-4 animate-in slide-in-from-top-4 duration-200 shadow-2xs">
              <h4 className="font-bold text-xs text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2">
                {editingExId ? 'Editar Exercício Acadêmico' : 'Lançar Novo Exercício Acadêmico'}
              </h4>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-[10.5px] font-bold text-slate-700 block">Curso Alvo:</label>
                  <select
                    value={exCourseId}
                    onChange={(e) => setExCourseId(e.target.value)}
                    className="w-full text-xs rounded-[10px] border border-slate-300 p-2.5 text-slate-800 bg-white"
                  >
                    <option value="">Selecione o Curso...</option>
                    {courses.map((c, idx) => (
                      <option key={`${c.id}-${idx}`} value={c.id}>{c.title}</option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-[10.5px] font-bold text-slate-700 block">Título do Exercício:</label>
                  <input
                    type="text"
                    value={exTitle}
                    onChange={(e) => setExTitle(e.target.value)}
                    placeholder="Ex: Estudo de Caso de Interfaces Mobile"
                    className="w-full text-xs rounded-[10px] border border-slate-300 p-2.5 text-slate-800 bg-white"
                  />
                </div>

                <div className="space-y-1 md:col-span-2">
                  <label className="text-[10.5px] font-bold text-slate-700 block">Descrição Geral (Objetivo):</label>
                  <textarea
                    value={exDescription}
                    onChange={(e) => setExDescription(e.target.value)}
                    placeholder="Descreva brevemente o objetivo e relevância desta tarefa..."
                    rows={2}
                    className="w-full text-xs rounded-[10px] border border-slate-300 p-2.5 text-slate-800 bg-white"
                  />
                </div>

                <div className="space-y-1 md:col-span-2">
                  <label className="text-[10.5px] font-bold text-slate-700 block">Instruções de Execução e Entrega (Passo a passo):</label>
                  <textarea
                    value={exInstructions}
                    onChange={(e) => setExInstructions(e.target.value)}
                    placeholder="Explique detalhadamente o que o aluno deve entregar (ex: relatório de 500 palavras, anexo em PDF ou DOC, critérios de avaliação)..."
                    rows={3}
                    className="w-full text-xs rounded-[10px] border border-slate-300 p-2.5 text-slate-800 bg-white"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-[10.5px] font-bold text-slate-700 block">Nota Máxima:</label>
                  <input
                    type="number"
                    value={exMaxPoints}
                    onChange={(e) => setExMaxPoints(Number(e.target.value))}
                    className="w-full text-xs rounded-[10px] border border-slate-300 p-2.5 text-slate-800 bg-white"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-[10.5px] font-bold text-slate-700 block">Prazo de Entrega (Opcional):</label>
                  <input
                    type="text"
                    value={exDueDate}
                    onChange={(e) => setExDueDate(e.target.value)}
                    placeholder="Ex: 15/07/2026"
                    className="w-full text-xs rounded-[10px] border border-slate-300 p-2.5 text-slate-800 bg-white"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <button
                  onClick={() => setShowExForm(false)}
                  className="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-3 py-1.5 rounded-[10px] text-xs transition-colors cursor-pointer"
                >
                  Cancelar
                </button>
                <button
                  onClick={() => {
                    if (!exCourseId || !exTitle.trim() || !exDescription.trim() || !exInstructions.trim()) {
                      alert('Por favor, preencha todos os campos obrigatórios.');
                      return;
                    }
                    if (editingExId) {
                      updatePracticalExercise(editingExId, {
                        courseId: exCourseId,
                        title: exTitle.trim(),
                        description: exDescription.trim(),
                        instructions: exInstructions.trim(),
                        maxPoints: exMaxPoints,
                        dueDate: exDueDate.trim() || undefined
                      });
                      alert('Exercício atualizado com sucesso!');
                    } else {
                      addPracticalExercise(exCourseId, exTitle.trim(), exDescription.trim(), exInstructions.trim(), exMaxPoints, exDueDate.trim() || undefined);
                      alert('Novo exercício lançado com sucesso!');
                    }
                    setShowExForm(false);
                    setEditingExId(null);
                  }}
                  className="bg-blue-600 hover:bg-blue-600 text-white font-bold px-4 py-1.5 rounded-[10px] text-xs transition-colors cursor-pointer"
                >
                  {editingExId ? 'Salvar Edição' : 'Publicar Atividade'}
                </button>
              </div>
            </div>
          )}

          {/* Master Grid: Columns for List of Exercises & Submissions awaiting grading */}
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            {/* Column 1: List of Exercises (lg:col-span-5) */}
            <div className="lg:col-span-5 space-y-4">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                  <Layers className="h-4 w-4 text-blue-600" />
                  <span>Exercícios Cadastrados</span>
                </h4>
                <span className="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{practicalExercises.length}</span>
              </div>

              <div className="space-y-3">
                {practicalExercises.length === 0 ? (
                  <div className="bg-white border border-slate-200 rounded-[10px] p-6 text-center text-slate-400 text-[11px]">
                    Nenhum exercício lançado para as disciplinas de ensino.
                  </div>
                ) : (
                  practicalExercises.map((ex, idx) => {
                    const course = courses.find(c => c.id === ex.courseId);
                    return (
                      <div key={`${ex.id}-${idx}`} className="bg-white border border-slate-200 rounded-[10px] p-3.5 leading-relaxed text-[11px] space-y-3 shadow-sm">
                        <div className="flex items-start justify-between gap-1.5 border-b border-slate-50 pb-2">
                          <div>
                            <span className="text-[9px] font-extrabold uppercase text-blue-700 block leading-tight">{course?.title || 'Curso Não Identificado'}</span>
                            <strong className="font-bold text-slate-900 block mt-0.5">{ex.title}</strong>
                          </div>
                          <span className="bg-amber-50 text-amber-800 text-[9px] font-black px-1.5 py-0.5 rounded border border-amber-200 shrink-0">
                            {ex.maxPoints} pts
                          </span>
                        </div>

                        <p className="text-slate-500 leading-normal line-clamp-2">{ex.description}</p>

                        <div className="flex items-center justify-between pt-1 border-t border-slate-50">
                          <span className="text-[10px] text-slate-400">{ex.dueDate ? `Prazo: ${ex.dueDate}` : 'Sem prazo determinado'}</span>
                          
                          <div className="flex items-center gap-1">
                            <button
                              onClick={() => {
                                setEditingExId(ex.id);
                                setExCourseId(ex.courseId);
                                setExTitle(ex.title);
                                setExDescription(ex.description);
                                setExInstructions(ex.instructions);
                                setExMaxPoints(ex.maxPoints);
                                setExDueDate(ex.dueDate || '');
                                setShowExForm(true);
                              }}
                              className="bg-slate-50 hover:bg-slate-100 text-slate-700 px-2 py-1 rounded text-[10px] border border-slate-200 transition-colors cursor-pointer font-bold"
                            >
                              Editar
                            </button>
                            <button
                              onClick={() => {
                                if (confirm('Tem certeza de que deseja remover este exercício? Todas as entregas de alunos associadas serão excluídas.')) {
                                  deletePracticalExercise(ex.id);
                                }
                              }}
                              className="bg-red-50 hover:bg-red-100 text-red-600 px-2 py-1 rounded text-[10px] border border-red-200/50 transition-colors cursor-pointer font-bold"
                            >
                              Excluir
                            </button>
                          </div>
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            </div>

            {/* Column 2: Student submissions awaiting grading (lg:col-span-7) */}
            <div className="lg:col-span-7 space-y-4">
              <div className="flex items-center justify-between">
                <h4 className="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                  <FileText className="h-4 w-4 text-blue-600" />
                  <span>Entregas de Alunos para Correção</span>
                </h4>
                <span className="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                  {exerciseSubmissions.filter(s => s.status === 'pending').length} pendentes
                </span>
              </div>

              <div className="space-y-4">
                {exerciseSubmissions.length === 0 ? (
                  <div className="bg-white border border-slate-200 rounded-[10px] p-8 text-center text-slate-400 text-[11px]">
                    Nenhum aluno realizou entregas de exercícios práticos até o momento.
                  </div>
                ) : (
                  exerciseSubmissions.map((sub, idx) => {
                    const ex = practicalExercises.find(e => e.id === sub.exerciseId);
                    const course = courses.find(c => c.id === ex?.courseId);
                    const isGrading = gradingSubId === sub.id;

                    return (
                      <div key={`${sub.id}-${idx}`} className="bg-white border border-slate-200 rounded-[10px] p-4 leading-relaxed text-[11px] space-y-4 shadow-sm">
                        
                        {/* Grader Header Info */}
                        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-2 border-b border-slate-100 pb-2.5">
                          <div>
                            <div className="flex items-center gap-2">
                              <span className="font-bold text-slate-900 text-xs">{sub.studentName}</span>
                              <span className="text-[9px] text-slate-400">Trabalho enviado em {sub.submittedAt}</span>
                            </div>
                            <span className="text-[10px] text-slate-500 mt-1 block">
                              Curso: <strong className="font-semibold text-slate-700">{course?.title || 'Fórum / Desconhecido'}</strong> ➔ <strong className="font-semibold text-slate-700">{ex?.title || 'Atividade Excluída'}</strong>
                            </span>
                          </div>

                          <span className={`text-[8px] font-extrabold uppercase px-2 py-0.5 rounded border self-start sm:self-auto ${
                            sub.status === 'approved' 
                              ? 'bg-emerald-50 border-emerald-200 text-emerald-800' 
                              : sub.status === 'rejected'
                              ? 'bg-red-50 border-red-200 text-red-800'
                              : sub.status === 'revision'
                              ? 'bg-amber-50 border-amber-250 text-amber-800'
                              : 'bg-blue-50 border-blue-200 text-blue-800 animate-pulse'
                          }`}>
                            {
                              sub.status === 'approved' ? `Aprovado (Nota: ${sub.score})` :
                              sub.status === 'rejected' ? 'Reprovado' :
                              sub.status === 'revision' ? 'Revisão Solicitada' : 'Aguardando Correção'
                            }
                          </span>
                        </div>

                        {/* Student submission text */}
                        <div className="bg-slate-50 p-3 rounded-md border border-slate-100 space-y-1.5">
                          <strong className="text-slate-800 font-bold block text-[10px] uppercase tracking-wide text-slate-400">Trabalho Escrito:</strong>
                          <p className="whitespace-pre-wrap font-mono text-[10px] leading-relaxed text-slate-700 max-h-48 overflow-y-auto bg-white p-2.5 rounded-md border border-slate-100">{sub.submissionText}</p>
                          
                          {sub.fileName && (
                            <div className="flex items-center gap-1.5 text-[10px] bg-blue-50/50 p-1.5 rounded border border-blue-100 mt-1">
                              <FileText className="h-3.5 w-3.5 text-blue-600" />
                              <span>Anexo: <strong className="text-slate-800">{sub.fileName}</strong></span>
                              <button
                                onClick={async () => {
                                  const err = await downloadSubmissionFile(sub.fileUrl || '', sub.fileName);
                                  if (err) showToast(err);
                                }}
                                className="text-blue-600 font-bold hover:underline ml-auto flex items-center gap-0.5 cursor-pointer"
                              >
                                Baixar Documento <Printer className="h-3 w-3" />
                              </button>
                            </div>
                          )}
                        </div>

                        {/* Existing grader comments if graded */}
                        {sub.feedback && !isGrading && (
                          <div className="bg-slate-50 border border-slate-200 rounded-[10px] p-3 text-[10.5px]">
                            <strong className="text-slate-900 font-bold block flex items-center gap-1">
                              <User className="h-3.5 w-3.5 text-blue-600" /> Nota & Avaliação Concedida (por {sub.gradedBy} em {sub.gradedAt}):
                            </strong>
                            <p className="text-slate-600 leading-relaxed mt-1 italic whitespace-pre-line">{sub.feedback}</p>
                          </div>
                        )}

                        {/* Evaluate form trigger / fields */}
                        {isGrading ? (
                          <div className="bg-blue-50/20 border border-blue-100 rounded-[10px] p-4.5 space-y-3.5 animate-in slide-in-from-top-1.5 duration-150">
                            <h5 className="font-bold text-xs text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                              <Check className="h-4 w-4" /> Formular Avaliação Acadêmica
                            </h5>

                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                              <div className="space-y-1 sm:col-span-1">
                                <label className="text-[10.5px] font-bold text-slate-700 block">Nota:</label>
                                <input
                                  type="number"
                                  min="0"
                                  max={ex?.maxPoints || 100}
                                  value={gradeScore}
                                  onChange={(e) => setGradeScore(Math.min(ex?.maxPoints || 100, Number(e.target.value)))}
                                  className="w-full text-xs rounded-md border border-slate-300 p-2 text-slate-800 bg-white"
                                />
                                <span className="text-[9px] text-slate-400 mt-0.5 block">Máximo: {ex?.maxPoints || 100} pontos</span>
                              </div>

                              <div className="space-y-1 sm:col-span-2">
                                <label className="text-[10.5px] font-bold text-slate-700 block">Feedback / Comentários:</label>
                                <textarea
                                  value={gradeFeedback}
                                  onChange={(e) => setGradeFeedback(e.target.value)}
                                  placeholder="Digite orientações pedagógicas, pontos fortes e recomendações de correção..."
                                  rows={2}
                                  className="w-full text-xs rounded-md border border-slate-300 p-2 text-slate-800 bg-white"
                                />
                              </div>
                            </div>

                            <div className="flex justify-end gap-1.5 pt-2 border-t border-slate-100">
                              <button
                                onClick={() => setGradingSubId(null)}
                                className="bg-white hover:bg-slate-100 text-slate-700 font-bold px-2.5 py-1.2 rounded-md border border-slate-200 text-[10.5px] cursor-pointer"
                              >
                                Cancelar
                              </button>

                              <button
                                onClick={() => {
                                  if (!gradeFeedback.trim()) {
                                    alert('Por favor, inclua considerações e comentários de feedback pedagógico para o aluno.');
                                    return;
                                  }
                                  gradeSubmission(sub.id, gradeScore, gradeFeedback.trim(), 'Gestor de Conteúdos', 'revision');
                                  setGradingSubId(null);
                                  alert('Foi solicitado ajustes e revisão de trabalho com sucesso!');
                                }}
                                className="bg-amber-600 hover:bg-amber-500 text-white font-bold px-3 py-1.2 rounded-md text-[10.5px] cursor-pointer"
                              >
                                Solicitar Ajustes
                              </button>

                              <button
                                onClick={() => {
                                  if (!gradeFeedback.trim()) {
                                    alert('Por favor, inclua considerações e comentários de feedback pedagógico para o aluno.');
                                    return;
                                  }
                                  gradeSubmission(sub.id, gradeScore, gradeFeedback.trim(), 'Gestor de Conteúdos', 'approved');
                                  setGradingSubId(null);
                                  alert('Trabalho avaliado, homologado e nota lançada com sucesso!');
                                }}
                                className="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-3 py-1.2 rounded-md text-[10.5px] cursor-pointer"
                              >
                                Aprovar & Lançar Nota
                              </button>
                            </div>
                          </div>
                        ) : (
                          <div className="flex justify-end">
                            <button
                              onClick={() => {
                                setGradingSubId(sub.id);
                                setGradeScore(sub.score || ex?.maxPoints || 100);
                                setGradeFeedback(sub.feedback || '');
                              }}
                              className="bg-blue-600 hover:bg-blue-500 text-white font-bold px-3 py-1.5 rounded-[10px] text-[10px] transition-colors flex items-center gap-1 cursor-pointer"
                            >
                              <CheckSquare className="h-3.5 w-3.5" />
                              <span>{sub.status === 'pending' ? 'Avaliar Trabalho' : 'Reavaliar Atividade'}</span>
                            </button>
                          </div>
                        )}
                      </div>
                    );
                  })
                )}
              </div>
            </div>

          </div>

        </div>
      )}


      {activeTab === 'settings' && (
        <div className="space-y-4 text-left">
          <div>
            <BackButton onClick={() => setActiveTab('analytics')} text="Voltar ao Painel Administrativo" />
          </div>
          <div className="bg-white border border-slate-200 rounded-[10px] p-5 text-left space-y-6 settings-tab-content">
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
                  <div className={`block w-8 h-4 rounded-full transition-colors ${systemSettings.allowDirectMessages ? 'bg-blue-500' : 'bg-slate-300'}`}></div>
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
                  <div className={`block w-8 h-4 rounded-full transition-colors ${systemSettings.allowGlobalChat ? 'bg-blue-500' : 'bg-slate-300'}`}></div>
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
                  <div className={`block w-8 h-4 rounded-full transition-colors ${systemSettings.openEnrollment ? 'bg-blue-500' : 'bg-slate-300'}`}></div>
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
                  <div className={`block w-8 h-4 rounded-full transition-colors ${systemSettings.autoCertify ? 'bg-blue-500' : 'bg-slate-300'}`}></div>
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
                  <div className={`block w-8 h-4 rounded-full transition-colors ${systemSettings.liveClassRecording ? 'bg-blue-500' : 'bg-slate-300'}`}></div>
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
                  className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500"
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

            <div className="bg-slate-50/70 rounded-[10px] p-4 border border-slate-200 border-dashed text-xs text-slate-500 mt-2">
              <strong className="text-slate-800 block mb-1">Nota da Assessoria de T.I:</strong>
              <p className="leading-relaxed">Novas rotas letivas criadas tanto pelo Administrador quanto pelos Professores cadastrados são adicionadas em tempo real em bancos na memória do navegador.</p>
            </div>

            <div className="pt-6 mt-2 border-t border-slate-100">
              <button
                onClick={() => showToast('Configurações salvas com sucesso!')}
                className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-[10px] text-xs transition-colors flex items-center justify-center gap-2 shadow-sm uppercase tracking-wide"
              >
                <Save className="h-4 w-4" />
                <span>Salvar Configurações</span>
              </button>
            </div>
          </div>
          </div>
        </div>
      )}

      {activeTab === 'export_bi' && (
        <div className="space-y-4 text-left">
          <div>
            <BackButton onClick={() => setActiveTab('analytics')} text="Voltar ao Painel Administrativo" />
          </div>
          <div className="bg-white border border-slate-200 rounded-[10px] p-6 text-left space-y-6">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
              <h3 className="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <Database className="h-5 w-5 text-blue-600" />
                <span>Integração de Dados e Exportação de Bases para BI</span>
              </h3>
              <p className="text-[11px] text-slate-500 mt-1">
                Estruturas de dados otimizadas para alimentação e modelagem em Power BI, Excel e relatórios analíticos de gestão.
              </p>
            </div>
            <button
              onClick={async () => {
                // Exportação consome EXCLUSIVAMENTE os endpoints seguros e auditados do
                // backend (/api/export/:dataset) — nunca o estado local do navegador.
                try {
                  await exportAllManagementBases();
                  showToast('As 5 bases de Dados Gerenciais foram exportadas com sucesso!');
                } catch (err: any) {
                  showToast(err?.message || 'Falha ao exportar as bases. Verifique sua sessão de administrador.');
                }
              }}
              className="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-[10px] transition-all flex items-center justify-center gap-1.5 shadow-sm cursor-pointer"
            >
              <Download className="h-4 w-4 text-blue-400" />
              <span>Baixar Todas as 5 Bases (.csv)</span>
            </button>
          </div>



          {/* Database Selector Tabs */}
          <div className="border-b border-slate-100 flex overflow-x-auto gap-1">
            {[
              { id: 'alunos', label: '1. Base de Alunos' },
              { id: 'cursos', label: '2. Base de Cursos' },
              { id: 'matriculas', label: '3. Base de Matrículas' },
              { id: 'progresso', label: '4. Progresso por Módulo' },
              { id: 'certificados', label: '5. Base de Certificados' }
            ].map((base) => {
              const isSel = selectedBiBase === base.id;
              return (
                <button
                  key={base.id}
                  onClick={() => setSelectedBiBase(base.id as any)}
                  className={`px-4 py-2 border-b-2 font-bold text-xs transition-all whitespace-nowrap cursor-pointer ${
                    isSel 
                      ? 'border-blue-600 text-blue-700' 
                      : 'border-transparent text-slate-400 hover:text-slate-600'
                  }`}
                >
                  {base.label}
                </button>
              );
            })}
          </div>

          {/* Active Base Preview & Info */}
          {(() => {
            // Compute databases on the fly for viewing and single-export
            const AlunosData = studentsList.map((st, index) => ({
              id_aluno: `ALU_${String(index + 1).padStart(3, '0')}`,
              nome: st.name,
              email: st.email,
              municipio: st.municipio || 'São Paulo',
              uf: st.uf || 'SP',
              area_de_interesse: st.areaInteresse || 'Design Digital',
              data_de_cadastro: st.dataCadastro || '2026-01-10'
            }));

            const CursosData = courses.map(c => ({
              id_curso: c.id,
              titulo: c.title,
              categoria: c.category,
              area_tematica: c.areaTematica || 'Design Digital',
              carga_horaria: c.cargaHoraria || 40,
              modalidade: c.modalidade || 'EAD',
              nivel: c.nivel || 'Intermediário',
              professor_responsavel: c.instructorName,
              emite_certificado: c.emiteCertificado !== false ? 'Sim' : 'Não',
              percentual_minimo: courseMinAttendance(c),
              status_do_curso: c.statusCurso || 'Ativo'
            }));

            const MatriculasData: any[] = [];
            let matCounter = 1;
            Object.entries(studentEnrollments || {}).forEach(([userId, enrollmentVal]) => {
              const enrollment = enrollmentVal as any;
              // Joins por userId (ADR 10); o nome é só display.
              const studentIndex = studentsList.findIndex(s => s.id === userId);
              const studentId = studentIndex >= 0 ? `ALU_${String(studentIndex + 1).padStart(3, '0')}` : 'ALU_001';
              const studentObj = studentsList.find(s => s.id === userId);

              if (enrollment.enrolledCourseId) {
                const courseId = enrollment.enrolledCourseId;
                const course = courses.find(c => c.id === courseId);
                if (course) {
                  const userProg = progress.find(p => p.courseId === courseId && p.userId === userId);
                  const compCount = userProg ? userProg.completedLessons.length : 0;
                  const totalCount = course.lessons.length;
                  const percent = totalCount > 0 ? Math.round((compCount / totalCount) * 100) : 0;
                  const hasCert = certificates.some(cert => cert.courseId === courseId && cert.userId === userId);

                  MatriculasData.push({
                    id_matricula: `MAT_${String(matCounter++).padStart(3, '0')}`,
                    id_aluno: studentId,
                    id_curso: courseId,
                    data_matricula: enrollment.enrolledAt ? new Date(enrollment.enrolledAt).toISOString().split('T')[0] : '2026-06-01',
                    data_inicio: enrollment.enrolledAt ? new Date(enrollment.enrolledAt).toISOString().split('T')[0] : '2026-06-01',
                    ultimo_acesso: (studentObj as any)?.lastAccess || '2026-06-15',
                    status_matricula: 'Ativa',
                    progresso_percentual: percent,
                    data_conclusao: '—',
                    certificado_liberado: hasCert ? 'Sim' : 'Não',
                    data_emissao: '—'
                  });
                }
              }

              if (enrollment.completedCourseIds && enrollment.completedCourseIds.length > 0) {
                enrollment.completedCourseIds.forEach((courseId: string) => {
                  const course = courses.find(c => c.id === courseId);
                  if (course) {
                    const cert = certificates.find(ct => ct.courseId === courseId && ct.userId === userId);
                    const issueDate = cert ? cert.issueDate : '2026-06-25';

                    MatriculasData.push({
                      id_matricula: `MAT_${String(matCounter++).padStart(3, '0')}`,
                      id_aluno: studentId,
                      id_curso: courseId,
                      data_matricula: enrollment.enrolledAt ? new Date(new Date(enrollment.enrolledAt).getTime() - 20 * 24 * 60 * 60 * 1000).toISOString().split('T')[0] : '2026-05-10',
                      data_inicio: enrollment.enrolledAt ? new Date(new Date(enrollment.enrolledAt).getTime() - 20 * 24 * 60 * 60 * 1000).toISOString().split('T')[0] : '2026-05-10',
                      ultimo_acesso: issueDate,
                      status_matricula: 'Concluída',
                      progresso_percentual: 100,
                      data_conclusao: issueDate,
                      certificado_liberado: 'Sim',
                      data_emissao: issueDate
                    });
                  }
                });
              }
            });

            const ProgressoData: any[] = [];
            let prgCounter = 1;
            MatriculasData.forEach(mat => {
              const course = courses.find(c => c.id === mat.id_curso);
              if (!course) return;

              const matStudent = studentsList[parseInt(mat.id_aluno.split('_')[1], 10) - 1];
              const userProg = progress.find(p => p.courseId === mat.id_curso && p.userId === matStudent?.id);

              course.lessons.forEach((lesson, index) => {
                const isCompleted = mat.status_matricula === 'Concluída' || (userProg && userProg.completedLessons.includes(lesson.id));
                const status = isCompleted ? 'Concluído' : (index === 0 || (userProg && userProg.completedLessons.length > 0 && index <= userProg.completedLessons.length) ? 'Em Andamento' : 'Não Iniciado');
                const completionDate = isCompleted ? (mat.status_matricula === 'Concluída' ? mat.data_conclusao : new Date(new Date(mat.data_matricula).getTime() + (index + 1) * 24 * 60 * 60 * 1000).toISOString().split('T')[0]) : '—';
                const startDate = mat.data_matricula;

                ProgressoData.push({
                  id_progresso: `PRG_${String(prgCounter++).padStart(4, '0')}`,
                  id_matricula: mat.id_matricula,
                  id_modulo: lesson.id,
                  titulo_modulo: lesson.title,
                  status_modulo: status,
                  data_inicio: startDate,
                  data_conclusao: completionDate
                });
              });
            });

            const CertificadosData: any[] = [];
            let certCounter = 1;
            certificates.forEach(c => {
              // Certificados históricos podem ter userId null — cai no nome como último recurso.
              const studentIndex = studentsList.findIndex(st => (c.userId ? st.id === c.userId : st.name === c.studentName));
              const studentId = studentIndex >= 0 ? `ALU_${String(studentIndex + 1).padStart(3, '0')}` : 'ALU_001';
              const matchedMat = MatriculasData.find(mat => mat.id_aluno === studentId && mat.id_curso === c.courseId);
              const matriculaId = matchedMat ? matchedMat.id_matricula : 'MAT_001';
              const courseObj = courses.find(co => co.id === c.courseId);

              CertificadosData.push({
                id_certificado: `CRT_${String(certCounter++).padStart(3, '0')}`,
                id_matricula: matriculaId,
                codigo_validacao: c.verificationHash || 'VAL-MOCK-HASH',
                data_emissao: c.issueDate,
                status_certificado: 'Ativo',
                carga_horaria_certificada: courseObj?.cargaHoraria || 40
              });
            });

            let currentData: any[] = [];
            let currentFilename = '';
            let currentTitle = '';
            let currentDesc = '';

            if (selectedBiBase === 'alunos') {
              currentData = AlunosData;
              currentFilename = 'base_alunos.csv';
              currentTitle = 'Base de Alunos';
              currentDesc = 'Cadastro geral dos alunos com informações demográficas de município, estado, área de interesse preferencial e data de matrícula inicial.';
            } else if (selectedBiBase === 'cursos') {
              currentData = CursosData;
              currentFilename = 'base_cursos.csv';
              currentTitle = 'Base de Cursos';
              currentDesc = 'Catálogo completo de trilhas ativas e inativas, incluindo a categoria, área temática, carga horária letiva oficial e regras de aprovação para atestados.';
            } else if (selectedBiBase === 'matriculas') {
              currentData = MatriculasData;
              currentFilename = 'base_matriculas.csv';
              currentTitle = 'Base de Matrículas';
              currentDesc = 'Registro transacional de vínculos dos alunos com os cursos. Rastreia o percentual concluído de progresso e a situação da matrícula (Ativa vs Concluída).';
            } else if (selectedBiBase === 'progresso') {
              currentData = ProgressoData;
              currentFilename = 'base_progresso_modulo.csv';
              currentTitle = 'Progresso por Módulo';
              currentDesc = 'Granularidade fina de progresso módulo por módulo (ou aula por aula). Ideal para analisar em que parte do curso o aluno está demorando mais tempo.';
            } else {
              currentData = CertificadosData;
              currentFilename = 'base_certificados.csv';
              currentTitle = 'Base de Certificados';
              currentDesc = 'Certidões e certificados emitidos, vinculados de forma relacional ao registro da matrícula correspondente e ao código de validação autenticado.';
            }

            // Exporta\u00E7\u00E3o individual: tamb\u00E9m consome exclusivamente o endpoint auditado do
            // backend \u2014 os dados locais acima servem apenas para a pr\u00E9-visualiza\u00E7\u00E3o na tela.
            const exportSingleCSV = async () => {
              try {
                const count = await exportManagementBase(selectedBiBase as ManagementBase);
                showToast(`Base ${currentTitle} exportada com sucesso (${count} registros do servidor)!`);
              } catch (err: any) {
                showToast(err?.message || 'Falha ao exportar a base. Verifique sua sess\u00E3o de administrador.');
              }
            };

            const keys = currentData.length > 0 ? Object.keys(currentData[0]) : [];

            return (
              <div className="space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50 border border-slate-100 rounded-[10px] p-4">
                  <div className="space-y-1">
                    <span className="text-[10px] font-black uppercase text-blue-600 tracking-wider">Visualizando estrutura de dados</span>
                    <h4 className="text-xs font-black text-slate-800 uppercase tracking-tight flex items-center gap-1.5">
                      <span>{currentTitle}</span>
                      <span className="text-[10px] text-slate-400 font-normal">({currentData.length} registros no total)</span>
                    </h4>
                    <p className="text-[10px] text-slate-500 leading-relaxed max-w-2xl">{currentDesc}</p>
                  </div>
                  <button
                    onClick={exportSingleCSV}
                    disabled={currentData.length === 0}
                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white font-bold text-xs rounded-md transition-all cursor-pointer shadow-sm flex items-center gap-1.5 self-start sm:self-center"
                  >
                    <Download className="h-3.5 w-3.5" />
                    <span>Exportar esta Base (CSV)</span>
                  </button>
                </div>

                {/* Live Preview Table */}
                <div className="border border-slate-200 rounded-[10px] overflow-hidden bg-white">
                  <div className="bg-slate-50 px-4 py-2.5 border-b border-slate-100 flex items-center justify-between">
                    <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Visualização Prévia (Top 5 Registros)</span>
                    <span className="text-[9px] text-slate-400">Total de colunas mapeadas: {keys.length}</span>
                  </div>
                  {currentData.length === 0 ? (
                    <div className="p-8 text-center text-slate-400 text-xs font-medium">
                      Nenhum registro encontrado nesta base de dados atualmente.
                    </div>
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full text-[11px] text-slate-700 text-left">
                        <thead className="bg-slate-50 border-b border-slate-100 font-black text-slate-500">
                          <tr>
                            {keys.map(k => (
                              <th key={k} className="p-3 whitespace-nowrap uppercase tracking-wider text-[9px] font-black">{k}</th>
                            ))}
                          </tr>
                        </thead>
                        <tbody>
                          {currentData.slice(0, 5).map((row, rIdx) => (
                            <tr key={rIdx} className="hover:bg-slate-50/55 border-b border-slate-100 last:border-b-0">
                              {keys.map(k => (
                                <td key={`${rIdx}-${k}`} className="p-3 whitespace-nowrap font-medium text-slate-800">
                                  {row[k] === '—' || !row[k] ? (
                                    <span className="text-slate-355">—</span>
                                  ) : k.includes('id_') || k.includes('codigo') ? (
                                    <code className="bg-slate-100 px-1 py-0.5 rounded text-blue-700 font-mono text-[10px]">{row[k]}</code>
                                  ) : (
                                    String(row[k])
                                  )}
                                </td>
                              ))}
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              </div>
            );
          })()}
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

          <div className="bg-white rounded-[10px] shadow-2xl border border-slate-200 w-full max-w-2xl overflow-hidden animate-in zoom-in-95 duration-200 my-8">
            
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
                  className="text-[10px] bg-blue-600 hover:bg-blue-500 font-extrabold text-white px-2.5 py-1.5 rounded-md flex items-center gap-1 cursor-pointer transition-colors shadow-sm"
                >
                  <Printer className="h-3 w-3 text-white" />
                  <span>Imprimir / PDF</span>
                </button>
                <button 
                  onClick={() => {
                    showToast(`Salvando ${activeDocViewer.type} de ${activeDocViewer.studentName} no Computador...`);
                  }}
                  className="text-[10px] bg-slate-800 hover:bg-slate-700 font-extrabold text-white px-2.5 py-1.5 rounded-md flex items-center gap-1 cursor-pointer transition-colors"
                >
                  <Download className="h-3 w-3 text-blue-400" />
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
              <div className="border border-slate-200 p-8 rounded-[10px] space-y-6 text-left relative overflow-hidden bg-slate-50/10">
                
                {/* Watermark design background */}
                <div className="absolute inset-x-0 top-1/3 text-center pointer-events-none opacity-[0.03] flex flex-col items-center justify-center">
                  <ShieldCheck className="h-48 w-48 text-slate-900" />
                </div>

                {/* Official Header */}
                <div className="border-b border-double border-slate-300 pb-5 text-center space-y-2">
                  <span className="text-[10px] bg-slate-900 text-white px-2.5 py-0.5 rounded font-mono font-bold uppercase tracking-widest">Via Homologada de Autenticidade</span>
                  <h3 className="text-base font-black uppercase text-slate-950 tracking-tight leading-none mt-1">Escola Estadual da Cultura</h3>
                  <p className="text-[9px] text-slate-500 font-bold uppercase tracking-wider mt-1.5">Setor de Registros e Certificações</p>
                </div>

                {/* Document Specific Content */}
                {activeDocViewer.type === 'historico' ? (
                  <div className="space-y-4">
                    <div className="text-center space-y-1">
                      <h4 className="font-extrabold text-sm text-slate-900 uppercase tracking-tight">Histórico Escolar Acadêmico Integral</h4>
                      <p className="text-[9px] text-slate-400 font-mono">Protocolo de Consulta: HIST-{Date.now().toString().substring(6)}</p>
                    </div>

                    {/* Student Info block */}
                    <div className="grid grid-cols-2 gap-4 bg-white border border-slate-100 p-3 rounded-md text-[11px] leading-relaxed">
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
                      <div className="border border-slate-200 rounded-md overflow-hidden bg-white">
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
                            {courses.map((course, idx) => {
                              // calculate attendance for studentName
                              let studentAttendance = 0;
                              const userProg = progress.find(p => p.courseId === course.id);
                              const totalActs = course.lessons.length + course.liveSessions.length;
                              if (totalActs > 0 && userProg) {
                                const completed = userProg.completedLessons.length + userProg.attendedLiveSessions.length;
                                studentAttendance = Math.round((completed / totalActs) * 100);
                              }

                              // average quiz score (resolve o id do aluno exibido no documento — ADR 10)
                              const docStudentId = studentsList.find(s => s.name === activeDocViewer.studentName)?.id;
                              const subs = quizSubmissions.filter(s => s.userId === docStudentId && s.courseId === course.id);
                              const quizScore = subs.length > 0 ? `${subs[0].scorePercent}%` : 'Pendente';

                              return (
                                <tr key={`${course.id}-${idx}`} className="border-b border-slate-100 hover:bg-slate-50/30">
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

                    <div className="text-[10px] text-slate-500 bg-slate-50/80 border border-slate-100 p-2.5 rounded-md leading-relaxed mt-4">
                      * Este histórico reflete integralmente os registros eletrônicos armazenados na Central AVA em {new Date().toLocaleDateString('pt-BR')}. A presença de 70% ou mais outorga a emissão eletrônica de certificados de habilidade prática.
                    </div>
                  </div>
                ) : activeDocViewer.type === 'certificado' ? (
                  <div className="space-y-6 text-center py-4">
                    <div className="space-y-1">
                      <span className="text-[9px] text-slate-400 font-bold uppercase tracking-widest block">Certificação Profissional</span>
                      <h4 className="font-black text-xl italic text-slate-950 antialiased font-serif">Certificado de Conclusão Técnica</h4>
                    </div>

                    <div className="text-slate-700 text-[13px] leading-relaxed max-w-md mx-auto space-y-4">
                      <p>
                        Certificamos de forma solene para os devidos fins legais, de competências e de complementação acadêmica que o aluno
                      </p>
                      <p className="text-lg font-black text-slate-905 border-b border-slate-200 py-1.5 w-fit mx-auto px-4 uppercase tracking-normal">
                        {activeDocViewer.studentName}
                      </p>
                      <p className="text-[11px] leading-normal text-slate-400">
                        concluiu com êxito os requisitos teóricos, testes práticos e obteve aproveitamento curricular superior a <strong className="font-semibold text-slate-800">70% de presença letiva</strong> nas aulas, assessorias síncronas e atividades do curso didático de:
                      </p>
                      <p className="text-sm font-black text-blue-900 uppercase">
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
                      Declaramos, para os devidos fins de direito e comprovação institucional acadêmica, que o estudante <strong className="font-bold text-slate-950 uppercase">{activeDocViewer.studentName}</strong> encontra-se regularmente cadastrado e ativamente matriculado nos sistemas desta Escola Estadual da Cultura, participando da grade didática atual no ano letivo corrente de 2026.
                    </p>

                    <p className="text-[11px] text-slate-705 leading-normal text-justify">
                      O aluno mantém status regular, frequentando as conferências de mentoria de forma remota, e submetendo-se a baterias de testes didáticos sob supervisão dos professores cadastrados.
                    </p>

                    <div className="grid grid-cols-2 gap-4 bg-slate-50 p-3 rounded-md border border-slate-100 text-[10px] text-slate-500 leading-normal mt-4">
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
                className="rounded-md border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 px-4 py-1.5 text-xs font-bold transition-all cursor-pointer"
              >
                Fechar Visualizador
              </button>
            </div>

          </div>
        </div>
      )}

      {/* DETAILED STUDENT PROFILE / EDIT PARAMETERS MODAL */}
      {activeStudentProfile && (() => {
        const rawStudent = mockStudents.find(s => s.name === activeStudentProfile);
        if (!rawStudent) return null;
        const st = getEnrichedStudent(rawStudent);
        const initials = st.name.split(' ').map(x => x[0]).join('').slice(0, 2).toUpperCase();
        return (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm animate-in fade-in duration-200">
            <div className="w-full max-w-lg bg-white rounded-[10px] shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200 text-left border border-slate-100 flex flex-col max-h-[90vh]">
              {/* Header */}
              <header className="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                <div className="flex items-center gap-3">
                  <div className="h-10 w-10 bg-blue-50 border border-blue-100 text-blue-700 text-sm font-black flex items-center justify-center rounded-full uppercase shrink-0">
                    {initials}
                  </div>
                  <div>
                    <h3 className="text-sm font-black text-slate-900 uppercase tracking-tight">{st.name}</h3>
                    <p className="text-[10px] text-slate-400 font-mono leading-none mt-0.5">RA: {st.ra} • {st.email}</p>
                  </div>
                </div>
                <button 
                  onClick={() => setActiveStudentProfile(null)}
                  className="p-1.5 hover:bg-slate-200 rounded-full transition-colors cursor-pointer text-slate-400"
                >
                  <X className="h-4 w-4" />
                </button>
              </header>

              {/* Body content with scroll */}
              <div className="p-5 space-y-4 overflow-y-auto grow">
                {/* Real-time statistics banner */}
                <div className="grid grid-cols-2 gap-3 bg-slate-50 border border-slate-200 p-3.5 rounded-[10px] text-xs">
                  <div className="space-y-0.5">
                    <span className="text-[9px] text-slate-400 font-black uppercase font-mono">Último Acesso Registrado</span>
                    <p className="font-extrabold text-slate-800 flex items-center gap-1">
                      <Clock className="h-3.5 w-3.5 text-blue-600 shrink-0" />
                      <span>{st.lastAccess}</span>
                    </p>
                    <span className="text-[8.5px] text-slate-400 block">Nível de Risco: {st.riskLevel}</span>
                  </div>
                  <div className="space-y-0.5">
                    <span className="text-[9px] text-slate-400 font-black uppercase font-mono">Status da Conta</span>
                    <p className="font-extrabold text-slate-800 flex items-center gap-1">
                      <ShieldCheck className="h-3.5 w-3.5 text-blue-600 shrink-0" />
                      <span>Conta {st.statusConta || 'Ativa'}</span>
                    </p>
                    <span className="text-[8.5px] text-slate-400 block flex items-center gap-1">
                      <span>Senha de acesso:</span>
                      <span className="font-mono font-bold text-slate-600">••••••••</span>
                    </span>
                  </div>
                </div>

                {/* Quick configuration forms */}
                <div className="space-y-3.5">
                  <span className="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Parâmetros de Matrícula & Acesso</span>
                  
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {/* Status da matrícula select */}
                    <div>
                      <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Status da Matrícula</label>
                      <select
                        value={st.statusMatricula}
                        onChange={(e) => {
                          updateOverride(st.name, { statusMatricula: e.target.value as any });
                          showToast(`Status de matrícula de ${st.name} alterado para ${e.target.value}.`);
                        }}
                        className="w-full bg-white border border-slate-200 p-2 text-xs rounded-md text-slate-700 focus:outline-hidden"
                      >
                        <option value="Ativa">Ativa</option>
                        <option value="Sem matrícula">Sem matrícula (Desvinculado)</option>
                        <option value="Trancada">Trancada</option>
                        <option value="Concluída">Concluída</option>
                        <option value="Cancelada">Cancelada</option>
                      </select>
                    </div>

                    {/* Status da Conta select */}
                    <div>
                      <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Status da Conta</label>
                      <select
                        value={st.statusConta || 'Ativa'}
                        onChange={(e) => {
                          updateOverride(st.name, { statusConta: e.target.value as any });
                          showToast(`Status da conta de ${st.name} alterado para ${e.target.value}.`);
                        }}
                        className="w-full bg-white border border-slate-200 p-2 text-xs rounded-md text-slate-700 focus:outline-hidden"
                      >
                        <option value="Ativa">Ativa (Acesso Permitido)</option>
                        <option value="Bloqueada">Bloqueada (Acesso Suspenso)</option>
                        <option value="Aguardando confirmação">Aguardando Confirmação</option>
                      </select>
                    </div>

                     {/* Progress Control */}
                    {st.statusMatricula !== 'Sem matrícula' && (
                      <div>
                        <label className="block text-[10px] font-bold text-slate-500 uppercase mb-1">Progresso Manual (%)</label>
                        <div className="flex items-center gap-2">
                          <input
                            type="range"
                            min="0"
                            max="100"
                            value={st.progresso}
                            onChange={(e) => {
                              const val = parseInt(e.target.value);
                              const total = st.horasTotais || 80;
                              const done = Math.round((val / 100) * total);
                              updateOverride(st.name, { progresso: val, horasConcluidas: done });
                            }}
                            className="grow"
                          />
                          <span className="text-xs font-mono font-black text-slate-700 w-8 text-right">{st.progresso}%</span>
                        </div>
                      </div>
                    )}
                  </div>
                </div>

                {/* Pendencies checklist */}
                <div className="space-y-2.5">
                  <span className="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Gerenciamento de Pendências & Restrições</span>
                  
                  <div className="grid grid-cols-2 gap-2.5 bg-slate-50/50 p-4 border border-slate-200 rounded-[10px]">
                    {['Termo de Compromisso', 'Documento', 'Atividade', 'Matrícula pendente', 'Contrato'].map((pend) => {
                      const labelText = pend === 'Documento' ? 'Documental' : pend === 'Atividade' ? 'Acadêmica (Atividade)' : pend === 'Contrato' ? 'Contrato Pendente' : pend;
                      const hasPend = st.pendencias.some(p => p.includes(pend));
                      return (
                        <label key={pend} className="flex items-center gap-2 cursor-pointer select-none">
                          <input
                            type="checkbox"
                            checked={hasPend}
                            onChange={() => {
                              let updatedList = [...st.pendencias];
                              if (updatedList.includes('Nenhuma')) {
                                updatedList = updatedList.filter(x => x !== 'Nenhuma');
                              }
                              if (hasPend) {
                                updatedList = updatedList.filter(p => !p.includes(pend));
                              } else {
                                updatedList.push(pend === 'Documento' ? 'Documento' : pend === 'Atividade' ? 'Atividade' : pend);
                              }
                              if (updatedList.length === 0) {
                                updatedList.push('Nenhuma');
                              }
                              updateOverride(st.name, { pendencias: updatedList });
                              showToast(`Lista de pendências de ${st.name} atualizada.`);
                            }}
                            className="rounded border-slate-300 text-blue-600 focus:ring-blue-500 text-xs"
                          />
                          <span className="text-xs text-slate-700 font-bold">{labelText}</span>
                        </label>
                      );
                    })}
                  </div>

                  <div className="flex items-center gap-2.5">
                    <button
                      onClick={() => {
                        updateOverride(st.name, { pendencias: ['Nenhuma'] });
                        showToast(`Todas as restrições e pendências de ${st.name} foram zeradas.`);
                      }}
                      className="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 font-bold text-[10px] uppercase rounded-md transition-colors cursor-pointer"
                    >
                      Resolver Tudo
                    </button>

                    {st.pendencias.some(p => p.includes('Termo de Compromisso')) && (
                      <button
                        onClick={() => {
                          if (st.id) clearStudentPenalty(st.id);
                          const updated = st.pendencias.filter(p => !p.includes('Termo de Compromisso'));
                          updateOverride(st.name, { pendencias: updated.length === 0 ? ['Nenhuma'] : updated });
                          showToast(`A pendência do termo de compromisso de ${st.name} foi resolvida.`);
                        }}
                        className="px-3 py-1.5 bg-sky-50 hover:bg-sky-100 border border-sky-200 text-blue-800 font-bold text-[10px] uppercase rounded-md transition-colors cursor-pointer"
                      >
                        Aprovar Termo
                      </button>
                    )}
                  </div>
                </div>

                {/* Quick Actions Footer inside Modal */}
                <div className="pt-2 border-t border-slate-100 flex flex-wrap gap-2">
                  <button
                    onClick={() => {
                      setResetPassInfo({ name: st.name, email: st.email });
                    }}
                    className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px] uppercase rounded-md transition-colors cursor-pointer"
                  >
                    Mudar Senha
                  </button>
                  <button
                    onClick={() => {
                      setSendMessageInfo({ name: st.name, email: st.email });
                    }}
                    className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px] uppercase rounded-md transition-colors cursor-pointer"
                  >
                    Enviar Notificação
                  </button>
                  <button
                    onClick={() => {
                      const magicLink = `https://ava.lms.edu/magic-access?email=${st.email}&token=magic_2025`;
                      navigator.clipboard.writeText(magicLink);
                      showToast(`Link de acesso seguro de ${st.name} copiado.`);
                    }}
                    className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px] uppercase rounded-md transition-colors cursor-pointer"
                  >
                    Copiar Link Mágico
                  </button>
                </div>
              </div>

              {/* Footer controls */}
              <footer className="p-4 bg-slate-50 border-t border-slate-100 flex justify-end shrink-0">
                <button
                  onClick={() => setActiveStudentProfile(null)}
                  className="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-black text-xs rounded-md transition-colors uppercase tracking-wider cursor-pointer"
                >
                  Salvar e Fechar
                </button>
              </footer>
            </div>
          </div>
        );
      })()}

      {/* RESET PASSWORD MODAL */}
      {resetPassInfo && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="w-full max-w-sm bg-white rounded-[10px] shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200 text-left border border-slate-100">
            <header className="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <div className="flex items-center gap-2">
                <Key className="h-4 w-4 text-orange-600" />
                <h3 className="text-sm font-black text-slate-900 uppercase tracking-tight">Redefinir Senha</h3>
              </div>
              <button 
                onClick={() => setResetPassInfo(null)}
                className="p-1 hover:bg-slate-200 rounded-full transition-colors cursor-pointer text-slate-400"
              >
                <X className="h-4 w-4" />
              </button>
            </header>

            <form onSubmit={(e) => {
              e.preventDefault();
              const form = e.currentTarget;
              const newPasswordValue = (form.elements.namedItem('newPassword') as HTMLInputElement).value;
              updateOverride(resetPassInfo.name, { password: newPasswordValue });
              showToast(`A senha de ${resetPassInfo.name} foi redefinida para: ${newPasswordValue}`);
              speakText(`A senha de ${resetPassInfo.name} foi atualizada.`);
              setResetPassInfo(null);
            }} className="p-4 space-y-4">
              <p className="text-[11px] text-slate-500 leading-relaxed">
                Insira abaixo a nova credencial de segurança para o aluno <strong className="font-bold text-slate-800">{resetPassInfo.name}</strong>. Esta ação revogará qualquer senha anterior de acesso.
              </p>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nova Senha</label>
                <input
                  name="newPassword"
                  type="text"
                  required
                  placeholder="Ex: 5678"
                  defaultValue={Math.floor(1000 + Math.random() * 9000).toString()}
                  className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800 font-mono focus:outline-hidden focus:ring-1 focus:ring-slate-400"
                />
              </div>

              <div className="flex gap-2 justify-end pt-2">
                <button
                  type="button"
                  onClick={() => setResetPassInfo(null)}
                  className="px-3 py-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors uppercase tracking-widest cursor-pointer"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-md transition-colors uppercase tracking-wider cursor-pointer"
                >
                  Definir Senha
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* SEND MESSAGE MODAL */}
      {sendMessageInfo && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="w-full max-w-md bg-white rounded-[10px] shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200 text-left border border-slate-100">
            <header className="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <div className="flex items-center gap-2">
                <Mail className="h-4 w-4 text-blue-600" />
                <h3 className="text-sm font-black text-slate-900 uppercase tracking-tight">Enviar Mensagem</h3>
              </div>
              <button 
                onClick={() => setSendMessageInfo(null)}
                className="p-1 hover:bg-slate-200 rounded-full transition-colors cursor-pointer text-slate-400"
              >
                <X className="h-4 w-4" />
              </button>
            </header>

            <form onSubmit={(e) => {
              e.preventDefault();
              showToast(`Mensagem enviada com sucesso para o canal de ${sendMessageInfo.name}!`);
              speakText(`Mensagem despachada.`);
              setSendMessageInfo(null);
            }} className="p-4 space-y-4">
              <p className="text-[11px] text-slate-500 leading-relaxed">
                Escreva abaixo a notificação push ou e-mail que será disparado para <strong className="font-bold text-slate-800">{sendMessageInfo.name}</strong> ({sendMessageInfo.email}).
              </p>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Conteúdo da Notificação</label>
                <textarea
                  required
                  rows={4}
                  placeholder="Ex: Caro aluno, identificamos que há uma atividade avaliativa com prazo final de expiração programada para esta noite..."
                  className="w-full border border-slate-200 p-2 text-xs rounded-md text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-slate-400 resize-none"
                />
              </div>

              <div className="flex gap-2 justify-end pt-2">
                <button
                  type="button"
                  onClick={() => setSendMessageInfo(null)}
                  className="px-3 py-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors uppercase tracking-widest cursor-pointer"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-md transition-colors uppercase tracking-wider cursor-pointer"
                >
                  Enviar
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* SUCCESS TOAST NOTIFICATE */}
      {toastMsg && (
        <div className="fixed bottom-6 right-6 z-50 rounded-md bg-slate-900 border border-slate-700 text-slate-100 px-5 py-3 shadow-2xl flex items-center gap-3 animate-in fade-in slide-in-from-bottom-2 duration-300 max-w-sm">
          <span className="h-2 w-2 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
          <p className="text-xs font-bold leading-normal text-left">{toastMsg}</p>
        </div>
      )}

        </main>
      </div>
    </div>
  );
}
