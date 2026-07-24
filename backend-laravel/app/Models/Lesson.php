<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Lesson extends Model
{
    protected $table = 'Lesson';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    // A coluna real é `lesson_order` (Prisma @map); o contrato expõe `order`.
    protected $fillable = ['id', 'courseId', 'title', 'duration', 'videoUrl', 'content', 'lesson_order'];

    protected $hidden = ['lesson_order'];

    protected $appends = ['order'];

    protected function order(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) $this->attributes['lesson_order'],
        );
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LessonDocument::class, 'lessonId');
    }
}
