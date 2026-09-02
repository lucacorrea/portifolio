<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = isset($_POST['acao']) ? (string) $_POST['acao'] : '';

    if ($acao === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $codigo = strtoupper(trim((string) ($_POST['codigo'] ?? '')));
        $precoVenda = decimal_value($_POST['preco_venda'] ?? 0);
        $custoMedio = decimal_value($_POST['custo_medio'] ?? 0);
        $estoqueMinimo = max(0, (float) str_replace(',', '.', (string) ($_POST['estoque_minimo'] ?? 0)));

        if ($nome === '') {
            flash('danger', 'Informe o nome da peça.');
            redirect('pecas.php' . ($id > 0 ? '?editar=' . $id : ''));
        }

        if ($precoVenda < 0 || $custoMedio < 0) {
            flash('danger', 'Os valores de custo e venda não podem ser negativos.');
            redirect('pecas.php' . ($id > 0 ? '?editar=' . $id : ''));
        }

        $dados = [
            'codigo' => $codigo === '' ? null : $codigo,
            'codigo_barras' => nullable_string($_POST['codigo_barras'] ?? null),
            'nome' => $nome,
            'marca' => nullable_string($_POST['marca'] ?? null),
            'unidade' => strtoupper(trim((string) ($_POST['unidade'] ?? 'UN'))) ?: 'UN',
            'estoque_minimo' => $estoqueMinimo,
            'custo_medio' => $custoMedio,
            'preco_venda' => $precoVenda,
            'localizacao' => nullable_string($_POST['localizacao'] ?? null),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
        ];

        try {
            if ($id > 0) {
                $antesStmt = $pdo->prepare('SELECT * FROM pecas WHERE id = ? AND deleted_at IS NULL');
                $antesStmt->execute([$id]);
                $antes = $antesStmt->fetch();

                if (!$antes) {
                    flash('danger', 'Peça não encontrada.');
                    redirect('pecas.php');
                }

                $stmt = $pdo->prepare('UPDATE pecas SET codigo = ?, codigo_barras = ?, nome = ?, marca = ?, unidade = ?, estoque_minimo = ?, custo_medio = ?, preco_venda = ?, localizacao = ?, ativo = ? WHERE id = ? AND deleted_at IS NULL');
                $stmt->execute([$dados['codigo'], $dados['codigo_barras'], $dados['nome'], $dados['marca'], $dados['unidade'], $dados['estoque_minimo'], $dados['custo_medio'], $dados['preco_venda'], $dados['localizacao'], $dados['ativo'], $id]);
                audit($pdo, 'pecas', $id, 'atualizar', $antes, $dados);
                flash('success', 'Peça atualizada com sucesso.');
            } else {
                $estoqueInicial = max(0, (float) str_replace(',', '.', (string) ($_POST['estoque_inicial'] ?? 0)));
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO pecas (codigo, codigo_barras, nome, marca, unidade, estoque_atual, estoque_minimo, custo_medio, preco_venda, localizacao, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$dados['codigo'], $dados['codigo_barras'], $dados['nome'], $dados['marca'], $dados['unidade'], $estoqueInicial, $dados['estoque_minimo'], $dados['custo_medio'], $dados['preco_venda'], $dados['localizacao'], $dados['ativo']]);
                $id = (int) $pdo->lastInsertId();

                if ($estoqueInicial > 0) {
                    $mov = $pdo->prepare('INSERT INTO estoque_movimentos (peca_id, tipo, quantidade, custo_unitario, observacao) VALUES (?, ?, ?, ?, ?)');
                    $mov->execute([$id, 'entrada', $estoqueInicial, $dados['custo_medio'], 'Estoque inicial']);
                }

                $pdo->commit();
                audit($pdo, 'pecas', $id, 'criar', null, $dados);
                flash('success', 'Peça cadastrada com sucesso.');
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ((string) $e->getCode() === '23000') {
                flash('danger', 'Código ou código de barras já cadastrado.');
            } else {
                flash('danger', 'Não foi possível salvar a peça.');
            }
        }

        redirect('pecas.php');
    }

    if ($acao === 'ajustar_estoque') {
        $id = (int) ($_POST['id'] ?? 0);
        $tipo = isset($_POST['tipo']) ? (string) $_POST['tipo'] : '';
        $quantidade = (float) str_replace(',', '.', (string) ($_POST['quantidade'] ?? 0));
        $observacao = nullable_string($_POST['observacao'] ?? null);
        $tipos = ['entrada', 'saida', 'ajuste_positivo', 'ajuste_negativo', 'devolucao'];

        if ($id <= 0 || !in_array($tipo, $tipos, true) || $quantidade <= 0) {
            flash('danger', 'Informe um ajuste de estoque válido.');
            redirect('pecas.php');
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM pecas WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute([$id]);
            $peca = $stmt->fetch();

            if (!$peca) {
                throw new RuntimeException('Peça não encontrada.');
            }

            $entrada = in_array($tipo, ['entrada', 'ajuste_positivo', 'devolucao'], true);
            $novoEstoque = (float) $peca['estoque_atual'] + ($entrada ? $quantidade : -$quantidade);

            if ($novoEstoque < 0) {
                throw new RuntimeException('Estoque insuficiente para esta saída.');
            }

            $pdo->prepare('UPDATE pecas SET estoque_atual = ? WHERE id = ?')->execute([$novoEstoque, $id]);
            $mov = $pdo->prepare('INSERT INTO estoque_movimentos (peca_id, tipo, quantidade, custo_unitario, observacao) VALUES (?, ?, ?, ?, ?)');
            $mov->execute([$id, $tipo, $quantidade, $peca['custo_medio'], $observacao]);
            $pdo->commit();
            audit($pdo, 'pecas', $id, 'ajustar_estoque', ['estoque_atual' => $peca['estoque_atual']], ['estoque_atual' => $novoEstoque, 'tipo' => $tipo, 'quantidade' => $quantidade]);
            flash('success', 'Estoque atualizado com sucesso.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', $e->getMessage() ?: 'Não foi possível ajustar o estoque.');
        }

        redirect('pecas.php');
    }

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM pecas WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $antes = $stmt->fetch();

        if ($antes) {
            $pdo->prepare('UPDATE pecas SET ativo = 0, deleted_at = NOW() WHERE id = ?')->execute([$id]);
            audit($pdo, 'pecas', $id, 'excluir', $antes, null);
            flash('success', 'Peça removida com sucesso.');
        }

        redirect('pecas.php');
    }
}

