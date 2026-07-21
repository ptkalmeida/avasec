// Cliente Prisma compartilhado por todo o backend.
// Usar sempre este singleton evita abrir múltiplas conexões com o MySQL.
import { PrismaClient } from '@prisma/client';

export const prisma = new PrismaClient();
