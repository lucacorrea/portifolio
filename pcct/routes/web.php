<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\PessoaController;

Route::get('professor/cadastromateriais', [MateriaisController::class, 'create']);
Route::post('professor/cadastromateriais', [MateriaisController::class, 'store']);
Route::post('professor/cadastro', [PessoaController::class, 'store']);
Route::get('professor/cadastro', [PessoaController::class, 'create']);

 Route::get('professor/cadastromateriais', function(){
     return view('professor.cadastromateriais');
 });

 //Mudar o nome da rota depois, criar outra rota pra os
 // materias, e futuramnete pra alunos e admim

 Route::get('aluno/cadastro', function(){
     return view('aluno/cadastro');
 });

 Route::get('admin/cadastro', function(){
     return view('admin/cadastro');
 });