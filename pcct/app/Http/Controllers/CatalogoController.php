<?php

namespace App\Http\Controllers;

use App\Models\Disciplina;
use App\Models\ObjetoAprendizagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CatalogoController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'disciplina' => ['nullable', 'integer', 'exists:disciplinas,id'],
            'serie' => ['nullable', 'in:1,2,3'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'bncc' => ['nullable', 'string', 'max:40'],
        ]);

        $query = ObjetoAprendizagem::with(['disciplina', 'professor.user'])
            ->where('status', 'publicado')->latest();

        if ($q = ($filters['q'] ?? null)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('titulo', 'like', "%{$q}%")
                    ->orWhere('descricao', 'like', "%{$q}%")
                    ->orWhere('tematica', 'like', "%{$q}%")
                    ->orWhere('tags', 'like', "%{$q}%");
            });
        }
        if (! empty($filters['disciplina'])) {
            $query->where('disciplina_id', $filters['disciplina']);
        }
        if (! empty($filters['serie'])) {
            $query->where('ano_escolar', $filters['serie']);
        }
        if (! empty($filters['tipo'])) {
            $query->where('tipo_objeto', $filters['tipo']);
        }
        if (! empty($filters['bncc'])) {
            $code = $filters['bncc'];
            $query->whereHas('habilidades', function ($sub) use ($code) {
                $sub->where('codigo', 'like', "%{$code}%");
            });
        }

        return view('catalogo.index', [
            'materiais' => $query->paginate(9)->withQueryString(),
            'disciplinas' => Disciplina::orderBy('nome')->get(),
        ]);
    }

    public function show(ObjetoAprendizagem $material)
    {
        if ($material->status !== 'publicado') {
            $user = Auth::user();
            $canPreview = $user && ($user->role === 'admin'
                || ($user->role === 'professor' && $material->professor()
                    ->where('user_id', $user->id)->exists()));
            abort_unless($canPreview, 404);
        }

        $material->load(['disciplina', 'habilidades', 'professor.user']);
        return view('catalogo.show', compact('material'));
    }
}
