<?php
declare(strict_types=1);

namespace App\Access\Service;

use App\Access\DTO\ProfileDetails;
use App\Access\DTO\ProfileFormData;
use App\Access\DTO\ProfileListItem;
use App\Access\Entity\Permission;
use App\Access\Entity\Profile;
use App\Access\Repository\PermissionRepository;
use App\Access\Repository\ProfilePermissionRepository;
use App\Access\Repository\ProfileRepository;
use App\Access\Repository\UserRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class ProfileManagementService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly ProfileRepository $profiles,
        private readonly PermissionRepository $permissions,
        private readonly ProfilePermissionRepository $profilePermissions,
        private readonly UserRepository $users
    ) {
    }

    /**
     * @return ProfileListItem[]
     */
    public function listProfiles(array $filters = []): array
    {
        return $this->profiles->findAllWithStatistics($this->normalizeFilters($filters));
    }

    /**
     * @return array{total:int,active:int,inactive:int,users:int}
     */
    public function profileSummary(): array
    {
        $profiles = $this->profiles->findAllWithStatistics();
        $summary = [
            'total' => count($profiles),
            'active' => 0,
            'inactive' => 0,
            'users' => 0,
        ];

        foreach ($profiles as $profile) {
            if ($profile->status() === 'ativo') {
                $summary['active']++;
            } else {
                $summary['inactive']++;
            }

            $summary['users'] += $profile->totalUsers();
        }

        return $summary;
    }

    public function getProfile(int $profileId): ProfileDetails
    {
        $profile = $this->requireProfile($profileId);

        if ($profile->isProtected()) {
            $this->ensureProtectedProfilePermissions($profile);
        }

        $permissionIds = $profile->isProtected()
            ? $this->permissions->findActiveIds()
            : $this->profilePermissions->findPermissionIdsByProfile($profileId);

        return new ProfileDetails(
            $profile,
            $permissionIds,
            $this->users->countByProfile($profileId),
            count($permissionIds)
        );
    }

    /**
     * @return array<string, Permission[]>
     */
    public function groupedActivePermissions(): array
    {
        return $this->permissions->findAllActiveGrouped();
    }

    /**
     * @return int[]
     */
    public function permissionIdsForProfile(int $profileId): array
    {
        $profile = $this->requireProfile($profileId);

        return $profile->isProtected()
            ? $this->permissions->findActiveIds()
            : $this->profilePermissions->findPermissionIdsByProfile($profileId);
    }

    /**
     * @return array<string, string>
     */
    public function permissionDependencies(): array
    {
        return $this->buildPermissionDependencies($this->permissions->findAllActive());
    }

    /**
     * @param int[] $permissionIds
     */
    public function createProfile(ProfileFormData $data, array $permissionIds): int
    {
        if ($this->profiles->nameExists($data->name())) {
            throw new InvalidArgumentException('Já existe um perfil com este nome.');
        }

        $permissionIds = $this->normalizePermissionSelection($permissionIds);

        return $this->withinTransaction(function () use ($data, $permissionIds): int {
            $profileId = $this->profiles->create(new Profile(
                null,
                $data->name(),
                $data->description(),
                false,
                $data->status()
            ));

            $this->profilePermissions->sync($profileId, $permissionIds);

            return $profileId;
        });
    }

    public function updateProfile(int $profileId, ProfileFormData $data): void
    {
        $profile = $this->requireProfile($profileId);

        if ($profile->isProtected()) {
            if ($data->name() !== $profile->name() || $data->status() !== $profile->status()) {
                throw new InvalidArgumentException('O perfil Administrador é protegido.');
            }
        }

        if ($this->profiles->nameExists($data->name(), $profileId)) {
            throw new InvalidArgumentException('Já existe um perfil com este nome.');
        }

        $this->profiles->update(new Profile(
            $profileId,
            $profile->isProtected() ? $profile->name() : $data->name(),
            $data->description(),
            $profile->isProtected(),
            $profile->isProtected() ? $profile->status() : $data->status(),
            $profile->createdAt(),
            $profile->updatedAt()
        ));
    }

    /**
     * @param int[] $permissionIds
     */
    public function syncPermissions(int $profileId, array $permissionIds): void
    {
        $profile = $this->requireProfile($profileId);

        if ($profile->isProtected()) {
            $this->ensureProtectedProfilePermissions($profile);
            throw new InvalidArgumentException('O perfil Administrador é protegido.');
        }

        $permissionIds = $this->normalizePermissionSelection($permissionIds);

        $this->withinTransaction(function () use ($profileId, $permissionIds): void {
            $this->lockProfile($profileId);
            $this->profilePermissions->sync($profileId, $permissionIds);
        });
    }

    public function duplicateProfile(int $sourceProfileId, string $newName, ?string $description): int
    {
        $sourceProfile = $this->requireProfile($sourceProfileId);
        $data = ProfileFormData::fromArray([
            'name' => $newName,
            'description' => $description,
            'status' => 'ativo',
        ]);

        if ($this->profiles->nameExists($data->name())) {
            throw new InvalidArgumentException('Já existe um perfil com este nome.');
        }

        return $this->withinTransaction(function () use ($sourceProfile, $data): int {
            $sourceId = $sourceProfile->id();
            if ($sourceId === null) {
                throw new RuntimeException('Perfil de origem invalido.');
            }

            $profileId = $this->profiles->create(new Profile(
                null,
                $data->name(),
                $data->description(),
                false,
                'ativo'
            ));

            $permissionIds = $sourceProfile->isProtected()
                ? $this->permissions->findActiveIds()
                : $this->normalizePermissionSelection($this->profilePermissions->findPermissionIdsByProfile($sourceId));

            $this->profilePermissions->sync($profileId, $permissionIds);

            return $profileId;
        });
    }

    public function activateProfile(int $profileId): void
    {
        $this->changeStatus($profileId, 'ativo');
    }

    public function deactivateProfile(int $profileId): void
    {
        $this->changeStatus($profileId, 'inativo');
    }

    public function deleteProfile(int $profileId): void
    {
        $profile = $this->requireProfile($profileId);

        if ($profile->isProtected()) {
            throw new InvalidArgumentException('O perfil Administrador é protegido.');
        }

        if ($this->users->countByProfile($profileId) > 0) {
            throw new InvalidArgumentException('Este perfil não pode ser excluído porque possui usuários vinculados.');
        }

        $this->withinTransaction(function () use ($profileId): void {
            $this->lockProfile($profileId);
            $this->profiles->delete($profileId);
        });
    }

    private function changeStatus(int $profileId, string $status): void
    {
        $profile = $this->requireProfile($profileId);

        if ($profile->isProtected() && $status !== 'ativo') {
            throw new InvalidArgumentException('O perfil Administrador é protegido.');
        }

        $this->profiles->changeStatus($profileId, $status);
    }

    private function requireProfile(int $profileId): Profile
    {
        if ($profileId <= 0) {
            throw new InvalidArgumentException('ID invalido.');
        }

        $profile = $this->profiles->findById($profileId);
        if ($profile === null) {
            throw new InvalidArgumentException('Perfil nao encontrado.');
        }

        return $profile;
    }

    /**
     * @param int[] $permissionIds
     * @return int[]
     */
    private function normalizePermissionSelection(array $permissionIds): array
    {
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
        $permissionIds = array_values(array_filter($permissionIds, static fn (int $id): bool => $id > 0));

        if ($permissionIds === []) {
            return [];
        }

        $permissions = $this->permissions->findActiveByIds($permissionIds);
        if (count($permissions) !== count($permissionIds)) {
            throw new InvalidArgumentException('Permissao invalida.');
        }

        $idByCode = [];
        $selectedCodes = [];
        foreach ($permissions as $permission) {
            $id = $permission->id();
            if ($id === null) {
                continue;
            }

            $idByCode[$permission->code()] = $id;
            $selectedCodes[$permission->code()] = true;
        }

        $allActivePermissions = $this->permissions->findAllActive();
        foreach ($allActivePermissions as $permission) {
            $id = $permission->id();
            if ($id !== null) {
                $idByCode[$permission->code()] = $id;
            }
        }

        $dependencies = $this->buildPermissionDependencies($allActivePermissions);
        foreach (array_keys($selectedCodes) as $code) {
            if (isset($dependencies[$code], $idByCode[$dependencies[$code]])) {
                $selectedCodes[$dependencies[$code]] = true;
            }
        }

        $resolvedIds = [];
        foreach (array_keys($selectedCodes) as $code) {
            if (isset($idByCode[$code])) {
                $resolvedIds[] = $idByCode[$code];
            }
        }

        sort($resolvedIds);

        return $resolvedIds;
    }

    /**
     * @param Permission[] $permissions
     * @return array<string, string>
     */
    private function buildPermissionDependencies(array $permissions): array
    {
        $activeCodes = [];
        foreach ($permissions as $permission) {
            $activeCodes[$permission->code()] = true;
        }

        $dependencies = [];
        foreach ($permissions as $permission) {
            $code = $permission->code();
            [$module, $action] = explode('.', $code, 2);
            $viewCode = $module . '.visualizar';

            if ($action !== 'visualizar' && isset($activeCodes[$viewCode])) {
                $dependencies[$code] = $viewCode;
            }
        }

        return $dependencies;
    }

    private function ensureProtectedProfilePermissions(Profile $profile): void
    {
        $profileId = $profile->id();
        if ($profileId === null || !$profile->isProtected()) {
            return;
        }

        $activeIds = $this->permissions->findActiveIds();
        $this->withinTransaction(function () use ($profileId, $activeIds): void {
            $this->lockProfile($profileId);
            $this->profilePermissions->insertIgnoreMany($profileId, $activeIds);
        });
    }

    private function lockProfile(int $profileId): void
    {
        $statement = $this->connection->prepare('SELECT id FROM perfis WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $profileId]);

        if ($statement->fetchColumn() === false) {
            throw new InvalidArgumentException('Perfil nao encontrado.');
        }
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'search' => trim(strip_tags((string) ($filters['search'] ?? ''))),
            'status' => in_array(($filters['status'] ?? ''), ['ativo', 'inativo'], true) ? (string) $filters['status'] : '',
            'type' => in_array(($filters['type'] ?? ''), ['protegido', 'personalizado'], true) ? (string) $filters['type'] : '',
        ];
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function withinTransaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->connection->inTransaction();

        try {
            if ($ownsTransaction) {
                $this->connection->beginTransaction();
            }

            $result = $callback();

            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
