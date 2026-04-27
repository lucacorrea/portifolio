<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Dashboard da Plataforma';
$pageDescription = 'Visão geral do SaaS, empresas locatárias e assinaturas.';

$pdo = db();
$totais = [
    'empresas' => (int) $pdo->query('SELECT COUNT(*) FROM empresas')->fetchColumn(),
    'ativas' => (int) $pdo->query("SELECT COUNT(*) FROM empresas WHERE status IN ('ativa','teste')")->fetchColumn(),
    'bloqueadas' => (int) $pdo->query("SELECT COUNT(*) FROM empresas WHERE status = 'bloqueada'")->fetchColumn(),
    'usuarios' => (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo IN ('empresa_admin','operador')")->fetchColumn(),
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="layout">
    <?php require APP_PATH . '/Includes/admin_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="grid four">
            <article class="card metric"><span>Empresas</span><strong><?= $totais['empresas'] ?></strong></article>
            <article class="card metric"><span>Ativas/Teste</span><strong><?= $totais['ativas'] ?></strong></article>
            <article class="card metric"><span>Bloqueadas</span><strong><?= $totais['bloqueadas'] ?></strong></article>
            <article class="card metric"><span>Usuários locatários</span><strong><?= $totais['usuarios'] ?></strong></article>
        </section>

        <section class="card">
            <h2>Próximos passos</h2>
            <p>Use o menu para cadastrar planos, empresas locatárias e usuários responsáveis por cada empresa.</p>
        </section>
    </main>
</div>
</body>
</html>
