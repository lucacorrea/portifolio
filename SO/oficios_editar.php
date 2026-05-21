<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
admin_check();

$id = (int)($_GET['id'] ?? 0);

function parse_oficio_money($valor) {
    $valor = trim((string)$valor);

    if ($valor === '') {
        return null;
    }

    $valor = str_replace([' ', '.'], ['', ''], $valor);
    $valor = str_replace(',', '.', $valor);

    if (!is_numeric($valor)) {
        throw new Exception("Informe um valor monetário válido.");
    }

    $valor = (float)$valor;

    if ($valor < 0) {
        throw new Exception("Valores monetários não podem ser negativos.");
    }

    return $valor;
}

function format_money_input($valor) {
    if ($valor === null || $valor === '') {
        return '';
    }

    return number_format((float)$valor, 2, ',', '.');
}

function format_quantity_input($valor) {
    return rtrim(rtrim(number_format((float)$valor, 2, '.', ''), '0'), '.');
}

function parse_datetime_local_required($valor, string $campo): string {
    $valor = trim((string)$valor);

    if ($valor === '') {
        throw new Exception("Informe {$campo}.");
    }

    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $valor);
    if (!$dt || $dt->format('Y-m-d\TH:i') !== $valor) {
        throw new Exception("Informe uma data válida para {$campo}.");
    }

    return $dt->format('Y-m-d H:i:s');
}

function parse_oficio_datetime($valor): string {
    return parse_datetime_local_required($valor, 'a data do ofício');
}

function format_datetime_local_input($valor): string {
    if (empty($valor)) {
        return '';
    }

    $timestamp = strtotime((string)$valor);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
}

$stmt = $pdo->prepare("
    SELECT o.*, s.nome as secretaria
    FROM oficios o
    JOIN secretarias s ON o.secretaria_id = s.id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$oficio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$oficio) {
    die("Solicitação não encontrada.");
}

