<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DirectMessage extends Model
{
    protected $table = 'DirectMessage';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'studentName', 'studentUserId', 'senderName', 'senderUserId', 'senderRole', 'text', 'timestamp'];
}
