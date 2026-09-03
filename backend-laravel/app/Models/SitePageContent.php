<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * PK = `pageKey` — uma linha por página pública editável.
 */
final class SitePageContent extends Model
{
    use Inativavel;

    protected $table = 'SitePageContent';

    protected $primaryKey = 'pageKey';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'pageKey', 'content', 'updatedByUserId', 'updatedAt',
    ];

    protected $casts = [
        'content' => 'array',
        'updatedAt' => 'datetime',
    ];
}
