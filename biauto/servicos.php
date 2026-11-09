<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM servicos WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $antes = $stmt->fetch();

        if ($antes) {
            $pdo->prepare('UPDATE servicos SET ativo = 0, deleted_at = NOW() WHERE id = ?')->execute([$id]);
            audit($pdo, 'servicos', $id, 'excluir', $antes, null);
            flash('success', 'Serviço removido com sucesso.');
        }

        redirect('servicos.php');
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$sql = 'SELECT * FROM servicos WHERE deleted_at IS NULL';
$params = [];

if ($q !== '') {
    $sql .= ' AND (nome LIKE ? OR categoria LIKE ? OR descricao LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca];
}

if ($status === 'ativo') {
    $sql .= ' AND ativo = 1';
} elseif ($status === 'inativo') {
    $sql .= ' AND ativo = 0';
}

$sql .= ' ORDER BY nome ASC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$servicos = $stmt->fetchAll();

$acoes = [];
if (pode_alterar('servicos')) {
    $acoes[] = ['label' => 'Novo serviço', 'href' => 'servico_form.php', 'icon' => 'plus', 'class' => 'btn-primary'];
}

$pageTitle = 'Serviços';
$currentPage = 'servicos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header('Serviços', 'Catálogo de serviços com categoria, tempo estimado e preço-base.', $acoes) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar serviço, categoria ou descrição"></div>
        <select class="select" name="status">
            <option value="">Todos os status</option>
            <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Ativos</option>
            <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativos</option>
        </select>
        <button class="btn" type="submit">Pesquisar</button>
        <?php if ($q !== '' || $status !== ''): ?><a class="btn" href="servicos.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Serviço</th><th>Categoria</th><th>Tempo estimado</th><th>Valor base</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (!$servicos): ?><tr><td colspan="6" class="muted">Nenhum serviço encontrado.</td></tr><?php endif; ?>
            <?php foreach ($servicos as $servico): ?>
                <tr>
                    <td><strong><?= h($servico['nome']) ?></strong></td>
                    <td><?= h($servico['categoria'] ?: '-') ?></td>
                    <td><?= $servico['tempo_estimado_min'] !== null ? (int) $servico['tempo_estimado_min'] . ' min' : '-' ?></td>
                    <td class="money"><?= money_br($servico['valor_base']) ?></td>
                    <td><span class="badge <?= (int) $servico['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $servico['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
                    <td>
                        <?php if (pode_alterar('servicos')): ?>
                            <div class="actions">
                                <a class="btn" href="servico_form.php?id=<?= (int) $servico['id'] ?>">Editar</a>
                                <form method="post" onsubmit="return confirm('Remover este serviço?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= (int) $servico['id'] ?>">
                                    <button class="btn btn-danger" type="submit">Excluir</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="muted">Somente consulta</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mobile-cards">
        <?php foreach ($servicos as $servico): ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($servico['nome']) ?></strong><span class="badge <?= (int) $servico['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $servico['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></div>
                <p><?= h($servico['categoria'] ?: 'Sem categoria') ?></p>
                <div class="mobile-card-bottom"><span class="money"><?= money_br($servico['valor_base']) ?></span><?php if (pode_alterar('servicos')): ?><a class="btn" href="servico_form.php?id=<?= (int) $servico['id'] ?>">Editar</a><?php endif; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
