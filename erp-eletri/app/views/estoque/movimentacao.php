<?php require_once '../app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Nova Movimentação</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="?url=estoque" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?url=estoque/movimentacao">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Produto</label>
                    <select class="form-select" name="produto_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($produtos as $produto): ?>
                            <option value="<?php echo $produto['id']; ?>">
                                <?php echo $produto['codigo_interno'] . ' - ' . $produto['nome']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tipo de Movimentação</label>
                    <select class="form-select" name="tipo" required>
                        <option value="entrada">Entrada (Compra)</option>
                        <option value="saida">Saída (Venda/Consumo)</option>
                        <option value="transferencia">Transferência</option>
                        <option value="ajuste_entrada">Ajuste (Entrada)</option>
                        <option value="ajuste_saida">Ajuste (Saída)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Quantidade</label>
                    <input type="number" class="form-control" name="quantidade" min="1" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Motivo / Observação</label>
                    <input type="text" class="form-control" name="motivo" placeholder="Ex: NF 1234, Quebra, etc." required>
                </div>
            </div>

            <div class="mt-4 text-end">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save"></i> Registrar Movimentação
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../app/views/partials/footer.php'; ?>
