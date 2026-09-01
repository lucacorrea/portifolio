<?php
$pageTitle = 'Configurações';
$currentPage = 'configuracoes';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Configurações', 'Dados da oficina e parâmetros básicos do sistema.', [
    ['label' => 'Salvar alterações', 'href' => '#', 'icon' => 'service', 'class' => 'btn-primary']
]) ?>

<div class="two-col">
    <div class="card section-card">
        <div class="section-title"><h2>Dados da oficina</h2></div>
        <div class="form-row">
            <div class="form-group" style="grid-column:1/-1">
                <label>Nome da oficina</label>
                <input class="input" value="Bianka Oficina Mecânica">
            </div>
            <div class="form-group">
                <label>CNPJ</label>
                <input class="input" placeholder="00.000.000/0000-00">
            </div>
            <div class="form-group">
                <label>Telefone</label>
                <input class="input" placeholder="(92) 99999-9999">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>Endereço</label>
                <input class="input" placeholder="Rua, número, bairro">
            </div>
            <div class="form-group">
                <label>Cidade</label>
                <input class="input" value="Coari">
            </div>
            <div class="form-group">
                <label>UF</label>
                <input class="input" value="AM">
            </div>
        </div>
    </div>

    <div class="card section-card">
        <div class="section-title"><h2>Preferências</h2></div>
        <div class="operation-list">
            <div class="operation-item"><div class="operation-copy"><strong>Permitir desconto em OS</strong><span>Controle financeiro</span></div><div><input type="checkbox" checked></div></div>
            <div class="operation-item"><div class="operation-copy"><strong>Controlar estoque mínimo</strong><span>Reposição automática</span></div><div><input type="checkbox" checked></div></div>
            <div class="operation-item"><div class="operation-copy"><strong>Exigir mecânico na OS</strong><span>Vinculação obrigatória</span></div><div><input type="checkbox" checked></div></div>
            <div class="operation-item"><div class="operation-copy"><strong>Numeração automática</strong><span>OS sequencial</span></div><div><input type="checkbox" checked></div></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
