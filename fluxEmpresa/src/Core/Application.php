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
use App\Admin\Repository\AdminAccessRepository;
use App\Admin\Repository\AdminCompanyRepository;
use App\Admin\Repository\AdminDashboardRepository;
use App\Admin\Service\AdminAccessService;
use App\Admin\Service\AdminCompanyService;
use App\Admin\Service\AdminDashboardService;
use App\Admin\Service\PlatformAdminPolicy;
use App\Catalog\Repository\ProductRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\Service\ProductManagementService;
use App\Catalog\Service\ServiceManagementService;
use App\Company\DTO\CompanyScope;
use App\Company\Service\ActiveCompanyContext;
use App\Company\Service\CompanySettingsService;
use App\Company\Service\OperationalCompanyContextResolver;
use App\CRM\Import\A7ClientReportMapper;
use App\CRM\Import\ClientPdfParser;
use App\CRM\Repository\ClientRepository;
use App\CRM\Service\ClientImportService;
use App\CRM\Service\ClientManagementService;
use App\Dashboard\Repository\DashboardRepository;
use App\Dashboard\Service\DashboardService;
use App\Finance\Service\AccountsPayableManagementService;
use App\Finance\Service\AccountsReceivableManagementService;
use App\Finance\Service\CashManagementService;
use App\Finance\Service\PaymentManagementService;
use App\Finance\Service\ReceiptService;
use App\Integration\SO\Repository\SoAcquisitionIntegrationRepository;
use App\Integration\SO\Repository\SoAcquisitionReadRepository;
use App\Integration\SO\Service\SoAcquisitionQueueService;
use App\Integration\SO\Service\SoAcquisitionBrowserService;
use App\Integration\SO\Service\SoSupplierService;
use App\Integration\SO\SoApiClient;
use App\Integration\SO\SoDatabase;
use App\Inventory\Service\InventoryManagementService;
use App\Purchasing\Service\SupplierManagementService;
use App\Report\Repository\ProductionReportRepository;
use App\Report\Service\ProductionReportService;
use App\Sales\Repository\BudgetRepository;
use App\Sales\Service\BudgetManagementService;
use App\Schedule\Repository\AgendaReminderRepository;
use App\Schedule\Service\AgendaManagementService;
use App\Security\CsrfTokenManager;
use App\Security\PrivilegedAuthorizationService;
use App\Security\SafeRedirect;
use App\Security\SessionManager;
use App\ServiceOrder\Repository\ServiceOrderRepository;
use App\ServiceOrder\Service\ServiceOrderFinalizationService;
use App\ServiceOrder\Service\ServiceOrderLifecycleService;
use App\ServiceOrder\Service\ServiceOrderManagementService;
use App\Workforce\Repository\EmployeeRepository;
use App\Workforce\Service\EmployeeManagementService;


final class Application
{
    use FiscalApplicationServices;

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

    private ?ClientImportService $clientImport = null;

    private ?BudgetManagementService $budgetManagement = null;

    private ?ServiceOrderManagementService $serviceOrderManagement = null;

    private ?AgendaManagementService $agendaManagement = null;

    private ?InventoryManagementService $inventoryManagement = null;

    private ?CashManagementService $cashManagement = null;

    private ?AccountsReceivableManagementService $accountsReceivableManagement = null;

    private ?AccountsPayableManagementService $accountsPayableManagement = null;

    private ?SupplierManagementService $supplierManagement = null;

    private ?PaymentManagementService $paymentManagement = null;

    private ?ReceiptService $receiptService = null;

    private ?CompanySettingsService $companySettings = null;

    private ?PrivilegedAuthorizationService $privilegedAuthorization = null;

    private ?ServiceOrderFinalizationService $serviceOrderFinalization = null;

    private ?ServiceOrderLifecycleService $serviceOrderLifecycle = null;

    private ?DashboardService $dashboardService = null;

    private ?ProductionReportService $productionReportService = null;

    private ?SafeRedirect $redirect = null;

    private ?AdminCompanyService $adminCompanyService = null;

    private ?AdminAccessService $adminAccessService = null;

    private ?AdminDashboardService $adminDashboardService = null;

    private ?SoSupplierService $soSupplierService = null;

    private ?SoApiClient $soApiClient = null;

    private ?SoAcquisitionIntegrationRepository $soAcquisitionIntegrationRepository = null;

    private ?SoAcquisitionQueueService $soAcquisitionQueueService = null;
    private ?SoAcquisitionBrowserService $soAcquisitionBrowserService = null;
    private ?SoDatabase $soDatabase = null;

    private ?ActiveCompanyContext $activeCompanyContext = null;

    private ?OperationalCompanyContextResolver $operationalCompanyContext = null;

