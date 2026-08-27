/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useEffect, useState } from 'react';
import { ArrowUp, ArrowDown, Trash2, Plus, ExternalLink, Save, AlertCircle, CheckCircle } from 'lucide-react';
import { useLMS } from '../../context/LMSContext';
import { BackButton } from '../BackButton';
import { SitePageContent, SitePageItem, SitePageKey, SitePageSchema } from '../../types';

interface SiteContentPanelProps {
  onBack: () => void;
  /** Abre a página pública correspondente, para conferir o resultado. */
  onPreviewPage: (pageKey: SitePageKey) => void;
  speakText: (text: string) => void;
  showToast: (message: string) => void;
}

/** Lê um campo de cabeçalho do rascunho como texto. */
const headerValue = (draft: SitePageContent, key: string): string =>
  typeof draft[key] === 'string' ? (draft[key] as string) : '';

/**
 * Gestão do conteúdo das páginas públicas do portal. Os formulários são
 * montados a partir do schema servido pela API (rótulos em português), de modo
 * que incluir um campo novo no backend não exige mexer nesta tela.
 *
 * Tudo é texto puro — não há HTML nem marcação a aprender. É proposital: quem
 * edita não precisa ser técnico, e conteúdo com HTML livre em página pública
 * seria uma porta de XSS.
 */
