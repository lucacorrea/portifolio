<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Usuários da empresa';
$pageDescription = 'Equipe com acesso ao painel da empresa.';
$empresaId = current_empresa_id();
$podeCadastrar = ($_SESSION['usuario']['tipo'] ?? '') === 'empresa_admin';

$stmt = db()->prepare('SELECT id, nome, email, tipo, ativo, ultimo_login, criado_em FROM usuarios WHERE empresa_id = :empresa_id ORDER BY id DESC');
$stmt->execute([':empresa_id' => $empresaId]);
$usuarios = $stmt->fetchAll();
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
    <?php require APP_PATH . '/Includes/tenant_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Usuários cadastrados</h2>
                    <p class="muted">Acessos internos da empresa, permissões e status de login.</p>
                </div>
                <?php if ($podeCadastrar): ?>
                    <a class="btn btn-primary" href="<?= e(public_url('/app/usuarios-cadastro.php')) ?>">Cadastrar usuário</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Nome</th><th>E-mail</th><th>Tipo</th><th>Status</th><th>Último login</th><th>Criado em</th></tr></thead>
                    <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><strong><?= e($u['nome']) ?></strong></td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="soft-label"><?= e($u['tipo']) ?></span></td>
                            <td><span class="badge <?= (int) $u['ativo'] === 1 ? 'ativa' : 'bloqueada' ?>"><?= (int) $u['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
                            <td><?= e($u['ultimo_login'] ? data_br($u['ultimo_login']) : 'Sem registro') ?></td>
                            <td><?= e(data_br($u['criado_em'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$usuarios): ?>
                        <tr><td colspan="6">Nenhum usuário cadastrado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
