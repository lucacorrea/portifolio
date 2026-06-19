<?php
declare(strict_types=1);

namespace App\Access\Service;

use App\Access\DTO\AuthenticatedUser;
use App\Access\DTO\LoginResult;
use App\Access\Exception\AuthenticationException;
use App\Access\Repository\ProfilePermissionRepository;
use App\Access\Repository\ProfileRepository;
use App\Access\Repository\UserRepository;
use App\Security\SessionManager;
use DateTimeImmutable;
use Throwable;

final class AuthenticationService
{
    private ?AuthenticatedUser $currentUser = null;
    private bool $currentUserResolved = false;

    public function __construct(
        private readonly UserRepository $users,
        private readonly ProfileRepository $profiles,
        private readonly ProfilePermissionRepository $profilePermissions,
        private readonly SessionManager $session,
        private readonly int $maxAttempts = 5,
        private readonly int $lockMinutes = 15
    ) {
    }

    public function attempt(string $identifier, string $password): LoginResult
    {
        $identifier = trim($identifier);
        $password = (string) $password;

        if ($identifier === '' || $password === '' || strlen($identifier) > 150 || strlen($password) > 4096) {
            return new LoginResult(false, 'Usuário ou senha inválidos.');
        }

        $user = $this->users->findByIdentifier($identifier);
        if ($user === null || $user->id() === null) {
            password_verify($password, password_hash(random_bytes(16), PASSWORD_DEFAULT));
            return new LoginResult(false, 'Usuário ou senha inválidos.');
        }

        if ($user->status() !== 'ativo') {
            return new LoginResult(false, 'Usuário ou senha inválidos.');
        }

        if ($this->isTemporarilyLocked($user->lockedUntil())) {
            return new LoginResult(false, 'Não foi possível realizar o acesso neste momento. Aguarde alguns minutos e tente novamente.');
        }

        $profile = $this->profiles->findById($user->profileId());
        if ($profile === null || $profile->status() !== 'ativo') {
            return new LoginResult(false, 'Usuário ou senha inválidos.');
        }

        if (!password_verify($password, $user->passwordHash())) {
            $attempts = $user->failedAttempts() + 1;
            $lockedUntil = null;

            if ($attempts >= $this->maxAttempts) {
                $lockedUntil = (new DateTimeImmutable())->modify('+' . $this->lockMinutes . ' minutes');
            }

            $this->users->registerFailedAttempt($user->id(), $attempts, $lockedUntil);

            if ($lockedUntil !== null) {
                return new LoginResult(false, 'Não foi possível realizar o acesso neste momento. Aguarde alguns minutos e tente novamente.');
            }

            return new LoginResult(false, 'Usuário ou senha inválidos.');
        }

        $now = new DateTimeImmutable();

        if (password_needs_rehash($user->passwordHash(), PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash($user->id(), password_hash($password, PASSWORD_DEFAULT), $now);
        }

        $this->users->resetFailedAttempts($user->id());
        $this->users->updateLastAccess($user->id(), $now);
        $this->session->authenticate($user->id());

        $authenticatedUser = new AuthenticatedUser(
            $user->id(),
            $user->profileId(),
            $profile->name(),
            $user->name(),
            $user->username(),
            $user->email(),
            $this->profilePermissions->findPermissionCodesByProfile($user->profileId())
        );

        $this->currentUser = $authenticatedUser;
        $this->currentUserResolved = true;

        return new LoginResult(true, 'Acesso realizado com sucesso.', $authenticatedUser);
    }

    public function logout(): void
    {
        $this->currentUser = null;
        $this->currentUserResolved = true;
        $this->session->destroy();
    }

    public function isAuthenticated(): bool
    {
        return $this->currentUser() !== null;
    }

    public function currentUser(): ?AuthenticatedUser
    {
        if ($this->currentUserResolved) {
            return $this->currentUser;
        }

        $this->currentUserResolved = true;
        $this->currentUser = null;

        if (!$this->session->isAuthenticated() || !$this->session->refreshActivity()) {
            return null;
        }

        $userId = $this->session->userId();
        if ($userId === null) {
            return null;
        }

        try {
            $user = $this->users->findById($userId);
            if ($user === null || $user->id() === null || $user->status() !== 'ativo') {
                $this->invalidateCurrentSession();
                return null;
            }

            $profile = $this->profiles->findById($user->profileId());
            if ($profile === null || $profile->status() !== 'ativo') {
                $this->invalidateCurrentSession();
                return null;
            }

            $this->currentUser = new AuthenticatedUser(
                $user->id(),
                $user->profileId(),
                $profile->name(),
                $user->name(),
                $user->username(),
                $user->email(),
                $this->profilePermissions->findPermissionCodesByProfile($user->profileId())
            );

            return $this->currentUser;
        } catch (Throwable $exception) {
            $this->invalidateCurrentSession();
            return null;
        }
    }

    public function requireAuthenticatedUser(): AuthenticatedUser
    {
        $user = $this->currentUser();
        if ($user === null) {
            throw new AuthenticationException('Login requerido.');
        }

        return $user;
    }

    private function isTemporarilyLocked(?string $lockedUntil): bool
    {
        if ($lockedUntil === null || trim($lockedUntil) === '') {
            return false;
        }

        try {
            return new DateTimeImmutable($lockedUntil) > new DateTimeImmutable();
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function invalidateCurrentSession(): void
    {
        $this->currentUser = null;
        $this->session->destroy();
        $this->session->flash('warning', 'Não foi possível manter o acesso ao sistema. Entre em contato com o administrador.');
    }
}
