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
    ) {}

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
                static fn(Profile $profile): bool =>
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

    /**
     * Atualiza os dados cadastrais de um usuário.
     */
    public function updateUser(
        int $userId,
        UserFormData $data
    ): void {
        $existingUser = $this->requireUser($userId);

        $profile = $this->requireActiveProfile(
            $data->profileId()
        );

        $this->assertUniqueUsername(
            $data->username(),
            $userId
        );

        $this->assertUniqueEmail(
            $data->email(),
            $userId
        );

        $profileId = $profile->id();

        if ($profileId === null || $profileId <= 0) {
            throw new RuntimeException(
                'Perfil selecionado invalido.'
            );
        }

        $this->assertAdministratorContinuity(
            $existingUser,
            $profileId,
            $data->status()
        );

        $updatedUser = new User(
            id: $userId,
            profileId: $profileId,
            name: $data->name(),
            username: $data->username(),
            email: $data->email(),
            passwordHash: $existingUser->passwordHash(),
            phone: $data->phone(),
            status: $data->status(),
            mustChangePassword: $data->mustChangePassword(),
            failedAttempts: $existingUser->failedAttempts(),
            lockedUntil: $existingUser->lockedUntil(),
            lastAccess: $existingUser->lastAccess(),
            passwordChangedAt: $existingUser->passwordChangedAt(),
            createdAt: $existingUser->createdAt(),
            updatedAt: $existingUser->updatedAt()
        );

        $this->users->update($updatedUser);

        /*
     * Na edição, a senha é opcional.
     * Quando preenchida, substitui a senha atual.
     */
        if ($data->hasPassword()) {
            $password = $data->password();

            if ($password === null) {
                throw new RuntimeException(
                    'Senha invalida.'
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
                    'Nao foi possivel proteger a senha.'
                );
            }

            $this->users->resetPassword(
                $userId,
                $passwordHash,
                $data->mustChangePassword()
            );
        }
    }

    /**
     * Altera o status do usuário.
     */
    public function changeUserStatus(
        int $userId,
        string $status
    ): void {
        $user = $this->requireUser($userId);

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
            throw new InvalidArgumentException(
                'Status de usuario invalido.'
            );
        }

        if ($user->status() === $status) {
            return;
        }

        /*
     * Um usuário só pode ser ativado quando seu perfil
     * também estiver ativo.
     */
        if ($status === 'ativo') {
            $this->requireActiveProfile(
                $user->profileId()
            );
        }

        $this->assertAdministratorContinuity(
            $user,
            $user->profileId(),
            $status
        );

        $this->users->changeStatus(
            $userId,
            $status
        );

        /*
     * Ao reativar, remove tentativas falhas e bloqueio
     * temporário anteriores.
     */
        if ($status === 'ativo') {
            $this->users->unlock($userId);
        }
    }

    /**
     * Remove somente o bloqueio temporário causado por
     * tentativas falhas de login.
     */
    public function unlockUser(int $userId): void
    {
        $this->requireUser($userId);

        $this->users->unlock($userId);
    }

    /**
     * Redefine administrativamente a senha de um usuário.
     */
    public function resetUserPassword(
        int $userId,
        string $password,
        string $confirmation,
        bool $mustChangePassword = true
    ): void {
        $this->requireUser($userId);

        $password = (string) $password;
        $confirmation = (string) $confirmation;

        if (
            $password === ''
            || $confirmation === ''
        ) {
            throw new InvalidArgumentException(
                'Informe a nova senha e a confirmacao.'
            );
        }

        if (str_contains($password, "\0")) {
            throw new InvalidArgumentException(
                'Senha invalida.'
            );
        }

        $length = strlen($password);

        if ($length < 8 || $length > 72) {
            throw new InvalidArgumentException(
                'A senha deve ter entre 8 e 72 caracteres.'
            );
        }

        if (!preg_match('/[A-Za-z]/', $password)) {
            throw new InvalidArgumentException(
                'A senha deve conter pelo menos uma letra.'
            );
        }

        if (!preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException(
                'A senha deve conter pelo menos um numero.'
            );
        }

        if (!hash_equals($password, $confirmation)) {
            throw new InvalidArgumentException(
                'A confirmacao da senha nao corresponde.'
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
                'Nao foi possivel proteger a nova senha.'
            );
        }

        $this->users->resetPassword(
            $userId,
            $passwordHash,
            $mustChangePassword
        );
    }

    /**
     * Impede que o último Administrador ativo seja
     * desativado, bloqueado ou movido para outro perfil.
     */
    private function assertAdministratorContinuity(
        User $existingUser,
        int $newProfileId,
        string $newStatus
    ): void {
        /*
     * A proteção somente se aplica a administradores
     * que estejam ativos atualmente.
     */
        if ($existingUser->status() !== 'ativo') {
            return;
        }

        $currentProfile = $this->profiles->findById(
            $existingUser->profileId()
        );

        if (
            $currentProfile === null
            || $currentProfile->name() !== 'Administrador'
        ) {
            return;
        }

        $newProfile = $this->profiles->findById(
            $newProfileId
        );

        $willRemainActiveAdministrator = (
            $newStatus === 'ativo'
            && $newProfile !== null
            && $newProfile->name() === 'Administrador'
        );

        if ($willRemainActiveAdministrator) {
            return;
        }

        if (
            $this->users->countActiveAdministrators() <= 1
        ) {
            throw new InvalidArgumentException(
                'O ultimo Administrador ativo nao pode ser desativado, bloqueado ou removido do perfil Administrador.'
            );
        }
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
