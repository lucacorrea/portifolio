<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

function recalcular_ordem(PDO $pdo, int $ordemId): void
{
    $servicos = (float) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM ordem_servico_servicos WHERE ordem_servico_id = ' . $ordemId . " AND status <> 'cancelado'")->fetchColumn();
    $pecas = (float) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM ordem_servico_pecas WHERE ordem_servico_id = ' . $ordemId)->fetchColumn();
    $stmt = $pdo->prepare('SELECT desconto, acrescimo FROM ordens_servico WHERE id = ?');
    $stmt->execute([$ordemId]);
    $valores = $stmt->fetch();
    $desconto = (float) ($valores['desconto'] ?? 0);
    $acrescimo = (float) ($valores['acrescimo'] ?? 0);
    $total = max(0, $servicos + $pecas - $desconto + $acrescimo);
    $pdo->prepare('UPDATE ordens_servico SET subtotal_servicos = ?, subtotal_pecas = ?, total = ? WHERE id = ?')->execute([$servicos, $pecas, $total, $ordemId]);
}

$ordemId = (int) ($_GET['id'] ?? $_POST['ordem_id'] ?? 0);
if ($ordemId <= 0) {
    flash('danger', 'Ordem de serviço inválida.');
    redirect('ordens.php');
}

$statusLabels = [
    'aberta' => ['Aberta', 'info'],
    'em_diagnostico' => ['Em diagnóstico', 'info'],
    'aguardando_aprovacao' => ['Aguardando aprovação', 'warning'],
    'em_servico' => ['Em serviço', 'info'],
    'aguardando_peca' => ['Aguardando peça', 'warning'],
    'aguardando_retirada' => ['Aguardando retirada', 'warning'],
    'finalizada' => ['Finalizada', 'success'],
    'cancelada' => ['Cancelada', 'danger'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = isset($_POST['acao']) ? (string) $_POST['acao'] : '';

    if ($acao === 'adicionar_servico') {
        $servicoId = (int) ($_POST['servico_id'] ?? 0);
        $mecanicoId = (int) ($_POST['mecanico_id'] ?? 0);
        $quantidade = max(0.001, (float) str_replace(',', '.', (string) ($_POST['quantidade'] ?? 1)));
        $servicoStmt = $pdo->prepare('SELECT * FROM servicos WHERE id = ? AND deleted_at IS NULL AND ativo = 1');
        $servicoStmt->execute([$servicoId]);
        $servico = $servicoStmt->fetch();

        if (!$servico) {
            flash('danger', 'Serviço inválido.');
            redirect('ordem_detalhe.php?id=' . $ordemId);
        }

        $valor = ($_POST['valor_unitario'] ?? '') !== '' ? decimal_value($_POST['valor_unitario']) : (float) $servico['valor_base'];
        $total = round($quantidade * $valor, 2);
        $stmt = $pdo->prepare('INSERT INTO ordem_servico_servicos (ordem_servico_id, servico_id, mecanico_id, descricao, quantidade, valor_unitario, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$ordemId, $servicoId, $mecanicoId > 0 ? $mecanicoId : null, $servico['nome'], $quantidade, $valor, $total, 'pendente']);
        recalcular_ordem($pdo, $ordemId);
        $pdo->prepare('INSERT INTO ordem_servico_historico (ordem_servico_id, acao, observacao) VALUES (?, ?, ?)')->execute([$ordemId, 'Serviço adicionado', $servico['nome']]);
        flash('success', 'Serviço adicionado à ordem.');
        redirect('ordem_detalhe.php?id=' . $ordemId);
    }

    if ($acao === 'remover_servico') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM ordem_servico_servicos WHERE id = ? AND ordem_servico_id = ?');
        $stmt->execute([$itemId, $ordemId]);
        recalcular_ordem($pdo, $ordemId);
        flash('success', 'Serviço removido da ordem.');
        redirect('ordem_detalhe.php?id=' . $ordemId);
    }

    if ($acao === 'atualizar_servico') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $statusServico = (string) ($_POST['status_servico'] ?? 'pendente');
        $permitidos = ['pendente', 'em_execucao', 'concluido', 'cancelado'];

        if (in_array($statusServico, $permitidos, true)) {
            $iniciado = $statusServico === 'em_execucao' ? date('Y-m-d H:i:s') : null;
            $concluido = $statusServico === 'concluido' ? date('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare("UPDATE ordem_servico_servicos SET status = ?, iniciado_em = CASE WHEN ? IS NOT NULL THEN COALESCE(iniciado_em, ?) ELSE iniciado_em END, concluido_em = CASE WHEN ? IS NOT NULL THEN ? ELSE concluido_em END WHERE id = ? AND ordem_servico_id = ?");
            $stmt->execute([$statusServico, $iniciado, $iniciado, $concluido, $concluido, $itemId, $ordemId]);
            recalcular_ordem($pdo, $ordemId);
            flash('success', 'Status do serviço atualizado.');
        }

        redirect('ordem_detalhe.php?id=' . $ordemId);
    }

    if ($acao === 'adicionar_peca') {
        $pecaId = (int) ($_POST['peca_id'] ?? 0);
        $quantidade = max(0.001, (float) str_replace(',', '.', (string) ($_POST['quantidade'] ?? 1)));

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM pecas WHERE id = ? AND deleted_at IS NULL AND ativo = 1 FOR UPDATE');
            $stmt->execute([$pecaId]);
            $peca = $stmt->fetch();

            if (!$peca) {
                throw new RuntimeException('Peça inválida.');
            }

            if (!$configPermitirEstoqueNegativo && (float) $peca['estoque_atual'] < $quantidade) {
                throw new RuntimeException('Estoque insuficiente para adicionar esta peça.');
            }

            $valor = ($_POST['valor_unitario'] ?? '') !== '' ? decimal_value($_POST['valor_unitario']) : (float) $peca['preco_venda'];
            $total = round($quantidade * $valor, 2);
            $item = $pdo->prepare('INSERT INTO ordem_servico_pecas (ordem_servico_id, peca_id, descricao, quantidade, custo_unitario, valor_unitario, total, baixada_estoque) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
            $item->execute([$ordemId, $pecaId, $peca['nome'], $quantidade, $peca['custo_medio'], $valor, $total]);
            $pdo->prepare('UPDATE pecas SET estoque_atual = estoque_atual - ? WHERE id = ?')->execute([$quantidade, $pecaId]);
            $mov = $pdo->prepare('INSERT INTO estoque_movimentos (peca_id, tipo, quantidade, custo_unitario, origem_tipo, origem_id, observacao) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $mov->execute([$pecaId, 'saida', $quantidade, $peca['custo_medio'], 'ordem_servico', $ordemId, 'Uso em ' . $ordemId]);
            $pdo->commit();
            recalcular_ordem($pdo, $ordemId);
            $pdo->prepare('INSERT INTO ordem_servico_historico (ordem_servico_id, acao, observacao) VALUES (?, ?, ?)')->execute([$ordemId, 'Peça adicionada', $peca['nome']]);
            flash('success', 'Peça adicionada e baixada do estoque.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', $e->getMessage() ?: 'Não foi possível adicionar a peça.');
        }

        redirect('ordem_detalhe.php?id=' . $ordemId);
    }

    if ($acao === 'remover_peca') {
        $itemId = (int) ($_POST['item_id'] ?? 0);

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM ordem_servico_pecas WHERE id = ? AND ordem_servico_id = ? FOR UPDATE');
            $stmt->execute([$itemId, $ordemId]);
            $item = $stmt->fetch();

            if ($item) {
                if ((int) $item['baixada_estoque'] === 1 && $item['peca_id']) {
                    $pdo->prepare('UPDATE pecas SET estoque_atual = estoque_atual + ? WHERE id = ?')->execute([$item['quantidade'], $item['peca_id']]);
                    $pdo->prepare('INSERT INTO estoque_movimentos (peca_id, tipo, quantidade, custo_unitario, origem_tipo, origem_id, observacao) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$item['peca_id'], 'devolucao', $item['quantidade'], $item['custo_unitario'], 'ordem_servico', $ordemId, 'Item removido da OS']);
                }
                $pdo->prepare('DELETE FROM ordem_servico_pecas WHERE id = ?')->execute([$itemId]);
            }

            $pdo->commit();
            recalcular_ordem($pdo, $ordemId);
            flash('success', 'Peça removida e estoque devolvido.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', 'Não foi possível remover a peça.');
        }

        redirect('ordem_detalhe.php?id=' . $ordemId);
    }

    if ($acao === 'atualizar_ordem') {
        $mecanicoId = (int) ($_POST['mecanico_responsavel_id'] ?? 0);
        $kmSaida = ($_POST['km_saida'] ?? '') !== '' ? max(0, (int) $_POST['km_saida']) : null;
        $previsao = trim((string) ($_POST['previsao_entrega'] ?? ''));
        $previsaoSql = $previsao !== '' ? date('Y-m-d H:i:s', strtotime($previsao)) : null;
        $desconto = $configPermitirDesconto ? max(0, decimal_value($_POST['desconto'] ?? 0)) : 0;
        $acrescimo = max(0, decimal_value($_POST['acrescimo'] ?? 0));
        $diagnostico = nullable_string($_POST['diagnostico'] ?? null);
        $observacoes = nullable_string($_POST['observacoes'] ?? null);

        if ($configExigirMecanico && $mecanicoId <= 0) {
            flash('danger', 'Selecione o mecânico responsável.');
            redirect('ordem_detalhe.php?id=' . $ordemId);
        }

        $stmt = $pdo->prepare('UPDATE ordens_servico SET mecanico_responsavel_id = ?, km_saida = ?, previsao_entrega = ?, desconto = ?, acrescimo = ?, diagnostico = ?, observacoes = ? WHERE id = ?');
        $stmt->execute([$mecanicoId > 0 ? $mecanicoId : null, $kmSaida, $previsaoSql, $desconto, $acrescimo, $diagnostico, $observacoes, $ordemId]);
        recalcular_ordem($pdo, $ordemId);
        flash('success', 'Dados da ordem atualizados.');
        redirect('ordem_detalhe.php?id=' . $ordemId);
    }

    if ($acao === 'alterar_status') {
        $novoStatus = (string) ($_POST['status'] ?? '');

        if (!isset($statusLabels[$novoStatus])) {
            flash('danger', 'Status inválido.');
            redirect('ordem_detalhe.php?id=' . $ordemId);
        }

        $stmt = $pdo->prepare('SELECT status, veiculo_id, km_saida FROM ordens_servico WHERE id = ?');
        $stmt->execute([$ordemId]);
        $atual = $stmt->fetch();

        if (!$atual) {
            flash('danger', 'Ordem não encontrada.');
            redirect('ordens.php');
        }

        $finalizacao = $novoStatus === 'finalizada' ? date('Y-m-d H:i:s') : null;
        $entrega = $novoStatus === 'finalizada' ? date('Y-m-d H:i:s') : null;
        $sql = 'UPDATE ordens_servico SET status = ?, data_finalizacao = CASE WHEN ? IS NOT NULL THEN COALESCE(data_finalizacao, ?) ELSE data_finalizacao END, data_entrega = CASE WHEN ? IS NOT NULL THEN COALESCE(data_entrega, ?) ELSE data_entrega END WHERE id = ?';
        $pdo->prepare($sql)->execute([$novoStatus, $finalizacao, $finalizacao, $entrega, $entrega, $ordemId]);

        if ($novoStatus === 'finalizada' && $atual['km_saida'] !== null) {
            $pdo->prepare('UPDATE veiculos SET km_atual = GREATEST(COALESCE(km_atual, 0), ?) WHERE id = ?')->execute([(int) $atual['km_saida'], (int) $atual['veiculo_id']]);
        }

        $pdo->prepare('INSERT INTO ordem_servico_historico (ordem_servico_id, status_anterior, status_novo, acao, observacao) VALUES (?, ?, ?, ?, ?)')->execute([$ordemId, $atual['status'], $novoStatus, 'Status alterado', $statusLabels[$novoStatus][0]]);
        audit($pdo, 'ordens_servico', $ordemId, 'alterar_status', ['status' => $atual['status']], ['status' => $novoStatus]);
        flash('success', 'Status da ordem atualizado.');
        redirect('ordem_detalhe.php?id=' . $ordemId);
    }

    if ($acao === 'adicionar_pagamento') {
        $forma = (string) ($_POST['forma'] ?? '');
        $valor = decimal_value($_POST['valor'] ?? 0);
        $parcelas = max(1, (int) ($_POST['parcelas'] ?? 1));
        $formas = ['dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'transferencia', 'boleto', 'outro'];

        if (!in_array($forma, $formas, true) || $valor <= 0) {
            flash('danger', 'Informe uma forma e um valor de pagamento válidos.');
            redirect('ordem_detalhe.php?id=' . $ordemId);
        }

        $stmt = $pdo->prepare('INSERT INTO pagamentos (ordem_servico_id, forma, status, valor, pago_em, parcelas, referencia, observacao) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)');
        $stmt->execute([$ordemId, $forma, 'pago', $valor, $parcelas, nullable_string($_POST['referencia'] ?? null), nullable_string($_POST['observacao_pagamento'] ?? null)]);
        $pdo->prepare('INSERT INTO ordem_servico_historico (ordem_servico_id, acao, observacao) VALUES (?, ?, ?)')->execute([$ordemId, 'Pagamento registrado', money_br($valor)]);
        flash('success', 'Pagamento registrado com sucesso.');
        redirect('ordem_detalhe.php?id=' . $ordemId);
    }

    if ($acao === 'remover_pagamento') {
        $pagamentoId = (int) ($_POST['pagamento_id'] ?? 0);
        $pdo->prepare('DELETE FROM pagamentos WHERE id = ? AND ordem_servico_id = ?')->execute([$pagamentoId, $ordemId]);
        flash('success', 'Pagamento removido.');
        redirect('ordem_detalhe.php?id=' . $ordemId);
    }
}

recalcular_ordem($pdo, $ordemId);

$stmt = $pdo->prepare("SELECT o.*, c.nome_razao AS cliente, c.telefone, c.whatsapp, CONCAT(v.marca, ' ', v.modelo) AS veiculo, v.placa, v.cor, m.nome AS mecanico FROM ordens_servico o INNER JOIN clientes c ON c.id = o.cliente_id INNER JOIN veiculos v ON v.id = o.veiculo_id LEFT JOIN mecanicos m ON m.id = o.mecanico_responsavel_id WHERE o.id = ?");
$stmt->execute([$ordemId]);
$ordem = $stmt->fetch();

if (!$ordem) {
    flash('danger', 'Ordem de serviço não encontrada.');
    redirect('ordens.php');
}

$servicosItensStmt = $pdo->prepare('SELECT s.*, m.nome AS mecanico FROM ordem_servico_servicos s LEFT JOIN mecanicos m ON m.id = s.mecanico_id WHERE s.ordem_servico_id = ? ORDER BY s.id');
$servicosItensStmt->execute([$ordemId]);
$servicosItens = $servicosItensStmt->fetchAll();
$pecasItensStmt = $pdo->prepare('SELECT * FROM ordem_servico_pecas WHERE ordem_servico_id = ? ORDER BY id');
$pecasItensStmt->execute([$ordemId]);
$pecasItens = $pecasItensStmt->fetchAll();
$historicoStmt = $pdo->prepare('SELECT * FROM ordem_servico_historico WHERE ordem_servico_id = ? ORDER BY created_at DESC, id DESC LIMIT 30');
$historicoStmt->execute([$ordemId]);
$historico = $historicoStmt->fetchAll();
$pagamentosStmt = $pdo->prepare('SELECT * FROM pagamentos WHERE ordem_servico_id = ? ORDER BY created_at DESC');
$pagamentosStmt->execute([$ordemId]);
$pagamentos = $pagamentosStmt->fetchAll();
$totalPago = 0.0;
foreach ($pagamentos as $pagamento) {
    if (in_array($pagamento['status'], ['pago', 'parcial'], true)) {
        $totalPago += (float) $pagamento['valor'];
    }
}
$saldo = max(0, (float) $ordem['total'] - $totalPago);
$catalogoServicos = $pdo->query('SELECT id, nome, valor_base FROM servicos WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome')->fetchAll();
$catalogoPecas = $pdo->query('SELECT id, nome, codigo, estoque_atual, unidade, preco_venda FROM pecas WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome')->fetchAll();
$mecanicos = $pdo->query('SELECT id, nome FROM mecanicos WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome')->fetchAll();
$statusInfo = $statusLabels[$ordem['status']] ?? [ucfirst(str_replace('_', ' ', $ordem['status'])), 'info'];

$pageTitle = $ordem['numero'];
$currentPage = 'ordens';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header($ordem['numero'], $ordem['veiculo'] . ' • ' . $ordem['placa'], [
    ['label' => 'Voltar', 'href' => 'ordens.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<div class="card section-card">
    <div class="section-title">
        <div><h2>Situação da ordem</h2><p>Atualize o andamento do atendimento.</p></div>
        <span class="badge <?= h($statusInfo[1]) ?>"><span class="dot"></span><?= h($statusInfo[0]) ?></span>
    </div>
    <form method="post" class="filters">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="alterar_status">
        <input type="hidden" name="ordem_id" value="<?= $ordemId ?>">
        <select class="select" name="status">
            <?php foreach ($statusLabels as $valor => $dados): ?>
                <option value="<?= h($valor) ?>" <?= $ordem['status'] === $valor ? 'selected' : '' ?>><?= h($dados[0]) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit">Atualizar status</button>
        <button class="btn" type="button" onclick="window.print()">Imprimir</button>
    </form>
</div>

<div class="two-col">
    <div>
        <div class="card section-card">
            <div class="section-title"><div><h2>Dados da ordem</h2><p>Informações do cliente, veículo e atendimento.</p></div></div>
            <div class="form-row">
                <div><span class="muted">Cliente</span><div><strong><?= h($ordem['cliente']) ?></strong></div></div>
                <div><span class="muted">Telefone</span><div><strong><?= h($ordem['telefone'] ?: $ordem['whatsapp'] ?: '-') ?></strong></div></div>
                <div><span class="muted">Veículo</span><div><strong><?= h($ordem['veiculo']) ?></strong></div></div>
                <div><span class="muted">Placa</span><div><strong><?= h($ordem['placa']) ?></strong></div></div>
                <div><span class="muted">KM entrada</span><div><strong><?= $ordem['km_entrada'] !== null ? number_format((int) $ordem['km_entrada'], 0, ',', '.') . ' km' : '-' ?></strong></div></div>
                <div><span class="muted">Mecânico</span><div><strong><?= h($ordem['mecanico'] ?: '-') ?></strong></div></div>
                <div><span class="muted">Entrada</span><div><strong><?= datetime_br($ordem['data_entrada']) ?></strong></div></div>
                <div><span class="muted">Previsão</span><div><strong><?= datetime_br($ordem['previsao_entrega']) ?></strong></div></div>
            </div>
            <?php if ($ordem['relato_cliente']): ?><div style="margin-top:16px"><span class="muted">Relato do cliente</span><p><?= nl2br(h($ordem['relato_cliente'])) ?></p></div><?php endif; ?>
        </div>

        <div class="card section-card">
            <div class="section-title"><div><h2>Serviços</h2><p>Serviços executados ou previstos nesta ordem.</p></div></div>
            <form method="post" class="filters">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="adicionar_servico">
                <input type="hidden" name="ordem_id" value="<?= $ordemId ?>">
                <select class="select" name="servico_id" required>
                    <option value="">Selecione o serviço</option>
                    <?php foreach ($catalogoServicos as $servico): ?>
                        <option value="<?= (int) $servico['id'] ?>"><?= h($servico['nome']) ?> • <?= money_br($servico['valor_base']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="select" name="mecanico_id">
                    <option value="0">Mecânico da OS</option>
                    <?php foreach ($mecanicos as $mecanico): ?><option value="<?= (int) $mecanico['id'] ?>"><?= h($mecanico['nome']) ?></option><?php endforeach; ?>
                </select>
                <input class="input" name="quantidade" value="1" inputmode="decimal" style="max-width:110px">
                <input class="input" name="valor_unitario" inputmode="decimal" placeholder="Valor padrão" style="max-width:150px">
                <button class="btn btn-primary" type="submit">Adicionar</button>
            </form>

            <div class="table-shell">
                <table class="table">
                    <thead><tr><th>Serviço</th><th>Mecânico</th><th>Qtd.</th><th>Unitário</th><th>Total</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                    <?php if (!$servicosItens): ?><tr><td colspan="7" class="muted">Nenhum serviço adicionado.</td></tr><?php endif; ?>
                    <?php foreach ($servicosItens as $item): ?>
                        <tr>
                            <td><?= h($item['descricao']) ?></td>
                            <td><?= h($item['mecanico'] ?: '-') ?></td>
                            <td><?= number_format((float) $item['quantidade'], 3, ',', '.') ?></td>
                            <td><?= money_br($item['valor_unitario']) ?></td>
                            <td class="money"><?= money_br($item['total']) ?></td>
                            <td>
                                <form method="post" class="actions">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="atualizar_servico">
                                    <input type="hidden" name="ordem_id" value="<?= $ordemId ?>">
                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                    <select class="select" name="status_servico">
                                        <option value="pendente" <?= $item['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                        <option value="em_execucao" <?= $item['status'] === 'em_execucao' ? 'selected' : '' ?>>Em execução</option>
                                        <option value="concluido" <?= $item['status'] === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                                        <option value="cancelado" <?= $item['status'] === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                    </select>
                                    <button class="btn" type="submit">Salvar</button>
                                </form>
                            </td>
                            <td>
                                <form method="post" onsubmit="return confirm('Remover este serviço?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="remover_servico">
                                    <input type="hidden" name="ordem_id" value="<?= $ordemId ?>">
                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                    <button class="btn btn-danger" type="submit">Remover</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><div><h2>Peças</h2><p><?= $configPermitirEstoqueNegativo ? 'A inclusão baixa o estoque e pode deixá-lo negativo conforme a configuração.' : 'A inclusão baixa automaticamente a quantidade do estoque.' ?></p></div></div>
            <form method="post" class="filters">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="adicionar_peca">
                <input type="hidden" name="ordem_id" value="<?= $ordemId ?>">
                <select class="select" name="peca_id" required>
                    <option value="">Selecione a peça</option>
                    <?php foreach ($catalogoPecas as $peca): ?>
                        <option value="<?= (int) $peca['id'] ?>"><?= h($peca['nome']) ?><?= $peca['codigo'] ? ' • ' . h($peca['codigo']) : '' ?> • estoque <?= number_format((float) $peca['estoque_atual'], 3, ',', '.') ?> <?= h($peca['unidade']) ?> • <?= money_br($peca['preco_venda']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="input" name="quantidade" value="1" inputmode="decimal" style="max-width:110px">
                <input class="input" name="valor_unitario" inputmode="decimal" placeholder="Valor padrão" style="max-width:150px">
                <button class="btn btn-primary" type="submit">Adicionar</button>
            </form>

            <div class="table-shell">
                <table class="table">
                    <thead><tr><th>Peça</th><th>Qtd.</th><th>Unitário</th><th>Total</th><th>Estoque</th><th>Ações</th></tr></thead>
                    <tbody>
                    <?php if (!$pecasItens): ?><tr><td colspan="6" class="muted">Nenhuma peça adicionada.</td></tr><?php endif; ?>
                    <?php foreach ($pecasItens as $item): ?>
                        <tr>
                            <td><?= h($item['descricao']) ?></td>
                            <td><?= number_format((float) $item['quantidade'], 3, ',', '.') ?></td>
                            <td><?= money_br($item['valor_unitario']) ?></td>
                            <td class="money"><?= money_br($item['total']) ?></td>
                            <td><span class="badge <?= (int) $item['baixada_estoque'] === 1 ? 'success' : 'warning' ?>"><?= (int) $item['baixada_estoque'] === 1 ? 'Baixada' : 'Pendente' ?></span></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Remover esta peça e devolver ao estoque?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="remover_peca">
                                    <input type="hidden" name="ordem_id" value="<?= $ordemId ?>">
                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                    <button class="btn btn-danger" type="submit">Remover</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="card section-card">
            <div class="section-title"><div><h2>Valores</h2><p>Resumo financeiro da ordem.</p></div></div>
            <div class="summary-box">
                <div class="summary-line"><span>Serviços</span><strong><?= money_br($ordem['subtotal_servicos']) ?></strong></div>
                <div class="summary-line"><span>Peças</span><strong><?= money_br($ordem['subtotal_pecas']) ?></strong></div>
                <?php if ($configPermitirDesconto): ?><div class="summary-line"><span>Desconto</span><strong>- <?= money_br($ordem['desconto']) ?></strong></div><?php endif; ?>
                <div class="summary-line"><span>Acréscimo</span><strong><?= money_br($ordem['acrescimo']) ?></strong></div>
                <div class="summary-total"><span>Total</span><span><?= money_br($ordem['total']) ?></span></div>
                <div class="summary-line"><span>Pago</span><strong><?= money_br($totalPago) ?></strong></div>
                <div class="summary-line"><span>Saldo</span><strong><?= money_br($saldo) ?></strong></div>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><div><h2>Ajustes da OS</h2><p>Responsável, valores e informações técnicas.</p></div></div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="atualizar_ordem">
                <input type="hidden" name="ordem_id" value="<?= $ordemId ?>">
                <div class="form-row" style="grid-template-columns:1fr">
                    <div class="form-group">
                        <label>Mecânico responsável<?= $configExigirMecanico ? ' *' : '' ?></label>
                        <select class="select" name="mecanico_responsavel_id" <?= $configExigirMecanico ? 'required' : '' ?>>
                            <option value="0"><?= $configExigirMecanico ? 'Selecione o mecânico' : 'Não definido' ?></option>
                            <?php foreach ($mecanicos as $mecanico): ?><option value="<?= (int) $mecanico['id'] ?>" <?= (int) $ordem['mecanico_responsavel_id'] === (int) $mecanico['id'] ? 'selected' : '' ?>><?= h($mecanico['nome']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>KM saída</label><input class="input" type="number" min="0" name="km_saida" value="<?= h($ordem['km_saida'] !== null ? (string) $ordem['km_saida'] : '') ?>"></div>
                    <div class="form-group"><label>Previsão de entrega</label><input class="input" type="datetime-local" name="previsao_entrega" value="<?= $ordem['previsao_entrega'] ? date('Y-m-d\TH:i', strtotime($ordem['previsao_entrega'])) : '' ?>"></div>
                    <?php if ($configPermitirDesconto): ?><div class="form-group"><label>Desconto</label><input class="input" name="desconto" inputmode="decimal" value="<?= number_format((float) $ordem['desconto'], 2, ',', '.') ?>"></div><?php endif; ?>
                    <div class="form-group"><label>Acréscimo</label><input class="input" name="acrescimo" inputmode="decimal" value="<?= number_format((float) $ordem['acrescimo'], 2, ',', '.') ?>"></div>
                    <div class="form-group"><label>Diagnóstico</label><textarea class="input" name="diagnostico"><?= h($ordem['diagnostico'] ?? '') ?></textarea></div>
                    <div class="form-group"><label>Observações</label><textarea class="input" name="observacoes"><?= h($ordem['observacoes'] ?? '') ?></textarea></div>
                </div>
                <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center;margin-top:12px">Salvar ajustes</button>
            </form>
        </div>

        <div class="card section-card">
            <div class="section-title"><div><h2>Pagamentos</h2><p>Registre os recebimentos da ordem.</p></div></div>
            <form method="post" style="display:grid;gap:10px;margin-bottom:16px">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="adicionar_pagamento">
                <input type="hidden" name="ordem_id" value="<?= $ordemId ?>">
                <select class="select" name="forma" required>
                    <option value="">Forma de pagamento</option>
                    <option value="dinheiro">Dinheiro</option>
                    <option value="pix">PIX</option>
                    <option value="cartao_credito">Cartão de crédito</option>
                    <option value="cartao_debito">Cartão de débito</option>
                    <option value="transferencia">Transferência</option>
                    <option value="boleto">Boleto</option>
                    <option value="outro">Outro</option>
                </select>
                <input class="input" name="valor" inputmode="decimal" placeholder="Valor" required value="<?= $saldo > 0 ? number_format($saldo, 2, ',', '.') : '' ?>">
                <input class="input" type="number" name="parcelas" min="1" value="1" placeholder="Parcelas">
                <input class="input" name="referencia" maxlength="120" placeholder="Referência opcional">
                <input class="input" name="observacao_pagamento" maxlength="500" placeholder="Observação opcional">
                <button class="btn btn-primary" type="submit">Registrar pagamento</button>
            </form>
            <div class="operation-list">
                <?php if (!$pagamentos): ?><span class="muted">Nenhum pagamento registrado.</span><?php endif; ?>
                <?php foreach ($pagamentos as $pagamento): ?>
                    <div class="operation-item">
                        <div class="operation-copy"><strong><?= money_br($pagamento['valor']) ?></strong><span><?= h(ucwords(str_replace('_', ' ', $pagamento['forma']))) ?> • <?= datetime_br($pagamento['pago_em']) ?></span></div>
                        <form method="post" onsubmit="return confirm('Remover este pagamento?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="remover_pagamento">
                            <input type="hidden" name="ordem_id" value="<?= $ordemId ?>">
                            <input type="hidden" name="pagamento_id" value="<?= (int) $pagamento['id'] ?>">
                            <button class="btn btn-danger" type="submit">Remover</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><div><h2>Histórico</h2><p>Registro das movimentações da ordem.</p></div></div>
            <div class="timeline">
                <?php if (!$historico): ?><span class="muted">Sem histórico.</span><?php endif; ?>
                <?php foreach ($historico as $evento): ?>
                    <div class="timeline-item"><strong><?= h($evento['acao']) ?></strong><small><?= datetime_br($evento['created_at']) ?><?= $evento['observacao'] ? ' • ' . h($evento['observacao']) : '' ?></small></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>