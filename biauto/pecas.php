<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'ajustar_estoque') {
        $id = (int) ($_POST['id'] ?? 0);
        $tipo = (string) ($_POST['tipo'] ?? '');
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

            if ($novoEstoque < 0 && !$configPermitirEstoqueNegativo) {
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

$q = trim((string) ($_GET['q'] ?? ''));
$estoque = (string) ($_GET['estoque'] ?? '');
$sql = 'SELECT * FROM pecas WHERE deleted_at IS NULL';
$params = [];

if ($q !== '') {
    $sql .= ' AND (nome LIKE ? OR codigo LIKE ? OR codigo_barras LIKE ? OR marca LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca, $busca];
}

if ($estoque === 'baixo' && $configControlarEstoqueMinimo) {
    $sql .= ' AND estoque_atual <= estoque_minimo';
} elseif ($estoque === 'zerado') {
    $sql .= ' AND estoque_atual <= 0';
}

$sql .= ' ORDER BY nome ASC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pecas = $stmt->fetchAll();

$acoes = [];
if (pode_alterar('pecas')) {
    $acoes[] = ['label' => 'Nova peça', 'href' => 'peca_form.php', 'icon' => 'plus', 'class' => 'btn-primary'];
}

$pageTitle = 'Peças';
$currentPage = 'pecas';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header('Peças', 'Controle de estoque, custo e valor de venda.', $acoes) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar produto, código ou marca"></div>
        <select class="select" name="estoque">
            <option value="">Todo o estoque</option>
            <?php if ($configControlarEstoqueMinimo): ?><option value="baixo" <?= $estoque === 'baixo' ? 'selected' : '' ?>>Estoque baixo</option><?php endif; ?>
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
                <?php
                $negativo = (float) $peca['estoque_atual'] < 0;
                $baixo = $configControlarEstoqueMinimo && (float) $peca['estoque_atual'] <= (float) $peca['estoque_minimo'];
                $statusClasse = ($negativo || $baixo) ? 'danger' : 'success';
                $statusTexto = $negativo ? 'Estoque negativo' : ($baixo ? 'Estoque baixo' : 'Normal');
                ?>
                <tr>
                    <td><strong><?= h($peca['nome']) ?></strong><?= $peca['marca'] ? '<div class="muted">' . h($peca['marca']) . '</div>' : '' ?></td>
                    <td><?= h($peca['codigo'] ?: '-') ?></td>
                    <td class="money"><?= number_format((float) $peca['estoque_atual'], 3, ',', '.') ?> <?= h($peca['unidade']) ?></td>
                    <td><?= number_format((float) $peca['estoque_minimo'], 3, ',', '.') ?></td>
                    <td><?= money_br($peca['custo_medio']) ?></td>
                    <td class="money"><?= money_br($peca['preco_venda']) ?></td>
                    <td><span class="badge <?= $statusClasse ?>"><?= h($statusTexto) ?></span></td>
                    <td>
                        <div class="actions">
                            <?php if (pode_alterar('pecas')): ?>
                                <a class="btn" href="peca_form.php?id=<?= (int) $peca['id'] ?>">Editar</a>
                                <button class="btn" type="button" data-stock-open data-id="<?= (int) $peca['id'] ?>" data-name="<?= h($peca['nome']) ?>" data-stock="<?= h(number_format((float) $peca['estoque_atual'], 3, ',', '.')) ?> <?= h($peca['unidade']) ?>">Estoque</button>
                                <form method="post" onsubmit="return confirm('Remover esta peça?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= (int) $peca['id'] ?>">
                                    <button class="btn btn-danger" type="submit">Excluir</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">Somente consulta</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mobile-cards">
        <?php foreach ($pecas as $peca): ?>
            <?php
            $negativo = (float) $peca['estoque_atual'] < 0;
            $baixo = $configControlarEstoqueMinimo && (float) $peca['estoque_atual'] <= (float) $peca['estoque_minimo'];
            $statusClasse = ($negativo || $baixo) ? 'danger' : 'success';
            $statusTexto = $negativo ? 'Negativo' : ($baixo ? 'Baixo' : 'Normal');
            ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($peca['nome']) ?></strong><span class="badge <?= $statusClasse ?>"><?= h($statusTexto) ?></span></div>
                <p><?= h($peca['codigo'] ?: 'Sem código') ?></p>
                <p>Estoque: <?= number_format((float) $peca['estoque_atual'], 3, ',', '.') ?> <?= h($peca['unidade']) ?></p>
                <div class="mobile-card-bottom">
                    <span class="money"><?= money_br($peca['preco_venda']) ?></span>
                    <?php if (pode_alterar('pecas')): ?>
                        <div class="actions">
                            <a class="btn" href="peca_form.php?id=<?= (int) $peca['id'] ?>">Editar</a>
                            <button class="btn" type="button" data-stock-open data-id="<?= (int) $peca['id'] ?>" data-name="<?= h($peca['nome']) ?>" data-stock="<?= h(number_format((float) $peca['estoque_atual'], 3, ',', '.')) ?> <?= h($peca['unidade']) ?>">Estoque</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (pode_alterar('pecas')): ?>
<dialog class="app-modal" id="stockModal">
    <form method="post" class="modal-card">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="ajustar_estoque">
        <input type="hidden" name="id" id="stockPieceId">
        <div class="modal-head">
            <div>
                <h2>Ajustar estoque</h2>
                <p id="stockPieceName">Peça</p>
            </div>
            <button class="icon-btn ghost-btn" type="button" data-modal-close aria-label="Fechar">×</button>
        </div>
        <div class="modal-current">Estoque atual: <strong id="stockCurrent">-</strong></div>
        <div class="form-group">
            <label>Tipo de movimentação</label>
            <select class="select" name="tipo" required>
                <option value="entrada">Entrada</option>
                <option value="saida">Saída</option>
                <option value="ajuste_positivo">Ajuste positivo</option>
                <option value="ajuste_negativo">Ajuste negativo</option>
                <option value="devolucao">Devolução</option>
            </select>
        </div>
        <div class="form-group"><label>Quantidade</label><input class="input" name="quantidade" inputmode="decimal" required></div>
        <div class="form-group"><label>Observação</label><input class="input" name="observacao" maxlength="500" placeholder="Opcional"></div>
        <?php if ($configPermitirEstoqueNegativo): ?><div class="badge warning">Estoque negativo permitido nas configurações.</div><?php endif; ?>
        <div class="modal-actions">
            <button class="btn" type="button" data-modal-close>Cancelar</button>
            <button class="btn btn-primary" type="submit">Confirmar ajuste</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
