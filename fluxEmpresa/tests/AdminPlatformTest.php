<?php
declare(strict_types=1);

function adminAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$guard = (string) file_get_contents($root . '/adm/includes/admin-guard.php');
adminAssert(str_contains($guard, 'catch (AuthenticationException)'), 'O admin deve redirecionar sessões ausentes ou expiradas.');
adminAssert(str_contains($guard, 'loginUrl()'), 'O admin deve retornar ao login com segurança.');

foreach (['empresa-criar.php', 'empresa-editar.php', 'empresa-vincular-so.php'] as $action) {
    $source = (string) file_get_contents($root . '/adm/actions/' . $action);
    adminAssert(str_contains($source, 'admin_post()'), 'Toda mutação administrativa deve validar POST e CSRF.');
    adminAssert(str_contains($source, 'adminCompanies()'), 'A ação ' . $action . ' não pode ser um redirecionamento vazio.');
}

$companiesPage = (string) file_get_contents($root . '/adm/pages/empresas.php');
adminAssert(str_contains($companiesPage, 'table-action-dropdown'), 'A lista deve preservar o dropdown global de ações da tabela.');
adminAssert(str_contains($companiesPage, 'Entrar no painel operacional'), 'A lista deve oferecer acesso ao painel da empresa selecionada.');
adminAssert(str_contains($companiesPage, 'admin-company-access-modal'), 'A entrada operacional deve solicitar confirmação em uma modal acessível.');
adminAssert(str_contains($companiesPage, 'data-bs-toggle="modal"'), 'A ação operacional deve abrir a modal sem abandonar a lista.');
adminAssert(str_contains($companiesPage, 'method="post"'), 'A entrada operacional deve usar POST.');
adminAssert(str_contains($companiesPage, "admin_url('actions/empresa-entrar.php')"), 'A modal deve enviar para a action administrativa protegida.');
adminAssert(str_contains($companiesPage, '$csrf->field()'), 'A entrada operacional deve incluir CSRF.');
adminAssert(str_contains($companiesPage, 'name="id"'), 'A entrada operacional deve identificar a empresa selecionada.');
adminAssert(str_contains($companiesPage, 'name="motivo"'), 'A entrada operacional deve exigir motivo auditável.');
adminAssert(str_contains($companiesPage, 'minlength="10"') && str_contains($companiesPage, 'maxlength="255"'), 'O motivo deve orientar os mesmos limites validados no servidor.');
adminAssert(str_contains($companiesPage, "=== 'ativo'"), 'A interface deve habilitar o painel somente para empresa ativa.');
adminAssert(!preg_match('/<a[^>]+empresa-entrar\.php/iu', $companiesPage), 'Troca de contexto não pode ocorrer por link GET.');

$companyPage = (string) file_get_contents($root . '/adm/pages/empresa.php');
adminAssert(str_contains($companyPage, 'actions/empresa-criar.php'), 'A interface deve oferecer cadastro manual.');
adminAssert(str_contains($companyPage, 'actions/empresa-editar.php'), 'A interface deve oferecer edição.');
adminAssert(str_contains($companyPage, 'actions/empresa-vincular-so.php'), 'A interface deve evitar duplicação ao vincular o SO.');
adminAssert(str_contains($companyPage, 'name="motivo"'), 'Atendimentos administrativos devem exigir motivo auditável.');
adminAssert(str_contains($companyPage, 'Entrar no painel operacional'), 'O detalhe deve usar o mesmo destino operacional oferecido na lista.');
adminAssert(str_contains($companyPage, "=== 'ativo'"), 'O detalhe não pode liberar painel de empresa inativa.');

$enterAction = (string) file_get_contents($root . '/adm/actions/empresa-entrar.php');
adminAssert(str_contains($enterAction, 'admin_post()'), 'Entrada no painel deve validar POST e CSRF antes de alterar o contexto.');
adminAssert(str_contains($enterAction, 'adminCompanies()->find'), 'Entrada no painel deve validar a empresa no servidor.');
adminAssert(str_contains($enterAction, "=== 'ativo'") || str_contains($enterAction, "!== 'ativo'"), 'Entrada no painel deve aceitar somente empresa ativa.');
adminAssert(str_contains($enterAction, 'regenerateId()'), 'Entrada no painel deve renovar a sessão antes de abrir o contexto.');
adminAssert(str_contains($enterAction, "admin_action_redirect('dashboard.php')"), 'Entrada confirmada deve abrir o painel operacional.');

$leaveAction = (string) file_get_contents($root . '/adm/actions/empresa-sair.php');
adminAssert(str_contains($leaveAction, 'admin_post()'), 'Encerramento do contexto deve aceitar somente POST com CSRF.');
adminAssert(str_contains($leaveAction, 'adminAccesses()->leave'), 'Encerramento deve fechar a auditoria administrativa.');

$operationalTopbar = (string) file_get_contents($root . '/includes/topbar.php');
adminAssert(str_contains($operationalTopbar, '$companyScope') && str_contains($operationalTopbar, 'isSupport()'), 'Topbar operacional deve usar somente o contexto de suporte já validado pelo guard.');
adminAssert(str_contains($operationalTopbar, 'Contexto de suporte'), 'Topbar operacional deve nomear claramente o acesso administrativo.');
adminAssert(str_contains($operationalTopbar, 'method="post" action="adm/actions/empresa-sair.php"'), 'Topbar deve encerrar e voltar por POST.');
adminAssert(str_contains($operationalTopbar, '$csrf->field()'), 'Saída do contexto operacional deve incluir CSRF.');
adminAssert(str_contains($operationalTopbar, 'Encerrar e voltar'), 'Administrador deve conseguir encerrar o contexto e voltar.');

$actionPortal = (string) file_get_contents($root . '/assets/js/fluxempresa-app.js');
adminAssert(str_contains($actionPortal, "dropdown.classList.contains('table-action-dropdown')"), 'Menu da lista deve continuar usando o portal global.');
adminAssert(str_contains($actionPortal, 'document.body.appendChild(menu)'), 'Dropdown de ações não pode ficar preso ao overflow da tabela.');

$accessRepository = (string) file_get_contents($root . '/src/Admin/Repository/AdminAccessRepository.php');
adminAssert(str_contains($accessRepository, 'sessao_chave'), 'A auditoria deve distinguir sessões simultâneas.');
adminAssert(str_contains($accessRepository, "SELECT COUNT(*)"), 'O histórico deve possuir total para paginação.');

$migration = (string) file_get_contents($root . '/database/migrations/024_complete_admin_platform.sql');
foreach (['CREATE TABLE IF NOT EXISTS empresas', 'CREATE TABLE IF NOT EXISTS empresa_integracoes', 'CREATE TABLE IF NOT EXISTS empresa_acessos_administrativos', 'uk_empresas_documento', 'motivo VARCHAR(255)', 'sessao_chave CHAR(64)'] as $required) {
    adminAssert(str_contains($migration, $required), 'Estrutura administrativa ausente na migration: ' . $required);
}
adminAssert(!str_contains($migration, "LOWER(TRIM(nome)) IN ('administrador', 'admin') THEN 'super_admin'"), 'Perfil operacional não pode virar administrador da plataforma.');

$seed = (string) file_get_contents($root . '/database/seeds/002_seed_profiles.sql');
adminAssert(str_contains($seed, '(nome, codigo, descricao, protegido, status)'), 'O seed deve preencher o código técnico obrigatório.');

echo "AdminPlatformTest: OK\n";
