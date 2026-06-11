import React, { useState, useEffect } from 'react';
import { motion } from 'motion/react';
import { 
  User, Mail, ArrowLeft, Shield, Award, BookOpen, Sparkles, 
  Clock, Settings, Volume2, VolumeX, Eye, EyeOff, RefreshCw, 
  Trash2, FileText, CheckCircle2, Copy, Check, Globe, Layout, Gauge,
  Lock, Key, Fingerprint, ShieldAlert, Camera, Upload
} from 'lucide-react';
import { useLMS } from '../context/LMSContext';

interface ProfileViewProps {
  onBack: () => void;
  speakText: (text: string) => void;
}

const AVATAR_PRESETS = [
  { id: 'cosmic', emoji: '🚀', label: 'Estudante Cósmico', color: 'bg-indigo-50 border-indigo-200 text-indigo-700' },
  { id: 'creative', emoji: '🎨', label: 'Criador Cultural', color: 'bg-emerald-50 border-emerald-250 text-emerald-700' },
  { id: 'reader', emoji: '📚', label: 'Leitor Devoto', color: 'bg-amber-50 border-amber-200 text-amber-700' },
  { id: 'dev', emoji: '💻', label: 'Hacker Cultural', color: 'bg-slate-900 border-slate-950 text-[#FFD23F]' },
  { id: 'star', emoji: '🌟', label: 'Superstar', color: 'bg-rose-50 border-rose-220 text-rose-650' },
  { id: 'peace', emoji: '🕊️', label: 'Embaixador da Paz', color: 'bg-sky-50 border-sky-200 text-sky-600' }
];

