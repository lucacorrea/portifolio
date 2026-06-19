<?php
declare(strict_types=1);

namespace App\Core;

use App\Access\Repository\ProfilePermissionRepository;
use App\Access\Repository\ProfileRepository;
use App\Access\Repository\UserRepository;
use App\Access\Service\AuthenticationService;
use App\Access\Service\AuthorizationService;
use App\Security\CsrfTokenManager;
use App\Security\SafeRedirect;
use App\Security\SessionManager;

final class Application
{
    private ?SessionManager $session = null;
    private ?CsrfTokenManager $csrf = null;
    private ?AuthenticationService $authentication = null;
    private ?AuthorizationService $authorization = null;
    private ?SafeRedirect $redirect = null;

    public function __construct(
        private readonly Database $database,
        private readonly array $settings
    ) {
    }

    public function database(): Database
    {
        return $this->database;
    }

    public function session(): SessionManager
    {
        if ($this->session === null) {
            $secure = ($this->settings['app_env'] ?? 'production') === 'production'
                || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

            $this->session = new SessionManager(
                (string) ($this->settings['session_name'] ?? 'YKSESSID'),
                (int) ($this->settings['session_timeout'] ?? 1800),
                (int) ($this->settings['session_absolute_timeout'] ?? 28800),
                (int) ($this->settings['session_regenerate_interval'] ?? 900),
                (string) ($this->settings['session_cookie_path'] ?? '/YK'),
                $secure
            );
        }

        return $this->session;
    }

    public function csrf(): CsrfTokenManager
    {
        if ($this->csrf === null) {
            $this->csrf = new CsrfTokenManager($this->session());
        }

        return $this->csrf;
    }

    public function authentication(): AuthenticationService
    {
        if ($this->authentication === null) {
            $connection = $this->database->connection();
            $this->authentication = new AuthenticationService(
                new UserRepository($connection),
                new ProfileRepository($connection),
                new ProfilePermissionRepository($connection),
                $this->session(),
                (int) ($this->settings['login_max_attempts'] ?? 5),
                (int) ($this->settings['login_lock_minutes'] ?? 15)
            );
        }

        return $this->authentication;
    }

    public function authorization(): AuthorizationService
    {
        if ($this->authorization === null) {
            $this->authorization = new AuthorizationService($this->authentication());
        }

        return $this->authorization;
    }

    public function redirect(): SafeRedirect
    {
        if ($this->redirect === null) {
            $this->redirect = new SafeRedirect();
        }

        return $this->redirect;
    }
}
