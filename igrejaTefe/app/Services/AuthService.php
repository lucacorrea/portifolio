<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\Usuario;
use PDO;

final class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private ?Usuario $usuarios = null;

    public function __construct(?Usuario $usuarios = null)
    {
        $this->usuarios = $usuarios;
    }

    public function attempt(string $email, string $password, string $ip, string $userAgent): ?array
    {
        $email = strtolower(trim($email));

        if ($email === '' || $password === '') {
            return null;
        }

        if ($this->isLoginBlocked($email, $ip)) {
            return null;
        }

        $user = $this->usuarios()->findActiveByEmail($email);
        $validPassword = $user !== null && password_verify($password, (string) $user['senha_hash']);

        $this->recordLoginAttempt($email, $ip, $userAgent, $validPassword);

        if (!$validPassword || $user === null) {
            return null;
        }

        if (password_needs_rehash((string) $user['senha_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->usuarios()->updatePasswordHash((int) $user['id'], (int) $user['igreja_id'], $newHash);
        }

        $this->usuarios()->updateLastLogin((int) $user['id'], (int) $user['igreja_id']);

        return $user;
    }

    public function login(array $user): void
    {
        $sessionData = [
            'user_id' => (int) $user['id'],
            'igreja_id' => (int) $user['igreja_id'],
            'igreja_nome' => (string) ($user['igreja_nome'] ?? 'Igreja cadastrada'),
            'user_name' => (string) $user['nome'],
            'user_email' => (string) $user['email'],
            'user_role' => (string) $user['papel'],
            'authenticated_at' => time(),
            'last_activity_at' => time(),
        ];

        if (!empty($user['igreja_logo_url'])) {
            $sessionData['igreja_logo_url'] = (string) $user['igreja_logo_url'];
        }

        Session::regenerate();
        Session::putMany($sessionData);
    }

    public function logout(): void
    {
        Session::destroy();
    }

    private function isLoginBlocked(string $email, string $ip): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) AS total
             FROM tentativas_login
             WHERE sucesso = 0
               AND criado_em >= (CURRENT_TIMESTAMP - INTERVAL 15 MINUTE)
               AND (email = :email OR ip = :ip)'
        );

        $statement->bindValue('email', $email);
        $statement->bindValue('ip', $ip);
        $statement->execute();

        return (int) $statement->fetchColumn() >= self::MAX_FAILED_ATTEMPTS;
    }

    private function recordLoginAttempt(string $email, string $ip, string $userAgent, bool $success): void
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO tentativas_login (email, ip, sucesso, user_agent)
             VALUES (:email, :ip, :sucesso, :user_agent)'
        );

        $statement->execute([
            'email' => $email,
            'ip' => substr($ip, 0, 45),
            'sucesso' => $success ? 1 : 0,
            'user_agent' => substr($userAgent, 0, 255),
        ]);
    }

    private function usuarios(): Usuario
    {
        if (!$this->usuarios instanceof Usuario) {
            $this->usuarios = new Usuario();
        }

        return $this->usuarios;
    }

    private function pdo(): PDO
    {
        return Database::connection();
    }
}
