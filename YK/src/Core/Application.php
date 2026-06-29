<?php

declare(strict_types=1);

namespace App\Core;

use App\Access\Repository\PermissionRepository;
use App\Access\Repository\ProfilePermissionRepository;
use App\Access\Repository\ProfileRepository;
use App\Access\Repository\UserRepository;
use App\Access\Service\AuthenticationService;
use App\Access\Service\AuthorizationService;
use App\Access\Service\ProfileManagementService;
use App\Access\Service\UserManagementService;
use App\Catalog\Repository\ProductRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\Service\ProductManagementService;
use App\Catalog\Service\ServiceManagementService;
use App\CRM\Repository\ClientRepository;
use App\CRM\Service\ClientManagementService;
use App\Security\CsrfTokenManager;
use App\Security\SafeRedirect;
use App\Security\SessionManager;
use App\Schedule\Repository\AgendaReminderRepository;
use App\Schedule\Service\AgendaManagementService;
use App\ServiceOrder\Repository\ServiceOrderRepository;
use App\ServiceOrder\Service\ServiceOrderManagementService;
use App\Sales\Repository\BudgetRepository;
use App\Sales\Service\BudgetManagementService;
use App\Workforce\Repository\EmployeeRepository;
use App\Workforce\Service\EmployeeManagementService;

final class Application
{
    private ?SessionManager $session = null;

    private ?CsrfTokenManager $csrf = null;

    private ?AuthenticationService $authentication = null;

    private ?AuthorizationService $authorization = null;

    private ?ProfileManagementService $profileManagement = null;

    private ?UserManagementService $userManagement = null;

    private ?EmployeeManagementService $employeeManagement = null;

    private ?ProductManagementService $productManagement = null;

    private ?ServiceManagementService $serviceManagement = null;

    private ?ClientManagementService $clientManagement = null;

    private ?BudgetManagementService $budgetManagement = null;

    private ?ServiceOrderManagementService $serviceOrderManagement = null;

    private ?AgendaManagementService $agendaManagement = null;

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
            $secure = (
                $this->settings['app_env'] ?? 'production'
            ) === 'production'
                || (
                    !empty($_SERVER['HTTPS'])
                    && $_SERVER['HTTPS'] !== 'off'
                );

            $this->session = new SessionManager(
                (string) (
                    $this->settings['session_name']
                    ?? 'YKSESSID'
                ),
                (int) (
                    $this->settings['session_timeout']
                    ?? 1800
                ),
                (int) (
                    $this->settings['session_absolute_timeout']
                    ?? 28800
                ),
                (int) (
                    $this->settings['session_regenerate_interval']
                    ?? 900
                ),
                (string) (
                    $this->settings['session_cookie_path']
                    ?? '/YK'
                ),
                $secure
            );
        }

        return $this->session;
    }

    public function csrf(): CsrfTokenManager
    {
        if ($this->csrf === null) {
            $this->csrf = new CsrfTokenManager(
                $this->session()
            );
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
                (int) (
                    $this->settings['login_max_attempts']
                    ?? 5
                ),
                (int) (
                    $this->settings['login_lock_minutes']
                    ?? 15
                )
            );
        }

        return $this->authentication;
    }

    public function authorization(): AuthorizationService
    {
        if ($this->authorization === null) {
            $this->authorization = new AuthorizationService(
                $this->authentication()
            );
        }

        return $this->authorization;
    }

    public function profileManagement(): ProfileManagementService
    {
        if ($this->profileManagement === null) {
            $connection = $this->database->connection();

            $this->profileManagement = new ProfileManagementService(
                $connection,
                new ProfileRepository($connection),
                new PermissionRepository($connection),
                new ProfilePermissionRepository($connection),
                new UserRepository($connection)
            );
        }

        return $this->profileManagement;
    }

    public function userManagement(): UserManagementService
    {
        if ($this->userManagement === null) {
            $connection = $this->database->connection();

            $this->userManagement = new UserManagementService(
                new UserRepository($connection),
                new ProfileRepository($connection)
            );
        }

        return $this->userManagement;
    }

    public function employeeManagement(): EmployeeManagementService
    {
        if ($this->employeeManagement === null) {
            $connection = $this->database->connection();

            $this->employeeManagement = new EmployeeManagementService(
                new EmployeeRepository($connection)
            );
        }

        return $this->employeeManagement;
    }

    public function productManagement(): ProductManagementService
    {
        if ($this->productManagement === null) {
            $connection = $this->database->connection();

            $this->productManagement = new ProductManagementService(
                new ProductRepository($connection)
            );
        }

        return $this->productManagement;
    }

    public function serviceManagement(): ServiceManagementService
    {
        if ($this->serviceManagement === null) {
            $connection = $this->database->connection();

            $this->serviceManagement = new ServiceManagementService(
                new ServiceRepository($connection)
            );
        }

        return $this->serviceManagement;
    }

    public function clientManagement(): ClientManagementService
    {
        if ($this->clientManagement === null) {
            $connection = $this->database->connection();

            $this->clientManagement = new ClientManagementService(
                new ClientRepository($connection)
            );
        }

        return $this->clientManagement;
    }

    public function budgetManagement(): BudgetManagementService
    {
        if ($this->budgetManagement === null) {
            $connection = $this->database->connection();

            $this->budgetManagement = new BudgetManagementService(
                new BudgetRepository($connection),
                new ClientRepository($connection),
                new ProductRepository($connection),
                new ServiceRepository($connection)
            );
        }

        return $this->budgetManagement;
    }

    public function serviceOrderManagement(): ServiceOrderManagementService
    {
        if ($this->serviceOrderManagement === null) {
            $connection = $this->database->connection();

            $this->serviceOrderManagement = new ServiceOrderManagementService(
                $connection,
                new ServiceOrderRepository($connection),
                new EmployeeRepository($connection),
                new ClientRepository($connection),
                new ServiceRepository($connection),
                new ProductRepository($connection),
                new BudgetRepository($connection)
            );
        }

        return $this->serviceOrderManagement;
    }

    public function agendaManagement(): AgendaManagementService
    {
        if ($this->agendaManagement === null) {
            $connection = $this->database->connection();

            $this->agendaManagement = new AgendaManagementService(
                new AgendaReminderRepository($connection)
            );
        }

        return $this->agendaManagement;
    }

    public function redirect(): SafeRedirect
    {
        if ($this->redirect === null) {
            $this->redirect = new SafeRedirect();
        }

        return $this->redirect;
    }
}
