<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * Vínculo de um gestor adicional com um curso (além do responsável em
 * `curso.instrutor_id`). Inativar o vínculo o desfaz sem apagar a linha (ADR 12).
 *
 * @property int $id
 * @property string $curso_id
 * @property string $usuario_id
 */
final class CursoGestor extends Model
{
    use Inativavel;

    protected $table = 'curso_gestor';

    public $timestamps = false;

    protected $fillable = ['curso_id', 'usuario_id'];
}
