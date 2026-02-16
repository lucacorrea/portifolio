<?php require_once '../app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Produtos</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="?url=produtos/create" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> Novo Produto
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Cód.</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Preço Venda</th>
                        <th>Preço À Vista</th>
                        <th>Estoque (Matriz)</th> <!-- Simplificação visual -->
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo $product['codigo_interno']; ?></td>
                        <td>
                            <strong><?php echo $product['nome']; ?></strong><br>
                            <small class="text-muted"><?php echo $product['codigo_barras']; ?></small>
                        </td>
                        <td><?php echo $product['categoria_nome']; ?></td>
                        <td>R$ <?php echo number_format($product['preco_venda'], 2, ',', '.'); ?></td>
                        <td>R$ <?php echo number_format($product['preco_avista'], 2, ',', '.'); ?></td>
                        <td>
                            <?php 
                                // Idealmente buscaria o estoque da view, mas vamos deixar simples por enquanto
                                echo '-'; 
                            ?>
                        </td>
                        <td>
                            <a href="?url=produtos/edit/<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-secondary">
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
