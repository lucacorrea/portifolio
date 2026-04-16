<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$page_title = "Editar Aquisição";

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_money')) {
    function format_money($value)
    {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }
}

if (!function_exists('number_input_value')) {
    function number_input_value($value, int $decimals = 2): string
    {
        return number_format((float)$value, $decimals, '.', '');
    }
}

function redirect_list(string $type, string $message): never
{
    $param = $type === 'erro' ? 'erro' : 'sucesso';
    header('Location: aquisicoes_lista.php?' . http_build_query([$param => $message]));
    exit;
}

function get_logged_user_id(): ?int
{
    $possibleKeys = ['usuario_id', 'user_id', 'id'];

    foreach ($possibleKeys as $key) {
        if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key])) {
            return (int)$_SESSION[$key];
        }
    }

    return null;
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    redirect_list('erro', 'Aquisição inválida.');
}

$nivel_user = strtoupper((string)($_SESSION['nivel'] ?? ''));
if (!in_array($nivel_user, ['ADMIN', 'SUPORTE'], true)) {
    redirect_list('erro', 'Você não tem permissão para editar aquisições.');
}

/*
|--------------------------------------------------------------------------
| CARREGA AQUISIÇÃO
|--------------------------------------------------------------------------
| Aqui usa a tabela oficios correta:
| - oficios.numero
| - oficios.secretaria_id
| e NÃO usa itens_oficio para editar aquisição.
*/
$stmtAq = $pdo->prepare("
    SELECT
        a.*,
        o.numero AS oficio_num,
        o.justificativa AS oficio_justificativa,
        o.status AS oficio_status,
        o.criado_em AS oficio_criado_em,
        s.nome AS secretaria_nome,
        f.nome AS fornecedor_nome
    FROM aquisicoes a
    INNER JOIN oficios o ON a.oficio_id = o.id
    INNER JOIN secretarias s ON o.secretaria_id = s.id
    INNER JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE a.id = :id
    LIMIT 1
");
$stmtAq->execute([':id' => $id]);
$aquisicao = $stmtAq->fetch(PDO::FETCH_ASSOC);

if (!$aquisicao) {
    redirect_list('erro', 'Aquisição não encontrada.');
}

if (($aquisicao['status'] ?? '') === 'FINALIZADO') {
    redirect_list('erro', 'Aquisição finalizada não pode ser editada.');
}

/*
|--------------------------------------------------------------------------
| FORNECEDORES
|--------------------------------------------------------------------------
*/
$fornecedores = $pdo->query("
    SELECT id, nome
    FROM fornecedores
    ORDER BY nome ASC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| ITENS DA AQUISIÇÃO
|--------------------------------------------------------------------------
| Aqui sim usa a tabela correta da edição:
| itens_aquisicao
*/
$stmtItens = $pdo->prepare("
    SELECT
        id,
        produto,
        quantidade,
        valor_unitario
    FROM itens_aquisicao
    WHERE aquisicao_id = :id
    ORDER BY id ASC
");
$stmtItens->execute([':id' => $id]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

if (empty($itens)) {
    $itens = [[
        'produto' => '',
        'quantidade' => '1.00',
        'valor_unitario' => '0.00',
    ]];
}

$erro = '';

/*
|--------------------------------------------------------------------------
| PROCESSA EDIÇÃO
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fornecedor_id = (int)($_POST['fornecedor_id'] ?? 0);
    $responsavel_entrega = trim((string)($_POST['responsavel_entrega'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'AGUARDANDO ENTREGA'));

    $statusPermitidos = ['AGUARDANDO ENTREGA', 'FINALIZADO'];
    if (!in_array($status, $statusPermitidos, true)) {
        $status = 'AGUARDANDO ENTREGA';
    }

    $produtos = $_POST['item_produto'] ?? [];
    $quantidades = $_POST['item_quantidade'] ?? [];
    $valores = $_POST['item_valor_unitario'] ?? [];

    $fornecedorExiste = false;
    foreach ($fornecedores as $forn) {
        if ((int)$forn['id'] === $fornecedor_id) {
            $fornecedorExiste = true;
            break;
        }
    }

    if (!$fornecedorExiste) {
        $erro = 'Selecione um fornecedor válido.';
    }

    $itensForm = [];
    $itensValidos = [];
    $valorTotal = 0.0;

    $maxLinhas = max(count($produtos), count($quantidades), count($valores));

    for ($i = 0; $i < $maxLinhas; $i++) {
        $produto = trim((string)($produtos[$i] ?? ''));
        $quantidadeRaw = trim((string)($quantidades[$i] ?? ''));
        $valorRaw = trim((string)($valores[$i] ?? ''));

        $itensForm[] = [
            'produto' => $produto,
            'quantidade' => $quantidadeRaw,
            'valor_unitario' => $valorRaw,
        ];

        $linhaVazia = ($produto === '' && $quantidadeRaw === '' && $valorRaw === '');
        if ($linhaVazia) {
            continue;
        }

        $quantidade = (float)str_replace(',', '.', $quantidadeRaw);
        $valorUnitario = (float)str_replace(',', '.', $valorRaw);

        if ($produto === '') {
            $erro = 'Preencha o nome do produto em todos os itens.';
            break;
        }

        if ($quantidade <= 0) {
            $erro = 'A quantidade deve ser maior que zero em todos os itens.';
            break;
        }

        if ($valorUnitario < 0) {
            $erro = 'O valor unitário não pode ser negativo.';
            break;
        }

        $itensValidos[] = [
            'produto' => $produto,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
        ];

        $valorTotal += ($quantidade * $valorUnitario);
    }

    if ($erro === '' && empty($itensValidos)) {
        $erro = 'Adicione pelo menos um item válido na aquisição.';
    }

    if ($erro === '') {
        try {
            $pdo->beginTransaction();

            $usuarioFinalizou = null;
            $dataFinalizacao = null;

            if ($status === 'FINALIZADO') {
                $usuarioFinalizou = get_logged_user_id();
                $dataFinalizacao = date('Y-m-d H:i:s');
            }

            $stmtUpdate = $pdo->prepare("
                UPDATE aquisicoes
                SET
                    fornecedor_id = :fornecedor_id,
                    responsavel_entrega = :responsavel_entrega,
                    status = :status,
                    valor_total = :valor_total,
                    data_finalizacao = :data_finalizacao,
                    usuario_id_finalizou = :usuario_id_finalizou
                WHERE id = :id
            ");

            $stmtUpdate->bindValue(':fornecedor_id', $fornecedor_id, PDO::PARAM_INT);
            $stmtUpdate->bindValue(
                ':responsavel_entrega',
                $responsavel_entrega !== '' ? $responsavel_entrega : null,
                $responsavel_entrega !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
            );
            $stmtUpdate->bindValue(':status', $status, PDO::PARAM_STR);
            $stmtUpdate->bindValue(':valor_total', $valorTotal);
            $stmtUpdate->bindValue(
                ':data_finalizacao',
                $dataFinalizacao,
                $dataFinalizacao !== null ? PDO::PARAM_STR : PDO::PARAM_NULL
            );
            $stmtUpdate->bindValue(
                ':usuario_id_finalizou',
                $usuarioFinalizou,
                $usuarioFinalizou !== null ? PDO::PARAM_INT : PDO::PARAM_NULL
            );
            $stmtUpdate->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtUpdate->execute();

            $stmtDeleteItens = $pdo->prepare("
                DELETE FROM itens_aquisicao
                WHERE aquisicao_id = :id
            ");
            $stmtDeleteItens->execute([':id' => $id]);

            $stmtInsertItem = $pdo->prepare("
                INSERT INTO itens_aquisicao (
                    aquisicao_id,
                    produto,
                    quantidade,
                    valor_unitario
                ) VALUES (
                    :aquisicao_id,
                    :produto,
                    :quantidade,
                    :valor_unitario
                )
            ");

            foreach ($itensValidos as $item) {
                $stmtInsertItem->execute([
                    ':aquisicao_id' => $id,
                    ':produto' => $item['produto'],
                    ':quantidade' => $item['quantidade'],
                    ':valor_unitario' => $item['valor_unitario'],
                ]);
            }

            $pdo->commit();
            redirect_list('sucesso', 'Aquisição atualizada com sucesso.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = 'Não foi possível salvar a edição da aquisição.';
        }
    }

    $aquisicao['fornecedor_id'] = $fornecedor_id;
    $aquisicao['responsavel_entrega'] = $responsavel_entrega;
    $aquisicao['status'] = $status;
    $aquisicao['valor_total'] = $valorTotal;
    $itens = !empty($itensForm) ? $itensForm : $itens;
}

include 'views/layout/header.php';
?>

<style>
    .edit-wrap .card {
        border: 1px solid #e9edf5;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        overflow: hidden;
        background: #fff;
    }

    .edit-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .edit-title h3 {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
    }

    .edit-title p {
        margin: .35rem 0 0;
        color: #64748b;
        font-size: .92rem;
    }

    .btn-custom {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        border-radius: 12px;
        padding: .78rem 1rem;
        border: 1px solid transparent;
        text-decoration: none;
        cursor: pointer;
        font-weight: 700;
        transition: .2s ease;
    }

    .btn-primary-custom {
        background: #206bc4;
        border-color: #206bc4;
        color: #fff;
    }

    .btn-primary-custom:hover {
        background: #1b5aa7;
        border-color: #1b5aa7;
        color: #fff;
        text-decoration: none;
    }

    .btn-outline-custom {
        background: #fff;
        border-color: #dbe2ea;
        color: #334155;
    }

    .btn-outline-custom:hover {
        background: #f8fafc;
        color: #0f172a;
        text-decoration: none;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .summary-box {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1rem;
        background: #f8fafc;
    }

    .summary-box .label {
        display: block;
        font-size: .78rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .35px;
        margin-bottom: .35rem;
    }

    .summary-box .value {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.35;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-label {
        display: block;
        margin-bottom: .5rem;
        font-size: .88rem;
        font-weight: 700;
        color: #334155;
    }

    .form-control {
        width: 100%;
        min-height: 46px;
        border-radius: 12px;
        border: 1px solid #dbe2ea;
        padding: .75rem .95rem;
        transition: .2s ease;
        background: #fff;
    }

    .form-control:focus {
        outline: none;
        border-color: #206bc4;
        box-shadow: 0 0 0 4px rgba(32, 107, 196, 0.10);
    }

    .table-items-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
    }

    .table-items {
        width: 100%;
        min-width: 820px;
        border-collapse: collapse;
    }

    .table-items th,
    .table-items td {
        padding: .9rem .8rem;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
    }

    .table-items thead th {
        background: #f8fafc;
        color: #334155;
        font-size: .85rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .table-items tbody tr:last-child td {
        border-bottom: none;
    }

    .td-right {
        text-align: right;
    }

    .td-center {
        text-align: center;
    }

    .item-total-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 120px;
        padding: .5rem .8rem;
        border-radius: 999px;
        background: rgba(32, 107, 196, 0.10);
        color: #206bc4;
        font-weight: 800;
        font-size: .84rem;
    }

    .total-geral-box {
        margin-top: 1rem;
        display: flex;
        justify-content: flex-end;
    }

    .total-geral-inner {
        min-width: 280px;
        border: 1px solid #dbeafe;
        background: #eff6ff;
        border-radius: 16px;
        padding: 1rem 1.25rem;
        text-align: right;
    }

    .total-geral-inner .label {
        display: block;
        color: #1d4ed8;
        font-size: .82rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    .total-geral-inner .value {
        display: block;
        margin-top: .35rem;
        color: #0f172a;
        font-size: 1.5rem;
        font-weight: 900;
    }

    .toolbar-itens {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .toolbar-itens h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
    }

    .toolbar-itens p {
        margin: .25rem 0 0;
        color: #64748b;
        font-size: .88rem;
    }

    .alert-inline {
        border-radius: 12px;
        padding: .95rem 1rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .alert-inline.error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .footer-actions {
        display: flex;
        justify-content: flex-end;
        gap: .75rem;
        flex-wrap: wrap;
        margin-top: 1.5rem;
    }

    .btn-remove-item {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 1px solid #fecdd3;
        background: #fff1f2;
        color: #be123c;
        cursor: pointer;
        transition: .2s ease;
    }

    .btn-remove-item:hover {
        background: #ffe4e6;
        color: #9f1239;
    }

    @media (max-width: 992px) {
        .summary-grid {
            grid-template-columns: repeat(2, minmax(180px, 1fr));
        }

        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }

        .footer-actions .btn-custom,
        .edit-topbar .btn-custom,
        .toolbar-itens .btn-custom {
            width: 100%;
        }

        .footer-actions {
            flex-direction: column;
        }

        .total-geral-box {
            justify-content: stretch;
        }

        .total-geral-inner {
            width: 100%;
            min-width: 0;
        }
    }
</style>

<div class="edit-wrap">
    <div class="card">
        <div class="card-body" style="padding: 1.5rem;">
            <div class="edit-topbar">
                <div class="edit-title">
                    <h3><i class="fas fa-pen-to-square" style="margin-right: .45rem; color: #206bc4;"></i>Editar Aquisição</h3>
                    <p>Altere os dados da aquisição e seus itens. O valor total será recalculado automaticamente.</p>
                </div>

                <a href="aquisicoes_lista.php" class="btn-custom btn-outline-custom">
                    <i class="fas fa-arrow-left"></i> Voltar para lista
                </a>
            </div>

            <?php if ($erro !== ''): ?>
                <div class="alert-inline error"><?php echo h($erro); ?></div>
            <?php endif; ?>

            <div class="summary-grid">
                <div class="summary-box">
                    <span class="label">Nº Aquisição</span>
                    <span class="value"><?php echo h($aquisicao['numero_aq'] ?? ''); ?></span>
                </div>
                <div class="summary-box">
                    <span class="label">Código Entrega</span>
                    <span class="value"><?php echo h($aquisicao['codigo_entrega'] ?? ''); ?></span>
                </div>
                <div class="summary-box">
                    <span class="label">Ofício</span>
                    <span class="value"><?php echo h($aquisicao['oficio_num'] ?? ''); ?></span>
                </div>
                <div class="summary-box">
                    <span class="label">Secretaria</span>
                    <span class="value"><?php echo h($aquisicao['secretaria_nome'] ?? ''); ?></span>
                </div>
            </div>

            <form method="POST" id="formEditarAquisicao" action="">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Fornecedor</label>
                        <select name="fornecedor_id" class="form-control" required>
                            <option value="">Selecione</option>
                            <?php foreach ($fornecedores as $forn): ?>
                                <option value="<?php echo (int)$forn['id']; ?>" <?php echo (int)($aquisicao['fornecedor_id'] ?? 0) === (int)$forn['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($forn['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Responsável pela Entrega</label>
                        <input
                            type="text"
                            name="responsavel_entrega"
                            class="form-control"
                            maxlength="255"
                            value="<?php echo h($aquisicao['responsavel_entrega'] ?? ''); ?>"
                            placeholder="Nome do responsável">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="AGUARDANDO ENTREGA" <?php echo ($aquisicao['status'] ?? '') === 'AGUARDANDO ENTREGA' ? 'selected' : ''; ?>>AGUARDANDO ENTREGA</option>
                            <option value="FINALIZADO" <?php echo ($aquisicao['status'] ?? '') === 'FINALIZADO' ? 'selected' : ''; ?>>FINALIZADO</option>
                        </select>
                    </div>
                </div>

                <div class="toolbar-itens">
                    <div>
                        <h4>Itens da Aquisição</h4>
                        <p>Esses itens são da tabela <strong>itens_aquisicao</strong>, não de <strong>itens_oficio</strong>.</p>
                    </div>

                    <button type="button" class="btn-custom btn-outline-custom" id="btnAdicionarItem">
                        <i class="fas fa-plus"></i> Adicionar item
                    </button>
                </div>

                <div class="table-items-wrap">
                    <table class="table-items" id="tableItens">
                        <thead>
                            <tr>
                                <th style="width: 44%;">Produto</th>
                                <th style="width: 18%;">Quantidade</th>
                                <th style="width: 18%;">Valor Unitário</th>
                                <th style="width: 14%;" class="td-right">Total</th>
                                <th style="width: 6%;" class="td-center">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyItens">
                            <?php foreach ($itens as $item): ?>
                                <?php
                                $qtd = (float)($item['quantidade'] ?? 0);
                                $vu = (float)($item['valor_unitario'] ?? 0);
                                $totalItem = $qtd * $vu;
                                ?>
                                <tr class="item-row">
                                    <td>
                                        <input type="text" name="item_produto[]" class="form-control item-produto" value="<?php echo h($item['produto'] ?? ''); ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" name="item_quantidade[]" class="form-control item-quantidade" value="<?php echo h(is_numeric($item['quantidade'] ?? null) ? number_input_value($item['quantidade'], 2) : $item['quantidade']); ?>" min="0.01" step="0.01" required>
                                    </td>
                                    <td>
                                        <input type="number" name="item_valor_unitario[]" class="form-control item-valor" value="<?php echo h(is_numeric($item['valor_unitario'] ?? null) ? number_input_value($item['valor_unitario'], 2) : $item['valor_unitario']); ?>" min="0" step="0.01" required>
                                    </td>
                                    <td class="td-right">
                                        <span class="item-total-badge item-total-text"><?php echo format_money($totalItem); ?></span>
                                    </td>
                                    <td class="td-center">
                                        <button type="button" class="btn-remove-item" title="Remover item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="total-geral-box">
                    <div class="total-geral-inner">
                        <span class="label">Valor total da aquisição</span>
                        <span class="value" id="totalGeralView"><?php echo format_money($aquisicao['valor_total'] ?? 0); ?></span>
                    </div>
                </div>

                <div class="footer-actions">
                    <a href="aquisicoes_lista.php" class="btn-custom btn-outline-custom">
                        Cancelar
                    </a>

                    <button type="submit" class="btn-custom btn-primary-custom">
                        <i class="fas fa-save"></i> Salvar alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const tbody = document.getElementById('tbodyItens');
        const btnAdicionar = document.getElementById('btnAdicionarItem');
        const totalGeralView = document.getElementById('totalGeralView');

        function parseValue(value) {
            const num = parseFloat(String(value).replace(',', '.'));
            return isNaN(num) ? 0 : num;
        }

        function formatBRL(value) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value || 0);
        }

        function updateRowTotal(row) {
            const qtdInput = row.querySelector('.item-quantidade');
            const valorInput = row.querySelector('.item-valor');
            const totalText = row.querySelector('.item-total-text');

            const qtd = parseValue(qtdInput.value);
            const valor = parseValue(valorInput.value);
            const total = qtd * valor;

            totalText.textContent = formatBRL(total);
            return total;
        }

        function updateGrandTotal() {
            let total = 0;

            document.querySelectorAll('.item-row').forEach(function(row) {
                total += updateRowTotal(row);
            });

            totalGeralView.textContent = formatBRL(total);
        }

        function bindRowEvents(row) {
            const qtdInput = row.querySelector('.item-quantidade');
            const valorInput = row.querySelector('.item-valor');
            const removeBtn = row.querySelector('.btn-remove-item');

            [qtdInput, valorInput].forEach(function(el) {
                el.addEventListener('input', updateGrandTotal);
                el.addEventListener('change', updateGrandTotal);
            });

            removeBtn.addEventListener('click', function() {
                const rows = document.querySelectorAll('.item-row');

                if (rows.length <= 1) {
                    row.querySelector('.item-produto').value = '';
                    row.querySelector('.item-quantidade').value = '1.00';
                    row.querySelector('.item-valor').value = '0.00';
                    updateGrandTotal();
                    return;
                }

                row.remove();
                updateGrandTotal();
            });
        }

        function createRow() {
            const tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.innerHTML = `
            <td>
                <input type="text" name="item_produto[]" class="form-control item-produto" required>
            </td>
            <td>
                <input type="number" name="item_quantidade[]" class="form-control item-quantidade" value="1.00" min="0.01" step="0.01" required>
            </td>
            <td>
                <input type="number" name="item_valor_unitario[]" class="form-control item-valor" value="0.00" min="0" step="0.01" required>
            </td>
            <td class="td-right">
                <span class="item-total-badge item-total-text">R$ 0,00</span>
            </td>
            <td class="td-center">
                <button type="button" class="btn-remove-item" title="Remover item">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

            tbody.appendChild(tr);
            bindRowEvents(tr);
            updateGrandTotal();

            const produto = tr.querySelector('.item-produto');
            if (produto) {
                produto.focus();
            }
        }

        btnAdicionar.addEventListener('click', createRow);

        document.querySelectorAll('.item-row').forEach(bindRowEvents);
        updateGrandTotal();
    })();
</script>

<?php include 'views/layout/footer.php'; ?>