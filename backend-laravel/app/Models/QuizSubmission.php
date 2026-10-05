<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Inativavel;
use Illuminate\Database\Eloquent\Model;

final class QuizSubmission extends Model
{
    use Inativavel;

    protected $table = 'QuizSubmission';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'studentName', 'userId', 'courseId', 'quizId', 'scorePercent', 'passed', 'submittedAt', 'enviadoEm'];

    protected $casts = [
        'scorePercent' => 'float',
        'passed' => 'boolean',
        /*
         * Eixo de ordenação das tentativas. `submittedAt` continua sendo o texto
         * que a tela exibe ('03/09/2026 às 16:23'), e ordenar por ele ordena
         * alfabeticamente — '01/12' antes de '03/09'.
         *
         * COM o deslocamento de fuso ('2026-09-03T20:00:00+00:00'), e isso é
         * essencial: a aplicação roda em UTC (`config('app.timezone')`) e o
         * navegador do aluno, não. Sem o sufixo, o JavaScript leria o valor como
         * hora local e uma tentativa recém-enviada pelo cliente pareceria três
         * horas MAIS ANTIGA que as do servidor — a "tentativa vigente" seria a
         * errada. Com o deslocamento, os dois lados falam do mesmo instante.
         */
        'enviadoEm' => 'datetime:Y-m-d\TH:i:sP',
    ];
}