export const SiteContentPanel: React.FC<SiteContentPanelProps> = ({
  onBack,
  onPreviewPage,
  speakText,
  showToast,
}) => {
  const { sitePageContent, sitePageSchema, updateSitePageContent } = useLMS();

  const pageKeys = sitePageSchema ? (Object.keys(sitePageSchema) as SitePageKey[]) : [];
  const [selectedPage, setSelectedPage] = useState<SitePageKey | null>(null);
  const [draft, setDraft] = useState<SitePageContent | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  // Seleciona a primeira página assim que o schema chega.
  useEffect(() => {
    if (selectedPage === null && pageKeys.length > 0) {
      setSelectedPage(pageKeys[0]);
    }
  }, [pageKeys, selectedPage]);

  // Recarrega o rascunho ao trocar de página (descartando edições não salvas).
  useEffect(() => {
    if (selectedPage === null || !sitePageContent) return;
    const loaded = sitePageContent[selectedPage];
    if (loaded) {
      setDraft(JSON.parse(JSON.stringify(loaded)) as SitePageContent);
      setError(null);
      setSaved(false);
    }
  }, [selectedPage, sitePageContent]);

  if (!sitePageSchema || !sitePageContent) {
    return (
      <div className="space-y-5 text-left">
        <BackButton onClick={onBack} text="Voltar ao Painel Administrativo" />
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-12 text-center">
          <p className="text-xs text-slate-500">Carregando o conteúdo das páginas...</p>
        </div>
      </div>
    );
  }

  const schema: SitePageSchema | null = selectedPage ? sitePageSchema[selectedPage] : null;

  const setHeaderField = (key: string, value: string) => {
    if (!draft) return;
    setDraft({ ...draft, [key]: value });
    setSaved(false);
  };

  const setItemField = (index: number, key: string, value: string) => {
    if (!draft) return;
    const items = [...draft.items];
    items[index] = { ...items[index], [key]: value };
    setDraft({ ...draft, items });
    setSaved(false);
  };

  const addItem = () => {
    if (!draft || !schema) return;
    if (draft.items.length >= schema.maxItems) {
      setError(`Esta página aceita no máximo ${schema.maxItems} ${schema.itemsLabel.toLowerCase()}.`);
      return;
    }
    const blank: SitePageItem = { id: `novo-${Date.now()}` };
    schema.item.forEach((field) => { blank[field.key] = ''; });
    setDraft({ ...draft, items: [...draft.items, blank] });
    setError(null);
    setSaved(false);
    speakText('Novo item adicionado ao final da lista.');
  };

  const removeItem = (index: number) => {
    if (!draft) return;
    setDraft({ ...draft, items: draft.items.filter((_, i) => i !== index) });
    setSaved(false);
    speakText('Item removido.');
  };

  const moveItem = (index: number, direction: -1 | 1) => {
    if (!draft) return;
    const target = index + direction;
    if (target < 0 || target >= draft.items.length) return;
    const items = [...draft.items];
    [items[index], items[target]] = [items[target], items[index]];
    setDraft({ ...draft, items });
    setSaved(false);
  };

  const handleSave = async () => {
    if (!draft || !selectedPage || !schema) return;
    setSaving(true);
    setError(null);

    // Envia só o que o schema conhece — cabeçalho + itens.
    const payload: Partial<SitePageContent> = { items: draft.items };
    schema.header.forEach((field) => {
      (payload as Record<string, unknown>)[field.key] = headerValue(draft, field.key);
    });

    const res = await updateSitePageContent(selectedPage, payload);
    setSaving(false);

    if (res.ok && res.page) {
      setDraft(JSON.parse(JSON.stringify(res.page)) as SitePageContent);
      setSaved(true);
      showToast('Conteúdo da página salvo e publicado no site.');
      speakText('Conteúdo salvo e publicado.');
      setTimeout(() => setSaved(false), 4000);
    } else {
      setError(res.error || 'Não foi possível salvar o conteúdo.');
    }
  };

  return (
    <div className="space-y-5 text-left">
      <div>
        <BackButton onClick={onBack} text="Voltar ao Painel Administrativo" />
      </div>

      <div className="space-y-1">
        <h2 className="text-lg font-black text-slate-900 font-serif">Gestão de Páginas do Site</h2>
        <p className="text-xs text-slate-500 leading-relaxed max-w-3xl">
          Edite os textos das páginas públicas do portal. Escolha a página, ajuste os campos e
          clique em <strong>Salvar</strong> — a alteração aparece no site imediatamente. Se um
          campo ficar em branco, o site mostra o texto original de fábrica.
        </p>
      </div>

      {/* Seletor de páginas: cards, para o editor ver tudo o que pode mudar. */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-2.5">
        {pageKeys.map((key) => {
          const isActive = key === selectedPage;
          const pageSchema = sitePageSchema[key];
          const itemCount = sitePageContent[key]?.items?.length ?? 0;
          return (
            <button
              key={key}
              onClick={() => { setSelectedPage(key); speakText(`Editando ${pageSchema.label}`); }}
              className={`text-left rounded-xl border px-3.5 py-3 transition-all cursor-pointer ${
                isActive
                  ? 'bg-[#540D6E] border-[#540D6E] text-white shadow-sm'
                  : 'bg-white border-slate-200 text-slate-700 hover:border-slate-350'
              }`}
            >
              <span className="block text-xs font-black leading-snug">{pageSchema.label}</span>
              <span className={`block text-[10px] mt-0.5 ${isActive ? 'text-white/70' : 'text-slate-400'}`}>
                {itemCount} {pageSchema.itemsLabel.toLowerCase()}
              </span>
            </button>
          );
        })}
      </div>

      {draft && schema && selectedPage && (
        <div className="space-y-5">
          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-5">
            <div className="space-y-0.5">
              <h3 className="text-sm font-black text-slate-900">{schema.label}</h3>
              {draft.updatedAt && (
                <p className="text-[10px] text-slate-400 font-mono">
                  Última alteração em {new Date(draft.updatedAt).toLocaleString('pt-BR')}
                </p>
              )}
            </div>
            <button
              onClick={() => onPreviewPage(selectedPage)}
              className="flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-[#540D6E] hover:underline cursor-pointer"
            >
              <ExternalLink className="h-3.5 w-3.5" />
              <span>Ver esta página no site</span>
            </button>
          </div>

          {/* Cabeçalho da página */}
          <div className="bg-white rounded-2xl border border-slate-200 p-5 space-y-4 shadow-3xs">
            <h4 className="text-xs font-black text-slate-800 uppercase tracking-wider">Topo da página</h4>
            {schema.header.map((field) => (
              <label key={field.key} className="block space-y-1.5">
                <span className="text-[11px] font-bold text-slate-600 block">{field.label}</span>
                {field.type === 'textarea' ? (
                  <textarea
                    value={headerValue(draft, field.key)}
                    onChange={(e) => setHeaderField(field.key, e.target.value)}
                    maxLength={field.maxLength}
                    rows={3}
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#540D6E]/20 focus:border-[#540D6E] resize-y"
                  />
                ) : (
                  <input
                    type="text"
                    value={headerValue(draft, field.key)}
                    onChange={(e) => setHeaderField(field.key, e.target.value)}
                    maxLength={field.maxLength}
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#540D6E]/20 focus:border-[#540D6E]"
                  />
                )}
              </label>
            ))}
          </div>

          {/* Lista de itens */}
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <h4 className="text-xs font-black text-slate-800 uppercase tracking-wider">
                {schema.itemsLabel}{' '}
                <span className="text-slate-400 font-mono normal-case">
                  ({draft.items.length} de {schema.maxItems})
                </span>
              </h4>
              <button
                onClick={addItem}
                className="flex items-center gap-1.5 rounded-xl bg-[#3BCEAC] hover:bg-teal-500 text-slate-900 px-3.5 py-2 text-[11px] font-black uppercase tracking-wider transition-all cursor-pointer shadow-3xs"
              >
                <Plus className="h-3.5 w-3.5" />
                <span>Adicionar</span>
              </button>
            </div>

            {draft.items.length === 0 ? (
              <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center">
                <p className="text-xs text-slate-500">
                  Nenhum item cadastrado. Clique em <strong>Adicionar</strong> para criar o primeiro.
                </p>
              </div>
            ) : (
              draft.items.map((item, index) => (
                <div key={item.id} className="bg-white rounded-2xl border border-slate-200 p-5 space-y-4 shadow-3xs">
                  <div className="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <span className="text-[11px] font-black text-slate-500 font-mono">
                      {index + 1}º de {draft.items.length}
                    </span>
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => moveItem(index, -1)}
                        disabled={index === 0}
                        title="Mover para cima"
                        className="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition-all"
                      >
                        <ArrowUp className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => moveItem(index, 1)}
                        disabled={index === draft.items.length - 1}
                        title="Mover para baixo"
                        className="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition-all"
                      >
                        <ArrowDown className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => removeItem(index)}
                        title="Remover este item"
                        className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 hover:text-rose-700 cursor-pointer transition-all"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </div>

                  {schema.item.map((field) => (
                    <label key={field.key} className="block space-y-1.5">
                      <span className="text-[11px] font-bold text-slate-600 block">{field.label}</span>
                      {field.type === 'textarea' ? (
                        <textarea
                          value={item[field.key] ?? ''}
                          onChange={(e) => setItemField(index, field.key, e.target.value)}
                          maxLength={field.maxLength}
                          rows={3}
                          className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#540D6E]/20 focus:border-[#540D6E] resize-y"
                        />
                      ) : (
                        <input
                          type="text"
                          value={item[field.key] ?? ''}
                          onChange={(e) => setItemField(index, field.key, e.target.value)}
                          maxLength={field.maxLength}
                          className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#540D6E]/20 focus:border-[#540D6E]"
                        />
                      )}
                    </label>
                  ))}
                </div>
              ))
            )}
          </div>

          {error && (
            <div className="flex items-start gap-2.5 rounded-xl bg-rose-50 border border-rose-200 p-3.5">
              <AlertCircle className="h-4 w-4 text-rose-600 shrink-0 mt-0.5" />
              <p className="text-xs text-rose-800 leading-relaxed">{error}</p>
            </div>
          )}

          {saved && (
            <div className="flex items-start gap-2.5 rounded-xl bg-emerald-50 border border-emerald-200 p-3.5">
              <CheckCircle className="h-4 w-4 text-emerald-600 shrink-0 mt-0.5" />
              <p className="text-xs text-emerald-800 leading-relaxed">
                Conteúdo publicado. Abra a página no site para conferir.
              </p>
            </div>
          )}

          <div className="flex justify-end border-t border-slate-200 pt-5">
            <button
              onClick={handleSave}
              disabled={saving}
              className="flex items-center gap-2 rounded-xl bg-[#540D6E] hover:bg-purple-950 disabled:opacity-60 disabled:cursor-not-allowed text-white px-6 py-3 text-xs font-black uppercase tracking-widest transition-all cursor-pointer shadow-sm"
            >
              <Save className="h-4 w-4" />
              <span>{saving ? 'Salvando...' : 'Salvar e publicar'}</span>
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
