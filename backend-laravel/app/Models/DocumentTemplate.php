<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

/**
 * PK = `type` — uma linha por tipo de documento (certificado, historico).
 */
final class DocumentTemplate extends Model
{
    use Inativavel;

    protected $table = 'DocumentTemplate';

    protected $primaryKey = 'type';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'type', 'institutionName', 'institutionLogoPath', 'signatories',
        'footerText', 'customHtml', 'updatedByUserId', 'updatedAt',
    ];

    protected $casts = [
        'signatories' => 'array',
        'updatedAt' => 'datetime',
    ];
}
