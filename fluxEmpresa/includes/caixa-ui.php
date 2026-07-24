<?php

declare(strict_types=1);

require_once __DIR__ . '/ui.php';

function cash_label(string $value): string
{
    return match ($value) {
        'entrada' => 'Entrada', 'saida' => 'Saída', 'estorno_entrada' => 'Estorno de entrada',
        'estorno_saida' => 'Estorno de saída', 'dinheiro' => 'Dinheiro', 'pix' => 'Pix',
        'boleto' => 'Boleto', 'cartao_debito' => 'Cartão de débito',
        'cartao_credito' => 'Cartão de crédito', 'transferencia' => 'Transferência',
        'cheque' => 'Cheque', 'outro' => 'Outro', 'emitida' => 'Emitida',
        'estornada' => 'Estornada', 'cancelada' => 'Cancelada', 'aberta' => 'Aberta',
        'fechada' => 'Fechada', default => ucfirst(str_replace('_', ' ', $value)),
    };
}

function cash_datetime(?string $value): string
{
    if ($value === null || $value === '') return '-';
    try { return (new DateTimeImmutable($value))->format('d/m/Y H:i'); } catch (Throwable) { return '-'; }
}

function cash_modal(string $id, string $title, string $action, string $body, string $submit, string $icon = 'bi-check-lg'): void
{
    global $csrf;
    ?>
    <div class="modal fade" id="<?= h($id) ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="<?= h($action) ?>">
        <div class="modal-header"><h2 class="modal-title fs-5"><?= h($title) ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><?= $body ?></div>
        <div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi <?= h($icon) ?>"></i> <?= h($submit) ?></button></div>
      </form></div>
    </div>
    <?php
}