export function ProfileView({
  onBack,
  speakText
}: ProfileViewProps) {
  const { 
    activeUser, 
    updateUserName, 
    courses, 
    progress, 
    certificates, 
    quizSubmissions,
    studentsList,
    professorsList,
    toggleUserRole,
    accessibilitySettings,
    updateAccessibilitySettings,
    isSpeechEnabled,
    setIsSpeechEnabled,
    currentLang,
    setCurrentLang,
    textSizeMultiplier,
    setTextSizeMultiplier
  } = useLMS();

  // Local state for profile configurations
  const [editableName, setEditableName] = useState(activeUser.name);
  const [copiedIndex, setCopiedIndex] = useState<number | null>(null);
  
  // State to manage showing the main Profile view or the New password change view
  const [currentTab, setCurrentTab] = useState<'profile' | 'password'>('profile');

  // Password shift parameters
  const [docType, setDocType] = useState<'cpf' | 'rg'>('cpf');
  const [docNumber, setDocNumber] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [validationError, setValidationError] = useState('');
  const [isResetSuccess, setIsResetSuccess] = useState(false);
  const [isDocVerified, setIsDocVerified] = useState(false);
  const [profileError, setProfileError] = useState('');
  const [currentPasswordInput, setCurrentPasswordInput] = useState('');
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [saveSuccess, setSaveSuccess] = useState(false);

  // States and Handlers for Upload and Web Camera Capture for Avatar
  const [customAvatar, setCustomAvatar] = useState<string | null>(() => {
    return localStorage.getItem('ava_profile_custom_avatar') || null;
  });
  const [isCameraActive, setIsCameraActive] = useState(false);
  const [cameraError, setCameraError] = useState('');
  const [stream, setStream] = useState<MediaStream | null>(null);

  const startCamera = async () => {
    setCameraError('');
    setIsCameraActive(true);
    try {
      const mediaStream = await navigator.mediaDevices.getUserMedia({ 
        video: { width: 300, height: 300, facingMode: 'user' } 
      });
      setStream(mediaStream);
      speakText("Câmera de captura ativada. Posicione sua face e clique no botão para capturar.");
    } catch (err: any) {
      console.error(err);
      setCameraError('Permissão para câmera negada ou dispositivo indisponível.');
      speakText("Erro: Não foi possível carregar a câmera.");
    }
  };

  const stopCamera = () => {
    if (stream) {
      stream.getTracks().forEach(track => track.stop());
      setStream(null);
    }
    setIsCameraActive(false);
  };

  const capturePhoto = () => {
    const video = document.getElementById('webcam-feed') as HTMLVideoElement | null;
    if (video) {
      const canvas = document.createElement('canvas');
      canvas.width = 300;
      canvas.height = 300;
      const ctx = canvas.getContext('2d');
      if (ctx) {
        const size = Math.min(video.videoWidth, video.videoHeight);
        const xOffset = (video.videoWidth - size) / 2;
        const yOffset = (video.videoHeight - size) / 2;
        ctx.drawImage(video, xOffset, yOffset, size, size, 0, 0, 300, 300);
        const dataUrl = canvas.toDataURL('image/jpeg');
        setCustomAvatar(dataUrl);
        localStorage.setItem('ava_profile_custom_avatar', dataUrl);
        speakText("Foto registrada no navegador com sucesso!");
        stopCamera();
      }
    }
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 2 * 1024 * 1024) {
        alert('A imagem inserida ultrapassa o limite de 2MB do sistema.');
        return;
      }
      const reader = new FileReader();
      reader.onload = (event) => {
        const result = event.target?.result as string;
        setCustomAvatar(result);
        localStorage.setItem('ava_profile_custom_avatar', result);
        speakText("Sua foto de perfil foi alterada e gravada.");
      };
      reader.readAsDataURL(file);
    }
  };

  const handleRemovePhoto = () => {
    setCustomAvatar(null);
    localStorage.removeItem('ava_profile_custom_avatar');
    speakText("Foto de perfil padrão reestabelecida.");
  };

  useEffect(() => {
    return () => {
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
      }
    };
  }, [stream]);

  // Document mask and formatting helper
  const formatDocument = (value: string, type: 'cpf' | 'rg') => {
    const numbers = value.replace(/\D/g, ''); // Clear alphanumeric symbols
    if (type === 'cpf') {
      if (numbers.length <= 3) return numbers;
      if (numbers.length <= 6) return `${numbers.slice(0, 3)}.${numbers.slice(3)}`;
      if (numbers.length <= 9) return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6)}`;
      return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6, 9)}-${numbers.slice(9, 11)}`;
    } else {
      if (numbers.length <= 2) return numbers;
      if (numbers.length <= 5) return `${numbers.slice(0, 2)}.${numbers.slice(2)}`;
      if (numbers.length <= 8) return `${numbers.slice(0, 2)}.${numbers.slice(2, 5)}.${numbers.slice(5)}`;
      return `${numbers.slice(0, 2)}.${numbers.slice(2, 5)}.${numbers.slice(5, 8)}-${numbers.slice(8, 9)}`;
    }
  };

  const getPasswordStrength = (pass: string) => {
    if (!pass) return { label: 'Em branco', color: 'text-slate-400 bg-slate-100', width: 'w-0' };
    if (pass.length < 6) return { label: 'Fraca', color: 'text-rose-650 bg-rose-50 border-rose-220', width: 'w-1/3 bg-rose-500' };
    if (pass.length < 9) return { label: 'Média', color: 'text-amber-700 bg-amber-50 border-amber-200', width: 'w-2/3 bg-amber-500' };
    return { label: 'Forte', color: 'text-emerald-700 bg-emerald-50 border-emerald-200', width: 'w-full bg-emerald-500' };
  };
  
  // Simulated stats persisted in LocalStorage to feel persistent and realistic
  const [statusPhrase, setStatusPhrase] = useState(() => {
    return localStorage.getItem('ava_profile_status') || 'Explorando novas rotas de Arte e Economia Criativa 🎭';
  });
  
  const [simulatedEmail, setSimulatedEmail] = useState(() => {
    return localStorage.getItem('ava_profile_email') || `${activeUser.name.toLowerCase().replace(/\s+/g, '.')}@lms.edu`;
  });

  const [selectedAvatarId, setSelectedAvatarId] = useState(() => {
    return localStorage.getItem('ava_profile_avatar_id') || 'cosmic';
  });

  const [presenceStatus, setPresenceStatus] = useState<'online' | 'offline'>(() => {
    return (localStorage.getItem(`ava_presence_status_${activeUser.name}`) as 'online' | 'offline') || 
           (localStorage.getItem('ava_profile_presence_status') as 'online' | 'offline') || 
           'online';
  });

  const togglePresenceStatus = () => {
    const next = presenceStatus === 'online' ? 'offline' : 'online';
    setPresenceStatus(next);
    localStorage.setItem(`ava_presence_status_${activeUser.name}`, next);
    localStorage.setItem('ava_profile_presence_status', next);
    speakText(`Status de presença alterado para ${next === 'online' ? 'online' : 'offline'}`);
  };

  // Handle changing avatar
  const handleSelectAvatar = (id: string, label: string) => {
    setSelectedAvatarId(id);
    localStorage.setItem('ava_profile_avatar_id', id);
    speakText(`Avatar alterado para ${label}`);
  };

  // Sync state if user logins as someone else
  useEffect(() => {
    setEditableName(activeUser.name);
    // Auto generate email if changed
    setSimulatedEmail(localStorage.getItem('ava_profile_email') || `${activeUser.name.toLowerCase().replace(/\s+/g, '.')}@lms.edu`);
    
    // Also load status for active user name
    const storedStatus = (localStorage.getItem(`ava_presence_status_${activeUser.name}`) as 'online' | 'offline') || 'online';
    setPresenceStatus(storedStatus);
  }, [activeUser.name]);

  // Save details
  const handleSaveDetails = () => {
    setProfileError('');
    setSaveSuccess(false);

    const storedPassword = localStorage.getItem(`ava_active_password_${activeUser.name}`) || '123456';
    if (!currentPasswordInput) {
      setProfileError('Por favor, informe sua senha atual para autorizar e homologar as alterações.');
      speakText('Aviso: Senha em branco.');
      return;
    }
    if (currentPasswordInput !== storedPassword) {
      setProfileError('A senha atual inserida está incorreta. Confirme os caracteres digitados.');
      speakText('Aviso: Senha incorreta.');
      return;
    }

    if (!editableName.trim()) {
      setProfileError('O campo de nome não pode ficar vazio.');
      speakText('Aviso: Nome civil não pode ficar vazio.');
      return;
    }

    updateUserName(editableName);
    localStorage.setItem('ava_profile_status', statusPhrase);
    localStorage.setItem('ava_profile_email', simulatedEmail);
    setCurrentPasswordInput(''); // Clear verification input
    setSaveSuccess(true);

    speakText('Informações básicas atualizadas e homologadas no sistema com sucesso!');
    setTimeout(() => setSaveSuccess(false), 5000);
  };

  const copyToClipboard = (text: string, index: number) => {
    navigator.clipboard.writeText(text);
    setCopiedIndex(index);
    speakText('Código copiado para a área de transferência');
    setTimeout(() => setCopiedIndex(null), 2000);
  };

  const handleResetDatabase = () => {
    if (confirm('Tem certeza que deseja restaurar as configurações padrão e limpar o cache do simulador?')) {
      localStorage.clear();
      speakText('Simulador reconfigurado para o estado original.');
      window.location.reload();
    }
  };

  const selectedAvatar = AVATAR_PRESETS.find(a => a.id === selectedAvatarId) || AVATAR_PRESETS[0];

  // Calculations for Student metrics
  const totalCourses = courses.length;
  const studentCerts = certificates.filter(c => c.studentName === activeUser.name);
  
  // Calculate average progress
  const studentProgressRecords = progress.filter(p => p.studentName === activeUser.name);
  const averageProgressPercent = studentProgressRecords.length > 0
    ? Math.round(studentProgressRecords.reduce((acc, current) => {
        const course = courses.find(c => c.id === current.courseId);
        if (!course) return acc;
        const totalLessons = course.lessons.length;
        const completedLessons = current.completedLessonIds.length;
        const percent = totalLessons > 0 ? (completedLessons / totalLessons) * 100 : 0;
        return acc + percent;
      }, 0) / studentProgressRecords.length)
    : 0;

  if (currentTab === 'password') {
    const strength = getPasswordStrength(newPassword);
    return (
      <div className="mx-auto max-w-2xl px-4 py-8 animate-in fade-in slide-in-from-bottom duration-300 text-left">
        
        {/* Upper header section */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
          <button
            type="button"
            onClick={() => {
              speakText("Voltando para as configurações de perfil.");
              setCurrentTab('profile');
              setIsResetSuccess(false);
            }}
            className="group flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-slate-900 transition-all bg-white border border-slate-200 px-4 py-2 rounded-xl cursor-pointer shadow-3xs"
            title="Retornar ao perfil"
          >
            <ArrowLeft className="h-4 w-4 group-hover:-translate-x-1 transition-transform" />
            <span>Voltar ao Meu Perfil</span>
          </button>

          <div className="flex items-center gap-2">
            <span className="h-2 w-2 rounded-full bg-indigo-500 animate-pulse" />
            <span className="text-[10px] text-slate-400 font-mono font-bold uppercase tracking-widest">
              Validador de Segurança Acadêmica
            </span>
          </div>
        </div>

        {isResetSuccess ? (
          <div className="bg-white border border-slate-200 rounded-2xl shadow-sm p-8 text-center space-y-6">
            <div className="h-16 w-16 bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-full flex items-center justify-center mx-auto text-2xl font-black">
              ✓
            </div>
            <div className="space-y-2">
              <h3 className="text-lg font-black text-slate-900">Senha Alterada com Sucesso!</h3>
              <p className="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
                Suas novas credenciais foram homologadas com sucesso. A partir de agora, use a sua nova senha para acessar o seu ambiente acadêmico no AVA.
              </p>
            </div>

            <div className="bg-slate-50 border border-slate-150 rounded-xl p-4 max-w-md mx-auto space-y-2.5">
              <div className="flex justify-between text-xs">
                <span className="text-slate-400 font-bold font-mono">USUÁRIO:</span>
                <span className="text-slate-800 font-black">{activeUser.name}</span>
              </div>
              <div className="flex justify-between text-xs">
                <span className="text-slate-400 font-bold font-mono">DOCUMENTO:</span>
                <span className="text-slate-850 font-black">
                  {docType.toUpperCase()}: {docNumber}
                </span>
              </div>
              <div className="flex justify-between text-xs">
                <span className="text-slate-400 font-bold font-mono">SENHA CADASTRADA:</span>
                <span className="text-emerald-700 font-mono font-black">•••••••• (Salva e Sincronizada)</span>
              </div>
            </div>

            <button
              type="button"
              onClick={() => {
                speakText("Retornando ao perfil principal.");
                setCurrentTab('profile');
                setIsResetSuccess(false);
              }}
              className="px-6 py-2.5 bg-[#540D6E] hover:bg-[#3D0A50] text-[#FFFFFF] font-semibold rounded-xl text-xs transition-colors cursor-pointer uppercase tracking-wider"
            >
              Concluir e Voltar
            </button>
          </div>
        ) : (
          <form 
            onSubmit={(e) => {
              e.preventDefault();
              setValidationError('');

              const rawDoc = docNumber.replace(/\D/g, '');
              if (docType === 'cpf' && rawDoc.length < 11) {
                setValidationError('O seu CPF precisa conter 11 dígitos numéricos válidos.');
                speakText("Aviso: O seu CPF precisa conter 11 dígitos.");
                return;
              }
              if (docType === 'rg' && rawDoc.length < 7) {
                setValidationError('A identidade (RG) inserida precisa ter ao menos 7 dígitos válidos.');
                speakText("Aviso: A identidade precisa ter ao menos 7 dígitos.");
                return;
              }

              if (!isDocVerified) {
                setIsDocVerified(true);
                speakText("Documento validado com sucesso! Os campos de alteração de senha foram habilitados.");
                return;
              }

              if (newPassword.length < 6) {
                setValidationError('A nova senha precisa ter no mínimo 6 caracteres.');
                speakText("Aviso: A nova senha precisa ter no mínimo 6 caracteres.");
                return;
              }
              if (newPassword !== confirmPassword) {
                setValidationError('A confirmação de senha não coincide com a nova senha digitada.');
                speakText("Aviso: As senhas digitadas não coincidem.");
                return;
              }

              // Salvar simulação no localStorage
              localStorage.setItem(`ava_active_password_${activeUser.name}`, newPassword);
              localStorage.setItem(`ava_active_doc_${activeUser.name}`, docNumber);
              localStorage.setItem(`ava_active_doc_type_${activeUser.name}`, docType);
              
              setIsResetSuccess(true);
              speakText("Senha de acesso atualizada com sucesso!");
            }}
            className="bg-white border border-slate-200 rounded-2xl shadow-3xs p-6 sm:p-8 space-y-6 text-left"
          >
            <div className="border-b border-slate-100 pb-4">
              <div className="flex items-center gap-2.5 text-[#540D6E] mb-1">
                <Key className="h-5 w-5" />
                <h2 className="text-base font-black text-slate-900 leading-none">
                  Alteração de Senha de Segurança
                </h2>
              </div>
              <p className="text-[11px] text-slate-400">
                Altere de forma autônoma a sua credencial. É obrigatório fornecer o CPF ou RG de cadastro para fins de conformidade legal de identidade.
              </p>
            </div>

            {validationError && (
              <div className="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs font-semibold flex items-center gap-2.5">
                <ShieldAlert className="h-4.5 w-4.5 text-rose-500 shrink-0" />
                <span>{validationError}</span>
              </div>
            )}

            {/* Document Verification Selector and Input */}
            <div className={`space-y-4 transition-all duration-300 ${isDocVerified ? 'opacity-60 pointer-events-none' : ''}`}>
              <div>
                <label className="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-2">
                  1. Selecione o Documento para Validação
                </label>
                <div className="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    disabled={isDocVerified}
                    onClick={() => {
                      setDocType('cpf');
                      setDocNumber('');
                      setValidationError('');
                      speakText("Modo de validação CPF ativo.");
                    }}
                    className={`p-3 rounded-xl border-2 text-xs font-black text-center transition-all flex items-center justify-center gap-2 ${
                      isDocVerified ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'
                    } ${
                      docType === 'cpf'
                        ? 'border-[#540D6E] bg-purple-50/20 text-[#540D6E]'
                        : 'border-slate-100 bg-slate-50/55 text-slate-500 hover:border-slate-200'
                    }`}
                  >
                    <Fingerprint className="h-4 w-4" />
                    <span>CPF Geral</span>
                  </button>
                  <button
                    type="button"
                    disabled={isDocVerified}
                    onClick={() => {
                      setDocType('rg');
                      setDocNumber('');
                      setValidationError('');
                      speakText("Modo de validação identidade ativo.");
                    }}
                    className={`p-3 rounded-xl border-2 text-xs font-black text-center transition-all flex items-center justify-center gap-2 ${
                      isDocVerified ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'
                    } ${
                      docType === 'rg'
                        ? 'border-[#540D6E] bg-purple-50/20 text-[#540D6E]'
                        : 'border-slate-100 bg-slate-50/55 text-slate-500 hover:border-slate-200'
                    }`}
                  >
                    <FileText className="h-4 w-4" />
                    <span>Identidade (RG)</span>
                  </button>
                </div>
              </div>

              <div>
                <label className="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">
                  informe seu {docType === 'cpf' ? 'CPF' : 'RG (Identidade)'}
                </label>
                <div className="relative">
                  <Fingerprint className="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400" />
                  <input
                    type="text"
                    required
                    disabled={isDocVerified}
                    placeholder={docType === 'cpf' ? '000.000.000-00' : '00.000.000-0'}
                    value={docNumber}
                    onChange={(e) => {
                      const val = formatDocument(e.target.value, docType);
                      setDocNumber(val);
                    }}
                    className="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-[#540D6E] outline-none transition-all disabled:opacity-70 disabled:bg-slate-100"
                  />
                </div>
                <span className="text-[9.5px] text-slate-400 block mt-1">
                  {docType === 'cpf' 
                    ? 'Digite os 11 números de seu CPF. A formatação de pontos é automática.' 
                    : 'Digite os dígitos de seu RG de identidade civil, incluindo letras se aplicável.'}
                </span>
              </div>
            </div>

            {/* Document Action / Verified notification */}
            {isDocVerified ? (
              <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-emerald-800 text-xs font-semibold animate-in fade-in duration-200">
                <div className="flex items-center gap-2">
                  <CheckCircle2 className="h-4.5 w-4.5 text-emerald-600 shrink-0" />
                  <span>Documento verificado: {docType.toUpperCase()} ({docNumber})</span>
                </div>
                <button
                  type="button"
                  onClick={() => {
                    setIsDocVerified(false);
                    speakText("Modo de edição do documento liberado.");
                  }}
                  className="text-[10px] text-emerald-700 hover:bg-emerald-100 transition-colors font-bold uppercase tracking-wider cursor-pointer bg-white px-2.5 py-1 rounded-md border border-emerald-200"
                >
                  Alterar
                </button>
              </div>
            ) : (
              <button
                type="button"
                onClick={() => {
                  setValidationError('');
                  const rawDoc = docNumber.replace(/\D/g, '');
                  if (docType === 'cpf' && rawDoc.length < 11) {
                    setValidationError('O seu CPF precisa conter 11 dígitos numéricos válidos.');
                    speakText("Aviso: O seu CPF precisa conter 11 dígitos.");
                    return;
                  }
                  if (docType === 'rg' && rawDoc.length < 7) {
                    setValidationError('A identidade (RG) inserida precisa ter ao menos 7 dígitos válidos.');
                    speakText("Aviso: A identidade precisa ter ao menos 7 dígitos.");
                    return;
                  }
                  setIsDocVerified(true);
                  speakText("Documento validado com sucesso! Os campos de alteração de senha foram habilitados.");
                }}
                className="w-full bg-[#540D6E] hover:bg-[#3D0A50] text-[#FFFFFF] font-bold py-2.5 rounded-xl text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-3xs uppercase tracking-wider"
              >
                <Fingerprint className="h-4 w-4 text-white" />
                <span>Validar Documento para Prosseguir</span>
              </button>
            )}

            {/* Type Passwords */}
            <div className={`space-y-4 pt-4 border-t border-slate-100 transition-all duration-300 ${
              isDocVerified ? 'opacity-100' : 'opacity-40 pointer-events-none select-none relative'
            }`}>
              <div className="flex items-center justify-between">
                <label className="text-[10px] font-black text-slate-400 uppercase tracking-wider block">
                  2. Configurar Nova Senha Segura
                </label>
                {!isDocVerified && (
                  <span className="text-[9px] bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-md font-bold flex items-center gap-1">
                    <Lock className="h-3 w-3" /> Bloqueado — Valide o documento acima
                  </span>
                )}
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="text-[10px] font-semibold text-slate-550 block mb-1">
                    Digite a Nova Senha
                  </label>
                  <div className="relative">
                    <Lock className="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                      type={showNewPassword ? 'text' : 'password'}
                      required={isDocVerified}
                      disabled={!isDocVerified}
                      placeholder={isDocVerified ? "Mínimo 6 caracteres" : "Autentique o documento acima"}
                      value={newPassword}
                      onChange={(e) => setNewPassword(e.target.value)}
                      className="w-full pl-10 pr-10 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-[#540D6E] outline-none transition-all"
                    />
                    <button
                      type="button"
                      disabled={!isDocVerified}
                      onClick={() => setShowNewPassword(!showNewPassword)}
                      className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-650 cursor-pointer disabled:pointer-events-none"
                    >
                      {showNewPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>

                  {/* Password strength indicator widget */}
                  {newPassword && isDocVerified && (
                    <div className="mt-2.5 space-y-1">
                      <div className="flex justify-between items-center text-[10px]">
                        <span className="text-slate-455">Complexidade do código:</span>
                        <span className="font-extrabold text-slate-700">{strength.label}</span>
                      </div>
                      <div className="h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                        <div className={`h-full transition-all duration-300 ${strength.width}`} />
                      </div>
                    </div>
                  )}
                </div>

                <div>
                  <label className="text-[10px] font-semibold text-slate-550 block mb-1">
                    Confirme a Senha Digitada
                  </label>
                  <div className="relative">
                    <Lock className="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400" />
                    <input
                      type={showConfirmPassword ? 'text' : 'password'}
                      required={isDocVerified}
                      disabled={!isDocVerified}
                      placeholder={isDocVerified ? "Igual à senha anterior" : "Autentique o documento acima"}
                      value={confirmPassword}
                      onChange={(e) => setConfirmPassword(e.target.value)}
                      className="w-full pl-10 pr-10 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-[#540D6E] outline-none transition-all"
                    />
                    <button
                      type="button"
                      disabled={!isDocVerified}
                      onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                      className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-650 cursor-pointer disabled:pointer-events-none"
                    >
                      {showConfirmPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div className="pt-4 border-t border-slate-100 flex flex-col xs:flex-row gap-3">
              <button
                type="button"
                onClick={() => {
                  speakText("Operação cancelada.");
                  setCurrentTab('profile');
                  setIsResetSuccess(false);
                }}
                className="order-2 xs:order-1 px-4 py-2.5 border border-slate-240 text-slate-600 hover:text-slate-850 font-bold rounded-xl text-xs text-center transition-colors cursor-pointer uppercase tracking-wider"
              >
                Cancelar
              </button>
              <button
                type="submit"
                disabled={!isDocVerified}
                className={`order-1 xs:order-2 flex-1 font-semibold py-2.5 rounded-xl text-xs transition-colors flex items-center justify-center gap-2 shadow-3xs uppercase tracking-wider ${
                  isDocVerified 
                    ? 'bg-[#540D6E] hover:bg-[#3D0A50] text-[#FFFFFF] cursor-pointer' 
                    : 'bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed opacity-50'
                }`}
              >
                <CheckCircle2 className="h-4 w-4 text-white" />
                <span>Salvar Nova Senha</span>
              </button>
            </div>

          </form>
        )}
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 md:px-6 animate-in fade-in duration-300">
      
      {/* Upper header section */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
        <button
          onClick={() => {
            window.dispatchEvent(new Event('reset-dashboard'));
            speakText("Voltando para o painel anterior.");
            onBack();
          }}
          className="group flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-slate-900 transition-all bg-white border border-slate-200 px-4 py-2 rounded-xl cursor-pointer shadow-3xs"
          title="Retornar"
        >
          <ArrowLeft className="h-4 w-4 group-hover:-translate-x-1 transition-transform" />
          <span>Voltar ao Painel</span>
        </button>

        <div 
          onClick={togglePresenceStatus}
          className="flex items-center gap-2 bg-slate-50/50 hover:bg-slate-50 border border-slate-200 hover:border-indigo-300 px-3.5 py-2 rounded-xl transition-all cursor-pointer select-none group shadow-3xs"
          title="Clique para alternar seu status de presença (Online / Offline)"
        >
          <span className={`h-2.5 w-2.5 rounded-full transition-all ${presenceStatus === 'online' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'}`} />
          <span className="text-[10px] text-slate-450 font-mono font-bold uppercase tracking-widest flex items-center gap-1.5">
            <span>Status:</span>
            <strong className={presenceStatus === 'online' ? 'text-emerald-600 font-extrabold' : 'text-slate-500 font-extrabold'}>
              {presenceStatus === 'online' ? 'Online' : 'Offline'}
            </strong>
            <span className="text-[8px] font-sans font-black text-indigo-650 opacity-40 group-hover:opacity-100 transition-opacity lowercase font-mono">(alterar)</span>
          </span>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-7">
        
        {/* LEFT COLUMN: Identity + Preset Avatar edit + Cohesive Accessibility Settings */}
        <div className="lg:col-span-5 space-y-6">
          
          {/* Bento-block 1: Interactive Profile Card */}
          <div className="bg-white border border-slate-200/80 rounded-2xl shadow-3xs p-6 relative overflow-hidden text-left">
            <div className="absolute top-0 right-0 w-32 h-32 bg-indigo-50/50 rounded-full filter blur-xl pointer-events-none" />
            
            <span className="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-4">
              Identidade do Usuário
            </span>

            {/* Profile main representation */}
            <div className="flex items-center gap-4 mb-6">
              <div className={`h-16 w-16 rounded-2xl border-2 flex items-center justify-center text-4xl shadow-sm shrink-0 transition-transform hover:scale-105 duration-200 overflow-hidden ${
                customAvatar ? 'border-slate-200 bg-slate-50' : selectedAvatar.color
              }`}>
                {customAvatar ? (
                  <img 
                    src={customAvatar} 
                    alt="Foto de perfil" 
                    className="w-full h-full object-cover"
                    referrerPolicy="no-referrer"
                  />
                ) : (
                  selectedAvatar.emoji
                )}
              </div>
              <div className="min-w-0">
                <h4 className="text-base font-black text-slate-900 leading-tight">
                  {activeUser.name}
                </h4>
                <p className="text-[10px] text-indigo-600 font-bold uppercase tracking-wider mt-0.5">
                  {activeUser.role === 'student' && 'Discente Credenciado'}
                  {activeUser.role === 'instructor' && 'Docente Avaliador'}
                  {activeUser.role === 'admin' && 'Moderação Coordenadora'}
                </p>
                <p className="text-[11px] text-slate-400 truncate max-w-[200px]">
                  {simulatedEmail}
                </p>
              </div>
            </div>

            {/* Custom Photo Upload & Capture Actions */}
            <div className="mb-6 bg-slate-50 border border-slate-200/60 p-3 rounded-2xl space-y-3">
              <span className="text-[9px] font-black text-slate-500 uppercase tracking-wider block">
                Foto de Exibição Personalizada
              </span>
              
              <div className="flex flex-wrap gap-2">
                {/* Upload Action */}
                <label className="flex-1 min-w-[120px]">
                  <input
                    type="file"
                    accept="image/*"
                    onChange={handleFileUpload}
                    className="hidden"
                  />
                  <span className="w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-white hover:bg-slate-50 border border-slate-200 hover:border-slate-350 text-slate-700 rounded-xl text-[11px] font-black cursor-pointer transition-all uppercase tracking-wider">
                    <Upload className="h-3.5 w-3.5 text-indigo-600 animate-pulse" />
                    <span>Upload</span>
                  </span>
                </label>

                {/* Capture Action */}
                {isCameraActive ? (
                  <button
                    type="button"
                    onClick={stopCamera}
                    className="flex-1 min-w-[120px] flex items-center justify-center gap-1.5 px-3 py-2 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 rounded-xl text-[11px] font-black cursor-pointer transition-all uppercase tracking-wider"
                  >
                    <span>Fechar Câmera</span>
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={startCamera}
                    className="flex-1 min-w-[120px] flex items-center justify-center gap-1.5 px-3 py-2 bg-white hover:bg-slate-50 border border-slate-200 hover:border-slate-350 text-slate-700 rounded-xl text-[11px] font-black cursor-pointer transition-all uppercase tracking-wider"
                  >
                    <Camera className="h-3.5 w-3.5 text-indigo-600" />
                    <span>Tirar Foto</span>
                  </button>
                )}

                {/* Remove Custom Photo (if set) */}
                {customAvatar && (
                  <button
                    type="button"
                    onClick={handleRemovePhoto}
                    className="p-2 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-600 rounded-xl transition-all cursor-pointer"
                    title="Remover foto personalizada"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                )}
              </div>

              {/* Camera Video Area */}
              {isCameraActive && (
                <div className="p-2 border border-slate-200 bg-white rounded-xl space-y-2 text-center animate-in fade-in duration-200">
                  {cameraError ? (
                    <p className="text-[10px] font-bold text-rose-600">{cameraError}</p>
                  ) : (
                    <>
                      <div className="relative aspect-square w-36 mx-auto bg-black rounded-lg overflow-hidden border border-slate-100">
                        <video
                          id="webcam-feed"
                          autoPlay
                          playsInline
                          ref={(video) => {
                            if (video && stream && video.srcObject !== stream) {
                              video.srcObject = stream;
                            }
                          }}
                          className="w-full h-full object-cover scale-x-[-1]"
                        />
                      </div>
                      <button
                        type="button"
                        onClick={capturePhoto}
                        className="px-3 py-1.5 bg-[#540D6E] hover:bg-[#3D0A50] text-[#FFFFFF] text-[10px] font-black rounded-lg uppercase tracking-wider cursor-pointer"
                      >
                        Capturar Agora
                      </button>
                    </>
                  )}
                </div>
              )}
            </div>

            {/* Select customizable virtual avatar */}
            <div className="space-y-2 mb-6">
              <label className="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                Selecione seu Avatar Temático
              </label>
              <div className="grid grid-cols-6 gap-2">
                {AVATAR_PRESETS.map((p) => (
                  <button
                    key={p.id}
                    onClick={() => handleSelectAvatar(p.id, p.label)}
                    className={`h-10 rounded-xl flex items-center justify-center text-lg border-2 transition-all cursor-pointer ${
                      selectedAvatarId === p.id 
                        ? 'border-indigo-600 ring-2 ring-indigo-50 scale-105' 
                        : 'border-slate-100 hover:border-slate-300'
                    }`}
                    title={p.label}
                  >
                    {p.emoji}
                  </button>
                ))}
              </div>
            </div>

            {/* Editing form inputs */}
            <div className="space-y-4">
              <div>
                <label className="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">
                  Nome Civil Completo
                </label>
                <div className="relative">
                  <User className="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400" />
                  <input
                    type="text"
                    value={editableName}
                    onChange={(e) => setEditableName(e.target.value)}
                    className="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-550 outline-none transition-all"
                  />
                </div>
              </div>

              <div>
                <label className="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">
                  E-mail Institucional Associado
                </label>
                <div className="relative">
                  <Mail className="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400" />
                  <input
                    type="email"
                    value={simulatedEmail}
                    onChange={(e) => setSimulatedEmail(e.target.value)}
                    className="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-550 outline-none transition-all"
                  />
                </div>
              </div>

              <div>
                <label className="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">
                  Status Acadêmico Atual / Biografia
                </label>
                <textarea
                  value={statusPhrase}
                  onChange={(e) => setStatusPhrase(e.target.value)}
                  rows={2}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-550 outline-none resize-none transition-all"
                  placeholder="Descreva seu momento atual..."
                />
              </div>

              <div className="pt-2 border-t border-slate-100">
                <label className="text-[10px] font-black text-[#540D6E] uppercase tracking-wider block mb-1 flex items-center justify-between">
                  <span>Confirmação de Segurança</span>
                  <span className="text-[8.5px] text-slate-400 font-normal normal-case">Necessário para salvar alterações</span>
                </label>
                <div className="relative">
                  <Lock className="absolute left-3.5 top-2.5 h-4 w-4 text-slate-400" />
                  <input
                    type={showCurrentPassword ? 'text' : 'password'}
                    value={currentPasswordInput}
                    onChange={(e) => setCurrentPasswordInput(e.target.value)}
                    placeholder="Sua senha atual de acesso (Padrão: 123456)"
                    className="w-full pl-10 pr-10 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-550/20 focus:border-indigo-600 outline-none transition-all placeholder:text-slate-400"
                  />
                  <button
                    type="button"
                    onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                    className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-650 cursor-pointer"
                  >
                    {showCurrentPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
              </div>

              {profileError && (
                <div className="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs font-semibold flex items-center gap-2.5 animate-in fade-in duration-200">
                  <ShieldAlert className="h-4.5 w-4.5 text-rose-500 shrink-0" />
                  <span>{profileError}</span>
                </div>
              )}

              {saveSuccess && (
                <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs font-semibold flex items-center gap-2.5 animate-in fade-in duration-200">
                  <CheckCircle2 className="h-4.5 w-4.5 text-emerald-600 shrink-0" />
                  <span>Informações atualizadas com sucesso!</span>
                </div>
              )}

              <button
                onClick={handleSaveDetails}
                className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-[#FFFFFF] font-bold py-2.5 rounded-xl text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-3xs uppercase tracking-wider"
              >
                <CheckCircle2 className="h-4 w-4 text-white" />
                <span>Salvar Informações</span>
              </button>

              <button
                type="button"
                onClick={() => {
                  setValidationError('');
                  setIsResetSuccess(false);
                  setIsDocVerified(false);
                  setDocNumber('');
                  setNewPassword('');
                  setConfirmPassword('');
                  setCurrentTab('password');
                  speakText("Página de alteração de senha de segurança carregada. Por favor, forneça as informações necessárias.");
                }}
                className="w-full bg-slate-100 hover:bg-slate-200 border border-slate-200 hover:border-slate-350 text-slate-800 font-bold py-2.5 rounded-xl text-xs transition-all flex items-center justify-center gap-2 cursor-pointer shadow-3xs uppercase tracking-wider mt-2.5"
              >
                <Lock className="h-4 w-4 text-[#540D6E]" />
                <span>Alterar Senha de Acesso</span>
              </button>
            </div>

          </div>

          {/* Bento-block 2: Modular Custom System Options */}
          <div className="bg-white border border-slate-200/80 rounded-2xl shadow-3xs p-6 text-left">
            <span className="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-4">
              Acessibilidade e Usabilidade
            </span>

            <div className="space-y-4">
              {/* Narration voice */}
              <div className="flex items-center justify-between p-3.5 bg-slate-50 rounded-xl border border-slate-150">
                <div className="flex items-center gap-3">
                  <div className={`p-2 rounded-lg ${isSpeechEnabled ? 'bg-indigo-550 text-indigo-50 text-white' : 'bg-slate-200 text-slate-500'}`}>
                    {isSpeechEnabled ? <Volume2 className="h-4 w-4" /> : <VolumeX className="h-4 w-4" />}
                  </div>
                  <div>
                    <strong className="text-xs text-slate-800 block">Sintetizador de Voz Ativo</strong>
                    <span className="text-[10px] text-slate-450 block">Audiodescrição em tempo real</span>
                  </div>
                </div>
                <button
                  onClick={() => {
                    const next = !isSpeechEnabled;
                    setIsSpeechEnabled(next);
                    speakText(next ? "Sintetizador de voz ativado." : "");
                  }}
                  className={`w-12 h-6.5 rounded-full p-1 transition-all duration-200 cursor-pointer ${
                    isSpeechEnabled ? 'bg-indigo-650 flex justify-end' : 'bg-slate-300 flex justify-start'
                  }`}
                >
                  <div className="h-4.5 w-4.5 bg-white rounded-full shadow-xs" />
                </button>
              </div>

              {/* Dyslexia font */}
              <div className="flex items-center justify-between p-3.5 bg-slate-50 rounded-xl border border-slate-150">
                <div className="flex items-center gap-3">
                  <div className={`p-2 rounded-lg ${accessibilitySettings.dyslexicFont ? 'bg-teal-600 text-teal-50 text-white' : 'bg-slate-200 text-slate-500'}`}>
                    <Layout className="h-4 w-4" />
                  </div>
                  <div>
                    <strong className="text-xs text-slate-800 block">Fonte de Alta Legibilidade</strong>
                    <span className="text-[10px] text-slate-450 block">Otimizada para dislexia e leitura</span>
                  </div>
                </div>
                <button
                  onClick={() => {
                    const next = !accessibilitySettings.dyslexicFont;
                    updateAccessibilitySettings({ dyslexicFont: next });
                    speakText(next ? "Fonte de legibilidade otimizada ativada." : "Fonte original restabelecida.");
                  }}
                  className={`w-12 h-6.5 rounded-full p-1 transition-all duration-200 cursor-pointer ${
                    accessibilitySettings.dyslexicFont ? 'bg-teal-600 flex justify-end' : 'bg-slate-300 flex justify-start'
                  }`}
                >
                  <div className="h-4.5 w-4.5 bg-white rounded-full shadow-xs" />
                </button>
              </div>

              {/* High Contrast */}
              <div className="flex items-center justify-between p-3.5 bg-slate-50 rounded-xl border border-slate-150">
                <div className="flex items-center gap-3">
                  <div className={`p-2 rounded-lg ${accessibilitySettings.highContrast ? 'bg-slate-900 text-slate-100 text-[#FFD23F]' : 'bg-slate-200 text-slate-500'}`}>
                    <Eye className="h-4 w-4" />
                  </div>
                  <div>
                    <strong className="text-xs text-slate-800 block">Modo Alto Contraste</strong>
                    <span className="text-[10px] text-slate-450 block">Máxima visibilidade de texto</span>
                  </div>
                </div>
                <button
                  onClick={() => {
                    const next = !accessibilitySettings.highContrast;
                    updateAccessibilitySettings({ highContrast: next });
                    speakText(next ? "Alto contraste ativado" : "Alto contraste desativado");
                  }}
                  className={`w-12 h-6.5 rounded-full p-1 transition-all duration-200 cursor-pointer ${
                    accessibilitySettings.highContrast ? 'bg-slate-900 flex justify-end' : 'bg-slate-300 flex justify-start'
                  }`}
                >
                  <div className="h-4.5 w-4.5 bg-white rounded-full shadow-xs" />
                </button>
              </div>

              {/* Online / Offline Presence Status Switch */}
              <div className="flex items-center justify-between p-3.5 bg-slate-50 rounded-xl border border-slate-150">
                <div className="flex items-center gap-3">
                  <div className={`p-2 rounded-lg transition-colors duration-200 flex items-center justify-center ${presenceStatus === 'online' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-200 text-slate-500'}`}>
                    <span className={`block h-3.5 w-3.5 rounded-full ${presenceStatus === 'online' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'}`} />
                  </div>
                  <div>
                    <strong className="text-xs text-slate-800 block">Status de Presença</strong>
                    <span className="text-[10px] text-slate-450 block">
                      Definido como {presenceStatus === 'online' ? 'Online (Ativo)' : 'Offline (Invisível)'}
                    </span>
                  </div>
                </div>
                <button
                  onClick={togglePresenceStatus}
                  className={`w-12 h-6.5 rounded-full p-1 transition-all duration-200 cursor-pointer flex ${
                    presenceStatus === 'online' ? 'bg-emerald-600 justify-end' : 'bg-slate-300 justify-start'
                  }`}
                  title="Alternar presença física síncrona"
                >
                  <div className="h-4.5 w-4.5 bg-white rounded-full shadow-xs" />
                </button>
              </div>

              {/* Text Multiplier Selection */}
              <div className="p-3.5 bg-slate-50 rounded-xl border border-slate-150 space-y-2.5">
                <div className="flex items-center justify-between">
                  <div>
                    <strong className="text-xs text-slate-800 block">Tamanho da Fonte</strong>
                    <span className="text-[10px] text-slate-450 block">Redimensione a escala do portal</span>
                  </div>
                  <span className="text-xs font-mono font-bold bg-white px-2 py-0.5 rounded border border-slate-200">
                    {textSizeMultiplier}x
                  </span>
                </div>
                <div className="grid grid-cols-3 gap-2">
                  {[
                    { value: 1.0, label: 'Padrão' },
                    { value: 1.15, label: 'Médio' },
                    { value: 1.3, label: 'Grande' }
                  ].map((option) => (
                    <button
                      key={option.value}
                      onClick={() => {
                        setTextSizeMultiplier(option.value);
                        speakText(`Fonte redimensionada para ${option.label}`);
                      }}
                      className={`py-1.5 rounded-lg text-xs font-bold border cursor-pointer transition-all ${
                        textSizeMultiplier === option.value
                          ? 'bg-white border-indigo-600 text-indigo-700 shadow-3xs'
                          : 'border-slate-200 text-slate-650 hover:bg-slate-100'
                      }`}
                    >
                      {option.label}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>

        </div>

        {/* RIGHT COLUMN: Real-Time Stats (Academic Summary), Cryptographic cert hashes and Simulated access logs */}
        <div className="lg:col-span-7 space-y-6">
          
          {/* Bento-block 3: User Analytics statistics dynamically generated depending on active login */}
          <div className="bg-white border border-slate-200/80 rounded-2xl shadow-3xs p-6 text-left relative overflow-hidden">
            <span className="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-4">
              Métricas e Rendimento Acadêmico
            </span>

            {activeUser.role === 'student' ? (
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                
                <div className="bg-gradient-to-br from-indigo-50/50 to-indigo-100/30 border border-indigo-100 p-4 rounded-xl flex flex-col justify-between">
                  <div className="flex items-center justify-between text-indigo-650 mb-4">
                    <BookOpen className="h-5 w-5" />
                    <span className="text-[9.5px] font-mono bg-white px-2 py-0.5 rounded border border-indigo-200/60 font-bold">Matriculado</span>
                  </div>
                  <div>
                    <span className="text-2xl font-black text-slate-900 leading-none block">{totalCourses}</span>
                    <span className="text-[10px] text-slate-450 uppercase font-black uppercase tracking-wide block mt-1">Cursos de Catálogo</span>
                  </div>
                </div>

                <div className="bg-gradient-to-br from-emerald-50/50 to-emerald-100/30 border border-emerald-150 p-4 rounded-xl flex flex-col justify-between">
                  <div className="flex items-center justify-between text-emerald-650 mb-4">
                    <Gauge className="h-5 w-5" />
                    <span className="text-[9.5px] font-mono bg-white px-2 py-0.5 rounded border border-emerald-200/65 font-bold">Rendimento</span>
                  </div>
                  <div>
                    <span className="text-2xl font-black text-slate-900 leading-none block">{averageProgressPercent}%</span>
                    <span className="text-[10px] text-slate-450 uppercase font-black uppercase tracking-wide block mt-1">Progresso Médio</span>
                  </div>
                </div>

                <div className="bg-gradient-to-br from-amber-50/50 to-amber-100/30 border border-amber-150 p-4 rounded-xl flex flex-col justify-between">
                  <div className="flex items-center justify-between text-amber-600 mb-4">
                    <Award className="h-5 w-5" />
                    <span className="text-[9.5px] font-mono bg-white px-2 py-0.5 rounded border border-amber-200/65 font-bold">Autêntico</span>
                  </div>
                  <div>
                    <span className="text-2xl font-black text-slate-900 leading-none block">{studentCerts.length}</span>
                    <span className="text-[10px] text-slate-450 uppercase font-black uppercase tracking-wide block mt-1">Certificados Emitidos</span>
                  </div>
                </div>

              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                
                <div className="bg-slate-50 border border-slate-180 p-4 rounded-xl">
                  <div className="text-slate-450 mb-3"><User className="h-5 w-5" /></div>
                  <span className="text-2xl font-black text-slate-850 block">{studentsList.length}</span>
                  <span className="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Alunos Co-Registrados</span>
                </div>

                <div className="bg-slate-50 border border-slate-180 p-4 rounded-xl">
                  <div className="text-slate-450 mb-3"><Sparkles className="h-5 w-5" /></div>
                  <span className="text-2xl font-black text-slate-850 block">{professorsList.length}</span>
                  <span className="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Docentes Habilitados</span>
                </div>

                <div className="bg-slate-50 border border-slate-180 p-4 rounded-xl">
                  <div className="text-slate-450 mb-3"><Settings className="h-5 w-5" /></div>
                  <span className="text-2xl font-black text-slate-850 block">Ativos</span>
                  <span className="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Acesso de Gestão AVA</span>
                </div>

              </div>
            )}

            {/* List Certificates Cryptographic Hashes styled */}
            {activeUser.role === 'student' && studentCerts.length > 0 && (
              <div className="mt-6 space-y-3.5 border-t border-slate-100 pt-5">
                <span className="text-[10px] font-extrabold text-[#540D6E] uppercase tracking-wider block">
                  Chaves de Autenticidade Curricular ({studentCerts.length})
                </span>
                <div className="space-y-2">
                  {studentCerts.map((cert, index) => (
                    <div 
                      key={cert.id}
                      className="bg-slate-50 border border-slate-205 p-3.5 rounded-xl flex justify-between items-center hover:bg-slate-100/50 transition-colors"
                    >
                      <div className="min-w-0 pr-2">
                        <strong className="text-xs text-slate-800 font-bold block truncate leading-snug">{cert.courseTitle}</strong>
                        <span className="text-[9.5px] font-mono text-slate-400 block truncate mt-1">
                          Ref: SEC-{cert.verificationHash}
                        </span>
                      </div>
                      <button
                        onClick={() => copyToClipboard(`SEC-${cert.verificationHash}`, index)}
                        className="bg-white border border-slate-200 text-slate-600 hover:text-[#540D6E] p-2 rounded-xl text-[10px] font-bold shadow-3xs flex items-center gap-1 shrink-0 transition-all cursor-pointer"
                        title="Copiar Hash"
                      >
                        {copiedIndex === index ? (
                          <>
                            <Check className="h-3 w-3 text-emerald-500" />
                            <span className="text-emerald-600 hidden xs:inline">Copiado!</span>
                          </>
                        ) : (
                          <>
                            <Copy className="h-3 w-3" />
                            <span className="hidden xs:inline">Copiar Chave</span>
                          </>
                        )}
                      </button>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Bento-block 5: Advanced Controls area - Toggle test identity & purge options */}
          <div className="bg-amber-50/30 border border-amber-250 p-6 rounded-2xl text-left relative overflow-hidden">
            <div className="absolute top-0 right-0 w-32 h-32 bg-amber-100/15 rounded-full filter blur-xl pointer-events-none" />
            
            <h4 className="font-extrabold text-amber-900 text-xs uppercase tracking-wider mb-2 flex items-center gap-2">
              <Settings className="h-4.5 w-4.5 text-amber-600 animate-spin" style={{ animationDuration: '6s' }} />
              <span>Ferramentas de Engenharia Pedagógica do Simulador</span>
            </h4>
            <p className="text-[11px] text-amber-800 leading-relaxed font-normal mb-5">
              Para validar o comportamento integrado de todas as abas das três identidades, você pode alternar rapidamente o perfil ativo ou redefinir a base de dados zerando alterações salvas em cookies/memória local.
            </p>

            <div className="flex flex-col xs:flex-row gap-3.5">
              <button
                onClick={() => {
                  toggleUserRole();
                  speakText(`Identidade de testes alternada com sucesso.`);
                  onBack();
                }}
                className="bg-amber-100 hover:bg-amber-200 border border-amber-300 text-amber-905 font-bold py-2 px-4 rounded-xl text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer shrink-0 uppercase tracking-wide shadow-3xs"
                title="Alternar Papel"
              >
                <RefreshCw className="h-3.5 w-3.5 text-amber-800" />
                <span>Alternar Papel ({activeUser.role === 'student' ? 'Docente' : 'Discente'})</span>
              </button>

              <button
                onClick={handleResetDatabase}
                className="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold py-2 px-4 rounded-xl text-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer shrink-0 uppercase tracking-wide shadow-3xs ml-auto"
                title="Limpar Cache"
              >
                <Trash2 className="h-3.5 w-3.5 text-rose-500" />
                <span>Zerar Cache do Simulador</span>
              </button>
            </div>
          </div>

        </div>

      </div>

    </div>
  );
}
