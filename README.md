# AVASEC

Ambiente virtual de aprendizagem da Escola Estadual da Cultura (SECEC-RJ): cursos, aulas,
avaliações, matrícula, certificados e gestão pedagógica.

Frontend **React** (SPA) consumindo uma API REST em **Laravel**, com banco **MySQL**.

## Stack e versões

Versões **efetivamente em uso**, lidas de `composer.lock`, `package-lock.json` e do ambiente de
desenvolvimento. Exigência da Norma Técnica TI-SECEC (C.1.2): toda troca de versão atualiza esta
tabela e o Documento de Arquitetura no mesmo ciclo da mudança.

| Camada | Tecnologia | Versão |
|---|---|---|
| Backend | PHP | 8.4.5 (mínimo exigido: 8.3) |
| | Laravel | 13.21.1 |
| | firebase/php-jwt (sessão em JWT) | 7.1.0 |
| Banco | MySQL (imagem `mysql:8.0`) | 8.0.46 |
| Frontend | React / React DOM | 19.2.7 |
| | React Router | 7.18.3 |
| | TypeScript | 5.8.3 |
| | Vite | 6.4.3 |
| | Tailwind CSS | 4.3.0 |
| Ferramentas | Node.js / npm | 22.22.3 / 10.9.8 |
| | Docker (MySQL de desenvolvimento) | Docker Compose |
| Qualidade | PHPUnit | 12.5.31 |
| | PHPStan / Larastan (nível 9) | 2.2.5 / 3.10.0 |
| | Laravel Pint | 1.29.3 |
| | Vitest / Testing Library | 3.2.7 / 16.3.2 |

Papel de cada tecnologia, e as bibliotecas de apoio: [TECNOLOGIAS.md](TECNOLOGIAS.md).

## Rodar localmente

Pré-requisitos: PHP 8.3+, Composer, Node.js 22, Docker.

```bash
npm install
(cd backend-laravel && composer install)
npm run db:up                                   # MySQL em Docker (127.0.0.1:3306)
npm run db:migrate
cd backend-laravel && php artisan serve --host=127.0.0.1 --port=8000   # API
npm run dev                                     # frontend em http://localhost:5173
```

No Windows, rode o `npm` pelo Git Bash. O passo a passo, com as armadilhas já encontradas neste
ambiente, está em `.claude/skills/rodar-avasec/SKILL.md`.

## Verificação de saúde

| Endereço | Para quê |
|---|---|
| `/health/live` | a aplicação está de pé (não consulta dependências) |
| `/health/ready` | pode receber requisições: banco e armazenamento de arquivos respondem (503 se não) |
| `/sistema/status` | página pública, em linguagem simples, com o estado de cada componente |

## Qualidade

```bash
npm run lint          # TypeScript
npm test              # testes do frontend
cd backend-laravel && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --memory-limit=1G && php artisan test
```

## Documentação

- [AGENTS.md](AGENTS.md): regras obrigatórias para qualquer agente de IA neste repositório
- [docs/adr/](docs/adr/): decisões de arquitetura (ex.: ADR 12, nada é apagado)
- [DEPLOY_LARAVEL.md](DEPLOY_LARAVEL.md): implantação
- [HARDENING.md](HARDENING.md) e [docs/security-audit/](docs/security-audit/): segurança
- [MIGRACAO_LARAVEL.md](MIGRACAO_LARAVEL.md): histórico da migração do backend Node para Laravel
