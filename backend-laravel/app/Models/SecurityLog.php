<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SecurityLog extends Model
{
    protected $table = 'SecurityLog';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'timestamp', 'user', 'role', 'ipAddress', 'device', 'action', 'details', 'status'];
}
