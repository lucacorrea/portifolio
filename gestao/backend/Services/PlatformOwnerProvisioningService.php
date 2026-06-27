<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\UserCompanyRepository;
use App\Repositories\UserRepository;
use PDO;

final class PlatformOwnerProvisioningService
{
    private PDO $db;
    private PlatformAuthorizationService $platform;
    private UserRepository $users;
    private UserCompanyRepository $memberships;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
        $this->users = new UserRepository($this->db);
        $this->memberships = new UserCompanyRepository($this->db);
        $this->platform = new PlatformAuthorizationService($this->users);
    }

    public function synchronizeOwnerAccess(): void
    {
        $stmt = $this->db->query('SELECT id FROM empresas');
        $companyIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        foreach ($companyIds as $empresaId) {
            $this->linkExistingOwnersToCompany($empresaId);
        }
    }

    public function synchronizeUserAccess(int $usuarioId): void
    {
        if (!$this->platform->isPlatformOwner($usuarioId)) {
            return;
        }

        $stmt = $this->db->query('SELECT id FROM empresas');
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $empresaId) {
            $this->memberships->createMembership($usuarioId, (int)$empresaId, 'admin', false);
        }
    }

    public function linkExistingOwnersToCompany(int $empresaId): void
    {
        $owners = $this->users->findExistingOwnersByEmails($this->platform->configuredOwnerEmails());

        foreach ($owners as $owner) {
            if ((int)($owner['ativo'] ?? 0) !== 1) {
                continue;
            }

            $this->memberships->createMembership((int)$owner['id'], $empresaId, 'admin', false);
        }
    }

    public function missingOwnerEmails(): array
    {
        $configured = $this->platform->configuredOwnerEmails();
        $existing = array_map(
            static fn (array $owner): string => mb_strtolower(trim((string)$owner['email'])),
            $this->users->findExistingOwnersByEmails($configured)
        );

        return array_values(array_diff($configured, $existing));
    }
}
