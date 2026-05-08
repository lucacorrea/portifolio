<?php
$contratosCliente = $contratosCliente ?? [];
$areaContrato = $areaContrato ?? 'recepcao';
$contratosAtivos = 0;

foreach ($contratosCliente as $contratoResumo) {
    if (($contratoResumo['status'] ?? '') === 'Ativo') {
        $contratosAtivos++;
    }
}
?>
<div class="contract-stack">
    <div class="contract-summary">
        <strong><?= count($contratosCliente) ?> contrato<?= count($contratosCliente) === 1 ? '' : 's' ?></strong>
        <span><?= $contratosAtivos ?> ativo<?= $contratosAtivos === 1 ? '' : 's' ?></span>
    </div>

    <?php foreach ($contratosCliente as $contrato): ?>
        <article class="contract-pill">
            <div class="contract-pill-head">
                <strong><?= htmlspecialchars($contrato['numero']) ?></strong>
                <span class="status <?= contrato_status_classe($contrato['status']) ?>">
                    <?= htmlspecialchars($contrato['status']) ?>
                </span>
            </div>
            <p><?= htmlspecialchars($contrato['titulo']) ?></p>
            <div class="contract-meta">
                <span><?= htmlspecialchars($contrato['vigencia']) ?></span>
                <strong><?= contrato_valor_formatado((float) $contrato['valor']) ?></strong>
            </div>
        </article>
    <?php endforeach; ?>
</div>
