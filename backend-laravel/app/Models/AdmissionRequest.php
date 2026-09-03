<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class AdmissionRequest extends Model
{
    use Inativavel;

    protected $table = 'AdmissionRequest';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'studentName', 'userId', 'courseId', 'status', 'submittedAt'];
}
