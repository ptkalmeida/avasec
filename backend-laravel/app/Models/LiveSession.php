<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LiveSession extends Model
{
    protected $table = 'LiveSession';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'courseId', 'title', 'scheduledAt', 'durationMinutes', 'meetingLink', 'isLive'];

    protected $casts = [
        'durationMinutes' => 'integer',
        'isLive' => 'boolean',
    ];
}
