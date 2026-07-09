<?php

use App\Http\Controllers\PainelController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PainelController::class, 'exibir'])->name('painel');
Route::post('/alunos/importar', [PainelController::class, 'importarAlunos'])->name('alunos.importar');
Route::post('/aprovados/comparar', [PainelController::class, 'compararAprovados'])->name('aprovados.comparar');
Route::get('/relatorio/baixar', [PainelController::class, 'baixarRelatorio'])->name('relatorio.baixar');
