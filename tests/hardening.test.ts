// Testes das garantias introduzidas no hardening de prontidão para produção:
// FKs reais na autorização, uploads público/privado, feature flags no backend,
// emissão de token por status, cookie HttpOnly, telemetria separada da auditoria
// e regra de penalidade de rematrícula no servidor.
// Pré-requisito: MySQL de desenvolvimento rodando e seedado (npm run db:up && npm run db:seed).
import { describe, it, expect, beforeAll } from 'vitest';
import request from 'supertest';
import { createApiApp, attachErrorHandler } from '../src/server/app';
import { prisma } from '../src/server/prisma';
import { features } from '../src/config/features';
import { signToken } from '../src/server/middlewares/auth';

// Assina um token diretamente para contas descartáveis criadas nos testes — evita esbarrar
// no rate limit de login (10/15min), que é intencionalmente real nesta suite.
function tokenFor(user: { id: string; name: string; role: string }) {
  return signToken({ sub: user.id, name: user.name, role: user.role as any });
}

const app = createApiApp();
attachErrorHandler(app);

// Mini-PDF válido (magic bytes %PDF) para os testes de upload.
const REAL_PDF = Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF');

async function loginAs(name: string, password: string) {
  const res = await request(app).post('/api/auth/login').send({ name, password });
  if (!res.body.token) {
    throw new Error(`Login de teste falhou para "${name}": ${res.status} ${JSON.stringify(res.body)}`);
  }
  return res.body.token as string;
}

let studentToken: string;
let instructorToken: string;
let adminToken: string;

beforeAll(async () => {
  studentToken = await loginAs('João Silva', '1234');
  instructorToken = await loginAs('Gestor de Conteúdos', 'prof1234');
  adminToken = await loginAs('Admin Superior', 'admin1234');
});

describe('Sessão via cookie HttpOnly', () => {
  it('login seta cookie ava_session com HttpOnly e SameSite=Lax', async () => {
    const res = await request(app).post('/api/auth/login').send({ name: 'João Silva', password: '1234' });
    const setCookie = res.headers['set-cookie']?.[0] ?? '';
    expect(setCookie).toContain('ava_session=');
    expect(setCookie).toContain('HttpOnly');
    expect(setCookie).toContain('SameSite=Lax');
  });

  it('requisição autenticada funciona só com o cookie (sem header Authorization)', async () => {
    const agent = request.agent(app);
    await agent.post('/api/auth/login').send({ name: 'João Silva', password: '1234' }).expect(200);
    const me = await agent.get('/api/auth/me');
    expect(me.status).toBe(200);
    expect(me.body.name).toBe('João Silva');
  });

  it('logout limpa o cookie de sessão', async () => {
    const agent = request.agent(app);
    await agent.post('/api/auth/login').send({ name: 'João Silva', password: '1234' }).expect(200);
    await agent.post('/api/auth/logout').expect(200);
    const me = await agent.get('/api/auth/me');
    expect(me.status).toBe(401);
  });
});

describe('Feature flags no backend', () => {
  it('rota de feature desligada responde 404 FEATURE_DISABLED (não apenas some do menu)', async () => {
    // No MVP atual, forum/dms/exercícios estão desligados.
    expect(features.forum).toBe(false);
    const forum = await request(app).get('/api/forum').set('Authorization', `Bearer ${studentToken}`);
    expect(forum.status).toBe(404);
    expect(forum.body.code).toBe('FEATURE_DISABLED');

    const dms = await request(app).get('/api/dms').set('Authorization', `Bearer ${studentToken}`);
    expect(dms.status).toBe(404);
    expect(dms.body.code).toBe('FEATURE_DISABLED');
  });

  it('rota de feature ligada continua funcionando', async () => {
    const res = await request(app).get('/api/courses');
    expect(res.status).toBe(200);
  });
});

