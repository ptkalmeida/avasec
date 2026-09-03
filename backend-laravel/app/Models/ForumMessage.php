<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class ForumMessage extends Model
{
    use Inativavel;

    protected $table = 'ForumMessage';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'courseId', 'senderName', 'senderUserId', 'senderRole', 'text', 'timestamp', 'likes', 'likedBy'];

    protected $casts = ['likes' => 'integer', 'likedBy' => 'array'];
}
