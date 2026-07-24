// Leitura da trilha de AUDITORIA (SecurityLog — gravada exclusivamente pelo servidor via
// auditService.logAudit) e gravação/leitura de TELEMETRIA (ClientEvent — eventos de UI de
// baixo risco enviados pelo frontend). São tabelas e finalidades distintas: telemetria nunca
// se mistura à trilha de auditoria.
import { prisma } from '../prisma';

export async function listSecurityLogs(skip: number, take: number) {
  const [items, total] = await Promise.all([
    prisma.securityLog.findMany({ orderBy: { timestamp: 'desc' }, skip, take }),
    prisma.securityLog.count(),
  ]);
  return { items, total };
}

export async function clearSecurityLogs() {
  await prisma.securityLog.deleteMany();
}

export async function recordClientEvent(
  input: { action: string; details: string; status?: 'SUCCESS' | 'WARNING' | 'FAILED' },
  actor: { name: string; role: string },
  ipAddress: string,
  device: string
) {
  return prisma.clientEvent.create({
    data: {
      user: actor.name,
      role: actor.role,
      ipAddress,
      device: device.slice(0, 190),
      action: input.action.slice(0, 190),
      details: input.details.slice(0, 1000),
      status: input.status ?? 'SUCCESS',
    },
  });
}

export async function listClientEvents(skip: number, take: number) {
  const [items, total] = await Promise.all([
    prisma.clientEvent.findMany({ orderBy: { createdAt: 'desc' }, skip, take }),
    prisma.clientEvent.count(),
  ]);
  return { items, total };
}
