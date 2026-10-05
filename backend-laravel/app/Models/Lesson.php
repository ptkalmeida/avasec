<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Lesson extends Model
{
    use Inativavel;

    protected $table = 'Lesson';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    // A coluna real é `lesson_order` (Prisma @map); o contrato expõe `order`.
    protected $fillable = ['id', 'courseId', 'title', 'duration', 'videoUrl', 'content', 'lesson_order'];

    protected $hidden = ['lesson_order'];

    protected $appends = ['order'];

    /** @return Attribute<int, never> */
    protected function order(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                $raw = $this->attributes['lesson_order'] ?? 0;

                return is_numeric($raw) ? (int) $raw : 0;
            },
        );
    }

    /** @return HasMany<LessonDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(LessonDocument::class, 'lessonId');
    }
}
