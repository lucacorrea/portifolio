<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Empresas locatárias';
$pageDescription = 'Listagem das empresas que alugam o FluxPay.';

$empresas = db()->query(
    "SELECT e.*, p.nome AS plano_nome,
            (SELECT COUNT(*) FROM usuarios u WHERE u.empresa_id = e.id) AS total_usuarios
     FROM empresas e
     LEFT JOIN planos p ON p.id = e.plano_id
     ORDER BY e.id DESC"
)->fetchAll();
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
                    <h2>Empresas cadastradas</h2>
                    <p class="muted">Listagem operacional com status, plano contratado e usuários vinculados.</p>
                </div>
                <div class="page-actions">
                    <a class="btn btn-primary" href="<?= e(public_url('/admin/empresas-cadastro.php')) ?>">Cadastrar empresa</a>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>Empresa</th><th>Plano</th><th>Status</th><th>Usuários</th><th>Contato</th><th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($empresas as $empresa): ?>
                        <tr>
                            <td><strong><?= e($empresa['nome']) ?></strong><br><small><?= e($empresa['cnpj']) ?></small></td>
                            <td><?= e($empresa['plano_nome'] ?? 'Sem plano') ?></td>
                            <td><span class="badge <?= e($empresa['status']) ?>"><?= e($empresa['status']) ?></span></td>
                            <td><?= (int) $empresa['total_usuarios'] ?></td>
                            <td><?= e($empresa['email']) ?><br><small><?= e($empresa['telefone']) ?></small></td>
                            <td>
                                <div class="table-actions">
                                    <form method="post" action="<?= e(public_url('/actions/admin/alterar_status_empresa.php')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="empresa_id" value="<?= (int) $empresa['id'] ?>">
                                        <select name="status">
                                            <option value="ativa">Ativar</option>
                                            <option value="teste">Teste</option>
                                            <option value="bloqueada">Bloquear</option>
                                            <option value="cancelada">Cancelar</option>
                                        </select>
                                        <button class="btn btn-sm">Aplicar</button>
                                    </form>
                                    <a class="btn btn-sm btn-secondary" href="<?= e(public_url('/admin/empresa-usuario-cadastro.php?empresa_id=' . (int) $empresa['id'])) ?>">Adicionar usuário</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$empresas): ?>
                        <tr><td colspan="6">Nenhuma empresa cadastrada.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
