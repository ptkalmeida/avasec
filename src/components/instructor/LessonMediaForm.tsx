/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useRef, useState } from 'react';
import { Check, X, Upload, AlertCircle, CheckCircle2, Loader2 } from 'lucide-react';
import { ehLegenda, parseLessonContent, serializeLessonBlock } from '../../utils/lessonContent';
import { parseVideoSource } from '../../utils/videoSource';
import { LessonContent } from '../student/LessonContent';

/** Extensões aceitas: espelha a allowlist de config/uploads.php do backend. */
const ACCEPT_IMAGEM = 'image/png,image/jpeg,image/webp,image/gif';

/**
 * Acima disto o aluno sente no celular. O servidor aceita até 15 MB e não
 * redimensiona nada, então o arquivo é baixado inteiro por quem abrir a aula —
 * daí o aviso. É aviso, não bloqueio: quem escreve pode ter um bom motivo.
 */
const BYTES_PESADO = 2 * 1024 * 1024;

const campo = 'w-full rounded-lg border border-slate-200 p-2.5 text-apoio text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500';
const rotuloCampo = 'block text-sobretitulo text-escult-ink-2 uppercase mb-1';

export interface MediaInicial {
  url: string;
  /** Descrição da imagem ou título do vídeo. */
  texto: string;
  caption: string | null;
}

interface LessonMediaFormProps {
  tipo: 'image' | 'video';
  inicial?: MediaInicial;
  /** Mesma assinatura de `uploadArquivo` do LMSContext. */
  onUpload?: (file: File) => Promise<{ ok: boolean; url?: string; error?: string }>;
  onCancel: () => void;
  /** Recebe o trecho JÁ serializado, pronto para entrar no conteúdo. */
  onConfirm: (texto: string) => void;
}

