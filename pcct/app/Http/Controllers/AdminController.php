<?php

namespace App\Http\Controllers;

use App\Models\Disciplina;
use App\Models\HabilidadeBncc;
use App\Models\Instituicao;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', [
            'usuarios' => User::latest()->paginate(10),
            'instituicoes' => Instituicao::orderBy('nome')->get(),
            'disciplinas' => Disciplina::orderBy('nome')->get(),
            'habilidades' => HabilidadeBncc::with('disciplina')->orderBy('codigo')->get(),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in(['professor', 'estudante'])],
            'instituicao_id' => ['nullable', 'integer', 'exists:instituicoes,id'],
            'formacao' => ['nullable', 'string', 'max:150'],
        ]);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
            ]);
            $table = $data['role'] === 'professor' ? 'professores' : 'estudantes';
            $profile = [
                'user_id' => $user->id,
                'instituicao_id' => $data['instituicao_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($data['role'] === 'professor') {
                $profile['formacao'] = $data['formacao'] ?? null;
            }
            DB::table($table)->insert($profile);
        });

        return back()->with('success', 'Usuário cadastrado com sucesso.');
    }

    public function storeInstituicao(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'localidade' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
        Instituicao::create($data);
        return back()->with('success', 'Instituição cadastrada.');
    }

    public function storeDisciplina(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'area_conhecimento' => ['required', Rule::in([
                'Linguagens', 'Matemática', 'Ciências da Natureza', 'Ciências Humanas', 'Formação Técnica',
            ])],
        ]);
        Disciplina::create($data);
        return back()->with('success', 'Disciplina cadastrada.');
    }

    public function storeHabilidade(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:40', 'unique:habilidades_bncc,codigo'],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'ano_escolar' => ['nullable', 'string', 'max:80'],
            'disciplina_id' => ['required', 'exists:disciplinas,id'],
        ]);
        $data['codigo'] = strtoupper(trim($data['codigo']));
        HabilidadeBncc::create($data);
        return back()->with('success', 'Habilidade cadastrada. Confira o código com a BNCC oficial.');
    }
}
