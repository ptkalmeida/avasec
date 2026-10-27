<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Avaliacao extends Model
{
    use Inativavel;

    protected $table = 'avaliacao';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'curso_id', 'titulo'];

    /** @return HasMany<QuestaoAvaliacao, $this> */
    public function questoes(): HasMany
    {
        return $this->hasMany(QuestaoAvaliacao::class, 'avaliacao_id');
    }
}
