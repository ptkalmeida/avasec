<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class AcademicRequest extends Model
{
    use Inativavel;

    protected $table = 'AcademicRequest';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'studentName', 'userId', 'type', 'description', 'status', 'submittedAt', 'courseTitle'];
}
