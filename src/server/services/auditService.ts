// Auditoria server-side. As ações sensíveis (login, falha de login, mudança de status,
// matrícula, cancelamento, emissão de certificado, exportação, aprovação de solicitação,
// alteração de curso/permissões) são registradas AQUI, no backend — nunca a partir de um
// POST de log vindo do cliente, que poderia ser forjado ou simplesmente omitido.
// Nunca logar senha, token ou dado sensível completo (só IDs/nomes/ação).
import { prisma } from '../prisma';
import type { AuthedRequest } from '../middlewares/auth';

export type AuditStatus = 'SUCCESS' | 'WARNING' | 'FAILED';

interface AuditActor {
  name: string;
  role: string;
}

function actorFromRequest(req: AuthedRequest): AuditActor {
  if (req.user) return { name: req.user.name, role: req.user.role };
  return { name: 'Visitante Anônimo', role: 'anonymous' };
}

function clientIp(req: AuthedRequest): string {
  const forwarded = req.headers['x-forwarded-for'];
  if (typeof forwarded === 'string' && forwarded.length > 0) return forwarded.split(',')[0].trim();
  return req.socket.remoteAddress || 'desconhecido';
}

export async function logAudit(
  req: AuthedRequest,
  action: string,
  details: string,
  status: AuditStatus = 'SUCCESS',
  actorOverride?: AuditActor
) {
  const actor = actorOverride ?? actorFromRequest(req);
  const now = new Date();
  try {
    await prisma.securityLog.create({
      data: {
        id: `log-${now.getTime()}-${Math.random().toString(36).slice(2, 8)}`,
        timestamp: now.toLocaleTimeString('pt-BR') + ' ' + now.toLocaleDateString('pt-BR'),
        user: actor.name,
        role: actor.role,
        ipAddress: clientIp(req),
        device: String(req.headers['user-agent'] || 'desconhecido').slice(0, 200),
        action,
        details: details.slice(0, 1000),
        status,
      },
    });
  } catch (err) {
    // Auditoria não pode derrubar a requisição principal, mas o erro precisa ficar visível no log do servidor.
    console.error('[AUDIT] Falha ao gravar log de auditoria:', err);
  }
}
