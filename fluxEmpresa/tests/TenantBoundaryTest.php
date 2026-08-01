<?php

declare(strict_types=1);

function tenantBoundaryAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tenantBoundarySource(string $root, string $path): string
{
    $absolutePath = $root . '/' . $path;
    tenantBoundaryAssert(is_file($absolutePath), 'Arquivo obrigatório do limite multiempresa ausente: ' . $path);
    $source = file_get_contents($absolutePath);
    tenantBoundaryAssert(is_string($source), 'Não foi possível ler o contrato multiempresa: ' . $path);
    return $source;
}

$root = dirname(__DIR__);

$scope = tenantBoundarySource($root, 'src/Company/DTO/CompanyScope.php');
tenantBoundaryAssert(str_contains($scope, 'public function id(): int'), 'CompanyScope deve expor um identificador positivo e tipado.');
tenantBoundaryAssert(str_contains($scope, "['member', 'support']"), 'O contexto deve distinguir vínculo normal de suporte administrativo.');
tenantBoundaryAssert(str_contains($scope, 'supportAccessId'), 'Contexto de suporte deve permanecer ligado à auditoria administrativa.');
tenantBoundaryAssert(str_contains($scope, "str_starts_with(\$permission, 'usuario.')"), 'Cadastro global de usuários deve permanecer fechado no contexto operacional.');
tenantBoundaryAssert(str_contains($scope, "str_starts_with(\$permission, 'perfil.')"), 'Cadastro global de perfis deve permanecer fechado no contexto operacional.');

$resolver = tenantBoundarySource($root, 'src/Company/Service/OperationalCompanyContextResolver.php');
tenantBoundaryAssert(str_contains($resolver, 'resolve(AuthenticatedUser $user): CompanyScope'), 'Resolver operacional deve retornar um CompanyScope obrigatório.');
tenantBoundaryAssert(str_contains($resolver, 'findPrimaryActiveCompanyMembership'), 'Usuário comum deve resolver a empresa por vínculo ativo.');
tenantBoundaryAssert(str_contains($resolver, 'ActiveCompanyContext'), 'Administrador em suporte deve usar o contexto de sessão validado.');
tenantBoundaryAssert(str_contains($resolver, 'findOpenAuthorized'), 'Contexto de suporte deve confirmar que a auditoria continua aberta e autorizada.');

$userRepository = tenantBoundarySource($root, 'src/Access/Repository/UserRepository.php');
tenantBoundaryAssert(str_contains($userRepository, 'usuario_empresas'), 'Repositório de usuários deve consultar os vínculos empresariais.');
tenantBoundaryAssert(str_contains($userRepository, "ue.status = 'ativo'"), 'Somente vínculo empresarial ativo pode abrir o painel.');
tenantBoundaryAssert(str_contains($userRepository, "e.status = 'ativo'"), 'Vínculo não pode liberar empresa operacional inativa.');

$activeCompanyContext = tenantBoundarySource($root, 'src/Company/Service/ActiveCompanyContext.php');
tenantBoundaryAssert(str_contains($activeCompanyContext, 'platform_company_context'), 'Contexto administrativo deve permanecer isolado em uma chave de sessão própria.');

$adminAccessRepository = tenantBoundarySource($root, 'src/Admin/Repository/AdminAccessRepository.php');
tenantBoundaryAssert(str_contains($adminAccessRepository, 'findOpenAuthorized'), 'Repositório administrativo deve revalidar o acesso de suporte aberto.');

$application = tenantBoundarySource($root, 'src/Core/Application.php');
tenantBoundaryAssert(str_contains($application, 'operationalCompanyContext()'), 'Application deve fornecer o resolver operacional central.');

$shell = tenantBoundarySource($root, 'includes/shell.php');
tenantBoundaryAssert(str_contains($shell, 'operationalCompanyContext()->resolve($currentUser)'), 'Toda página operacional deve resolver a empresa antes de carregar conteúdo.');
tenantBoundaryAssert(!str_contains($shell, "\$_GET['empresa_id']") && !str_contains($shell, "\$_POST['empresa_id']"), 'Empresa operacional não pode ser escolhida diretamente pela requisição.');