const formatarTamanho = (bytes: number): string =>
  bytes < 1024 * 1024 ? `${Math.round(bytes / 1024)} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;

/**
 * Formulário de imagem e vídeo do corpo da aula, com dois pontos de entrada: o
 * menu "+" (criar) e o lápis (editar). Um só formulário para os dois casos, para
 * a validação da descrição e da legenda não existir em duplicata.
 *
 * Imagem entra por ENVIO DE ARQUIVO, não por endereço colado: assim o material
 * fica no servidor da escola, não depende de site de terceiro continuar no ar e
 * não expõe o IP do aluno a quem hospeda a figura.
 *
 * Vídeo é o oposto — entra por LINK do YouTube, porque `/api/upload` não aceita
 * mp4/webm (config/uploads.php) e o ADR 08 define o YouTube como origem.
 */
export const LessonMediaForm: React.FC<LessonMediaFormProps> = ({
  tipo, inicial, onUpload, onCancel, onConfirm,
}) => {
  const [url, setUrl] = useState(inicial?.url ?? '');
  const [texto, setTexto] = useState(inicial?.texto ?? '');
  const [legenda, setLegenda] = useState(inicial?.caption ?? '');
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [pesado, setPesado] = useState<string | null>(null);
  const inputArquivo = useRef<HTMLInputElement>(null);

  const ehImagem = tipo === 'image';
  const videoReconhecido = ehImagem ? null : parseVideoSource(url.trim());

  const enviarArquivo = async (file: File) => {
    if (onUpload === undefined) return;
    setErro(null);
    setPesado(file.size > BYTES_PESADO ? formatarTamanho(file.size) : null);
    setEnviando(true);
    const r = await onUpload(file);
    setEnviando(false);

    if (!r.ok || r.url === undefined) {
      setErro(r.error ?? 'Falha ao enviar a imagem.');

      return;
    }
    setUrl(r.url);
    // A descrição NUNCA é preenchida com o nome do arquivo: "IMG_20240712.jpg"
    // lido em voz alta por um leitor de tela é ruído, não informação.
  };

  const legendaLimpa = legenda.trim();
  const legendaForaDoPadrao = legendaLimpa !== '' && !ehLegenda(legendaLimpa);

  const bloco = {
    ...(ehImagem
      ? { kind: 'image' as const, url: url.trim(), alt: texto.trim() }
      : { kind: 'video' as const, url: url.trim(), title: texto.trim() }),
    caption: legendaLimpa === '' ? null : legendaLimpa,
    range: { start: 0, end: 0 },
  };
  const serializado = serializeLessonBlock(bloco);

  const faltaDescricao = texto.trim() === '';
  const podeAplicar = url.trim() !== '' && !faltaDescricao && !enviando && !legendaForaDoPadrao;

  return (
    <div className="rounded-xl border-2 border-teal-500/60 bg-teal-50/20 p-3 space-y-2.5">
      <span className="text-sobretitulo uppercase text-teal-700">
        {inicial ? 'Editando' : 'Adicionar'}: {ehImagem ? 'Imagem' : 'Vídeo'}
      </span>

      {ehImagem ? (
        <div>
          <label className={rotuloCampo}>Arquivo da imagem</label>
          <input
            ref={inputArquivo}
            type="file"
            accept={ACCEPT_IMAGEM}
            className="hidden"
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (file) void enviarArquivo(file);
              e.target.value = '';
            }}
          />
          <button
            type="button"
            disabled={enviando || onUpload === undefined}
            onClick={() => inputArquivo.current?.click()}
            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-apoio font-bold text-slate-600 hover:border-teal-400 hover:text-teal-700 transition-colors cursor-pointer disabled:cursor-not-allowed disabled:text-escult-ink-2"
          >
            {enviando
              ? <><Loader2 className="h-3.5 w-3.5 animate-spin" /> Enviando...</>
              : <><Upload className="h-3.5 w-3.5" /> {url === '' ? 'Escolher imagem' : 'Trocar imagem'}</>}
          </button>

          {onUpload === undefined && (
            <p className="mt-1 text-apoio text-escult-ink-2">
              O envio de arquivos não está disponível nesta tela.
            </p>
          )}
          {url !== '' && !enviando && (
            <p className="mt-1.5 inline-flex items-center gap-1.5 text-apoio font-bold text-emerald-700">
              <CheckCircle2 className="h-3.5 w-3.5" /> Imagem enviada.
            </p>
          )}
          {pesado !== null && (
            <p className="mt-1 flex items-start gap-1.5 text-apoio font-bold text-amber-700">
              <AlertCircle className="h-3.5 w-3.5 shrink-0 mt-px" />
              <span>Esta imagem é pesada ({pesado}) e pode demorar para abrir no celular do aluno.</span>
            </p>
          )}
        </div>
      ) : (
        <div>
          <label className={rotuloCampo}>Link do vídeo no YouTube</label>
          <input
            type="text"
            inputMode="url"
            autoFocus
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            placeholder="Cole o link do YouTube aqui (ex.: https://youtu.be/...)"
            className={campo}
          />
          {url.trim() !== '' && videoReconhecido === null && (
            <p className="mt-1 flex items-start gap-1.5 text-apoio font-bold text-amber-700">
              <AlertCircle className="h-3.5 w-3.5 shrink-0 mt-px" />
              <span>
                Não reconhecemos esse link. Copie o endereço direto do vídeo no YouTube — ele
                começa com https://www.youtube.com ou https://youtu.be.
              </span>
            </p>
          )}
        </div>
      )}

      {erro !== null && (
        <p className="flex items-start gap-1.5 text-apoio font-bold text-red-700">
          <AlertCircle className="h-3.5 w-3.5 shrink-0 mt-px" /> {erro}
        </p>
      )}

      <div>
        <label className={rotuloCampo}>
          {ehImagem ? 'Descrição da imagem (obrigatória)' : 'Título do vídeo (obrigatório)'}
        </label>
        <input
          type="text"
          value={texto}
          onChange={(e) => setTexto(e.target.value)}
          placeholder={ehImagem ? 'Ex.: hierarquia das classes Pessoa, Física e Aluno' : 'Ex.: demonstração do cadastro'}
          className={campo}
        />
        {ehImagem && (
          <p className="mt-1 text-apoio text-escult-ink-2">
            Descreva o que a imagem mostra. Quem usa leitor de tela, ou está com a internet ruim,
            recebe esta descrição no lugar da figura.
          </p>
        )}
      </div>

      <div>
        <label className={rotuloCampo}>Legenda (opcional)</label>
        <input
          type="text"
          value={legenda}
          onChange={(e) => setLegenda(e.target.value)}
          placeholder={ehImagem ? 'Ex.: Figura 1 - fluxo de matrícula' : 'Ex.: Vídeo 1 - demonstração'}
          className={campo}
        />
        {legendaForaDoPadrao ? (
          <p className="mt-1 flex items-start gap-1.5 text-apoio font-bold text-amber-700">
            <AlertCircle className="h-3.5 w-3.5 shrink-0 mt-px" />
            <span>
              Comece a legenda por Figura, Imagem, Gráfico, Vídeo, Tabela ou Quadro, seguido do
              número — senão ela vira um parágrafo solto em vez de legenda.
            </span>
          </p>
        ) : (
          <p className="mt-1 text-apoio text-escult-ink-2">Aparece abaixo da figura, na aula.</p>
        )}
      </div>

      {url.trim() !== '' && !faltaDescricao && (
        <div>
          <span className={rotuloCampo}>Prévia (é assim que o aluno vai ver)</span>
          <div className="rounded-lg border border-slate-200 bg-white p-3">
            <LessonContent blocks={parseLessonContent(serializado).blocks} />
          </div>
        </div>
      )}

      <div className="flex justify-end gap-2 pt-0.5">
        <button
          type="button"
          onClick={onCancel}
          className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-apoio font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
        >
          <X className="h-3 w-3" /> Cancelar
        </button>
        <button
          type="button"
          disabled={!podeAplicar}
          title={faltaDescricao ? 'Descreva a mídia antes de aplicar' : undefined}
          onClick={() => onConfirm(serializado)}
          className="inline-flex items-center gap-1 rounded-lg bg-teal-600 hover:bg-teal-500 px-3.5 py-1.5 text-sobretitulo uppercase text-white transition-colors cursor-pointer disabled:bg-slate-300 disabled:cursor-not-allowed"
        >
          <Check className="h-3 w-3" /> Aplicar
        </button>
      </div>
    </div>
  );
};
