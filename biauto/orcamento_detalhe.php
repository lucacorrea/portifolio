<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

function recalcular_orcamento(PDO $pdo, int $orcamentoId): void
{
    $servicos = (float) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM orcamento_servicos WHERE orcamento_id = ' . $orcamentoId)->fetchColumn();
    $pecas = (float) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM orcamento_pecas WHERE orcamento_id = ' . $orcamentoId)->fetchColumn();
    $stmt = $pdo->prepare('SELECT desconto FROM orcamentos WHERE id = ?');
    $stmt->execute([$orcamentoId]);
    $desconto = (float) ($stmt->fetch()['desconto'] ?? 0);
    $total = max(0, $servicos + $pecas - $desconto);
    $pdo->prepare('UPDATE orcamentos SET subtotal_servicos = ?, subtotal_pecas = ?, total = ? WHERE id = ?')->execute([$servicos, $pecas, $total, $orcamentoId]);
}

$orcamentoId = (int) ($_GET['id'] ?? $_POST['orcamento_id'] ?? 0);
if ($orcamentoId <= 0) {
    flash('danger', 'Orçamento inválido.');
    redirect('orcamentos.php');
}

$statusLabels = [
    'rascunho' => ['Rascunho', 'info'],
    'enviado' => ['Enviado', 'info'],
    'aguardando_aprovacao' => ['Aguardando aprovação', 'warning'],
    'aprovado' => ['Aprovado', 'success'],
    'recusado' => ['Recusado', 'danger'],
    'expirado' => ['Expirado', 'warning'],
    'convertido' => ['Convertido em OS', 'success'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = isset($_POST['acao']) ? (string) $_POST['acao'] : '';

    if ($acao === 'adicionar_servico') {
        $servicoId = (int) ($_POST['servico_id'] ?? 0);
        $quantidade = max(0.001, (float) str_replace(',', '.', (string) ($_POST['quantidade'] ?? 1)));
        $stmt = $pdo->prepare('SELECT * FROM servicos WHERE id = ? AND deleted_at IS NULL AND ativo = 1');
        $stmt->execute([$servicoId]);
        $servico = $stmt->fetch();

        if (!$servico) {
            flash('danger', 'Serviço inválido.');
            redirect('orcamento_detalhe.php?id=' . $orcamentoId);
        }

        $valor = ($_POST['valor_unitario'] ?? '') !== '' ? decimal_value($_POST['valor_unitario']) : (float) $servico['valor_base'];
        $total = round($quantidade * $valor, 2);
        $pdo->prepare('INSERT INTO orcamento_servicos (orcamento_id, servico_id, descricao, quantidade, valor_unitario, total) VALUES (?, ?, ?, ?, ?, ?)')->execute([$orcamentoId, $servicoId, $servico['nome'], $quantidade, $valor, $total]);
        recalcular_orcamento($pdo, $orcamentoId);
        flash('success', 'Serviço adicionado ao orçamento.');
        redirect('orcamento_detalhe.php?id=' . $orcamentoId);
    }

    if ($acao === 'remover_servico') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $pdo->prepare('DELETE FROM orcamento_servicos WHERE id = ? AND orcamento_id = ?')->execute([$itemId, $orcamentoId]);
        recalcular_orcamento($pdo, $orcamentoId);
        flash('success', 'Serviço removido.');
        redirect('orcamento_detalhe.php?id=' . $orcamentoId);
    }

    if ($acao === 'adicionar_peca') {
        $pecaId = (int) ($_POST['peca_id'] ?? 0);
        $quantidade = max(0.001, (float) str_replace(',', '.', (string) ($_POST['quantidade'] ?? 1)));
        $stmt = $pdo->prepare('SELECT * FROM pecas WHERE id = ? AND deleted_at IS NULL AND ativo = 1');
        $stmt->execute([$pecaId]);
        $peca = $stmt->fetch();

        if (!$peca) {
            flash('danger', 'Peça inválida.');
            redirect('orcamento_detalhe.php?id=' . $orcamentoId);
        }

        $valor = ($_POST['valor_unitario'] ?? '') !== '' ? decimal_value($_POST['valor_unitario']) : (float) $peca['preco_venda'];
        $total = round($quantidade * $valor, 2);
        $pdo->prepare('INSERT INTO orcamento_pecas (orcamento_id, peca_id, descricao, quantidade, valor_unitario, total) VALUES (?, ?, ?, ?, ?, ?)')->execute([$orcamentoId, $pecaId, $peca['nome'], $quantidade, $valor, $total]);
        recalcular_orcamento($pdo, $orcamentoId);
        flash('success', 'Peça adicionada ao orçamento.');
        redirect('orcamento_detalhe.php?id=' . $orcamentoId);
    }

    if ($acao === 'remover_peca') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $pdo->prepare('DELETE FROM orcamento_pecas WHERE id = ? AND orcamento_id = ?')->execute([$itemId, $orcamentoId]);
        recalcular_orcamento($pdo, $orcamentoId);
        flash('success', 'Peça removida.');
        redirect('orcamento_detalhe.php?id=' . $orcamentoId);
    }

    if ($acao === 'atualizar') {
        $validade = trim((string) ($_POST['validade_ate'] ?? ''));
        $desconto = max(0, decimal_value($_POST['desconto'] ?? 0));
        $observacoes = nullable_string($_POST['observacoes'] ?? null);
        $pdo->prepare('UPDATE orcamentos SET validade_ate = ?, desconto = ?, observacoes = ? WHERE id = ?')->execute([$validade !== '' ? $validade : null, $desconto, $observacoes, $orcamentoId]);
        recalcular_orcamento($pdo, $orcamentoId);
        flash('success', 'Orçamento atualizado.');
        redirect('orcamento_detalhe.php?id=' . $orcamentoId);
    }

    if ($acao === 'alterar_status') {
        $novoStatus = (string) ($_POST['status'] ?? '');

        if (!isset($statusLabels[$novoStatus]) || $novoStatus === 'convertido') {
            flash('danger', 'Status inválido.');
            redirect('orcamento_detalhe.php?id=' . $orcamentoId);
        }

        $aprovadoEm = $novoStatus === 'aprovado' ? date('Y-m-d H:i:s') : null;
        $recusadoEm = $novoStatus === 'recusado' ? date('Y-m-d H:i:s') : null;
        $stmt = $pdo->prepare('UPDATE orcamentos SET status = ?, aprovado_em = CASE WHEN ? IS NOT NULL THEN ? ELSE aprovado_em END, recusado_em = CASE WHEN ? IS NOT NULL THEN ? ELSE recusado_em END WHERE id = ?');
        $stmt->execute([$novoStatus, $aprovadoEm, $aprovadoEm, $recusadoEm, $recusadoEm, $orcamentoId]);
        audit($pdo, 'orcamentos', $orcamentoId, 'alterar_status', null, ['status' => $novoStatus]);
        flash('success', 'Status do orçamento atualizado.');
        redirect('orcamento_detalhe.php?id=' . $orcamentoId);
    }

    if ($acao === 'converter') {
        $stmt = $pdo->prepare('SELECT * FROM orcamentos WHERE id = ? FOR UPDATE');

        try {
            $pdo->beginTransaction();
            $stmt->execute([$orcamentoId]);
            $orc = $stmt->fetch();

            if (!$orc || $orc['status'] !== 'aprovado') {
                throw new RuntimeException('Apenas orçamentos aprovados podem ser convertidos.');
            }

            $existente = $pdo->prepare('SELECT id FROM ordens_servico WHERE orcamento_id = ?');
            $existente->execute([$orcamentoId]);
            if ($existente->fetch()) {
                throw new RuntimeException('Este orçamento já foi convertido em ordem de serviço.');
            }

            $numeroOs = next_number($pdo, 'ordens_servico', 'OS');
            $novaOs = $pdo->prepare('INSERT INTO ordens_servico (numero, cliente_id, veiculo_id, orcamento_id, status, data_entrada, subtotal_servicos, subtotal_pecas, desconto, total, observacoes) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)');
            $novaOs->execute([$numeroOs, $orc['cliente_id'], $orc['veiculo_id'], $orcamentoId, 'aberta', $orc['subtotal_servicos'], $orc['subtotal_pecas'], $orc['desconto'], $orc['total'], 'Originada do orçamento ' . $orc['numero']]);
            $ordemId = (int) $pdo->lastInsertId();

            $servicos = $pdo->prepare('SELECT * FROM orcamento_servicos WHERE orcamento_id = ?');
            $servicos->execute([$orcamentoId]);
            $insertServico = $pdo->prepare('INSERT INTO ordem_servico_servicos (ordem_servico_id, servico_id, descricao, quantidade, valor_unitario, total, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($servicos->fetchAll() as $item) {
                $insertServico->execute([$ordemId, $item['servico_id'], $item['descricao'], $item['quantidade'], $item['valor_unitario'], $item['total'], 'pendente']);
            }

            $pecas = $pdo->prepare('SELECT * FROM orcamento_pecas WHERE orcamento_id = ?');
            $pecas->execute([$orcamentoId]);
            $insertPeca = $pdo->prepare('INSERT INTO ordem_servico_pecas (ordem_servico_id, peca_id, descricao, quantidade, custo_unitario, valor_unitario, total, baixada_estoque) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
            foreach ($pecas->fetchAll() as $item) {
                $pecaStmt = $pdo->prepare('SELECT estoque_atual, custo_medio FROM pecas WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
                $pecaStmt->execute([$item['peca_id']]);
                $peca = $pecaStmt->fetch();

                if (!$peca || (float) $peca['estoque_atual'] < (float) $item['quantidade']) {
                    throw new RuntimeException('Estoque insuficiente para ' . $item['descricao'] . '.');
                }

                $insertPeca->execute([$ordemId, $item['peca_id'], $item['descricao'], $item['quantidade'], $peca['custo_medio'], $item['valor_unitario'], $item['total']]);
                $pdo->prepare('UPDATE pecas SET estoque_atual = estoque_atual - ? WHERE id = ?')->execute([$item['quantidade'], $item['peca_id']]);
                $pdo->prepare('INSERT INTO estoque_movimentos (peca_id, tipo, quantidade, custo_unitario, origem_tipo, origem_id, observacao) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$item['peca_id'], 'saida', $item['quantidade'], $peca['custo_medio'], 'ordem_servico', $ordemId, 'Conversão do orçamento ' . $orc['numero']]);
            }

            $pdo->prepare('UPDATE orcamentos SET status = ? WHERE id = ?')->execute(['convertido', $orcamentoId]);
            $pdo->prepare('INSERT INTO ordem_servico_historico (ordem_servico_id, status_novo, acao, observacao) VALUES (?, ?, ?, ?)')->execute([$ordemId, 'aberta', 'Ordem criada por orçamento', $orc['numero']]);
            $pdo->commit();
            audit($pdo, 'orcamentos', $orcamentoId, 'converter', null, ['ordem_servico_id' => $ordemId]);
            flash('success', 'Orçamento convertido em ordem de serviço.');
            redirect('ordem_detalhe.php?id=' . $ordemId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', $e->getMessage() ?: 'Não foi possível converter o orçamento.');
            redirect('orcamento_detalhe.php?id=' . $orcamentoId);
        }
    }
}

recalcular_orcamento($pdo, $orcamentoId);
$stmt = $pdo->prepare("SELECT o.*, c.nome_razao AS cliente, c.telefone, c.whatsapp, CONCAT(v.marca, ' ', v.modelo) AS veiculo, v.placa FROM orcamentos o INNER JOIN clientes c ON c.id = o.cliente_id INNER JOIN veiculos v ON v.id = o.veiculo_id WHERE o.id = ?");
$stmt->execute([$orcamentoId]);
$orcamento = $stmt->fetch();

if (!$orcamento) {
    flash('danger', 'Orçamento não encontrado.');
    redirect('orcamentos.php');
}

$servicosStmt = $pdo->prepare('SELECT * FROM orcamento_servicos WHERE orcamento_id = ? ORDER BY id');
$servicosStmt->execute([$orcamentoId]);
$servicosItens = $servicosStmt->fetchAll();
$pecasStmt = $pdo->prepare('SELECT * FROM orcamento_pecas WHERE orcamento_id = ? ORDER BY id');
$pecasStmt->execute([$orcamentoId]);
$pecasItens = $pecasStmt->fetchAll();
$catalogoServicos = $pdo->query('SELECT id, nome, valor_base FROM servicos WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome')->fetchAll();
$catalogoPecas = $pdo->query('SELECT id, nome, codigo, estoque_atual, unidade, preco_venda FROM pecas WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome')->fetchAll();
$statusInfo = $statusLabels[$orcamento['status']] ?? [ucfirst(str_replace('_', ' ', $orcamento['status'])), 'info'];

$pageTitle = $orcamento['numero'];
$currentPage = 'orcamentos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header($orcamento['numero'], $orcamento['cliente'] . ' • ' . $orcamento['veiculo'] . ' • ' . $orcamento['placa'], [
    ['label' => 'Voltar', 'href' => 'orcamentos.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<div class="card section-card">
    <div class="section-title"><div><h2>Situação do orçamento</h2><p>Controle o envio e a aprovação do cliente.</p></div><span class="badge <?= h($statusInfo[1]) ?>"><span class="dot"></span><?= h($statusInfo[0]) ?></span></div>
    <?php if ($orcamento['status'] !== 'convertido'): ?>
        <form method="post" class="filters">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="alterar_status">
            <input type="hidden" name="orcamento_id" value="<?= $orcamentoId ?>">
            <select class="select" name="status">
                <?php foreach ($statusLabels as $valor => $dados): ?>
                    <?php if ($valor === 'convertido') continue; ?>
                    <option value="<?= h($valor) ?>" <?= $orcamento['status'] === $valor ? 'selected' : '' ?>><?= h($dados[0]) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Atualizar status</button>
            <button class="btn" type="button" onclick="window.print()">Imprimir</button>
        </form>
    <?php endif; ?>
    <?php if ($orcamento['status'] === 'aprovado'): ?>
        <form method="post" onsubmit="return confirm('Converter este orçamento em ordem de serviço e baixar as peças do estoque?')">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="converter">
            <input type="hidden" name="orcamento_id" value="<?= $orcamentoId ?>">
            <button class="btn btn-primary" type="submit">Converter em OS</button>
        </form>
    <?php endif; ?>
</div>

<div class="two-col">
    <div>
        <div class="card section-card">
            <div class="section-title"><div><h2>Serviços</h2><p>Serviços previstos no orçamento.</p></div></div>
            <?php if ($orcamento['status'] !== 'convertido'): ?>
                <form method="post" class="filters">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="adicionar_servico">
                    <input type="hidden" name="orcamento_id" value="<?= $orcamentoId ?>">
                    <select class="select" name="servico_id" required>
                        <option value="">Selecione o serviço</option>
                        <?php foreach ($catalogoServicos as $servico): ?><option value="<?= (int) $servico['id'] ?>"><?= h($servico['nome']) ?> • <?= money_br($servico['valor_base']) ?></option><?php endforeach; ?>
                    </select>
                    <input class="input" name="quantidade" value="1" inputmode="decimal" style="max-width:110px">
                    <input class="input" name="valor_unitario" inputmode="decimal" placeholder="Valor padrão" style="max-width:150px">
                    <button class="btn btn-primary" type="submit">Adicionar</button>
                </form>
            <?php endif; ?>
            <div class="table-shell">
                <table class="table">
                    <thead><tr><th>Serviço</th><th>Qtd.</th><th>Unitário</th><th>Total</th><th>Ações</th></tr></thead>
                    <tbody>
                    <?php if (!$servicosItens): ?><tr><td colspan="5" class="muted">Nenhum serviço adicionado.</td></tr><?php endif; ?>
                    <?php foreach ($servicosItens as $item): ?>
                        <tr><td><?= h($item['descricao']) ?></td><td><?= number_format((float) $item['quantidade'], 3, ',', '.') ?></td><td><?= money_br($item['valor_unitario']) ?></td><td class="money"><?= money_br($item['total']) ?></td><td><?php if ($orcamento['status'] !== 'convertido'): ?><form method="post" onsubmit="return confirm('Remover este serviço?')"><?= csrf_field() ?><input type="hidden" name="acao" value="remover_servico"><input type="hidden" name="orcamento_id" value="<?= $orcamentoId ?>"><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-danger" type="submit">Remover</button></form><?php endif; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><div><h2>Peças</h2><p>As peças só serão baixadas do estoque na conversão em OS.</p></div></div>
            <?php if ($orcamento['status'] !== 'convertido'): ?>
                <form method="post" class="filters">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="adicionar_peca">
                    <input type="hidden" name="orcamento_id" value="<?= $orcamentoId ?>">
                    <select class="select" name="peca_id" required>
                        <option value="">Selecione a peça</option>
                        <?php foreach ($catalogoPecas as $peca): ?><option value="<?= (int) $peca['id'] ?>"><?= h($peca['nome']) ?><?= $peca['codigo'] ? ' • ' . h($peca['codigo']) : '' ?> • estoque <?= number_format((float) $peca['estoque_atual'], 3, ',', '.') ?> <?= h($peca['unidade']) ?> • <?= money_br($peca['preco_venda']) ?></option><?php endforeach; ?>
                    </select>
                    <input class="input" name="quantidade" value="1" inputmode="decimal" style="max-width:110px">
                    <input class="input" name="valor_unitario" inputmode="decimal" placeholder="Valor padrão" style="max-width:150px">
                    <button class="btn btn-primary" type="submit">Adicionar</button>
                </form>
            <?php endif; ?>
            <div class="table-shell">
                <table class="table">
                    <thead><tr><th>Peça</th><th>Qtd.</th><th>Unitário</th><th>Total</th><th>Ações</th></tr></thead>
                    <tbody>
                    <?php if (!$pecasItens): ?><tr><td colspan="5" class="muted">Nenhuma peça adicionada.</td></tr><?php endif; ?>
                    <?php foreach ($pecasItens as $item): ?>
                        <tr><td><?= h($item['descricao']) ?></td><td><?= number_format((float) $item['quantidade'], 3, ',', '.') ?></td><td><?= money_br($item['valor_unitario']) ?></td><td class="money"><?= money_br($item['total']) ?></td><td><?php if ($orcamento['status'] !== 'convertido'): ?><form method="post" onsubmit="return confirm('Remover esta peça?')"><?= csrf_field() ?><input type="hidden" name="acao" value="remover_peca"><input type="hidden" name="orcamento_id" value="<?= $orcamentoId ?>"><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><button class="btn btn-danger" type="submit">Remover</button></form><?php endif; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="card section-card">
            <div class="section-title"><div><h2>Valores</h2><p>Resumo do orçamento.</p></div></div>
            <div class="summary-box">
                <div class="summary-line"><span>Serviços</span><strong><?= money_br($orcamento['subtotal_servicos']) ?></strong></div>
                <div class="summary-line"><span>Peças</span><strong><?= money_br($orcamento['subtotal_pecas']) ?></strong></div>
                <div class="summary-line"><span>Desconto</span><strong>- <?= money_br($orcamento['desconto']) ?></strong></div>
                <div class="summary-total"><span>Total</span><span><?= money_br($orcamento['total']) ?></span></div>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><div><h2>Dados complementares</h2><p>Validade, desconto e observações.</p></div></div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="atualizar">
                <input type="hidden" name="orcamento_id" value="<?= $orcamentoId ?>">
                <div class="form-row" style="grid-template-columns:1fr">
                    <div class="form-group"><label>Validade</label><input class="input" type="date" name="validade_ate" value="<?= h($orcamento['validade_ate'] ?? '') ?>"></div>
                    <div class="form-group"><label>Desconto</label><input class="input" name="desconto" inputmode="decimal" value="<?= number_format((float) $orcamento['desconto'], 2, ',', '.') ?>"></div>
                    <div class="form-group"><label>Observações</label><textarea class="input" name="observacoes"><?= h($orcamento['observacoes'] ?? '') ?></textarea></div>
                </div>
                <?php if ($orcamento['status'] !== 'convertido'): ?><button class="btn btn-primary" type="submit" style="width:100%;justify-content:center;margin-top:12px">Salvar</button><?php endif; ?>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
