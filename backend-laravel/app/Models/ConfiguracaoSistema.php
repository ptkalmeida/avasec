<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ConfiguracaoSistema extends Model
{
    protected $table = 'configuracao_sistema';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'dados'];

    protected $casts = ['dados' => 'array'];
}
