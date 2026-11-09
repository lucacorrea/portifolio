<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $veiculoId = (int) ($_POST['veiculo_id'] ?? 0);
    $mecanicoId = (int) ($_POST['mecanico_responsavel_id'] ?? 0);
    $kmEntrada = ($_POST['km_entrada'] ?? '') !== '' ? max(0, (int) $_POST['km_entrada']) : null;
    $dataEntrada = trim((string) ($_POST['data_entrada'] ?? ''));
    $previsao = trim((string) ($_POST['previsao_entrega'] ?? ''));
    $relato = nullable_string($_POST['relato_cliente'] ?? null);
    $diagnostico = nullable_string($_POST['diagnostico'] ?? null);
    $observacoes = nullable_string($_POST['observacoes'] ?? null);

    if ($clienteId <= 0 || $veiculoId <= 0) {
        flash('danger', 'Selecione o cliente e o veículo.');
        redirect('ordem_nova.php');
    }

    if ($configExigirMecanico && $mecanicoId <= 0) {
        flash('danger', 'Selecione o mecânico responsável.');
        redirect('ordem_nova.php');
    }

    $veiculoStmt = $pdo->prepare('SELECT id FROM veiculos WHERE id = ? AND cliente_id = ? AND deleted_at IS NULL AND ativo = 1');
    $veiculoStmt->execute([$veiculoId, $clienteId]);
    if (!$veiculoStmt->fetch()) {
        flash('danger', 'O veículo selecionado não pertence ao cliente informado.');
        redirect('ordem_nova.php');
    }

    $dataEntradaSql = $dataEntrada !== '' ? date('Y-m-d H:i:s', strtotime($dataEntrada)) : date('Y-m-d H:i:s');
    $previsaoSql = $previsao !== '' ? date('Y-m-d H:i:s', strtotime($previsao)) : null;
    $numero = next_number($pdo, 'ordens_servico', 'OS');

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO ordens_servico (numero, cliente_id, veiculo_id, mecanico_responsavel_id, status, km_entrada, data_entrada, previsao_entrega, relato_cliente, diagnostico, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$numero, $clienteId, $veiculoId, $mecanicoId > 0 ? $mecanicoId : null, 'aberta', $kmEntrada, $dataEntradaSql, $previsaoSql, $relato, $diagnostico, $observacoes]);
        $ordemId = (int) $pdo->lastInsertId();

        if ($mecanicoId > 0) {
            $pdo->prepare('INSERT IGNORE INTO ordem_servico_mecanicos (ordem_servico_id, mecanico_id, papel) VALUES (?, ?, ?)')->execute([$ordemId, $mecanicoId, 'Responsável']);
        }

        $pdo->prepare('INSERT INTO ordem_servico_historico (ordem_servico_id, status_novo, acao, observacao) VALUES (?, ?, ?, ?)')->execute([$ordemId, 'aberta', 'Ordem criada', 'Ordem de serviço cadastrada']);
        $pdo->commit();
        audit($pdo, 'ordens_servico', $ordemId, 'criar', null, ['numero' => $numero, 'cliente_id' => $clienteId, 'veiculo_id' => $veiculoId]);
        flash('success', 'Ordem de serviço criada. Adicione os serviços e peças.');
        redirect('ordem_detalhe.php?id=' . $ordemId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', 'Não foi possível criar a ordem de serviço.');
        redirect('ordem_nova.php');
    }
}

$clientes = $pdo->query('SELECT id, nome_razao FROM clientes WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome_razao')->fetchAll();
$veiculos = $pdo->query("SELECT v.id, v.cliente_id, v.placa, v.marca, v.modelo, c.nome_razao AS cliente FROM veiculos v INNER JOIN clientes c ON c.id = v.cliente_id WHERE v.deleted_at IS NULL AND v.ativo = 1 AND c.deleted_at IS NULL ORDER BY c.nome_razao, v.marca, v.modelo")->fetchAll();
$mecanicos = $pdo->query('SELECT id, nome FROM mecanicos WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome')->fetchAll();

