<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM veiculos WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $antes = $stmt->fetch();

        if ($antes) {
            $pdo->prepare('UPDATE veiculos SET ativo = 0, deleted_at = NOW() WHERE id = ?')->execute([$id]);
            audit($pdo, 'veiculos', $id, 'excluir', $antes, null);
            flash('success', 'Veículo removido com sucesso.');
        }

        redirect('veiculos.php');
    }
}

$clientes = $pdo->query('SELECT id, nome_razao FROM clientes WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome_razao')->fetchAll();
$q = trim((string) ($_GET['q'] ?? ''));
$clienteFiltro = (int) ($_GET['cliente_id'] ?? 0);
$sql = 'SELECT v.*, c.nome_razao AS cliente_nome, (SELECT MAX(o.data_entrada) FROM ordens_servico o WHERE o.veiculo_id = v.id) AS ultimo_servico FROM veiculos v INNER JOIN clientes c ON c.id = v.cliente_id WHERE v.deleted_at IS NULL';
$params = [];

if ($q !== '') {
    $sql .= ' AND (v.placa LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? OR c.nome_razao LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca, $busca];
}

if ($clienteFiltro > 0) {
    $sql .= ' AND v.cliente_id = ?';
    $params[] = $clienteFiltro;
}

$sql .= ' ORDER BY v.marca, v.modelo, v.placa LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$veiculos = $stmt->fetchAll();

$acoes = [];
if (pode_alterar('veiculos')) {
    $hrefNovo = 'veiculo_form.php' . ($clienteFiltro > 0 ? '?cliente_id=' . $clienteFiltro : '');
    $acoes[] = ['label' => 'Novo veículo', 'href' => $hrefNovo, 'icon' => 'plus', 'class' => 'btn-primary'];
}

$pageTitle = 'Veículos';
$currentPage = 'veiculos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header('Veículos', 'Consulte os veículos cadastrados e seu histórico de manutenção.', $acoes) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar por placa, marca, modelo ou cliente"></div>
        <select class="select" name="cliente_id">
            <option value="0">Todos os clientes</option>
            <?php foreach ($clientes as $cliente): ?>
                <option value="<?= (int) $cliente['id'] ?>" <?= $clienteFiltro === (int) $cliente['id'] ? 'selected' : '' ?>><?= h($cliente['nome_razao']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Pesquisar</button>
        <?php if ($q !== '' || $clienteFiltro > 0): ?><a class="btn" href="veiculos.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Veículo</th><th>Placa</th><th>Cliente</th><th>Ano</th><th>KM atual</th><th>Último serviço</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (!$veiculos): ?><tr><td colspan="8" class="muted">Nenhum veículo encontrado.</td></tr><?php endif; ?>
            <?php foreach ($veiculos as $veiculo): ?>
                <tr>
                    <td><strong><?= h($veiculo['marca'] . ' ' . $veiculo['modelo']) ?></strong><?= $veiculo['versao'] ? '<div class="muted">' . h($veiculo['versao']) . '</div>' : '' ?></td>
                    <td><?= h($veiculo['placa']) ?></td>
                    <td><?= h($veiculo['cliente_nome']) ?></td>
                    <td><?= h((string) ($veiculo['ano_modelo'] ?: $veiculo['ano_fabricacao'] ?: '-')) ?></td>
                    <td><?= $veiculo['km_atual'] !== null ? number_format((int) $veiculo['km_atual'], 0, ',', '.') . ' km' : '-' ?></td>
                    <td><?= date_br($veiculo['ultimo_servico']) ?></td>
                    <td><span class="badge <?= (int) $veiculo['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $veiculo['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
                    <td>
                        <div class="actions">
                            <?php if (pode_alterar('veiculos')): ?><a class="btn" href="veiculo_form.php?id=<?= (int) $veiculo['id'] ?>">Editar</a><?php endif; ?>
                            <a class="btn" href="ordens.php?veiculo_id=<?= (int) $veiculo['id'] ?>">Histórico</a>
                            <?php if (pode_alterar('veiculos')): ?>
                                <form method="post" onsubmit="return confirm('Remover este veículo?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= (int) $veiculo['id'] ?>">
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
        <?php foreach ($veiculos as $veiculo): ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($veiculo['marca'] . ' ' . $veiculo['modelo']) ?></strong><span><?= h($veiculo['placa']) ?></span></div>
                <p><?= h($veiculo['cliente_nome']) ?></p>
                <p><?= $veiculo['km_atual'] !== null ? number_format((int) $veiculo['km_atual'], 0, ',', '.') . ' km' : 'KM não informado' ?></p>
                <div class="mobile-card-bottom">
                    <span><?= date_br($veiculo['ultimo_servico']) ?></span>
                    <div class="actions">
                        <?php if (pode_alterar('veiculos')): ?><a class="btn" href="veiculo_form.php?id=<?= (int) $veiculo['id'] ?>">Editar</a><?php endif; ?>
                        <a class="btn" href="ordens.php?veiculo_id=<?= (int) $veiculo['id'] ?>">Histórico</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
