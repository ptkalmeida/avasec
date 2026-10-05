<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Escada de audiência do conteúdo (ADR 12).
 *
 * O número é o PAPEL MÍNIMO para ver, então quem vê o nível 1 vê tudo acima:
 *
 *   1 publicado  aluno vê (e gestor e admin)
 *   2 restrito   gestor e admin — pronto, mas não chega ao aluno
 *   3 rascunho   só admin — em elaboração
 *
 * Não existe nível "excluído" aqui de propósito. Existir e quem-pode-ver são
 * perguntas diferentes: a primeira é respondida por `inativadoEm` (ver
 * App\Models\Concerns\Inativavel). Um valor 0 nesta escada faria o número
 * significar duas coisas e criaria duas fontes para o mesmo fato.
 *
 * Aviso de projeto: `Course.statusCurso` é um campo de texto legado com os
 * valores 'Ativo' e NULL que NENHUMA consulta filtra — decoração. Todo valor
 * acrescentado a esta escada tem de ter um consumidor, ou terá o mesmo destino.
 */
final class Visibilidade
{
    public const PUBLICADO = 1;

    public const RESTRITO = 2;

    public const RASCUNHO = 3;

    /** @return list<int> */
    public static function niveis(): array
    {
        return [self::PUBLICADO, self::RESTRITO, self::RASCUNHO];
    }

    /**
     * Nível máximo que um papel alcança. Anônimo e aluno chegam só ao publicado.
     *
     * O instrutor é tratado como gestão porque é ele quem prepara o material:
     * precisa ver o próprio conteúdo restrito. O escopo de QUAIS cursos ele
     * alcança continua sendo de InstructorScope — esta escada não substitui
     * pertencimento, só diz o que está no ar para cada papel.
     */
    public static function nivelMaximoDoPapel(?string $role): int
    {
        return match ($role) {
            'admin' => self::RASCUNHO,
            'instructor' => self::RESTRITO,
            default => self::PUBLICADO,
        };
    }

    /**
     * Restringe a consulta ao que aquele papel pode ver.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicar(Builder $query, ?string $role): void
    {
        $query->where('status', '<=', self::nivelMaximoDoPapel($role));
    }

    /** O papel alcança aquele nível de visibilidade. */
    public static function papelAlcanca(?string $role, int $status): bool
    {
        return $status <= self::nivelMaximoDoPapel($role);
    }

    /** Normaliza o valor recebido do cliente, recusando nível inventado. */
    public static function normalizar(mixed $valor): int
    {
        if (! is_numeric($valor)) {
            return self::PUBLICADO;
        }
        $n = (int) $valor;

        return in_array($n, self::niveis(), true) ? $n : self::PUBLICADO;
    }
}
