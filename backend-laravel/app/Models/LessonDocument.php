<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class LessonDocument extends Model
{
    use Inativavel;

    protected $table = 'LessonDocument';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'lessonId', 'title', 'type', 'url', 'size'];
}
