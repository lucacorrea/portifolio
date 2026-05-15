<?php

$today = is_string($today ?? null) ? $today : date('Y-m-d');
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

        <form class="form-stack entry-form" method="post" action="<?= \App\Core\View::e(url('/entradas')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= \App\Core\Session::csrfToken() ?>">

            <div class="field-grid">
                <label>
                    Tipo
                    <select name="tipo" required>
                        <option value="dizimo">Dízimo</option>
                        <option value="oferta">Oferta</option>
                    </select>
                </label>

                <label>
                    Valor
                    <input type="number" name="valor" min="0.01" step="0.01" placeholder="0,00" required>
                </label>

                <label>
                    Data
                    <input type="date" name="data_entrada" value="<?= \App\Core\View::e($today) ?>" required>
                </label>

                <label>
                    Forma de pagamento
                    <select name="forma_pagamento">
                        <option value="">Selecione</option>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">Pix</option>
                        <option value="cartao">Cartão</option>
                        <option value="transferencia">Transferência</option>
                        <option value="outro">Outro</option>
                    </select>
                </label>
            </div>

            <label>
                Contribuinte
                <input type="text" name="contribuinte_nome" maxlength="180" placeholder="Nome do contribuinte">
            </label>

            <label>
                Descrição
                <textarea name="descricao" rows="4" placeholder="Observações sobre a entrada"></textarea>
            </label>

            <div class="form-actions">
                <button class="button primary" type="button" disabled title="A gravação será implementada no próximo passo">
                    <i data-lucide="save"></i>
                    Salvar entrada
                </button>
                <a class="button secondary" href="<?= \App\Core\View::e(url('/entradas')) ?>">Cancelar</a>
            </div>
        </form>
    </article>
</section>
