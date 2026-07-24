<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SystemSettings extends Model
{
    protected $table = 'SystemSettings';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'data'];

    protected $casts = ['data' => 'array'];
}
