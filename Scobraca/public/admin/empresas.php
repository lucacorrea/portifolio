<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Empresas locatárias';
$pageDescription = 'Empresas que alugam o Tático GPS SaaS.';

$pdo = db();
$planos = $pdo->query('SELECT id, nome, preco FROM planos WHERE ativo = 1 ORDER BY preco ASC')->fetchAll();
$empresas = $pdo->query(
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
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="layout">
    <?php require APP_PATH . '/Includes/admin_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="card">
            <h2>Cadastrar empresa locatária</h2>
            <p class="muted">Aqui você cadastra a empresa que vai alugar o sistema e já cria o usuário principal dela.</p>

            <form method="post" action="/actions/admin/salvar_empresa.php" class="form-grid">
                <?= csrf_field() ?>
                <label>Nome da empresa<input name="nome" required></label>
                <label>CNPJ<input name="cnpj" placeholder="00.000.000/0000-00"></label>
                <label>E-mail da empresa<input type="email" name="email" required></label>
                <label>Telefone<input name="telefone"></label>
                <label>Plano
                    <select name="plano_id">
                        <option value="">Sem plano</option>
                        <?php foreach ($planos as $plano): ?>
                            <option value="<?= (int) $plano['id'] ?>"><?= e($plano['nome']) ?> - <?= moeda_br((float) $plano['preco']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="status">
                        <option value="teste">Teste</option>
                        <option value="ativa">Ativa</option>
                        <option value="bloqueada">Bloqueada</option>
                    </select>
                </label>
                <label>Nome do usuário principal<input name="usuario_nome" required></label>
                <label>E-mail do usuário principal<input type="email" name="usuario_email" required></label>
                <label>Senha inicial<input type="password" name="usuario_senha" required></label>
                <div class="form-actions"><button class="btn btn-primary">Cadastrar empresa e usuário</button></div>
            </form>
        </section>

        <section class="card">
            <h2>Empresas cadastradas</h2>
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
                                <form method="post" action="/actions/admin/alterar_status_empresa.php" class="inline-form">
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
                            </td>
                        </tr>
                        <tr class="subrow">
                            <td colspan="6">
                                <form method="post" action="/actions/admin/salvar_usuario_empresa.php" class="compact-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="empresa_id" value="<?= (int) $empresa['id'] ?>">
                                    <input name="nome" placeholder="Novo usuário da empresa">
                                    <input type="email" name="email" placeholder="email@empresa.com">
                                    <input type="password" name="senha" placeholder="senha inicial">
                                    <select name="tipo">
                                        <option value="operador">Operador</option>
                                        <option value="empresa_admin">Admin da empresa</option>
                                    </select>
                                    <button class="btn btn-sm btn-secondary">Adicionar usuário</button>
                                </form>
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
