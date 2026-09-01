<?php
$pageTitle = 'Configurações';
$currentPage = 'configuracoes';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div><h1>Configurações</h1><p>Dados da oficina e parâmetros básicos do sistema.</p></div>
    <div class="actions"><button class="btn btn-primary">Salvar alterações</button></div>
</div>
<div class="two-col">
    <div class="card section-card">
        <div class="section-title"><h2>Dados da oficina</h2></div>
        <div class="form-grid">
            <div class="form-group full"><label>Nome da oficina</label><input class="input" value="Bianka Oficina Mecânica"></div>
            <div class="form-group"><label>CNPJ</label><input class="input" placeholder="00.000.000/0000-00"></div>
            <div class="form-group"><label>Telefone</label><input class="input" placeholder="(92) 99999-9999"></div>
            <div class="form-group full"><label>Endereço</label><input class="input" placeholder="Rua, número, bairro"></div>
            <div class="form-group"><label>Cidade</label><input class="input" value="Coari"></div>
            <div class="form-group"><label>UF</label><input class="input" value="AM"></div>
        </div>
    </div>
    <div class="card section-card">
        <div class="section-title"><h2>Preferências</h2></div>
        <ul class="list-clean">
            <li><span>Permitir desconto em OS</span><input type="checkbox" checked></li>
            <li><span>Controlar estoque mínimo</span><input type="checkbox" checked></li>
            <li><span>Exigir mecânico na OS</span><input type="checkbox" checked></li>
            <li><span>Numeração automática de OS</span><input type="checkbox" checked></li>
        </ul>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
