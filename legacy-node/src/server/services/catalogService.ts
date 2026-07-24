// Biblioteca digital e webinars — conteúdo de catálogo, mesma filosofia de visibilidade
// pública das páginas de curso.
import { prisma } from '../prisma';

export const listLibraryItems = () => prisma.libraryItem.findMany();

export async function createLibraryItem(item: any) {
  const id = item.id || `lib-${Date.now()}`;
  return prisma.libraryItem.upsert({ where: { id }, update: { ...item, id }, create: { ...item, id } });
}

export const listWebinars = () => prisma.webinarEvent.findMany();

export async function createWebinar(webinar: any) {
  const id = webinar.id || `webinar-${Date.now()}`;
  return prisma.webinarEvent.upsert({ where: { id }, update: { ...webinar, id }, create: { ...webinar, id } });
}
