<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Migração para MPA (ADR 017)
|--------------------------------------------------------------------------
|
| Os prefixos que pertencem ao SERVIDOR, lidos de `rotas-laravel.json` — o mesmo
| arquivo que o `vite.config.ts` usa para montar o proxy de desenvolvimento. Uma
| fonte só, dois leitores; `RoteadorMpaTest` prende os dois ao conteúdo dela.
|
| Para que servem aqui: um caminho inexistente sob um destes prefixos é 404 de
| verdade, e NÃO deve cair no SPA React. Sem essa regra, `/uploads/../../.env`
| (que a rota recusa de propósito) passava a responder 200 com a página do React
| — a recusa continuava acontecendo, mas deixava de ser visível para quem chama,
| e uma varredura de segurança leria 200 como sucesso.
|
| Só funções nativas aqui: arquivo de configuração é lido ANTES de os facades
| existirem, e `File::get()` morre com "A facade root has not been set".
|
*/

$padrao = ['/api', '/uploads', '/health', '/sistema', '/storage', '/sanctum', '/up'];

$contrato = base_path('../rotas-laravel.json');
$prefixos = null;

if (is_file($contrato)) {
    $lido = json_decode((string) file_get_contents($contrato), true);
    if (is_array($lido) && isset($lido['prefixos']) && is_array($lido['prefixos'])) {
        $prefixos = array_values(array_filter($lido['prefixos'], is_string(...)));
    }
}

return [

    /*
     * Na falta do arquivo (ou com ele corrompido), vale a lista mínima acima em
     * vez de lista vazia: errar para o lado do 404 é preferível a devolver HTML
     * do React para um cliente de API. `RoteadorMpaTest` garante que o arquivo
     * existe e está íntegro, então este caminho é rede, não rotina.
     */
    'prefixos_do_servidor' => $prefixos === null || $prefixos === [] ? $padrao : $prefixos,

];
