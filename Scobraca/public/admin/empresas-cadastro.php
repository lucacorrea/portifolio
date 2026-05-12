<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Cadastrar empresa';
$pageDescription = 'Crie uma empresa locatária e o usuário principal dela.';

$planos = db()->query('SELECT id, nome, preco FROM planos WHERE ativo = 1 ORDER BY preco ASC')->fetchAll();
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
                    <h2>Nova empresa locatária</h2>
                    <p class="muted">Cadastre a empresa, vincule um plano e crie o usuário principal de acesso.</p>
                </div>
                <a class="btn" href="<?= e(public_url('/admin/empresas.php')) ?>">Voltar para listagem</a>
            </div>

            <form method="post" action="<?= e(public_url('/actions/admin/salvar_empresa.php')) ?>" class="form-grid">
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
                <label>CPF ou CNPJ do usuário principal<input name="usuario_documento" required inputmode="numeric" placeholder="CPF ou CNPJ para login"></label>
                <label>Senha inicial<input type="password" name="usuario_senha" required autocomplete="new-password"></label>
                <div class="form-actions"><button class="btn btn-primary">Cadastrar empresa e usuário</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
