<?php
$pageTitle = 'Nova Ordem de Serviço';
$currentPage = 'ordens';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div><h1>Nova Ordem de Serviço</h1><p>Preencha os dados de entrada do veículo e os itens da OS.</p></div>
    <div class="actions"><a class="btn" href="ordens.php">Cancelar</a></div>
</div>

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
            <div class="form-grid">
                <div class="form-group"><label>Cliente</label><select class="select"><option>Selecione...</option><option>João da Silva</option><option>Maria Souza</option></select></div>
                <div class="form-group"><label>Veículo</label><select class="select"><option>Selecione...</option><option>Toyota Hilux • ABC1D23</option><option>Chevrolet Onix • DEF4G56</option></select></div>
                <div class="form-group"><label>Quilometragem</label><input class="input" placeholder="Ex.: 83520"></div>
                <div class="form-group"><label>Mecânico responsável</label><select class="select"><option>Carlos Alberto</option><option>Paulo Henrique</option><option>Renato Costa</option></select></div>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><h2>Entrada do veículo</h2></div>
            <div class="form-grid">
                <div class="form-group"><label>Data de entrada</label><input class="input" type="date" value="2026-09-01"></div>
                <div class="form-group"><label>Previsão de entrega</label><input class="input" type="date"></div>
                <div class="form-group full"><label>Relato do cliente</label><textarea class="input" placeholder="Descreva o problema informado pelo cliente"></textarea></div>
                <div class="form-group full"><label>Observações técnicas</label><textarea class="input" placeholder="Avaliação inicial do veículo"></textarea></div>
            </div>
        </div>

        <div class="card section-card">
            <div class="section-title"><h2>Serviços</h2><button class="btn"><i class="icon icon-plus"></i> Adicionar serviço</button></div>
            <div class="table-wrap"><table><thead><tr><th>Serviço</th><th>Qtd.</th><th>Valor</th><th>Total</th><th></th></tr></thead><tbody><tr><td>Troca de óleo</td><td>1</td><td>R$ 150,00</td><td class="money">R$ 150,00</td><td><button class="btn btn-danger">Remover</button></td></tr></tbody></table></div>
        </div>

        <div class="card section-card">
            <div class="section-title"><h2>Peças</h2><button class="btn"><i class="icon icon-plus"></i> Adicionar peça</button></div>
            <div class="table-wrap"><table><thead><tr><th>Peça</th><th>Qtd.</th><th>Valor</th><th>Total</th><th></th></tr></thead><tbody><tr><td>Óleo 5W30</td><td>5</td><td>R$ 65,00</td><td class="money">R$ 325,00</td><td><button class="btn btn-danger">Remover</button></td></tr></tbody></table></div>
        </div>
    </div>

    <div>
        <div class="card section-card" style="position:sticky;top:92px">
            <div class="section-title"><h2>Resumo</h2></div>
            <div class="summary-box">
                <div class="summary-line"><span>Serviços</span><strong>R$ 150,00</strong></div>
                <div class="summary-line"><span>Peças</span><strong>R$ 325,00</strong></div>
                <div class="summary-line"><span>Desconto</span><strong>R$ 0,00</strong></div>
                <div class="summary-total"><span>Total</span><span>R$ 475,00</span></div>
            </div>
            <button class="btn btn-primary" style="width:100%;justify-content:center;margin-top:14px"><i class="icon icon-check"></i> Criar ordem de serviço</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
