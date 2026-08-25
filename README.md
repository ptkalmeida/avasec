# AVASEC — Escola da Cultura

Ambiente Virtual de Aprendizagem (AVA) institucional para Cultura e Economia Criativa

## Visão Geral

AVASEC ("Escola da Cultura") é uma plataforma completa de Ambiente Virtual de Aprendizagem, voltada à formação e qualificação profissional em Cultura e Economia Criativa por meio de **Cursos Livres**. O sistema oferece catálogo público de cursos, matrícula, consumo de aulas gravadas e ao vivo, acompanhamento de progresso, emissão de certificado digital com verificação pública e um painel administrativo completo para gestão da escola.

O frontend é construído em **React + Vite + TypeScript**, consumindo uma API REST em **Laravel + MySQL**, com autenticação JWT em cookie HttpOnly e uma camada de segurança completa (rate limiting, RBAC, validação, auditoria e feature flags no servidor).

## Requisitos do Sistema

### Software

- Node.js 20+ (recomendado 22 LTS)
- PHP 8.2+ (recomendado 8.4)
- Composer
- MySQL 8 (via Docker ou instalação local)
- Docker e Docker Compose (para subir o MySQL local)

## Instalação

### 1. Clone o projeto

```bash
git clone https://github.com/ptkalmeida/avasec.git
cd avasec
```

### 2. Instalar dependências do frontend

```bash
npm install
```

### 3. Instalar dependências do backend (Laravel)

```bash
cd backend-laravel
composer install
cd ..
```

### 4. Configuração de Segurança (IMPORTANTE!)

```bash
# Copie o arquivo de exemplo
cp .env.example .env

# Edite o arquivo .env com seus valores seguros
# NUNCA compartilhe ou faça commit deste arquivo!
```

Variáveis obrigatórias:

