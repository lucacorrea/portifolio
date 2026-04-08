<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
view_check();

$page_title = "Relatórios Inteligentes";

/* =========================
   FUNÇÕES AUXILIARES
========================= */
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

if (!function_exists('secretaria_sigla')) {
    function secretaria_sigla(string $nome): string
    {
        $nomeUp = mb_strtoupper(trim($nome), 'UTF-8');

        $map = [
            'SECRETARIA MUNICIPAL DE EDUCAÇÃO' => 'SEMED',
            'SECRETARIA MUNICIPAL DE SAÚDE' => 'SEMSA',
            'SECRETARIA MUNICIPAL DE ASSISTÊNCIA SOCIAL' => 'SEMAS',
            'SECRETARIA MUNICIPAL DE OBRAS E LIMPEZA PÚBLICA' => 'SEMOB/SEMLIP',
            'SECRETARIA MUNICIPAL DE OBRAS' => 'SEMOB',
            'SECRETARIA MUNICIPAL DE LIMPEZA PÚBLICA' => 'SEMLIP',
            'SECRETARIA MUNICIPAL DE ADMINISTRAÇÃO' => 'SEMAD',
            'SECRETARIA MUNICIPAL DE FINANÇAS' => 'SEMF',
            'SECRETARIA MUNICIPAL DE INFRAESTRUTURA' => 'SEMINF',
            'SECRETARIA MUNICIPAL DE PRODUÇÃO RURAL' => 'SEMPROR',
            'SECRETARIA MUNICIPAL DE CULTURA' => 'SEMCULT',
            'SECRETARIA MUNICIPAL DE MEIO AMBIENTE' => 'SEMMA',
            'SECRETARIA MUNICIPAL DE TURISMO' => 'SEMTUR',
            'SECRETARIA MUNICIPAL DE ESPORTE' => 'SEMESP',
            'SECRETARIA MUNICIPAL DE ESPORTES' => 'SEMESP',
            'SECRETARIA MUNICIPAL DE HABITAÇÃO' => 'SMTH',
            'SECRETARIA MUNICIPAL DE PLANEJAMENTO' => 'SEMPLA',
            'SECRETARIA MUNICIPAL DE SEGURANÇA' => 'SEMSEG',
        ];

        foreach ($map as $trecho => $sigla) {
            if (mb_strpos($nomeUp, $trecho) !== false) {
                return $sigla;
            }
        }

        if (preg_match('/^\s*([A-Z]{3,}(?:\/[A-Z]{3,})?)\s*-/u', $nomeUp, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/\b(SE[A-Z]{2,}(?:\/SE[A-Z]{2,})?)\b/u', $nomeUp, $m)) {
            return trim($m[1]);
        }

        $partes = preg_split('/\s+/', preg_replace('/[^\p{L}\s\/-]+/u', '', $nomeUp));
        $ignorar = ['DE', 'DA', 'DO', 'DAS', 'DOS', 'E', 'A', 'O', 'AS', 'OS', 'MUNICIPAL', 'SECRETARIA'];

        $sigla = '';
        foreach ($partes as $parte) {
            $parte = trim($parte);
            if ($parte === '' || in_array($parte, $ignorar, true)) {
                continue;
            }
            $sigla .= mb_substr($parte, 0, 1, 'UTF-8');
        }

        return $sigla !== '' ? $sigla : $nomeUp;
    }
}

/* =========================
   FILTROS
========================= */
$sec_id          = isset($_GET['sec_id']) ? trim((string)$_GET['sec_id']) : '';
$forn_id         = isset($_GET['forn_id']) ? trim((string)$_GET['forn_id']) : '';
$produto         = isset($_GET['produto']) ? trim((string)$_GET['produto']) : '';
$periodo_inicio  = isset($_GET['inicio']) ? trim((string)$_GET['inicio']) : '';
$periodo_fim     = isset($_GET['fim']) ? trim((string)$_GET['fim']) : '';
$export          = isset($_GET['export']) ? trim((string)$_GET['export']) : '';

$whereParts = [];
$params = [];

$whereParts[] = "1=1";

if ($sec_id !== '') {
    $whereParts[] = "o.secretaria_id = :sec_id";
    $params[':sec_id'] = (int)$sec_id;
}

if ($forn_id !== '') {
    $whereParts[] = "a.fornecedor_id = :forn_id";
    $params[':forn_id'] = (int)$forn_id;
}

if ($produto !== '') {
    $whereParts[] = "ia.produto LIKE :produto";
    $params[':produto'] = '%' . $produto . '%';
}

if ($periodo_inicio !== '') {
    $whereParts[] = "a.criado_em >= :inicio";
    $params[':inicio'] = $periodo_inicio . ' 00:00:00';
}

if ($periodo_fim !== '') {
    $whereParts[] = "a.criado_em <= :fim";
    $params[':fim'] = $periodo_fim . ' 23:59:59';
}

$where = implode(' AND ', $whereParts);

/* =========================
   LISTAS DOS FILTROS
========================= */
$secretarias = $pdo->query("SELECT * FROM secretarias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$fornecedores = $pdo->query("SELECT * FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   RELATÓRIO PRINCIPAL
   TOTAL POR SECRETARIA
========================= */
$sql_secretarias = "
    SELECT
        s.id AS secretaria_id,
        s.nome AS secretaria_nome,
        COALESCE(SUM(ia.quantidade), 0) AS total_qtd,
        COALESCE(SUM(ia.quantidade * ia.valor_unitario), 0) AS total_valor
    FROM itens_aquisicao ia
    INNER JOIN aquisicoes a ON ia.aquisicao_id = a.id
    INNER JOIN oficios o ON a.oficio_id = o.id
    INNER JOIN secretarias s ON o.secretaria_id = s.id
    WHERE $where
    GROUP BY s.id, s.nome
    ORDER BY total_valor DESC, s.nome ASC
";

$stmt_secretarias = $pdo->prepare($sql_secretarias);
$stmt_secretarias->execute($params);
$relatorio_secretarias = $stmt_secretarias->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   DETALHES DOS PRODUTOS
   POR SECRETARIA
========================= */
$sql_detalhes = "
    SELECT
        s.id AS secretaria_id,
        s.nome AS secretaria_nome,
        ia.produto,
        f.nome AS fornecedor,
        COALESCE(SUM(ia.quantidade), 0) AS total_qtd,
        COALESCE(SUM(ia.quantidade * ia.valor_unitario), 0) AS total_valor
    FROM itens_aquisicao ia
    INNER JOIN aquisicoes a ON ia.aquisicao_id = a.id
    INNER JOIN oficios o ON a.oficio_id = o.id
    INNER JOIN secretarias s ON o.secretaria_id = s.id
    INNER JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE $where
    GROUP BY s.id, s.nome, ia.produto, f.nome
    ORDER BY s.nome ASC, total_valor DESC, ia.produto ASC
";

$stmt_detalhes = $pdo->prepare($sql_detalhes);
$stmt_detalhes->execute($params);
$detalhes_produtos = $stmt_detalhes->fetchAll(PDO::FETCH_ASSOC);

$detalhes_por_secretaria = [];
foreach ($detalhes_produtos as $item) {
    $sid = (int)$item['secretaria_id'];
    if (!isset($detalhes_por_secretaria[$sid])) {
        $detalhes_por_secretaria[$sid] = [];
    }
    $detalhes_por_secretaria[$sid][] = $item;
}

/* =========================
   TOTAIS
========================= */
$total_geral = 0;
$total_qtd_geral = 0;
foreach ($relatorio_secretarias as $row) {
    $total_geral += (float)$row['total_valor'];
    $total_qtd_geral += (float)$row['total_qtd'];
}

/* =========================
   DADOS DO GRÁFICO
========================= */
$chart_labels = [];
$chart_labels_full = [];
$chart_values = [];

foreach ($relatorio_secretarias as $row) {
    $chart_labels[] = secretaria_sigla((string)$row['secretaria_nome']);
    $chart_labels_full[] = (string)$row['secretaria_nome'];
    $chart_values[] = (float)$row['total_valor'];
}

/* =========================
   TEXTO DOS FILTROS
========================= */
$nome_secretaria_filtro = 'Todas';
if ($sec_id !== '') {
    foreach ($secretarias as $s) {
        if ((string)$s['id'] === (string)$sec_id) {
            $nome_secretaria_filtro = $s['nome'];
            break;
        }
    }
}

$nome_fornecedor_filtro = 'Todos';
if ($forn_id !== '') {
    foreach ($fornecedores as $f) {
        if ((string)$f['id'] === (string)$forn_id) {
            $nome_fornecedor_filtro = $f['nome'];
            break;
        }
    }
}

$periodo_texto = 'Todos';
if ($periodo_inicio !== '' || $periodo_fim !== '') {
    $inicioTxt = $periodo_inicio !== '' ? date('d/m/Y', strtotime($periodo_inicio)) : '...';
    $fimTxt    = $periodo_fim !== '' ? date('d/m/Y', strtotime($periodo_fim)) : '...';
    $periodo_texto = $inicioTxt . ' até ' . $fimTxt;
}

/* =========================
   EXPORTAÇÃO EXCEL
========================= */
if ($export === 'excel') {
    $filename = 'relatorio_secretarias_' . date('Ymd_His') . '.xls';

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
            body{
                font-family: Arial, sans-serif;
                font-size: 12px;
                color: #1f2937;
                margin: 18px;
            }

            table{
                border-collapse: collapse;
                width: 100%;
                table-layout: fixed;
            }

            .sheet{
                width: 100%;
                max-width: 1100px;
            }

            .sheet td,
            .sheet th{
                border: 1px solid #7c8aa5;
                padding: 7px 8px;
                vertical-align: middle;
                word-wrap: break-word;
            }

            .sheet .no-border{
                border: none !important;
            }

            .title-main{
                background: #dbeafe;
                color: #0f172a;
                font-size: 18px;
                font-weight: bold;
                text-align: center;
                border: 1px solid #7c8aa5;
                padding: 12px;
            }

            .sub-info{
                background: #f8fafc;
                font-size: 11px;
            }

            .section-title{
                background: #1d4ed8;
                color: #fff;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: .3px;
                text-align: center;
            }

            .thead{
                background: #e5e7eb;
                font-weight: bold;
                text-align: center;
            }

            .summary-label{
                background: #f8fafc;
                font-weight: bold;
                text-align: center;
            }

            .summary-value{
                text-align: center;
                font-weight: bold;
                font-size: 14px;
                background: #ffffff;
            }

            .left{
                text-align: left;
            }

            .center{
                text-align: center;
            }

            .right{
                text-align: right;
            }

            .secretaria-head{
                background: #dbeafe;
                font-weight: bold;
                color: #0f172a;
            }

            .total-row{
                background: #eef2ff;
                font-weight: bold;
            }

            .spacer td{
                border: none !important;
                height: 8px;
                padding: 0;
                background: transparent;
            }
        </style>
    </head>
    <body>
        <table class="sheet">
            <colgroup>
                <col style="width: 38%;">
                <col style="width: 27%;">
                <col style="width: 15%;">
                <col style="width: 20%;">
            </colgroup>

            <tr>
                <td colspan="4" class="title-main">RELATÓRIO DE AQUISIÇÕES POR SECRETARIA</td>
            </tr>

            <tr>
                <td colspan="4" class="sub-info left"><strong>Gerado em:</strong> <?php echo date('d/m/Y H:i:s'); ?></td>
            </tr>
            <tr>
                <td colspan="4" class="sub-info left"><strong>Detalhes:</strong> Totais por secretaria e detalhamento de produtos por secretaria</td>
            </tr>
            <tr>
                <td colspan="2" class="sub-info left"><strong>Secretaria:</strong> <?php echo h($nome_secretaria_filtro); ?></td>
                <td colspan="2" class="sub-info left"><strong>Fornecedor:</strong> <?php echo h($nome_fornecedor_filtro); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="sub-info left"><strong>Produto:</strong> <?php echo $produto !== '' ? h($produto) : 'Todos'; ?></td>
                <td colspan="2" class="sub-info left"><strong>Período:</strong> <?php echo h($periodo_texto); ?></td>
            </tr>

            <tr class="spacer"><td colspan="4"></td></tr>

            <tr>
                <td colspan="2" class="summary-label">TOTAL GERAL FINANCEIRO</td>
                <td colspan="2" class="summary-label">TOTAL GERAL DE QUANTIDADE</td>
            </tr>
            <tr>
                <td colspan="2" class="summary-value"><?php echo format_money($total_geral); ?></td>
                <td colspan="2" class="summary-value"><?php echo number_format($total_qtd_geral, 2, ',', '.'); ?></td>
            </tr>

            <tr class="spacer"><td colspan="4"></td></tr>

            <tr>
                <td colspan="4" class="section-title">RESUMO POR SECRETARIA</td>
            </tr>
            <tr class="thead">
                <th>Secretaria</th>
                <th>Fornecedor / Observação</th>
                <th>Qtd Total</th>
                <th>Valor Total</th>
            </tr>

            <?php if (!empty($relatorio_secretarias)): ?>
                <?php foreach ($relatorio_secretarias as $row): ?>
                    <tr>
                        <td class="left"><?php echo h($row['secretaria_nome']); ?></td>
                        <td class="center">Resumo consolidado</td>
                        <td class="center"><?php echo number_format((float)$row['total_qtd'], 2, ',', '.'); ?></td>
                        <td class="right"><?php echo format_money($row['total_valor']); ?></td>
                    </tr>
                <?php endforeach; ?>

                <tr class="total-row">
                    <td colspan="2" class="left">TOTAL GERAL</td>
                    <td class="center"><?php echo number_format($total_qtd_geral, 2, ',', '.'); ?></td>
                    <td class="right"><?php echo format_money($total_geral); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="center">Nenhum dado encontrado para os filtros selecionados.</td>
                </tr>
            <?php endif; ?>

            <tr class="spacer"><td colspan="4"></td></tr>
            <tr>
                <td colspan="4" class="section-title">DETALHES DOS PRODUTOS POR SECRETARIA</td>
            </tr>

            <?php if (!empty($relatorio_secretarias)): ?>
                <?php foreach ($relatorio_secretarias as $sec): ?>
                    <?php $sid = (int)$sec['secretaria_id']; ?>

                    <tr>
                        <td colspan="4" class="secretaria-head">
                            Secretaria: <?php echo h($sec['secretaria_nome']); ?> | Total: <?php echo format_money($sec['total_valor']); ?>
                        </td>
                    </tr>
                    <tr class="thead">
                        <th>Produto</th>
                        <th>Fornecedor</th>
                        <th>Qtd</th>
                        <th>Valor Total</th>
                    </tr>

                    <?php if (!empty($detalhes_por_secretaria[$sid])): ?>
                        <?php foreach ($detalhes_por_secretaria[$sid] as $det): ?>
                            <tr>
                                <td class="left"><?php echo h($det['produto']); ?></td>
                                <td class="left"><?php echo h($det['fornecedor']); ?></td>
                                <td class="center"><?php echo number_format((float)$det['total_qtd'], 2, ',', '.'); ?></td>
                                <td class="right"><?php echo format_money($det['total_valor']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="center">Sem detalhes para esta secretaria.</td>
                        </tr>
                    <?php endif; ?>

                    <tr class="spacer"><td colspan="4"></td></tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </body>
    </html>
    <?php
    exit;
}

include 'views/layout/header.php';
?>

<style>
    .relatorio-wrapper .card {
        border: 1px solid #e9edf5;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        overflow: hidden;
        background: #fff;
    }

    .relatorio-wrapper .card + .card,
    .relatorio-wrapper .row + .card,
    .relatorio-wrapper .card + .row {
        margin-top: 1.25rem;
    }

    .report-header-title {
        display: flex;
        align-items: center;
        gap: .75rem;
        margin-bottom: 1.25rem;
    }

    .report-header-title .icon-box {
        width: 42px;
        height: 42px;
        min-width: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: rgba(32, 107, 196, 0.12);
        color: #206bc4;
        font-size: 1rem;
    }

    .report-header-title h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
    }

    .report-header-title p {
        margin: .15rem 0 0;
        font-size: .88rem;
        color: #64748b;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 1rem;
    }

    .form-group label,
    .form-label {
        display: block;
        font-weight: 700;
        color: #334155;
        margin-bottom: .45rem;
        font-size: .88rem;
    }

    .form-control {
        width: 100%;
        min-height: 44px;
        border-radius: 12px;
        border: 1px solid #dbe2ea;
        padding: .7rem .9rem;
        transition: .2s ease;
        background: #fff;
    }

    .form-control:focus {
        outline: none;
        border-color: #206bc4;
        box-shadow: 0 0 0 4px rgba(32, 107, 196, 0.10);
    }

    .filter-actions {
        display: flex;
        justify-content: flex-end;
        gap: .75rem;
        margin-top: 1.25rem;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        border-radius: 12px;
        padding: .7rem 1rem;
        border: 1px solid transparent;
        text-decoration: none;
        cursor: pointer;
        font-weight: 700;
        transition: .2s ease;
    }

    .btn-sm {
        padding: .62rem .95rem;
        font-size: .85rem;
    }

    .btn-primary {
        background: #206bc4;
        border-color: #206bc4;
        color: #fff;
    }

    .btn-primary:hover {
        background: #1a5aa8;
        border-color: #1a5aa8;
        color: #fff;
    }

    .btn-outline {
        background: #fff;
        border-color: #dbe2ea;
        color: #334155;
    }

    .btn-outline:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #0f172a;
    }

    .btn-success-custom {
        background: #198754;
        border-color: #198754;
        color: #fff;
    }

    .btn-success-custom:hover {
        background: #157347;
        border-color: #146c43;
        color: #fff;
    }

    .btn-danger-soft {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #be123c;
    }

    .btn-danger-soft:hover {
        background: #ffe4e6;
        border-color: #fda4af;
        color: #9f1239;
    }

    .summary-card {
        height: 100%;
        position: relative;
    }

    .summary-card .card-body {
        padding: 1.5rem;
    }

    .summary-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .85rem;
    }

    .summary-top .mini-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(32, 107, 196, 0.10);
        color: #206bc4;
    }

    .summary-label {
        color: #64748b;
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .35px;
        margin: 0;
    }

    .summary-number {
        font-size: 2rem;
        line-height: 1.1;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        word-break: break-word;
    }

    .summary-helper {
        margin-top: .6rem;
        color: #64748b;
        font-size: .9rem;
    }

    .chart-card-body {
        padding: 1.5rem;
    }

    .chart-wrap {
        position: relative;
        width: 100%;
        height: 320px;
    }

    .table-header-box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .report-actions {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
    }

    .modern-table-wrap {
        border: 1px solid #e9edf5;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
    }

    .table-scroll-x {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .table-scroll-x::-webkit-scrollbar {
        height: 10px;
    }

    .table-scroll-x::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    .table-scroll-x::-webkit-scrollbar-track {
        background: #f8fafc;
    }

    .modern-table {
        width: 100%;
        min-width: 760px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .modern-table thead th {
        background: #f8fafc;
        color: #334155;
        font-size: .86rem;
        font-weight: 800;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem .9rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .modern-table tbody td {
        padding: .95rem .9rem;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
        color: #0f172a;
        background: #fff;
        white-space: nowrap;
    }

    .modern-table tbody tr:hover > td {
        background: #fcfdff;
    }

    .modern-table tbody tr:last-child td {
        border-bottom: none;
    }

    .td-secretaria {
        font-weight: 800;
        color: #206bc4 !important;
    }

    .td-right {
        text-align: right;
    }

    .td-center {
        text-align: center;
    }

    .text-nowrap {
        white-space: nowrap !important;
    }

    .badge-total {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .42rem .75rem;
        border-radius: 999px;
        background: rgba(25, 135, 84, 0.10);
        color: #157347;
        font-weight: 800;
        font-size: .82rem;
        white-space: nowrap;
    }

    .total-row-main td {
        background: #f8fafc !important;
        font-weight: 800;
        color: #0f172a;
    }

    .empty-state {
        text-align: center;
        padding: 2.4rem 1rem !important;
        color: #64748b !important;
    }

    .details-modal {
        position: fixed;
        inset: 0;
        z-index: 1060;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .details-modal.open {
        display: flex;
    }

    .details-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.62);
        backdrop-filter: blur(2px);
    }

    .details-modal-dialog {
        position: relative;
        z-index: 2;
        width: min(1100px, 100%);
        max-height: calc(100vh - 2rem);
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 24px 60px rgba(2, 6, 23, 0.28);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: modalFadeIn .18s ease;
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: translateY(8px) scale(.985);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .details-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.25rem;
        border-bottom: 1px solid #e9edf5;
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    }

    .details-modal-title-wrap {
        min-width: 0;
    }

    .details-modal-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.3;
    }

    .details-modal-subtitle {
        margin: .25rem 0 0;
        font-size: .88rem;
        color: #64748b;
    }

    .details-modal-close {
        min-width: 42px;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        border: 1px solid #dbe2ea;
        background: #fff;
        color: #334155;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: .2s ease;
        flex-shrink: 0;
    }

    .details-modal-close:hover {
        background: #f8fafc;
        color: #0f172a;
        border-color: #cbd5e1;
    }

    .details-modal-body {
        padding: 1.1rem 1.25rem 1.25rem;
        overflow: auto;
    }

    .details-modal-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .details-mini-card {
        border: 1px solid #e9edf5;
        border-radius: 14px;
        padding: 1rem;
        background: #fff;
    }

    .details-mini-label {
        margin: 0 0 .35rem;
        font-size: .78rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .35px;
    }

    .details-mini-value {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
        color: #0f172a;
        word-break: break-word;
    }

    .details-inner-table-wrap {
        border: 1px solid #dbe7f5;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
    }

    .details-inner-table {
        width: 100%;
        min-width: 760px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .details-inner-table th {
        background: #eaf2fd;
        color: #1e3a8a;
        font-weight: 800;
        padding: .9rem .85rem;
        border-bottom: 1px solid #dbe7f5;
        font-size: .86rem;
        white-space: nowrap;
    }

    .details-inner-table td {
        padding: .88rem .85rem;
        border-bottom: 1px solid #edf2f7;
        background: #fff;
        color: #0f172a;
        font-size: .92rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .details-inner-table tr:last-child td {
        border-bottom: none;
    }

    .details-inner-table tbody tr:hover td {
        background: #fcfdff;
    }

    .body-modal-open {
        overflow: hidden !important;
    }

    @media (max-width: 992px) {
        .summary-number {
            font-size: 1.6rem;
        }

        .chart-wrap {
            height: 280px;
        }

        .details-modal-dialog {
            width: min(1000px, 100%);
        }
    }

    @media (max-width: 768px) {
        .filter-actions,
        .report-actions,
        .table-header-box {
            width: 100%;
        }

        .filter-actions .btn,
        .report-actions .btn {
            flex: 1 1 100%;
        }

        .chart-wrap {
            height: 250px;
        }

        .modern-table {
            min-width: 720px;
        }

        .modern-table thead th,
        .modern-table tbody td {
            padding: .75rem .7rem;
            font-size: .86rem;
        }

        .details-modal {
            padding: .65rem;
        }

        .details-modal-dialog {
            width: 100%;
            max-height: calc(100vh - 1.3rem);
            border-radius: 16px;
        }

        .details-modal-header {
            padding: 1rem;
        }

        .details-modal-title {
            font-size: .98rem;
        }

        .details-modal-subtitle {
            font-size: .84rem;
        }

        .details-modal-body {
            padding: 1rem;
        }

        .details-modal-summary {
            grid-template-columns: 1fr;
            gap: .75rem;
        }

        .details-inner-table {
            min-width: 720px;
        }

        .details-inner-table th,
        .details-inner-table td {
            padding: .75rem .7rem;
            font-size: .85rem;
        }
    }

    @media (max-width: 480px) {
        .details-modal {
            padding: .45rem;
        }

        .details-modal-dialog {
            max-height: calc(100vh - .9rem);
            border-radius: 14px;
        }

        .details-modal-header,
        .details-modal-body {
            padding: .85rem;
        }

        .details-modal-close {
            min-width: 38px;
            width: 38px;
            height: 38px;
            border-radius: 10px;
        }

        .details-mini-card {
            padding: .85rem;
        }

        .details-mini-value {
            font-size: 1rem;
        }

        .modern-table {
            min-width: 680px;
        }

        .details-inner-table {
            min-width: 680px;
        }
    }

    @media print {
        .no-print,
        .btn,
        button,
        .report-actions,
        .filter-actions,
        .details-open-modal,
        .details-modal {
            display: none !important;
        }

        .relatorio-wrapper .card {
            box-shadow: none !important;
            border: 1px solid #d1d5db !important;
        }

        .table-scroll-x {
            overflow: visible !important;
        }

        .modern-table,
        .details-inner-table {
            min-width: 0 !important;
            width: 100% !important;
        }

        .modern-table th,
        .modern-table td,
        .details-inner-table th,
        .details-inner-table td {
            white-space: normal !important;
        }
    }
</style>

<div class="relatorio-wrapper">

    <div class="card no-print">
        <div class="card-body" style="padding: 1.5rem;">
            <div class="report-header-title">
                <div class="icon-box">
                    <i class="fas fa-filter"></i>
                </div>
                <div>
                    <h3>Filtros do Relatório</h3>
                    <p>Refine os resultados por secretaria, fornecedor, produto e período.</p>
                </div>
            </div>

            <form action="" method="GET">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Secretaria</label>
                        <select name="sec_id" class="form-control">
                            <option value="">Todas</option>
                            <?php foreach ($secretarias as $s): ?>
                                <option value="<?php echo h($s['id']); ?>" <?php echo $sec_id == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($s['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fornecedor</label>
                        <select name="forn_id" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($fornecedores as $f): ?>
                                <option value="<?php echo h($f['id']); ?>" <?php echo $forn_id == $f['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($f['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nome do Produto</label>
                        <input type="text" name="produto" class="form-control" placeholder="Buscar produto..." value="<?php echo h($produto); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Período de Início</label>
                        <input type="date" name="inicio" class="form-control" value="<?php echo h($periodo_inicio); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Período de Fim</label>
                        <input type="date" name="fim" class="form-control" value="<?php echo h($periodo_fim); ?>">
                    </div>
                </div>

                <div class="filter-actions">
                    <a href="relatorios.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-eraser"></i> Limpar
                    </a>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-sync-alt"></i> Gerar Relatório
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row" style="margin-top: 1.25rem;">
        <div class="col-lg-8 col-12" style="margin-bottom: 1.25rem;">
            <div class="card summary-card">
                <div class="card-body chart-card-body">
                    <div class="report-header-title" style="margin-bottom: 1rem;">
                        <div class="icon-box">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div>
                            <h3>Distribuição de Gastos por Secretaria</h3>
                            <p>Visualização do valor total adquirido por secretaria.</p>
                        </div>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="chartSecretaria"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-12" style="margin-bottom: 1.25rem;">
            <div class="card summary-card">
                <div class="card-body">
                    <div class="summary-top">
                        <div>
                            <p class="summary-label">Total financeiro geral</p>
                            <h3 class="summary-number"><?php echo format_money($total_geral); ?></h3>
                        </div>
                        <div class="mini-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="summary-helper">
                        Soma de todos os valores encontrados conforme os filtros selecionados.
                    </div>
                </div>
            </div>

            <div class="card summary-card" style="margin-top: 1rem;">
                <div class="card-body">
                    <div class="summary-top">
                        <div>
                            <p class="summary-label">Quantidade total</p>
                            <h3 class="summary-number"><?php echo number_format($total_qtd_geral, 2, ',', '.'); ?></h3>
                        </div>
                        <div class="mini-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                    <div class="summary-helper">
                        Quantidade acumulada dos itens de aquisições no período filtrado.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="padding: 1.5rem;">
            <div class="table-header-box">
                <div class="report-header-title" style="margin-bottom: 0;">
                    <div class="icon-box">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h3>Detalhamento por Secretaria</h3>
                        <p>Veja os totais consolidados e abra os itens detalhados de cada secretaria.</p>
                    </div>
                </div>

                <div class="report-actions no-print">
                    <a
                        href="?<?php echo h(http_build_query(array_merge($_GET, ['export' => 'excel']))); ?>"
                        class="btn btn-success-custom btn-sm"
                    >
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </a>
                </div>
            </div>

            <div class="modern-table-wrap">
                <div class="table-scroll-x">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th class="text-nowrap">Secretaria</th>
                                <th class="td-right text-nowrap">Qtd Total</th>
                                <th class="td-right text-nowrap">Valor Total</th>
                                <th class="no-print td-center text-nowrap" style="width: 150px;">Detalhes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($relatorio_secretarias)): ?>
                                <?php foreach ($relatorio_secretarias as $row): ?>
                                    <?php $sid = (int)$row['secretaria_id']; ?>
                                    <tr>
                                        <td class="td-secretaria text-nowrap">
                                            <?php echo h($row['secretaria_nome']); ?>
                                        </td>
                                        <td class="td-right text-nowrap">
                                            <?php echo number_format((float)$row['total_qtd'], 2, ',', '.'); ?>
                                        </td>
                                        <td class="td-right text-nowrap">
                                            <span class="badge-total"><?php echo format_money($row['total_valor']); ?></span>
                                        </td>
                                        <td class="no-print td-center text-nowrap">
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-sm details-open-modal"
                                                data-modal="modal-secretaria-<?php echo $sid; ?>"
                                            >
                                                <i class="fas fa-search"></i> Detalhes
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <tr class="total-row-main">
                                    <td class="text-nowrap">TOTAL GERAL</td>
                                    <td class="td-right text-nowrap"><?php echo number_format($total_qtd_geral, 2, ',', '.'); ?></td>
                                    <td class="td-right text-nowrap"><?php echo format_money($total_geral); ?></td>
                                    <td class="no-print"></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        Nenhum dado encontrado para os filtros selecionados.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($relatorio_secretarias)): ?>
        <?php foreach ($relatorio_secretarias as $row): ?>
            <?php $sid = (int)$row['secretaria_id']; ?>
            <div
                class="details-modal"
                id="modal-secretaria-<?php echo $sid; ?>"
                aria-hidden="true"
                role="dialog"
                aria-modal="true"
            >
                <div class="details-modal-backdrop" data-close-modal></div>

                <div class="details-modal-dialog">
                    <div class="details-modal-header">
                        <div class="details-modal-title-wrap">
                            <h4 class="details-modal-title">
                                Produtos da secretaria: <?php echo h($row['secretaria_nome']); ?>
                            </h4>
                            <p class="details-modal-subtitle">
                                Visualize abaixo os produtos, fornecedores, quantidades e valores totais desta secretaria.
                            </p>
                        </div>

                        <button type="button" class="details-modal-close" data-close-modal aria-label="Fechar modal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="details-modal-body">
                        <div class="details-modal-summary">
                            <div class="details-mini-card">
                                <p class="details-mini-label">Quantidade total</p>
                                <p class="details-mini-value"><?php echo number_format((float)$row['total_qtd'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="details-mini-card">
                                <p class="details-mini-label">Valor total</p>
                                <p class="details-mini-value"><?php echo format_money($row['total_valor']); ?></p>
                            </div>
                        </div>

                        <div class="details-inner-table-wrap">
                            <div class="table-scroll-x">
                                <table class="details-inner-table">
                                    <thead>
                                        <tr>
                                            <th class="text-nowrap">Produto</th>
                                            <th class="text-nowrap">Fornecedor</th>
                                            <th class="td-right text-nowrap">Qtd</th>
                                            <th class="td-right text-nowrap">Valor Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($detalhes_por_secretaria[$sid])): ?>
                                            <?php foreach ($detalhes_por_secretaria[$sid] as $det): ?>
                                                <tr>
                                                    <td class="text-nowrap" style="font-weight: 700;"><?php echo h($det['produto']); ?></td>
                                                    <td class="text-nowrap"><?php echo h($det['fornecedor']); ?></td>
                                                    <td class="td-right text-nowrap"><?php echo number_format((float)$det['total_qtd'], 2, ',', '.'); ?></td>
                                                    <td class="td-right text-nowrap" style="font-weight: 700;"><?php echo format_money($det['total_valor']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="empty-state">
                                                    Nenhum produto encontrado para esta secretaria.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; margin-top: 1rem;" class="no-print">
                            <button type="button" class="btn btn-danger-soft btn-sm" data-close-modal>
                                <i class="fas fa-times"></i> Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartElement = document.getElementById('chartSecretaria');

    if (chartElement) {
        const labels = <?php echo json_encode($chart_labels, JSON_UNESCAPED_UNICODE); ?>;
        const fullLabels = <?php echo json_encode($chart_labels_full, JSON_UNESCAPED_UNICODE); ?>;
        const values = <?php echo json_encode($chart_values); ?>;

        if (labels.length > 0) {
            const ctx = chartElement.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [
                            '#206bc4',
                            '#2fb344',
                            '#f59e0b',
                            '#d63939',
                            '#6f42c1',
                            '#0ea5e9',
                            '#14b8a6',
                            '#f97316',
                            '#64748b',
                            '#8b5cf6'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 16,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    const index = context[0]?.dataIndex ?? 0;
                                    return fullLabels[index] || labels[index] || '';
                                },
                                label: function(context) {
                                    const index = context.dataIndex ?? 0;
                                    const sigla = labels[index] || '';
                                    const value = Number(context.raw || 0);
                                    return sigla + ': ' + new Intl.NumberFormat('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL'
                                    }).format(value);
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    const body = document.body;
    const modalButtons = document.querySelectorAll('.details-open-modal');
    const modals = document.querySelectorAll('.details-modal');

    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        body.classList.add('body-modal-open');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');

        const hasOpenModal = document.querySelector('.details-modal.open');
        if (!hasOpenModal) {
            body.classList.remove('body-modal-open');
        }
    }

    modalButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            openModal(modal);
        });
    });

    modals.forEach(function(modal) {
        modal.querySelectorAll('[data-close-modal]').forEach(function(closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeModal(modal);
            });
        });
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.details-modal.open').forEach(function(modal) {
                closeModal(modal);
            });
        }
    });
});
</script>

<?php include 'views/layout/footer.php'; ?>