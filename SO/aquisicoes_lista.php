<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$page_title = "Lista de Aquisições";

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function format_date_excel($date)
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime((string)$date);
    if ($timestamp === false) {
        return '-';
    }

    return date('d/m/Y H:i', $timestamp);
}

$busca = trim((string)($_GET['busca'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$secretaria_id = trim((string)($_GET['secretaria_id'] ?? ''));
$fornecedor_id = trim((string)($_GET['fornecedor_id'] ?? ''));
$data_inicio = trim((string)($_GET['data_inicio'] ?? ''));
$data_fim = trim((string)($_GET['data_fim'] ?? ''));
$export = trim((string)($_GET['export'] ?? ''));
$data_inicio_valida = $data_inicio !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio);
$data_fim_valida = $data_fim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim);

$where_parts = ["1=1"];
$params = [];
$status_options = ['AGUARDANDO ENTREGA', 'FINALIZADO'];

if ($status !== '' && in_array($status, $status_options, true)) {
    $where_parts[] = "a.status = :status";
    $params[':status'] = $status;
}

if ($busca !== '') {
    $where_parts[] = "(
        a.numero_aq LIKE :busca_aq
        OR o.numero LIKE :busca_oficio
        OR s.nome LIKE :busca_secretaria
        OR f.nome LIKE :busca_fornecedor
    )";
    $busca_like = '%' . $busca . '%';
    $params[':busca_aq'] = $busca_like;
    $params[':busca_oficio'] = $busca_like;
    $params[':busca_secretaria'] = $busca_like;
    $params[':busca_fornecedor'] = $busca_like;
}

if ($secretaria_id !== '') {
    $where_parts[] = "o.secretaria_id = :secretaria_id";
    $params[':secretaria_id'] = (int)$secretaria_id;
}

if ($fornecedor_id !== '') {
    $where_parts[] = "a.fornecedor_id = :fornecedor_id";
    $params[':fornecedor_id'] = (int)$fornecedor_id;
}

if ($data_inicio_valida) {
    $where_parts[] = "a.criado_em >= :data_inicio";
    $params[':data_inicio'] = $data_inicio . ' 00:00:00';
}

if ($data_fim_valida) {
    $where_parts[] = "a.criado_em <= :data_fim";
    $params[':data_fim'] = $data_fim . ' 23:59:59';
}

$where = implode(' AND ', $where_parts);

