<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Environment;
use App\Core\Logger;
use App\Core\Validator;
use PDO;
use RuntimeException;

final class FirstAdminService
{
    public function isAvailable(): bool
    {
        return Environment::bool('INSTALLATION_ENABLED', false)
            && !$this->lockExists()
            && $this->countAdministrators() === 0;
    }

    /** @param array{nome:string,cpf:string,email:string,senha:string} $data */
    public function create(array $data): int
    {
        if (!Environment::bool('INSTALLATION_ENABLED', false)) {
            throw new RuntimeException('Instalação desativada.');
        }

        if ($this->lockExists()) {
            throw new RuntimeException('Instalação bloqueada.');
        }

        $name = trim($data['nome']);
        $cpf = Validator::onlyDigits($data['cpf']);
        $email = mb_strtolower(trim($data['email']));
        $password = $data['senha'];

        if ($name === '' || !Validator::cpf($cpf) || !Validator::email($email) || !Validator::strongPassword($password)) {
            throw new RuntimeException('Dados inválidos.');
        }

        $hashAlgorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $passwordHash = password_hash($password, $hashAlgorithm);

        if ($passwordHash === false) {
            throw new RuntimeException('Senha não pôde ser processada.');
        }

        $userId = Database::transaction(function (PDO $pdo) use ($name, $cpf, $email, $passwordHash): int {
            if ($this->countAdministrators($pdo) > 0) {
                throw new RuntimeException('Administrador inicial já existe.');
            }

            $sectorId = $pdo->query("SELECT id FROM setores WHERE slug = 'administracao-sistema' AND ativo = 1 LIMIT 1")->fetchColumn();
            $levelId = $pdo->query("SELECT id FROM niveis_acesso WHERE slug = 'administrador' AND ativo = 1 LIMIT 1")->fetchColumn();

            if (!$sectorId || !$levelId) {
                throw new RuntimeException('Dados iniciais ausentes.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO usuarios
                    (setor_id, nivel_id, nome, cpf, email, senha_hash, status, precisa_trocar_senha, aprovado_em, criado_em)
                 VALUES
                    (:setor_id, :nivel_id, :nome, :cpf, :email, :senha_hash, :status, 1, NOW(), NOW())'
            );

            $statement->execute([
                'setor_id' => (int) $sectorId,
                'nivel_id' => (int) $levelId,
                'nome' => $name,
                'cpf' => $cpf,
                'email' => $email,
                'senha_hash' => $passwordHash,
                'status' => 'ativo',
            ]);

            $userId = (int) $pdo->lastInsertId();

            $audit = $pdo->prepare(
                'INSERT INTO auditoria (usuario_id, usuario_alvo_id, acao, modulo, descricao, ip, user_agent, criado_em)
                 VALUES (:usuario_id, :usuario_alvo_id, :acao, :modulo, :descricao, :ip, :user_agent, NOW())'
            );

            $audit->execute([
                'usuario_id' => $userId,
                'usuario_alvo_id' => $userId,
                'acao' => 'primeiro_administrador_criado',
                'modulo' => 'instalacao',
                'descricao' => 'Primeiro administrador criado por instalador temporário.',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);

            return $userId;
        });

        $this->createLockFile();

        return $userId;
    }

    public function installationKeyMatches(?string $providedKey): bool
    {
        $expected = Environment::required('INSTALLATION_KEY');

        return is_string($providedKey) && $providedKey !== '' && hash_equals($expected, $providedKey);
    }

    public function lockExists(): bool
    {
        $lockPath = $this->lockPath();

        return is_file($lockPath);
    }

    private function countAdministrators(?PDO $pdo = null): int
    {
        $pdo ??= Database::connection();

        $count = $pdo->query(
            "SELECT COUNT(*)
             FROM usuarios u
             INNER JOIN niveis_acesso n ON n.id = u.nivel_id
             WHERE n.slug = 'administrador'
               AND u.excluido_em IS NULL"
        )->fetchColumn();

        return (int) $count;
    }

    private function createLockFile(): void
    {
        $lockPath = $this->lockPath();
        $directory = dirname($lockPath);

        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Lock não pôde ser criado.');
        }

        $content = 'SIGAS installation locked at ' . date('c') . PHP_EOL;

        if (file_put_contents($lockPath, $content, LOCK_EX) === false) {
            throw new RuntimeException('Lock não pôde ser criado.');
        }
    }

    private function lockPath(): string
    {
        $path = Environment::required('INSTALLATION_LOCK_PATH');

        if (str_contains($path, "\0") || str_contains($path, '://') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            Logger::security('Invalid installation lock path configuration.');
            throw new RuntimeException('Lock inválido.');
        }

        return $path;
    }
}
