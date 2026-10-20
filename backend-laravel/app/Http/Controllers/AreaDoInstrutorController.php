<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PainelDoInstrutor;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Área do instrutor em Blade — fase M4 do plano 16.
 *
 * Como na área do aluno, nenhuma regra de negócio mora aqui: posse de curso,
 * escopo de alunos e o que cada papel alcança continuam nos services que a API
 * usa. Migrar a tela não pode mudar quem alcança o quê.
 */
final class AreaDoInstrutorController extends Controller
{
    public function __construct(private readonly PainelDoInstrutor $painel) {}

    /**
     * `/inst` — a escolha de curso, e a fila do que há para fazer.
     *
     * Esta tela existe porque grade, avaliações e alunos são sempre de UM
     * curso. O painel React nasceu sem ela: `selectedCourseId` começava no
     * primeiro curso da lista, então quem entrava já estava dentro de um curso
     * que não escolheu, e as abas operavam sobre ele.
     */
    public function escolha(Request $request): View
    {
        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        return view('instrutor.escolha', [
            'cursos' => $this->painel->cursos($instrutor),
            'aFazer' => $this->painel->aFazer($instrutor),
        ]);
    }
}
