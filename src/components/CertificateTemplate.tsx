/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useEffect, useRef, useState } from 'react';
import { Award, Check, Download, Printer, ShieldCheck, X } from 'lucide-react';
import { Certificate, DocumentTemplate } from '../types';
import { downloadCertificatePdf } from '../utils/fileDownload';
import { useLMS } from '../context/LMSContext';

interface CertificateTemplateProps {
  certificate: Certificate;
  onClose: () => void;
}

const DEFAULT_TEMPLATE: Pick<DocumentTemplate, 'institutionName' | 'signatories' | 'footerText' | 'customHtml'> = {
  institutionName: 'República Federativa do Brasil • AVA LMS',
  signatories: [
    { name: 'Alessandro Pinto', role: 'Diretor de Tecnologia & AVA' },
    { name: 'Mariana Santos', role: 'Professora Responsável (Coordenação Acadêmica)' },
  ],
  footerText: 'A emissão de certificados na plataforma AVA respeita a presença mínima e obrigatória nas atividades letivas e transmissões ao vivo.',
  customHtml: null,
};

export const CertificateTemplate: React.FC<CertificateTemplateProps> = ({ certificate, onClose }) => {
  const { getDocumentTemplate } = useLMS();
  const printRef = useRef<HTMLDivElement>(null);
  const [isDownloading, setIsDownloading] = useState(false);
  const [downloadError, setDownloadError] = useState<string | null>(null);
  const [template, setTemplate] = useState(DEFAULT_TEMPLATE);

  useEffect(() => {
    let cancelled = false;
    getDocumentTemplate('certificado').then((res) => {
      if (!cancelled && res.ok && res.template) {
        setTemplate(res.template);
      }
    });
    return () => { cancelled = true; };
  }, [getDocumentTemplate]);

  const handlePrint = () => {
    if (printRef.current) {
      window.print();
    }
  };

  const handleDownloadPdf = async () => {
    setIsDownloading(true);
    setDownloadError(null);
    const error = await downloadCertificatePdf(certificate.id);
    setIsDownloading(false);
    if (error) setDownloadError(error);
  };

  return (
    <div 
      className="fixed inset-0 z-50 overflow-y-auto bg-black/60 p-4 backdrop-blur-sm flex items-start justify-center cursor-default"
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <style>{`
        @media print {
          body * {
            visibility: hidden !important;
          }
          #printable-certificate, #printable-certificate * {
            visibility: visible !important;
          }
          #printable-certificate {
            position: fixed !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            height: auto !important;
            max-height: 100% !important;
            border: 8px double #78350f !important;
            background: #fffcf9 !important;
            padding: 2.5rem !important;
            box-shadow: none !important;
            margin: 0 !important;
            border-radius: 0.5rem !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
          }
        }
      `}</style>

      <div className="relative my-8 w-full max-w-4xl rounded-2xl bg-white p-6 shadow-2xl md:p-8 animate-in fade-in zoom-in-95 duration-200">
        
        {/* Top bar controls */}
        <div className="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 gap-4">
          <div className="flex items-center gap-2">
            <Award className="h-6 w-6 text-amber-500 font-sans" />
            <div>
              <h2 className="text-lg font-bold text-slate-800 leading-tight">Visualização de Certificado</h2>
              <p className="text-[10px] text-slate-400 mt-0.5 leading-none">Baixe o PDF oficial com QR de verificação ou imprima esta visualização.</p>
              {downloadError && (
                <p className="text-[10px] text-red-600 mt-1 leading-none font-semibold">{downloadError}</p>
              )}
            </div>
          </div>
          
          <div className="flex items-center gap-2 self-end sm:self-auto">
            <button
              onClick={handleDownloadPdf}
              disabled={isDownloading}
              className="flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500 cursor-pointer transition-colors shadow-xs disabled:opacity-60 disabled:cursor-wait"
            >
              <Download className="h-4 w-4" />
              <span>{isDownloading ? 'Gerando...' : 'Baixar PDF'}</span>
            </button>
            <button
              onClick={handlePrint}
              className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer transition-colors shadow-xs"
            >
              <Printer className="h-4 w-4" />
              <span>Imprimir</span>
            </button>
            <button
              onClick={onClose}
              className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 cursor-pointer transition-colors border border-slate-200"
              title="Fechar Visualização"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
        </div>

        {/* The Printable Certificate Design */}
        <div
          ref={printRef}
          id="printable-certificate"
          className="relative overflow-hidden rounded-xl border-12 border-double border-amber-800 bg-linear-to-b from-amber-50/50 to-orange-50/30 p-8 md:p-12 text-center shadow-inner"
          style={{ fontFamily: 'Georgia, serif' }}
        >
          {/* Decorative Corner Borders */}
          <div className="absolute top-2 left-2 h-16 w-16 border-t-4 border-l-4 border-amber-800/40" />
          <div className="absolute top-2 right-2 h-16 w-16 border-t-4 border-r-4 border-amber-800/40" />
          <div className="absolute bottom-2 left-2 h-16 w-16 border-b-4 border-l-4 border-amber-800/40" />
          <div className="absolute bottom-2 right-2 h-16 w-16 border-b-4 border-r-4 border-amber-800/40" />

          {/* Background Watermark Symbol */}
          <div className="absolute inset-0 flex items-center justify-center opacity-5 pointer-events-none">
            <Award className="h-96 w-96 text-amber-900" />
          </div>

          <div className="relative z-10 flex flex-col items-center">
            {/* Header / Seal */}
            <div className="mb-4 flex flex-col items-center">
              <div className="mb-2 rounded-full bg-amber-100 p-3">
                <Award className="h-10 w-10 text-amber-700" />
              </div>
              <p className="text-xs uppercase tracking-widest text-amber-800 font-semibold font-sans">
                {template.institutionName}
              </p>
            </div>

            <h1 className="mb-6 text-3xl md:text-5xl font-bold tracking-tight text-amber-950 font-serif">
              Certificado de Conclusão
            </h1>

            <p className="max-w-xl text-md leading-relaxed text-slate-700 md:text-lg">
              Certificamos para os devidos fins de direito que o(a) aluno(a) de excelência acadêmica
            </p>

            <h3 className="my-6 text-2xl md:text-4xl font-extrabold text-amber-900 underline decoration-amber-500/30 decoration-wavy underline-offset-8">
              {certificate.studentName}
            </h3>

            <p className="max-w-2xl text-md leading-relaxed text-slate-700 md:text-lg">
              concluiu e obteve aprovação no programa de capacitação do curso livre da plataforma digital AVA
            </p>

            <h4 className="my-4 text-xl md:text-2xl font-bold text-slate-900 font-sans">
              {certificate.courseTitle}
            </h4>

            <p className="max-w-xl text-sm leading-relaxed text-slate-600 md:text-md">
              com carga horária integralizada e aproveitamento com frequência registrada de{' '}
              <strong className="text-emerald-700 font-sans">{certificate.attendancePercent}%</strong> de presença total
              nas aulas teóricas e transmissões periódicas ao vivo.
            </p>

            {/* Signatures */}
            <div className={`mt-12 grid w-full gap-8 border-t border-amber-900/10 pt-8 font-sans ${
              template.signatories.length >= 3 ? 'grid-cols-3' : template.signatories.length === 2 ? 'grid-cols-2' : 'grid-cols-1'
            }`}>
              {template.signatories.map((sig, idx) => (
                <div key={idx} className="flex flex-col items-center">
                  <div className="h-8 text-slate-400 italic font-serif">{sig.name}</div>
                  <div className="w-40 border-t border-slate-300 my-1" />
                  <span className="text-xs font-semibold text-slate-700">{sig.name}</span>
                  <span className="text-[10px] text-slate-500">{sig.role}</span>
                </div>
              ))}
            </div>

            {template.customHtml && (
              <p className="mt-6 text-[10.5px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 max-w-xl">
                Este documento usa um layout HTML personalizado — o PDF baixado pode ter uma aparência
                diferente desta prévia. Use "Baixar PDF" para ver o resultado final.
              </p>
            )}

            {/* Validation Hash Block code */}
            <div className="mt-8 flex flex-col items-center gap-1 font-mono text-[10px] text-slate-400">
              <div className="flex items-center gap-1">
                <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
                <span>Código de Verificação: <strong className="text-slate-600">{certificate.verificationHash}</strong></span>
              </div>
              <span>Emitido digitalmente em {certificate.issueDate}</span>
            </div>
          </div>
        </div>

        {/* Info footer */}
        <div className="mt-5 text-center text-xs text-slate-500">
          * {template.footerText}
        </div>
      </div>
    </div>
  );
};
