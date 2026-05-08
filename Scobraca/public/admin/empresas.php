<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Empresas locatárias';
$pageDescription = 'Empresas que alugam o FluxPay.';

$pdo = db();
$planos = $pdo->query('SELECT id, nome, preco FROM planos WHERE ativo = 1 ORDER BY preco ASC')->fetchAll();
$empresas = $pdo->query(
    "SELECT e.*, p.nome AS plano_nome,
            (SELECT COUNT(*) FROM usuarios u WHERE u.empresa_id = e.id) AS total_usuarios
     FROM empresas e
     LEFT JOIN planos p ON p.id = e.plano_id
     ORDER BY e.id DESC"
)->fetchAll();
$totalEmpresas = count($empresas);
$empresasAtivas = count(array_filter($empresas, static fn (array $empresa): bool => in_array($empresa['status'], ['ativa', 'teste'], true)));
$empresasBloqueadas = count(array_filter($empresas, static fn (array $empresa): bool => $empresa['status'] === 'bloqueada'));
$usuariosLocatarios = array_sum(array_map(static fn (array $empresa): int => (int) $empresa['total_usuarios'], $empresas));
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

        <section class="grid four">
            <article class="card metric accent-blue"><span>Total de empresas</span><strong><?= $totalEmpresas ?></strong><small class="metric-note">Carteira cadastrada</small></article>
            <article class="card metric accent-green"><span>Ativas e teste</span><strong><?= $empresasAtivas ?></strong><small class="metric-note">Com acesso operacional</small></article>
            <article class="card metric accent-red"><span>Bloqueadas</span><strong><?= $empresasBloqueadas ?></strong><small class="metric-note">Exigem regularização</small></article>
            <article class="card metric accent-purple"><span>Usuários vinculados</span><strong><?= $usuariosLocatarios ?></strong><small class="metric-note">Soma por empresa</small></article>
        </section>

        <section class="report-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Pipeline de empresas</h2>
                        <p>Visão estimada para priorizar conversão, ativação e retenção.</p>
                    </div>
                    <span class="soft-label">Estimado</span>
                </div>
                <div class="progress-list">
                    <div class="progress-item"><div class="progress-head"><span>Novas em teste</span><strong>42%</strong></div><div class="progress-track"><span class="progress-fill" style="--value: 42%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Empresas prontas para upgrade</span><strong>27%</strong></div><div class="progress-track"><span class="progress-fill green" style="--value: 27%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Risco financeiro</span><strong>11%</strong></div><div class="progress-track"><span class="progress-fill yellow" style="--value: 11%;"></span></div></div>
                </div>
            </article>

            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Alertas comerciais</h2>
                        <p>Sugestões para orientar a operação.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-item"><span class="insight-dot green"></span><div><strong>Contato de boas-vindas</strong><span>Enviar onboarding para novas empresas nas primeiras 24 horas.</span></div></div>
                    <div class="insight-item"><span class="insight-dot yellow"></span><div><strong>Validar plano escolhido</strong><span>Empresas sem plano devem ser revisadas antes de liberar uso produtivo.</span></div></div>
                    <div class="insight-item"><span class="insight-dot red"></span><div><strong>Bloqueios recorrentes</strong><span>Separar bloqueio financeiro de cancelamento definitivo.</span></div></div>
                </div>
            </article>
        </section>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Cadastrar empresa locatária</h2>
                    <p class="muted">Aqui você cadastra a empresa que vai alugar o sistema e já cria o usuário principal dela.</p>
                </div>
                <span class="soft-label success">Novo contrato</span>
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
                <label>Senha inicial<input type="password" name="usuario_senha" required></label>
                <div class="form-actions"><button class="btn btn-primary">Cadastrar empresa e usuário</button></div>
            </form>
        </section>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Empresas cadastradas</h2>
                    <p class="muted">Lista operacional com status, plano e usuarios vinculados.</p>
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
                            </td>
                        </tr>
                        <tr class="subrow">
                            <td colspan="6">
                                <form method="post" action="<?= e(public_url('/actions/admin/salvar_usuario_empresa.php')) ?>" class="compact-form">
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
