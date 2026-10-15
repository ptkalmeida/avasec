import React, { useRef, useState } from 'react';
import { ImageUp, Loader2, Trash2 } from 'lucide-react';
import { safeHref } from '../../utils/safeUrl';

interface CapaDoCursoProps {
  /** URL atual da capa, vazia quando o curso ainda não tem. */
  capa?: string;
  /** Envia o arquivo e devolve a URL que o servidor gravou. */
  onEnviar: (file: File) => Promise<{ ok: boolean; url?: string; error?: string }>;
  /** Grava a URL no curso (ou a limpa, com `null`). */
  onGravar: (url: string | null) => void;
  onErro: (mensagem: string) => void;
  onSucesso: (mensagem: string) => void;
}

/** 4 MB. O servidor também limita; aqui é só para avisar antes de subir. */
const LIMITE_BYTES = 4 * 1024 * 1024;

const TIPOS = ['image/png', 'image/jpeg', 'image/webp'];

/**
 * Envio da imagem de capa do curso — a que o aluno vê na sala sem aula aberta.
 *
 * NÃO é a miniatura (`thumbnail`), que aparece nos cartões do catálogo. São dois
 * campos distintos e com usos distintos; trocá-los faz a listagem exibir uma
 * imagem cortada e a sala exibir uma esticada.
 *
 * A validação daqui é de conveniência, não de segurança: o `UploadService` do
 * servidor confere o tipo real por *magic bytes* e gera o nome do arquivo. Um
 * arquivo renomeado para `.png` passa por esta checagem e é recusado lá — que é
 * onde a recusa importa.
 */
export const CapaDoCurso: React.FC<CapaDoCursoProps> = ({
  capa,
  onEnviar,
  onGravar,
  onErro,
  onSucesso,
}) => {
  const [enviando, setEnviando] = useState(false);
  const campo = useRef<HTMLInputElement>(null);

  const escolher = async (file: File | undefined): Promise<void> => {
    if (!file) return;

    if (!TIPOS.includes(file.type)) {
      onErro('A capa precisa ser PNG, JPEG ou WebP.');

      return;
    }
    if (file.size > LIMITE_BYTES) {
      onErro(`A imagem tem ${(file.size / 1024 / 1024).toFixed(1)} MB; o limite é 4 MB.`);

      return;
    }

    setEnviando(true);
    const r = await onEnviar(file);
    setEnviando(false);

    /*
     * Só grava depois de o envio responder com a URL. A primeira versão gravava
     * o nome do arquivo e disparava o envio em paralelo: numa falha de rede, o
     * curso ficava apontando para um arquivo que nunca chegou ao servidor, e a
     * sala do aluno exibia imagem quebrada.
     */
    if (!r.ok || !r.url) {
      onErro(r.error ?? 'Não foi possível enviar a imagem.');

      return;
    }

    onGravar(r.url);
    onSucesso('Capa do curso atualizada.');

    // Sem isto, escolher o MESMO arquivo de novo não dispara `change`.
    if (campo.current) campo.current.value = '';
  };

  return (
    <div className="flex items-center gap-2">
      <span className="font-bold text-escult-ink-3 uppercase tracking-wide">Capa:</span>

      {capa ? (
        <img
          src={safeHref(capa)}
          alt="Capa atual do curso"
          className="h-8 w-14 rounded border border-slate-200 object-cover"
        />
      ) : (
        <span className="text-apoio text-escult-ink-3 italic">sem capa</span>
      )}

      <input
        ref={campo}
        type="file"
        accept={TIPOS.join(',')}
        className="hidden"
        onChange={(e) => void escolher(e.target.files?.[0])}
      />

      <button
        type="button"
        disabled={enviando}
        onClick={() => campo.current?.click()}
        className="flex items-center gap-1 rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-apoio font-bold text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 cursor-pointer"
      >
        {enviando ? (
          <>
            <Loader2 className="h-3 w-3 animate-spin" />
            <span>Enviando…</span>
          </>
        ) : (
          <>
            <ImageUp className="h-3 w-3" />
            <span>{capa ? 'Trocar' : 'Enviar'}</span>
          </>
        )}
      </button>

      {/*
        Remover limpa o CAMPO do curso, não o arquivo no servidor. O arquivo
        pode estar referenciado por outro registro, e apagá-lo daqui seria
        remoção física — ver ADR 12.
      */}
      {capa && !enviando && (
        <button
          type="button"
          onClick={() => {
            onGravar(null);
            onSucesso('Capa removida do curso.');
          }}
          aria-label="Remover a capa deste curso"
          className="rounded-md border border-slate-200 bg-white p-1 text-escult-ink-2 transition-colors hover:border-rose-200 hover:text-rose-600 cursor-pointer"
        >
          <Trash2 className="h-3 w-3" />
        </button>
      )}
    </div>
  );
};
