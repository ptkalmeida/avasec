<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ForumMessage extends Model
{
    protected $table = 'ForumMessage';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'courseId', 'senderName', 'senderUserId', 'senderRole', 'text', 'timestamp', 'likes', 'likedBy'];

    protected $casts = ['likes' => 'integer', 'likedBy' => 'array'];
}
