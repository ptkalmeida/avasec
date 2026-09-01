---
name: rodar-avasec
description: Sobe o AVASEC localmente (MySQL em Docker + API Laravel + Vite), verifica que subiu de verdade e tira screenshot da interface. Use quando o pedido for rodar, subir, iniciar, testar no navegador ou tirar print da aplicação — e antes de qualquer verificação manual de comportamento em tela. Registra as armadilhas do ambiente Windows deste projeto: processos órfãos presos nas portas, binário de Chromium que trava, e acento quebrando payload de login pelo Git Bash.
---

# Rodar o AVASEC localmente

Caminho verificado em 01/09/2026, Windows 11 + Git Bash. Cada passo abaixo foi
executado; as armadilhas anotadas são erros que **realmente aconteceram** aqui, não
precauções teóricas.

## 0. Antes de subir: procure processos órfãos nas portas

**Faça isto primeiro, sempre.** O `php artisan serve` e o `vite` sobrevivem ao
fechamento do terminal que os criou e ficam presos nas portas por dias. Já foi
encontrado um par com **4 dias** de idade, e o efeito é traiçoeiro:

- o Vite novo escorrega para 5174 sem avisar em letras garrafais, e você testa uma
  instância enquanto olha a outra;
- o `php artisan serve` novo **imprime "Server running on 8000" mesmo sem conseguir
  ocupar a porta** — então você acredita ter subido a API e está falando com o
  processo velho.

```powershell
Get-NetTCPConnection -LocalPort 5173,8000 -State Listen -ErrorAction SilentlyContinue |
  ForEach-Object { $p = Get-CimInstance Win32_Process -Filter "ProcessId=$($_.OwningProcess)"
    [PSCustomObject]@{ Porta=$_.LocalPort; PID=$_.OwningProcess; Nome=$p.Name; Criado=$p.CreationDate } } |
  Format-Table -AutoSize
```

Se a coluna `Criado` não for de hoje, é órfão. Encerre antes de subir:

```powershell
Stop-Process -Id <PID> -Force
```

Detalhe que evita conclusão errada: o `php -S` do artisan **relê o código do disco a
cada requisição**, então um processo velho serve código novo. Um servidor de dias
atrás pode responder com o comportamento correto de hoje — e por isso a idade do
processo não invalida o que você testou, mas a porta ocupada invalida a sua certeza
sobre *qual* processo respondeu. Suba limpo e reconfirme.

## 1. MySQL

```bash
docker compose ps          # esperado: avasec-mysql ... Up (healthy)
docker compose up -d       # se não estiver de pé (ou: npm run db:up)
```

Confirme a conexão pela aplicação, não pela porta — a 3306 aceita TCP sem responder
HTTP, e `curl` nela sempre dá 000:

```bash
cd backend-laravel && php artisan tinker --execute='echo "DB ok: ", \DB::table("Course")->count(), " cursos";'
```

## 2. API Laravel (porta 8000)

```bash
cd backend-laravel && php artisan serve --host=127.0.0.1 --port=8000
```

Rode em segundo plano. O `npm run api` do `package.json` faz o mesmo com
`PHP_CLI_SERVER_WORKERS=8`, mas usa a sintaxe `set X=Y&&` do cmd.exe — chamar o
`artisan` direto é o caminho que não depende de qual shell está interpretando.

## 3. Vite (porta 5173)

```bash
npm run dev
```

Use **Git Bash**, não PowerShell: há um arquivo espúrio `C:\Windows\system32\npm`
nesta máquina que faz o PowerShell abrir o seletor de aplicativo do Windows em vez de
executar o npm.

Confirme no output qual porta saiu. Se disser 5174, voltou ao passo 0.

## 4. Verifique pelo caminho do app, não pela API direta

O Vite encaminha `/api` e `/uploads` para o Laravel (`vite.config.ts`), então bater em
`localhost:5173/api/...` exercita o mesmo trajeto que o navegador — proxy incluído.
Testar direto na 8000 pula o proxy e esconde erro de configuração dele.

```bash
curl -s http://127.0.0.1:8000/api/health-laravel     # {"status":"ok","database":"ok"}
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:5173/
curl -s -o /dev/null -w "%{size_download}\n" http://localhost:5173/api/courses
```

### Login por curl: use e-mail ou CPF, e mande o corpo por arquivo

`-d '{"name":"João Silva",...}'` **falha com 400 `password field is required`** — o
acento é mutilado no caminho Git Bash → curl → PHP, o JSON deixa de ser válido e o
Laravel recebe corpo vazio. Não é bug da aplicação; já custou uma investigação.

```bash
printf '%s' '{"email":"joao.silva@lms.edu","password":"1234"}' > /tmp/body.json
curl -s -c /tmp/ck.txt -X POST http://localhost:5173/api/auth/login \
  -H "Content-Type: application/json" --data-binary "@/tmp/body.json"
```

O cookie de sessão é HttpOnly (`ava_session`); guarde-o com `-c` e reenvie com `-b`.

### Comportamento esperado do catálogo (regressão de segurança)

`GET /api/courses` é público, mas o material sai só para quem pertence ao curso
(ISO-01). Confira a diferença — se o anônimo trouxer material, houve regressão:

```bash
curl -s http://localhost:5173/api/courses -o /tmp/anon.json                  # ~6,4 KB
curl -s -b /tmp/ck.txt http://localhost:5173/api/courses -o /tmp/auth.json   # ~10,7 KB
```

Com João Silva (aluno), o esperado é material em `course-1` e
`course-1787928131660`, e vitrine seca em `course-2` e `course-3`.

## 5. Screenshot da interface

**Use `chrome-headless-shell.exe`, não `chrome.exe`.** O `chrome.exe` do Playwright
trava indefinidamente aqui, com `--headless=new` e com `--headless=old`, sem gerar
arquivo nem mensagem de erro — some até o timeout. O binário dedicado a headless
funciona de primeira e ainda imprime o console do navegador, o que entrega erro de JS
de graça.

```bash
HS="/c/Users/$USERNAME/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe"
"$HS" --disable-gpu --hide-scrollbars --window-size=1440,1500 \
      --screenshot=/tmp/portal.png --user-data-dir=/tmp/perfil-chrome \
      http://localhost:5173/
```

Ajuste o número da versão (`chromium_headless_shell-*`) ao que existir na pasta.
`--virtual-time-budget=5000` funciona normalmente neste binário, apesar de a interface
ter animação infinita (`animate-pulse`, `motion`) — chegou-se a culpar a animação pelo
travamento, e a causa era só o binário errado.

O `msedge.exe --headless=new` também funciona, como alternativa se o Playwright não
estiver instalado.

**Depois, olhe a imagem.** Quadro branco é falha de montagem, não sucesso.

## 6. Encerrar

```powershell
Get-NetTCPConnection -LocalPort 5173,8000 -State Listen |
  ForEach-Object { Stop-Process -Id $_.OwningProcess -Force }
```

Encerre ao terminar. Deixar de pé é como nascem os órfãos do passo 0.

## Contas para teste manual

As senhas de demonstração (`1234` aluno, `5678` gestor, `9999` admin) **ainda valem** —
a rotação está pendente (`docs/security-audit/STATUS-CORRECOES.md`). Quando forem
rotacionadas, este trecho e os exemplos de login acima ficam desatualizados: use
`php artisan avasec:rotate-demo-passwords --dry-run` para ver quais contas restam.
