<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Inativação em vez de exclusão (ADR 12).
 *
 * Registro de pessoa, disciplina, conteúdo ou avaliação NÃO é apagado do banco.
 * O que sai do ar é inativado: a linha permanece íntegra, com quem tirou, quando
 * e por quê.
 *
 * Usa o `SoftDeletes` do Eloquent com a coluna renomeada para `inativadoEm`. A
 * escolha do SoftDeletes sobre uma coluna conferida à mão é o ponto da ADR: o
 * Eloquent exclui o inativo de TODA consulta automaticamente. Com filtro manual,
 * a proteção depende de cada uma das dezenas de listagens lembrar dele, e uma
 * esquecida exibe no site conteúdo que a escola tirou do ar.
 *
 * A palavra "delete" fica confinada ao framework. Código de domínio usa
 * `inativar()`, `reativar()` e `estaInativo()` — chamar `delete()` direto
 * funciona, mas não registra quem nem por quê, então não use.
 */
trait Inativavel
{
    use SoftDeletes;

    /** Nome da coluna que o SoftDeletes usa. */
    public const DELETED_AT = 'inativadoEm';

    /**
     * Tira do ar preservando o registro.
     *
     * `$porUserId` é a identidade de quem agiu (ADR 10) e `$motivo` é o que a
     * auditoria pergunta depois. Ambos opcionais na assinatura para não travar
     * chamada de rotina interna, mas toda ação originada de pessoa deve informar.
     */
    public function inativar(?string $porUserId = null, ?string $motivo = null): void
    {
        $this->setAttribute('inativadoPor', $porUserId);
        $this->setAttribute('motivoInativacao', $motivo);
        // Grava a autoria ANTES de inativar: depois de inativado o registro sai
        // das consultas padrão, e um save() posterior não o encontraria.
        $this->saveQuietly();
        $this->delete();
    }

    /** Devolve ao ar, limpando o registro da inativação anterior. */
    public function reativar(): void
    {
        $this->restore();
        $this->setAttribute('inativadoPor', null);
        $this->setAttribute('motivoInativacao', null);
        $this->saveQuietly();
    }

    public function estaInativo(): bool
    {
        return $this->trashed();
    }
}
