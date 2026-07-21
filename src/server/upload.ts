// Upload de arquivos (PDFs, imagens, comprovantes, entregas de exercícios) para o disco do servidor.
// Para vídeos das aulas, use um serviço de vídeo dedicado (Cloudflare Stream / Bunny Stream) —
// este endpoint não deve ser usado para vídeo por causa do custo de banda/disco.
//
// Nota de segurança (ver relatório de revisão do backend): os arquivos hoje ficam em uma pasta
// servida estaticamente (`/uploads`), o que é aceitável para materiais de curso (já pensados para
// serem públicos aos alunos matriculados), mas não é apropriado para dados sensíveis de longo
// prazo. Migrar para um storage privado com URLs assinadas (S3/GCS/Backblaze) é a evolução
// recomendada quando o volume de uploads crescer — ver DEPLOY.md.
import { Router, Request, Response, NextFunction } from 'express';
import multer from 'multer';
import path from 'path';
import fs from 'fs';
import fsp from 'fs/promises';
import crypto from 'crypto';
import { fromBuffer as fileTypeFromBuffer } from 'file-type';
import { env } from './config/env';
import { uploadLimiter } from './middlewares/rateLimiters';
import { requireAuth } from './middlewares/auth';
import { requireActiveAccount } from './middlewares/accountStatus';
import { Errors } from './utils/ApiError';
import { asyncHandler } from './utils/asyncHandler';

export const UPLOADS_DIR = path.join(process.cwd(), 'uploads');

if (!fs.existsSync(UPLOADS_DIR)) {
  fs.mkdirSync(UPLOADS_DIR, { recursive: true });
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

const MAX_FILE_SIZE_BYTES = env.UPLOAD_MAX_SIZE_MB * 1024 * 1024;

// Guarda em memória para inspecionar os bytes reais antes de decidir o nome final e gravar em
// disco — impede que um arquivo com extensão errada (ou conteúdo executável disfarçado) chegue
// a ser persistido.
const storage = multer.memoryStorage();

const upload = multer({
  storage,
  limits: { fileSize: MAX_FILE_SIZE_BYTES },
  fileFilter: (_req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase();
    if (!ALLOWED_TYPES[ext]) {
      // ApiError também é um Error, então o multer o repassa normalmente para o error handler central,
      // que já sabe convertê-lo num 400 padronizado (em vez de um 500 genérico).
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
  asyncHandler(async (req: Request, res: Response) => {
    if (!req.file) {
      throw Errors.badRequest('Nenhum arquivo enviado.');
    }

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
    await fsp.writeFile(path.join(UPLOADS_DIR, safeName), req.file.buffer, { mode: 0o644 });

    res.status(201).json({
      url: `/uploads/${safeName}`,
      fileName: req.file.originalname,
      size: `${(req.file.size / (1024 * 1024)).toFixed(2)} MB`,
    });
  })
);

// Handler de erro específico do multer (tamanho excedido, tipo inválido, etc.) — repassa para
// o errorHandler central em vez de responder aqui diretamente.
uploadRouter.use((err: Error, _req: Request, _res: Response, next: NextFunction) => {
  next(err);
});
