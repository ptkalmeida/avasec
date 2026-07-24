// Carrega e valida as variáveis de ambiente uma única vez na subida do servidor.
// Falhar cedo aqui evita rodar em produção com JWT_SECRET fraco/ausente ou CORS aberto demais.
import { z } from 'zod';

const envSchema = z.object({
  NODE_ENV: z.enum(['development', 'production', 'test']).default('development'),
  PORT: z.coerce.number().int().positive().default(3000),
  DATABASE_URL: z.string().min(1, 'DATABASE_URL é obrigatória.'),
  JWT_SECRET: z
    .string()
    .min(16, 'JWT_SECRET deve ter ao menos 16 caracteres. Gere um valor forte, ex: openssl rand -base64 48.'),
  // Lista de origens permitidas separadas por vírgula. Em produção NÃO deve ficar vazia/"*"
  CORS_ORIGIN: z.string().default('http://localhost:3000'),
  UPLOAD_MAX_SIZE_MB: z.coerce.number().positive().default(15),
});

const parsed = envSchema.safeParse(process.env);

if (!parsed.success) {
  console.error('[CONFIG] Variáveis de ambiente inválidas:');
  for (const issue of parsed.error.issues) {
    console.error(`  - ${issue.path.join('.')}: ${issue.message}`);
  }
  process.exit(1);
}

const isProduction = parsed.data.NODE_ENV === 'production';

if (isProduction && (parsed.data.JWT_SECRET === 'troque-este-segredo-em-producao' || parsed.data.JWT_SECRET.length < 32)) {
  console.error('[CONFIG] JWT_SECRET inseguro detectado em produção. Defina um segredo forte e único antes de subir o servidor.');
  process.exit(1);
}

export const env = {
  ...parsed.data,
  isProduction,
  corsOrigins: parsed.data.CORS_ORIGIN.split(',').map((o) => o.trim()).filter(Boolean),
};
