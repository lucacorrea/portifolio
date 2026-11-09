<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!usuario_admin()) {
    flash('warning', 'Somente o administrador pode gerenciar usuários.');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = (string) ($_POST['acao'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($id === (int) ($_SESSION['usuario_id'] ?? 0)) {
        flash('warning', 'Você não pode alterar o status ou excluir seu próprio usuário.');
        redirect('usuarios.php');
    }

    if ($acao === 'status') {
        $stmt = $pdo->prepare('SELECT id, ativo FROM usuarios WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $novoStatus = (int) $usuario['ativo'] === 1 ? 0 : 1;
            $pdo->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?')->execute([$novoStatus, $id]);
            audit($pdo, 'usuarios', $id, $novoStatus === 1 ? 'ativar' : 'desativar');
            flash('success', $novoStatus === 1 ? 'Usuário ativado.' : 'Usuário desativado.');
        }

        redirect('usuarios.php');
    }

    if ($acao === 'excluir') {
        $stmt = $pdo->prepare('UPDATE usuarios SET deleted_at = NOW(), ativo = 0 WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            audit($pdo, 'usuarios', $id, 'excluir');
            flash('success', 'Usuário removido do sistema.');
        }

        redirect('usuarios.php');
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id, nome, email, nivel, ativo, ultimo_login_em, created_at FROM usuarios WHERE deleted_at IS NULL';
$params = [];

if ($q !== '') {
    $sql .= ' AND (nome LIKE ? OR email LIKE ? OR nivel LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca];
}

$sql .= ' ORDER BY nome';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$pageTitle = 'Usuários';
$currentPage = 'usuarios';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header('Usuários', 'Controle quem pode acessar o sistema e o nível de cada conta.', [
    ['label' => 'Novo usuário', 'href' => 'usuario_form.php', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar por nome, e-mail ou nível"></div>
        <button class="btn" type="submit">Pesquisar</button>
        <?php if ($q !== ''): ?><a class="btn" href="usuarios.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Usuário</th><th>Nível</th><th>Status</th><th>Último acesso</th><th>Cadastrado em</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (!$usuarios): ?><tr><td colspan="6" class="muted">Nenhum usuário encontrado.</td></tr><?php endif; ?>
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><strong><?= h($usuario['nome']) ?></strong><div class="muted"><?= h($usuario['email']) ?></div></td>
                    <td><?= h(ucfirst($usuario['nivel'])) ?></td>
                    <td><span class="badge <?= (int) $usuario['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $usuario['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
                    <td><?= datetime_br($usuario['ultimo_login_em']) ?></td>
                    <td><?= date_br($usuario['created_at']) ?></td>
                    <td>
                        <div class="actions">
                            <a class="btn" href="usuario_form.php?id=<?= (int) $usuario['id'] ?>">Editar</a>
                            <?php if ((int) $usuario['id'] !== (int) ($_SESSION['usuario_id'] ?? 0)): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="status">
                                    <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                                    <button class="btn" type="submit"><?= (int) $usuario['ativo'] === 1 ? 'Desativar' : 'Ativar' ?></button>
                                </form>
                                <form method="post" onsubmit="return confirm('Remover este usuário?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                                    <button class="btn btn-danger" type="submit">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mobile-cards">
        <?php foreach ($usuarios as $usuario): ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($usuario['nome']) ?></strong><span class="badge <?= (int) $usuario['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $usuario['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></div>
                <p><?= h($usuario['email']) ?></p>
                <p>Nível: <?= h(ucfirst($usuario['nivel'])) ?></p>
                <div class="mobile-card-bottom"><span><?= datetime_br($usuario['ultimo_login_em']) ?></span><a class="btn" href="usuario_form.php?id=<?= (int) $usuario['id'] ?>">Editar</a></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
