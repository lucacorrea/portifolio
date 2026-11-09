<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PainelController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogoController::class, 'index'])->name('catalogo');
Route::get('/materiais/{material}', [CatalogoController::class, 'show'])->name('materiais.show');
Route::get('/materiais/{material}/midia/{tipo}', [CatalogoController::class, 'media'])->where('tipo', 'arquivo|capa')->name('materiais.midia');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/cadastro', [AuthController::class, 'registerForm'])->name('cadastro');
    Route::post('/cadastro', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('cadastro.store');
});
Route::post('/sair', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/painel', [PainelController::class, 'index'])->name('painel');

    Route::middleware('role:professor')->prefix('professor')->name('professor.')->group(function () {
        Route::get('/materiais', [MaterialController::class, 'index'])->name('materiais.index');
        Route::get('/materiais/novo', [MaterialController::class, 'create'])->name('materiais.create');
        Route::post('/materiais', [MaterialController::class, 'store'])->name('materiais.store');
        Route::get('/materiais/{material}/editar', [MaterialController::class, 'edit'])->name('materiais.edit');
        Route::put('/materiais/{material}', [MaterialController::class, 'update'])->name('materiais.update');
        Route::delete('/materiais/{material}', [MaterialController::class, 'destroy'])->name('materiais.destroy');
        // Compatibilidade com a URL da antiga tela de cadastro de materiais.
        Route::get('/cadastromateriais', [MaterialController::class, 'create']);
        Route::post('/cadastromateriais', [MaterialController::class, 'store']);
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::post('/usuarios', [AdminController::class, 'storeUser'])->name('usuarios.store');
        Route::post('/instituicoes', [AdminController::class, 'storeInstituicao'])->name('instituicoes.store');
        Route::post('/disciplinas', [AdminController::class, 'storeDisciplina'])->name('disciplinas.store');
        Route::post('/habilidades', [AdminController::class, 'storeHabilidade'])->name('habilidades.store');
    });
});
