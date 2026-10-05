<?php

declare(strict_types=1);

use App\Http\Controllers\SaudeController;
use Illuminate\Support\Facades\Route;

/*
 * Saúde e status (Norma Técnica TI-SECEC, C.8).
 *
 * Carregado FORA do grupo "web" (ver bootstrap/app.php): estes endereços são
 * chamados por monitor e balanceador a cada poucos segundos, e o grupo web
 * abriria uma sessão — um arquivo gravado em disco — a cada verificação.
 */
Route::get('/health/live', [SaudeController::class, 'live']);
Route::get('/health/ready', [SaudeController::class, 'ready']);
Route::get('/sistema/status', [SaudeController::class, 'status']);
