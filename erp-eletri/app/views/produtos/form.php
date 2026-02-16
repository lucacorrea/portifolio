<?php require_once '../app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo ($action == 'create') ? 'Novo Produto' : 'Editar Produto'; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="?url=produtos/index" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?url=produtos/<?php echo $action; ?><?php echo ($action == 'edit') ? '/' . $product['id'] : ''; ?>">
            
            <div class="row g-3">
                <!-- Identificação -->
                <div class="col-md-12">
                     <h6 class="text-muted border-bottom pb-2">Identificação</h6>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Código Interno</label>
                    <input type="text" class="form-control" name="codigo_interno" value="<?php echo $product['codigo_interno'] ?? ''; ?>" required>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Código de Barras (EAN)</label>
                    <input type="text" class="form-control" name="codigo_barras" value="<?php echo $product['codigo_barras'] ?? ''; ?>">
                </div>

                <div class="col-md-7">
                    <label class="form-label">Nome do Produto</label>
                    <input type="text" class="form-control" name="nome" value="<?php echo $product['nome'] ?? ''; ?>" required>
                </div>

                <!-- Classificação -->
                <div class="col-md-4">
                    <label class="form-label">Categoria</label>
                    <select class="form-select" name="categoria_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo (isset($product['categoria_id']) && $product['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo $cat['nome']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Unidade</label>
                     <select class="form-select" name="unidade">
                        <option value="UN" <?php echo (isset($product['unidade']) && $product['unidade'] == 'UN') ? 'selected' : ''; ?>>Unidade (UN)</option>
                        <option value="M" <?php echo (isset($product['unidade']) && $product['unidade'] == 'M') ? 'selected' : ''; ?>>Metro (M)</option>
                        <option value="KG" <?php echo (isset($product['unidade']) && $product['unidade'] == 'KG') ? 'selected' : ''; ?>>Quilo (KG)</option>
                        <option value="CX" <?php echo (isset($product['unidade']) && $product['unidade'] == 'CX') ? 'selected' : ''; ?>>Caixa (CX)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">NCM</label>
                    <input type="text" class="form-control" name="ncm" value="<?php echo $product['ncm'] ?? ''; ?>">
                </div>

                 <!-- Preços -->
                <div class="col-md-12 mt-4">
                     <h6 class="text-muted border-bottom pb-2">Precificação</h6>
                </div>

                 <div class="col-md-3">
                    <label class="form-label">Preço de Custo</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control" name="preco_custo" value="<?php echo isset($product['preco_custo']) ? number_format($product['preco_custo'], 2, ',', '') : '0,00'; ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Preço de Venda (Normal)</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control fw-bold" name="preco_venda" value="<?php echo isset($product['preco_venda']) ? number_format($product['preco_venda'], 2, ',', '') : '0,00'; ?>" required>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-success">Preço À Vista (-10%)</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control" name="preco_avista" value="<?php echo isset($product['preco_avista']) ? number_format($product['preco_avista'], 2, ',', '') : '0,00'; ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-info">Preço Prefeitura (+15%)</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control" name="preco_prefeitura" value="<?php echo isset($product['preco_prefeitura']) ? number_format($product['preco_prefeitura'], 2, ',', '') : '0,00'; ?>">
                    </div>
                </div>

                <!-- Estoque (Apenas visualização na edição) -->
                <?php if ($action == 'edit'): ?>
                <div class="col-md-12 mt-4">
                     <h6 class="text-muted border-bottom pb-2">Estoque Atual</h6>
                     <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Filial</th>
                                    <th>Quantidade</th>
                                    <th>Localização</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estoque as $est): ?>
                                <tr>
                                    <td><?php echo $est['filial_nome']; ?></td>
                                    <td><?php echo $est['quantidade']; ?></td>
                                    <td><?php echo $est['localizacao']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                     </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="mt-4 text-end">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save"></i> Salvar Produto
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../app/views/partials/footer.php'; ?>
