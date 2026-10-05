<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class PracticalExercise extends Model
{
    use Inativavel;

    protected $table = 'PracticalExercise';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'courseId', 'title', 'description', 'instructions', 'maxPoints', 'dueDate'];

    protected $casts = ['maxPoints' => 'integer'];
}
