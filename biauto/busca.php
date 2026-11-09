<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
$clientes = [];
$veiculos = [];
$ordens = [];
$orcamentos = [];
$pecas = [];

if (mb_strlen($q) >= 2) {
    $busca = '%' . $q . '%';

    if (pode_acessar('clientes')) {
        $stmt = $pdo->prepare('SELECT id, nome_razao, cpf_cnpj, telefone FROM clientes WHERE deleted_at IS NULL AND (nome_razao LIKE ? OR cpf_cnpj LIKE ? OR telefone LIKE ?) ORDER BY nome_razao LIMIT 8');
        $stmt->execute([$busca, $busca, $busca]);
        $clientes = $stmt->fetchAll();
    }

    if (pode_acessar('veiculos')) {
        $stmt = $pdo->prepare("SELECT v.id, v.placa, v.marca, v.modelo, c.nome_razao AS cliente FROM veiculos v INNER JOIN clientes c ON c.id = v.cliente_id WHERE v.deleted_at IS NULL AND c.deleted_at IS NULL AND (v.placa LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? OR c.nome_razao LIKE ?) ORDER BY v.marca, v.modelo LIMIT 8");
        $stmt->execute([$busca, $busca, $busca, $busca]);
        $veiculos = $stmt->fetchAll();
    }

    if (pode_acessar('ordens')) {
        $stmt = $pdo->prepare("SELECT o.id, o.numero, o.status, o.total, c.nome_razao AS cliente, CONCAT(v.marca, ' ', v.modelo) AS veiculo, v.placa FROM ordens_servico o INNER JOIN clientes c ON c.id = o.cliente_id INNER JOIN veiculos v ON v.id = o.veiculo_id WHERE o.numero LIKE ? OR c.nome_razao LIKE ? OR v.placa LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? ORDER BY o.data_entrada DESC LIMIT 8");
        $stmt->execute([$busca, $busca, $busca, $busca, $busca]);
        $ordens = $stmt->fetchAll();
    }

    if (pode_acessar('orcamentos')) {
        $stmt = $pdo->prepare("SELECT o.id, o.numero, o.status, o.total, c.nome_razao AS cliente, CONCAT(v.marca, ' ', v.modelo) AS veiculo, v.placa FROM orcamentos o INNER JOIN clientes c ON c.id = o.cliente_id INNER JOIN veiculos v ON v.id = o.veiculo_id WHERE o.numero LIKE ? OR c.nome_razao LIKE ? OR v.placa LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ? ORDER BY o.created_at DESC LIMIT 8");
        $stmt->execute([$busca, $busca, $busca, $busca, $busca]);
        $orcamentos = $stmt->fetchAll();
    }

    if (pode_acessar('pecas')) {
        $stmt = $pdo->prepare('SELECT id, codigo, nome, marca, estoque_atual, preco_venda FROM pecas WHERE deleted_at IS NULL AND (nome LIKE ? OR codigo LIKE ? OR codigo_barras LIKE ? OR marca LIKE ?) ORDER BY nome LIMIT 8');
        $stmt->execute([$busca, $busca, $busca, $busca]);
        $pecas = $stmt->fetchAll();
    }
}

$totalResultados = count($clientes) + count($veiculos) + count($ordens) + count($orcamentos) + count($pecas);

$pageTitle = 'Busca';
$currentPage = 'busca';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Busca global', $q === '' ? 'Pesquise ordens, clientes, veículos e peças.' : 'Resultados para: ' . $q) ?>

<?php if ($q === ''): ?>
    <div class="card section-card">
        <div class="empty-state">Digite um termo no campo de busca acima.</div>
    </div>
<?php elseif (mb_strlen($q) < 2): ?>
    <div class="card section-card">
        <div class="empty-state">Digite pelo menos 2 caracteres para pesquisar.</div>
    </div>
<?php elseif ($totalResultados === 0): ?>
    <div class="card section-card">
        <div class="empty-state">Nenhum resultado encontrado para <strong><?= h($q) ?></strong>.</div>
    </div>
