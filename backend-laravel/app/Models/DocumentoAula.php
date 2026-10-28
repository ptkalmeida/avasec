<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class DocumentoAula extends Model
{
    use Inativavel;

    protected $table = 'documento_aula';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'aula_id', 'titulo', 'tipo', 'url', 'tamanho'];
}
