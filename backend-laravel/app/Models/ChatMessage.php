<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class ChatMessage extends Model
{
    use Inativavel;

    protected $table = 'ChatMessage';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'sessionId', 'senderName', 'senderUserId', 'senderRole', 'text', 'timestamp'];
}
