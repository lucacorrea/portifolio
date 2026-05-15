<?php

$today = is_string($today ?? null) ? $today : date('Y-m-d');
$categorias = is_array($categorias ?? null) ? $categorias : [];
$old = is_array($old ?? null) ? $old : [];
$oldValue = static fn (string $key, mixed $default = ''): string => (string) ($old[$key] ?? $default);
?>

<section class="page-section module-page">
    <div class="section-header with-actions">
        <div>
            <p class="eyebrow">Saídas</p>
            <h1>Cadastrar saída</h1>
        </div>

        <a class="button secondary" href="<?= \App\Core\View::e(url('/saidas')) ?>">
            <i data-lucide="arrow-left"></i>
            Voltar para listagem
        </a>
    </div>

    <article class="form-card">
        <div class="form-card-header">
            <div>
                <span class="section-kicker">Novo pagamento</span>
                <h2>Dados da saída</h2>
            </div>
            <span class="badge badge-danger">Despesa</span>
        </div>

        <?php if (is_string($error ?? null)): ?>
            <div class="alert error"><?= \App\Core\View::e($error) ?></div>
        <?php endif; ?>

        <?php if ($categorias === []): ?>
            <div class="empty-state compact-empty">
                <i data-lucide="tags"></i>
                <strong>Cadastre uma categoria antes</strong>
                <p>Saídas precisam de uma categoria ativa para manter os relatórios organizados.</p>
                <a class="button primary" href="<?= \App\Core\View::e(url('/categorias/criar')) ?>">
                    <i data-lucide="plus-circle"></i>
                    Criar categoria
                </a>
            </div>
        <?php else: ?>
            <form class="form-stack entry-form" method="post" action="<?= \App\Core\View::e(url('/saidas')) ?>">
                <input type="hidden" name="_csrf_token" value="<?= \App\Core\Session::csrfToken() ?>">

                <div class="field-grid">
                    <label>
                        Categoria
                        <select name="categoria_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= \App\Core\View::e($categoria['id']) ?>" <?= $oldValue('categoria_id') === (string) $categoria['id'] ? 'selected' : '' ?>>
                                    <?= \App\Core\View::e($categoria['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Valor
                        <input type="number" name="valor" min="0.01" step="0.01" value="<?= \App\Core\View::e($oldValue('valor')) ?>" placeholder="0,00" required>
                    </label>

                    <label>
                        Data
                        <input type="date" name="data_saida" value="<?= \App\Core\View::e($oldValue('data_saida', $today)) ?>" required>
                    </label>

                    <label>
                        Forma de pagamento
                        <select name="forma_pagamento">
                            <option value="">Selecione</option>
                            <option value="dinheiro" <?= $oldValue('forma_pagamento') === 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                            <option value="pix" <?= $oldValue('forma_pagamento') === 'pix' ? 'selected' : '' ?>>Pix</option>
                            <option value="cartao" <?= $oldValue('forma_pagamento') === 'cartao' ? 'selected' : '' ?>>Cartão</option>
                            <option value="transferencia" <?= $oldValue('forma_pagamento') === 'transferencia' ? 'selected' : '' ?>>Transferência</option>
                            <option value="boleto" <?= $oldValue('forma_pagamento') === 'boleto' ? 'selected' : '' ?>>Boleto</option>
                            <option value="outro" <?= $oldValue('forma_pagamento') === 'outro' ? 'selected' : '' ?>>Outro</option>
                        </select>
                    </label>
                </div>

                <label>
                    Fornecedor
                    <input type="text" name="fornecedor" maxlength="180" value="<?= \App\Core\View::e($oldValue('fornecedor')) ?>" placeholder="Nome do fornecedor ou beneficiário">
                </label>

                <label>
                    Descrição
                    <textarea name="descricao" rows="4" placeholder="Observações sobre a saída"><?= \App\Core\View::e($oldValue('descricao')) ?></textarea>
                </label>

                <div class="form-actions">
                    <button class="button soft-danger" type="submit">
                        <i data-lucide="save"></i>
                        Salvar saída
                    </button>
                    <a class="button secondary" href="<?= \App\Core\View::e(url('/saidas')) ?>">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </article>
</section>
