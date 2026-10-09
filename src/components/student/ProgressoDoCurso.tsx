import React from 'react';

interface ProgressoDoCursoProps {
  /** Quantas aulas o curso tem. */
  total: number;
  /** Quantas o aluno já concluiu. */
  concluidas: number;
  className?: string;
}

/**
 * "Seu progresso no curso" — barra, porcentagem e texto, como na tela 15.
 *
 * Existe como componente, e não solto na tela de aula, porque aparece em DOIS
 * lugares conforme a largura: na barra lateral a partir de `lg`, e no corpo da
 * aula abaixo disso. Duplicar a marcação faria as duas versões divergirem com o
 * tempo — e a que ninguém olha é sempre a que fica errada.
 *
 * O número e o texto andam junto com a barra de propósito: progresso que só
 * existe como cor não é legível por quem não distingue a cor (D §2.13).
 */
export const ProgressoDoCurso: React.FC<ProgressoDoCursoProps> = ({
  total,
  concluidas,
  className = '',
}) => {
  const pct = total > 0 ? Math.round((concluidas / total) * 100) : 0;

  return (
    <div className={`min-w-0 ${className}`}>
      <div className="flex items-center justify-between gap-2">
        <span className="text-sobretitulo uppercase text-escult-ink-2">Seu progresso no curso</span>
        <strong className="text-rotulo font-black text-ava-acao">{pct}%</strong>
      </div>
      <div className="mt-1 h-1.5 w-full rounded-full bg-white border border-ava-borda overflow-hidden">
        <div className="h-full bg-ava-acao" style={{ width: `${pct}%` }} />
      </div>
      <span className="mt-1 block text-apoio text-escult-ink-2">
        {concluidas} de {total} aulas concluídas
      </span>
    </div>
  );
};
