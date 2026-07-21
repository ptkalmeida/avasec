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
