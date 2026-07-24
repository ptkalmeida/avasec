<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LessonDocument extends Model
{
    protected $table = 'LessonDocument';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'lessonId', 'title', 'type', 'url', 'size'];
}
