<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $antes = $stmt->fetch();

        if ($antes) {
            $pdo->prepare('UPDATE clientes SET ativo = 0, deleted_at = NOW() WHERE id = ?')->execute([$id]);
            audit($pdo, 'clientes', $id, 'excluir', $antes, null);
            flash('success', 'Cliente removido com sucesso.');
        }

        redirect('clientes.php');
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT c.*, (SELECT COUNT(*) FROM veiculos v WHERE v.cliente_id = c.id AND v.deleted_at IS NULL) AS total_veiculos, (SELECT MAX(o.data_entrada) FROM ordens_servico o WHERE o.cliente_id = c.id) AS ultimo_servico FROM clientes c WHERE c.deleted_at IS NULL';
$params = [];

if ($q !== '') {
    $sql .= ' AND (c.nome_razao LIKE ? OR c.cpf_cnpj LIKE ? OR c.telefone LIKE ? OR c.whatsapp LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca, $busca];
}

$sql .= ' ORDER BY c.nome_razao ASC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

$acoes = [];
if (pode_alterar('clientes')) {
    $acoes[] = ['label' => 'Novo cliente', 'href' => 'cliente_form.php', 'icon' => 'plus', 'class' => 'btn-primary'];
}

$pageTitle = 'Clientes';
$currentPage = 'clientes';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header('Clientes', 'Consulte os clientes cadastrados e acesse seus veículos.', $acoes) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar por nome, CPF/CNPJ ou telefone"></div>
        <button class="btn" type="submit">Pesquisar</button>
        <?php if ($q !== ''): ?><a class="btn" href="clientes.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Cliente</th><th>CPF/CNPJ</th><th>Telefone</th><th>Veículos</th><th>Último serviço</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (!$clientes): ?><tr><td colspan="7" class="muted">Nenhum cliente encontrado.</td></tr><?php endif; ?>
            <?php foreach ($clientes as $cliente): ?>
                <tr>
                    <td><strong><?= h($cliente['nome_razao']) ?></strong></td>
                    <td><?= h($cliente['cpf_cnpj'] ?: '-') ?></td>
                    <td><?= h($cliente['telefone'] ?: $cliente['whatsapp'] ?: '-') ?></td>
                    <td><?= (int) $cliente['total_veiculos'] ?></td>
                    <td><?= date_br($cliente['ultimo_servico']) ?></td>
                    <td><span class="badge <?= (int) $cliente['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $cliente['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
                    <td>
                        <div class="actions">
                            <?php if (pode_alterar('clientes')): ?><a class="btn" href="cliente_form.php?id=<?= (int) $cliente['id'] ?>">Editar</a><?php endif; ?>
                            <a class="btn" href="veiculos.php?cliente_id=<?= (int) $cliente['id'] ?>">Veículos</a>
                            <?php if (pode_alterar('clientes')): ?>
                                <form method="post" onsubmit="return confirm('Remover este cliente?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
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
        <?php foreach ($clientes as $cliente): ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($cliente['nome_razao']) ?></strong><span class="badge <?= (int) $cliente['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $cliente['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></div>
                <p><?= h($cliente['cpf_cnpj'] ?: 'Sem CPF/CNPJ') ?></p>
                <p><?= h($cliente['telefone'] ?: $cliente['whatsapp'] ?: 'Sem telefone') ?></p>
                <div class="mobile-card-bottom">
                    <span><?= (int) $cliente['total_veiculos'] ?> veículo(s)</span>
                    <div class="actions">
                        <?php if (pode_alterar('clientes')): ?><a class="btn" href="cliente_form.php?id=<?= (int) $cliente['id'] ?>">Editar</a><?php endif; ?>
                        <a class="btn" href="veiculos.php?cliente_id=<?= (int) $cliente['id'] ?>">Veículos</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
