<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Uma emissão do histórico escolar de um aluno: o que o admin emitiu e o aluno baixa.
 *
 * @property int $id
 * @property string $usuario_id
 * @property string|null $emitido_por
 * @property Carbon $emitido_em
 * @property array{nome_aluno: string, linhas: list<array{curso: string, carga_horaria: string, frequencia: string, conclusao: string}>} $dados
 */
final class HistoricoEmitido extends Model
{
    use Inativavel;

    protected $table = 'historico_emitido';

    public $timestamps = false;

    protected $fillable = ['usuario_id', 'emitido_por', 'emitido_em', 'dados'];

    protected $casts = [
        'dados' => 'array',
        'emitido_em' => 'datetime',
    ];
}
