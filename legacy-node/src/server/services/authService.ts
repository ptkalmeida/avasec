// Regras de negócio de autenticação: hash/verificação de senha, bloqueio por tentativas
// repetidas, emissão de JWT e regras de status de conta na criação de usuários.
import bcrypt from 'bcryptjs';
import { prisma } from '../prisma';
import { signToken } from '../middlewares/auth';
import { ApiError, Errors } from '../utils/ApiError';
import type { AuthTokenPayload } from '../middlewares/auth';

const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;

function toPublicUser(user: {
  id: string;
  name: string;
  email: string;
  role: string;
  status: string;
  municipio?: string | null;
  uf?: string | null;
  areaInteresse?: string | null;
  dataCadastro?: string | null;
}) {
  return {
    id: user.id,
    name: user.name,
    email: user.email,
    role: user.role,
    status: user.status,
    municipio: user.municipio ?? undefined,
    uf: user.uf ?? undefined,
    areaInteresse: user.areaInteresse ?? undefined,
    dataCadastro: user.dataCadastro ?? undefined,
  };
}

interface RegisterInput {
  name: string;
  email: string;
  password: string;
  role?: 'student' | 'instructor' | 'admin';
  cpf?: string;
  municipio?: string;
  uf?: string;
  areaInteresse?: string;
  dataCadastro?: string;
}

/**
 * Regra de provisionamento de conta:
 * - Cadastro público (sem admin autenticado): sempre vira 'student', status 'pending_confirmation'
 *   (aguarda homologação da coordenação — não há hoje um fluxo de confirmação por e-mail).
 * - Cadastro feito por um admin autenticado: qualquer papel é permitido, status 'active'
 *   (o admin já está vouching pela conta).
 * - Cadastro de instrutor/admin por qualquer outra via é rejeitado.
 */
export async function registerUser(input: RegisterInput, requester?: AuthTokenPayload) {
  const isAdminProvisioning = requester?.role === 'admin';

  if (!isAdminProvisioning && input.role && input.role !== 'student') {
    throw Errors.forbidden('Apenas administradores podem criar contas de instrutor ou administrador.');
  }

  const existing = await prisma.user.findUnique({ where: { email: input.email } });
  if (existing) {
    throw Errors.conflict('Já existe um usuário cadastrado com este e-mail.');
  }

  const passwordHash = await bcrypt.hash(input.password, 10);
  const user = await prisma.user.create({
    data: {
      name: input.name,
      email: input.email,
      passwordHash,
      role: isAdminProvisioning ? input.role ?? 'student' : 'student',
      status: isAdminProvisioning ? 'active' : 'pending_confirmation',
      cpf: input.cpf,
      municipio: input.municipio,
      uf: input.uf,
      areaInteresse: input.areaInteresse,
      dataCadastro: input.dataCadastro ?? new Date().toISOString().split('T')[0],
    },
  });

  // Access token só é emitido para a PRÓPRIA pessoa quando a conta nasce ativa.
  // - Cadastro público: conta nasce pending_confirmation -> nenhum token (aguarda homologação).
  // - Provisionamento por admin: a conta é de terceiro -> o admin não recebe credencial dela.
  const token =
    user.status === 'active' && !isAdminProvisioning
      ? signToken({ sub: user.id, name: user.name, role: user.role as any })
      : null;
  return { token, user: toPublicUser(user) };
}

interface LoginInput {
  name?: string;
  email?: string;
  password: string;
}

export async function loginUser(input: LoginInput) {
  const user = input.email
    ? await prisma.user.findUnique({ where: { email: input.email } })
    : await prisma.user.findFirst({ where: { name: input.name } });

  // Mensagem genérica sempre — nunca revela se o e-mail/nome existe ou não.
  const invalidCredentials = () => Errors.unauthorized('Usuário ou senha inválidos.');

  if (!user) {
    throw invalidCredentials();
  }

  if (user.lockedUntil && user.lockedUntil > new Date()) {
    throw new ApiError(
      429,
      'ACCOUNT_LOCKED',
      'Conta temporariamente bloqueada após várias tentativas de login. Tente novamente em alguns minutos.'
    );
  }

  const passwordValid = await bcrypt.compare(input.password, user.passwordHash);

  if (!passwordValid) {
    const nextAttempts = user.failedLoginAttempts + 1;
    const shouldLock = nextAttempts >= MAX_FAILED_ATTEMPTS;
    await prisma.user.update({
      where: { id: user.id },
      data: {
        failedLoginAttempts: shouldLock ? 0 : nextAttempts,
        lockedUntil: shouldLock ? new Date(Date.now() + LOCKOUT_MINUTES * 60 * 1000) : null,
      },
    });
    throw invalidCredentials();
  }

  // Login válido: zera o contador de tentativas.
  if (user.failedLoginAttempts > 0 || user.lockedUntil) {
    await prisma.user.update({ where: { id: user.id }, data: { failedLoginAttempts: 0, lockedUntil: null } });
  }

  // Senha correta, mas conta sem acesso liberado: NÃO emite access token.
  // A resposta distingue o motivo (institucional) — aqui não há vazamento de credencial,
  // pois este ramo só é alcançado após a senha ter sido validada.
  if (user.status === 'blocked') {
    throw new ApiError(
      403,
      'ACCOUNT_BLOCKED',
      'Seu acesso à Escola Estadual da Cultura foi suspenso. Entre em contato com a coordenação para mais informações.'
    );
  }
  if (user.status === 'pending_confirmation') {
    throw new ApiError(
      403,
      'ACCOUNT_PENDING_CONFIRMATION',
      'Seu cadastro ainda está aguardando confirmação da coordenação. Você será liberado para acessar a plataforma assim que for homologado.'
    );
  }

  const token = signToken({ sub: user.id, name: user.name, role: user.role as any });
  return { token, user: toPublicUser(user) };
}