<?php else: ?>
    <div class="grid" style="gap:18px">
        <?php if ($ordens): ?>
            <div class="card section-card">
                <div class="section-title"><div><h2>Ordens de Serviço</h2><p><?= count($ordens) ?> resultado(s)</p></div></div>
                <div class="table-shell">
                    <table class="table">
                        <thead><tr><th>OS</th><th>Cliente</th><th>Veículo</th><th>Status</th><th>Total</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($ordens as $ordem): ?>
                            <tr>
                                <td><strong><?= h($ordem['numero']) ?></strong></td>
                                <td><?= h($ordem['cliente']) ?></td>
                                <td><?= h($ordem['veiculo'] . ' • ' . $ordem['placa']) ?></td>
                                <td><?= h(ucfirst(str_replace('_', ' ', $ordem['status']))) ?></td>
                                <td class="money"><?= money_br($ordem['total']) ?></td>
                                <td><a class="btn" href="ordem_detalhe.php?id=<?= (int) $ordem['id'] ?>">Abrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($orcamentos): ?>
            <div class="card section-card">
                <div class="section-title"><div><h2>Orçamentos</h2><p><?= count($orcamentos) ?> resultado(s)</p></div></div>
                <div class="table-shell">
                    <table class="table">
                        <thead><tr><th>Número</th><th>Cliente</th><th>Veículo</th><th>Status</th><th>Total</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($orcamentos as $orcamento): ?>
                            <tr>
                                <td><strong><?= h($orcamento['numero']) ?></strong></td>
                                <td><?= h($orcamento['cliente']) ?></td>
                                <td><?= h($orcamento['veiculo'] . ' • ' . $orcamento['placa']) ?></td>
                                <td><?= h(ucfirst(str_replace('_', ' ', $orcamento['status']))) ?></td>
                                <td class="money"><?= money_br($orcamento['total']) ?></td>
                                <td><a class="btn" href="orcamento_detalhe.php?id=<?= (int) $orcamento['id'] ?>">Abrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($clientes): ?>
            <div class="card section-card">
                <div class="section-title"><div><h2>Clientes</h2><p><?= count($clientes) ?> resultado(s)</p></div></div>
                <div class="table-shell">
                    <table class="table">
                        <thead><tr><th>Cliente</th><th>CPF/CNPJ</th><th>Telefone</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><strong><?= h($cliente['nome_razao']) ?></strong></td>
                                <td><?= h($cliente['cpf_cnpj'] ?: '-') ?></td>
                                <td><?= h($cliente['telefone'] ?: '-') ?></td>
                                <td><a class="btn" href="clientes.php?q=<?= urlencode($cliente['nome_razao']) ?>">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($veiculos): ?>
            <div class="card section-card">
                <div class="section-title"><div><h2>Veículos</h2><p><?= count($veiculos) ?> resultado(s)</p></div></div>
                <div class="table-shell">
                    <table class="table">
                        <thead><tr><th>Veículo</th><th>Placa</th><th>Cliente</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($veiculos as $veiculo): ?>
                            <tr>
                                <td><strong><?= h($veiculo['marca'] . ' ' . $veiculo['modelo']) ?></strong></td>
                                <td><?= h($veiculo['placa']) ?></td>
                                <td><?= h($veiculo['cliente']) ?></td>
                                <td><a class="btn" href="veiculos.php?q=<?= urlencode($veiculo['placa']) ?>">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($pecas): ?>
            <div class="card section-card">
                <div class="section-title"><div><h2>Peças</h2><p><?= count($pecas) ?> resultado(s)</p></div></div>
                <div class="table-shell">
                    <table class="table">
                        <thead><tr><th>Peça</th><th>Código</th><th>Marca</th><th>Estoque</th><th>Venda</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($pecas as $peca): ?>
                            <tr>
                                <td><strong><?= h($peca['nome']) ?></strong></td>
                                <td><?= h($peca['codigo'] ?: '-') ?></td>
                                <td><?= h($peca['marca'] ?: '-') ?></td>
                                <td><?= h((string) $peca['estoque_atual']) ?></td>
                                <td class="money"><?= money_br($peca['preco_venda']) ?></td>
                                <td><a class="btn" href="pecas.php?q=<?= urlencode($peca['nome']) ?>">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