    private ?CompanyScope $companyScope = null;

    private ?PlatformAdminPolicy $platformAdminPolicy = null;

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
                $this->settings['app_env']
                ?? 'production'
            ) === 'production'
                || (
                    !empty($_SERVER['HTTPS'])
                    && $_SERVER['HTTPS'] !== 'off'
                );

            $this->session = new SessionManager(
                (string) (
                    $this->settings['session_name']
                    ?? 'FLUXEMPRESASESSID'
                ),
                (int) (
                    $this->settings['session_timeout']
                    ?? 86400
                ),
                (int) (
                    $this->settings['session_absolute_timeout']
                    ?? 86400
                ),
                (int) (
                    $this->settings['session_regenerate_interval']
                    ?? 900
                ),
                (string) (
                    $this->settings['session_cookie_path']
                    ?? '/fluxEmpresa'
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
                new EmployeeRepository(
                    $connection,
                    $this->companyScope()
                )
            );
        }

        return $this->employeeManagement;
    }

    public function productManagement(): ProductManagementService
    {
        if ($this->productManagement === null) {
            $connection = $this->database->connection();

            $this->productManagement = new ProductManagementService(
                new ProductRepository(
                    $connection,
                    $this->companyScope()
                )
            );
        }

        return $this->productManagement;
    }

    public function serviceManagement(): ServiceManagementService
    {
        if ($this->serviceManagement === null) {
            $connection = $this->database->connection();

            $this->serviceManagement = new ServiceManagementService(
                new ServiceRepository(
                    $connection,
                    $this->companyScope()
                )
            );
        }

        return $this->serviceManagement;
    }

    public function clientManagement(): ClientManagementService
    {
        if ($this->clientManagement === null) {
            $connection = $this->database->connection();

            $this->clientManagement = new ClientManagementService(
                new ClientRepository(
                    $connection,
                    $this->companyScope()
                )
            );
        }

        return $this->clientManagement;
    }

    public function clientImport(): ClientImportService
    {
        if ($this->clientImport === null) {
            $connection = $this->database->connection();

            $this->clientImport = new ClientImportService(
                new ClientRepository(
                    $connection,
                    $this->companyScope()
                ),
                new ClientPdfParser(
                    new A7ClientReportMapper()
                )
            );
        }

        return $this->clientImport;
    }

    public function budgetManagement(): BudgetManagementService
    {
        if ($this->budgetManagement === null) {
            $connection = $this->database->connection();

            $this->budgetManagement = new BudgetManagementService(
                new BudgetRepository(
                    $connection,
                    $this->companyScope()
                ),
                new ClientRepository(
                    $connection,
                    $this->companyScope()
                ),
                new ProductRepository(
                    $connection,
                    $this->companyScope()
                ),
                new ServiceRepository(
                    $connection,
                    $this->companyScope()
                )
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
                $this->companyScope(),
                new ServiceOrderRepository(
                    $connection,
                    $this->companyScope()
                ),
                new EmployeeRepository(
                    $connection,
                    $this->companyScope()
                ),
                new ClientRepository(
                    $connection,
                    $this->companyScope()
                ),
                new ServiceRepository(
                    $connection,
                    $this->companyScope()
                ),
                new ProductRepository(
                    $connection,
                    $this->companyScope()
                ),
                new BudgetRepository(
                    $connection,
                    $this->companyScope()
                )
            );
        }

        return $this->serviceOrderManagement;
    }

    public function agendaManagement(): AgendaManagementService
    {
        if ($this->agendaManagement === null) {
            $connection = $this->database->connection();

            $this->agendaManagement = new AgendaManagementService(
                new AgendaReminderRepository(
                    $connection,
                    $this->companyScope()
                )
            );
        }

        return $this->agendaManagement;
    }

    public function inventoryManagement(): InventoryManagementService
    {
        if ($this->inventoryManagement === null) {
            $this->inventoryManagement = new InventoryManagementService(
                $this->database->connection(),
                $this->companyScope()
            );
        }

        return $this->inventoryManagement;
    }

    public function cashManagement(): CashManagementService
    {
        if ($this->cashManagement === null) {
            $this->cashManagement = new CashManagementService(
                $this->database->connection(),
                $this->companyScope(),
                $this->inventoryManagement()
            );
        }

        return $this->cashManagement;
    }

    public function accountsReceivableManagement(): AccountsReceivableManagementService
    {
        if ($this->accountsReceivableManagement === null) {
            $this->accountsReceivableManagement =
                new AccountsReceivableManagementService(
                    $this->database->connection(),
                    $this->cashManagement(),
                    $this->companyScope()
                );
        }

        return $this->accountsReceivableManagement;
    }

    public function accountsPayableManagement(): AccountsPayableManagementService
    {
        if ($this->accountsPayableManagement === null) {
            $this->accountsPayableManagement =
                new AccountsPayableManagementService(
                    $this->database->connection(),
                    $this->cashManagement(),
                    $this->companyScope()
                );
        }

        return $this->accountsPayableManagement;
    }

    public function supplierManagement(): SupplierManagementService
    {
        if ($this->supplierManagement === null) {
            $this->supplierManagement = new SupplierManagementService(
                $this->database->connection(),
                $this->companyScope()
            );
        }

        return $this->supplierManagement;
    }

    public function paymentManagement(): PaymentManagementService
    {
        if ($this->paymentManagement === null) {
            $this->paymentManagement = new PaymentManagementService(
                $this->database->connection(),
                $this->accountsReceivableManagement(),
                $this->receiptService(),
                $this->companyScope()
            );
        }

        return $this->paymentManagement;
    }

    public function receiptService(): ReceiptService
    {
        if ($this->receiptService === null) {
            $this->receiptService = new ReceiptService(
                $this->database->connection(),
                $this->companyScope()
            );
        }

        return $this->receiptService;
    }

    public function companySettings(): CompanySettingsService
    {
        if ($this->companySettings === null) {
            $this->companySettings = new CompanySettingsService(
                $this->database->connection(),
                $this->companyScope()
            );
        }

        return $this->companySettings;
    }

    public function privilegedAuthorization(): PrivilegedAuthorizationService
    {
        if ($this->privilegedAuthorization === null) {
            $connection = $this->database->connection();

            $this->privilegedAuthorization =
                new PrivilegedAuthorizationService(
                    new UserRepository($connection),
                    new ProfilePermissionRepository($connection),
                    new ProfileRepository($connection)
                );
        }

        return $this->privilegedAuthorization;
    }

    public function serviceOrderFinalization(): ServiceOrderFinalizationService
    {
        if ($this->serviceOrderFinalization === null) {
            $connection = $this->database->connection();

            $this->serviceOrderFinalization =
                new ServiceOrderFinalizationService(
                    $connection,
                    $this->companyScope(),
                    new ServiceOrderRepository(
                        $connection,
                        $this->companyScope()
                    ),
                    $this->inventoryManagement(),
                    $this->accountsReceivableManagement()
                );
        }

        return $this->serviceOrderFinalization;
    }

    public function serviceOrderLifecycle(): ServiceOrderLifecycleService
    {
        if ($this->serviceOrderLifecycle === null) {
            $this->serviceOrderLifecycle =
                new ServiceOrderLifecycleService(
                    $this->database->connection(),
                    $this->companyScope(),
                    $this->cashManagement()
                );
        }

        return $this->serviceOrderLifecycle;
    }

    public function dashboard(): DashboardService
    {
        if ($this->dashboardService === null) {
            $connection = $this->database->connection();

            $this->dashboardService = new DashboardService(
                new DashboardRepository(
                    $connection,
                    $this->companyScope()
                )
            );
        }

        return $this->dashboardService;
    }

    public function reports(): ProductionReportService
    {
        if ($this->productionReportService === null) {
            $connection = $this->database->connection();

            $this->productionReportService =
                new ProductionReportService(
                    new ProductionReportRepository(
                        $connection,
                        $this->companyScope()
                    )
                );
        }

        return $this->productionReportService;
    }

    public function productionReports(): ProductionReportService
    {
        return $this->reports();
    }

    public function redirect(): SafeRedirect
    {
        if ($this->redirect === null) {
            $this->redirect = new SafeRedirect(
                (string) (
                    $this->settings['app_base_path']
                    ?? '/fluxEmpresa'
                )
            );
        }

        return $this->redirect;
    }

    public function platformAdminPolicy(): PlatformAdminPolicy
    {
        return $this->platformAdminPolicy
            ??= new PlatformAdminPolicy();
    }

    public function adminCompanies(): AdminCompanyService
    {
        if ($this->adminCompanyService === null) {
            $connection = $this->database->connection();

            $this->adminCompanyService = new AdminCompanyService(
                $connection,
                new AdminCompanyRepository($connection)
            );
        }

        return $this->adminCompanyService;
    }

    public function adminAccesses(): AdminAccessService
    {
        if ($this->adminAccessService === null) {
            $connection = $this->database->connection();

            $this->adminAccessService = new AdminAccessService(
                $connection,
                new AdminAccessRepository($connection)
            );
        }

        return $this->adminAccessService;
    }

    public function adminDashboard(): AdminDashboardService
    {
        if ($this->adminDashboardService === null) {
            $connection = $this->database->connection();

            $this->adminDashboardService = new AdminDashboardService(
                new AdminDashboardRepository($connection),
                $this->adminCompanies()
            );
        }

        return $this->adminDashboardService;
    }

    /**
     * Leitura direta e somente leitura do banco do SO.
     *
     * Utilizado para localizar fornecedores e, futuramente,
     * consultar aquisições já existentes no SO.
     */
    public function soSuppliers(): SoSupplierService
    {
        if ($this->soSupplierService === null) {
            $this->soSupplierService = new SoSupplierService(
                $this->soDatabase()
            );
        }

        return $this->soSupplierService;
    }

    /**
     * Cliente HTTPS responsável por criar aquisições no SO.
     *
     * A comunicação utiliza:
     * - JSON;
     * - HTTPS;
     * - HMAC-SHA256;
     * - timestamp;
     * - nonce;
     * - idempotência.
     *
     * O segredo permanece somente no backend.
     */
    public function soApiClient(): SoApiClient
    {
        if ($this->soApiClient === null) {
            $this->soApiClient = new SoApiClient(
                enabled: (bool) (
                    $this->settings['so_integration_enabled']
                    ?? false
                ),
                baseUrl: (string) (
                    $this->settings['so_api_base_url']
                    ?? ''
                ),
                acquisitionPath: (string) (
                    $this->settings['so_api_acquisition_path']
                    ?? ''
                ),
                clientId: (string) (
                    $this->settings['so_api_client_id']
                    ?? ''
                ),
                secret: (string) (
                    $this->settings['so_api_secret']
                    ?? ''
                ),
                connectTimeout: (int) (
                    $this->settings['so_api_connect_timeout']
                    ?? 5
                ),
                timeout: (int) (
                    $this->settings['so_api_timeout']
                    ?? 15
                ),
                verifyTls: (bool) (
                    $this->settings['so_api_verify_tls']
                    ?? true
                )
            );
        }

        return $this->soApiClient;
    }

    /**
     * Repository responsável pelo vínculo local entre:
     *
     * - orçamento/OS do Flux Empresas;
     * - aquisição do SO;
     * - eventos pendentes da outbox.
     */
    public function soAcquisitionIntegrations(): SoAcquisitionIntegrationRepository
    {
        if ($this->soAcquisitionIntegrationRepository === null) {
            $this->soAcquisitionIntegrationRepository =
                new SoAcquisitionIntegrationRepository(
                    connection: $this->database->connection(),
                    companyScope: $this->companyScope()
                );
        }

        return $this->soAcquisitionIntegrationRepository;
    }

    /**
     * Prepara orçamento ou OS aprovada para criação de aquisição no SO.
     *
     * Este serviço monta o payload, gera a chave de idempotência
     * e registra a integração na outbox. A comunicação HTTP é
     * executada posteriormente pelo worker.
     */
    public function soAcquisitionQueue(): SoAcquisitionQueueService
    {
        if ($this->soAcquisitionQueueService === null) {
            $this->soAcquisitionQueueService =
                new SoAcquisitionQueueService(
                    connection: $this->database->connection(),
                    companyScope: $this->companyScope(),
                    integrations: $this->soAcquisitionIntegrations()
                );
        }

        return $this->soAcquisitionQueueService;
    }

    public function soAcquisitionBrowser(): SoAcquisitionBrowserService
    {
        if ($this->soAcquisitionBrowserService === null) {
            $this->soAcquisitionBrowserService = new SoAcquisitionBrowserService(
                $this->database->connection(),
                new SoAcquisitionReadRepository($this->soDatabase()->connection())
            );
        }
        return $this->soAcquisitionBrowserService;
    }

    private function soDatabase(): SoDatabase
    {
        return $this->soDatabase ??= new SoDatabase((string) ($this->settings['project_root'] ?? ''));
    }

    public function activeCompanyContext(): ActiveCompanyContext
    {
        if ($this->activeCompanyContext === null) {
            $this->activeCompanyContext = new ActiveCompanyContext(
                $this->session()
            );
        }

        return $this->activeCompanyContext;
    }

    public function operationalCompanyContext(): OperationalCompanyContextResolver
    {
        if ($this->operationalCompanyContext === null) {
            $connection = $this->database->connection();

            $this->operationalCompanyContext =
                new OperationalCompanyContextResolver(
                    new UserRepository($connection),
                    new AdminAccessRepository($connection),
                    $this->activeCompanyContext()
                );
        }

        return $this->operationalCompanyContext;
    }

    public function companyScope(): CompanyScope
    {
        if ($this->companyScope === null) {
            $this->companyScope = $this
                ->operationalCompanyContext()
                ->resolve(
                    $this
                        ->authorization()
                        ->requireLogin()
                );
        }

        return $this->companyScope;
    }
}
