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
  app.use('/api/upload', uploadRouter);
  // Estático serve APENAS a subpasta pública; arquivos privados só saem via /api/files (autorizado).
  app.use('/uploads', express.static(UPLOADS_PUBLIC_DIR));
  app.use('/api/files', fileRouter);

  app.use('/api/courses', courseRouter);
  app.use('/api/progress', progressRouter);
  app.use('/api/enrollments', enrollmentRouter);
  app.use('/api/admissions', admissionRouter);
  app.use('/api/certificates', certificateRouter);
  app.use('/api/academic-requests', academicRequestRouter);
  app.use('/api/export', exportRouter);
  app.use('/api/library', libraryRouter);
  app.use('/api/webinars', webinarRouter);
  app.use('/api/quizzes', quizRouter);
  app.use('/api/quiz-submissions', quizSubmissionRouter);
  app.use('/api/forum', forumRouter);
  app.use('/api/exercises', exerciseRouter);
  app.use('/api/exercise-submissions', exerciseSubmissionRouter);
  app.use('/api/chat', chatRouter);
  app.use('/api/dms', dmRouter);
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
