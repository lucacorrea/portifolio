<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Usuários da plataforma';
$pageDescription = 'Administradores internos do SaaS.';
$usuarios = db()->query("SELECT * FROM usuarios WHERE tipo = 'platform_admin' ORDER BY id DESC")->fetchAll();

$formatarDataHora = static function (?string $data): string {
    $timestamp = $data ? strtotime($data) : false;
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : 'Sem registro';
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
</head>
<body>
<div class="layout">
    <?php require APP_PATH . '/Includes/admin_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Administradores</h2>
                    <p class="muted">Usuários com acesso interno à administração da plataforma FluxPay.</p>
                </div>
                <a class="btn btn-primary" href="<?= e(public_url('/admin/usuarios-plataforma-cadastro.php')) ?>">Cadastrar administrador</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr><th>Nome</th><th>E-mail</th><th>Status</th><th>Perfil</th><th>Último login</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><strong><?= e($u['nome']) ?></strong></td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="badge <?= (int) $u['ativo'] === 1 ? 'ativa' : 'bloqueada' ?>"><?= (int) $u['ativo'] === 1 ? 'Ativo' : 'Bloqueado' ?></span></td>
                            <td><span class="soft-label"><?= e($u['tipo']) ?></span></td>
                            <td><?= e($formatarDataHora($u['ultimo_login'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$usuarios): ?>
                        <tr><td colspan="5">Nenhum administrador cadastrado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
