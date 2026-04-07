<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
view_check();

$page_title = "Relatórios Inteligentes";

// Filtros
$sec_id = $_GET['sec_id'] ?? '';
$forn_id = $_GET['forn_id'] ?? '';
$produto = $_GET['produto'] ?? '';
$periodo_inicio = $_GET['inicio'] ?? '';
$periodo_fim = $_GET['fim'] ?? '';

$where = "TRUE";
if($sec_id) $where .= " AND o.secretaria_id = $sec_id";
if($forn_id) $where .= " AND a.fornecedor_id = $forn_id";
if($produto) $where .= " AND ia.produto LIKE '%$produto%'";
if($periodo_inicio) $where .= " AND a.criado_em >= '$periodo_inicio 00:00:00'";
if($periodo_fim) $where .= " AND a.criado_em <= '$periodo_fim 23:59:59'";

// Total por Produto
$stmt_prod = $pdo->query("
    SELECT ia.produto, SUM(ia.quantidade) as total_qtd, SUM(ia.quantidade * ia.valor_unitario) as total_valor, f.nome as fornecedor
    FROM itens_aquisicao ia
    JOIN aquisicoes a ON ia.aquisicao_id = a.id
    JOIN oficios o ON a.oficio_id = o.id
    JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE $where
    GROUP BY ia.produto, f.id
    ORDER BY total_qtd DESC
");
$relatorio_produtos = $stmt_prod->fetchAll();

// Total Gasto por Secretaria
$stmt_sec_totais = $pdo->query("
    SELECT s.nome, SUM(a.valor_total) as total
    FROM aquisicoes a
    JOIN oficios o ON a.oficio_id = o.id
    JOIN secretarias s ON o.secretaria_id = s.id
    WHERE $where
    GROUP BY s.id
");
$gastos_secretaria = $stmt_sec_totais->fetchAll();

$secretarias = $pdo->query("SELECT * FROM secretarias ORDER BY nome")->fetchAll();
$fornecedores = $pdo->query("SELECT * FROM fornecedores ORDER BY nome")->fetchAll();

include 'views/layout/header.php';
?>

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
                        <?php foreach($secretarias as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $sec_id == $s['id'] ? 'selected' : ''; ?>><?php echo $s['nome']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Fornecedor</label>
                    <select name="forn_id" class="form-control">
                        <option value="">Todos</option>
                        <?php foreach($fornecedores as $f): ?>
                            <option value="<?php echo $f['id']; ?>" <?php echo $forn_id == $f['id'] ? 'selected' : ''; ?>><?php echo $f['nome']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nome do Produto</label>
                    <input type="text" name="produto" class="form-control" placeholder="Buscar produto..." value="<?php echo $produto; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Período de Início</label>
                    <input type="date" name="inicio" class="form-control" value="<?php echo $periodo_inicio; ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Período de Fim</label>
                    <input type="date" name="fim" class="form-control" value="<?php echo $periodo_fim; ?>">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="relatorios.php" class="btn btn-outline btn-sm">Limpar</a>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> Gerar Relatório</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-6" style="margin-bottom: 1.5rem;">
        <div class="card" style="height: 100%;">
            <div class="card-body" style="height: 100%; display: flex; flex-direction: column;">
                <h3 class="card-title" style="margin-bottom: 1.5rem; font-weight: 700; font-size: 1rem;">
                    <i class="fas fa-chart-pie" style="margin-right: 8px; color: var(--primary);"></i> Gastos por Secretaria
                </h3>
                <div style="flex-grow: 1; min-height: 300px; position: relative;">
                    <canvas id="chartSecretaria"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6" style="margin-bottom: 1.5rem;">
        <div class="card" style="height: 100%;">
            <div class="card-body" style="display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 3rem;">
                <h3 class="card-label" style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem;">TOTAL INVESTIDO NO PERÍODO</h3>
                <?php $total_geral = array_sum(array_column($gastos_secretaria, 'total')); ?>
                <div class="card-number" style="font-size: 3rem; color: var(--primary); margin: 0; line-height: 1;"><?php echo format_money($total_geral); ?></div>
                <div style="margin-top: 2rem; color: var(--text-muted); font-size: 0.875rem;">
                    <p><i class="fas fa-info-circle"></i> Valores calculados com base nas aquisições finalizadas.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1rem;">
                <i class="fas fa-table" style="margin-right: 10px; color: var(--primary);"></i> Detalhamento por Produto
            </h3>
            <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i> Exportar PDF</button>
        </div>
        <div class="table-responsive">
            <table class="table-vcenter">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Fornecedor</th>
                        <th style="text-align: right;">Qtd Total</th>
                        <th style="text-align: right;">Gasto Total (R$)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($relatorio_produtos as $rp): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary);"><?php echo $rp['produto']; ?></td>
                        <td><span class="text-muted"><?php echo $rp['fornecedor']; ?></span></td>
                        <td style="text-align: right;"><?php echo number_format($rp['total_qtd'], 2, ',', '.'); ?></td>
                        <td style="text-align: right; font-weight: 600;"><?php echo format_money($rp['total_valor']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($relatorio_produtos)): ?>
                        <tr><td colspan="4" style="text-align:center; padding: 3rem; color: var(--text-muted);">Nenhum dado encontrado para os filtros selecionados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('chartSecretaria').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($gastos_secretaria, 'nome')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($gastos_secretaria, 'total')); ?>,
                backgroundColor: ['#206bc4', '#2fb344', '#f59e0b', '#d63939', '#4299e1', '#626976']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { family: 'Inter' } } }
            }
        }
    });
});
</script>

<?php include 'views/layout/footer.php'; ?>
