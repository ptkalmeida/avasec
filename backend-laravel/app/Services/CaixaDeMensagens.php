<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\AssuntoDaAula;
use App\Support\Fuso;
use Carbon\CarbonImmutable;

/**
 * As conversas entre aluno e gestor, prontas para a tela.
 *
 * Quem lê do banco continua sendo o `MessagingService`, que é o mesmo da API e
 * já aplica o escopo: o instrutor só alcança mensagens dos alunos dos cursos
 * dele (`InstructorScope::studentIds`), e o aluno só as próprias. Este serviço
 * não refaz nenhuma dessas consultas — ele agrupa, ordena e separa o assunto
 * da aula, que é trabalho de apresentação.
 *
 * "Aguardando resposta" é calculado, não guardado: a conversa espera quando a
 * última mensagem é do aluno. Sem coluna nova, sem marcar nada como lido, e
 * sem a possibilidade de o estado divergir do que está escrito.
 */
final class CaixaDeMensagens
{
    public function __construct(private readonly MessagingService $mensagens) {}

    /**
     * As conversas que chegam ao gestor, a que espera resposta primeiro.
     *
     * @param  array{sub:string,nome:string,papel:string}  $instrutor
     * @return list<array{alunoId:string,nome:string,quantas:int,aguardando:bool,ultima:array{texto:string,aula:string|null,quando:string,deQuem:string}}>
     */
    public function conversas(array $instrutor): array
    {
        $porAluno = [];
        foreach ($this->mensagens->listDirectMessages($instrutor, null) as $linha) {
            $alunoId = is_string($linha['aluno_usuario_id'] ?? null) ? $linha['aluno_usuario_id'] : null;
            if ($alunoId === null) {
                continue;
            }
            $porAluno[$alunoId][] = $linha;
        }

        $conversas = [];
        foreach ($porAluno as $alunoId => $linhas) {
            $ultima = $linhas[count($linhas) - 1];
            $conversas[] = [
                'alunoId' => $alunoId,
                'nome' => is_string($ultima['nome_aluno'] ?? null) ? $ultima['nome_aluno'] : 'Aluno',
                'quantas' => count($linhas),
                'aguardando' => ($ultima['papel_remetente'] ?? null) === 'student',
                'ultima' => $this->paraTela($ultima),
            ];
        }

        /*
         * Quem espera vem primeiro, e dentro de cada grupo a conversa mais
         * recente. Ordenar só por data poria no alto uma conversa que o gestor
         * acabou de responder, empurrando para baixo quem está esperando.
         */
        usort($conversas, static function (array $a, array $b): int {
            if ($a['aguardando'] !== $b['aguardando']) {
                return $a['aguardando'] ? -1 : 1;
            }

            return strcmp($b['ultima']['ordenacao'], $a['ultima']['ordenacao']);
        });

        return $conversas;
    }

    /**
     * Uma conversa inteira, na ordem em que foi escrita.
     *
     * O escopo é o do `MessagingService`: pedir o aluno de outro instrutor
     * devolve lista vazia, e não a conversa alheia.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return list<array{texto:string,aula:string|null,quando:string,deQuem:string,autor:string}>
     */
    public function conversa(array $requester, ?string $alunoId): array
    {
        $linhas = $this->mensagens->listDirectMessages($requester, $alunoId);

        return array_map(fn (array $linha): array => $this->paraTela($linha), array_values($linhas));
    }

    /**
     * Quantas conversas esperam resposta — o número da fila "A fazer".
     *
     * @param  array{sub:string,nome:string,papel:string}  $instrutor
     */
    public function quantasAguardam(array $instrutor): int
    {
        return count(array_filter($this->conversas($instrutor), static fn (array $c): bool => $c['aguardando']));
    }

    /**
     * @param  array<string, mixed>  $linha
     * @return array{texto:string,aula:string|null,quando:string,deQuem:string,autor:string,ordenacao:string}
     */
    private function paraTela(array $linha): array
    {
        $bruto = is_string($linha['texto'] ?? null) ? $linha['texto'] : '';
        $separado = AssuntoDaAula::separar($bruto);

        return [
            'texto' => $separado['texto'],
            'aula' => $separado['aula'],
            'quando' => $this->quando($linha),
            'deQuem' => is_string($linha['papel_remetente'] ?? null) ? $linha['papel_remetente'] : '',
            'autor' => is_string($linha['nome_remetente'] ?? null) ? $linha['nome_remetente'] : '',
            'ordenacao' => is_string($linha['enviada_em'] ?? null) ? $linha['enviada_em'] : (is_string($linha['id'] ?? null) ? $linha['id'] : ''),
        ];
    }

    /**
     * Data e hora para uma pessoa ler, no fuso de exibição.
     *
     * `enviada_em` é a coluna de instante (UTC) e é a fonte; `timestamp` é texto
     * antigo, guardado em formatos diferentes ao longo do tempo, e só serve de
     * último recurso.
     *
     * @param  array<string, mixed>  $linha
     */
    private function quando(array $linha): string
    {
        $instante = $linha['enviada_em'] ?? null;
        if (is_string($instante) && $instante !== '') {
            try {
                return Fuso::de(new CarbonImmutable($instante))->format('d/m/Y H:i');
            } catch (\Throwable) {
                // Texto que não é instante cai no ramo de baixo.
            }
        }

        return is_string($linha['registrado_em_texto'] ?? null) ? $linha['registrado_em_texto'] : '';
    }
}
