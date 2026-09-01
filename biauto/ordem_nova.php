<?php
$pageTitle = 'Nova Ordem de Serviço';
$currentPage = 'ordens';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Nova Ordem de Serviço', 'Preencha os dados do atendimento e monte a OS.', [
    ['label' => 'Cancelar', 'href' => 'ordens.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<div class="steps">
    <div class="step active">1. Cliente e veículo</div>
    <div class="step">2. Entrada</div>
    <div class="step">3. Serviços</div>
    <div class="step">4. Peças</div>
    <div class="step">5. Valores</div>
    <div class="step">6. Confirmar</div>
</div>

<div class="two-col">
    <div>
        <div class="card section-card">
            <div class="section-title"><h2>Cliente e veículo</h2></div>
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente</label>
                    <select class="select"><option>Selecione...</option><option>João da Silva</option><option>Maria Souza</option></select>
                </div>
                <div class="form-group">
                    <label>Veículo</label>
                    <select class="select"><option>Selecione...</option><option>Toyota Hilux • ABC1D23</option><option>Chevrolet Onix • DEF4G56</option></select>
                </div>
                <div class="form-group">
                    <label>Quilometragem</label>
                    <input class="input" placeholder="Ex.: 83520">
                </div>
                <div class="form-group">
                    <label>Mecânico responsável</label>
                    <select class="select"><option>Carlos Alberto</option><option>Paulo Henrique</option><option>Renato Costa</option></select>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><h2>Entrada e diagnóstico</h2></div>
            <div class="form-row">
                <div class="form-group">
                    <label>Data de entrada</label>
                    <input type="date" class="input" value="2026-09-01">
                </div>
                <div class="form-group">
                    <label>Previsão de entrega</label>
                    <input type="date" class="input">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Relato do cliente</label>
                    <textarea class="input" placeholder="Descreva o problema informado pelo cliente"></textarea>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Observações técnicas</label>
                    <textarea class="input" placeholder="Avaliação técnica inicial"></textarea>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><h2>Serviços</h2><a href="#" class="btn"><?= ui_icon('plus') ?><span>Adicionar serviço</span></a></div>
            <div class="table-shell">
                <table class="table">
                    <thead><tr><th>Serviço</th><th>Qtd.</th><th>Valor</th><th>Total</th><th></th></tr></thead>
                    <tbody>
                        <tr><td>Troca de óleo</td><td>1</td><td>R$ 150,00</td><td class="money">R$ 150,00</td><td><a href="#" class="btn btn-danger">Remover</a></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><h2>Peças</h2><a href="#" class="btn"><?= ui_icon('plus') ?><span>Adicionar peça</span></a></div>
            <div class="table-shell">
                <table class="table">
                    <thead><tr><th>Peça</th><th>Qtd.</th><th>Valor</th><th>Total</th><th></th></tr></thead>
                    <tbody>
                        <tr><td>Óleo 5W30</td><td>5</td><td>R$ 65,00</td><td class="money">R$ 325,00</td><td><a href="#" class="btn btn-danger">Remover</a></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="card section-card" style="position:sticky;top:106px">
            <div class="section-title"><h2>Resumo da OS</h2></div>
            <div class="summary-box">
                <div class="summary-line"><span>Serviços</span><strong>R$ 150,00</strong></div>
                <div class="summary-line"><span>Peças</span><strong>R$ 325,00</strong></div>
                <div class="summary-line"><span>Desconto</span><strong>R$ 0,00</strong></div>
                <div class="summary-total"><span>Total</span><span>R$ 475,00</span></div>
            </div>
            <div style="margin-top:14px;display:grid;gap:10px">
                <a href="#" class="btn btn-primary" style="justify-content:center"><?= ui_icon('plus') ?><span>Criar OS</span></a>
                <a href="ordens.php" class="btn" style="justify-content:center">Voltar</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
