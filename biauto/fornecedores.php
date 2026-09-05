<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM fornecedores WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $fornecedor = $stmt->fetch();

        if ($fornecedor) {
            $pdo->prepare('UPDATE fornecedores SET ativo = 0, deleted_at = NOW() WHERE id = ?')->execute([$id]);
            audit($pdo, 'fornecedores', $id, 'excluir', $fornecedor, null);
            flash('success', 'Fornecedor removido com sucesso.');
        }

        redirect('fornecedores.php');
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$sql = 'SELECT f.*, (SELECT COUNT(*) FROM pecas p WHERE p.fornecedor_id = f.id AND p.deleted_at IS NULL) AS total_pecas FROM fornecedores f WHERE f.deleted_at IS NULL';
$params = [];

if ($q !== '') {
    $sql .= ' AND (f.nome_razao LIKE ? OR f.cpf_cnpj LIKE ? OR f.contato LIKE ? OR f.telefone LIKE ? OR f.email LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca, $busca, $busca];
}

if ($status === 'ativo') {
    $sql .= ' AND f.ativo = 1';
} elseif ($status === 'inativo') {
    $sql .= ' AND f.ativo = 0';
}

$sql .= ' ORDER BY f.nome_razao ASC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fornecedores = $stmt->fetchAll();

$acoes = [
    ['label' => 'Histórico de estoque', 'href' => 'estoque_historico.php', 'icon' => 'report', 'class' => 'btn-secondary'],
];
if (pode_alterar('pecas')) {
    $acoes[] = ['label' => 'Novo fornecedor', 'href' => 'fornecedor_form.php', 'icon' => 'plus', 'class' => 'btn-primary'];
}

$pageTitle = 'Fornecedores';
$currentPage = 'fornecedores';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header('Fornecedores', 'Cadastre os fornecedores utilizados na compra de peças e materiais.', $acoes) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar nome, CNPJ, contato, telefone ou e-mail"></div>
        <select class="select" name="status">
            <option value="">Todos os status</option>
            <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Ativos</option>
            <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativos</option>
        </select>
        <button class="btn" type="submit">Pesquisar</button>
        <?php if ($q !== '' || $status !== ''): ?><a class="btn" href="fornecedores.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Fornecedor</th><th>CPF/CNPJ</th><th>Contato</th><th>Telefone</th><th>Peças</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (!$fornecedores): ?><tr><td colspan="7" class="muted">Nenhum fornecedor encontrado.</td></tr><?php endif; ?>
            <?php foreach ($fornecedores as $fornecedor): ?>
                <tr>
                    <td><strong><?= h($fornecedor['nome_razao']) ?></strong><?= $fornecedor['email'] ? '<div class="muted">' . h($fornecedor['email']) . '</div>' : '' ?></td>
                    <td><?= h($fornecedor['cpf_cnpj'] ?: '-') ?></td>
                    <td><?= h($fornecedor['contato'] ?: '-') ?></td>
                    <td><?= h($fornecedor['telefone'] ?: '-') ?></td>
                    <td><?= (int) $fornecedor['total_pecas'] ?></td>
                    <td><span class="badge <?= (int) $fornecedor['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $fornecedor['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
                    <td>
                        <?php if (pode_alterar('pecas')): ?>
                            <div class="actions">
                                <a class="btn" href="fornecedor_form.php?id=<?= (int) $fornecedor['id'] ?>">Editar</a>
                                <form method="post" onsubmit="return confirm('Remover este fornecedor?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= (int) $fornecedor['id'] ?>">
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
        <?php foreach ($fornecedores as $fornecedor): ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($fornecedor['nome_razao']) ?></strong><span class="badge <?= (int) $fornecedor['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $fornecedor['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></div>
                <p><?= h($fornecedor['cpf_cnpj'] ?: 'Sem CPF/CNPJ') ?></p>
                <p><?= h($fornecedor['telefone'] ?: $fornecedor['email'] ?: 'Sem contato') ?></p>
                <div class="mobile-card-bottom"><span><?= (int) $fornecedor['total_pecas'] ?> peça(s)</span><?php if (pode_alterar('pecas')): ?><a class="btn" href="fornecedor_form.php?id=<?= (int) $fornecedor['id'] ?>">Editar</a><?php endif; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
