<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!pode_alterar('veiculos')) {
    flash('warning', 'Seu usuário possui acesso somente para consulta de veículos.');
    redirect('veiculos.php');
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$clienteInicial = (int) ($_GET['cliente_id'] ?? 0);
$veiculo = null;

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM veiculos WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $veiculo = $stmt->fetch();

    if (!$veiculo) {
        flash('danger', 'Veículo não encontrado.');
        redirect('veiculos.php');
    }
}

$clientes = $pdo->query('SELECT id, nome_razao FROM clientes WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome_razao')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $placa = normalize_plate($_POST['placa'] ?? '');
    $marca = trim((string) ($_POST['marca'] ?? ''));
    $modelo = trim((string) ($_POST['modelo'] ?? ''));

    if ($clienteId <= 0 || $placa === '' || $marca === '' || $modelo === '') {
        flash('danger', 'Informe cliente, placa, marca e modelo.');
        redirect('veiculo_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    $clienteStmt = $pdo->prepare('SELECT id FROM clientes WHERE id = ? AND deleted_at IS NULL AND ativo = 1');
    $clienteStmt->execute([$clienteId]);
    if (!$clienteStmt->fetch()) {
        flash('danger', 'Cliente inválido.');
        redirect('veiculo_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    $dados = [
        'cliente_id' => $clienteId,
        'placa' => $placa,
        'marca' => $marca,
        'modelo' => $modelo,
        'versao' => nullable_string($_POST['versao'] ?? null),
        'ano_fabricacao' => ($_POST['ano_fabricacao'] ?? '') !== '' ? (int) $_POST['ano_fabricacao'] : null,
        'ano_modelo' => ($_POST['ano_modelo'] ?? '') !== '' ? (int) $_POST['ano_modelo'] : null,
        'cor' => nullable_string($_POST['cor'] ?? null),
        'combustivel' => nullable_string($_POST['combustivel'] ?? null),
        'chassi' => nullable_string($_POST['chassi'] ?? null),
        'renavam' => nullable_string($_POST['renavam'] ?? null),
        'km_atual' => ($_POST['km_atual'] ?? '') !== '' ? max(0, (int) $_POST['km_atual']) : null,
        'observacoes' => nullable_string($_POST['observacoes'] ?? null),
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
    ];

    try {
        if ($id > 0) {
            $antes = $veiculo;
            $stmt = $pdo->prepare('UPDATE veiculos SET cliente_id = ?, placa = ?, marca = ?, modelo = ?, versao = ?, ano_fabricacao = ?, ano_modelo = ?, cor = ?, combustivel = ?, chassi = ?, renavam = ?, km_atual = ?, observacoes = ?, ativo = ? WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([
                $dados['cliente_id'], $dados['placa'], $dados['marca'], $dados['modelo'], $dados['versao'], $dados['ano_fabricacao'], $dados['ano_modelo'], $dados['cor'], $dados['combustivel'], $dados['chassi'], $dados['renavam'], $dados['km_atual'], $dados['observacoes'], $dados['ativo'], $id,
            ]);
            audit($pdo, 'veiculos', $id, 'atualizar', $antes, $dados);
            flash('success', 'Veículo atualizado com sucesso.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO veiculos (cliente_id, placa, marca, modelo, versao, ano_fabricacao, ano_modelo, cor, combustivel, chassi, renavam, km_atual, observacoes, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $dados['cliente_id'], $dados['placa'], $dados['marca'], $dados['modelo'], $dados['versao'], $dados['ano_fabricacao'], $dados['ano_modelo'], $dados['cor'], $dados['combustivel'], $dados['chassi'], $dados['renavam'], $dados['km_atual'], $dados['observacoes'], $dados['ativo'],
            ]);
            $id = (int) $pdo->lastInsertId();
            audit($pdo, 'veiculos', $id, 'criar', null, $dados);
            flash('success', 'Veículo cadastrado com sucesso.');
        }

        redirect('veiculos.php?cliente_id=' . $clienteId);
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            flash('danger', 'Placa, chassi ou RENAVAM já cadastrado.');
        } else {
            flash('danger', 'Não foi possível salvar o veículo.');
        }

        redirect('veiculo_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }
}

$clienteSelecionado = (int) ($veiculo['cliente_id'] ?? $clienteInicial);
$pageTitle = $id > 0 ? 'Editar Veículo' : 'Novo Veículo';
$currentPage = 'veiculos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header($id > 0 ? 'Editar veículo' : 'Novo veículo', $id > 0 ? 'Atualize os dados do veículo selecionado.' : 'Cadastre o veículo e vincule ao proprietário.', [
    ['label' => 'Voltar', 'href' => 'veiculos.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="card section-card">
        <div class="section-title"><div><h2>Identificação</h2><p>Dados principais do veículo.</p></div></div>
        <div class="form-row">
            <div class="form-group">
                <label>Cliente</label>
                <select class="select" name="cliente_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= (int) $cliente['id'] ?>" <?= $clienteSelecionado === (int) $cliente['id'] ? 'selected' : '' ?>><?= h($cliente['nome_razao']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Placa</label><input class="input" name="placa" maxlength="10" required value="<?= h($veiculo['placa'] ?? '') ?>"></div>
            <div class="form-group"><label>Marca</label><input class="input" name="marca" maxlength="100" required value="<?= h($veiculo['marca'] ?? '') ?>"></div>
            <div class="form-group"><label>Modelo</label><input class="input" name="modelo" maxlength="120" required value="<?= h($veiculo['modelo'] ?? '') ?>"></div>
            <div class="form-group"><label>Versão</label><input class="input" name="versao" maxlength="120" value="<?= h($veiculo['versao'] ?? '') ?>"></div>
            <div class="form-group"><label>Cor</label><input class="input" name="cor" maxlength="60" value="<?= h($veiculo['cor'] ?? '') ?>"></div>
            <div class="form-group"><label>Ano fabricação</label><input class="input" type="number" name="ano_fabricacao" min="1900" max="2100" value="<?= h(isset($veiculo['ano_fabricacao']) ? (string) $veiculo['ano_fabricacao'] : '') ?>"></div>
            <div class="form-group"><label>Ano modelo</label><input class="input" type="number" name="ano_modelo" min="1900" max="2100" value="<?= h(isset($veiculo['ano_modelo']) ? (string) $veiculo['ano_modelo'] : '') ?>"></div>
            <div class="form-group">
                <label>Combustível</label>
                <select class="select" name="combustivel">
                    <option value="">Não informado</option>
                    <?php foreach (['gasolina' => 'Gasolina', 'etanol' => 'Etanol', 'flex' => 'Flex', 'diesel' => 'Diesel', 'gnv' => 'GNV', 'eletrico' => 'Elétrico', 'hibrido' => 'Híbrido', 'outro' => 'Outro'] as $valor => $rotulo): ?>
                        <option value="<?= h($valor) ?>" <?= ($veiculo['combustivel'] ?? '') === $valor ? 'selected' : '' ?>><?= h($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>KM atual</label><input class="input" type="number" name="km_atual" min="0" value="<?= h(isset($veiculo['km_atual']) ? (string) $veiculo['km_atual'] : '') ?>"></div>
        </div>
    </div>

    <div class="card section-card">
        <div class="section-title"><div><h2>Documentação</h2><p>Campos opcionais para identificação do veículo.</p></div></div>
        <div class="form-row">
            <div class="form-group"><label>Chassi</label><input class="input" name="chassi" maxlength="40" value="<?= h($veiculo['chassi'] ?? '') ?>"></div>
            <div class="form-group"><label>RENAVAM</label><input class="input" name="renavam" maxlength="30" value="<?= h($veiculo['renavam'] ?? '') ?>"></div>
            <div class="form-group" style="grid-column:1/-1"><label>Observações</label><textarea class="input" name="observacoes"><?= h($veiculo['observacoes'] ?? '') ?></textarea></div>
            <div class="form-group"><label><input type="checkbox" name="ativo" value="1" <?= !isset($veiculo['ativo']) || (int) $veiculo['ativo'] === 1 ? 'checked' : '' ?>> Veículo ativo</label></div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= $id > 0 ? 'Salvar alterações' : 'Cadastrar veículo' ?></button>
        <a class="btn" href="veiculos.php">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
