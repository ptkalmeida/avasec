<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use App\Services\VersoesDeConteudo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Aula extends Model
{
    use Inativavel;

    protected $table = 'aula';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'curso_id', 'titulo', 'duracao', 'url_video', 'conteudo', 'ordem'];

    protected $casts = ['ordem' => 'integer'];

    /**
     * Todo texto de aula que é SUBSTITUÍDO guarda antes a versão que ia se perder
     * (ADR 021). Fica aqui, no model, e não nos services, porque a aula é gravada
     * por vários caminhos (editor, API do curso, restauração) e um só esquecido
     * deixaria um buraco no histórico.
     */
    protected static function booted(): void
    {
        self::updating(static function (self $aula): void {
            $campos = ['titulo', 'duracao', 'url_video', 'conteudo'];
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

    /** @return HasMany<DocumentoAula, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoAula::class, 'aula_id');
    }
}