$secretarias_list = $pdo->query("SELECT id, nome FROM secretarias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$fornecedores_list = $pdo->query("SELECT id, nome, cnpj FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$nome_secretaria_filtro = 'Todas';
if ($secretaria_id !== '') {
    foreach ($secretarias_list as $sec) {
        if ((string)$sec['id'] === (string)$secretaria_id) {
            $nome_secretaria_filtro = $sec['nome'];
            break;
        }
    }
}

$nome_fornecedor_filtro = 'Todos';
if ($fornecedor_id !== '') {
    foreach ($fornecedores_list as $fornecedor) {
        if ((string)$fornecedor['id'] === (string)$fornecedor_id) {
            $nome_fornecedor_filtro = $fornecedor['nome'];
            break;
        }
    }
}

$sql_select = "
    SELECT
        a.id,
        a.numero_aq,
        a.valor_total,
        a.status,
        a.criado_em,
        o.numero as oficio_num,
        s.nome as secretaria,
        f.nome as fornecedor
    FROM aquisicoes a
    JOIN oficios o ON a.oficio_id = o.id
    JOIN secretarias s ON o.secretaria_id = s.id
    JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE $where
";

$sql_order = "
    ORDER BY
        CAST(SUBSTRING_INDEX(REPLACE(REPLACE(UPPER(TRIM(a.numero_aq)), 'AQ-', ''), 'AQ', ''), '-', 1) AS UNSIGNED) ASC,
        CAST(SUBSTRING_INDEX(REPLACE(REPLACE(UPPER(TRIM(a.numero_aq)), 'AQ-', ''), 'AQ', ''), '-', -1) AS UNSIGNED) ASC,
        a.id ASC
";

if ($export === 'excel') {
    $stmt_export = $pdo->prepare($sql_select . $sql_order);
    $stmt_export->execute($params);
    $aquisicoes_export = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

    $total_export = 0;
    foreach ($aquisicoes_export as $aq_export) {
        $total_export += (float)$aq_export['valor_total'];
    }

    $periodo_texto = 'Todos';
    if ($data_inicio_valida || $data_fim_valida) {
        $inicio_txt = $data_inicio_valida ? date('d/m/Y', strtotime($data_inicio)) : '...';
        $fim_txt = $data_fim_valida ? date('d/m/Y', strtotime($data_fim)) : '...';
        $periodo_texto = $inicio_txt . ' até ' . $fim_txt;
    }

    $filename = 'relatorio_aquisicoes_' . date('Ymd_His') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
?>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; color: #1f2937; margin: 18px; }
            table { border-collapse: collapse; width: 100%; table-layout: fixed; }
            .sheet td, .sheet th { border: 1px solid #7c8aa5; padding: 7px 8px; vertical-align: middle; word-wrap: break-word; }
            .title-main { background: #dbeafe; color: #0f172a; font-size: 18px; font-weight: bold; text-align: center; padding: 12px; }
            .sub-info { background: #f8fafc; font-size: 11px; }
            .thead { background: #e5e7eb; font-weight: bold; text-align: center; }
            .summary-label { background: #f8fafc; font-weight: bold; text-align: center; }
            .summary-value { text-align: center; font-weight: bold; font-size: 14px; background: #ffffff; }
            .section-title { background: #1d4ed8; color: #fff; font-weight: bold; text-transform: uppercase; text-align: center; }
            .left { text-align: left; }
            .center { text-align: center; }
            .right { text-align: right; }
            .text-cell { mso-number-format: "\@"; }
            .total-row { background: #eef2ff; font-weight: bold; }
            .spacer td { border: none !important; height: 8px; padding: 0; background: transparent; }
        </style>
    </head>
    <body>
        <table class="sheet">
            <colgroup>
                <col style="width: 14%;">
                <col style="width: 14%;">
                <col style="width: 24%;">
                <col style="width: 24%;">
                <col style="width: 12%;">
                <col style="width: 12%;">
            </colgroup>

            <tr>
                <td colspan="6" class="title-main">RELATÓRIO DE AQUISIÇÕES</td>
            </tr>
            <tr>
                <td colspan="6" class="sub-info left"><strong>Gerado em:</strong> <?php echo date('d/m/Y H:i:s'); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="sub-info left"><strong>Busca:</strong> <?php echo $busca !== '' ? h($busca) : 'Todos'; ?></td>
                <td colspan="3" class="sub-info left"><strong>Status:</strong> <?php echo $status !== '' ? h($status) : 'Todos'; ?></td>
            </tr>
            <tr>
                <td colspan="3" class="sub-info left"><strong>Período:</strong> <?php echo h($periodo_texto); ?></td>
                <td colspan="3" class="sub-info left"><strong>Secretaria:</strong> <?php echo h($nome_secretaria_filtro); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="sub-info left"><strong>Fornecedor:</strong> <?php echo h($nome_fornecedor_filtro); ?></td>
                <td colspan="3" class="sub-info left"><strong>Registros:</strong> <?php echo count($aquisicoes_export); ?></td>
            </tr>

            <tr class="spacer"><td colspan="6"></td></tr>

            <tr>
                <td colspan="3" class="summary-label">TOTAL DE AQUISIÇÕES</td>
                <td colspan="3" class="summary-label">VALOR TOTAL</td>
            </tr>
            <tr>
                <td colspan="3" class="summary-value"><?php echo count($aquisicoes_export); ?></td>
                <td colspan="3" class="summary-value"><?php echo format_money($total_export); ?></td>
            </tr>

            <tr class="spacer"><td colspan="6"></td></tr>

            <tr>
                <td colspan="6" class="section-title">AQUISIÇÕES INDIVIDUAIS</td>
            </tr>
            <tr class="thead">
                <th>Nº Aquisição</th>
                <th>Nº Ofício</th>
                <th>Secretaria</th>
                <th>Fornecedor</th>
                <th>Data</th>
                <th>Valor</th>
            </tr>

            <?php if (!empty($aquisicoes_export)): ?>
                <?php foreach ($aquisicoes_export as $aq): ?>
                    <tr>
                        <td class="center text-cell"><?php echo h($aq['numero_aq']); ?></td>
                        <td class="center text-cell"><?php echo h($aq['oficio_num']); ?></td>
                        <td class="left text-cell"><?php echo h($aq['secretaria']); ?></td>
                        <td class="left text-cell"><?php echo h($aq['fornecedor']); ?></td>
                        <td class="center"><?php echo h(format_date_excel($aq['criado_em'])); ?></td>
                        <td class="right"><?php echo format_money($aq['valor_total']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="5" class="right">TOTAL GERAL</td>
                    <td class="right"><?php echo format_money($total_export); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="center">Nenhuma aquisição encontrada para os filtros selecionados.</td>
                </tr>
            <?php endif; ?>
        </table>
    </body>
    </html>
<?php
    exit;
}

// Configurações de Paginação
$itens_por_pagina = 6;
$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$pagina_atual = max(1, $pagina_atual);
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Contagem total para paginação
$stmt_count = $pdo->prepare("
    SELECT COUNT(*)
    FROM aquisicoes a
    JOIN oficios o ON a.oficio_id = o.id
    JOIN secretarias s ON o.secretaria_id = s.id
    JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE $where
");
$stmt_count->execute($params);
$total_registros = (int)$stmt_count->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $itens_por_pagina));

if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
    $offset = ($pagina_atual - 1) * $itens_por_pagina;
}

// Query principal com LIMIT
$stmt = $pdo->prepare($sql_select . $sql_order . "
    LIMIT $itens_por_pagina OFFSET $offset
");
$stmt->execute($params);
$aquisicoes = $stmt->fetchAll();

// Função auxiliar para manter parâmetros na URL da paginação
function get_pagination_url($page)
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

include 'views/layout/header.php';
?>

<style>
    .filtros-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 1rem;
        align-items: end;
    }

    .filtro-busca {
        grid-column: span 3;
    }

    .filtro-status {
        grid-column: span 2;
    }

    .filtro-secretaria {
        grid-column: span 3;
    }

    .filtro-fornecedor {
        grid-column: span 4;
    }

    .filtro-data {
        grid-column: span 2;
    }

    .filtros-acoes {
        display: flex;
        gap: .5rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: nowrap;
        grid-column: span 8;
    }

    .filtros-acoes .btn {
        min-height: 40px;
        justify-content: center;
        white-space: nowrap;
    }

    @media (max-width: 1200px) {
        .filtro-busca,
        .filtro-fornecedor {
            grid-column: span 6;
        }

        .filtro-status,
        .filtro-secretaria,
        .filtro-data {
            grid-column: span 3;
        }

        .filtros-acoes {
            grid-column: span 6;
        }
    }

    .lista-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .lista-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .lista-table {
        min-width: 1220px;
    }

    .lista-table,
    .lista-table th,
    .lista-table td,
    .lista-table span,
    .lista-table a,
    .lista-table .badge {
        white-space: nowrap !important;
    }

    .acoes-wrap {
        display: flex;
        gap: 8px;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: flex-end;
    }

    .paginacao-box {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: .75rem;
        margin-top: 2rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1.5rem;
        flex-wrap: wrap;
    }

    .paginacao-info {
        font-weight: 600;
        color: var(--text-dark);
    }

    .btn-limpar {
        width: 100%;
    }

    @media (max-width: 768px) {
        .filtros-grid {
            grid-template-columns: 1fr;
        }

        .filtro-busca,
        .filtro-status,
        .filtro-secretaria,
        .filtro-fornecedor,
        .filtro-data,
        .filtros-acoes {
            grid-column: span 1;
        }

        .lista-header {
            flex-direction: column;
            align-items: stretch;
        }

        .lista-header .btn {
            width: 100%;
            justify-content: center;
        }

        .filtros-acoes {
            display: grid;
            grid-template-columns: 1fr;
            gap: .75rem;
        }

        .filtros-acoes .btn {
            width: 100%;
        }

        .paginacao-box {
            flex-direction: column;
        }

        .paginacao-box .btn {
            width: 100%;
            max-width: 260px;
            justify-content: center;
        }
    }

    /* Dropdown Actions Styles */
    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        z-index: 9999;
        display: none !important;
        min-width: 200px;
        padding: 0.5rem 0;
        margin-top: 0.25rem;
        background-color: #fff;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .dropdown-menu.show {
        display: block !important;
        animation: dropdownFadeIn 0.2s ease-out;
    }

    /* Versão Dropup */
    .dropdown-menu.dropup {
        top: auto !important;
        bottom: 100% !important;
        margin-top: 0 !important;
        margin-bottom: 0.25rem !important;
        animation: dropdownFadeUp 0.2s ease-out !important;
    }

    @keyframes dropdownFadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes dropdownFadeUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 0.75rem 1rem;
        color: var(--text-dark) !important;
        text-decoration: none !important;
        font-weight: 500;
        font-size: 0.825rem;
        transition: all 0.2s;
        border: 0;
        background: transparent;
        cursor: pointer;
        padding-right: 2rem;
    }

    .dropdown-item:hover {
        background-color: var(--primary-light);
        color: var(--primary) !important;
    }

    .dropdown-item i {
        width: 16px;
        text-align: center;
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    .dropdown-item:hover i {
        color: var(--primary);
    }

    .btn-three-dots {
        background: #fff;
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        padding: 0;
        cursor: pointer;
    }

    .btn-three-dots:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--primary-light);
    }

    /* Fix table clipping */
    .lista-table-wrap {
        overflow-x: auto !important;
        padding-bottom: 0 !important;
        margin-bottom: 0 !important;
        -webkit-overflow-scrolling: touch;
    }

    .lista-table td {
        position: relative;
    }
</style>

<div class="card no-print">
    <div class="card-body">
        <h3 class="card-title" style="margin-bottom: 1rem; font-weight: 700; font-size: 1rem;">
            <i class="fas fa-filter" style="margin-right: 5px; color: var(--primary);"></i> Filtros de Busca
        </h3>

        <form action="" method="GET" class="filtros-grid">
            <div class="form-group filtro-busca" style="margin-bottom: 0;">
                <label class="form-label">Termo de busca</label>
                <input
                    type="text"
                    name="busca"
                    class="form-control"
                    placeholder="Nº aquisição, ofício, secretaria ou fornecedor..."
                    value="<?php echo htmlspecialchars($_GET['busca'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group filtro-status" style="margin-bottom: 0;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">Todos Status</option>
                    <option value="AGUARDANDO ENTREGA" <?php echo $status === 'AGUARDANDO ENTREGA' ? 'selected' : ''; ?>>AGUARDANDO ENTREGA</option>
                    <option value="FINALIZADO" <?php echo $status === 'FINALIZADO' ? 'selected' : ''; ?>>FINALIZADO</option>
                </select>
            </div>

            <div class="form-group filtro-secretaria" style="margin-bottom: 0;">
                <label class="form-label">Secretaria</label>
                <select name="secretaria_id" class="form-control">
                    <option value="">Todas as Secretarias</option>
                    <?php foreach ($secretarias_list as $sec): ?>
                        <option value="<?php echo (int)$sec['id']; ?>" <?php echo $secretaria_id == $sec['id'] ? 'selected' : ''; ?>>
                            <?php echo h($sec['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group filtro-fornecedor" style="margin-bottom: 0;">
                <label class="form-label">Fornecedor</label>
                <select name="fornecedor_id" class="form-control">
                    <option value="">Todos os Fornecedores</option>
                    <?php foreach ($fornecedores_list as $fornecedor): ?>
                        <option value="<?php echo (int)$fornecedor['id']; ?>" <?php echo $fornecedor_id == $fornecedor['id'] ? 'selected' : ''; ?>>
                            <?php echo h($fornecedor['nome']); ?>
                            <?php if (!empty($fornecedor['cnpj'])): ?>
                                (<?php echo h($fornecedor['cnpj']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group filtro-data" style="margin-bottom: 0;">
                <label class="form-label">Data inicial</label>
                <input
                    type="date"
                    name="data_inicio"
                    class="form-control"
                    value="<?php echo h($data_inicio); ?>">
            </div>

            <div class="form-group filtro-data" style="margin-bottom: 0;">
                <label class="form-label">Data final</label>
                <input
                    type="date"
                    name="data_fim"
                    class="form-control"
                    value="<?php echo h($data_fim); ?>">
            </div>

            <div class="form-group filtros-acoes" style="margin-bottom: 0;">
                <button type="submit" class="btn btn-outline btn-sm" title="Filtrar">
                    <i class="fas fa-search"></i> Filtrar
                </button>

                <button
                    type="submit"
                    name="export"
                    value="excel"
                    class="btn btn-primary btn-sm"
                    title="Exportar Excel">
                    <i class="fas fa-file-excel"></i> Excel
                </button>

                <a href="aquisicoes_lista.php" class="btn btn-outline btn-sm" title="Limpar Filtros">
                    <i class="fas fa-eraser"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="lista-header">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1.25rem; margin: 0;">
                <i class="fas fa-file-invoice-dollar" style="margin-right: 10px; color: var(--primary);"></i> Aquisições Geradas
            </h3>
        </div>

        <?php display_flash(); ?>

        <div class="table-responsive lista-table-wrap">
            <table class="table-vcenter text-nowrap lista-table">
                <thead>
                    <tr>
                        <th>Nº Aquisição</th>
                        <th>Ref. Ofício</th>
                        <th>Secretaria</th>
                        <th>Fornecedor</th>
                        <th>Data</th>
                        <th style="text-align: right;">Valor Total</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aquisicoes as $aq): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-dark);">
                                    <?php echo htmlspecialchars($aq['numero_aq'], ENT_QUOTES, 'UTF-8'); ?>
                                </strong>
                            </td>
                            <td>
                                <span class="text-muted">
                                    <?php echo htmlspecialchars($aq['oficio_num'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 600;">
                                    <?php echo htmlspecialchars($aq['secretaria'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($aq['fornecedor'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo format_date($aq['criado_em']); ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--primary);">
                                <?php echo format_money($aq['valor_total']); ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?php echo strtolower($aq['status'] === 'AGUARDANDO ENTREGA' ? 'pending' : 'finalized'); ?>" style="padding: 0.4rem 1rem;">
                                    <?php echo htmlspecialchars($aq['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="acoes-wrap">
                                    <div class="dropdown">
                                        <button class="btn-three-dots" data-dropdown-toggle title="Ações">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="aquisicoes_visualizar.php?id=<?php echo (int)$aq['id']; ?>">
                                                <i class="fas fa-eye"></i> Visualizar
                                            </a>
                                            
                                            <?php 
                                                $nivel_user = strtoupper($_SESSION['nivel'] ?? '');
                                                if ($aq['status'] === 'AGUARDANDO ENTREGA' && ($nivel_user === 'ADMIN' || $nivel_user === 'SUPORTE')): 
                                            ?>
                                                <a class="dropdown-item" href="aquisicao_finalizar.php?id=<?php echo (int)$aq['id']; ?>" style="color: var(--status-finalized) !important;" onclick="return confirm('Confirmar o recebimento desta aquisição?')">
                                                    <i class="fas fa-check-circle"></i> Marcar como Recebido
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($aquisicoes)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding: 3rem; color: var(--text-muted);">
                                Nenhuma aquisição gerada até o momento.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
            <div class="paginacao-box">
                <a href="<?php echo $pagina_atual > 1 ? get_pagination_url($pagina_atual - 1) : '#'; ?>"
                    class="btn btn-outline btn-sm <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                    <i class="fas fa-angle-left"></i> Anterior
                </a>

                <span class="paginacao-info">
                    Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                </span>

                <a href="<?php echo $pagina_atual < $total_paginas ? get_pagination_url($pagina_atual + 1) : '#'; ?>"
                    class="btn btn-outline btn-sm <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                    Próxima <i class="fas fa-angle-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>
