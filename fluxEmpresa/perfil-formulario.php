<?php
declare(strict_types=1);

$profileId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$viewOnly = ((string) ($_GET['view'] ?? '')) === '1';

$pageTitle = $viewOnly ? 'Visualizar Perfil' : (is_int($profileId) ? 'Editar Perfil' : 'Novo Perfil');
$pageSubtitle = 'Dados do tipo de usuário';
$activePage = 'perfis-acesso';
$primaryActionLabel = 'Voltar';
$primaryActionIcon = 'bi-arrow-left';
$primaryActionHref = 'perfis-acesso.php';
$requiredPermission = $viewOnly ? 'perfil.visualizar' : (is_int($profileId) ? 'perfil.editar' : 'perfil.criar');
$pageContent = __DIR__ . '/pages/perfil-formulario.php';

require __DIR__ . '/includes/shell.php';
