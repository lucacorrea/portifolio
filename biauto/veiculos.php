<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = isset($_POST['acao']) ? (string) $_POST['acao'] : '';

    if ($acao === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $clienteId = (int) ($_POST['cliente_id'] ?? 0);
        $placa = normalize_plate($_POST['placa'] ?? '');
        $marca = trim((string) ($_POST['marca'] ?? ''));
        $modelo = trim((string) ($_POST['modelo'] ?? ''));

        if ($clienteId <= 0 || $placa === '' || $marca === '' || $modelo === '') {
            flash('danger', 'Informe cliente, placa, marca e modelo.');
            redirect('veiculos.php' . ($id > 0 ? '?editar=' . $id : ''));
        }

        $clienteStmt = $pdo->prepare('SELECT id FROM clientes WHERE id = ? AND deleted_at IS NULL');
        $clienteStmt->execute([$clienteId]);
        if (!$clienteStmt->fetch()) {
            flash('danger', 'Cliente inválido.');
            redirect('veiculos.php');
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
                $antesStmt = $pdo->prepare('SELECT * FROM veiculos WHERE id = ? AND deleted_at IS NULL');
                $antesStmt->execute([$id]);
                $antes = $antesStmt->fetch();

                if (!$antes) {
                    flash('danger', 'Veículo não encontrado.');
                    redirect('veiculos.php');
                }

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
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                flash('danger', 'Placa, chassi ou RENAVAM já cadastrado.');
            } else {
                flash('danger', 'Não foi possível salvar o veículo.');
            }
        }

        redirect('veiculos.php');
    }

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

$clientesStmt = $pdo->query('SELECT id, nome_razao FROM clientes WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome_razao');
$clientesLista = $clientesStmt->fetchAll();

$editar = null;
$editarId = (int) ($_GET['editar'] ?? 0);
if ($editarId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM veiculos WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$editarId]);
    $editar = $stmt->fetch() ?: null;
}

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

$pageTitle = 'Veículos';
$currentPage = 'veiculos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header('Veículos', 'Veículos vinculados aos clientes e seu histórico de manutenção.', [
    ['label' => 'Novo veículo', 'href' => 'veiculos.php#form-veiculo', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="card section-card" id="form-veiculo">
    <div class="section-title">
        <div>
            <h2><?= $editar ? 'Editar veículo' : 'Novo veículo' ?></h2>
            <p>Cadastre os dados do veículo e vincule ao proprietário.</p>
        </div>
        <?php if ($editar): ?><a class="btn" href="veiculos.php">Cancelar edição</a><?php endif; ?>
    </div>

    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">

        <div class="form-row">
            <div class="form-group">
                <label>Cliente</label>
                <select class="select" name="cliente_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($clientesLista as $cliente): ?>
                        <option value="<?= (int) $cliente['id'] ?>" <?= (int) ($editar['cliente_id'] ?? $clienteFiltro) === (int) $cliente['id'] ? 'selected' : '' ?>><?= h($cliente['nome_razao']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Placa</label>
                <input class="input" name="placa" maxlength="10" required value="<?= h($editar['placa'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Marca</label>
                <input class="input" name="marca" maxlength="100" required value="<?= h($editar['marca'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Modelo</label>
                <input class="input" name="modelo" maxlength="120" required value="<?= h($editar['modelo'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Versão</label>
                <input class="input" name="versao" maxlength="120" value="<?= h($editar['versao'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Cor</label>
                <input class="input" name="cor" maxlength="60" value="<?= h($editar['cor'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Ano fabricação</label>
                <input class="input" type="number" name="ano_fabricacao" min="1900" max="2100" value="<?= h(isset($editar['ano_fabricacao']) ? (string) $editar['ano_fabricacao'] : '') ?>">
            </div>
            <div class="form-group">
                <label>Ano modelo</label>
                <input class="input" type="number" name="ano_modelo" min="1900" max="2100" value="<?= h(isset($editar['ano_modelo']) ? (string) $editar['ano_modelo'] : '') ?>">
            </div>
            <div class="form-group">
                <label>Combustível</label>
                <select class="select" name="combustivel">
                    <option value="">Não informado</option>
                    <?php foreach (['gasolina' => 'Gasolina', 'etanol' => 'Etanol', 'flex' => 'Flex', 'diesel' => 'Diesel', 'gnv' => 'GNV', 'eletrico' => 'Elétrico', 'hibrido' => 'Híbrido', 'outro' => 'Outro'] as $valor => $rotulo): ?>
                        <option value="<?= h($valor) ?>" <?= ($editar['combustivel'] ?? '') === $valor ? 'selected' : '' ?>><?= h($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>KM atual</label>
                <input class="input" type="number" name="km_atual" min="0" value="<?= h(isset($editar['km_atual']) ? (string) $editar['km_atual'] : '') ?>">
            </div>
            <div class="form-group">
                <label>Chassi</label>
                <input class="input" name="chassi" maxlength="40" value="<?= h($editar['chassi'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>RENAVAM</label>
                <input class="input" name="renavam" maxlength="30" value="<?= h($editar['renavam'] ?? '') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>Observações</label>
                <textarea class="input" name="observacoes"><?= h($editar['observacoes'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="ativo" value="1" <?= !isset($editar['ativo']) || (int) $editar['ativo'] === 1 ? 'checked' : '' ?>> Veículo ativo</label>
            </div>
        </div>

        <div class="actions" style="margin-top:16px">
            <button class="btn btn-primary" type="submit"><?= $editar ? 'Salvar alterações' : 'Cadastrar veículo' ?></button>
        </div>
    </form>
</div>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow">
            <input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar por placa, marca, modelo ou cliente">
        </div>
        <select class="select" name="cliente_id">
            <option value="0">Todos os clientes</option>
            <?php foreach ($clientesLista as $cliente): ?>
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
                            <a class="btn" href="veiculos.php?editar=<?= (int) $veiculo['id'] ?>#form-veiculo">Editar</a>
                            <a class="btn" href="ordens.php?veiculo_id=<?= (int) $veiculo['id'] ?>">Histórico</a>
                            <form method="post" onsubmit="return confirm('Remover este veículo?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $veiculo['id'] ?>">
                                <button class="btn btn-danger" type="submit">Excluir</button>
                            </form>
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
                <div class="mobile-card-bottom"><span><?= date_br($veiculo['ultimo_servico']) ?></span><a class="btn" href="veiculos.php?editar=<?= (int) $veiculo['id'] ?>#form-veiculo">Editar</a></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
