<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class ExerciseSubmission extends Model
{
    use Inativavel;

    protected $table = 'ExerciseSubmission';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'exerciseId', 'studentName', 'userId', 'submissionText', 'fileUrl', 'fileName',
        'submittedAt', 'status', 'score', 'feedback', 'gradedAt', 'gradedBy',
    ];

    protected $casts = ['score' => 'integer'];
}
