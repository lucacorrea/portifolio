<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 bg-light">
            <div class="card-body p-4">
                <div class="text-muted small fw-bold text-uppercase mb-2">Faturamento Bruto (Mes)</div>
                <h2 class="fw-bold mb-0"><?= formatarMoeda($receita ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 bg-light">
            <div class="card-body p-4">
                <div class="text-muted small fw-bold text-uppercase mb-2">Custos Totais (Mes)</div>
                <h2 class="fw-bold mb-0 text-danger">- <?= formatarMoeda($totalCustos ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-primary border-0 shadow-sm h-100 bg-primary text-black">
            <div class="card-body p-4">
                <div class="text-white-50 small fw-bold text-uppercase mb-2">Lucro Operacional Estimado</div>
                <h2 class="fw-bold mb-0"><?= formatarMoeda($lucro ?? 0) ?></h2>
                <div class="mt-2 small fw-bold">
                    Margem Operacional: <?= number_format($percentual ?? 0, 1) ?>%
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold">Análise Prospectiva</h6>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-4">
            A rentabilidade é calculada subtraindo-se os <b>Custos Fixos</b> e <b>Variáveis</b> lançados do faturamento total de vendas concluídas no período.
        </p>
        <div class="progress mb-3" style="height: 30px;">
            <?php 
                $custoPerc = $receita > 0 ? (($totalCustos / $receita) * 100) : 0;
                $lucroPerc = 100 - $custoPerc;
            ?>
            <div class="progress-bar bg-danger" style="width: <?= $custoPerc ?>%" title="Custos: <?= number_format($custoPerc, 1) ?>%">Custos</div>
            <div class="progress-bar bg-success" style="width: <?= $lucroPerc ?>%" title="Lucro: <?= number_format($lucroPerc, 1) ?>%">Lucro</div>
        </div>
        <div class="d-flex justify-content-between small fw-bold">
            <span class="text-danger"><?= number_format($custoPerc, 1) ?>% Absorvido por Custos</span>
            <span class="text-success"><?= number_format($lucroPerc, 1) ?>% Margem de Contribuição</span>
        </div>
    </div>
</div>
