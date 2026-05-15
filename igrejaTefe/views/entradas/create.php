<?php

$today = is_string($today ?? null) ? $today : date('Y-m-d');
$old = is_array($old ?? null) ? $old : [];
$oldValue = static fn (string $key, mixed $default = ''): string => (string) ($old[$key] ?? $default);
?>

<section class="page-section entries-page">
    <div class="section-header with-actions">
        <div>
            <p class="eyebrow">Entradas</p>
            <h1>Cadastrar entrada</h1>
        </div>

        <a class="button secondary" href="<?= \App\Core\View::e(url('/entradas')) ?>">
            <i data-lucide="arrow-left"></i>
            Voltar para listagem
        </a>
    </div>

    <article class="form-card">
        <div class="form-card-header">
            <div>
                <span class="section-kicker">Novo registro</span>
                <h2>Dados da entrada</h2>
            </div>
            <span class="badge badge-muted">Cadastro</span>
        </div>

        <?php if (is_string($error ?? null)): ?>
            <div class="alert error"><?= \App\Core\View::e($error) ?></div>
        <?php endif; ?>

        <form class="form-stack entry-form" method="post" action="<?= \App\Core\View::e(url('/entradas')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= \App\Core\Session::csrfToken() ?>">

            <div class="field-grid">
                <label>
                    Tipo
                    <select name="tipo" required>
                        <option value="dizimo" <?= $oldValue('tipo', 'dizimo') === 'dizimo' ? 'selected' : '' ?>>Dízimo</option>
                        <option value="oferta" <?= $oldValue('tipo') === 'oferta' ? 'selected' : '' ?>>Oferta</option>
                    </select>
                </label>

                <label>
                    Valor
                    <input type="number" name="valor" min="0.01" step="0.01" value="<?= \App\Core\View::e($oldValue('valor')) ?>" placeholder="0,00" required>
                </label>

                <label>
                    Data
                    <input type="date" name="data_entrada" value="<?= \App\Core\View::e($oldValue('data_entrada', $today)) ?>" required>
                </label>

                <label>
                    Forma de pagamento
                    <select name="forma_pagamento">
                        <option value="">Selecione</option>
                        <option value="dinheiro" <?= $oldValue('forma_pagamento') === 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                        <option value="pix" <?= $oldValue('forma_pagamento') === 'pix' ? 'selected' : '' ?>>Pix</option>
                        <option value="cartao" <?= $oldValue('forma_pagamento') === 'cartao' ? 'selected' : '' ?>>Cartão</option>
                        <option value="transferencia" <?= $oldValue('forma_pagamento') === 'transferencia' ? 'selected' : '' ?>>Transferência</option>
                        <option value="outro" <?= $oldValue('forma_pagamento') === 'outro' ? 'selected' : '' ?>>Outro</option>
                    </select>
                </label>
            </div>

            <label>
                Contribuinte
                <input type="text" name="contribuinte_nome" maxlength="180" value="<?= \App\Core\View::e($oldValue('contribuinte_nome')) ?>" placeholder="Nome do contribuinte">
            </label>

            <label>
                Descrição
                <textarea name="descricao" rows="4" placeholder="Observações sobre a entrada"><?= \App\Core\View::e($oldValue('descricao')) ?></textarea>
            </label>

            <div class="form-actions">
                <button class="button primary" type="submit">
                    <i data-lucide="save"></i>
                    Salvar entrada
                </button>
                <a class="button secondary" href="<?= \App\Core\View::e(url('/entradas')) ?>">Cancelar</a>
            </div>
        </form>
    </article>
</section>
