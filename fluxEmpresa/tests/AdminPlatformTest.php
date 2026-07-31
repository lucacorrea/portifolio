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

$companyPage = (string) file_get_contents($root . '/adm/pages/empresa.php');
adminAssert(str_contains($companyPage, 'actions/empresa-criar.php'), 'A interface deve oferecer cadastro manual.');
adminAssert(str_contains($companyPage, 'actions/empresa-editar.php'), 'A interface deve oferecer edição.');
adminAssert(str_contains($companyPage, 'actions/empresa-vincular-so.php'), 'A interface deve evitar duplicação ao vincular o SO.');
adminAssert(str_contains($companyPage, 'name="motivo"'), 'Atendimentos administrativos devem exigir motivo auditável.');

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
