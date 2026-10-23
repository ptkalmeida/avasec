<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\VersaoDeConteudo;
use App\Support\Fuso;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Histórico de versões do conteúdo que o responsável edita (ADR 021).
 *
 * Página do site, modelo de documento e aula eram SUBSTITUÍDOS ao salvar: o texto
 * anterior ficava perdido, o que a ADR 12 proíbe ("substituir registro em vez de
 * acrescentar"). Agora, antes de cada gravação que muda o conteúdo, o que existia
 * é guardado aqui. Só se acrescenta; restaurar uma versão é uma gravação como
 * qualquer outra, que por sua vez guarda o conteúdo que estava no ar.
 *
 * Quem chama passa o conteúdo ANTERIOR, já como lista de campos. Este serviço não
 * conhece as regras de cada tipo — só guarda, lista e devolve.
 */
final class VersoesDeConteudo
{
    public const PAGINA_DO_SITE = 'pagina_site';

    public const MODELO_DE_DOCUMENTO = 'modelo_documento';

    public const AULA = 'aula';

    /** Quantas versões a tela mostra. O banco guarda todas. */
    public const LIMITE_NA_TELA = 30;

    /**
     * @param  array<string, mixed>  $conteudo  o que existia antes da alteração
     */
    public function guardar(string $tipo, string $referencia, array $conteudo, ?string $porUserId, string $origem = 'edicao'): void
    {
        VersaoDeConteudo::query()->create([
            'id' => 'ver-'.Str::lower((string) Str::ulid()),
            'tipo' => $tipo,
            'referencia' => $referencia,
            'conteudo' => json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'origem' => $origem,
            'criadoPorUserId' => $porUserId,
            'criadoEm' => Carbon::now(),
        ]);
    }

    /**
     * As versões mais recentes primeiro.
     *
     * `porNome` é quem fez a alteração que SUBSTITUIU esta versão — ou seja, quem
     * a tirou do ar. Vem de `User` com `withTrashed`: a conta pode ter sido
     * inativada depois, e o histórico não pode perder o nome por isso.
     *
     * @return list<array{id:string,criadoEm:Carbon,origem:string,porNome:?string,conteudo:array<string, mixed>}>
     */
    public function listar(string $tipo, string $referencia, int $limite = self::LIMITE_NA_TELA): array
    {
        $linhas = VersaoDeConteudo::query()
            ->where('tipo', $tipo)
            ->where('referencia', $referencia)
            ->orderByDesc('criadoEm')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();

        $ids = $linhas->pluck('criadoPorUserId')->filter(static fn (mixed $v): bool => is_string($v))->unique()->values()->all();
        /** @var array<string, string> $nomes */
        $nomes = $ids === [] ? [] : User::query()->withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->all();

        $saida = [];
        foreach ($linhas as $linha) {
            $saida[] = [
                'id' => $linha->id,
                'criadoEm' => $linha->criadoEm ?? Carbon::now(),
                'origem' => $linha->origem,
                'porNome' => $linha->criadoPorUserId !== null ? ($nomes[$linha->criadoPorUserId] ?? null) : null,
                'conteudo' => self::decodificar($linha->conteudo),
            ];
        }

        return $saida;
    }

    /**
     * As versões prontas para a tela: data no fuso de exibição e um resumo de uma
     * linha, que quem chama sabe montar para o seu tipo de conteúdo.
     *
     * @param  callable(array<string, mixed>): string  $resumo
     * @return list<array{id:string,quando:string,porNome:?string,origem:string,resumo:string}>
     */
    public function paraTela(string $tipo, string $referencia, callable $resumo): array
    {
        $saida = [];
        foreach ($this->listar($tipo, $referencia) as $v) {
            $saida[] = [
                'id' => $v['id'],
                'quando' => Fuso::de($v['criadoEm'])->format('d/m/Y H:i'),
                'porNome' => $v['porNome'],
                'origem' => $v['origem'],
                'resumo' => $resumo($v['conteudo']),
            ];
        }

        return $saida;
    }

    /**
     * Uma versão, só se for DESTE item: o id de outra página ou de outra aula
     * nunca restaura em cima da errada.
     *
     * @return array<string, mixed>|null
     */
    public function obter(string $tipo, string $referencia, string $id): ?array
    {
        $linha = VersaoDeConteudo::query()
            ->where('tipo', $tipo)
            ->where('referencia', $referencia)
            ->whereKey($id)
            ->first();

        return $linha === null ? null : self::decodificar($linha->conteudo);
    }

    /** Quem está agindo na requisição em curso, para o model registrar a autoria. */
    public static function autorDaRequisicao(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $usuario = request()->attributes->get('auth_user');

        return is_array($usuario) && is_string($usuario['sub'] ?? null) ? $usuario['sub'] : null;
    }

    /** @return array<string, mixed> */
    private static function decodificar(string $json): array
    {
        $lido = json_decode($json, true);

        /** @var array<string, mixed> */
        return is_array($lido) ? $lido : [];
    }
}
