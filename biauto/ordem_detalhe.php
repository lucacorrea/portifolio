<?php
$pageTitle = 'OS #00128';
$currentPage = 'ordens';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('OS #00128', 'Toyota Hilux SRV • ABC1D23', [
    ['label' => 'Imprimir', 'href' => '#', 'icon' => 'report', 'class' => 'btn-secondary'],
    ['label' => 'Finalizar serviço', 'href' => '#', 'icon' => 'service', 'class' => 'btn-primary']
]) ?>

<div class="two-col">
    <div>
        <div class="card section-card">
            <div class="section-title"><h2>Dados da ordem</h2></div>
            <div class="form-row">
                <div><label class="muted">Cliente</label><div><strong>João da Silva</strong></div></div>
                <div><label class="muted">Telefone</label><div><strong>(92) 99999-1111</strong></div></div>
                <div><label class="muted">Veículo</label><div><strong>Toyota Hilux SRV</strong></div></div>
                <div><label class="muted">Placa</label><div><strong>ABC1D23</strong></div></div>
                <div><label class="muted">KM entrada</label><div><strong>83.520 km</strong></div></div>
                <div><label class="muted">Mecânico</label><div><strong>Carlos Alberto</strong></div></div>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><h2>Serviços e peças</h2></div>
            <div class="table-shell">
                <table class="table">
                    <thead><tr><th>Tipo</th><th>Descrição</th><th>Qtd.</th><th>Unitário</th><th>Total</th></tr></thead>
                    <tbody>
                        <tr><td>Serviço</td><td>Revisão de freios</td><td>1</td><td>R$ 250,00</td><td class="money">R$ 250,00</td></tr>
                        <tr><td>Peça</td><td>Pastilha dianteira</td><td>1</td><td>R$ 260,00</td><td class="money">R$ 260,00</td></tr>
                        <tr><td>Serviço</td><td>Troca de óleo</td><td>1</td><td>R$ 150,00</td><td class="money">R$ 150,00</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="card section-card">
            <div class="section-title"><h2>Valores</h2></div>
            <div class="summary-box">
                <div class="summary-line"><span>Serviços</span><strong>R$ 400,00</strong></div>
                <div class="summary-line"><span>Peças</span><strong>R$ 1.450,00</strong></div>
                <div class="summary-total"><span>Total</span><span>R$ 1.850,00</span></div>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><h2>Histórico da OS</h2></div>
            <div class="timeline">
                <div class="timeline-item"><strong>OS criada</strong><small>01/09/2026 às 08:42</small></div>
                <div class="timeline-item"><strong>Diagnóstico registrado</strong><small>01/09/2026 às 09:15</small></div>
                <div class="timeline-item"><strong>Serviço iniciado</strong><small>01/09/2026 às 09:40</small></div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
