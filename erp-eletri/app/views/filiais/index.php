<?php require_once '../app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Filiais</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> Nova Filial
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nome</th>
                        <th>Cidade/UF</th>
                        <th>Telefone</th>
                        <th>Gerente</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filiais as $filial): ?>
                    <tr>
                        <td><?php echo $filial['id']; ?></td>
                        <td>
                            <strong><?php echo $filial['nome']; ?></strong><br>
                            <small class="text-muted"><?php echo $filial['cnpj'] ?? 'CNPJ não inf.'; ?></small>
                        </td>
                        <td><?php echo ($filial['cidade'] ?? '-') . '/' . ($filial['estado'] ?? '-'); ?></td>
                        <td><?php echo $filial['telefone'] ?? '-'; ?></td>
                        <td><?php echo $filial['gerente'] ?? '-'; ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../app/views/partials/footer.php'; ?>
