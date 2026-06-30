import React, { useState } from 'react';
import { useLMS } from '../context/LMSContext';
import { Course } from '../types';
import { MessageSquare, Send, Heart, Trash2, Users, AlertCircle, HelpCircle, Lightbulb, Smile, Check } from 'lucide-react';

interface CourseForumProps {
  selectedCourse: Course;
}

export const CourseForum: React.FC<CourseForumProps> = ({ selectedCourse }) => {
  const {
    activeUser,
    forumMessages,
    addForumMessage,
    toggleForumMessageLike,
    deleteForumMessage,
    studentEnrollments,
  } = useLMS();

  const [inputText, setInputText] = useState('');
  const [selectedTag, setSelectedTag] = useState<string | null>(null);
  const [activeFilter, setActiveFilter] = useState<'all' | 'questions' | 'insights' | 'instructor'>('all');
  const [successToast, setSuccessToast] = useState(false);

  // Check enrollment
  const enrollment = studentEnrollments[activeUser.name];
  const isEnrolled = enrollment && enrollment.enrolledCourseId === selectedCourse.id;
  const canParticipate = isEnrolled || activeUser.role === 'instructor' || activeUser.role === 'admin';

  // Quick Tags
  const quickTags = [
    { label: '👋 Olá', prefix: '[Apresentação] ' },
    { label: '❓ Dúvida', prefix: '[Dúvida] ' },
    { label: '💡 Insight', prefix: '[Insight] ' },
    { label: '📌 Ideia', prefix: '[Sugerido] ' },
  ];

  // Filter messages for this course
  const messages = forumMessages.filter(msg => msg.courseId === selectedCourse.id);

  // Apply visual discussion lists
  const filteredMessages = messages.filter(msg => {
    if (activeFilter === 'questions') {
      return msg.text.includes('[Dúvida]') || msg.text.includes('?');
    }
    if (activeFilter === 'insights') {
      return msg.text.includes('[Insight]') || msg.likedBy.length >= 3;
    }
    if (activeFilter === 'instructor') {
      return msg.senderRole === 'instructor' || msg.senderRole === 'admin';
    }
    return true;
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!inputText.trim() || !canParticipate) return;

    let textToSend = inputText.trim();
    if (selectedTag) {
      const tagObj = quickTags.find(t => t.label === selectedTag);
      if (tagObj && !textToSend.includes(tagObj.prefix)) {
        textToSend = `${tagObj.prefix}${textToSend}`;
      }
    }

    addForumMessage(selectedCourse.id, textToSend);
    setInputText('');
    setSelectedTag(null);
    setSuccessToast(true);
    setTimeout(() => setSuccessToast(false), 3000);
  };

  const getUserInitials = (name: string) => {
    const parts = name.split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
  };

  const getAvatarBg = (username: string) => {
    const colors = [
      'bg-blue-500',
      'bg-teal-500',
      'bg-indigo-500',
      'bg-violet-500',
      'bg-emerald-500',
      'bg-amber-500',
      'bg-rose-500',
    ];
    let sum = 0;
    for (let i = 0; i < username.length; i++) {
      sum += username.charCodeAt(i);
    }
    return colors[sum % colors.length];
  };

  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden text-left" id="course-forum-section">
      
      {/* Forum Header Banner */}
      <div className="p-5 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex items-start gap-3">
          <div className="rounded-xl bg-teal-100 text-teal-700 p-2.5 shrink-0">
            <MessageSquare className="h-5 w-5" />
          </div>
          <div>
            <h4 className="font-extrabold text-sm text-slate-900 flex items-center gap-2">
              Fórum de Discussão da Comunidade
              <span className="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full font-mono">
                {messages.length} {messages.length === 1 ? 'mensagem' : 'mensagens'}
              </span>
            </h4>
            <p className="text-xs text-slate-500 mt-1">
              Debata lições, compartilhe reflexões e conecte-se com alunos ativos do curso.
            </p>
          </div>
        </div>

        {/* Group indicators */}
        <div className="flex items-center gap-1.5 self-start sm:self-auto text-xs text-slate-600 bg-teal-50/50 border border-teal-100 px-3 py-1.5 rounded-lg">
          <Users className="h-3.5 w-3.5 text-teal-600" />
          <span className="font-medium text-[11px] text-teal-800">Discussão Ativa</span>
        </div>
      </div>

      {/* Quick Filter Tabs */}
      <div className="px-5 py-2.5 bg-slate-50/20 border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">
        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Filtrar Tópicos</span>
        <div className="flex gap-1.5 flex-wrap">
          <button
            onClick={() => setActiveFilter('all')}
            className={`px-3 py-1 rounded-full text-xs font-semibold cursor-pointer transition-colors ${
              activeFilter === 'all'
                ? 'bg-slate-800 text-white'
                : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'
            }`}
          >
            Ver Tudo ({messages.length})
          </button>
          <button
            onClick={() => setActiveFilter('questions')}
            className={`px-3 py-1 rounded-full text-xs font-semibold cursor-pointer transition-colors ${
              activeFilter === 'questions'
                ? 'bg-amber-100 text-amber-800 border border-amber-200'
                : 'bg-white border border-slate-200 text-slate-600 hover:bg-amber-50/50'
            }`}
          >
            ❓ Dúvidas
          </button>
          <button
            onClick={() => setActiveFilter('insights')}
            className={`px-3 py-1 rounded-full text-xs font-semibold cursor-pointer transition-colors ${
              activeFilter === 'insights'
                ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                : 'bg-white border border-slate-200 text-slate-600 hover:bg-emerald-50/50'
            }`}
          >
            💡 Populares
          </button>
          <button
            onClick={() => setActiveFilter('instructor')}
            className={`px-3 py-1 rounded-full text-xs font-semibold cursor-pointer transition-colors ${
              activeFilter === 'instructor'
                ? 'bg-teal-100 text-teal-800 border border-teal-200'
                : 'bg-white border border-slate-200 text-slate-600 hover:bg-teal-50/50'
            }`}
          >
            👨‍🏫 Instrutores
          </button>
        </div>
      </div>

      {/* Discussion List */}
      <div className="divide-y divide-slate-100 max-h-[380px] overflow-y-auto p-5 space-y-4">
        {filteredMessages.length === 0 ? (
          <div className="text-center py-8 text-slate-500 space-y-2">
            <div className="w-10 h-10 rounded-full bg-slate-150/10 flex items-center justify-center mx-auto text-slate-400">
              <MessageSquare className="h-5 w-5" />
            </div>
            <p className="text-xs font-bold text-slate-800">Nenhum diálogo encontrado</p>
            <p className="text-[11px] text-slate-500">Seja o primeiro a inaugurar o debate enviando uma observação abaixo!</p>
          </div>
        ) : (
          filteredMessages.map((msg) => {
            const hasLiked = msg.likedBy.includes(activeUser.name);
            const isOwnMsg = msg.senderName === activeUser.name;
            const canDelete = isOwnMsg || activeUser.role === 'instructor' || activeUser.role === 'admin';

            return (
              <div key={msg.id} className="pt-4 first:pt-0 flex gap-3.5 group animate-in fade-in duration-200 text-xs">
                
                {/* User Avatar Circle */}
                <div className={`w-9 h-9 rounded-full ${getAvatarBg(msg.senderName)} text-white text-xs font-black flex items-center justify-center shrink-0 shadow-xs`}>
                  {getUserInitials(msg.senderName)}
                </div>

                {/* Message Body Column */}
                <div className="flex-1 space-y-1.5 min-w-0">
                  <div className="flex flex-wrap items-center gap-1.5">
                    <span className="font-extrabold text-slate-950 text-xs truncate">{msg.senderName}</span>
                    
                    {/* Badge mapping */}
                    {msg.senderRole === 'instructor' && (
                      <span className="bg-teal-600 text-white text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded">
                        Professor
                      </span>
                    )}
                    {msg.senderRole === 'admin' && (
                      <span className="bg-slate-900 text-white text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded">
                        Admin
                      </span>
                    )}
                    {msg.senderRole === 'student' && (
                      <span className="bg-slate-100 text-slate-600 text-[9px] font-bold uppercase tracking-wider px-1.5 rouded-md">
                        Aluno
                      </span>
                    )}

                    <span className="text-[10px] text-slate-400 font-mono ml-auto shrink-0">{msg.timestamp}</span>
                  </div>

                  {/* Text representation with formatted tags */}
                  <div className="text-slate-700 leading-relaxed break-words whitespace-pre-wrap">
                    {msg.text.startsWith('[Ap') || msg.text.startsWith('[Dú') || msg.text.startsWith('[In') || msg.text.startsWith('[Su') ? (
                      (() => {
                        const braceIndex = msg.text.indexOf('] ');
                        if (braceIndex !== -1) {
                          const tag = msg.text.substring(0, braceIndex + 1);
                          const content = msg.text.substring(braceIndex + 2);
                          let tagStyle = 'bg-slate-100 text-slate-700';
                          if (tag.includes('Dúvida')) tagStyle = 'bg-amber-100 text-amber-900 font-extrabold border border-amber-200';
                          if (tag.includes('Insight')) tagStyle = 'bg-emerald-100 text-emerald-900 font-extrabold border border-emerald-200';
                          if (tag.includes('Sugerido')) tagStyle = 'bg-violet-100 text-violet-900 font-bold border border-violet-200';
                          if (tag.includes('Apresentação')) tagStyle = 'bg-sky-100 text-sky-900 font-bold border border-sky-200';

                          return (
                            <span>
                              <span className={`inline-block mr-1.5 px-2 py-0.5 rounded text-[10px] uppercase tracking-wider ${tagStyle}`}>
                                {tag}
                              </span>
                              {content}
                            </span>
                          );
                        }
                        return msg.text;
                      })()
                    ) : (
                      msg.text
                    )}
                  </div>

                  {/* Actions Area */}
                  <div className="flex items-center gap-3 pt-0.5">
                    <button
                      onClick={() => toggleForumMessageLike(msg.id, activeUser.name)}
                      className={`flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider py-1 px-2 rounded-md transition-all cursor-pointer ${
                        hasLiked 
                          ? 'bg-rose-50 text-rose-600 border border-rose-200/50 scale-[1.01]' 
                          : 'bg-slate-50 text-slate-500 border border-slate-200 hover:text-slate-800'
                      }`}
                    >
                      <Heart className={`h-3 w-3 transition-colors ${hasLiked ? 'fill-rose-600 stroke-rose-600' : 'text-slate-400'}`} />
                      <span>{msg.likes} {msg.likes === 1 ? 'Curtida' : 'Curtidas'}</span>
                    </button>

                    {canDelete && (
                      <button
                        onClick={() => {
                          if (confirm('Tem certeza de que deseja excluir este comentário do fórum?')) {
                            deleteForumMessage(msg.id);
                          }
                        }}
                        className="text-slate-400 hover:text-rose-600 p-1 rounded-md hover:bg-slate-50 ml-auto opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
                        title="Excluir mensagem"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    )}
                  </div>

                </div>
              </div>
            );
          })
        )}
      </div>

      {/* Send Message Section */}
      <div className="p-5 border-t border-slate-100 bg-slate-50/50">
        {!canParticipate ? (
          <div className="p-3 bg-rose-50 border border-rose-150 rounded-xl flex items-center gap-2.5 text-rose-900 text-xs font-semibold">
            <AlertCircle className="h-4 w-4 text-rose-600 shrink-0" />
            <span>
              Restrição de Participação: Apenas alunos devidamente <strong>matriculados e ativos</strong> neste curso podem enviar dúvidas ou ponderações no painel do fórum.
            </span>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-3.5">
            
            {/* Quick Tag Options */}
            <div className="flex items-center gap-2 flex-wrap text-xs">
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest shrink-0">Etiquetar dúvida:</span>
              {quickTags.map(tag => (
                <button
                  type="button"
                  key={tag.label}
                  onClick={() => setSelectedTag(selectedTag === tag.label ? null : tag.label)}
                  className={`px-2.5 py-1 rounded-lg text-xs font-bold cursor-pointer transition-colors flex items-center gap-1 ${
                    selectedTag === tag.label
                      ? 'bg-teal-600 text-white shadow-xs'
                      : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'
                  }`}
                >
                  <span>{tag.label}</span>
                  {selectedTag === tag.label && <Check className="h-3 w-3" />}
                </button>
              ))}
            </div>

            {/* Input writing panel */}
            <div className="flex items-center gap-2 relative">
              <input
                type="text"
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
                placeholder={
                  selectedTag
                    ? `Publicar no fórum de ${selectedCourse.title} com etiqueta "${selectedTag}"...`
                    : `Escreva uma mensagem no fórum deste curso para debater com outros alunos...`
                }
                className="w-full bg-white border border-slate-200 text-xs rounded-xl pl-4 pr-12 py-3 focus:border-teal-400 focus:ring-1 focus:ring-teal-400/50 focus:outline-hidden"
              />
              
              <button
                type="submit"
                disabled={!inputText.trim()}
                className={`absolute right-1.5 p-2 rounded-lg text-white transition-colors cursor-pointer ${
                  inputText.trim() 
                    ? 'bg-teal-600 hover:bg-teal-500 hover:scale-105 active:scale-95' 
                    : 'bg-slate-200 text-slate-400 cursor-not-allowed'
                }`}
              >
                <Send className="h-4 w-4" />
              </button>
            </div>

            {/* Success feedback toast */}
            {successToast && (
              <p className="text-[10px] font-bold text-emerald-600 text-center animate-pulse flex items-center justify-center gap-1">
                <span>✓ Mensagem adicionada com sucesso no fórum exclusivo!</span>
              </p>
            )}
            
          </form>
        )}
      </div>

    </div>
  );
};
