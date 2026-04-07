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
            body { font-family: Arial, sans-serif; font-size: 12px; color: #222; }
            .title { font-size: 18px; font-weight: bold; margin-bottom: 6px; }
            .subtitle { font-size: 12px; margin-bottom: 16px; }
            .filters { margin-bottom: 18px; }
            .filters div { margin-bottom: 4px; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 18px; }
            th, td { border: 1px solid #000; padding: 8px; vertical-align: middle; }
            th { background: #d9d9d9; text-align: center; font-weight: bold; }
            .right { text-align: right; }
            .center { text-align: center; }
            .section { font-size: 14px; font-weight: bold; margin: 16px 0 8px; }
            .total-box { margin-bottom: 18px; }
        </style>
    </head>
    <body>
        <div class="title">RELATÓRIO DE AQUISIÇÕES POR SECRETARIA</div>
        <div class="subtitle">Gerado em <?php echo date('d/m/Y H:i:s'); ?></div>

        <div class="filters">
            <div><strong>Detalhes:</strong> Totais por secretaria e detalhamento de produtos por secretaria</div>
            <div><strong>Secretaria:</strong> <?php echo h($nome_secretaria_filtro); ?></div>
            <div><strong>Fornecedor:</strong> <?php echo h($nome_fornecedor_filtro); ?></div>
            <div><strong>Produto:</strong> <?php echo $produto !== '' ? h($produto) : 'Todos'; ?></div>
            <div><strong>Período:</strong> <?php echo h($periodo_texto); ?></div>
        </div>

        <div class="total-box">
            <table>
                <tr>
                    <th>Total Geral de Quantidade</th>
                    <th>Total Geral Financeiro</th>
                </tr>
                <tr>
                    <td class="center"><?php echo number_format($total_qtd_geral, 2, ',', '.'); ?></td>
                    <td class="right"><?php echo format_money($total_geral); ?></td>
                </tr>
            </table>
        </div>

        <div class="section">RESUMO POR SECRETARIA</div>
        <table>
            <thead>
                <tr>
                    <th>Secretaria</th>
                    <th>Qtd Total</th>
                    <th>Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($relatorio_secretarias)): ?>
                    <?php foreach ($relatorio_secretarias as $row): ?>
                        <tr>
                            <td><?php echo h($row['secretaria_nome']); ?></td>
                            <td class="center"><?php echo number_format((float)$row['total_qtd'], 2, ',', '.'); ?></td>
                            <td class="right"><?php echo format_money($row['total_valor']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><strong>TOTAL GERAL</strong></td>
                        <td class="center"><strong><?php echo number_format($total_qtd_geral, 2, ',', '.'); ?></strong></td>
                        <td class="right"><strong><?php echo format_money($total_geral); ?></strong></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="center">Nenhum dado encontrado para os filtros selecionados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="section">DETALHES DOS PRODUTOS POR SECRETARIA</div>

        <?php if (!empty($relatorio_secretarias)): ?>
            <?php foreach ($relatorio_secretarias as $sec): ?>
                <?php $sid = (int)$sec['secretaria_id']; ?>
                <table>
                    <thead>
                        <tr>
                            <th colspan="4" style="text-align:left;">
                                Secretaria: <?php echo h($sec['secretaria_nome']); ?> |
                                Total: <?php echo format_money($sec['total_valor']); ?>
                            </th>
                        </tr>
                        <tr>
                            <th>Produto</th>
                            <th>Fornecedor</th>
                            <th>Qtd</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($detalhes_por_secretaria[$sid])): ?>
                            <?php foreach ($detalhes_por_secretaria[$sid] as $det): ?>
                                <tr>
                                    <td><?php echo h($det['produto']); ?></td>
                                    <td><?php echo h($det['fornecedor']); ?></td>
                                    <td class="center"><?php echo number_format((float)$det['total_qtd'], 2, ',', '.'); ?></td>
                                    <td class="right"><?php echo format_money($det['total_valor']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="center">Sem detalhes para esta secretaria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

include 'views/layout/header.php';
?>

<style>
    .report-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .details-row {
        display: none;
        background: #f8fafc;
    }

    .details-row.open {
        display: table-row;
    }

    .details-box {
        padding: 1rem;
    }

    .details-title {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text-dark);
        margin-bottom: 1rem;
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

    .badge-total {
        display: inline-block;
        padding: 0.35rem 0.65rem;
        border-radius: 999px;
        background: rgba(32, 107, 196, 0.10);
        color: #206bc4;
        font-weight: 700;
        font-size: 0.8rem;
    }

    .chart-card-body {
        padding: 1.25rem;
    }

    .chart-wrap {
        position: relative;
        width: 100%;
        height: 280px;
        max-height: 280px;
    }

    .total-card-body {
        padding: 2rem 1.5rem;
    }

    .total-card-number {
        font-size: 2.3rem !important;
        line-height: 1.1 !important;
        word-break: break-word;
    }

    @media (max-width: 768px) {
        .report-actions {
            width: 100%;
        }

        .report-actions .btn {
            flex: 1 1 100%;
            justify-content: center;
        }

        .chart-wrap {
            height: 240px;
            max-height: 240px;
        }

        .total-card-body {
            padding: 1.5rem 1rem;
        }

        .total-card-number {
            font-size: 1.8rem !important;
        }
    }

    @media print {
        .no-print,
        .btn,
        button,
        .report-actions,
        .details-toggle {
            display: none !important;
        }

        .details-row {
            display: table-row !important;
        }

        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
    }
</style>

<div class="card no-print">
    <div class="card-body">
        <h3 class="card-title" style="margin-bottom: 1.5rem; font-weight: 700; font-size: 1rem;">
            <i class="fas fa-filter" style="margin-right: 5px; color: var(--primary);"></i> Filtros de Relatório
        </h3>

        <form action="" method="GET">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
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

            <div style="display: flex; justify-content: flex-end; gap: 1rem; flex-wrap: wrap;">
                <a href="relatorios.php" class="btn btn-outline btn-sm">Limpar</a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-sync"></i> Gerar Relatório
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-12" style="margin-bottom: 1.25rem;">
        <div class="card">
            <div class="card-body chart-card-body">
                <h3 class="card-title" style="margin-bottom: 1rem; font-weight: 700; font-size: 1rem;">
                    <i class="fas fa-chart-pie" style="margin-right: 8px; color: var(--primary);"></i> Gastos por Secretaria
                </h3>
                <div class="chart-wrap">
                    <canvas id="chartSecretaria"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12" style="margin-bottom: 1.5rem;">
        <div class="card">
            <div class="card-body total-card-body" style="display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;">
                <h3 class="card-label" style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                    TOTAL GERAL DE TODAS AS SECRETARIAS
                </h3>

                <div class="card-number total-card-number" style="color: var(--primary); margin: 0;">
                    <?php echo format_money($total_geral); ?>
                </div>

                <div style="margin-top: 0.85rem; color: var(--text-muted); font-size: 0.95rem;">
                    Quantidade total acumulada:
                    <strong><?php echo number_format($total_qtd_geral, 2, ',', '.'); ?></strong>
                </div>

                <div style="margin-top: 1.25rem; color: var(--text-muted); font-size: 0.875rem;">
                    <p style="margin: 0;"><i class="fas fa-info-circle"></i> Valores calculados com base nos itens das aquisições filtradas.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1rem; margin: 0;">
                <i class="fas fa-building" style="margin-right: 10px; color: var(--primary);"></i> Detalhamento por Secretaria
            </h3>

            <div class="report-actions no-print">
                <a
                    href="?<?php echo h(http_build_query(array_merge($_GET, ['export' => 'excel']))); ?>"
                    class="btn btn-success-custom btn-sm"
                >
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </a>

                <button onclick="window.print()" class="btn btn-outline btn-sm">
                    <i class="fas fa-print"></i> Exportar PDF
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table-vcenter">
                <thead>
                    <tr>
                        <th>Secretaria</th>
                        <th style="text-align: right;">Qtd Total</th>
                        <th style="text-align: right;">Valor Total</th>
                        <th class="no-print" style="text-align: center; width: 140px;">Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($relatorio_secretarias)): ?>
                        <?php foreach ($relatorio_secretarias as $row): ?>
                            <?php $sid = (int)$row['secretaria_id']; ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--primary);">
                                    <?php echo h($row['secretaria_nome']); ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php echo number_format((float)$row['total_qtd'], 2, ',', '.'); ?>
                                </td>
                                <td style="text-align: right; font-weight: 700;">
                                    <span class="badge-total"><?php echo format_money($row['total_valor']); ?></span>
                                </td>
                                <td class="no-print" style="text-align: center;">
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm details-toggle"
                                        data-target="details-<?php echo $sid; ?>"
                                    >
                                        <i class="fas fa-search"></i> Detalhes
                                    </button>
                                </td>
                            </tr>

                            <tr id="details-<?php echo $sid; ?>" class="details-row">
                                <td colspan="4">
                                    <div class="details-box">
                                        <div class="details-title">
                                            Produtos da secretaria: <?php echo h($row['secretaria_nome']); ?>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table-vcenter" style="margin-bottom: 0;">
                                                <thead>
                                                    <tr>
                                                        <th>Produto</th>
                                                        <th>Fornecedor</th>
                                                        <th style="text-align: right;">Qtd</th>
                                                        <th style="text-align: right;">Valor Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($detalhes_por_secretaria[$sid])): ?>
                                                        <?php foreach ($detalhes_por_secretaria[$sid] as $det): ?>
                                                            <tr>
                                                                <td style="font-weight: 600;"><?php echo h($det['produto']); ?></td>
                                                                <td><?php echo h($det['fornecedor']); ?></td>
                                                                <td style="text-align: right;"><?php echo number_format((float)$det['total_qtd'], 2, ',', '.'); ?></td>
                                                                <td style="text-align: right; font-weight: 600;"><?php echo format_money($det['total_valor']); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="4" style="text-align:center; padding: 1.5rem; color: var(--text-muted);">
                                                                Nenhum produto encontrado para esta secretaria.
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr style="background: rgba(32, 107, 196, 0.06);">
                            <td style="font-weight: 800;">TOTAL GERAL</td>
                            <td style="text-align: right; font-weight: 800;">
                                <?php echo number_format($total_qtd_geral, 2, ',', '.'); ?>
                            </td>
                            <td style="text-align: right; font-weight: 800;">
                                <?php echo format_money($total_geral); ?>
                            </td>
                            <td class="no-print"></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding: 3rem; color: var(--text-muted);">
                                Nenhum dado encontrado para os filtros selecionados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartElement = document.getElementById('chartSecretaria');

    if (chartElement) {
        const labels = <?php echo json_encode(array_column($relatorio_secretarias, 'secretaria_nome'), JSON_UNESCAPED_UNICODE); ?>;
        const values = <?php echo json_encode(array_map('floatval', array_column($relatorio_secretarias, 'total_valor'))); ?>;

        if (labels.length > 0) {
            const ctx = chartElement.getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#206bc4', '#2fb344', '#f59e0b', '#d63939', '#4299e1', '#626976', '#8e44ad', '#16a085', '#e67e22', '#34495e']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: { family: 'Inter' }
                            }
                        }
                    }
                }
            });
        }
    }

    document.querySelectorAll('.details-toggle').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const row = document.getElementById(targetId);

            if (!row) return;

            row.classList.toggle('open');

            if (row.classList.contains('open')) {
                this.innerHTML = '<i class="fas fa-times"></i> Fechar';
            } else {
                this.innerHTML = '<i class="fas fa-search"></i> Detalhes';
            }
        });
    });
});
</script>

<?php include 'views/layout/footer.php'; ?>