$pageTitle = 'Nova Ordem de Serviço';
$currentPage = 'ordens';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header('Nova Ordem de Serviço', 'Registre a entrada do veículo. Os serviços e peças serão adicionados na tela da OS.', [
    ['label' => 'Cancelar', 'href' => 'ordens.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<form method="post" autocomplete="off">
    <?= csrf_field() ?>

    <div class="two-col">
        <div>
            <div class="card section-card">
                <div class="section-title"><div><h2>Cliente e veículo</h2><p>Selecione o proprietário e o veículo atendido.</p></div></div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Cliente</label>
                        <select class="select" name="cliente_id" id="clienteId" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?= (int) $cliente['id'] ?>"><?= h($cliente['nome_razao']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Veículo</label>
                        <select class="select" name="veiculo_id" id="veiculoId" required>
                            <option value="">Selecione o cliente primeiro</option>
                            <?php foreach ($veiculos as $veiculo): ?>
                                <option value="<?= (int) $veiculo['id'] ?>" data-cliente="<?= (int) $veiculo['cliente_id'] ?>" hidden><?= h($veiculo['marca'] . ' ' . $veiculo['modelo'] . ' • ' . $veiculo['placa']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quilometragem de entrada</label>
                        <input class="input" type="number" name="km_entrada" min="0" placeholder="Ex.: 83520">
                    </div>
                    <div class="form-group">
                        <label>Mecânico responsável<?= $configExigirMecanico ? ' *' : '' ?></label>
                        <select class="select" name="mecanico_responsavel_id" <?= $configExigirMecanico ? 'required' : '' ?>>
                            <option value="0"><?= $configExigirMecanico ? 'Selecione o mecânico' : 'Não definido' ?></option>
                            <?php foreach ($mecanicos as $mecanico): ?>
                                <option value="<?= (int) $mecanico['id'] ?>"><?= h($mecanico['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card section-card">
                <div class="section-title"><div><h2>Entrada e diagnóstico</h2><p>Registre as informações iniciais do atendimento.</p></div></div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Data de entrada</label>
                        <input type="datetime-local" class="input" name="data_entrada" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="form-group">
                        <label>Previsão de entrega</label>
                        <input type="datetime-local" class="input" name="previsao_entrega">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Relato do cliente</label>
                        <textarea class="input" name="relato_cliente" placeholder="Descreva o problema informado pelo cliente"></textarea>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Diagnóstico inicial</label>
                        <textarea class="input" name="diagnostico" placeholder="Avaliação técnica inicial"></textarea>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Observações</label>
                        <textarea class="input" name="observacoes"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card section-card" style="position:sticky;top:106px">
                <div class="section-title"><div><h2>Criar ordem</h2><p>Após criar, você poderá incluir serviços, peças, pagamentos e alterar o status.</p></div></div>
                <div class="operation-list">
                    <div class="operation-item"><div class="operation-copy"><strong>1. Entrada</strong><span>Cliente, veículo e relato</span></div></div>
                    <div class="operation-item"><div class="operation-copy"><strong>2. Itens</strong><span>Serviços e peças na tela da OS</span></div></div>
                    <div class="operation-item"><div class="operation-copy"><strong>3. Execução</strong><span>Status, histórico e pagamento</span></div></div>
                </div>
                <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center;margin-top:16px">Criar ordem de serviço</button>
            </div>
        </div>
    </div>
</form>

<script>
const cliente = document.getElementById('clienteId');
const veiculo = document.getElementById('veiculoId');
function filtrarVeiculos() {
    const clienteId = cliente.value;
    let primeiro = true;
    Array.from(veiculo.options).forEach((option, index) => {
        if (index === 0) return;
        const mostrar = clienteId !== '' && option.dataset.cliente === clienteId;
        option.hidden = !mostrar;
        option.disabled = !mostrar;
        if (mostrar && primeiro) primeiro = false;
    });
    veiculo.value = '';
}
cliente.addEventListener('change', filtrarVeiculos);
filtrarVeiculos();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
