// Runner do seed para `npx prisma db seed` / `npm run db:seed`.
import { PrismaClient } from '@prisma/client';
import { runSeed } from './seedData';

const prisma = new PrismaClient();

runSeed(prisma)
  .catch((e) => {
    console.error('Erro durante o seed:', e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
