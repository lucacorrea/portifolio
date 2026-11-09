<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$veiculoId = (int) ($_GET['veiculo_id'] ?? 0);
$mecanicoId = (int) ($_GET['mecanico_id'] ?? 0);

$statusLabels = [
    'aberta' => ['Aberta', 'info'],
    'em_diagnostico' => ['Em diagnóstico', 'info'],
    'aguardando_aprovacao' => ['Aguardando aprovação', 'warning'],
    'em_servico' => ['Em serviço', 'info'],
    'aguardando_peca' => ['Aguardando peça', 'warning'],
    'aguardando_retirada' => ['Aguardando retirada', 'warning'],
    'finalizada' => ['Finalizada', 'success'],
    'cancelada' => ['Cancelada', 'danger'],
];

$sql = "SELECT o.*, c.nome_razao AS cliente, CONCAT(v.marca, ' ', v.modelo) AS veiculo, v.placa, m.nome AS mecanico FROM ordens_servico o INNER JOIN clientes c ON c.id = o.cliente_id INNER JOIN veiculos v ON v.id = o.veiculo_id LEFT JOIN mecanicos m ON m.id = o.mecanico_responsavel_id WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= ' AND (o.numero LIKE ? OR c.nome_razao LIKE ? OR v.placa LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca, $busca, $busca];
}

if (isset($statusLabels[$status])) {
    $sql .= ' AND o.status = ?';
    $params[] = $status;
}

if ($veiculoId > 0) {
    $sql .= ' AND o.veiculo_id = ?';
    $params[] = $veiculoId;
}

if ($mecanicoId > 0) {
    $sql .= ' AND o.mecanico_responsavel_id = ?';
    $params[] = $mecanicoId;
}

$sql .= ' ORDER BY o.data_entrada DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordens = $stmt->fetchAll();
$mecanicos = $pdo->query('SELECT id, nome FROM mecanicos WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome')->fetchAll();

$pageTitle = 'Ordens de Serviço';
$currentPage = 'ordens';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header('Ordens de Serviço', 'Controle de entrada, execução e finalização das ordens.', [
    ['label' => 'Nova OS', 'href' => 'ordem_nova.php', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="OS, cliente, placa, marca ou modelo"></div>
        <select class="select" name="status">
            <option value="">Todos os status</option>
            <?php foreach ($statusLabels as $valor => $dados): ?>
                <option value="<?= h($valor) ?>" <?= $status === $valor ? 'selected' : '' ?>><?= h($dados[0]) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="select" name="mecanico_id">
            <option value="0">Todos os mecânicos</option>
            <?php foreach ($mecanicos as $mecanico): ?>
                <option value="<?= (int) $mecanico['id'] ?>" <?= $mecanicoId === (int) $mecanico['id'] ? 'selected' : '' ?>><?= h($mecanico['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($veiculoId > 0): ?><input type="hidden" name="veiculo_id" value="<?= $veiculoId ?>"><?php endif; ?>
        <button class="btn" type="submit">Pesquisar</button>
        <?php if ($q !== '' || $status !== '' || $veiculoId > 0 || $mecanicoId > 0): ?><a class="btn" href="ordens.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Mecânico</th><th>Entrada</th><th>Status</th><th>Total</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (!$ordens): ?><tr><td colspan="8" class="muted">Nenhuma ordem encontrada.</td></tr><?php endif; ?>
            <?php foreach ($ordens as $ordem): ?>
                <?php $statusInfo = $statusLabels[$ordem['status']] ?? [ucfirst(str_replace('_', ' ', $ordem['status'])), 'info']; ?>
                <tr>
                    <td><strong><?= h($ordem['numero']) ?></strong></td>
                    <td><?= h($ordem['cliente']) ?></td>
                    <td><?= h($ordem['veiculo'] . ' • ' . $ordem['placa']) ?></td>
                    <td><?= h($ordem['mecanico'] ?: '-') ?></td>
                    <td><?= datetime_br($ordem['data_entrada']) ?></td>
                    <td><span class="badge <?= h($statusInfo[1]) ?>"><span class="dot"></span><?= h($statusInfo[0]) ?></span></td>
                    <td class="money"><?= money_br($ordem['total']) ?></td>
                    <td><a class="btn" href="ordem_detalhe.php?id=<?= (int) $ordem['id'] ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mobile-cards">
        <?php foreach ($ordens as $ordem): ?>
            <?php $statusInfo = $statusLabels[$ordem['status']] ?? [ucfirst(str_replace('_', ' ', $ordem['status'])), 'info']; ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($ordem['numero']) ?></strong><span class="badge <?= h($statusInfo[1]) ?>"><?= h($statusInfo[0]) ?></span></div>
                <p><?= h($ordem['cliente']) ?></p>
                <p><?= h($ordem['veiculo'] . ' • ' . $ordem['placa']) ?></p>
                <div class="mobile-card-bottom"><span class="money"><?= money_br($ordem['total']) ?></span><a class="btn" href="ordem_detalhe.php?id=<?= (int) $ordem['id'] ?>">Abrir</a></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
