// Chat de aulas ao vivo e mensagens diretas. `senderName`/`senderRole` sempre vêm da
// identidade autenticada — nunca do corpo enviado pelo cliente. Novas mensagens gravam também
// as FKs de usuário (senderUserId/studentUserId); leitura usa FK-first com fallback por nome
// para mensagens legadas.
import { prisma } from '../prisma';
import { Errors } from '../utils/ApiError';
import { Requester, resolveStudentUserId } from '../utils/identity';

export const listChatMessages = (sessionId?: string) =>
  prisma.chatMessage.findMany({ where: sessionId ? { sessionId } : undefined, orderBy: { timestamp: 'asc' } });

export async function createChatMessage(input: { sessionId: string; text: string }, requester: Requester) {
  return prisma.chatMessage.create({
    data: {
      id: `chat-${Date.now()}`,
      sessionId: input.sessionId,
      senderName: requester.name,
      senderUserId: requester.sub,
      senderRole: requester.role as any,
      text: input.text,
      timestamp: new Date().toISOString(),
    },
  });
}

export async function listDirectMessages(requester: Requester, studentName?: string) {
  if (requester.role === 'student') {
    return prisma.directMessage.findMany({
      where: {
        OR: [
          { studentUserId: requester.sub },
          { AND: [{ studentUserId: null }, { studentName: requester.name }] },
        ],
      },
      orderBy: { timestamp: 'asc' },
    });
  }
  return prisma.directMessage.findMany({
    where: studentName ? { studentName } : undefined,
    orderBy: { timestamp: 'asc' },
  });
}

export async function createDirectMessage(input: { studentName: string; text: string }, requester: Requester) {
  if (requester.role === 'student' && input.studentName !== requester.name) {
    throw Errors.forbidden('Você só pode enviar mensagens no seu próprio canal de atendimento.');
  }
  const studentUserId = await resolveStudentUserId(input.studentName, requester);
  return prisma.directMessage.create({
    data: {
      id: `dm-${Date.now()}`,
      studentName: input.studentName,
      studentUserId,
      senderName: requester.name,
      senderUserId: requester.sub,
      senderRole: requester.role as any,
      text: input.text,
      timestamp: new Date().toISOString(),
    },
  });
}
