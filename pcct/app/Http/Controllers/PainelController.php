<?php

namespace App\Http\Controllers;

use App\Models\ObjetoAprendizagem;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PainelController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $stats = [];

        if ($user->role === 'admin') {
            $stats = [
                'Usuários' => User::count(),
                'Materiais publicados' => ObjetoAprendizagem::where('status', 'publicado')->count(),
                'Rascunhos' => ObjetoAprendizagem::where('status', 'rascunho')->count(),
            ];
        } elseif ($user->role === 'professor') {
            $professorId = DB::table('professores')->where('user_id', $user->id)->value('id');
            $stats = [
                'Meus materiais' => ObjetoAprendizagem::where('professor_id', $professorId)->count(),
                'Publicados' => ObjetoAprendizagem::where('professor_id', $professorId)->where('status', 'publicado')->count(),
                'Rascunhos' => ObjetoAprendizagem::where('professor_id', $professorId)->where('status', 'rascunho')->count(),
            ];
        } else {
            $stats = [
                'Materiais disponíveis' => ObjetoAprendizagem::where('status', 'publicado')->count(),
            ];
        }

        return view('painel.index', compact('user', 'stats'));
    }
}
