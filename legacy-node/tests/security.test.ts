// Testes de segurança do backend do AVASEC. Rodam contra o banco de desenvolvimento real
// (Docker) através do app Express montado em memória (supertest), sem precisar abrir uma porta.
//
// Pré-requisito: MySQL rodando (`npm run db:up`) e populado (`npm run db:seed`) — os testes
// usam os usuários demo do seed (João Silva, Gestor de Conteúdos, Admin Superior) e criam
// contas descartáveis com e-mail único a cada execução para os cenários de status de conta.
//
// Nota: os tokens de login são obtidos UMA VEZ em beforeAll e reaproveitados em todos os
// testes — o rate limit de login (10 tentativas/15min por IP) é real e compartilhado entre
// os testes deste arquivo, então logar de novo a cada `it` esgotaria o limite no meio da suíte.
import { describe, it, expect, beforeAll } from 'vitest';
import request from 'supertest';
import { createApiApp, attachErrorHandler } from '../src/server/app';
import { prisma } from '../src/server/prisma';

const app = createApiApp();
attachErrorHandler(app);

async function loginAs(name: string, password: string) {
  const res = await request(app).post('/api/auth/login').send({ name, password });
  if (!res.body.token) {
    throw new Error(`Login de teste falhou para "${name}": ${res.status} ${JSON.stringify(res.body)}`);
  }
  return { status: res.status, token: res.body.token as string, user: res.body.user };
}

let studentToken: string;
let instructorToken: string;
let adminToken: string;

beforeAll(async () => {
  const student = await loginAs('João Silva', '1234');
  const instructor = await loginAs('Gestor de Conteúdos', '5678');
  const admin = await loginAs('Admin Superior', '9999');
  studentToken = student.token;
  instructorToken = instructor.token;
  adminToken = admin.token;
});

describe('Autenticação e status de conta', () => {
  it('recusa rota interna sem token (401)', async () => {
    const res = await request(app).get('/api/progress');
    expect(res.status).toBe(401);
    expect(res.body).toMatchObject({ error: true, code: 'UNAUTHORIZED' });
  });

  it('login com senha errada retorna mensagem genérica (401)', async () => {
    const res = await request(app).post('/api/auth/login').send({ name: 'João Silva', password: 'senha-errada-unica' });
    expect(res.status).toBe(401);
    expect(res.body.message).toBe('Usuário ou senha inválidos.');
  });

  it('cadastro público (pending_confirmation) NÃO recebe access token e login também não emite', async () => {
    const email = `pendente-${Date.now()}@example.com`;
    const register = await request(app)
      .post('/api/auth/register')
      .send({ name: 'Aluno Pendente Teste', email, password: 'senha123456' });
    expect(register.body.user.status).toBe('pending_confirmation');
    expect(register.body.token).toBeNull();

    // Login com a senha CORRETA de conta pendente: 403 institucional, sem token.
    const login = await request(app).post('/api/auth/login').send({ email, password: 'senha123456' });
    expect(login.status).toBe(403);
    expect(login.body.code).toBe('ACCOUNT_PENDING_CONFIRMATION');
    expect(login.body.token).toBeUndefined();
  });

  it('usuário com status blocked não recebe token no login e token antigo não acessa área interna', async () => {
    const email = `bloqueado-${Date.now()}@example.com`;
    const name = `Aluno Bloqueado Teste ${Date.now()}`;
    const register = await request(app)
      .post('/api/auth/register')
      .set('Authorization', `Bearer ${adminToken}`) // provisionado por admin -> nasce 'active'
      .send({ name, email, password: 'senha123456', role: 'student' });
    expect(register.body.user.status).toBe('active');
    // Provisionamento por admin não entrega a credencial da conta de terceiro.
    expect(register.body.token).toBeNull();

    // Token legítimo obtido ENQUANTO a conta estava ativa (cenário real de bloqueio posterior).
    const activeLogin = await request(app).post('/api/auth/login').send({ email, password: 'senha123456' });
    expect(activeLogin.status).toBe(200);
    const oldToken = activeLogin.body.token as string;

    await request(app)
      .put(`/api/auth/users/${register.body.user.id}/status`)
      .set('Authorization', `Bearer ${adminToken}`)
      .send({ status: 'blocked' })
      .expect(200);

    // Novo login (senha correta): 403 institucional, sem token novo.
    const blockedLogin = await request(app).post('/api/auth/login').send({ email, password: 'senha123456' });
    expect(blockedLogin.status).toBe(403);
    expect(blockedLogin.body.code).toBe('ACCOUNT_BLOCKED');
    expect(blockedLogin.body.token).toBeUndefined();

    // Token antigo (emitido antes do bloqueio) também é barrado pelo requireActiveAccount.
    const res = await request(app).get('/api/progress').set('Authorization', `Bearer ${oldToken}`);
    expect(res.status).toBe(403);
    expect(res.body.code).toBe('ACCOUNT_BLOCKED');
  });

  it('cadastro público não pode se auto-promover a admin/instrutor', async () => {
    const res = await request(app)
      .post('/api/auth/register')
      .send({ name: 'Tentativa Escalada', email: `escalada-${Date.now()}@example.com`, password: 'senha123456', role: 'admin' });
    expect(res.status).toBe(403);
  });
});

