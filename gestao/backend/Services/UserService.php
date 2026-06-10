<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Security\Password;
use InvalidArgumentException;
use RuntimeException;

final class UserService
{
    private const ALLOWED_LEVELS = [
        'admin',
        'gerente',
        'operador',
        'estoquista',
        'leitor',
    ];

    public function __construct(private UserRepository $users)
    {
    }

    public function create(int $empresaId, array $data): int
    {
        $nome = trim((string)($data['nome'] ?? ''));
        $email = mb_strtolower(trim((string)($data['email'] ?? '')));
        $telefone = trim((string)($data['telefone'] ?? ''));
        $senha = (string)($data['senha'] ?? '');
        $nivel = (string)($data['nivel'] ?? 'operador');
        $ativo = isset($data['ativo']) ? (int)$data['ativo'] : 1;

        $this->validateNome($nome);
        $this->validateEmail($email);
        $this->validateSenhaCriacao($senha);
        $this->validateNivel($nivel);
        $this->validateAtivo($ativo);

        if ($this->users->emailExists($email, $empresaId)) {
            throw new InvalidArgumentException('Este e-mail já está cadastrado para esta empresa.');
        }

        return $this->users->create($empresaId, [
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone !== '' ? $telefone : null,
            'senha_hash' => Password::hash($senha),
            'nivel' => $nivel,
            'ativo' => $ativo,
        ]);
    }

    public function update(int $id, int $empresaId, array $data): bool
    {
        $user = $this->users->findById($id, $empresaId);

        if (!$user) {
            throw new RuntimeException('Usuário não encontrado.');
        }

        $nome = trim((string)($data['nome'] ?? ''));
        $email = mb_strtolower(trim((string)($data['email'] ?? '')));
        $telefone = trim((string)($data['telefone'] ?? ''));
        $nivel = (string)($data['nivel'] ?? 'operador');
        $ativo = isset($data['ativo']) ? (int)$data['ativo'] : 1;
        $senha = (string)($data['senha'] ?? '');

        $this->validateNome($nome);
        $this->validateEmail($email);
        $this->validateNivel($nivel);
        $this->validateAtivo($ativo);

        if ($this->users->emailExists($email, $empresaId, $id)) {
            throw new InvalidArgumentException('Este e-mail já está sendo usado por outro usuário.');
        }

        $updated = $this->users->update($id, $empresaId, [
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone !== '' ? $telefone : null,
            'nivel' => $nivel,
            'ativo' => $ativo,
        ]);

        if ($senha !== '') {
            $this->validateSenhaEdicao($senha);
            $this->users->updatePassword($id, $empresaId, Password::hash($senha));
        }

        return $updated;
    }

    public function activate(int $id, int $empresaId): bool
    {
        $user = $this->users->findById($id, $empresaId);

        if (!$user) {
            throw new RuntimeException('Usuário não encontrado.');
        }

        return $this->users->activate($id, $empresaId);
    }

    public function deactivate(int $id, int $empresaId, int $currentUserId): bool
    {
        if ($id === $currentUserId) {
            throw new InvalidArgumentException('Você não pode inativar o próprio usuário logado.');
        }

        $user = $this->users->findById($id, $empresaId);

        if (!$user) {
            throw new RuntimeException('Usuário não encontrado.');
        }

        return $this->users->deactivate($id, $empresaId);
    }

    public function allowedLevels(): array
    {
        return self::ALLOWED_LEVELS;
    }

    private function validateNome(string $nome): void
    {
        if ($nome === '') {
            throw new InvalidArgumentException('Informe o nome do usuário.');
        }

        if (mb_strlen($nome) < 3) {
            throw new InvalidArgumentException('O nome do usuário deve ter pelo menos 3 caracteres.');
        }

        if (mb_strlen($nome) > 140) {
            throw new InvalidArgumentException('O nome do usuário deve ter no máximo 140 caracteres.');
        }
    }

    private function validateEmail(string $email): void
    {
        if ($email === '') {
            throw new InvalidArgumentException('Informe o e-mail do usuário.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Informe um e-mail válido.');
        }

        if (mb_strlen($email) > 180) {
            throw new InvalidArgumentException('O e-mail deve ter no máximo 180 caracteres.');
        }
    }

    private function validateSenhaCriacao(string $senha): void
    {
        if ($senha === '') {
            throw new InvalidArgumentException('Informe a senha do usuário.');
        }

        $this->validateSenhaEdicao($senha);
    }

    private function validateSenhaEdicao(string $senha): void
    {
        if (mb_strlen($senha) < 6) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 6 caracteres.');
        }

        if (mb_strlen($senha) > 72) {
            throw new InvalidArgumentException('A senha deve ter no máximo 72 caracteres.');
        }
    }

    private function validateNivel(string $nivel): void
    {
        if (!in_array($nivel, self::ALLOWED_LEVELS, true)) {
            throw new InvalidArgumentException('Nível de acesso inválido.');
        }
    }

    private function validateAtivo(int $ativo): void
    {
        if (!in_array($ativo, [0, 1], true)) {
            throw new InvalidArgumentException('Status do usuário inválido.');
        }
    }
}