<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use App\Services\VersoesDeConteudo;
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

    /**
     * Todo texto de aula que é SUBSTITUÍDO guarda antes a versão que ia se perder
     * (ADR 021). Fica aqui, no model, e não nos services, porque a aula é gravada
     * por vários caminhos (editor, API do curso, restauração) e um só esquecido
     * deixaria um buraco no histórico.
     */
    protected static function booted(): void
    {
        self::updating(static function (self $aula): void {
            $campos = ['title', 'duration', 'videoUrl', 'content'];
            $id = $aula->getKey();
            if (! is_string($id) || ! $aula->isDirty($campos)) {
                return;
            }

            $anterior = [];
            foreach ($campos as $campo) {
                $anterior[$campo] = $aula->getOriginal($campo);
            }

            app(VersoesDeConteudo::class)->guardar(
                VersoesDeConteudo::AULA,
                $id,
                $anterior,
                VersoesDeConteudo::autorDaRequisicao(),
            );
        });
    }

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
