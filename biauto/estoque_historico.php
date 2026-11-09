<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
$tipo = (string) ($_GET['tipo'] ?? '');
$dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
$dataFim = trim((string) ($_GET['data_fim'] ?? ''));
$tiposPermitidos = ['entrada', 'saida', 'ajuste_positivo', 'ajuste_negativo', 'devolucao'];

$sql = "SELECT m.*, p.nome AS peca_nome, p.codigo AS peca_codigo, p.unidade, u.nome AS usuario_nome FROM estoque_movimentos m INNER JOIN pecas p ON p.id = m.peca_id LEFT JOIN usuarios u ON u.id = m.usuario_id WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= ' AND (p.nome LIKE ? OR p.codigo LIKE ? OR m.observacao LIKE ? OR m.origem_tipo LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca, $busca];
}

if (in_array($tipo, $tiposPermitidos, true)) {
    $sql .= ' AND m.tipo = ?';
    $params[] = $tipo;
}

if ($dataInicio !== '') {
    $sql .= ' AND m.created_at >= ?';
    $params[] = $dataInicio . ' 00:00:00';
}

if ($dataFim !== '') {
    $sql .= ' AND m.created_at <= ?';
    $params[] = $dataFim . ' 23:59:59';
}

$sql .= ' ORDER BY m.created_at DESC, m.id DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimentos = $stmt->fetchAll();

$labels = [
    'entrada' => ['Entrada', 'success', '+'],
    'saida' => ['Saída', 'danger', '-'],
    'ajuste_positivo' => ['Ajuste positivo', 'success', '+'],
    'ajuste_negativo' => ['Ajuste negativo', 'warning', '-'],
    'devolucao' => ['Devolução', 'info', '+'],
];

$pageTitle = 'Histórico de Estoque';
$currentPage = 'estoque';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Histórico de estoque', 'Consulte as entradas, saídas, ajustes e devoluções das peças.', [
    ['label' => 'Voltar para peças', 'href' => 'pecas.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar peça, código, origem ou observação"></div>
        <select class="select" name="tipo">
            <option value="">Todos os tipos</option>
            <?php foreach ($labels as $valor => $dados): ?><option value="<?= h($valor) ?>" <?= $tipo === $valor ? 'selected' : '' ?>><?= h($dados[0]) ?></option><?php endforeach; ?>
        </select>
        <input class="input" type="date" name="data_inicio" value="<?= h($dataInicio) ?>">
        <input class="input" type="date" name="data_fim" value="<?= h($dataFim) ?>">
        <button class="btn" type="submit">Filtrar</button>
        <?php if ($q !== '' || $tipo !== '' || $dataInicio !== '' || $dataFim !== ''): ?><a class="btn" href="estoque_historico.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Data</th><th>Peça</th><th>Tipo</th><th>Quantidade</th><th>Custo</th><th>Origem</th><th>Usuário</th><th>Observação</th></tr></thead>
            <tbody>
            <?php if (!$movimentos): ?><tr><td colspan="8" class="muted">Nenhuma movimentação encontrada.</td></tr><?php endif; ?>
            <?php foreach ($movimentos as $movimento): ?>
                <?php $info = $labels[$movimento['tipo']] ?? [ucfirst($movimento['tipo']), 'info', '']; ?>
                <tr>
                    <td><?= datetime_br($movimento['created_at']) ?></td>
                    <td><strong><?= h($movimento['peca_nome']) ?></strong><?= $movimento['peca_codigo'] ? '<div class="muted">' . h($movimento['peca_codigo']) . '</div>' : '' ?></td>
                    <td><span class="badge <?= h($info[1]) ?>"><?= h($info[0]) ?></span></td>
                    <td class="money"><?= h($info[2]) ?><?= number_format((float) $movimento['quantidade'], 3, ',', '.') ?> <?= h($movimento['unidade']) ?></td>
                    <td><?= $movimento['custo_unitario'] !== null ? money_br($movimento['custo_unitario']) : '-' ?></td>
                    <td><?= h($movimento['origem_tipo'] ?: 'Manual') ?><?= $movimento['origem_id'] ? ' #' . (int) $movimento['origem_id'] : '' ?></td>
                    <td><?= h($movimento['usuario_nome'] ?: 'Sistema') ?></td>
                    <td><?= h($movimento['observacao'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mobile-cards">
        <?php foreach ($movimentos as $movimento): ?>
            <?php $info = $labels[$movimento['tipo']] ?? [ucfirst($movimento['tipo']), 'info', '']; ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($movimento['peca_nome']) ?></strong><span class="badge <?= h($info[1]) ?>"><?= h($info[0]) ?></span></div>
                <p><?= datetime_br($movimento['created_at']) ?></p>
                <p><?= h($info[2]) ?><?= number_format((float) $movimento['quantidade'], 3, ',', '.') ?> <?= h($movimento['unidade']) ?></p>
                <p><?= h($movimento['observacao'] ?: 'Sem observação') ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
