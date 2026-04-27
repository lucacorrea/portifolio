<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Usuários da empresa';
$pageDescription = 'Usuários internos da empresa que alugou o sistema.';
$empresaId = current_empresa_id();
$stmt = db()->prepare('SELECT id, nome, email, tipo, ativo, ultimo_login, criado_em FROM usuarios WHERE empresa_id = :empresa_id ORDER BY id DESC');
$stmt->execute([':empresa_id' => $empresaId]);
$usuarios = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($pageTitle) ?></title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body>
<div class="layout">
<?php require APP_PATH . '/Includes/tenant_sidebar.php'; ?>
<main class="content">
<?php require APP_PATH . '/Includes/topbar.php'; ?>
<?php require APP_PATH . '/Includes/flash.php'; ?>

<?php if (($_SESSION['usuario']['tipo'] ?? '') === 'empresa_admin'): ?>
<section class="card">
<h2>Novo usuário</h2>
<form method="post" action="/actions/app/salvar_usuario.php" class="form-grid">
<?= csrf_field() ?>
<label>Nome<input name="nome" required></label>
<label>E-mail<input type="email" name="email" required></label>
<label>Senha inicial<input type="password" name="senha" required></label>
<label>Tipo<select name="tipo"><option value="operador">Operador</option><option value="empresa_admin">Administrador da empresa</option></select></label>
<div class="form-actions"><button class="btn btn-primary">Cadastrar usuário</button></div>
</form>
</section>
<?php endif; ?>

<section class="card">
<h2>Usuários cadastrados</h2>
<table><thead><tr><th>Nome</th><th>E-mail</th><th>Tipo</th><th>Status</th><th>Último login</th></tr></thead><tbody>
<?php foreach ($usuarios as $u): ?>
<tr><td><?= e($u['nome']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['tipo']) ?></td><td><?= (int)$u['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></td><td><?= e($u['ultimo_login']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
</section>
</main>
</div>
</body>
</html>
