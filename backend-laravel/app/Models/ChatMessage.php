<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ChatMessage extends Model
{
    protected $table = 'ChatMessage';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'sessionId', 'senderName', 'senderUserId', 'senderRole', 'text', 'timestamp'];
}
