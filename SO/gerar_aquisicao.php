<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
admin_check();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT o.*, s.nome as secretaria
    FROM oficios o
    JOIN secretarias s ON o.secretaria_id = s.id
    WHERE o.id = ? AND o.status = 'APROVADO'
");
$stmt->execute([$id]);
$oficio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$oficio) {
    die("Solicitação não encontrada ou não está aprovada.");
}

$stmt_existing = $pdo->prepare("
    SELECT a.id, a.numero_aq, a.valor_total, a.status, f.nome AS fornecedor
    FROM aquisicoes a
    JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE a.oficio_id = ?
    ORDER BY a.id ASC
");
$stmt_existing->execute([$id]);
$aquisicoes_existentes = $stmt_existing->fetchAll(PDO::FETCH_ASSOC);

$stmt_items = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ? ORDER BY id ASC");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$fornecedores = $pdo->query("SELECT * FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$items_by_id = [];
$total_oficio = 0;
foreach ($items as $item) {
    $item_id = (int)$item['id'];
    $items_by_id[$item_id] = $item;
    $total_oficio += (float)$item['quantidade'] * (float)($item['valor_unitario'] ?? 0);
}

$fornecedores_by_id = [];
foreach ($fornecedores as $fornecedor) {
    $fornecedores_by_id[(int)$fornecedor['id']] = $fornecedor;
}

$qtd_empresas_form = isset($_POST['qtd_empresas']) ? max(1, (int)$_POST['qtd_empresas']) : 1;
if (!empty($fornecedores)) {
    $qtd_empresas_form = min($qtd_empresas_form, count($fornecedores));
}
$empresas_form = $_POST['empresas'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!empty($aquisicoes_existentes)) {
            throw new Exception("Esta solicitação já possui aquisição gerada. Para evitar duplicidade, utilize as aquisições existentes na lista.");
        }

        if (empty($items)) {
            throw new Exception("Esta solicitação não possui itens para gerar aquisição.");
        }

        if (empty($fornecedores)) {
            throw new Exception("Cadastre pelo menos um fornecedor antes de gerar aquisição.");
        }

        $qtd_empresas = max(1, (int)($_POST['qtd_empresas'] ?? 1));
        if ($qtd_empresas > count($fornecedores)) {
            throw new Exception("A quantidade de empresas não pode ser maior que a quantidade de fornecedores cadastrados.");
        }

        $empresas = $_POST['empresas'] ?? [];
        $empresas_sanitizadas = [];
        $itens_atribuidos = [];
        $fornecedores_usados = [];

        for ($i = 0; $i < $qtd_empresas; $i++) {
            $empresa = $empresas[$i] ?? [];
            $fornecedor_id = (int)($empresa['fornecedor_id'] ?? 0);

            if (!isset($fornecedores_by_id[$fornecedor_id])) {
                throw new Exception("Selecione um fornecedor válido para a empresa " . ($i + 1) . ".");
            }

            if (isset($fornecedores_usados[$fornecedor_id])) {
                throw new Exception("O mesmo fornecedor não pode ser selecionado em mais de uma empresa.");
            }
            $fornecedores_usados[$fornecedor_id] = true;

            $item_ids = array_map('intval', $empresa['itens'] ?? []);
            $item_ids = array_values(array_unique(array_filter($item_ids)));

            if (empty($item_ids)) {
                throw new Exception("Selecione pelo menos um item para a empresa " . ($i + 1) . ".");
            }

            $itens_empresa = [];
            $valor_total_empresa = 0;

            foreach ($item_ids as $item_id) {
                if (!isset($items_by_id[$item_id])) {
                    throw new Exception("Um dos itens selecionados não pertence a esta solicitação.");
                }

                if (isset($itens_atribuidos[$item_id])) {
                    throw new Exception("O item '{$items_by_id[$item_id]['produto']}' foi selecionado em mais de uma empresa.");
                }

                $item = $items_by_id[$item_id];
                $valor_unitario = (float)($item['valor_unitario'] ?? 0);
                $quantidade = (float)($item['quantidade'] ?? 0);
                $valor_total_empresa += $quantidade * $valor_unitario;

                $itens_atribuidos[$item_id] = true;
                $itens_empresa[] = $item;
            }

            $empresas_sanitizadas[] = [
                'fornecedor_id' => $fornecedor_id,
                'itens' => $itens_empresa,
                'valor_total' => $valor_total_empresa,
            ];
        }

        if (count($itens_atribuidos) !== count($items_by_id)) {
            $pendentes = [];
            foreach ($items_by_id as $item_id => $item) {
                if (!isset($itens_atribuidos[$item_id])) {
                    $pendentes[] = $item['produto'];
                }
            }

            throw new Exception("Todos os itens devem ser distribuídos entre as empresas. Pendentes: " . implode(', ', $pendentes) . ".");
        }

        $pdo->beginTransaction();

        $stmt_aq = $pdo->prepare("
            INSERT INTO aquisicoes (
                numero_aq,
                codigo_entrega,
                oficio_id,
                fornecedor_id,
                valor_total
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $stmt_item_aq = $pdo->prepare("
            INSERT INTO itens_aquisicao (
                aquisicao_id,
                oficio_item_id,
                produto,
                quantidade,
                valor_unitario
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $aquisicoes_geradas = [];

        foreach ($empresas_sanitizadas as $empresa) {
            $numero_aq = generate_aquisicao_number($pdo);
            $codigo_entrega = generate_unique_code($pdo);

            $stmt_aq->execute([
                $numero_aq,
                $codigo_entrega,
                $id,
                $empresa['fornecedor_id'],
                $empresa['valor_total'],
            ]);

            $aq_id = (int)$pdo->lastInsertId();

            foreach ($empresa['itens'] as $item) {
                $stmt_item_aq->execute([
                    $aq_id,
                    (int)$item['id'],
                    $item['produto'],
                    (float)$item['quantidade'],
                    (float)($item['valor_unitario'] ?? 0),
                ]);
            }

            $aquisicoes_geradas[] = [
                'id' => $aq_id,
                'numero' => $numero_aq,
                'fornecedor' => $fornecedores_by_id[$empresa['fornecedor_id']]['nome'] ?? '',
            ];
        }

        $numeros = implode(', ', array_column($aquisicoes_geradas, 'numero'));
        log_action($pdo, "GERAR_AQUISICOES", "Aquisições {$numeros} geradas para Solicitação {$oficio['numero']}");
        $pdo->commit();

        if (count($aquisicoes_geradas) === 1) {
            flash_message('success', "Aquisição {$aquisicoes_geradas[0]['numero']} gerada com sucesso.");
            header("Location: aquisicoes_visualizar.php?id=" . $aquisicoes_geradas[0]['id']);
            exit();
        }

        flash_message('success', count($aquisicoes_geradas) . " aquisições geradas com sucesso para o ofício {$oficio['numero']}.");
        header("Location: aquisicoes_lista.php?busca=" . urlencode($oficio['numero']));
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = "Erro ao gerar aquisição: " . $e->getMessage();
    }
}

$page_title = "Gerar Aquisição: Solicitação " . $oficio['numero'];
include 'views/layout/header.php';
?>

<style>
    .aq-card {
        border-radius: 14px;
        overflow: hidden;
    }

    .aq-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .aq-info-box {
        background: #f8fafc;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }

    .aq-info-label {
        display: block;
        color: var(--text-muted);
        font-size: 0.78rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .split-control {
        display: grid;
        grid-template-columns: minmax(220px, 320px) 1fr;
        gap: 1rem;
        align-items: end;
        margin-bottom: 1.5rem;
    }

    .empresa-card {
        border: 1px solid var(--border-color);
        border-radius: 12px;
        margin-bottom: 1rem;
        background: #fff;
        overflow: hidden;
    }

    .empresa-card-header {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
        padding: 1rem;
        background: #f8fafc;
        border-bottom: 1px solid var(--border-color);
        flex-wrap: wrap;
    }

    .empresa-card-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        color: var(--text-dark);
    }

    .empresa-total {
        font-weight: 800;
        color: var(--primary);
    }

    .empresa-card-body {
        padding: 1rem;
    }

    .items-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .item-choice {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.8rem;
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 0.75rem;
        align-items: start;
        background: #fff;
        cursor: pointer;
    }

    .item-choice input {
        margin-top: 0.25rem;
    }

    .item-choice.is-disabled {
        opacity: 0.45;
        cursor: not-allowed;
        background: #f8fafc;
    }

    .item-title {
        font-weight: 800;
        color: var(--text-dark);
        line-height: 1.35;
    }

    .item-meta {
        color: var(--text-muted);
        font-size: 0.82rem;
        margin-top: 0.25rem;
    }

    .distribuicao-status {
        padding: 0.85rem 1rem;
        border-radius: 10px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .existing-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }

    .existing-table th,
    .existing-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border-color);
    }

    .existing-table th {
        text-align: left;
        background: #f8fafc;
    }

    @media (max-width: 900px) {
        .aq-info-box,
        .split-control,
        .items-grid {
            grid-template-columns: 1fr;
        }

        .aq-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        .aq-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="card aq-card">
    <div class="card-body">
        <div class="aq-header">
            <h3 style="margin:0;">
                <i class="fas fa-file-invoice-dollar"></i>
                Gerar Aquisição - Solicitação <?php echo htmlspecialchars($oficio['numero'], ENT_QUOTES, 'UTF-8'); ?>
            </h3>
            <a href="oficios_lista.php" class="btn btn-outline btn-sm">Voltar</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="aq-info-box">
            <div>
                <span class="aq-info-label">Ofício</span>
                <strong><?php echo htmlspecialchars($oficio['numero'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div>
                <span class="aq-info-label">Secretaria</span>
                <strong><?php echo htmlspecialchars($oficio['secretaria'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div>
                <span class="aq-info-label">Total dos itens</span>
                <strong><?php echo format_money($total_oficio); ?></strong>
            </div>
        </div>

        <?php if (!empty($aquisicoes_existentes)): ?>
            <div class="alert alert-info">
                Este ofício já possui aquisição gerada. Cada aquisição abaixo pode ser controlada e impressa individualmente.
            </div>

            <div style="overflow-x:auto;">
                <table class="existing-table">
                    <thead>
                        <tr>
                            <th>Nº Aquisição</th>
                            <th>Fornecedor</th>
                            <th>Status</th>
                            <th style="text-align:right;">Valor</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aquisicoes_existentes as $aq): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($aq['numero_aq'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><?php echo htmlspecialchars($aq['fornecedor'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($aq['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="text-align:right; font-weight:700;"><?php echo format_money($aq['valor_total']); ?></td>
                                <td style="text-align:right;">
                                    <a href="aquisicoes_visualizar.php?id=<?php echo (int)$aq['id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> Visualizar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (empty($items)): ?>
            <div class="alert alert-warning">
                Esta solicitação não possui itens. Atribua os itens antes de gerar aquisição.
            </div>
        <?php elseif (empty($fornecedores)): ?>
            <div class="alert alert-warning">
                Nenhum fornecedor cadastrado. Cadastre fornecedores antes de gerar aquisição.
            </div>
        <?php else: ?>
            <form action="" method="POST" id="split-form">
                <div class="split-control">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Quantidade de empresas participantes</label>
                        <input
                            type="number"
                            name="qtd_empresas"
                            id="qtd-empresas"
                            class="form-control"
                            min="1"
                            max="<?php echo count($fornecedores); ?>"
                            value="<?php echo (int)$qtd_empresas_form; ?>"
                            required>
                    </div>

                    <div class="distribuicao-status" id="distribuicao-status">
                        Distribua todos os itens entre as empresas antes de gerar.
                    </div>
                </div>

                <div id="empresas-container">
                    <?php for ($empresa_idx = 0; $empresa_idx < $qtd_empresas_form; $empresa_idx++): ?>
                        <?php
                        $empresa_post = $empresas_form[$empresa_idx] ?? [];
                        $fornecedor_selected = (int)($empresa_post['fornecedor_id'] ?? 0);
                        $itens_selected = array_map('intval', $empresa_post['itens'] ?? []);
                        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $empresa_idx === 0 && $qtd_empresas_form === 1) {
                            $itens_selected = array_map('intval', array_column($items, 'id'));
                        }
                        ?>
                        <div class="empresa-card" data-company-index="<?php echo $empresa_idx; ?>">
                            <div class="empresa-card-header">
                                <h4 class="empresa-card-title">
                                    <i class="fas fa-building"></i>
                                    Empresa <?php echo $empresa_idx + 1; ?>
                                </h4>
                                <span class="empresa-total">Total: R$ 0,00</span>
                            </div>

                            <div class="empresa-card-body">
                                <div class="form-group">
                                    <label class="form-label">Fornecedor</label>
                                    <select name="empresas[<?php echo $empresa_idx; ?>][fornecedor_id]" class="form-control fornecedor-select" required>
                                        <option value="">Selecione o fornecedor...</option>
                                        <?php foreach ($fornecedores as $fornecedor): ?>
                                            <option value="<?php echo (int)$fornecedor['id']; ?>" <?php echo $fornecedor_selected === (int)$fornecedor['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($fornecedor['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if (!empty($fornecedor['cnpj'])): ?>
                                                    (<?php echo htmlspecialchars($fornecedor['cnpj'], ENT_QUOTES, 'UTF-8'); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="items-grid">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $item_id = (int)$item['id'];
                                        $quantidade = (float)$item['quantidade'];
                                        $valor_unitario = (float)($item['valor_unitario'] ?? 0);
                                        $subtotal = $quantidade * $valor_unitario;
                                        $checked = in_array($item_id, $itens_selected, true);
                                        ?>
                                        <label class="item-choice">
                                            <input
                                                type="checkbox"
                                                class="item-check"
                                                name="empresas[<?php echo $empresa_idx; ?>][itens][]"
                                                value="<?php echo $item_id; ?>"
                                                data-item-id="<?php echo $item_id; ?>"
                                                data-total="<?php echo htmlspecialchars((string)$subtotal, ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo $checked ? 'checked' : ''; ?>>
                                            <span>
                                                <span class="item-title"><?php echo htmlspecialchars($item['produto'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="item-meta">
                                                    <?php echo number_format($quantidade, 2, ',', '.'); ?>
                                                    <?php echo htmlspecialchars($item['unidade'] ?? 'UN', ENT_QUOTES, 'UTF-8'); ?>
                                                    | <?php echo format_money($valor_unitario); ?>
                                                    | Subtotal <?php echo format_money($subtotal); ?>
                                                </span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="aq-actions" style="margin-top: 2rem; text-align: right;">
                    <a href="oficios_lista.php" class="btn btn-outline" style="margin-right: 10px;">Cancelar</a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-check"></i> Gerar Aquisição
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($aquisicoes_existentes) && !empty($items) && !empty($fornecedores)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fornecedores = <?php echo json_encode($fornecedores, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const items = <?php echo json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const countInput = document.getElementById('qtd-empresas');
    const container = document.getElementById('empresas-container');
    const statusBox = document.getElementById('distribuicao-status');
    const form = document.getElementById('split-form');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMoneyBR(value) {
        return 'R$ ' + Number(value || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function getState() {
        return Array.from(container.querySelectorAll('.empresa-card')).map(card => {
            return {
                fornecedor_id: card.querySelector('.fornecedor-select')?.value || '',
                itens: Array.from(card.querySelectorAll('.item-check:checked')).map(input => String(input.dataset.itemId))
            };
        });
    }

    function renderCards(nextCount) {
        const previous = getState();
        const max = fornecedores.length;
        const count = Math.max(1, Math.min(max, parseInt(nextCount, 10) || 1));
        countInput.value = count;

        let html = '';
        for (let index = 0; index < count; index++) {
            const state = previous[index] || { fornecedor_id: '', itens: [] };
            const selectedItems = new Set((state.itens || []).map(String));

            let fornecedorOptions = '<option value="">Selecione o fornecedor...</option>';
            fornecedores.forEach(fornecedor => {
                const selected = String(fornecedor.id) === String(state.fornecedor_id) ? 'selected' : '';
                const cnpj = fornecedor.cnpj ? ` (${escapeHtml(fornecedor.cnpj)})` : '';
                fornecedorOptions += `<option value="${fornecedor.id}" ${selected}>${escapeHtml(fornecedor.nome)}${cnpj}</option>`;
            });

            let itemOptions = '';
            items.forEach(item => {
                const itemId = String(item.id);
                const quantidade = Number(item.quantidade || 0);
                const valorUnitario = Number(item.valor_unitario || 0);
                const subtotal = quantidade * valorUnitario;
                const checked = selectedItems.has(itemId) ? 'checked' : '';
                itemOptions += `
                    <label class="item-choice">
                        <input
                            type="checkbox"
                            class="item-check"
                            name="empresas[${index}][itens][]"
                            value="${item.id}"
                            data-item-id="${item.id}"
                            data-total="${subtotal}"
                            ${checked}>
                        <span>
                            <span class="item-title">${escapeHtml(item.produto)}</span>
                            <span class="item-meta">
                                ${quantidade.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                ${escapeHtml(item.unidade || 'UN')}
                                | ${formatMoneyBR(valorUnitario)}
                                | Subtotal ${formatMoneyBR(subtotal)}
                            </span>
                        </span>
                    </label>
                `;
            });

            html += `
                <div class="empresa-card" data-company-index="${index}">
                    <div class="empresa-card-header">
                        <h4 class="empresa-card-title">
                            <i class="fas fa-building"></i>
                            Empresa ${index + 1}
                        </h4>
                        <span class="empresa-total">Total: R$ 0,00</span>
                    </div>

                    <div class="empresa-card-body">
                        <div class="form-group">
                            <label class="form-label">Fornecedor</label>
                            <select name="empresas[${index}][fornecedor_id]" class="form-control fornecedor-select" required>
                                ${fornecedorOptions}
                            </select>
                        </div>

                        <div class="items-grid">
                            ${itemOptions}
                        </div>
                    </div>
                </div>
            `;
        }

        container.innerHTML = html;
        updateDistribution();
    }

    function updateDistribution() {
        const selectedByItem = {};

        container.querySelectorAll('.item-check:checked').forEach(input => {
            const itemId = input.dataset.itemId;
            if (!selectedByItem[itemId]) {
                selectedByItem[itemId] = [];
            }
            selectedByItem[itemId].push(input);
        });

        container.querySelectorAll('.item-check').forEach(input => {
            const itemId = input.dataset.itemId;
            const selectedElsewhere = selectedByItem[itemId]?.some(selected => selected !== input);
            input.disabled = Boolean(selectedElsewhere);
            input.closest('.item-choice')?.classList.toggle('is-disabled', Boolean(selectedElsewhere));
        });

        container.querySelectorAll('.empresa-card').forEach(card => {
            let total = 0;
            card.querySelectorAll('.item-check:checked').forEach(input => {
                total += Number(input.dataset.total || 0);
            });

            const totalEl = card.querySelector('.empresa-total');
            if (totalEl) {
                totalEl.textContent = 'Total: ' + formatMoneyBR(total);
            }
        });

        const totalItems = items.length;
        const assignedCount = Object.keys(selectedByItem).length;
        statusBox.textContent = `Itens distribuídos: ${assignedCount} de ${totalItems}`;
        statusBox.style.background = assignedCount === totalItems ? '#ecfdf5' : '#eff6ff';
        statusBox.style.borderColor = assignedCount === totalItems ? '#bbf7d0' : '#bfdbfe';
        statusBox.style.color = assignedCount === totalItems ? '#166534' : '#1e40af';
    }

    countInput.addEventListener('change', function() {
        renderCards(this.value);
    });

    countInput.addEventListener('input', function() {
        if (this.value !== '') {
            renderCards(this.value);
        }
    });

    container.addEventListener('change', function(e) {
        if (e.target.classList.contains('item-check') || e.target.classList.contains('fornecedor-select')) {
            updateDistribution();
        }
    });

    form.addEventListener('submit', function(e) {
        updateDistribution();

        const selectedItems = new Set();
        let hasError = false;
        let message = '';
        const suppliers = new Set();

        container.querySelectorAll('.empresa-card').forEach((card, index) => {
            const fornecedor = card.querySelector('.fornecedor-select')?.value || '';
            const checked = card.querySelectorAll('.item-check:checked');

            if (!fornecedor && !hasError) {
                hasError = true;
                message = `Selecione o fornecedor da empresa ${index + 1}.`;
            }

            if (fornecedor && suppliers.has(fornecedor) && !hasError) {
                hasError = true;
                message = 'O mesmo fornecedor não pode ser usado em mais de uma empresa.';
            }
            if (fornecedor) {
                suppliers.add(fornecedor);
            }

            if (checked.length === 0 && !hasError) {
                hasError = true;
                message = `Selecione pelo menos um item para a empresa ${index + 1}.`;
            }

            checked.forEach(input => selectedItems.add(String(input.dataset.itemId)));
        });

        if (!hasError && selectedItems.size !== items.length) {
            hasError = true;
            message = 'Distribua todos os itens entre as empresas antes de gerar.';
        }

        if (hasError) {
            e.preventDefault();
            alert(message);
            return false;
        }
    });

    updateDistribution();
});
</script>
<?php endif; ?>

<?php include 'views/layout/footer.php'; ?>
