<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\CompanyAuditRepository;
use App\Repositories\MatrixRepository;
use App\Repositories\UserCompanyRepository;
use App\Repositories\UserRepository;
use App\Security\Password;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class MatrixAdminService
{
    private PDO $db;
    private MatrixRepository $matrices;
    private UserRepository $users;
    private UserCompanyRepository $memberships;
    private CompanyAuditRepository $audit;
    private PlatformAuthorizationService $platform;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
        $this->matrices = new MatrixRepository($this->db);
        $this->users = new UserRepository($this->db);
        $this->memberships = new UserCompanyRepository($this->db);
        $this->audit = new CompanyAuditRepository($this->db);
        $this->platform = new PlatformAuthorizationService($this->users, $this->audit);
    }

    public function findPrimaryAdmin(int $matrizId): ?array
    {
        $matrix = $this->matrices->findMatrixById($matrizId);
        if (!$matrix || empty($matrix['admin_principal_usuario_id'])) {
            return null;
        }

        return $this->users->findById((int)$matrix['admin_principal_usuario_id'], $matrizId);
    }

    public function isPrimaryAdmin(int $usuarioId, int $matrizId): bool
    {
        $matrix = $this->matrices->findMatrixById($matrizId);
        if (!$matrix || (int)($matrix['ativo'] ?? 0) !== 1) {
            return false;
        }

        if ((int)($matrix['admin_principal_usuario_id'] ?? 0) !== $usuarioId) {
            return false;
        }

        $membership = $this->memberships->findMembership($usuarioId, $matrizId);

        return $membership !== null
            && (int)$membership['usuario_ativo'] === 1
            && (int)$membership['vinculo_ativo'] === 1
            && (int)$membership['empresa_ativa'] === 1
            && (string)$membership['nivel'] === 'admin';
    }

    public function assertPrimaryAdmin(int $usuarioId, int $matrizId): void
    {
        if (!$this->isPrimaryAdmin($usuarioId, $matrizId)) {
            throw new RuntimeException('Você não possui autorização para administrar esta matriz.');
        }
    }

    public function assignPrimaryAdmin(int $platformUserId, int $matrizId, string $adminEmail, array $newUserData = []): array
    {
        return $this->setPrimaryAdmin($platformUserId, $matrizId, $adminEmail, $newUserData, false);
    }

    public function replacePrimaryAdmin(int $platformUserId, int $matrizId, string $adminEmail, array $newUserData = []): array
    {
        return $this->setPrimaryAdmin($platformUserId, $matrizId, $adminEmail, $newUserData, true);
    }

    public function assignPrimaryAdminInsideTransaction(
        int $platformUserId,
        int $matrizId,
        string $adminEmail,
        array $newUserData = [],
        bool $replace = false
    ): array {
        $this->platform->assertPlatformOwner($platformUserId);

        $matrix = $this->matrices->findMatrixById($matrizId);
        if (!$matrix || (string)$matrix['tipo'] !== 'matriz' || $matrix['empresa_pai_id'] !== null) {
            throw new RuntimeException('Matriz inválida.');
        }

        $email = $this->normalizeEmail($adminEmail);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Informe um e-mail válido para o administrador principal.');
        }

        if ($this->platform->isPlatformOwnerEmail($email)) {
            throw new InvalidArgumentException('Este e-mail é reservado para a administração da plataforma.');
        }

        $user = $this->users->findIdentityByEmail($email);
        $created = false;

        if (!$user) {
            $nome = trim((string)($newUserData['nome'] ?? ''));
            $senha = (string)($newUserData['senha'] ?? '');
            $senhaConfirmacao = (string)($newUserData['senha_confirmacao'] ?? '');

            if (mb_strlen($nome) < 3) {
                throw new InvalidArgumentException('Informe o nome do administrador principal.');
            }

            if (mb_strlen($senha) < 6 || mb_strlen($senha) > 72 || $senha !== $senhaConfirmacao) {
                throw new InvalidArgumentException('Informe e confirme uma senha inicial válida para o administrador principal.');
            }

            $userId = $this->users->create($matrizId, [
                'nome' => $nome,
                'email' => $email,
                'telefone' => trim((string)($newUserData['telefone'] ?? '')) ?: null,
                'senha_hash' => Password::hash($senha),
                'nivel' => 'admin',
                'ativo' => 1,
            ]);
            $user = $this->users->findIdentityById($userId);
            $created = true;
        }

        if (!$user || (int)$user['ativo'] !== 1) {
            throw new RuntimeException('O administrador principal precisa estar ativo.');
        }

        $userId = (int)$user['id'];
        $this->memberships->createMembership($userId, $matrizId, 'admin', true);
        $this->matrices->assignPrimaryAdmin($matrizId, $userId);
        $this->audit->record(
            $platformUserId,
            $matrizId,
            $matrizId,
            $replace ? 'alterar_admin_matriz' : 'definir_admin_matriz',
            ['admin_usuario_id' => $userId, 'admin_email' => $email, 'usuario_criado' => $created]
        );

        return $this->users->findById($userId, $matrizId) ?? $user;
    }

    private function setPrimaryAdmin(int $platformUserId, int $matrizId, string $adminEmail, array $newUserData, bool $replace): array
    {
        $this->db->beginTransaction();
        try {
            $admin = $this->assignPrimaryAdminInsideTransaction($platformUserId, $matrizId, $adminEmail, $newUserData, $replace);
            $this->db->commit();

            return $admin;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
