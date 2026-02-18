<?php require_once '../app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?php echo ($action == 'create') ? 'Novo Cliente' : 'Editar Cliente'; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="?url=clientes/index" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?url=clientes/<?php echo $action; ?><?php echo ($action == 'edit') ? '/' . $cliente['id'] : ''; ?>">
            
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tipo de Pessoa</label>
                    <select class="form-select" name="tipo" id="tipo_pessoa" required>
                        <option value="pessoa_fisica" <?php echo (isset($cliente['tipo']) && $cliente['tipo'] == 'pessoa_fisica') ? 'selected' : ''; ?>>Pessoa Física</option>
                        <option value="pessoa_juridica" <?php echo (isset($cliente['tipo']) && $cliente['tipo'] == 'pessoa_juridica') ? 'selected' : ''; ?>>Pessoa Jurídica</option>
                    </select>
                </div>

                <div class="col-md-9">
                    <label class="form-label">Nome Completo / Razão Social</label>
                    <input type="text" class="form-control" name="nome" value="<?php echo $cliente['nome'] ?? ''; ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">CPF / CNPJ</label>
                    <input type="text" class="form-control" name="cpf_cnpj" value="<?php echo $cliente['cpf_cnpj'] ?? ''; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Inscrição Estadual</label>
                    <input type="text" class="form-control" name="ie" value="<?php echo $cliente['ie'] ?? ''; ?>">
                </div>
                
                 <div class="col-md-4">
                    <label class="form-label">Limite de Crédito</label>
                     <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control" name="limite_credito" value="<?php echo isset($cliente['limite_credito']) ? number_format($cliente['limite_credito'], 2, ',', '') : '0,00'; ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Endereço</label>
                    <input type="text" class="form-control" name="endereco" value="<?php echo $cliente['endereco'] ?? ''; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Cidade</label>
                    <input type="text" class="form-control" name="cidade" value="<?php echo $cliente['cidade'] ?? ''; ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Estado (UF)</label>
                    <input type="text" class="form-control" name="estado" value="<?php echo $cliente['estado'] ?? ''; ?>" maxlength="2">
                </div>
            </div>

            <div class="mt-4 text-end">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save"></i> Salvar Cliente
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../app/views/partials/footer.php'; ?>