export async function getCurrentUser(userId: string) {
  const user = await prisma.user.findUnique({ where: { id: userId } });
  if (!user) throw Errors.notFound('Usuário não encontrado.');
  return toPublicUser(user);
}

export async function changePassword(userId: string, newPassword: string, currentPassword?: string) {
  const user = await prisma.user.findUnique({ where: { id: userId } });
  if (!user) throw Errors.notFound('Usuário não encontrado.');

  if (currentPassword) {
    const valid = await bcrypt.compare(currentPassword, user.passwordHash);
    if (!valid) throw Errors.unauthorized('Senha atual incorreta.');
  }

  const passwordHash = await bcrypt.hash(newPassword, 10);
  await prisma.user.update({ where: { id: user.id }, data: { passwordHash } });
}

const USER_LIST_SELECT = {
  id: true, name: true, email: true, role: true, status: true,
  municipio: true, uf: true, areaInteresse: true, dataCadastro: true,
} as const;

export async function listUsersByRole(role: string | undefined, skip: number, take: number) {
  const where = role ? { role: role as any } : undefined;
  const [items, total] = await Promise.all([
    prisma.user.findMany({ where, skip, take, orderBy: { createdAt: 'asc' }, select: USER_LIST_SELECT }),
    prisma.user.count({ where }),
  ]);
  return { items, total };
}

/** Regra de escopo por perfil: instrutor só vê alunos matriculados/admitidos em cursos
 * que ele próprio leciona (não a base inteira de alunos da escola).
 * Identifica os cursos por FK (instructorId) com fallback por nome para cursos legados,
 * e os alunos por FK (userId) com fallback por nome para registros legados. */
export async function listStudentsForInstructor(
  instructor: { sub: string; name: string },
  skip: number,
  take: number
) {
  const instructorCourses = await prisma.course.findMany({
    where: {
      OR: [
        { instructorId: instructor.sub },
        { AND: [{ instructorId: null }, { instructorName: instructor.name }] },
      ],
    },
    select: { id: true },
  });
  const courseIds = instructorCourses.map((c) => c.id);

  if (courseIds.length === 0) {
    return { items: [], total: 0 };
  }

  const [admissions, enrollments] = await Promise.all([
    prisma.admissionRequest.findMany({
      where: { courseId: { in: courseIds }, status: 'approved' },
      select: { studentName: true, userId: true },
    }),
    prisma.studentEnrollment.findMany({
      where: { enrolledCourseId: { in: courseIds } },
      select: { studentName: true, userId: true },
    }),
  ]);

  const linked = [...admissions, ...enrollments];
  const studentIds = Array.from(new Set(linked.map((r) => r.userId).filter((id): id is string => !!id)));
  const legacyNames = Array.from(new Set(linked.filter((r) => !r.userId).map((r) => r.studentName)));

  if (studentIds.length === 0 && legacyNames.length === 0) {
    return { items: [], total: 0 };
  }

  const where = {
    role: 'student' as const,
    OR: [
      ...(studentIds.length ? [{ id: { in: studentIds } }] : []),
      ...(legacyNames.length ? [{ name: { in: legacyNames } }] : []),
    ],
  };
  const [items, total] = await Promise.all([
    prisma.user.findMany({ where, skip, take, orderBy: { createdAt: 'asc' }, select: USER_LIST_SELECT }),
    prisma.user.count({ where }),
  ]);
  return { items, total };
}

export async function updateAccountStatus(userId: string, status: 'active' | 'blocked' | 'pending_confirmation') {
  const user = await prisma.user.update({ where: { id: userId }, data: { status } }).catch(() => null);
  if (!user) throw Errors.notFound('Usuário não encontrado.');
  return toPublicUser(user);
}

export async function deleteUser(userId: string) {
  await prisma.user.delete({ where: { id: userId } }).catch(() => null);
}

export { toPublicUser };
