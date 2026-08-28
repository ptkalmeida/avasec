/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState } from 'react';
import { List, X } from 'lucide-react';
import { LessonSection } from '../../utils/lessonContent';

interface LessonIndexProps {
  sections: LessonSection[];
  /** Rolagem suave até o título, respeitando o cabeçalho fixo (scroll-mt). */
  onNavigate?: (id: string) => void;
}

/**
 * Índice das seções da aula. Some quando o conteúdo não tem títulos suficientes
 * para justificar navegação — índice de um item só é ruído.
 */
export const LessonIndex: React.FC<LessonIndexProps> = ({ sections, onNavigate }) => {
  const [isOpen, setIsOpen] = useState(false);

  if (sections.length < 2) return null;

  const jump = (id: string) => {
    setIsOpen(false);
    if (onNavigate) {
      onNavigate(id);
      return;
    }
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setIsOpen((v) => !v)}
        aria-expanded={isOpen}
        className="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-3 py-2 text-[11px] font-black uppercase tracking-wider text-slate-600 transition-colors cursor-pointer shadow-3xs"
      >
        {isOpen ? <X className="h-3.5 w-3.5" /> : <List className="h-3.5 w-3.5" />}
        <span>Nesta aula</span>
        <span className="text-slate-400 font-mono normal-case">({sections.length})</span>
      </button>

      {isOpen && (
        <nav
          aria-label="Seções desta aula"
          className="absolute right-0 z-30 mt-2 w-72 max-h-[60vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-lg animate-in fade-in duration-150"
        >
          <ul className="space-y-0.5">
            {sections.map((section) => (
              <li key={section.id}>
                <button
                  type="button"
                  onClick={() => jump(section.id)}
                  className={`w-full text-left rounded-lg px-3 py-2 text-xs transition-colors cursor-pointer hover:bg-slate-50 ${
                    section.level === 2
                      ? 'font-bold text-slate-800'
                      : 'font-medium text-slate-500 pl-6'
                  }`}
                >
                  {section.level === 2 && section.index !== undefined && (
                    <span className="text-[#540D6E] font-mono mr-1.5">{section.index}.</span>
                  )}
                  {section.text}
                </button>
              </li>
            ))}
          </ul>
        </nav>
      )}
    </div>
  );
};