describe('Controle de acesso por perfil', () => {
  it('aluno não acessa dados (progresso) de outro aluno', async () => {
    const res = await request(app)
      .get('/api/progress?studentName=' + encodeURIComponent('Gabriel Rodrigues'))
      .set('Authorization', `Bearer ${studentToken}`);
    expect(res.status).toBe(403);
  });

  it('aluno não lista dados de outros alunos', async () => {
    const res = await request(app).get('/api/auth/users?role=student').set('Authorization', `Bearer ${studentToken}`);
    expect(res.status).toBe(403);
  });

  it('instrutor não edita/exclui curso não vinculado a ele', async () => {
    const email = `outro-instrutor-${Date.now()}@example.com`;
    const name = `Outro Instrutor ${Date.now()}`;
    await request(app)
      .post('/api/auth/register')
      .set('Authorization', `Bearer ${adminToken}`)
      .send({ name, email, password: 'senha123456', role: 'instructor' });
    const otherInstructorLogin = await loginAs(name, 'senha123456');
    const otherInstructorToken = otherInstructorLogin.token;

    // course-1 pertence a "Gestor de Conteúdos" no seed, não ao instrutor recém-criado.
    const updateAttempt = await request(app)
      .put('/api/courses/course-1')
      .set('Authorization', `Bearer ${otherInstructorToken}`)
      .send({ title: 'Tentativa de alteração indevida' });
    expect(updateAttempt.status).toBe(403);

    const deleteAttempt = await request(app)
      .delete('/api/courses/course-1')
      .set('Authorization', `Bearer ${otherInstructorToken}`);
    expect(deleteAttempt.status).toBe(403);

    const course = await prisma.course.findUnique({ where: { id: 'course-1' } });
    expect(course?.title).not.toBe('Tentativa de alteração indevida');
  });

  it('admin consegue exportar Dados Gerenciais', async () => {
    const res = await request(app).get('/api/export/students').set('Authorization', `Bearer ${adminToken}`);
    expect(res.status).toBe(200);
    expect(Array.isArray(res.body.data)).toBe(true);
    expect(res.body.data[0]).not.toHaveProperty('passwordHash');
  });

  it('aluno NÃO consegue exportar Dados Gerenciais', async () => {
    const res = await request(app).get('/api/export/students').set('Authorization', `Bearer ${studentToken}`);
    expect(res.status).toBe(403);
  });

  it('instrutor também não consegue exportar Dados Gerenciais', async () => {
    const res = await request(app).get('/api/export/students').set('Authorization', `Bearer ${instructorToken}`);
    expect(res.status).toBe(403);
  });
});

describe('Regras de negócio críticas', () => {
  it('matrícula duplicada é impedida (409)', async () => {
    const email = `aluno-matricula-${Date.now()}@example.com`;
    const name = `Aluno Matricula ${Date.now()}`;
    await request(app)
      .post('/api/auth/register')
      .set('Authorization', `Bearer ${adminToken}`)
      .send({ name, email, password: 'senha123456', role: 'student' });
    const createdLogin = await loginAs(name, 'senha123456');
    const token = createdLogin.token;

    const body = { studentName: name, courseId: 'course-2' };
    const first = await request(app).post('/api/admissions').set('Authorization', `Bearer ${token}`).send(body);
    expect([200, 201]).toContain(first.status);

    const duplicate = await request(app).post('/api/admissions').set('Authorization', `Bearer ${token}`).send(body);
    expect(duplicate.status).toBe(409);
  });

  it('certificado só é emitido quando o critério de frequência é atingido', async () => {
    // course-3 (Metodologias Ágeis) não tem progresso registrado para João Silva no seed.
    const res = await request(app)
      .post('/api/certificates')
      .set('Authorization', `Bearer ${studentToken}`)
      .send({ studentName: 'João Silva', courseId: 'course-3' });
    expect(res.status).toBe(403);
    expect(res.body.message).toMatch(/frequência/i);
  });

  it('aluno não emite certificado em nome de outro aluno', async () => {
    const res = await request(app)
      .post('/api/certificates')
      .set('Authorization', `Bearer ${studentToken}`)
      .send({ studentName: 'Gabriel Rodrigues', courseId: 'course-1' });
    expect(res.status).toBe(403);
  });
});

describe('Validação de entrada', () => {
  it('rejeita e-mail inválido no cadastro', async () => {
    const res = await request(app)
      .post('/api/auth/register')
      .send({ name: 'Teste', email: 'nao-e-email', password: 'senha123456' });
    expect(res.status).toBe(400);
    expect(res.body.code).toBe('VALIDATION_ERROR');
  });

  it('rejeita senha curta no cadastro', async () => {
    const res = await request(app)
      .post('/api/auth/register')
      .send({ name: 'Teste', email: `curta-${Date.now()}@example.com`, password: '123' });
    expect(res.status).toBe(400);
  });
});

describe('Formato padronizado de erro', () => {
  it('rota inexistente responde 404 no formato padrão', async () => {
    const res = await request(app).get('/api/rota-que-nao-existe');
    expect(res.status).toBe(404);
    expect(res.body).toMatchObject({ error: true, code: 'NOT_FOUND' });
  });

  it('nunca inclui stack trace na resposta de erro', async () => {
    const res = await request(app).get('/api/rota-que-nao-existe');
    expect(res.body).not.toHaveProperty('stack');
  });
});