$secretarias = $pdo->query("SELECT id, nome FROM secretarias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$fornecedores = $pdo->query("SELECT id, nome, cnpj FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$stmt_aquisicao = $pdo->prepare("
    SELECT a.id, a.numero_aq, a.status, a.criado_em, a.fornecedor_id, f.nome AS fornecedor
    FROM aquisicoes a
    LEFT JOIN fornecedores f ON f.id = a.fornecedor_id
    WHERE a.oficio_id = ?
    ORDER BY a.id ASC
");
$stmt_aquisicao->execute([$id]);
$aquisicoes_vinculadas = $stmt_aquisicao->fetchAll(PDO::FETCH_ASSOC);
$total_aquisicoes_vinculadas = count($aquisicoes_vinculadas);

$stmt_items = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ? ORDER BY id ASC");
$stmt_items->execute([$id]);
$items_existentes = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$items_existentes_by_id = [];
foreach ($items_existentes as $item_existente) {
    $items_existentes_by_id[(int)$item_existente['id']] = $item_existente;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $numero_manual = mb_strtoupper(trim($_POST['numero_oficio'] ?? ''), 'UTF-8');
        $secretaria_id = (int)($_POST['secretaria_id'] ?? 0);
        $local = trim((string)($_POST['local'] ?? ''));
        $criado_em = parse_oficio_datetime($_POST['criado_em'] ?? '');
        $valor_orcamento = parse_oficio_money($_POST['valor_orcamento'] ?? '');
        $produtos = $_POST['produtos'] ?? [];
        $aquisicoes_datas = $_POST['aquisicoes_datas'] ?? [];
        $aquisicoes_fornecedores = $_POST['aquisicoes_fornecedores'] ?? [];

        if ($numero_manual === '') {
            throw new Exception("O número do ofício é obrigatório.");
        }

        $secretarias_validas = array_map('intval', array_column($secretarias, 'id'));
        $fornecedores_validos = array_map('intval', array_column($fornecedores, 'id'));
        if ($secretaria_id <= 0 || !in_array($secretaria_id, $secretarias_validas, true)) {
            throw new Exception("Selecione uma secretaria válida.");
        }

        if ($local === '') {
            throw new Exception("Informe o local do ofício.");
        }

        $aquisicoes_datas_sanitizadas = [];
        $aquisicoes_fornecedores_sanitizados = [];
        foreach ($aquisicoes_vinculadas as $aq) {
            $aq_id = (int)$aq['id'];

            if (!array_key_exists($aq_id, $aquisicoes_datas)) {
                $aquisicoes_datas[$aq_id] = format_datetime_local_input($aq['criado_em'] ?? '');
            }

            $aquisicoes_datas_sanitizadas[$aq_id] = parse_datetime_local_required(
                $aquisicoes_datas[$aq_id],
                "a data da aquisição {$aq['numero_aq']}"
            );

            if (!array_key_exists($aq_id, $aquisicoes_fornecedores)) {
                $aquisicoes_fornecedores[$aq_id] = (int)($aq['fornecedor_id'] ?? 0);
            }

            $fornecedor_id_aquisicao = (int)$aquisicoes_fornecedores[$aq_id];
            if ($fornecedor_id_aquisicao <= 0 || !in_array($fornecedor_id_aquisicao, $fornecedores_validos, true)) {
                throw new Exception("Selecione um fornecedor válido para a aquisição {$aq['numero_aq']}.");
            }

            $aquisicoes_fornecedores_sanitizados[$aq_id] = $fornecedor_id_aquisicao;
        }

        $stmt_check = $pdo->prepare("SELECT id FROM oficios WHERE numero = ? AND id <> ?");
        $stmt_check->execute([$numero_manual, $id]);
        if ($stmt_check->fetch()) {
            throw new Exception("O número de ofício '{$numero_manual}' já está cadastrado em outra solicitação.");
        }

        $itens_sanitizados = [];
        $total_calculado = 0;

        foreach ($produtos as $idx => $p) {
            $item_id_original = (int)($p['id'] ?? 0);
            $nome = trim((string)($p['nome'] ?? ''));

            if ($nome === '') {
                continue;
            }

            if ($item_id_original > 0 && !isset($items_existentes_by_id[$item_id_original])) {
                throw new Exception("Um dos itens enviados não pertence a esta solicitação.");
            }

            $qtd = (float)str_replace(',', '.', (string)($p['qtd'] ?? 0));
            $unidade = trim((string)($p['unidade'] ?? 'UN'));
            $valor_unitario = parse_oficio_money($p['valor'] ?? '0');
            $valor_unitario = $valor_unitario ?? 0;

            if ($qtd <= 0) {
                throw new Exception("A quantidade do item " . ($idx + 1) . " deve ser maior que zero.");
            }

            if ($unidade === '') {
                $unidade = 'UN';
            }

            $total_calculado += ($qtd * $valor_unitario);

            $itens_sanitizados[] = [
                'id_original' => $item_id_original,
                'produto' => $nome,
                'quantidade' => $qtd,
                'unidade' => $unidade,
                'valor_unitario' => $valor_unitario,
            ];
        }

        if (empty($itens_sanitizados)) {
            throw new Exception("Informe pelo menos um item para a solicitação.");
        }

        if ($valor_orcamento !== null && $valor_orcamento > 0 && abs($total_calculado - $valor_orcamento) > 0.02) {
            throw new Exception("O valor total dos itens deve ser exatamente igual ao orçamento previsto de R$ " . number_format($valor_orcamento, 2, ',', '.'));
        }

        if ($total_aquisicoes_vinculadas > 1) {
            foreach ($itens_sanitizados as $item) {
                if ((int)$item['id_original'] <= 0) {
                    throw new Exception("Este ofício possui múltiplas aquisições. Para adicionar item novo, ajuste a aquisição específica para definir qual fornecedor irá atendê-lo.");
                }
            }
        }

        $pdo->beginTransaction();

        $novo_status = $oficio['status'] === 'PENDENTE_ITENS' ? 'ENVIADO' : $oficio['status'];

        $stmt_update = $pdo->prepare("
            UPDATE oficios
            SET numero = ?, secretaria_id = ?, local = ?, criado_em = ?, valor_orcamento = ?, status = ?
            WHERE id = ?
        ");
        $stmt_update->execute([$numero_manual, $secretaria_id, $local, $criado_em, $valor_orcamento, $novo_status, $id]);

        if (!empty($aquisicoes_datas_sanitizadas) || !empty($aquisicoes_fornecedores_sanitizados)) {
            $stmt_update_aquisicao_dados = $pdo->prepare("
                UPDATE aquisicoes
                SET criado_em = ?, fornecedor_id = ?
                WHERE id = ? AND oficio_id = ?
            ");

            foreach ($aquisicoes_datas_sanitizadas as $aquisicao_id => $data_aquisicao) {
                $stmt_update_aquisicao_dados->execute([
                    $data_aquisicao,
                    (int)$aquisicoes_fornecedores_sanitizados[$aquisicao_id],
                    (int)$aquisicao_id,
                    $id,
                ]);
            }
        }

        $pdo->prepare("DELETE FROM itens_oficio WHERE oficio_id = ?")->execute([$id]);

        $stmt_item = $pdo->prepare("
            INSERT INTO itens_oficio (oficio_id, produto, quantidade, unidade, valor_unitario)
            VALUES (?, ?, ?, ?, ?)
        ");

        $item_id_map = [];
        $itens_reinseridos = [];

        foreach ($itens_sanitizados as $item) {
            $stmt_item->execute([
                $id,
                $item['produto'],
                $item['quantidade'],
                $item['unidade'],
                $item['valor_unitario'],
            ]);

            $novo_item_id = (int)$pdo->lastInsertId();
            $item['id_novo'] = $novo_item_id;
            $itens_reinseridos[] = $item;

            if ((int)$item['id_original'] > 0) {
                $item_id_map[(int)$item['id_original']] = $novo_item_id;
            }
        }

        if ($total_aquisicoes_vinculadas === 1) {
            $aquisicao_id = (int)$aquisicoes_vinculadas[0]['id'];

            $pdo->prepare("DELETE FROM itens_aquisicao WHERE aquisicao_id = ?")->execute([$aquisicao_id]);

            $stmt_item_aquisicao = $pdo->prepare("
                INSERT INTO itens_aquisicao (aquisicao_id, oficio_item_id, produto, quantidade, valor_unitario)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($itens_reinseridos as $item) {
                $stmt_item_aquisicao->execute([
                    $aquisicao_id,
                    (int)$item['id_novo'],
                    $item['produto'],
                    $item['quantidade'],
                    $item['valor_unitario'],
                ]);
            }

            $pdo->prepare("UPDATE aquisicoes SET valor_total = ? WHERE id = ?")
                ->execute([$total_calculado, $aquisicao_id]);
        } elseif ($total_aquisicoes_vinculadas > 1) {
            $stmt_aq_items = $pdo->prepare("
                SELECT ia.*
                FROM itens_aquisicao ia
                JOIN aquisicoes a ON a.id = ia.aquisicao_id
                WHERE a.oficio_id = ?
                ORDER BY ia.id ASC
            ");
            $stmt_aq_items->execute([$id]);
            $itens_aquisicao = $stmt_aq_items->fetchAll(PDO::FETCH_ASSOC);

            foreach ($itens_aquisicao as $item_aq) {
                if (empty($item_aq['oficio_item_id'])) {
                    throw new Exception("As aquisições deste ofício não possuem vínculo técnico com os itens originais. Edite cada aquisição individualmente para evitar inconsistência entre fornecedores.");
                }
            }

            $itens_por_original = [];
            foreach ($itens_reinseridos as $item) {
                $itens_por_original[(int)$item['id_original']] = $item;
            }

            $stmt_update_item_aq = $pdo->prepare("
                UPDATE itens_aquisicao
                SET oficio_item_id = ?, produto = ?, quantidade = ?, valor_unitario = ?
                WHERE id = ?
            ");
            $stmt_delete_item_aq = $pdo->prepare("DELETE FROM itens_aquisicao WHERE id = ?");

            foreach ($itens_aquisicao as $item_aq) {
                $old_item_id = (int)$item_aq['oficio_item_id'];

                if (!isset($itens_por_original[$old_item_id], $item_id_map[$old_item_id])) {
                    $stmt_delete_item_aq->execute([(int)$item_aq['id']]);
                    continue;
                }

                $item = $itens_por_original[$old_item_id];
                $stmt_update_item_aq->execute([
                    (int)$item_id_map[$old_item_id],
                    $item['produto'],
                    $item['quantidade'],
                    $item['valor_unitario'],
                    (int)$item_aq['id'],
                ]);
            }

            $stmt_recalc_aq = $pdo->prepare("
                UPDATE aquisicoes
                SET valor_total = (
                    SELECT COALESCE(SUM(quantidade * valor_unitario), 0)
                    FROM itens_aquisicao
                    WHERE aquisicao_id = ?
                )
                WHERE id = ?
            ");

            foreach ($aquisicoes_vinculadas as $aq) {
                $stmt_recalc_aq->execute([(int)$aq['id'], (int)$aq['id']]);
            }
        }

        log_action($pdo, "EDITAR_OFICIO", "Solicitação {$oficio['numero']} editada para {$numero_manual}");
        $pdo->commit();

        $msg = "Solicitação {$numero_manual} atualizada com sucesso.";
        if ($total_aquisicoes_vinculadas === 1) {
            $msg .= " A aquisição " . $aquisicoes_vinculadas[0]['numero_aq'] . " também foi sincronizada.";
        } elseif ($total_aquisicoes_vinculadas > 1) {
            $msg .= " As aquisições vinculadas também foram sincronizadas.";
        }

        flash_message('success', $msg);
        header("Location: oficios_visualizar.php?id={$id}");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = "Erro ao editar: " . $e->getMessage();
    }
}

$stmt_resumo = $pdo->prepare("
    SELECT
        TRIM(produto) AS produto,
        COALESCE(NULLIF(TRIM(unidade), ''), 'UN') AS unidade,
        COUNT(*) AS total_registros,
        SUM(quantidade) AS quantidade_total,
        SUM(quantidade * valor_unitario) AS valor_total_produto
    FROM itens_oficio
    WHERE oficio_id = ?
    GROUP BY TRIM(produto), COALESCE(NULLIF(TRIM(unidade), ''), 'UN')
    ORDER BY produto ASC
");
$stmt_resumo->execute([$id]);
$resumo_produtos = $stmt_resumo->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items_form = [];
    foreach (($_POST['produtos'] ?? []) as $p) {
        $items_form[] = [
            'id' => (int)($p['id'] ?? 0),
            'produto' => $p['nome'] ?? '',
            'quantidade_input' => $p['qtd'] ?? '1',
            'unidade' => $p['unidade'] ?? 'UN',
            'valor_input' => $p['valor'] ?? '',
        ];
    }
} else {
    $items_form = !empty($items_existentes)
        ? $items_existentes
        : [['produto' => '', 'quantidade' => 1, 'unidade' => 'UN', 'valor_unitario' => 0]];
}

$numero_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['numero_oficio'] ?? '')
    : ($oficio['numero'] ?? '');

$secretaria_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int)($_POST['secretaria_id'] ?? 0)
    : (int)($oficio['secretaria_id'] ?? 0);

$local_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['local'] ?? '')
    : ($oficio['local'] ?? '');

$criado_em_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['criado_em'] ?? '')
    : format_datetime_local_input($oficio['criado_em'] ?? '');

$secretaria_nome_atual = $oficio['secretaria'] ?? '';
foreach ($secretarias as $sec) {
    if ((int)$sec['id'] === (int)$secretaria_value) {
        $secretaria_nome_atual = $sec['nome'];
        break;
    }
}

$criado_em_label = '-';
$criado_em_timestamp = strtotime(str_replace('T', ' ', (string)$criado_em_value));
if ($criado_em_timestamp !== false) {
    $criado_em_label = date('d/m/Y H:i', $criado_em_timestamp);
}

$aquisicoes_datas_values = [];
$aquisicoes_fornecedores_values = [];
foreach ($aquisicoes_vinculadas as $aq) {
    $aq_id = (int)$aq['id'];
    $aquisicoes_datas_values[$aq_id] = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_POST['aquisicoes_datas'][$aq_id] ?? format_datetime_local_input($aq['criado_em'] ?? ''))
        : format_datetime_local_input($aq['criado_em'] ?? '');

    $aquisicoes_fornecedores_values[$aq_id] = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? (int)($_POST['aquisicoes_fornecedores'][$aq_id] ?? ($aq['fornecedor_id'] ?? 0))
        : (int)($aq['fornecedor_id'] ?? 0);
}

$orcamento_value = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['valor_orcamento'] ?? '')
    : format_money_input($oficio['valor_orcamento'] ?? null);

$page_title = "Editar Solicitação - " . $oficio['numero'];
include 'views/layout/header.php';
?>

<style>
    .edit-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .oficio-edit-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .oficio-edit-span-2 {
        grid-column: span 2;
    }

    .form-section-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin: 0 0 1rem;
        color: var(--text-dark);
        font-size: 1rem;
        font-weight: 800;
    }

    .aquisicoes-date-panel {
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 1.25rem;
        margin: 0 0 1.5rem;
        background: #fff;
    }

    .aquisicoes-section-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin: 0 0 1rem;
        flex-wrap: wrap;
    }

    .aquisicoes-date-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin: 0;
        color: var(--text-dark);
        font-size: 1rem;
        font-weight: 800;
    }

    .aquisicoes-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        padding: .35rem .75rem;
        border-radius: 999px;
        background: #eef2ff;
        color: #3156a3;
        font-size: .78rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .aquisicoes-date-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: .85rem;
    }

    .aquisicao-date-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1rem;
        background: #f8fafc;
        display: grid;
        grid-template-columns: minmax(150px, .8fr) minmax(280px, 1.7fr) minmax(220px, 1fr) minmax(150px, auto);
        gap: 1rem;
        align-items: center;
    }

    .aquisicao-readonly {
        background: #fff;
        font-weight: 900;
        color: var(--text-dark);
    }

    .aquisicao-status-pill {
        min-height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: .65rem .8rem;
        border-radius: 10px;
        background: #eef2f7;
        color: #475569;
        font-size: .78rem;
        font-weight: 900;
        text-transform: uppercase;
        text-align: center;
        white-space: nowrap;
    }

    .item-row {
        display: grid;
        grid-template-columns: 80px 2fr 1fr 1fr 1fr 1.2fr auto;
        gap: 1rem;
        margin-bottom: 1rem;
        align-items: end;
        padding: 1rem;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        background: #fff;
    }

    .budget-info {
        background: #f1f5f9;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }

    .total-calc {
        font-size: 1.25rem;
        font-weight: 700;
    }

    .diff-warning {
        color: #dc3545;
    }

    .diff-ok {
        color: #198754;
    }

    .item-seq {
        text-align: center;
        font-weight: 800;
        background: #f8fafc;
    }

    .item-total {
        background: #f8fafc;
        font-weight: 800;
        color: #198754;
        text-align: right;
    }

    .edit-actions {
        text-align: right;
        border-top: 1px solid var(--border-color);
        padding-top: 2rem;
    }

    @media (max-width: 1200px) {
        .item-row {
            grid-template-columns: 70px 1.8fr 1fr 1fr 1fr 1fr auto;
        }
    }

    @media (max-width: 992px) {
        .oficio-edit-grid,
        .item-row {
            grid-template-columns: 1fr;
        }

        .oficio-edit-span-2 {
            grid-column: span 1;
        }

        .aquisicoes-date-grid {
            grid-template-columns: 1fr;
        }

        .aquisicao-date-card {
            grid-template-columns: 1fr;
        }

        .budget-info {
            align-items: flex-start;
            flex-direction: column;
        }

        .edit-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="card">
    <div class="card-body">
        <div class="edit-header">
            <h3 style="margin: 0;">
                <i class="fas fa-edit"></i> Editar Solicitação - <?php echo htmlspecialchars($oficio['numero'], ENT_QUOTES, 'UTF-8'); ?>
            </h3>
            <a href="oficios_lista.php" class="btn btn-outline btn-sm">Voltar</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($total_aquisicoes_vinculadas === 1): ?>
            <div class="alert alert-warning">
                Esta solicitação já possui a aquisição
                <strong><?php echo htmlspecialchars($aquisicoes_vinculadas[0]['numero_aq'], ENT_QUOTES, 'UTF-8'); ?></strong>
                gerada. Ao salvar, dados da aquisição, itens e valor total serão atualizados junto com o ofício.
            </div>
        <?php elseif ($total_aquisicoes_vinculadas > 1): ?>
            <div class="alert alert-warning">
                Esta solicitação já possui
                <strong><?php echo (int)$total_aquisicoes_vinculadas; ?> aquisições</strong>
                geradas. Ao salvar, fornecedor/data e itens já vinculados serão atualizados nas respectivas aquisições. Itens novos devem ser incluídos na aquisição específica do fornecedor.
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="items-form">
            <h4 class="form-section-title">
                <i class="fas fa-file-alt"></i> Dados do Ofício
            </h4>

            <div class="oficio-edit-grid">
                <div class="form-group">
                    <label class="form-label">Número do Ofício <span style="color:red">*</span></label>
                    <input
                        type="text"
                        name="numero_oficio"
                        class="form-control"
                        value="<?php echo htmlspecialchars($numero_value, ENT_QUOTES, 'UTF-8'); ?>"
                        oninput="this.value = this.value.toUpperCase()"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label">Secretaria <span style="color:red">*</span></label>
                    <select name="secretaria_id" class="form-control" required>
                        <option value="">Selecione a secretaria</option>
                        <?php foreach ($secretarias as $sec): ?>
                            <option
                                value="<?php echo (int)$sec['id']; ?>"
                                <?php echo (int)$secretaria_value === (int)$sec['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sec['nome'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Data do Ofício <span style="color:red">*</span></label>
                    <input
                        type="datetime-local"
                        name="criado_em"
                        class="form-control"
                        value="<?php echo htmlspecialchars($criado_em_value, ENT_QUOTES, 'UTF-8'); ?>"
                        required>
                </div>

                <div class="form-group oficio-edit-span-2">
                    <label class="form-label">Local <span style="color:red">*</span></label>
                    <input
                        type="text"
                        name="local"
                        class="form-control"
                        placeholder="Ex: Secretaria Municipal de Administração"
                        value="<?php echo htmlspecialchars($local_value, ENT_QUOTES, 'UTF-8'); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label">Valor do Orçamento</label>
                    <input
                        type="text"
                        name="valor_orcamento"
                        id="valor-orcamento"
                        class="form-control"
                        placeholder="0,00"
                        value="<?php echo htmlspecialchars($orcamento_value, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <?php if (!empty($aquisicoes_vinculadas)): ?>
                <div class="aquisicoes-date-panel">
                    <div class="aquisicoes-section-head">
                        <h4 class="aquisicoes-date-title">
                            <i class="fas fa-shopping-bag"></i> Aquisições Vinculadas
                        </h4>
                        <span class="aquisicoes-count">
                            <?php echo (int)$total_aquisicoes_vinculadas; ?> aquisição(ões)
                        </span>
                    </div>

                    <div class="aquisicoes-date-grid">
                        <?php foreach ($aquisicoes_vinculadas as $aq): ?>
                            <?php $aq_id = (int)$aq['id']; ?>
                            <div class="aquisicao-date-card">
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Nº Aquisição</label>
                                    <input
                                        type="text"
                                        class="form-control aquisicao-readonly"
                                        value="<?php echo htmlspecialchars($aq['numero_aq'], ENT_QUOTES, 'UTF-8'); ?>"
                                        readonly>
                                </div>

                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Fornecedor</label>
                                    <select
                                        name="aquisicoes_fornecedores[<?php echo $aq_id; ?>]"
                                        class="form-control"
                                        required>
                                        <option value="">Selecione o fornecedor</option>
                                        <?php foreach ($fornecedores as $fornecedor): ?>
                                            <option
                                                value="<?php echo (int)$fornecedor['id']; ?>"
                                                <?php echo (int)($aquisicoes_fornecedores_values[$aq_id] ?? 0) === (int)$fornecedor['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($fornecedor['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if (!empty($fornecedor['cnpj'])): ?>
                                                    (<?php echo htmlspecialchars($fornecedor['cnpj'], ENT_QUOTES, 'UTF-8'); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Data da Aquisição</label>
                                    <input
                                        type="datetime-local"
                                        name="aquisicoes_datas[<?php echo $aq_id; ?>]"
                                        class="form-control"
                                        value="<?php echo htmlspecialchars($aquisicoes_datas_values[$aq_id] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        required>
                                </div>

                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Status</label>
                                    <div class="aquisicao-status-pill">
                                        <?php echo htmlspecialchars($aq['status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="budget-info">
                <div>
                    <span class="text-muted">Secretaria:</span>
                    <strong><?php echo htmlspecialchars($secretaria_nome_atual, ENT_QUOTES, 'UTF-8'); ?></strong><br>

                    <span class="text-muted">Data:</span>
                    <strong><?php echo htmlspecialchars($criado_em_label, ENT_QUOTES, 'UTF-8'); ?></strong><br>

                    <span class="text-muted">Local:</span>
                    <strong><?php echo htmlspecialchars($local_value !== '' ? $local_value : '-', ENT_QUOTES, 'UTF-8'); ?></strong><br>

                    <span class="text-muted">Status atual:</span>
                    <strong><?php echo htmlspecialchars($oficio['status'], ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>

                <div style="text-align: right;">
                    <span class="text-muted">Total Atual dos Itens:</span><br>
                    <span id="total-itens" class="total-calc">R$ 0,00</span>
                </div>
            </div>

            <?php if (!empty($resumo_produtos)): ?>
                <div class="card" style="margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
                    <div class="card-body">
                        <h4 style="margin-bottom: 1rem;">
                            <i class="fas fa-chart-bar"></i> Resumo dos Produtos
                        </h4>

                        <div style="overflow-x:auto;">
                            <table class="table" style="width:100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background:#f8fafc;">
                                        <th style="padding: 12px; text-align:left;">Produto</th>
                                        <th style="padding: 12px; text-align:center;">Unidade</th>
                                        <th style="padding: 12px; text-align:center;">Qtd. de Lançamentos</th>
                                        <th style="padding: 12px; text-align:center;">Quantidade Total</th>
                                        <th style="padding: 12px; text-align:right;">Valor Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resumo_produtos as $rp): ?>
                                        <tr style="border-top:1px solid #e5e7eb;">
                                            <td style="padding: 12px; font-weight:600;">
                                                <?php echo htmlspecialchars($rp['produto'], ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td style="padding: 12px; text-align:center;">
                                                <?php echo htmlspecialchars($rp['unidade'], ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td style="padding: 12px; text-align:center; font-weight:700;">
                                                <?php echo (int)$rp['total_registros']; ?>
                                            </td>
                                            <td style="padding: 12px; text-align:center; font-weight:700;">
                                                <?php echo number_format((float)$rp['quantidade_total'], 2, ',', '.'); ?>
                                            </td>
                                            <td style="padding: 12px; text-align:right; font-weight:700; color:#198754;">
                                                <?php echo format_money((float)$rp['valor_total_produto']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <h4 class="form-section-title">
                <i class="fas fa-boxes"></i> Itens do Ofício
            </h4>

            <div id="items-container">
                <?php foreach ($items_form as $idx => $it): ?>
                    <?php
                    $qtd_input = $it['quantidade_input'] ?? format_quantity_input($it['quantidade'] ?? 1);
                    $valor_input = $it['valor_input'] ?? format_money_input($it['valor_unitario'] ?? 0);
                    $qtd_item = (float)str_replace(',', '.', (string)$qtd_input);
                    try {
                        $valor_unit_item = parse_oficio_money($valor_input) ?? 0;
                    } catch (Exception $e) {
                        $valor_unit_item = 0;
                    }
                    $valor_total_item = $qtd_item * $valor_unit_item;
                    ?>
                    <div class="item-row">
                        <input type="hidden" name="produtos[<?php echo $idx; ?>][id]" value="<?php echo (int)($it['id'] ?? 0); ?>">

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Nº</label>
                            <input type="text" class="form-control item-seq" value="<?php echo $idx + 1; ?>" readonly>
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Nome do Item</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][nome]"
                                class="form-control"
                                required
                                placeholder="Ex: Papel A4"
                                value="<?php echo htmlspecialchars($it['produto'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Quantidade</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="produtos[<?php echo $idx; ?>][qtd]"
                                class="form-control item-qtd"
                                required
                                value="<?php echo htmlspecialchars((string)$qtd_input, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Unidade</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][unidade]"
                                class="form-control"
                                value="<?php echo htmlspecialchars($it['unidade'] ?? 'UN', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Valor Unitário</label>
                            <input
                                type="text"
                                name="produtos[<?php echo $idx; ?>][valor]"
                                class="form-control item-valor"
                                required
                                placeholder="0,00"
                                value="<?php echo htmlspecialchars($valor_input, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Total do Item</label>
                            <input
                                type="text"
                                class="form-control item-total"
                                value="R$ <?php echo number_format($valor_total_item, 2, ',', '.'); ?>"
                                readonly>
                        </div>

                        <div style="margin-bottom: 5px;">
                            <button
                                type="button"
                                class="btn btn-outline btn-sm remove-item"
                                style="color:red; border-color:#ff000033;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button
                type="button"
                class="btn btn-outline"
                id="add-item"
                style="margin-bottom: 2rem;">
                <i class="fas fa-plus"></i> Adicionar Mais Itens
            </button>

            <div class="edit-actions">
                <button
                    type="submit"
                    class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('items-container');
    const totalDisplay = document.getElementById('total-itens');
    const budgetInput = document.getElementById('valor-orcamento');
    const addButton = document.getElementById('add-item');
    const form = document.getElementById('items-form');

    function parseValorBR(valor) {
        if (!valor) return 0;
        let v = String(valor).trim();
        v = v.replace(/\s/g, '');
        v = v.replace(/\./g, '');
        v = v.replace(',', '.');
        return parseFloat(v) || 0;
    }

    function formatMoneyBR(valor) {
        return 'R$ ' + Number(valor || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function renumberItems() {
        const rows = container.querySelectorAll('.item-row');
        rows.forEach((row, index) => {
            const seqInput = row.querySelector('.item-seq');
            if (seqInput) {
                seqInput.value = index + 1;
            }

            row.querySelectorAll('input[name^="produtos["]').forEach(input => {
                input.name = input.name.replace(/produtos\[\d+\]/, `produtos[${index}]`);
            });
        });
    }

    function updateItemTotals() {
        container.querySelectorAll('.item-row').forEach(row => {
            const qtd = parseFloat(row.querySelector('.item-qtd')?.value) || 0;
            const valorUnit = parseValorBR(row.querySelector('.item-valor')?.value);
            const totalItem = qtd * valorUnit;
            const totalField = row.querySelector('.item-total');

            if (totalField) {
                totalField.value = formatMoneyBR(totalItem);
            }
        });
    }

    function calculateTotal() {
        let total = 0;
        const orcamentoPrevisto = parseValorBR(budgetInput?.value || '');

        container.querySelectorAll('.item-row').forEach(row => {
            const qtd = parseFloat(row.querySelector('.item-qtd')?.value) || 0;
            const valorUnit = parseValorBR(row.querySelector('.item-valor')?.value);
            total += (qtd * valorUnit);
        });

        totalDisplay.textContent = formatMoneyBR(total);

        if (orcamentoPrevisto > 0) {
            if (Math.abs(total - orcamentoPrevisto) > 0.02) {
                totalDisplay.classList.add('diff-warning');
                totalDisplay.classList.remove('diff-ok');
            } else {
                totalDisplay.classList.add('diff-ok');
                totalDisplay.classList.remove('diff-warning');
            }
        } else {
            totalDisplay.classList.remove('diff-warning', 'diff-ok');
        }

        updateItemTotals();
    }

    container.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-valor')) {
            e.target.value = e.target.value.replace(/[^\d,.\s]/g, '');
        }
        calculateTotal();
    });

    if (budgetInput) {
        budgetInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^\d,.\s]/g, '');
            calculateTotal();
        });
    }

    if (addButton) {
        addButton.addEventListener('click', function() {
            const index = container.querySelectorAll('.item-row').length;
            const row = document.createElement('div');
            row.className = 'item-row';
            row.innerHTML = `
                <input type="hidden" name="produtos[${index}][id]" value="0">

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Nº</label>
                    <input type="text" class="form-control item-seq" value="${index + 1}" readonly>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Nome do Item</label>
                    <input type="text" name="produtos[${index}][nome]" class="form-control" required placeholder="Ex: Papel A4">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Quantidade</label>
                    <input type="number" step="0.01" min="0.01" name="produtos[${index}][qtd]" class="form-control item-qtd" required value="1">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Unidade</label>
                    <input type="text" name="produtos[${index}][unidade]" class="form-control" value="UN">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Valor Unitário</label>
                    <input type="text" name="produtos[${index}][valor]" class="form-control item-valor" required placeholder="0,00">
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label">Total do Item</label>
                    <input type="text" class="form-control item-total" value="R$ 0,00" readonly>
                </div>

                <div style="margin-bottom: 5px;">
                    <button type="button" class="btn btn-outline btn-sm remove-item" style="color:red; border-color:#ff000033;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;

            container.appendChild(row);
            renumberItems();
            calculateTotal();
        });
    }

    container.addEventListener('click', function(e) {
        if (e.target.closest('.remove-item')) {
            const rows = container.querySelectorAll('.item-row');
            if (rows.length > 1) {
                e.target.closest('.item-row').remove();
                renumberItems();
                calculateTotal();
            }
        }
    });

    form.addEventListener('submit', function(e) {
        renumberItems();

        const orcamentoPrevisto = parseValorBR(budgetInput?.value || '');
        if (orcamentoPrevisto > 0) {
            let total = 0;

            container.querySelectorAll('.item-row').forEach(row => {
                const qtd = parseFloat(row.querySelector('.item-qtd')?.value) || 0;
                const valorUnit = parseValorBR(row.querySelector('.item-valor')?.value);
                total += (qtd * valorUnit);
            });

            if (Math.abs(total - orcamentoPrevisto) > 0.02) {
                e.preventDefault();
                alert("Bloqueado: O valor total atual dos itens não corresponde ao Valor do Orçamento Previsto.");
                return false;
            }
        }
    });

    renumberItems();
    calculateTotal();
});
</script>

<?php include 'views/layout/footer.php'; ?>
