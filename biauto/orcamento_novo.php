<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $veiculoId = (int) ($_POST['veiculo_id'] ?? 0);
    $validade = trim((string) ($_POST['validade_ate'] ?? ''));
    $observacoes = nullable_string($_POST['observacoes'] ?? null);

    if ($clienteId <= 0 || $veiculoId <= 0) {
        flash('danger', 'Selecione o cliente e o veículo.');
        redirect('orcamento_novo.php');
    }

    $stmt = $pdo->prepare('SELECT id FROM veiculos WHERE id = ? AND cliente_id = ? AND deleted_at IS NULL AND ativo = 1');
    $stmt->execute([$veiculoId, $clienteId]);
    if (!$stmt->fetch()) {
        flash('danger', 'O veículo selecionado não pertence ao cliente informado.');
        redirect('orcamento_novo.php');
    }

    $numero = next_number($pdo, 'orcamentos', 'ORC');
    $validadeSql = $validade !== '' ? $validade : date('Y-m-d', strtotime('+7 days'));

    try {
        $stmt = $pdo->prepare('INSERT INTO orcamentos (numero, cliente_id, veiculo_id, status, validade_ate, observacoes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$numero, $clienteId, $veiculoId, 'rascunho', $validadeSql, $observacoes]);
        $id = (int) $pdo->lastInsertId();
        audit($pdo, 'orcamentos', $id, 'criar', null, ['numero' => $numero, 'cliente_id' => $clienteId, 'veiculo_id' => $veiculoId]);
        flash('success', 'Orçamento criado. Adicione os serviços e peças.');
        redirect('orcamento_detalhe.php?id=' . $id);
    } catch (Throwable $e) {
        flash('danger', 'Não foi possível criar o orçamento.');
        redirect('orcamento_novo.php');
    }
}

$clientes = $pdo->query('SELECT id, nome_razao FROM clientes WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome_razao')->fetchAll();
$veiculos = $pdo->query("SELECT v.id, v.cliente_id, v.placa, v.marca, v.modelo FROM veiculos v INNER JOIN clientes c ON c.id = v.cliente_id WHERE v.deleted_at IS NULL AND v.ativo = 1 AND c.deleted_at IS NULL ORDER BY c.nome_razao, v.marca, v.modelo")->fetchAll();

$pageTitle = 'Novo Orçamento';
$currentPage = 'orcamentos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header('Novo orçamento', 'Selecione o cliente e o veículo para iniciar o orçamento.', [
    ['label' => 'Cancelar', 'href' => 'orcamentos.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="two-col">
        <div class="card section-card">
            <div class="section-title"><div><h2>Dados do orçamento</h2><p>O orçamento será criado como rascunho.</p></div></div>
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente</label>
                    <select class="select" name="cliente_id" id="clienteId" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($clientes as $cliente): ?><option value="<?= (int) $cliente['id'] ?>"><?= h($cliente['nome_razao']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Veículo</label>
                    <select class="select" name="veiculo_id" id="veiculoId" required>
                        <option value="">Selecione o cliente primeiro</option>
                        <?php foreach ($veiculos as $veiculo): ?><option value="<?= (int) $veiculo['id'] ?>" data-cliente="<?= (int) $veiculo['cliente_id'] ?>" hidden><?= h($veiculo['marca'] . ' ' . $veiculo['modelo'] . ' • ' . $veiculo['placa']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Validade</label>
                    <input class="input" type="date" name="validade_ate" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Observações</label>
                    <textarea class="input" name="observacoes"></textarea>
                </div>
            </div>
        </div>

        <div>
            <div class="card section-card" style="position:sticky;top:106px">
                <div class="section-title"><div><h2>Próxima etapa</h2><p>Depois de criar, você poderá adicionar itens e controlar a aprovação.</p></div></div>
                <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Criar orçamento</button>
            </div>
        </div>
    </div>
</form>

<script>
const cliente = document.getElementById('clienteId');
const veiculo = document.getElementById('veiculoId');
function filtrarVeiculos() {
    const clienteId = cliente.value;
    Array.from(veiculo.options).forEach((option, index) => {
        if (index === 0) return;
        const mostrar = clienteId !== '' && option.dataset.cliente === clienteId;
        option.hidden = !mostrar;
        option.disabled = !mostrar;
    });
    veiculo.value = '';
}
cliente.addEventListener('change', filtrarVeiculos);
filtrarVeiculos();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
