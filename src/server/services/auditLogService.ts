// Leitura/gravação de logs de auditoria via API (distinto de auditService.logAudit, que é
// chamado internamente pelos outros services para eventos sensíveis). Este arquivo cobre o
// endpoint usado pelo frontend para eventos de UI de baixo risco (navegação, narração) e a
// listagem paginada para o painel do administrador.
import { prisma } from '../prisma';

export async function listSecurityLogs(skip: number, take: number) {
  const [items, total] = await Promise.all([
    prisma.securityLog.findMany({ orderBy: { timestamp: 'desc' }, skip, take }),
    prisma.securityLog.count(),
  ]);
  return { items, total };
}

export async function recordClientEvent(
  input: { action: string; details: string; status?: 'SUCCESS' | 'WARNING' | 'FAILED' },
  actor: { name: string; role: string },
  ipAddress: string,
  device: string
) {
  const now = new Date();
  return prisma.securityLog.create({
    data: {
      id: `log-${now.getTime()}-${Math.random().toString(36).slice(2, 8)}`,
      timestamp: now.toLocaleTimeString('pt-BR') + ' ' + now.toLocaleDateString('pt-BR'),
      user: actor.name,
      role: actor.role,
      ipAddress,
      device: device.slice(0, 200),
      action: input.action.slice(0, 200),
      details: input.details.slice(0, 1000),
      status: input.status ?? 'SUCCESS',
    },
  });
}

export async function clearSecurityLogs() {
  await prisma.securityLog.deleteMany();
}