foreach (['actions/usuario-action-common.php', 'actions/perfil-action-common.php'] as $accessAction) {
    $source = tenantBoundarySource($root, $accessAction);
    tenantBoundaryAssert(str_contains($source, 'operationalCompanyContext()->resolve'), $accessAction . ' deve validar o contexto da empresa antes de alterar acessos.');
    tenantBoundaryAssert(str_contains($source, 'companyScope->allows'), $accessAction . ' deve aplicar a permissão operacional escopada.');
}

$migration = tenantBoundarySource($root, 'database/migrations/025_tenant_operational_scope.sql');
foreach (['usuario_empresas', 'empresa_auditoria_operacional'] as $table) {
    tenantBoundaryAssert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS ' . $table), 'Migration deve criar a estrutura multiempresa: ' . $table);
}
foreach ([
    'funcionarios',
    'produtos',
    'servicos',
    'clientes',
    'orcamentos',
    'ordens_servico',
    'agenda_lembretes',
    'estoque_movimentacoes',
    'caixa_movimentacoes',
    'contas_receber',
    'configuracoes_empresa',
    'configuracoes_fiscais',
    'recibos',
    'fornecedores',
    'contas_pagar',
    'caixa_sessoes',
] as $table) {
    tenantBoundaryAssert(
        preg_match(
            '/ALTER\s+TABLE\s+' . preg_quote($table, '/') . '\b[^;]*\bADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+empresa_id/iu',
            $migration
        ) === 1,
        'Migration deve preparar empresa_id na tabela operacional ' . $table . '.'
    );
}
tenantBoundaryAssert(
    preg_match('/UPDATE\s+\w+\s+SET\s+empresa_id/iu', $migration) !== 1,
    'Migration estrutural não pode atribuir dados legados a uma empresa arbitrária.'
);

$scopedSqlOwners = [
    'src/Company/Service/CompanySettingsService.php',
    'src/Catalog/Repository/ProductRepository.php',
    'src/Catalog/Repository/ServiceRepository.php',
    'src/CRM/Repository/ClientRepository.php',
    'src/Workforce/Repository/EmployeeRepository.php',
    'src/Purchasing/Service/SupplierManagementService.php',
    'src/Inventory/Service/InventoryManagementService.php',
    'src/Schedule/Repository/AgendaReminderRepository.php',
    'src/Sales/Repository/BudgetRepository.php',
    'src/ServiceOrder/Repository/ServiceOrderRepository.php',
    'src/Dashboard/Repository/DashboardRepository.php',
    'src/Report/Repository/ProductionReportRepository.php',
    'src/Finance/Service/CashManagementService.php',
    'src/Finance/Service/AccountsReceivableManagementService.php',
    'src/Finance/Service/AccountsPayableManagementService.php',
    'src/Finance/Service/ReceiptService.php',
    'src/ServiceOrder/Service/ServiceOrderManagementService.php',
    'src/ServiceOrder/Service/ServiceOrderFinalizationService.php',
    'src/ServiceOrder/Service/ServiceOrderLifecycleService.php',
    'src/Fiscal/Repository/FiscalConfigurationRepository.php',
];

foreach ($scopedSqlOwners as $path) {
    $source = tenantBoundarySource($root, $path);
    tenantBoundaryAssert(str_contains($source, 'CompanyScope'), $path . ' deve receber o contexto tipado da empresa.');
    tenantBoundaryAssert(str_contains($source, 'empresa_id'), $path . ' deve restringir suas consultas por empresa_id.');
    tenantBoundaryAssert(str_contains($source, 'companyScope->id()'), $path . ' deve vincular o ID vindo do contexto confiável.');
}

$paymentManagement = tenantBoundarySource($root, 'src/Finance/Service/PaymentManagementService.php');
tenantBoundaryAssert(str_contains($paymentManagement, 'CompanyScope'), 'Pagamento deve receber o contexto tipado da empresa.');
tenantBoundaryAssert(str_contains($paymentManagement, 'companyId()'), 'Pagamento deve recusar dependências financeiras de outra empresa.');

echo "TenantBoundaryTest: OK\n";
