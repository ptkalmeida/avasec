// Reset completo do banco para os dados de seed. Só existe fora de produção — em produção,
// resetar o banco inteiro é uma ação destrutiva demais para ficar atrás de uma única rota de API,
// mesmo com admin-only + rate limit. Use `npx prisma migrate reset` manualmente se precisar.
import { Router } from 'express';
import { prisma } from '../prisma';
import { runSeed } from '../../../prisma/seedData';
import { requireAuth } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { requireRole } from '../middlewares/rbac';
import { asyncHandler } from '../utils/asyncHandler';
import { logAudit } from '../services/auditService';

export const devRouter = Router();

devRouter.post(
  '/reset',
  requireAuth,
  requireActiveAccount,
  requireRole('admin'),
  asyncHandler(async (req, res) => {
    await prisma.$transaction([
      prisma.exerciseSubmission.deleteMany(),
      prisma.practicalExercise.deleteMany(),
      prisma.quizSubmission.deleteMany(),
      prisma.quizQuestion.deleteMany(),
      prisma.quiz.deleteMany(),
      prisma.forumMessage.deleteMany(),
      prisma.chatMessage.deleteMany(),
      prisma.directMessage.deleteMany(),
      prisma.certificate.deleteMany(),
      prisma.studentProgress.deleteMany(),
      prisma.studentEnrollment.deleteMany(),
      prisma.academicRequest.deleteMany(),
      prisma.admissionRequest.deleteMany(),
      prisma.securityLog.deleteMany(),
      prisma.lessonDocument.deleteMany(),
      prisma.lesson.deleteMany(),
      prisma.liveSession.deleteMany(),
      prisma.course.deleteMany(),
      prisma.libraryItem.deleteMany(),
      prisma.webinarEvent.deleteMany(),
      prisma.systemSettings.deleteMany(),
      prisma.user.deleteMany(),
    ]);
    await runSeed(prisma);
    await logAudit(req as any, 'Restauração do Banco (Dev)', 'Banco de dados restaurado para o padrão da plataforma.', 'WARNING');
    res.json({ success: true, message: 'Banco de dados restaurado para o padrão da plataforma.' });
  })
);
