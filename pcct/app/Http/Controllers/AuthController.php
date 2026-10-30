<?php

namespace App\Http\Controllers;

use App\Models\Instituicao;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.entrar');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return back()->withErrors(['email' => 'E-mail ou senha inválidos.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        if (! in_array(Auth::user()->role, ['admin', 'professor', 'estudante'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Conta sem perfil de acesso configurado.']);
        }

        return redirect()->intended(route('painel'));
    }

    public function registerForm()
    {
        return view('auth.cadastro', ['instituicoes' => Instituicao::orderBy('nome')->get()]);
    }

    public function register(Request $request)
    {
        // O cadastro publico cria SOMENTE estudante. Professores sao criados pelo admin.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'instituicao_id' => ['nullable', 'integer', 'exists:instituicoes,id'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'estudante',
            ]);
            DB::table('estudantes')->insert([
                'user_id' => $user->id,
                'instituicao_id' => $data['instituicao_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('painel')->with('success', 'Cadastro realizado!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('catalogo');
    }
}
