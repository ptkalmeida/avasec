// Download de anexos de entregas. Arquivos privados são servidos por /api/files/:id, que exige
// autenticação — um <a href> comum não envia o token, então o download é feito via authFetch
// e um blob temporário. URLs públicas (/uploads/...) abrem normalmente em nova aba.
import { authFetch } from '../context/LMSContext';

export async function downloadSubmissionFile(fileUrl: string, fileName?: string): Promise<string | null> {
  if (!fileUrl || fileUrl === '#') return 'Arquivo indisponível.';

  // Materiais públicos não exigem token — comportamento original preservado.
  if (!fileUrl.startsWith('/api/files/')) {
    window.open(fileUrl, '_blank', 'noopener,noreferrer');
    return null;
  }

  try {
    const res = await authFetch(fileUrl);
    if (!res.ok) {
      const body = await res.json().catch(() => ({} as any));
      return body.message || 'Você não tem permissão para acessar este arquivo.';
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName || fileUrl.split('/').pop() || 'anexo';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    return null;
  } catch {
    return 'Servidor indisponível para download do arquivo.';
  }
}

// Pré-visualização do template de documento ATUALMENTE SALVO, com dados de
// exemplo — abre numa nova aba (o navegador exibe o PDF direto no visualizador
// nativo, não força download). Só existe pipeline de PDF para 'certificado' hoje.
export async function previewDocumentTemplatePdf(type: string): Promise<string | null> {
  try {
    const res = await authFetch(`/api/document-templates/${encodeURIComponent(type)}/preview`);
    if (!res.ok) {
      const body = await res.json().catch(() => ({} as any));
      return body.message || 'Não foi possível gerar a pré-visualização.';
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank', 'noopener,noreferrer');
    // Revoga depois de dar tempo da nova aba carregar o blob.
    setTimeout(() => URL.revokeObjectURL(url), 60000);
    return null;
  } catch {
    return 'Servidor indisponível para pré-visualização.';
  }
}

// PDF de certificado (ADR 09): rota autenticada, mesmo padrão de blob temporário.
// Retorna null em sucesso ou uma mensagem de erro para exibição.
export async function downloadCertificatePdf(certificateId: string): Promise<string | null> {
  if (!certificateId) return 'Certificado indisponível.';

  try {
    const res = await authFetch(`/api/certificates/${encodeURIComponent(certificateId)}/pdf`);
    if (!res.ok) {
      const body = await res.json().catch(() => ({} as any));
      return body.message || 'Não foi possível baixar o certificado.';
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `certificado-${certificateId}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    return null;
  } catch {
    return 'Servidor indisponível para download do certificado.';
  }
}
