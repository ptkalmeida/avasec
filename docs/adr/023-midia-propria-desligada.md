# ADR 023 — Mídia própria (vídeo hospedado pelo AVASEC): pronta e desligada

- **Data:** 26/10/2026
- **Situação:** aceita — o responsável decidiu deixar o recurso no projeto, desligado
- **Afeta:** ADR 08 (provedor de vídeo derivado da URL), ADR 12 (nada é apagado), plano 11 (F3, MinIO)

## Contexto

O vídeo de uma aula é hoje um link do YouTube em `Lesson.videoUrl`. Funciona e não custa
armazenamento nem banda ao projeto. A coordenação quer, porém, **ter pronta** a opção de
hospedar o próprio vídeo (MinIO + FFmpeg), para o dia em que o YouTube deixar de servir
(privacidade, vídeo que não pode ser público, aluno sem acesso). Deixar para fazer só nesse
dia é mais caro do que manter uma base pequena, testada e desligada.

O aviso do `AGENTS.md` sobre `Course.statusCurso` ("existe e nenhuma consulta o lê; é
decoração") vale aqui: código sem consumidor apodrece. Por isso a base tem **testes que rodam
sempre** e **uma única porta de entrada** (o interruptor), em vez de código solto.

## Decisão

Existe, no código, o caminho **original → FFmpeg → MinIO → `Lesson.videoUrl`**, e ele só roda com
`MEDIA_DRIVER=minio`. O padrão é `youtube`: com ele o job `ProcessarVideoDaAula` sai na primeira
linha, sem tocar em disco, banco ou FFmpeg.

| Peça | Arquivo |
|---|---|
| Interruptor e leitura tipada da configuração | `config/midia.php`, `App\Support\MidiaPropria` |
| FFmpeg (comandos em array, sem shell) | `App\Services\Midia\TranscodificadorDeVideo` |
| Processamento assíncrono | `App\Jobs\ProcessarVideoDaAula`, fila `midia` |
| Disco do original e dos derivados | disco `midia` em `config/filesystems.php` (local; `s3` = MinIO) |
| FFmpeg na imagem | estágio `app` de `docker/producao/Dockerfile` (`ARG INSTALAR_FFMPEG`) |

**Sem mudança de schema.** O job só grava `Lesson.videoUrl` **no fim**, quando o MP4 já está no
disco. Quem lê a aula nunca vê um vídeo pela metade, e uma falha deixa a aula como estava. O
custo: a interface não mostra "processando" enquanto roda. Mostrar isso exige coluna nova e,
portanto, validação do responsável (`AGENTS.md`, "Nunca mude sem sinalizar").

## O que garante que nada do que existe muda

- **Fila própria.** O job vai para a fila `midia`. O worker atual (`queue:work`, fila `default`)
  nunca a consome; e, com o recurso desligado, ninguém despacha o job.
- **Nenhuma rota, tela ou controlador novo.** Nada chama o job hoje.
- **Disco `midia` é local por padrão**, na pasta `uploads/midia`, que só passa a existir se o
  recurso for usado. `public/` e `private/` não foram tocados.
- **Docker:** o FFmpeg entra em uma camada à parte, depois de tudo o que já existia (o cache das
  camadas anteriores é preservado). A imagem `app` — que também é a de `queue` e `scheduler` —
  cresce cerca de **127 MB**. Para uma imagem sem ele: `--build-arg INSTALAR_FFMPEG=0`.
  O espelho local (`docker/php`) não foi alterado.
- Build do estágio `app` validado em 26/10: extensões `gd`, `pdo_mysql`, `intl`, `bcmath` e
  `zip` presentes, usuário `www-data`, FFmpeg 8.1 com `libx264` e `aac`.

## ADR 12 (nada é apagado)

- O **original nunca é apagado nem sobrescrito**.
- Cada execução grava numa pasta nova (`aulas/<aula>/<timestamp>-<aleatório>/`); reprocessar
  **acrescenta**, não substitui.
- Ao trocar o `videoUrl`, o model `Lesson` já guarda a versão anterior (ADR 021): o link do
  YouTube que saiu fica no histórico, e dá para restaurar.
- A única coisa removida é a pasta **temporária** de trabalho do job (cópia de trabalho, não
  registro).

## Decidido para depois (quando a flag for ligada, se for)

1. **Entrega do HLS.** O job sabe gerar HLS (`MEDIA_HLS=true`), mas `VideoSource` só entende
   arquivos (`mp4`, `webm`…). `.m3u8` pede um novo `VideoProvider` e o hls.js no player. Hoje só
   o MP4 é entregue.
2. **Entrega privada com permissão.** O job exige `MEDIA_PUBLIC_URL` (bucket ou CDN) e se
   recusa a rodar sem ele, para não gravar um endereço que ninguém sabe servir. Se o vídeo tiver
   de respeitar `Visibilidade`, a entrega passa a ser por URL assinada ou proxy — decisão a
   tomar com o desenho do acesso em mãos.
3. **Tela de envio e acompanhamento** no editor da aula (e o status "processando", que exige
   coluna).
4. **Worker.** Hoje: `queue:work --tries=3 --timeout=90` na fila `default`. Para ligar é preciso
   um worker da fila `midia` (`--queue=midia`), e o `DB_QUEUE_RETRY_AFTER` da conexão **acima**
   do `MEDIA_TIMEOUT` (3600 s) — senão o job é entregue de novo enquanto ainda roda. O
   `docker-compose.hml.yml` **não foi alterado**.
5. **Migração dos vídeos já enviados:** nenhuma. O que existe continua no YouTube ou em
   `uploads/public`.

## Testes

`tests/Unit/TranscodificadorDeVideoTest.php` (comandos, nome com metacaracteres de shell,
falhas) e `tests/Feature/ProcessarVideoDaAulaTest.php` (desligado não toca em nada; ligado só
muda a aula no fim; original e link anterior preservados; duas execuções não se sobrescrevem;
HLS; falha deixa a aula intacta; fila própria). O FFmpeg é simulado: o teste prende o contrato,
não a qualidade da codificação.

**Não verificado:** uma transcodificação real, ponta a ponta, contra um MinIO. Isso só foi
exercitado com o disco simulado.
