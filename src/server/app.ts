// Monta o Express app com toda a API (rotas, segurança, error handler), sem o bootstrap de
// listen()/Vite/estáticos — extraído à parte para poder ser testado com supertest sem precisar
// abrir uma porta de rede de verdade.
import express from 'express';
import helmet from 'helmet';
import cors from 'cors';
import { env } from './config/env';
import { corsOptions } from './config/cors';
import { globalApiLimiter } from './middlewares/rateLimiters';
import { notFoundHandler, errorHandler } from './middlewares/errorHandler';
import { uploadRouter, fileRouter, UPLOADS_PUBLIC_DIR } from './upload';
import { authRouter } from './routes/authRoutes';
import { courseRouter } from './routes/courseRoutes';
import { progressRouter, enrollmentRouter, admissionRouter } from './routes/enrollmentRoutes';
import { certificateRouter } from './routes/certificateRoutes';
import { academicRequestRouter } from './routes/requestRoutes';
import { exportRouter } from './routes/exportRoutes';
import { libraryRouter, webinarRouter } from './routes/catalogRoutes';
import {
  quizRouter,
  quizSubmissionRouter,
  forumRouter,
  exerciseRouter,
  exerciseSubmissionRouter,
} from './routes/learningRoutes';
import { chatRouter, dmRouter } from './routes/messagingRoutes';
import { settingsRouter } from './routes/settingsRoutes';
import { auditRouter } from './routes/auditRoutes';
import { devRouter } from './routes/devRoutes';
import { requireFeature } from './middlewares/featureGate';

export function createApiApp() {
  const app = express();

  app.set('trust proxy', 1);

  app.use(helmet());
  app.use(cors(corsOptions));
  app.use(express.json({ limit: '2mb' }));
  app.use('/api', globalApiLimiter);

  app.get('/api/health', (_req, res) => {
    res.json({ status: 'ok', timestamp: new Date() });
  });

  app.use('/api/auth', authRouter);

  // Cada domínio funcional é montado atrás da sua feature flag — flag desligada significa
  // rota desligada (404 FEATURE_DISABLED), não apenas botão escondido no frontend.
  app.use('/api/upload', requireFeature('uploadArquivos'), uploadRouter);
  // Estático serve APENAS a subpasta pública; arquivos privados só saem via /api/files (autorizado).
  app.use('/uploads', express.static(UPLOADS_PUBLIC_DIR));
  app.use('/api/files', requireFeature('uploadArquivos'), fileRouter);

  app.use('/api/courses', requireFeature('catalogoCursos'), courseRouter);
  app.use('/api/progress', requireFeature('progresso'), progressRouter);
  app.use('/api/enrollments', requireFeature('matricula'), enrollmentRouter);
  app.use('/api/admissions', requireFeature('matricula'), admissionRouter);
  app.use('/api/certificates', requireFeature('certificados'), certificateRouter);
  app.use('/api/academic-requests', requireFeature('solicitacoesAcademicas'), academicRequestRouter);
  app.use('/api/export', requireFeature('dadosGerenciais'), exportRouter);
  app.use('/api/library', requireFeature('materiaisComplementares'), libraryRouter);
  app.use('/api/webinars', requireFeature('eventosWebinars'), webinarRouter);
  app.use('/api/quizzes', requireFeature('quizSimples'), quizRouter);
  app.use('/api/quiz-submissions', requireFeature('quizSimples'), quizSubmissionRouter);
  app.use('/api/forum', requireFeature('forum'), forumRouter);
  app.use('/api/exercises', requireFeature('atividadesPraticasAvancadas'), exerciseRouter);
  app.use('/api/exercise-submissions', requireFeature('atividadesPraticasAvancadas'), exerciseSubmissionRouter);
  app.use('/api/chat', requireFeature('liveClassroom'), chatRouter);
  app.use('/api/dms', requireFeature('mensagensDiretas'), dmRouter);
  app.use('/api/system-settings', settingsRouter);
  app.use('/api/security-logs', auditRouter);

  if (!env.isProduction) {
    app.use('/api/dev', devRouter);
  }

  app.use('/api', notFoundHandler);

  return app;
}

/** Deve ser chamado por último, depois de qualquer middleware adicional (Vite/estáticos/SPA
 * fallback) — um error handler do Express só enxerga erros de rotas registradas ANTES dele. */
export function attachErrorHandler(app: express.Express) {
  app.use(errorHandler);
}