- `JWT_SECRET` — chave secreta para os tokens (HS256)
- `CORS_ORIGIN` — origem(s) permitida(s) pela API (nunca use `*` com credenciais)
- Credenciais do banco de dados (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
- `UPLOAD_MAX_SIZE_MB` — tamanho máximo permitido para uploads

### 5. Subir o banco de dados (MySQL via Docker)

```bash
npm run db:up
```

### 6. Rodar as migrations

```bash
npm run db:migrate
```

## Como Executar

### Subir o backend (Laravel)

```bash
npm run api
```

A API estará disponível em `http://127.0.0.1:8000`.

### Subir o frontend (Vite)

```bash
npm run dev
```

O sistema estará disponível em:

- **Aplicação:** http://localhost:5173
- **API:** http://127.0.0.1:8000/api (proxy automático via Vite em desenvolvimento)

### Build de produção

```bash
npm run build
```

Em produção, o Nginx serve o SPA (`dist/`) e faz proxy de `/api` para o PHP-FPM. Detalhes completos em `DEPLOY_LARAVEL.md`.

## Perfis de Usuário

O sistema possui três papéis (`role`), definidos no cadastro e verificados via JWT em toda rota interna:

- **Aluno (`student`)** — navega pelo catálogo, se matricula, assiste aulas, acompanha progresso e emite certificado.
- **Instrutor (`instructor`)** — cria e gerencia apenas os próprios cursos, alunos matriculados neles, aulas, sessões ao vivo e correções.
- **Administrador (`admin`)** — acesso irrestrito: gestão de usuários, configurações do sistema, exportação de Dados Gerenciais e auditoria completa.

## Funcionalidades

### 1. Catálogo e Matrícula
- Catálogo público de cursos (sem necessidade de login)
- Solicitação de matrícula com aprovação por instrutor/admin
- Bloqueio de matrícula duplicada pendente
- Penalidade de 30 dias após cancelamento tardio (>5 dias matriculado)

### 2. Aulas e Progresso
- Aulas gravadas (vídeo externo validado) e sessões ao vivo
- Documentos complementares e quizzes
- Progresso registrado por aluno e por curso
- Cálculo de frequência recalculado inteiramente no servidor

### 3. Certificação Digital
- Emissão automática ao atingir o percentual mínimo de frequência (padrão 70%, configurável por curso)
- Emissão idempotente (sem duplicidade por aluno/curso)
- Geração de PDF oficial no servidor, com QR code de verificação pública
- Verificação pública sem exposição de dados internos

### 4. Painel do Instrutor
- Gestão de cursos, aulas, sessões ao vivo, documentos e quizzes
- Listagem de alunos apenas dos próprios cursos
- Aprovação/rejeição de matrículas e solicitações acadêmicas
- Correção de exercícios com nota e feedback

### 5. Painel Administrativo
- Gestão de contas (criação, bloqueio, exclusão)
- Aprovação de solicitações e configurações do sistema
- Exportação de Dados Gerenciais (5 bases em CSV)
- Trilha de auditoria completa (`/api/security-logs`)

### 6. Upload de Arquivos
- Validação de extensão **e** de conteúdo real (assinatura binária/magic bytes)
- Nome de arquivo gerado pelo servidor
- Separação entre uploads públicos e privados
- Tamanho máximo configurável

### 7. Segurança e Auditoria
- Log de auditoria server-side para ações sensíveis (login, matrícula, certificado, exportação, alteração de curso etc.)
- Erros padronizados, sem exposição de stack trace ao cliente

## Segurança Implementada

- **Autenticação:** JWT HS256 em cookie HttpOnly `SameSite=Lax` (expiração de 12h) + bcrypt para hash de senha
- **Bloqueio por força-bruta:** 5 falhas de login consecutivas travam a conta por 15 minutos
- **Rate limiting:** por rota sensível — login (10/15min), cadastro (10/hora), troca de senha (5/15min), matrícula (20/15min), exportação (10/hora), upload (30/15min) e limite global de 300/min por IP
- **RBAC:** verificação de papel e posse de recurso sempre no backend, nunca só escondida no frontend
- **Status de conta:** reconsultado no banco a cada requisição (`active`, `blocked`, `pending_confirmation`)
- **Validação de entrada:** em praticamente todas as rotas de escrita
- **CORS:** restrito às origens definidas em `CORS_ORIGIN`
- **Headers de segurança:** aplicados globalmente na API e via CSP/HSTS no Nginx em produção
- **Uploads:** validação por magic bytes, nunca reaproveita o nome enviado pelo cliente

## Estrutura do Projeto

```
avasec/
├── server.ts                  # bootstrap do dev server (Vite)
├── src/
│   ├── App.tsx                 # shell da aplicação (landing pública + navegação por perfil)
│   ├── context/LMSContext.tsx  # estado global do frontend + chamadas à API
│   ├── components/             # AdminDashboard, InstructorDashboard, StudentDashboard...
│   ├── config/features.ts      # feature flags
│   └── types.ts                # tipos compartilhados frontend/backend
├── backend-laravel/            # API REST (Laravel + Eloquent + MySQL)
│   ├── routes/                 # roteadores por domínio
│   ├── app/Http/Controllers/   # controllers
│   ├── app/Services/           # regras de negócio
│   ├── app/Http/Middleware/    # jwt, active, role, feature, SecurityHeaders
│   └── database/migrations/    # migrations do schema
├── legacy-node/                # backend Node/Express/Prisma anterior (arquivado)
├── db/init/                    # scripts de inicialização do MySQL
├── deploy/                     # configurações de Nginx / PM2
├── tests/frontend/             # testes do frontend (Vitest)
└── docker-compose.yml          # MySQL 8 local para desenvolvimento
```

## Scripts Principais

| Comando | O que faz |
| --- | --- |
| `npm run db:up` / `db:down` | Sobe / derruba o MySQL via Docker |
| `npm run api` | Sobe o backend Laravel em `http://127.0.0.1:8000` |
| `npm run dev` | Sobe o Vite em `http://localhost:5173` (proxy `/api` → Laravel) |
| `npm run build` | Build de produção do frontend (`dist/`) |
| `npm test` / `npm run test:frontend` | Testes do frontend (Vitest) |
| `npm run test:api` | Testes do backend (PHPUnit) |
| `npm run test:api:coverage` | Cobertura do backend (PHPUnit + Xdebug) |
| `npm run db:migrate` | Migrations do Laravel |
| `npm run lint` | Type-check do frontend (`tsc --noEmit`) |

## Tecnologias Utilizadas

**Frontend:** React 19, TypeScript, Vite 6, Tailwind CSS 4, lucide-react, Framer Motion, Recharts
**Backend:** PHP 8.4, Laravel 13, Eloquent ORM, MySQL 8, firebase/php-jwt
**Qualidade:** PHPUnit, Laravel Pint, PHPStan/Larastan (nível 9), Vitest + Testing Library
**Infraestrutura:** Docker/Docker Compose, Nginx, PM2

## Solução de Problemas

**Erro ao conectar no banco de dados**
Verifique se o container do MySQL está rodando com `npm run db:up` e se as credenciais em `.env` conferem com o `docker-compose.yml`.

**Porta já em uso**
Altere a porta do Vite em `vite.config.ts` ou a porta do Laravel ao subir com `npm run api`.

**Upload rejeitado**
Confirme se o tipo de arquivo está na allowlist configurada no backend e se o tamanho não excede `UPLOAD_MAX_SIZE_MB`.

## Documentação Adicional

- `DOCUMENTATION.md` — documentação técnica e funcional detalhada do estado atual do código
- `TECNOLOGIAS.md` — stack completo de tecnologias e scripts
- `HARDENING.md` — medidas de reforço de segurança
- `MIGRACAO_LARAVEL.md` — histórico da migração do backend Node para Laravel
- `DEPLOY_LARAVEL.md` — guia de deploy em produção

