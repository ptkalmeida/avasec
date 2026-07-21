// Upload de arquivos do AVASEC.
// - Arquivos PÚBLICOS (materiais de aula, biblioteca): pasta uploads/public, servidos
//   estaticamente em /uploads/<nome> — pensados para serem acessíveis aos alunos.
// - Arquivos PRIVADOS (entregas de exercício): pasta uploads/private, NUNCA servidos
//   estaticamente; o download passa por GET /api/files/:id com autorização (dono,
//   instrutor ou admin) — ver fileRouter abaixo.
// Para vídeos das aulas, use um serviço de vídeo dedicado (Cloudflare Stream / Bunny Stream) —
// este endpoint não deve ser usado para vídeo por causa do custo de banda/disco.
import { Router, Request, Response, NextFunction } from 'express';
import multer from 'multer';
import path from 'path';
import fs from 'fs';
import fsp from 'fs/promises';
import crypto from 'crypto';
import { fromBuffer as fileTypeFromBuffer } from 'file-type';
import { env } from './config/env';
import { prisma } from './prisma';
import { uploadLimiter } from './middlewares/rateLimiters';
import { requireAuth, AuthedRequest } from './middlewares/auth';
import { requireActiveAccount } from './middlewares/accountStatus';
import { Errors } from './utils/ApiError';
import { asyncHandler } from './utils/asyncHandler';

const UPLOADS_ROOT = path.join(process.cwd(), 'uploads');
export const UPLOADS_PUBLIC_DIR = path.join(UPLOADS_ROOT, 'public');
export const UPLOADS_PRIVATE_DIR = path.join(UPLOADS_ROOT, 'private');

for (const dir of [UPLOADS_PUBLIC_DIR, UPLOADS_PRIVATE_DIR]) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

// Extensão declarada -> MIME real esperado (conferido pelos magic bytes do arquivo, não pelo
// Content-Type informado pelo cliente, que pode ser forjado).
const ALLOWED_TYPES: Record<string, string[]> = {
  '.pdf': ['application/pdf'],
  '.png': ['image/png'],
  '.jpg': ['image/jpeg'],
  '.jpeg': ['image/jpeg'],
  '.webp': ['image/webp'],
  '.gif': ['image/gif'],
  '.docx': ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
  // .doc (formato binário legado OLE) não tem assinatura própria e confiável de checar via
  // magic bytes — não é aceito. Peça ao usuário para salvar como .docx ou .pdf.
};

const MIME_BY_EXT: Record<string, string> = {
  '.pdf': 'application/pdf',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
  '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
};

const MAX_FILE_SIZE_BYTES = env.UPLOAD_MAX_SIZE_MB * 1024 * 1024;

// Guarda em memória para inspecionar os bytes reais antes de decidir o nome final e gravar em
// disco — impede que um arquivo com extensão errada (ou conteúdo executável disfarçado) chegue
// a ser persistido.
const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: MAX_FILE_SIZE_BYTES },
  fileFilter: (_req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase();
    if (!ALLOWED_TYPES[ext]) {
      // ApiError também é um Error; o errorHandler central o converte num 400 padronizado.
      cb(Errors.badRequest(`Tipo de arquivo não permitido: ${ext || '(sem extensão)'}`) as unknown as Error);
      return;
    }
    cb(null, true);
  },
});

export const uploadRouter = Router();

uploadRouter.post(
  '/',
  uploadLimiter,
  requireAuth,
  requireActiveAccount,
  upload.single('file'),
  asyncHandler(async (req: AuthedRequest, res: Response) => {
    if (!req.file) {
      throw Errors.badRequest('Nenhum arquivo enviado.');
    }

    const visibility = req.query.visibility === 'private' ? 'private' : 'public';

    const ext = path.extname(req.file.originalname).toLowerCase();
    const detected = await fileTypeFromBuffer(req.file.buffer);
    const allowedMimes = ALLOWED_TYPES[ext] ?? [];

    if (!detected || !allowedMimes.includes(detected.mime)) {
      throw Errors.badRequest(
        `O conteúdo do arquivo não corresponde a um ${ext.replace('.', '').toUpperCase()} válido.`
      );
    }

    // Nome gerado no servidor — nunca reaproveita o nome original enviado pelo cliente,
    // fechando qualquer tentativa de path traversal ou sobrescrita de arquivo.
    const safeName = `${Date.now()}-${crypto.randomBytes(8).toString('hex')}${ext}`;
    const targetDir = visibility === 'private' ? UPLOADS_PRIVATE_DIR : UPLOADS_PUBLIC_DIR;
    await fsp.writeFile(path.join(targetDir, safeName), req.file.buffer, { mode: 0o644 });

    await prisma.storedFile.create({
      data: {
        id: safeName,
        originalName: req.file.originalname.slice(0, 190),
        visibility,
        ownerUserId: req.user!.sub,
      },
    });

    res.status(201).json({
      url: visibility === 'private' ? `/api/files/${safeName}` : `/uploads/${safeName}`,
      fileName: req.file.originalname,
      size: `${(req.file.size / (1024 * 1024)).toFixed(2)} MB`,
      visibility,
    });
  })
);

// Handler de erro específico do multer — repassa para o errorHandler central.
uploadRouter.use((err: Error, _req: Request, _res: Response, next: NextFunction) => {
  next(err);
});

// ---------- DOWNLOAD AUTORIZADO DE ARQUIVOS PRIVADOS ----------

export const fileRouter = Router();

fileRouter.get(
  '/:id',
  requireAuth,
  requireActiveAccount,
  asyncHandler(async (req: AuthedRequest, res: Response) => {
    const { id } = req.params;

    // Nome vem do banco, não do cliente — mas rejeita qualquer tentativa de traversal por camada extra.
    if (id.includes('/') || id.includes('\\') || id.includes('..')) {
      throw Errors.badRequest('Identificador de arquivo inválido.');
    }

    const record = await prisma.storedFile.findUnique({ where: { id } });
    if (!record) throw Errors.notFound('Arquivo não encontrado.');

    // Autorização: o dono do arquivo, ou perfis de correção/gestão (instrutor/admin).
    const isOwner = record.ownerUserId === req.user!.sub;
    const isStaff = req.user!.role === 'instructor' || req.user!.role === 'admin';
    if (record.visibility === 'private' && !isOwner && !isStaff) {
      throw Errors.forbidden('Você não tem permissão para acessar este arquivo.');
    }

    const dir = record.visibility === 'private' ? UPLOADS_PRIVATE_DIR : UPLOADS_PUBLIC_DIR;
    const filePath = path.join(dir, record.id);
    if (!fs.existsSync(filePath)) throw Errors.notFound('Arquivo não encontrado no armazenamento.');

    const ext = path.extname(record.id).toLowerCase();
    res.setHeader('Content-Type', MIME_BY_EXT[ext] ?? 'application/octet-stream');
    // 'attachment' força download — arquivo enviado por terceiros nunca é executado/renderizado
    // no contexto da aplicação.
    res.setHeader('Content-Disposition', `attachment; filename="${encodeURIComponent(record.originalName)}"`);
    res.sendFile(filePath);
  })
);
