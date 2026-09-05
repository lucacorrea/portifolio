<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$statusLabels = [
    'rascunho' => ['Rascunho', 'info'],
    'enviado' => ['Enviado', 'info'],
    'aguardando_aprovacao' => ['Aguardando aprovação', 'warning'],
    'aprovado' => ['Aprovado', 'success'],
    'recusado' => ['Recusado', 'danger'],
    'expirado' => ['Expirado', 'warning'],
    'convertido' => ['Convertido em OS', 'success'],
];

$sql = "SELECT o.*, c.nome_razao AS cliente, CONCAT(v.marca, ' ', v.modelo) AS veiculo, v.placa FROM orcamentos o INNER JOIN clientes c ON c.id = o.cliente_id INNER JOIN veiculos v ON v.id = o.veiculo_id WHERE 1=1";
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

$sql .= ' ORDER BY o.created_at DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orcamentos = $stmt->fetchAll();

$pageTitle = 'Orçamentos';
$currentPage = 'orcamentos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header('Orçamentos', 'Crie, acompanhe e converta orçamentos em ordens de serviço.', [
    ['label' => 'Novo orçamento', 'href' => 'orcamento_novo.php', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Número, cliente, placa, marca ou modelo"></div>
        <select class="select" name="status">
            <option value="">Todos os status</option>
            <?php foreach ($statusLabels as $valor => $dados): ?>
                <option value="<?= h($valor) ?>" <?= $status === $valor ? 'selected' : '' ?>><?= h($dados[0]) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Pesquisar</button>
        <?php if ($q !== '' || $status !== ''): ?><a class="btn" href="orcamentos.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Nº</th><th>Cliente</th><th>Veículo</th><th>Emissão</th><th>Validade</th><th>Status</th><th>Total</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (!$orcamentos): ?><tr><td colspan="8" class="muted">Nenhum orçamento encontrado.</td></tr><?php endif; ?>
            <?php foreach ($orcamentos as $orcamento): ?>
                <?php $statusInfo = $statusLabels[$orcamento['status']] ?? [ucfirst(str_replace('_', ' ', $orcamento['status'])), 'info']; ?>
                <tr>
                    <td><strong><?= h($orcamento['numero']) ?></strong></td>
                    <td><?= h($orcamento['cliente']) ?></td>
                    <td><?= h($orcamento['veiculo'] . ' • ' . $orcamento['placa']) ?></td>
                    <td><?= date_br($orcamento['created_at']) ?></td>
                    <td><?= date_br($orcamento['validade_ate']) ?></td>
                    <td><span class="badge <?= h($statusInfo[1]) ?>"><span class="dot"></span><?= h($statusInfo[0]) ?></span></td>
                    <td class="money"><?= money_br($orcamento['total']) ?></td>
                    <td><a class="btn" href="orcamento_detalhe.php?id=<?= (int) $orcamento['id'] ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mobile-cards">
        <?php foreach ($orcamentos as $orcamento): ?>
            <?php $statusInfo = $statusLabels[$orcamento['status']] ?? [ucfirst(str_replace('_', ' ', $orcamento['status'])), 'info']; ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($orcamento['numero']) ?></strong><span class="badge <?= h($statusInfo[1]) ?>"><?= h($statusInfo[0]) ?></span></div>
                <p><?= h($orcamento['cliente']) ?></p>
                <p><?= h($orcamento['veiculo'] . ' • ' . $orcamento['placa']) ?></p>
                <div class="mobile-card-bottom"><span class="money"><?= money_br($orcamento['total']) ?></span><a class="btn" href="orcamento_detalhe.php?id=<?= (int) $orcamento['id'] ?>">Abrir</a></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
