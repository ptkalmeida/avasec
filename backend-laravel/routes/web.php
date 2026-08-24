<?php

use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Arquivos públicos de upload (materiais, imagens, vídeos de teste) — equivalente ao
// express.static('/uploads', ...) do Node legado. Sem autenticação, mesmo comportamento
// de antes; anti-traversal e checagem de existência ficam em UploadService::resolvePublicFile.
Route::get('/uploads/{filename}', [UploadController::class, 'servePublic'])
    ->where('filename', '[^\\/\\\\]+');
