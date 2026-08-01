<?php
declare(strict_types=1);

$dashboard = $application->adminDashboard()->data($currentUser->id());
$counts = $dashboard['counts'];
$schemaReady = $application->adminCompanies()->ready();
try {
    $soCount = $application->soSuppliers()->count('');
    $soAvailable = true;
} catch (Throwable $exception) {
    $soCount = 0;
    $soAvailable = false;
}
$pageScriptData = ['charts' => [
    'status' => [['Ativas', $counts['ativo']], ['Pendentes', $counts['pendente']], ['Inativas', $counts['inativo']], ['Bloqueadas', $counts['bloqueado']]],
    'segments' => $dashboard['segments'],
    'months' => $dashboard['months'],
    'origin' => [['Importadas do SO', $counts['so']], ['Cadastradas manualmente', max(0, $counts['total'] - $counts['so'])]],
]];
$pageChart = true;
$adminContent = static function () use ($counts, $soCount, $soAvailable, $schemaReady): void { ?>
<?php if (!$schemaReady): ?><div class="alert alert-danger" role="alert">Banco administrativo pendente. Execute a migration 024 para habilitar empresas, vínculos e auditoria.</div><?php endif; ?>
<div class="admin-metrics"><?php foreach ([['Empresas cadastradas', $counts['total'], 'bi-buildings'], ['Ativas', $counts['ativo'], 'bi-check-circle'], ['Pendentes', $counts['pendente'], 'bi-hourglass-split'], ['Bloqueadas', $counts['bloqueado'], 'bi-slash-circle'], ['Vinculadas ao SO', $counts['so'], 'bi-link-45deg'], ['Cadastradas por mim', $counts['mine'], 'bi-person-check'], ['Fornecedores do SO', $soAvailable ? $soCount : 'SO indisponível', 'bi-database']] as [$label, $value, $icon]): ?><article class="admin-metric"><i class="bi <?= $icon ?>"></i><span><?= admin_h($label) ?></span><strong><?= admin_h($value) ?></strong></article><?php endforeach; ?></div>
<div class="admin-charts">
    <section class="admin-panel"><h2>Empresas por situação</h2><canvas data-admin-chart="status" aria-label="Gráfico de empresas por situação" role="img"></canvas></section>
    <section class="admin-panel"><h2>Empresas por segmento</h2><canvas data-admin-chart="segments" aria-label="Gráfico de empresas por segmento" role="img"></canvas></section>
    <section class="admin-panel"><h2>Cadastros nos últimos 12 meses</h2><canvas data-admin-chart="months" aria-label="Gráfico de cadastros mensais" role="img"></canvas></section>
    <section class="admin-panel"><h2>Origem dos cadastros</h2><canvas data-admin-chart="origin" aria-label="Gráfico da origem dos cadastros" role="img"></canvas></section>
</div>
<?php };
