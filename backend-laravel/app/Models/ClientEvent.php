<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ClientEvent extends Model
{
    protected $table = 'ClientEvent';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'createdAt', 'user', 'role', 'ipAddress', 'device', 'action', 'details', 'status'];

    protected $casts = ['createdAt' => 'datetime'];
}
