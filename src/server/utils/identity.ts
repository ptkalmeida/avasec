// Helpers de identidade da fase de transição nome -> FK.
// Regra: linhas novas sempre gravam a FK (userId); a autorização usa a FK quando a linha tem
// uma, e só cai para a comparação por nome em linhas antigas que ainda não foram associadas.
import { prisma } from '../prisma';

export interface Requester {
  sub: string; // User.id (do token JWT)
  name: string;
  role: 'student' | 'instructor' | 'admin' | string;
}

/** Resolve o User.id de um aluno a partir do nome (para ações de admin em nome de terceiros).
 * Se o requester é o próprio aluno, usa o id do token sem consultar o banco. */
export async function resolveStudentUserId(studentName: string, requester: Requester): Promise<string | null> {
  if (requester.role === 'student' && requester.name === studentName) return requester.sub;
  const user = await prisma.user.findFirst({
    where: { name: studentName, role: 'student' },
    select: { id: true },
  });
  return user?.id ?? null;
}

/** Cláusula where transicional: linhas do próprio usuário por FK, OU linhas legadas (FK nula)
 * que ainda casam pelo nome. Impede que uma linha já associada a outro userId seja lida por
 * alguém que por coincidência (ou má-fé) tenha o mesmo nome de exibição. */
export function ownRowsWhere(requester: Requester) {
  return {
    OR: [
      { userId: requester.sub },
      { AND: [{ userId: null }, { studentName: requester.name }] },
    ],
  };
}

/** Checagem de posse de uma linha específica: FK primeiro, nome como fallback legado. */
export function ownsRow(row: { userId?: string | null; studentName?: string | null }, requester: Requester): boolean {
  if (row.userId) return row.userId === requester.sub;
  return row.studentName === requester.name;
}
