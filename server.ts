import express from 'express';
import path from 'path';
import { createServer as createViteServer } from 'vite';
import { Course, LibraryItem, WebinarEvent, StudentProgress, Certificate, ChatMessage, DirectMessage, AcademicRequest, AdmissionRequest, SecurityLog } from './src/types';
import { INITIAL_COURSES, INITIAL_LIBRARY, INITIAL_WEBINARS } from './src/data/mockData';

async function startServer() {
  const app = express();
  const PORT = 3000;

  // Middleware to parse JSON
  app.use(express.json());

  // In-memory persistent database store initialized with our rich mock data
  let db = {
    courses: [...INITIAL_COURSES] as Course[],
    libraryItems: [...INITIAL_LIBRARY] as any[] as LibraryItem[],
    webinarEvents: [...INITIAL_WEBINARS] as WebinarEvent[],
    progress: [] as StudentProgress[],
    certificates: [] as Certificate[],
    chatMessages: [] as ChatMessage[],
    directMessages: [] as DirectMessage[],
    academicRequests: [] as AcademicRequest[],
    admissionRequests: [] as AdmissionRequest[],
    securityLogs: [
      {
        id: 'log-1',
        timestamp: new Date(Date.now() - 3600000 * 5).toLocaleTimeString('pt-BR') + ' ' + new Date(Date.now() - 3600000 * 5).toLocaleDateString('pt-BR'),
        user: 'Admin Superior',
        role: 'admin',
        ipAddress: '192.168.1.14',
        device: 'Chrome / macOS (Sistema Autenticado)',
        action: 'Auditoria de Sistema',
        details: 'Geração de relatório geral de matrículas ativas na Escola da Cultura.',
        status: 'SUCCESS' as const
      },
      {
        id: 'log-2',
        timestamp: new Date(Date.now() - 3600000 * 3).toLocaleTimeString('pt-BR') + ' ' + new Date(Date.now() - 3600000 * 3).toLocaleDateString('pt-BR'),
        user: 'Alessandro Pinto',
        role: 'instructor',
        ipAddress: '172.16.254.12',
        device: 'Firefox / Windows 11',
        action: 'Atualização de Aula',
        details: 'Novas diretrizes e links adicionados na aula inaugural de Vídeo Mapping.',
        status: 'SUCCESS' as const
      },
      {
        id: 'log-3',
        timestamp: new Date(Date.now() - 3600000 * 1).toLocaleTimeString('pt-BR') + ' ' + new Date(Date.now() - 3600000 * 1).toLocaleDateString('pt-BR'),
        user: 'João Silva',
        role: 'student',
        ipAddress: '189.122.45.92',
        device: 'Safari / iPhone 15 Pro',
        action: 'Autenticação no Sistema',
        details: 'Acesso realizado com êxito sob as diretrizes de LGPD e segurança de canais.',
        status: 'SUCCESS' as const
      }
    ] as SecurityLog[],
    systemSettings: {
      siteName: 'Escola da Cultura',
      maintenanceMode: false,
      allowNewRegistrations: true,
      defaultLanguage: 'pt',
      sessionTimeout: 30,
      requireMfa: true,
      mfaType: 'pin' as 'email' | 'sms' | 'app' | 'pin',
      maxLoginAttempts: 5,
    }
  };

  // --- API ROUTES ---

  // Health check API
  app.get('/api/health', (req, res) => {
    res.json({ status: 'ok', timestamp: new Date() });
  });

  // Database Reset endpoint
  app.post('/api/reset', (req, res) => {
    db.courses = [...INITIAL_COURSES];
    db.libraryItems = [...INITIAL_LIBRARY] as any[] as LibraryItem[];
    db.webinarEvents = [...INITIAL_WEBINARS];
    db.progress = [];
    db.certificates = [];
    db.chatMessages = [];
    db.directMessages = [];
    db.academicRequests = [];
    db.admissionRequests = [];
    db.securityLogs = [
      {
        id: `log-reset-${Date.now()}`,
        timestamp: new Date().toLocaleTimeString('pt-BR') + ' ' + new Date().toLocaleDateString('pt-BR'),
        user: 'Sistema',
        role: 'admin',
        ipAddress: '127.0.0.1',
        device: 'Terminal de Restauro',
        action: 'Purga do Servidor',
        details: 'O banco de dados em memória foi redefinido para o padrão da plataforma.',
        status: 'WARNING'
      }
    ];
    res.json({ success: true, message: 'Banco de dados restaurado.' });
  });

  // Courses Endpoints
  app.get('/api/courses', (req, res) => {
    res.json(db.courses);
  });

  app.post('/api/courses', (req, res) => {
    const course = req.body as Course;
    if (!course.id) {
      course.id = `course-${Date.now()}`;
    }
    db.courses.push(course);
    res.status(201).json(course);
  });

  app.put('/api/courses/:id', (req, res) => {
    const { id } = req.params;
    const updates = req.body;
    const index = db.courses.findIndex((c) => c.id === id);
    if (index !== -1) {
      db.courses[index] = { ...db.courses[index], ...updates };
      res.json(db.courses[index]);
    } else {
      res.status(404).json({ error: 'Curso não encontrado' });
    }
  });

  // Library Endpoints
  app.get('/api/library', (req, res) => {
    res.json(db.libraryItems);
  });

  app.post('/api/library', (req, res) => {
    const item = req.body as LibraryItem;
    if (!item.id) {
      item.id = `lib-${Date.now()}`;
    }
    db.libraryItems.push(item);
    res.status(201).json(item);
  });

  // Webinars Endpoints
  app.get('/api/webinars', (req, res) => {
    res.json(db.webinarEvents);
  });

  app.post('/api/webinars', (req, res) => {
    const webinar = req.body as WebinarEvent;
    if (!webinar.id) {
      webinar.id = `webinar-${Date.now()}`;
    }
    db.webinarEvents.push(webinar);
    res.status(201).json(webinar);
  });

  // Student Progress Endpoints
  app.get('/api/progress', (req, res) => {
    res.json(db.progress);
  });

  app.post('/api/progress', (req, res) => {
    const newProgress = req.body as StudentProgress;
    const index = db.progress.findIndex((p) => p.courseId === newProgress.courseId);
    if (index !== -1) {
      db.progress[index] = newProgress;
    } else {
      db.progress.push(newProgress);
    }
    res.json(newProgress);
  });

  // Certificates Endpoints
  app.get('/api/certificates', (req, res) => {
    res.json(db.certificates);
  });

  app.post('/api/certificates', (req, res) => {
    const cert = req.body as Certificate;
    if (!cert.id) {
      cert.id = `cert-${Date.now()}`;
    }
    const idx = db.certificates.findIndex(c => c.id === cert.id);
    if (idx !== -1) {
      db.certificates[idx] = cert;
    } else {
      db.certificates.push(cert);
    }
    res.status(201).json(cert);
  });

  // Forum/Live Chats Endpoints
  app.get('/api/chat', (req, res) => {
    res.json(db.chatMessages);
  });

  app.post('/api/chat', (req, res) => {
    const msg = req.body as ChatMessage;
    if (!msg.id) {
      msg.id = `chat-${Date.now()}`;
    }
    const idx = db.chatMessages.findIndex(m => m.id === msg.id);
    if (idx === -1) {
      db.chatMessages.push(msg);
    }
    res.status(201).json(msg);
  });

  // Direct Messages Endpoints
  app.get('/api/dms', (req, res) => {
    res.json(db.directMessages);
  });

  app.post('/api/dms', (req, res) => {
    const dm = req.body as DirectMessage;
    if (!dm.id) {
      dm.id = `dm-${Date.now()}`;
    }
    const idx = db.directMessages.findIndex(m => m.id === dm.id);
    if (idx === -1) {
      db.directMessages.push(dm);
    }
    res.status(201).json(dm);
  });

  // Academic Requests Endpoints
  app.get('/api/academic-requests', (req, res) => {
    res.json(db.academicRequests);
  });

  app.post('/api/academic-requests', (req, res) => {
    const request = req.body as AcademicRequest;
    if (!request.id) {
      request.id = `req-${Date.now()}`;
    }
    const idx = db.academicRequests.findIndex((r) => r.id === request.id);
    if (idx !== -1) {
      db.academicRequests[idx] = request;
    } else {
      db.academicRequests.push(request);
    }
    res.status(201).json(request);
  });

  app.put('/api/academic-requests/:id', (req, res) => {
    const { id } = req.params;
    const { status } = req.body;
    const index = db.academicRequests.findIndex((req) => req.id === id);
    if (index !== -1) {
      db.academicRequests[index].status = status;
      res.json(db.academicRequests[index]);
    } else {
      res.status(404).json({ error: 'Solicitação acadêmica não encontrada' });
    }
  });

  // Admission Requests Endpoints
  app.get('/api/admissions', (req, res) => {
    res.json(db.admissionRequests);
  });

  app.post('/api/admissions', (req, res) => {
    const adm = req.body as AdmissionRequest;
    if (!adm.id) {
      adm.id = `adm-${Date.now()}`;
    }
    // Evita duplicados pendentes
    const isDuplicate = db.admissionRequests.some(
      (r) => r.studentName === adm.studentName && r.courseId === adm.courseId && r.status === 'pending'
    );
    if (isDuplicate) {
      res.status(400).json({ error: 'Matrícula Pendente para este curso já registrada!' });
      return;
    }
    db.admissionRequests.push(adm);
    res.status(201).json(adm);
  });

  app.put('/api/admissions/:id', (req, res) => {
    const { id } = req.params;
    const { status } = req.body;
    const index = db.admissionRequests.findIndex((req) => req.id === id);
    if (index !== -1) {
      db.admissionRequests[index].status = status;
      res.json(db.admissionRequests[index]);
    } else {
      res.status(404).json({ error: 'Matrícula não encontrada' });
    }
  });

  // Security Logs Endpoints
  app.get('/api/security-logs', (req, res) => {
    res.json(db.securityLogs);
  });

  app.post('/api/security-logs', (req, res) => {
    const log = req.body as SecurityLog;
    if (!log.id) {
      log.id = `log-${Date.now()}`;
    }
    const idx = db.securityLogs.findIndex(l => l.id === log.id);
    if (idx === -1) {
      db.securityLogs.unshift(log); // Coloca mais recente primeiro
      db.securityLogs = db.securityLogs.slice(0, 100);
    }
    res.status(201).json(log);
  });

  app.delete('/api/security-logs', (req, res) => {
    db.securityLogs = [];
    res.json({ success: true, message: 'Logs de auditoria zerados com sucesso.' });
  });

  // System Settings Endpoints
  app.get('/api/system-settings', (req, res) => {
    res.json(db.systemSettings);
  });

  app.put('/api/system-settings', (req, res) => {
    const updates = req.body;
    db.systemSettings = { ...db.systemSettings, ...updates };
    res.json(db.systemSettings);
  });

  // --- VITE AND SPA FALLBACK MIDDLEWARES ---

  if (process.env.NODE_ENV !== 'production') {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), 'dist');
    app.use(express.static(distPath));
    app.get('*', (req, res) => {
      res.sendFile(path.join(distPath, 'index.html'));
    });
  }

  app.listen(PORT, '0.0.0.0', () => {
    console.log(`[AVASEC Full-Stack Server] running on http://localhost:${PORT}`);
  });
}

startServer();
