<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Environment;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Validator;
use App\Domain\UserStatus;
use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Repositories\AccessLevelRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use DateTimeImmutable;
use Throwable;

final class AuthService
{
    private const GENERIC_LOGIN_ERROR = 'CPF, e-mail ou senha inválidos.';

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserSessionRepository $sessions,
        private readonly AccessLevelRepository $accessLevels,
        private readonly AuditService $audit,
    ) {
    }

    public function attempt(string $identity, string $password): User
    {
        Session::start();

        $identity = trim($identity);

        if ($identity === '' || trim($password) === '') {
            throw new AuthenticationException(self::GENERIC_LOGIN_ERROR);
        }

        $user = $this->users->findByIdentity($identity);

        if (!$user instanceof User) {
            $this->auditAuthentication(null, null, 'login_falhou', 'Identificação não localizada.');
            throw new AuthenticationException(self::GENERIC_LOGIN_ERROR);
        }

        $now = new DateTimeImmutable();
        $currentAttempts = $user->tentativasLogin;

        if ($user->bloqueadoAte !== null) {
            if ($user->bloqueadoAte > $now) {
                $this->auditAuthentication($user->id, $user->id, 'login_bloqueado_temporariamente');
                throw new AuthenticationException('Muitas tentativas de acesso. Tente novamente mais tarde.');
            }

            $this->users->clearExpiredLoginLock($user->id);
            $currentAttempts = 0;
        }

        if (!password_verify($password, $user->getPasswordHashForVerification())) {
            $nextAttempts = $currentAttempts + 1;
            $blockedUntil = $nextAttempts >= $this->maxAttempts()
                ? new DateTimeImmutable('+' . $this->lockMinutes() . ' minutes')
                : null;

            $this->users->registerFailedLogin($user->id, $nextAttempts, $blockedUntil);
            $this->auditAuthentication(
                $user->id,
                $user->id,
                $blockedUntil === null ? 'login_falhou' : 'login_bloqueado_temporariamente'
            );

            throw new AuthenticationException($blockedUntil === null
                ? self::GENERIC_LOGIN_ERROR
                : 'Muitas tentativas de acesso. Tente novamente mais tarde.');
        }

        $this->assertAllowedAfterPassword($user);
        $this->assertActiveLevel($user);

        Session::regenerate();
        $sessionIdentifier = session_id();
        $expiresAt = new DateTimeImmutable('+' . $this->sessionLifetime() . ' seconds');

        try {
            Database::transaction(function () use ($user, $sessionIdentifier, $expiresAt): void {
                $this->users->registerSuccessfulLogin($user->id, $this->clientIp());
                $this->sessions->create(
                    $user->id,
                    $sessionIdentifier,
                    $this->clientIp(),
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    $expiresAt
                );
                $this->auditAuthentication($user->id, $user->id, 'login_sucesso');
            });
        } catch (Throwable $exception) {
            Session::destroy();
            Logger::application('Login transaction failed.', ['type' => $exception::class, 'code' => $exception->getCode()]);
            throw new AuthenticationException('Não foi possível autenticar agora. Tente novamente.');
        }

        $_SESSION['auth_user_id'] = $user->id;
        $_SESSION['auth_authorization_version'] = $user->versaoAutorizacao;
        $_SESSION['auth_login_at'] = time();

        return $user;
    }

    public function currentUser(): ?User
    {
        Session::start();

        $userId = $_SESSION['auth_user_id'] ?? null;
        $version = $_SESSION['auth_authorization_version'] ?? null;

        if (!is_int($userId) || !is_int($version)) {
            return null;
        }

        $user = $this->users->findById($userId);

        if (!$user instanceof User
            || $user->excluidoEm !== null
            || $user->status !== UserStatus::ACTIVE
            || $user->versaoAutorizacao !== $version
            || !$this->hasActiveLevel($user)) {
            $this->invalidateSession($user?->id, 'sessao_invalida');
            return null;
        }

        $session = $this->sessions->findActiveByIdentifier(session_id());

        if ($session === null || $session->usuarioId !== $user->id) {
            $this->invalidateSession($user->id, 'sessao_invalida');
            return null;
        }

        $this->sessions->touch(session_id());

        return $user;
    }

    public function requireUser(): User
    {
        $user = $this->currentUser();

        if ($user instanceof User) {
            return $user;
        }

        header('Location: index.php');
        exit;
    }

    public function logout(): void
    {
        Session::start();
        $userId = is_int($_SESSION['auth_user_id'] ?? null) ? $_SESSION['auth_user_id'] : null;

        $this->sessions->revoke(session_id());
        $this->auditAuthentication($userId, $userId, 'logout');
        Session::destroy();
    }

    private function assertAllowedAfterPassword(User $user): void
    {
        match ($user->status) {
            UserStatus::ACTIVE => null,
            UserStatus::PENDING => $this->denyStatus($user, 'login_conta_pendente', 'Sua solicitação de acesso ainda aguarda aprovação.'),
            UserStatus::REJECTED => $this->denyStatus($user, 'login_conta_rejeitada', 'Sua solicitação de acesso não foi aprovada.'),
            UserStatus::BLOCKED => $this->denyStatus($user, 'login_conta_bloqueada', 'Sua conta está bloqueada. Procure o suporte.'),
            UserStatus::INACTIVE => $this->denyStatus($user, 'login_conta_inativa', 'Sua conta está inativa. Procure o suporte.'),
        };
    }

    private function denyStatus(User $user, string $action, string $message): never
    {
        $this->auditAuthentication($user->id, $user->id, $action);
        throw new AuthenticationException($message);
    }

    private function assertActiveLevel(User $user): void
    {
        if (!$this->hasActiveLevel($user)) {
            throw new AuthenticationException('Sua conta não possui um nível de acesso válido. Procure o suporte.');
        }
    }

    private function hasActiveLevel(User $user): bool
    {
        if ($user->nivelId === null) {
            return false;
        }

        $level = $this->accessLevels->findById($user->nivelId);

        return $level !== null && $level->ativo;
    }

    private function invalidateSession(?int $userId, string $action): void
    {
        try {
            $this->sessions->revoke(session_id());
            $this->auditAuthentication($userId, $userId, $action);
        } catch (Throwable $exception) {
            Logger::application('Session invalidation failed.', ['type' => $exception::class, 'code' => $exception->getCode()]);
        }

        Session::destroy();
    }

    private function auditAuthentication(?int $userId, ?int $targetUserId, string $action, ?string $description = null): void
    {
        try {
            $this->audit->record($userId, $targetUserId, $action, 'autenticacao', $description);
        } catch (Throwable $exception) {
            Logger::application('Authentication audit failed.', ['action' => $action, 'type' => $exception::class, 'code' => $exception->getCode()]);
        }
    }

    private function maxAttempts(): int
    {
        $value = Environment::int('LOGIN_MAX_ATTEMPTS', 5);

        return $value > 0 ? $value : 5;
    }

    private function lockMinutes(): int
    {
        $value = Environment::int('LOGIN_LOCK_MINUTES', 15);

        return $value > 0 ? $value : 15;
    }

    private function sessionLifetime(): int
    {
        $value = Environment::int('SESSION_LIFETIME', 7200);

        return $value > 0 ? $value : 7200;
    }

    private function clientIp(): ?string
    {
        return isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? mb_substr($_SERVER['REMOTE_ADDR'], 0, 45)
            : null;
    }
}
