<?php

declare(strict_types=1);

namespace App\Access\Service;

use App\Access\DTO\UserFormData;
use App\Access\DTO\UserListItem;
use App\Access\Entity\Profile;
use App\Access\Entity\User;
use App\Access\Repository\ProfileRepository;
use App\Access\Repository\UserRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final class UserManagementService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ProfileRepository $profiles
    ) {
    }

    /**
     * Retorna os usuários conforme os filtros informados.
     *
     * Filtros aceitos:
     *
     * search
     * status
     * profile_id
     *
     * @return UserListItem[]
     */
    public function listUsers(array $filters = []): array
    {
        return $this->users->findAllWithProfile(
            $this->normalizeFilters($filters)
        );
    }

    /**
     * Retorna os totais utilizados nos cards da listagem.
     *
     * @return array{
     *     total:int,
     *     active:int,
     *     inactive:int,
     *     blocked:int,
     *     temporary_locked:int
     * }
     */
    public function userSummary(): array
    {
        return $this->users->userSummary();
    }

    /**
     * Retorna somente perfis ativos.
     *
     * @return Profile[]
     */
    public function activeProfiles(): array
    {
        return array_values(
            array_filter(
                $this->profiles->findAll(),
                static fn (Profile $profile): bool =>
                    $profile->status() === 'ativo'
            )
        );
    }

    /**
     * Retorna um usuário cadastrado ou lança uma exceção.
     */
    public function getUser(int $userId): User
    {
        return $this->requireUser($userId);
    }

    /**
     * Cadastra um novo usuário.
     */
    public function createUser(UserFormData $data): int
    {
        $profile = $this->requireActiveProfile(
            $data->profileId()
        );

        $this->assertUniqueUsername(
            $data->username()
        );

        $this->assertUniqueEmail(
            $data->email()
        );

        $password = $data->password();

        if ($password === null || $password === '') {
            throw new InvalidArgumentException(
                'Senha e obrigatoria para cadastrar o usuario.'
            );
        }

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        if (
            !is_string($passwordHash)
            || trim($passwordHash) === ''
        ) {
            throw new RuntimeException(
                'Nao foi possivel proteger a senha do usuario.'
            );
        }

        $profileId = $profile->id();

        if ($profileId === null || $profileId <= 0) {
            throw new RuntimeException(
                'Perfil selecionado invalido.'
            );
        }

        $user = new User(
            id: null,
            profileId: $profileId,
            name: $data->name(),
            username: $data->username(),
            email: $data->email(),
            passwordHash: $passwordHash,
            phone: $data->phone(),
            status: $data->status(),
            mustChangePassword: $data->mustChangePassword(),
            failedAttempts: 0,
            lockedUntil: null,
            lastAccess: null,
            passwordChangedAt: (
                new DateTimeImmutable()
            )->format('Y-m-d H:i:s'),
            createdAt: null,
            updatedAt: null
        );

        return $this->users->create($user);
    }

    /**
     * Confirma se o nome de usuário está disponível.
     */
    public function usernameAvailable(
        string $username,
        ?int $ignoreUserId = null
    ): bool {
        $username = trim($username);

        if ($username === '') {
            return false;
        }

        return !$this->users->existsByUsername(
            $username,
            $ignoreUserId
        );
    }

    /**
     * Confirma se o e-mail está disponível.
     */
    public function emailAvailable(
        string $email,
        ?int $ignoreUserId = null
    ): bool {
        $email = strtolower(trim($email));

        if (
            $email === ''
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            return false;
        }

        return !$this->users->existsByEmail(
            $email,
            $ignoreUserId
        );
    }

    private function requireUser(int $userId): User
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException(
                'ID de usuario invalido.'
            );
        }

        $user = $this->users->findById($userId);

        if ($user === null) {
            throw new InvalidArgumentException(
                'Usuario nao encontrado.'
            );
        }

        return $user;
    }

    private function requireActiveProfile(
        int $profileId
    ): Profile {
        if ($profileId <= 0) {
            throw new InvalidArgumentException(
                'Perfil invalido.'
            );
        }

        $profile = $this->profiles->findById(
            $profileId
        );

        if ($profile === null) {
            throw new InvalidArgumentException(
                'Perfil nao encontrado.'
            );
        }

        if ($profile->status() !== 'ativo') {
            throw new InvalidArgumentException(
                'O perfil selecionado esta inativo.'
            );
        }

        return $profile;
    }

    private function assertUniqueUsername(
        string $username,
        ?int $ignoreUserId = null
    ): void {
        if (
            $this->users->existsByUsername(
                $username,
                $ignoreUserId
            )
        ) {
            throw new InvalidArgumentException(
                'Ja existe um usuario com este nome de acesso.'
            );
        }
    }

    private function assertUniqueEmail(
        string $email,
        ?int $ignoreUserId = null
    ): void {
        if (
            $this->users->existsByEmail(
                $email,
                $ignoreUserId
            )
        ) {
            throw new InvalidArgumentException(
                'Ja existe um usuario com este e-mail.'
            );
        }
    }

    /**
     * @return array{
     *     search:string,
     *     status:string,
     *     profile_id:int
     * }
     */
    private function normalizeFilters(array $filters): array
    {
        $search = trim(
            strip_tags(
                (string) ($filters['search'] ?? '')
            )
        );

        if (strlen($search) > 150) {
            $search = substr($search, 0, 150);
        }

        $status = (string) (
            $filters['status'] ?? ''
        );

        if (
            !in_array(
                $status,
                [
                    'ativo',
                    'inativo',
                    'bloqueado',
                ],
                true
            )
        ) {
            $status = '';
        }

        $profileId = filter_var(
            $filters['profile_id'] ?? 0,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        return [
            'search' => $search,
            'status' => $status,
            'profile_id' => $profileId === false
                ? 0
                : (int) $profileId,
        ];
    }
}