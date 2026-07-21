import { prisma } from '../prisma';

export async function getSystemSettings() {
  const row = await prisma.systemSettings.findUnique({ where: { id: 'singleton' } });
  return row?.data ?? {};
}

export async function updateSystemSettings(updates: Record<string, unknown>) {
  const row = await prisma.systemSettings.findUnique({ where: { id: 'singleton' } });
  const merged = { ...((row?.data as object) ?? {}), ...updates } as Record<string, unknown>;
  const saved = await prisma.systemSettings.upsert({
    where: { id: 'singleton' },
    update: { data: merged as any },
    create: { id: 'singleton', data: merged as any },
  });
  return saved.data;
}
