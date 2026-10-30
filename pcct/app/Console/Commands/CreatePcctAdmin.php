<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreatePcctAdmin extends Command
{
    protected $signature = 'pcct:admin {email?}';
    protected $description = 'Cria um administrador PCCT de forma interativa (sem senha fixa no codigo).';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?: $this->ask('E-mail do administrador'));
        $name = (string) $this->ask('Nome completo');
        $password = (string) $this->secret('Senha (mínimo 12 caracteres)');
        $confirmation = (string) $this->secret('Repita a senha');

        $validator = Validator::make(compact('email', 'name', 'password'), [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails() || $password !== $confirmation) {
            $this->error('Dados inválidos, e-mail existente ou senhas diferentes.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $name, $password) {
            $user = User::create([
                'name' => $name, 'email' => $email,
                'password' => Hash::make($password), 'role' => 'admin',
            ]);
            DB::table('administradores')->insert([
                'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->info('Administrador criado. Acesse /entrar.');
        return self::SUCCESS;
    }
}