$editar = null;
$editarId = (int) ($_GET['editar'] ?? 0);
if ($editarId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM pecas WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$editarId]);
    $editar = $stmt->fetch() ?: null;
}

$q = trim((string) ($_GET['q'] ?? ''));
$estoque = isset($_GET['estoque']) ? (string) $_GET['estoque'] : '';
$sql = 'SELECT * FROM pecas WHERE deleted_at IS NULL';
$params = [];

if ($q !== '') {
    $sql .= ' AND (nome LIKE ? OR codigo LIKE ? OR codigo_barras LIKE ? OR marca LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca, $busca];
}

if ($estoque === 'baixo') {
    $sql .= ' AND estoque_atual <= estoque_minimo';
} elseif ($estoque === 'zerado') {
    $sql .= ' AND estoque_atual <= 0';
}

$sql .= ' ORDER BY nome ASC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pecas = $stmt->fetchAll();

$pageTitle = 'Peças';
$currentPage = 'pecas';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header('Peças', 'Controle de estoque, custo e valor de venda.', [
    ['label' => 'Nova peça', 'href' => 'pecas.php#form-peca', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="card section-card" id="form-peca">
    <div class="section-title">
        <div>
            <h2><?= $editar ? 'Editar peça' : 'Nova peça' ?></h2>
            <p>Cadastre o item e os parâmetros de estoque.</p>
        </div>
        <?php if ($editar): ?><a class="btn" href="pecas.php">Cancelar edição</a><?php endif; ?>
    </div>

    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">

        <div class="form-row">
            <div class="form-group">
                <label>Nome</label>
                <input class="input" name="nome" maxlength="190" required value="<?= h($editar['nome'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Código</label>
                <input class="input" name="codigo" maxlength="80" value="<?= h($editar['codigo'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Código de barras</label>
                <input class="input" name="codigo_barras" maxlength="80" value="<?= h($editar['codigo_barras'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Marca</label>
                <input class="input" name="marca" maxlength="100" value="<?= h($editar['marca'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Unidade</label>
                <input class="input" name="unidade" maxlength="20" value="<?= h($editar['unidade'] ?? 'UN') ?>">
            </div>
            <?php if (!$editar): ?>
                <div class="form-group">
                    <label>Estoque inicial</label>
                    <input class="input" name="estoque_inicial" inputmode="decimal" value="0">
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label>Estoque mínimo</label>
                <input class="input" name="estoque_minimo" inputmode="decimal" value="<?= h(isset($editar['estoque_minimo']) ? (string) $editar['estoque_minimo'] : '0') ?>">
            </div>
            <div class="form-group">
                <label>Custo médio</label>
                <input class="input" name="custo_medio" inputmode="decimal" value="<?= h(isset($editar['custo_medio']) ? number_format((float) $editar['custo_medio'], 2, ',', '.') : '0,00') ?>">
            </div>
            <div class="form-group">
                <label>Preço de venda</label>
                <input class="input" name="preco_venda" inputmode="decimal" required value="<?= h(isset($editar['preco_venda']) ? number_format((float) $editar['preco_venda'], 2, ',', '.') : '0,00') ?>">
            </div>
            <div class="form-group">
                <label>Localização</label>
                <input class="input" name="localizacao" maxlength="120" value="<?= h($editar['localizacao'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="ativo" value="1" <?= !isset($editar['ativo']) || (int) $editar['ativo'] === 1 ? 'checked' : '' ?>> Peça ativa</label>
            </div>
        </div>

        <div class="actions" style="margin-top:16px">
            <button class="btn btn-primary" type="submit"><?= $editar ? 'Salvar alterações' : 'Cadastrar peça' ?></button>
        </div>
    </form>
</div>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar produto, código ou marca"></div>
        <select class="select" name="estoque">
            <option value="">Todo o estoque</option>
            <option value="baixo" <?= $estoque === 'baixo' ? 'selected' : '' ?>>Estoque baixo</option>
            <option value="zerado" <?= $estoque === 'zerado' ? 'selected' : '' ?>>Sem estoque</option>
        </select>
        <button class="btn" type="submit">Pesquisar</button>
        <?php if ($q !== '' || $estoque !== ''): ?><a class="btn" href="pecas.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Produto</th><th>Código</th><th>Estoque</th><th>Mínimo</th><th>Custo</th><th>Venda</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (!$pecas): ?><tr><td colspan="8" class="muted">Nenhuma peça encontrada.</td></tr><?php endif; ?>
            <?php foreach ($pecas as $peca): ?>
                <?php $baixo = (float) $peca['estoque_atual'] <= (float) $peca['estoque_minimo']; ?>
                <tr>
                    <td><strong><?= h($peca['nome']) ?></strong><?= $peca['marca'] ? '<div class="muted">' . h($peca['marca']) . '</div>' : '' ?></td>
                    <td><?= h($peca['codigo'] ?: '-') ?></td>
                    <td class="money"><?= number_format((float) $peca['estoque_atual'], 3, ',', '.') ?> <?= h($peca['unidade']) ?></td>
                    <td><?= number_format((float) $peca['estoque_minimo'], 3, ',', '.') ?></td>
                    <td><?= money_br($peca['custo_medio']) ?></td>
                    <td class="money"><?= money_br($peca['preco_venda']) ?></td>
                    <td><span class="badge <?= $baixo ? 'danger' : 'success' ?>"><?= $baixo ? 'Estoque baixo' : 'Normal' ?></span></td>
                    <td>
                        <div class="actions">
                            <a class="btn" href="pecas.php?editar=<?= (int) $peca['id'] ?>#form-peca">Editar</a>
                            <details>
                                <summary class="btn">Estoque</summary>
                                <form method="post" style="margin-top:10px;display:grid;gap:8px;min-width:230px">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="ajustar_estoque">
                                    <input type="hidden" name="id" value="<?= (int) $peca['id'] ?>">
                                    <select class="select" name="tipo" required>
                                        <option value="entrada">Entrada</option>
                                        <option value="saida">Saída</option>
                                        <option value="ajuste_positivo">Ajuste positivo</option>
                                        <option value="ajuste_negativo">Ajuste negativo</option>
                                        <option value="devolucao">Devolução</option>
                                    </select>
                                    <input class="input" name="quantidade" inputmode="decimal" placeholder="Quantidade" required>
                                    <input class="input" name="observacao" maxlength="500" placeholder="Observação">
                                    <button class="btn btn-primary" type="submit">Confirmar</button>
                                </form>
                            </details>
                            <form method="post" onsubmit="return confirm('Remover esta peça?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $peca['id'] ?>">
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
        <?php foreach ($pecas as $peca): ?>
            <?php $baixo = (float) $peca['estoque_atual'] <= (float) $peca['estoque_minimo']; ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($peca['nome']) ?></strong><span class="badge <?= $baixo ? 'danger' : 'success' ?>"><?= $baixo ? 'Baixo' : 'Normal' ?></span></div>
                <p><?= h($peca['codigo'] ?: 'Sem código') ?></p>
                <p>Estoque: <?= number_format((float) $peca['estoque_atual'], 3, ',', '.') ?> <?= h($peca['unidade']) ?></p>
                <div class="mobile-card-bottom"><span class="money"><?= money_br($peca['preco_venda']) ?></span><a class="btn" href="pecas.php?editar=<?= (int) $peca['id'] ?>#form-peca">Editar</a></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
