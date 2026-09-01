<?php
$pageTitle = 'Clientes';
$currentPage = 'clientes';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div><h1>Clientes</h1><p>Cadastro e histórico dos clientes da oficina.</p></div>
    <div class="actions"><button class="btn btn-primary"><i class="icon icon-user-plus"></i> Novo cliente</button></div>
</div>
<div class="card section-card">
    <div class="filters">
        <div class="search"><i class="icon icon-search"></i><input class="input" placeholder="Nome, CPF/CNPJ ou telefone"></div>
        <select class="select" style="max-width:180px"><option>Todos</option><option>Pessoa Física</option><option>Pessoa Jurídica</option></select>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cliente</th><th>CPF/CNPJ</th><th>Telefone</th><th>Veículos</th><th>Último serviço</th><th></th></tr></thead>
            <tbody>
                <tr><td>João da Silva</td><td>***.***.***-12</td><td>(92) 99999-1111</td><td>2</td><td>29/08/2026</td><td><button class="btn">Ver ficha</button></td></tr>
                <tr><td>Maria Souza</td><td>***.***.***-44</td><td>(92) 98888-2222</td><td>1</td><td>01/09/2026</td><td><button class="btn">Ver ficha</button></td></tr>
                <tr><td>Lucas Lima</td><td>***.***.***-78</td><td>(92) 97777-3333</td><td>1</td><td>31/08/2026</td><td><button class="btn">Ver ficha</button></td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
