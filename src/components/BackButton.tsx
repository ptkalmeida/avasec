import React from 'react';
import { ArrowLeft } from 'lucide-react';

interface BackButtonProps {
  onClick: () => void;
  text: string;
  className?: string;
}

export const BackButton: React.FC<BackButtonProps> = ({ onClick, text, className = '' }) => {
  return (
    <button
      onClick={onClick}
      className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 transition-all cursor-pointer text-xs font-black uppercase tracking-wider border border-slate-200/65 group ${className}`}
    >
      <ArrowLeft className="h-3.5 w-3.5 group-hover:-translate-x-0.5 transition-transform" />
      <span>{text}</span>
    </button>
  );
};