describe('Uploads público vs privado com download autorizado', () => {
  it('upload público retorna URL estática e o arquivo fica acessível', async () => {
    const res = await request(app)
      .post('/api/upload?visibility=public')
      .set('Authorization', `Bearer ${instructorToken}`)
      .attach('file', REAL_PDF, 'material.pdf');
    expect(res.status).toBe(201);
    expect(res.body.url).toMatch(/^\/uploads\//);
    expect(res.body.visibility).toBe('public');
  });

  it('upload privado: dono baixa, outro aluno recebe 403, instrutor baixa, anônimo 401', async () => {
    const up = await request(app)
      .post('/api/upload?visibility=private')
      .set('Authorization', `Bearer ${studentToken}`)
      .attach('file', REAL_PDF, 'entrega.pdf');
    expect(up.status).toBe(201);
    expect(up.body.url).toMatch(/^\/api\/files\//);

    const fileUrl = up.body.url as string;

    const owner = await request(app).get(fileUrl).set('Authorization', `Bearer ${studentToken}`);
    expect(owner.status).toBe(200);
    expect(owner.headers['content-disposition']).toContain('attachment');

    const otherStudentToken = await loginAs('Gabriel Rodrigues', '1234');
    const intruder = await request(app).get(fileUrl).set('Authorization', `Bearer ${otherStudentToken}`);
    expect(intruder.status).toBe(403);

    const staff = await request(app).get(fileUrl).set('Authorization', `Bearer ${instructorToken}`);
    expect(staff.status).toBe(200);

    const anon = await request(app).get(fileUrl);
    expect(anon.status).toBe(401);
  });

  it('arquivo privado não é servido pela rota estática pública', async () => {
    const up = await request(app)
      .post('/api/upload?visibility=private')
      .set('Authorization', `Bearer ${studentToken}`)
      .attach('file', REAL_PDF, 'privado.pdf');
    const fileName = (up.body.url as string).split('/').pop();
    const viaStatic = await request(app).get(`/uploads/${fileName}`);
    expect(viaStatic.status).toBe(404);
  });
});

describe('Ownership por FK (userId) — homônimos não vazam dados', () => {
  it('dois alunos com o MESMO nome não enxergam o progresso um do outro', async () => {
    const suffix = Date.now();
    const name = 'Homonimo Teste';
    // Duas contas ativas com o mesmo nome de exibição (e-mails distintos), criadas por admin.
    await request(app)
      .post('/api/auth/register')
      .set('Authorization', `Bearer ${adminToken}`)
      .send({ name, email: `homonimo-a-${suffix}@example.com`, password: 'senha123456', role: 'student' })
      .expect(201);
    await request(app)
      .post('/api/auth/register')
      .set('Authorization', `Bearer ${adminToken}`)
      .send({ name, email: `homonimo-b-${suffix}@example.com`, password: 'senha123456', role: 'student' })
      .expect(201);

    const tokenA = (await request(app).post('/api/auth/login').send({ email: `homonimo-a-${suffix}@example.com`, password: 'senha123456' })).body.token;
    const tokenB = (await request(app).post('/api/auth/login').send({ email: `homonimo-b-${suffix}@example.com`, password: 'senha123456' })).body.token;

    // A registra progresso no course-3.
    await request(app)
      .post('/api/progress')
      .set('Authorization', `Bearer ${tokenA}`)
      .send({ studentName: name, courseId: 'course-3', completedLessons: ['lesson-3-1'], attendedLiveSessions: [] })
      .expect(200);

    // A vê a própria linha (associada por FK ao seu userId).
    const seenByA = await request(app).get('/api/progress').set('Authorization', `Bearer ${tokenA}`);
    expect(seenByA.body.some((p: any) => p.courseId === 'course-3')).toBe(true);

    // B, apesar do mesmo nome, NÃO vê a linha de A.
    const seenByB = await request(app).get('/api/progress').set('Authorization', `Bearer ${tokenB}`);
    expect(seenByB.body.some((p: any) => p.courseId === 'course-3')).toBe(false);
  });
});

describe('Certificado com FKs e critério no servidor', () => {
  it('emissão grava userId e enrollmentId; re-emissão é idempotente', async () => {
    const suffix = Date.now();
    const name = `Aluno Cert FK ${suffix}`;
    const email = `certfk-${suffix}@example.com`;
    await request(app)
      .post('/api/auth/register')
      .set('Authorization', `Bearer ${adminToken}`)
      .send({ name, email, password: 'senha123456', role: 'student' })
      .expect(201);
    const created = await prisma.user.findUnique({ where: { email } });
    const token = tokenFor(created!);

    // Matricula-se e completa todas as atividades do course-3 (3 aulas + 1 live no seed).
    await request(app).post('/api/enrollments/self/enroll').set('Authorization', `Bearer ${token}`).send({ courseId: 'course-3' }).expect(200);

    const course = await prisma.course.findUnique({ where: { id: 'course-3' }, include: { lessons: true, liveSessions: true } });
    await request(app)
      .post('/api/progress')
      .set('Authorization', `Bearer ${token}`)
      .send({
        studentName: name,
        courseId: 'course-3',
        completedLessons: course!.lessons.map((l) => l.id),
        attendedLiveSessions: course!.liveSessions.map((s) => s.id),
      })
      .expect(200);

    const issued = await request(app)
      .post('/api/certificates')
      .set('Authorization', `Bearer ${token}`)
      .send({ studentName: name, courseId: 'course-3' });
    expect(issued.status).toBe(201);
    expect(issued.body.userId).toBeTruthy();
    expect(issued.body.enrollmentId).toBeTruthy();

    // Idempotência: segunda emissão retorna o MESMO certificado.
    const again = await request(app)
      .post('/api/certificates')
      .set('Authorization', `Bearer ${token}`)
      .send({ studentName: name, courseId: 'course-3' });
    expect(again.body.id).toBe(issued.body.id);
  });
});

describe('Exportação de Dados Gerenciais (dados para o CSV)', () => {
  it('dataset courses inclui percentual mínimo e área temática; progress inclui FKs', async () => {
    const courses = await request(app).get('/api/export/courses').set('Authorization', `Bearer ${adminToken}`);
    expect(courses.status).toBe(200);
    expect(courses.body.data[0]).toHaveProperty('minAttendance');
    expect(courses.body.data[0]).toHaveProperty('areaTematica');

    const progress = await request(app).get('/api/export/progress').set('Authorization', `Bearer ${adminToken}`);
    expect(progress.status).toBe(200);
    expect(progress.body.data[0]).toHaveProperty('userId');
    expect(progress.body.data[0]).toHaveProperty('enrollmentId');
  });
});

describe('Telemetria separada da auditoria', () => {
  it('POST /api/security-logs não existe mais (auditoria é só do servidor)', async () => {
    const res = await request(app)
      .post('/api/security-logs')
      .set('Authorization', `Bearer ${adminToken}`)
      .send({ action: 'Forjado', details: 'tentativa de escrever na trilha de auditoria' });
    expect(res.status).toBe(404);
  });

  it('telemetria aceita evento anônimo mas registra identidade do token quando houver', async () => {
    const anon = await request(app).post('/api/telemetry').send({ action: 'Navegação', details: 'visita à landing page' });
    expect(anon.status).toBe(201);
    expect(anon.body.user).toBe('Visitante Anônimo');

    const authed = await request(app)
      .post('/api/telemetry')
      .set('Authorization', `Bearer ${studentToken}`)
      // O corpo TENTA se passar por admin — a identidade gravada vem do token.
      .send({ action: 'Narração', details: 'leitor de tela ativado', user: 'Admin Superior', role: 'admin' } as any);
    expect(authed.status).toBe(201);
    expect(authed.body.user).toBe('João Silva');
    expect(authed.body.role).toBe('student');
  });

  it('leitura de telemetria é admin-only', async () => {
    const asStudent = await request(app).get('/api/telemetry').set('Authorization', `Bearer ${studentToken}`);
    expect(asStudent.status).toBe(403);
    const asAdmin = await request(app).get('/api/telemetry').set('Authorization', `Bearer ${adminToken}`);
    expect(asAdmin.status).toBe(200);
    expect(Array.isArray(asAdmin.body.items)).toBe(true);
  });
});

describe('Matrícula self com regra no servidor', () => {
  it('matrícula duplicada em curso ativo é recusada (409) e flag OFF não gera penalidade no drop', async () => {
    expect(features.penalidadesCancelamento).toBe(false);

    const suffix = Date.now();
    const name = `Aluno Matricula Self ${suffix}`;
    const email = `matself-${suffix}@example.com`;
    await request(app)
      .post('/api/auth/register')
      .set('Authorization', `Bearer ${adminToken}`)
      .send({ name, email, password: 'senha123456', role: 'student' })
      .expect(201);
    const created = await prisma.user.findUnique({ where: { email } });
    const token = tokenFor(created!);

    await request(app).post('/api/enrollments/self/enroll').set('Authorization', `Bearer ${token}`).send({ courseId: 'course-1' }).expect(200);

    const dup = await request(app).post('/api/enrollments/self/enroll').set('Authorization', `Bearer ${token}`).send({ courseId: 'course-2' });
    expect(dup.status).toBe(409);

    // Simula matrícula antiga (12 dias) DIRETO no banco — o cliente não controla isso.
    await prisma.studentEnrollment.update({
      where: { studentName: name },
      data: { enrolledAt: new Date(Date.now() - 12 * 24 * 60 * 60 * 1000).toISOString() },
    });

    const drop = await request(app).post('/api/enrollments/self/drop').set('Authorization', `Bearer ${token}`).send({ courseId: 'course-1' });
    expect(drop.status).toBe(200);
    // Flag penalidadesCancelamento OFF -> nenhuma restrição, mesmo após 12 dias.
    expect(drop.body.penaltyApplied).toBe(false);
    expect(drop.body.enrollment.dropOutPenaltyUntil).toBeNull();

    // E pode se matricular de novo imediatamente.
    await request(app).post('/api/enrollments/self/enroll').set('Authorization', `Bearer ${token}`).send({ courseId: 'course-2' }).expect(200);
  });

  it('conclusão sem critério de frequência é recusada (403)', async () => {
    const suffix = Date.now();
    const name = `Aluno Conclusao ${suffix}`;
    const email = `conclusao-${suffix}@example.com`;
    await request(app)
      .post('/api/auth/register')
      .set('Authorization', `Bearer ${adminToken}`)
      .send({ name, email, password: 'senha123456', role: 'student' })
      .expect(201);
    const created = await prisma.user.findUnique({ where: { email } });
    const token = tokenFor(created!);

    await request(app).post('/api/enrollments/self/enroll').set('Authorization', `Bearer ${token}`).send({ courseId: 'course-1' }).expect(200);
    const res = await request(app).post('/api/enrollments/self/complete').set('Authorization', `Bearer ${token}`).send({ courseId: 'course-1' });
    expect(res.status).toBe(403);
    expect(res.body.message).toMatch(/frequência/i);
  });
});
