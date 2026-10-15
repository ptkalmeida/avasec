import React from 'react';
import { Archive, Download, ExternalLink, FileText } from 'lucide-react';
import type { LessonDocument } from '../../types';
import { safeHref } from '../../utils/safeUrl';

interface MateriaisDeApoioProps {
  documentos: LessonDocument[];
  /**
   * `lateral` é a barra da tela 15, a partir de `lg`: cartão próprio, um
   * documento por linha. `corpo` é o rodapé do conteúdo da aula, usado abaixo
   * de `lg`, onde não há barra lateral.
   */
  variante: 'lateral' | 'corpo';
  className?: string;
}

/**
 * O anexo é arquivo servido por nós, ou link para fora?
 *
 * A distinção decide o ícone, e o ícone é uma promessa: seta para baixo diz
 * "isto baixa". Prometer download num link do Drive ou num endereço externo
 * seria mentir — o navegador vai abrir outra aba, não gravar arquivo. Só o que
 * sai de `/uploads/` é servido pelo próprio AVASEC e baixa de verdade.
 */
function ehArquivoNosso(url: string): boolean {
  return url.startsWith('/uploads/');
}

/** Cor de fundo e do rótulo conforme o tipo do arquivo. */
function estiloPorTipo(tipo: string): { fundo: string; rotulo: string } {
  if (tipo === 'pdf') {
    return {
      fundo: 'bg-rose-50/50 border-rose-100 hover:bg-rose-50 hover:border-rose-250',
      rotulo: 'bg-rose-100 text-rose-800',
    };
  }
  if (tipo === 'drive') {
    return {
      fundo: 'bg-emerald-50/50 border-emerald-100 hover:bg-emerald-50 hover:border-emerald-250',
      rotulo: 'bg-emerald-100 text-emerald-800',
    };
  }
  if (tipo === 'url') {
    return {
      fundo: 'bg-amber-50/50 border-amber-100 hover:bg-amber-50 hover:border-amber-250',
      rotulo: 'bg-amber-100 text-amber-800',
    };
  }
  return {
    fundo: 'bg-slate-50 border-slate-200 hover:bg-slate-100 hover:border-slate-350',
    rotulo: 'bg-slate-100 text-slate-700',
  };
}

/**
 * "Material de apoio" da aula — os anexos que o instrutor disponibilizou.
 *
 * Existe como componente, e não solto na aula, porque aparece em DOIS lugares
 * conforme a largura: na barra lateral a partir de `lg` (tela 15) e no fim do
 * conteúdo abaixo disso, onde barra lateral não existe. A lista e o link são os
 * mesmos; muda só o arranjo. Duplicar a marcação faria as duas divergirem.
 *
 * `rel="noopener noreferrer"` e `safeHref` continuam como estavam: o endereço
 * vem de conteúdo cadastrado por outra pessoa e não pode virar `javascript:`.
 */
export const MateriaisDeApoio: React.FC<MateriaisDeApoioProps> = ({
  documentos,
  variante,
  className = '',
}) => {
  if (documentos.length === 0) {
    return null;
  }

  const naLateral = variante === 'lateral';

  const lista = (
    <div className={naLateral ? 'space-y-2.5' : 'grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1.5'}>
      {documentos.map((doc, idx) => {
        const estilo = estiloPorTipo(doc.type);
        /*
         * `download` e a seta só no arquivo nosso: em endereço de outra origem o
         * atributo é IGNORADO pelo navegador, e o ícone prometeria o que não
         * acontece.
         */
        const baixa = ehArquivoNosso(doc.url);

        return (
          <a
            key={`${doc.id}-${idx}`}
            href={safeHref(doc.url)}
            target="_blank"
            referrerPolicy="no-referrer"
            rel="noopener noreferrer"
            download={baixa ? '' : undefined}
            className={`p-3.5 rounded-xl border transition-all text-left flex items-start justify-between gap-3 group/doc shadow-3xs ${estilo.fundo}`}
          >
            <div className="flex items-start gap-3 min-w-0">
              <div className="p-2 rounded-lg bg-white shrink-0 border border-slate-100 shadow-3xs">
                <FileText className="h-4.5 w-4.5 text-ava-ciano-texto" />
              </div>
              <div className="min-w-0">
                <p className="font-extrabold text-slate-900 text-xs truncate group-hover/doc:text-ava-ciano-texto">
                  {doc.title}
                </p>
                <div className="flex items-center gap-1.5 mt-1">
                  <span className={`px-1.5 py-0.2 rounded text-sobretitulo uppercase ${estilo.rotulo}`}>
                    {doc.type}
                  </span>
                  {doc.size && <span className="text-apoio text-escult-ink-2">{doc.size}</span>}
                </div>
              </div>
            </div>
            {baixa ? (
              <Download
                aria-label="Baixar arquivo"
                className="h-3.5 w-3.5 text-escult-ink-2 group-hover/doc:text-ava-ciano-texto transition-colors shrink-0 self-center"
              />
            ) : (
              <ExternalLink
                aria-label="Abrir link em nova aba"
                className="h-3.5 w-3.5 text-escult-ink-2 group-hover/doc:text-ava-ciano-texto transition-colors shrink-0 self-center"
              />
            )}
          </a>
        );
      })}
    </div>
  );

  if (naLateral) {
    return (
      <div className={`rounded-2xl border border-slate-200 bg-white p-4 space-y-3 ${className}`}>
        <h4 className="text-slate-950 text-sobretitulo uppercase flex items-center gap-2">
          <Archive className="h-4 w-4 text-ava-ciano-texto" />
          Material de apoio ({documentos.length})
        </h4>
        {lista}
      </div>
    );
  }

  return (
    <div className={`mt-8 border-t border-slate-150 pt-6 space-y-3.5 ${className}`}>
      <h4 className="text-slate-950 text-sobretitulo uppercase flex items-center gap-2">
        <Archive className="h-4 w-4 text-ava-ciano-texto" />
        Material de Apoio e Documentos Anexos ({documentos.length})
      </h4>
      <p className="text-apoio text-escult-ink-2 -mt-1 leading-none">
        Arquivos e links disponibilizados pelo seu instrutor para aprofundamento.
      </p>
      {lista}
    </div>
  );
};
