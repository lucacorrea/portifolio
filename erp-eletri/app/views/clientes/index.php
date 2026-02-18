<?php require_once '../app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Clientes</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="?url=clientes/create" class="btn btn-sm btn-primary">
            <i class="bi bi-person-plus"></i> Novo Cliente
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF/CNPJ</th>
                        <th>Cidade/UF</th>
                        <th>Tipo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td>
                            <strong><?php echo $cliente['nome']; ?></strong>
                        </td>
                        <td><?php echo $cliente['cpf_cnpj']; ?></td>
                        <td><?php echo $cliente['cidade'] . '/' . $cliente['estado']; ?></td>
                         <td>
                            <?php if ($cliente['tipo'] == 'pessoa_juridica'): ?>
                                <span class="badge bg-info text-dark">Jurídica</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Física</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?url=clientes/edit/<?php echo $cliente['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../app/views/partials/footer.php'; ?>
