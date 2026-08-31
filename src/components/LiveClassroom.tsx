/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect, useRef } from 'react';
import {
  Video, VideoOff, Mic, MicOff, MessageSquare, Send, Users,
  CheckCircle, Radio, ExternalLink, Calendar, Plus, Hand, Monitor,
  Info, Sparkles, Smile, Maximize2, Minimize2, AlertTriangle
} from 'lucide-react';
import { useLMS } from '../context/LMSContext';
import { Course, LiveSession } from '../types';
import { safeHref } from '../utils/safeUrl';

interface LiveClassroomProps {
  course: Course;
  session: LiveSession;
  onClose: () => void;
}

export const LiveClassroom: React.FC<LiveClassroomProps> = ({ course, session, onClose }) => {
  const { chatMessages, sendLiveChatMessage, attendLiveSession, progress, activeUser, courses, setLiveSessionStatus } = useLMS();

  const currentCourse = courses.find((c) => c.id === course.id);
  const reactiveSession = currentCourse?.liveSessions.find((s) => s.id === session.id) || session;
  const isSessionLive = reactiveSession.isLive;

  const [inputText, setInputText] = useState('');
  const [cameraOn, setCameraOn] = useState(true);
  const [micOn, setMicOn] = useState(true);
  const [isScreenSharing, setIsScreenSharing] = useState(false);
  const [handRaised, setHandRaised] = useState(false);
  const [showToast, setShowToast] = useState(false);
  const [activeTab, setActiveTab] = useState<'chat' | 'info'>(course.hasChat === false ? 'info' : 'chat');
  const chatEndRef = useRef<HTMLDivElement>(null);
  const [isFocusMode, setIsFocusMode] = useState(false);

  const toggleFocusMode = () => {
    const next = !isFocusMode;
    setIsFocusMode(next);
    if (next) {
      setActiveTab('chat');
    }
    if ('speechSynthesis' in window && 'SpeechSynthesisUtterance' in window) {
      try {
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(next ? "Modo foco ativado" : "Modo foco desativado");
        window.speechSynthesis.speak(utterance);
      } catch (err) {
        console.warn("Speech synthesis error:", err);
      }
    }
  };

  // Check if student has already attended / marked presence
  const currentProgress = progress.find((p) => p.courseId === course.id);
  const isPresent = currentProgress?.attendedLiveSessions.includes(session.id) || false;

  // Filter messages for this specific session
  const messages = chatMessages.filter((msg) => msg.sessionId === session.id);

  // Scroll to bottom of chat when messages change
  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  // Generate some realistic bot responses half a second after student enters or when they send specific things
  useEffect(() => {
    // If there are very few messages, add a welcome bot message
    if (messages.length === 0) {
      setTimeout(() => {
        sendLiveChatMessage(session.id, `Olá ${activeUser.name}, seja bem-vindo ao nosso espaço de aula ao vivo!`);
      }, 1000);
    }
  }, [session.id]);

  const handleSendMessage = (e: React.FormEvent) => {
    e.preventDefault();
    if (!inputText.trim()) return;
    sendLiveChatMessage(session.id, inputText.trim());
    
    // Simulate professor / other student answers in a fun interactive way!
    const userMsg = inputText.toLowerCase();
    setInputText('');

    setTimeout(() => {
      if (userMsg.includes('dúvida') || userMsg.includes('ajuda') || userMsg.includes('como')) {
        sendLiveChatMessage(session.id, `Excelente pergunta! Vou responder ao vivo em instantes na aula.`);
      } else if (userMsg.includes('presença') || userMsg.includes('certificado')) {
        sendLiveChatMessage(session.id, `A presença é registrada clicando no botão verde "Confirmar Presença" no painel esquerdo da aula!`);
      } else {
        const responses = [
          "Sensacional essa explicação!",
          "Também concordo perfeitamente com esse ponto.",
          "Estou anotando tudo!",
          "Que conteúdo incrível",
        ];
        const randomResp = responses[Math.floor(Math.random() * responses.length)];
        // Choose a random student name
        const names = ["Beatriz Costa", "Lucas Mendes", "Tiago Souza", "Sofia Rocha"];
        const randomName = names[Math.floor(Math.random() * names.length)];
        
        // Temporarily change name to simulate a peer sending messages
        const originalUser = activeUser.name;
        const originalRole = activeUser.role;
        
        // We can inject directly but we'll use a standard helper of sending back text
        // In the context we can just let it appear
      }
    }, 2000);
  };

  const handleConfirmAttendance = () => {
    attendLiveSession(course.id, session.id);
    setShowToast(true);
    setTimeout(() => setShowToast(false), 4400);
  };

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-slate-950 text-slate-100 font-sans">
      
      {/* Top Header of classroom */}
      {!isFocusMode && (
        <header className="flex h-16 items-center justify-between border-b border-slate-800 bg-slate-900 px-4 md:px-6">
          <div className="flex items-center gap-3">
            {isSessionLive ? (
              <div className="flex items-center gap-1.5 rounded-full bg-red-500/10 px-2.5 py-1 text-xs font-semibold text-red-400 border border-red-500/20 animate-pulse">
                <Radio className="h-3 w-3" />
                <span>AO VIVO</span>
              </div>
            ) : (
              <div className="flex items-center gap-1.5 rounded-full bg-slate-800 px-2.5 py-1 text-xs font-semibold text-slate-400 border border-slate-700">
                <VideoOff className="h-3 w-3 text-slate-400" />
                <span>OFFLINE</span>
              </div>
            )}
            <div className="text-left">
              <h1 className="text-sm font-bold text-slate-100 max-w-xs md:max-w-xl truncate leading-tight">
                {session.title}
              </h1>
              <p className="text-[11px] text-slate-400 truncate">
                Curso: {course.title} • {course.instructorName}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2 md:gap-4">
            {/* Start / Stop Transmission controls for instructor or admin */}
            {(activeUser.role === 'instructor' || activeUser.role === 'admin') && (
              <button
                onClick={() => setLiveSessionStatus(course.id, session.id, !isSessionLive)}
                className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-xs ${
                  isSessionLive
                    ? 'bg-amber-600 hover:bg-amber-500 text-white'
                    : 'bg-emerald-600 hover:bg-emerald-500 text-white'
                }`}
              >
                <Radio className="h-3.5 w-3.5" />
                <span>{isSessionLive ? 'Encerrar Transmissão' : 'Iniciar Transmissão'}</span>
              </button>
            )}

            <div className="hidden items-center gap-1.5 text-xs text-slate-400 sm:flex">
              <Users className="h-4 w-4 text-slate-400" />
              <span>{isSessionLive ? '42 alunos assistindo' : 'Aguardando transmissão'}</span>
            </div>
            
            <button
              onClick={toggleFocusMode}
              className="rounded-lg border border-indigo-750 bg-indigo-950/40 hover:bg-indigo-950/80 text-indigo-300 hover:text-indigo-200 text-indigo-400 px-4 py-2 text-xs font-bold font-mono tracking-wider transition-all flex items-center gap-1.5 cursor-pointer uppercase shadow-3xs"
              title="Ativar o Modo Foco (Ocultar menus, manter apenas Player + Chat)"
            >
              <Maximize2 className="h-3.5 w-3.5" />
              <span>Modo Foco</span>
            </button>

            <button
              onClick={onClose}
              className="rounded-lg bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-700 transition-colors"
            >
              Sair da Aula
            </button>
          </div>
        </header>
      )}

      {/* Main Grid split */}
      <div className="flex flex-1 flex-col overflow-hidden lg:flex-row">
        
        {/* Left Column: Interactive Video Player with Controls */}
        <div className={`flex flex-1 flex-col bg-slate-950 relative transition-all duration-300 ${
          isFocusMode ? 'p-0 overflow-hidden' : 'p-4 justify-between overflow-y-auto'
        }`}>
          
          {/* Main stream simulation */}
          <div className={isFocusMode ? 'w-full h-full flex flex-col justify-center' : 'flex-1 flex flex-col justify-center items-center min-h-[300px]'}>
            <div className={`relative transition-all duration-300 bg-slate-900 shadow-xl flex flex-col items-center justify-center overflow-hidden ${
              isFocusMode 
                ? 'w-full h-full border-0 rounded-none' 
                : 'w-full aspect-video max-w-4xl rounded-2xl border border-slate-800'
            }`}>
              
              {/* Floating Header on top of the player when in Focus Mode */}
              {isFocusMode && (
                <div className="absolute top-4 left-4 right-4 z-30 flex items-center justify-between bg-slate-950/80 backdrop-blur-md px-4 py-2.5 rounded-xl border border-slate-800/80 hover:opacity-100 opacity-95 transition-opacity duration-200 shadow-xl">
                  <div className="flex items-center gap-2">
                    <span className="flex h-2 w-2 rounded-full bg-red-500 animate-pulse" />
                    <span className="text-[10px] font-extrabold uppercase font-mono tracking-wider text-red-400">Modo Foco Ativo</span>
                    <span className="text-slate-500 font-mono text-[11px] hidden md:inline">|</span>
                    <h2 className="text-xs font-bold text-white leading-none max-w-xs md:max-w-md truncate">{session.title}</h2>
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={toggleFocusMode}
                      className="rounded-lg bg-indigo-650 hover:bg-indigo-600 border border-indigo-500/25 text-white px-3 py-1.5 text-[10.5px] font-bold tracking-tight transition-all flex items-center gap-1.5 cursor-pointer"
                      title="Sair do Modo Foco"
                    >
                      <Minimize2 className="h-3 w-3" />
                      <span>Voltar p/ Tela Normal</span>
                    </button>
                    <button
                      onClick={onClose}
                      className="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-3 py-1.5 text-[10.5px] font-bold transition-all cursor-pointer"
                    >
                      Sair da Aula
                    </button>
                  </div>
                </div>
              )}
              
              {/* Virtual Presentation background */}
              {!isSessionLive && activeUser.role === 'student' ? (
                <div className="absolute inset-0 bg-slate-900 flex flex-col items-center justify-center p-6 text-center">
                  <div className="p-4 bg-slate-850 rounded-full border border-slate-800 text-slate-500 mb-4 animate-pulse">
                    <VideoOff className="h-10 w-10 text-slate-400" />
                  </div>
                  <h3 className="text-sm font-bold text-slate-200">Aguardando o início da transmissão</h3>
                  <p className="text-xs text-slate-400 max-w-sm mt-1.5 leading-relaxed">
                    O professor ainda não iniciou a transmissão ao vivo desta aula virtual. Você pode utilizar o chat ao lado para interagir com seus colegas e professores enquanto aguarda.
                  </p>
                </div>
              ) : isScreenSharing ? (
                <div className="absolute inset-0 bg-slate-900 flex flex-col p-6 text-left border border-teal-500/40">
                  <div className="flex items-center justify-between border-b border-teal-950 pb-3 mb-4">
                    <span className="text-xs font-semibold text-teal-400 flex items-center gap-2">
                      <Monitor className="h-4 w-4" />
                      Compartilhando Tela — {course.instructorName}
                    </span>
                    <span className="text-[10px] bg-slate-800 px-2 py-0.5 rounded text-slate-300">Apresentação_Final.pdf</span>
                  </div>
                  <div className="flex-1 rounded-lg bg-slate-950/80 p-6 flex flex-col justify-center border border-slate-800">
                    <span className="text-xs uppercase font-semibold text-teal-400 mb-2">Estrutura Estratégica do AVA</span>
                    <h3 className="text-xl md:text-2xl font-bold mb-4 text-white">REQUISITOS METRICOS DE SUCESSO</h3>
                    <div className="space-y-2 text-sm text-slate-300">
                      <div className="flex items-center gap-2">
                        <CheckCircle className="h-4 w-4 text-emerald-400" />
                        <span>Presença Mínima Obrigatória: <strong>70%</strong></span>
                      </div>
                      <div className="flex items-center gap-2">
                        <CheckCircle className="h-4 w-4 text-emerald-400" />
                        <span>Emissão Inteligente de Certificado: <strong>Sincronizado</strong></span>
                      </div>
                      <div className="flex items-center gap-2">
                        <CheckCircle className="h-4 w-4 text-emerald-400" />
                        <span>Módulos de Fixação e Diários: <strong>Ativo</strong></span>
                      </div>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="absolute inset-0 bg-linear-to-b from-teal-950/40 to-slate-950/80 flex flex-col justify-center items-center p-4">
                  
                  {/* Speaker webcam frame simulation */}
                  <div className="relative h-60 w-60 rounded-full border-4 border-teal-500/20 overflow-hidden flex items-center justify-center bg-slate-800 shadow-2xl">
                    <div className="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&auto=format&fit=crop&q=60')] bg-cover bg-center opacity-85" />
                    {!cameraOn && (
                      <div className="absolute inset-0 bg-slate-800 flex items-center justify-center">
                        <VideoOff className="h-10 w-10 text-slate-500" />
                      </div>
                    )}
                    {/* Pulsing indicator */}
                    {isSessionLive && (
                      <div className="absolute top-4 right-4 h-3 w-3 rounded-full bg-emerald-500 animate-pulse border-2 border-slate-900" />
                    )}
                  </div>
                  
                  <div className="mt-4 text-center">
                    <h4 className="font-semibold text-slate-100">{course.instructorName}</h4>
                    <span className="text-xs text-teal-300">
                      {isSessionLive ? 'Instrutor Responsável • Transmitindo ao Vivo' : 'Prévia da Câmera (Você está Offline)'}
                    </span>
                  </div>

                  {!isSessionLive && (activeUser.role === 'instructor' || activeUser.role === 'admin') && (
                    <div className="absolute bottom-4 left-4 right-4 bg-amber-500/90 text-slate-950 font-bold text-[11px] px-4 py-2.5 rounded-lg flex items-center justify-between gap-3 shadow-lg">
                      <AlertTriangle className="h-4 w-4 shrink-0" />
                      <span>Você está offline. Clique em "Iniciar Transmissão" no cabeçalho acima para iniciar a aula letiva.</span>
                    </div>
                  )}
                </div>
              )}

              {/* Attendance quick reminder banner inside feed if not marked */}
              {!isPresent && activeUser.role === 'student' && (
                <div className="absolute top-4 left-4 right-4 bg-teal-600/90 text-white text-xs px-4 py-3 rounded-lg flex items-center justify-between gap-4 backdrop-blur-md shadow-lg border border-teal-400/30 animate-bounce">
                  <div className="flex items-center gap-2">
                    <Sparkles className="h-4 w-4 text-amber-300" />
                    <span>Marque sua presença para garantir seu certificado! Necessário <strong>70% de participação</strong>.</span>
                  </div>
                  <button
                    onClick={handleConfirmAttendance}
                    className="shrink-0 bg-white text-teal-700 hover:bg-slate-100 px-3 py-1.5 rounded-md font-semibold text-xs shadow-xs transition-all"
                  >
                    Confirmar Presença
                  </button>
                </div>
              )}

              {/* Audio visualizer bar in low part */}
              {micOn && (
                <div className="absolute bottom-4 left-4 flex items-end gap-1 h-6">
                  <div className="w-1 bg-teal-500 rounded animate-[bounce_1s_infinite]" style={{ height: '40%' }}></div>
                  <div className="w-1 bg-teal-500 rounded animate-[bounce_1.4s_infinite]" style={{ height: '70%' }}></div>
                  <div className="w-1 bg-teal-500 rounded animate-[bounce_0.8s_infinite]" style={{ height: '30%' }}></div>
                  <div className="w-1 bg-teal-500 rounded animate-[bounce_1.2s_infinite]" style={{ height: '90%' }}></div>
                  <span className="text-[10px] text-slate-400 ml-1.5">Áudio ao vivo</span>
                </div>
              )}

              {/* Hand raised overlay */}
              {handRaised && (
                <div className="absolute bottom-4 right-4 flex items-center gap-1.5 bg-amber-500 text-slate-950 font-bold px-3 py-1 rounded text-xs animate-pulse">
                  <Hand className="h-3.5 w-3.5" />
                  <span>Mão Levantada</span>
                </div>
              )}
            </div>
          </div>

          {/* Interactive media bar */}
          <div className={`transition-all duration-300 ${
            isFocusMode 
              ? 'absolute bottom-4 left-4 right-4 z-20 bg-slate-950/85 backdrop-blur-md p-3 rounded-xl border border-slate-800/80 hover:opacity-100 opacity-30 shadow-2xl flex flex-col md:flex-row items-center justify-between gap-4' 
              : 'bg-slate-900/60 p-4 rounded-xl border border-slate-800 flex flex-col md:flex-row items-center justify-between gap-4 mt-4'
          }`}>
            
            {/* Control buttons */}
            <div className="flex items-center gap-2">
              <button
                onClick={() => setMicOn(!micOn)}
                title={micOn ? "Silenciar" : "Ativar microfone"}
                className={`p-3 rounded-full transition-colors ${
                  micOn ? 'bg-slate-800 hover:bg-slate-700 text-slate-200' : 'bg-red-500/20 text-red-400 hover:bg-red-500/30'
                }`}
              >
                {micOn ? <Mic className="h-5 w-5" /> : <MicOff className="h-5 w-5" />}
              </button>

              <button
                onClick={() => setCameraOn(!cameraOn)}
                title={cameraOn ? "Desativar câmera" : "Ativar câmera"}
                className={`p-3 rounded-full transition-colors ${
                  cameraOn ? 'bg-slate-800 hover:bg-slate-700 text-slate-200' : 'bg-red-500/20 text-red-400 hover:bg-red-500/30'
                }`}
              >
                {cameraOn ? <Video className="h-5 w-5" /> : <VideoOff className="h-5 w-5" />}
              </button>

              <button
                onClick={() => setIsScreenSharing(!isScreenSharing)}
                title="Compartilhar Tela"
                className={`p-3 rounded-full transition-colors ${
                  isScreenSharing ? 'bg-teal-500 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'
                }`}
              >
                <Monitor className="h-5 w-5" />
              </button>

              <button
                onClick={() => setHandRaised(!handRaised)}
                title="Levantar a Mão"
                className={`p-3 rounded-full transition-colors ${
                  handRaised ? 'bg-amber-500 text-slate-950' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'
                }`}
              >
                <Hand className="h-5 w-5" />
              </button>
            </div>

            {/* Attendance Status Action block */}
            <div className="flex items-center gap-3">
              <span className="text-[11px] text-slate-400">Presença do Aluno:</span>
              {isPresent ? (
                <div className="flex items-center gap-1.5 bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 px-4 py-2 rounded-lg text-xs font-semibold">
                  <CheckCircle className="h-4 w-4" />
                  <span>Presença Confirmada neste evento!</span>
                </div>
              ) : activeUser.role === 'student' ? (
                <button
                  onClick={handleConfirmAttendance}
                  className="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-2 rounded-lg text-xs flex items-center gap-2 transition-all hover:scale-102 cursor-pointer shadow-md"
                >
                  <CheckCircle className="h-4 w-4" />
                  <span>Confirmar Minha Presença</span>
                </button>
              ) : (
                <div className="bg-slate-800 px-3 py-2 rounded text-xs font-mono text-teal-300">
                  Modo Instrutor • Presença desativada
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Right Column: Chat panel & integrations */}
        <div className="w-full lg:w-96 border-t lg:border-t-0 lg:border-l border-slate-800 bg-slate-900 flex flex-col h-[400px] lg:h-auto animate-in fade-in duration-200">
          
          {/* Sub Navigation */}
          {isFocusMode ? (
            <div className="flex h-14 items-center justify-between border-b border-slate-800 bg-slate-950 px-4 shrink-0 shadow-2xs">
              <span className="text-[10px] font-extrabold tracking-wider text-teal-400 uppercase flex items-center gap-1.5 font-mono">
                <span className="h-2 w-2 rounded-full bg-teal-500 animate-pulse" />
                <span>Bate-papo da Aula ao Vivo</span>
              </span>
              <span className="text-[9px] font-mono font-bold text-slate-400 bg-slate-900 border border-slate-800 px-2 py-0.5 rounded uppercase">
                {messages.length} msgs
              </span>
            </div>
          ) : (
            <div className="flex border-b border-slate-800">
              {course.hasChat !== false && (
                <button
                  onClick={() => setActiveTab('chat')}
                  className={`flex-1 py-3 text-xs font-bold transition-all border-b-2 ${
                    activeTab === 'chat' 
                      ? 'border-teal-500 text-teal-400 bg-slate-950/20' 
                      : 'border-transparent text-slate-400 hover:text-slate-200'
                  }`}
                >
                  <div className="flex items-center justify-center gap-1.5">
                    <MessageSquare className="h-4 w-4" />
                    <span>Bate-Papo ({messages.length})</span>
                  </div>
                </button>
              )}
              <button
                onClick={() => setActiveTab('info')}
                className={`flex-1 py-3 text-xs font-bold transition-all border-b-2 ${
                  activeTab === 'info' 
                    ? 'border-teal-500 text-teal-400 bg-slate-950/20' 
                    : 'border-transparent text-slate-400 hover:text-slate-200'
                }`}
              >
                <div className="flex items-center justify-center gap-1.5">
                  <Info className="h-4 w-4" />
                  <span>Integração Externa</span>
                </div>
              </button>
            </div>
          )}

          {activeTab === 'chat' && course.hasChat !== false ? (
            <>
              {/* Chat messages canvas container */}
              <div className="flex-1 overflow-y-auto p-4 space-y-3 scrollbar-thin scrollbar-thumb-slate-800">
                {messages.length === 0 ? (
                  <div className="text-center text-xs text-slate-500 py-10">
                    Nenhuma mensagem enviada. Seja o primeiro a iniciar!
                  </div>
                ) : (
                  messages.map((msg) => {
                    const isInstructor = msg.senderRole === 'instructor';
                    return (
                      <div key={msg.id} className="flex flex-col text-left">
                        <div className="flex items-baseline gap-1.5">
                          <span className={`text-[11px] font-bold ${isInstructor ? 'text-amber-400' : 'text-teal-400'}`}>
                            {msg.senderName}
                          </span>
                          {isInstructor && (
                            <span className="bg-amber-500/10 border border-amber-500/20 text-amber-400 text-[8px] uppercase tracking-wider px-1 py-0.2 rounded font-semibold">
                              Instrutor
                            </span>
                          )}
                          <span className="text-[9px] text-slate-500">
                            {new Date(msg.timestamp).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                          </span>
                        </div>
                        <p className="mt-0.5 rounded-lg bg-slate-950/40 p-2 text-xs border border-slate-800/60 text-slate-200">
                          {msg.text}
                        </p>
                      </div>
                    );
                  })
                )}
                <div ref={chatEndRef} />
              </div>

              {/* Chat action Form footer */}
              <form onSubmit={handleSendMessage} className="p-3 border-t border-slate-800 bg-slate-950/80 flex items-center gap-2">
                <input
                  type="text"
                  value={inputText}
                  onChange={(e) => setInputText(e.target.value)}
                  placeholder="Envie uma mensagem..."
                  className="flex-1 rounded-lg bg-slate-800 px-3 py-2 text-xs placeholder-slate-500 focus:outline-hidden focus:ring-1 focus:ring-teal-500 border border-slate-700/60"
                />
                <button
                  type="submit"
                  className="rounded-lg bg-teal-600 p-2 hover:bg-teal-500 transition-colors cursor-pointer"
                >
                  <Send className="h-4 w-4" />
                </button>
              </form>
            </>
          ) : (
            /* External Integrations Info Panel */
            <div className="flex-1 p-5 space-y-4 text-left text-xs leading-relaxed overflow-y-auto">
              <div className="p-3.5 rounded-lg bg-teal-950/20 border border-teal-900 text-teal-300">
                <div className="flex items-center gap-2 font-bold mb-1">
                  <ExternalLink className="h-4 w-4" />
                  <span>Ambiente de Transmissão Real</span>
                </div>
                O AVA integra-se nativamente com ferramentas como Google Meet, Zoom, MS Teams ou Webex. Abaixo está o canal oficial reservado para este curso.
              </div>

              <div className="space-y-2 border border-slate-800 rounded-lg p-3 bg-slate-950/20">
                <span className="text-slate-400 uppercase tracking-widest text-[10px] font-bold">Link de Videoconferência</span>
                <p className="font-mono text-[11px] text-emerald-400 truncate select-all">{session.meetingLink}</p>
                <a
                  href={safeHref(session.meetingLink)}
                  target="_blank"
                  referrerPolicy="no-referrer"
                  className="mt-2 inline-flex items-center gap-1.5 bg-teal-600 hover:bg-teal-500 text-white font-semibold px-3 py-1.5 rounded text-[11px] transition-all"
                >
                  <span>Abrir em Nova Aba</span>
                  <ExternalLink className="h-3 w-3" />
                </a>
              </div>

              <div className="space-y-2 text-slate-400 text-[11px]">
                <h5 className="font-bold text-slate-300 uppercase shrink text-[10px]">Instruções para o Aluno</h5>
                <ul className="list-disc leading-loose pl-4 space-y-1">
                  <li>Seu progresso da aula ao vivo exige que você permaneça ativo na transmissão por alguns momentos.</li>
                  <li>Incentivamos que abra a conferência externa para participar por áudio/vídeo.</li>
                  <li>Clique no botão verde de presença nesta página para que o AVA registre seu comparecimento no banco de dados.</li>
                </ul>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Success Toast banner upon Confirming Presence */}
      {showToast && (
        <div className="absolute top-20 right-6 z-50 rounded-lg bg-emerald-600 border border-emerald-400 text-white px-5 py-3 shadow-2xl flex items-center gap-3 animate-in slide-in-from-top-4 duration-300">
          <div className="rounded-full bg-emerald-700/50 p-1">
            <CheckCircle className="h-5 w-5" />
          </div>
          <div>
            <h5 className="text-xs font-bold text-white">Presença Registrada!</h5>
            <p className="text-[10px] text-emerald-100">Atualizamos suas estatísticas do curso com sucesso.</p>
          </div>
        </div>
      )}
    </div>
  );
};
