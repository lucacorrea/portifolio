<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$company = $application->companySettings()->get();
$canEdit = $authorization->can('configuracao.editar');
?>

<div class="page-body settings-page">
<section class="panel">
    <div class="panel-header"><div class="panel-title"><i class="bi bi-building"></i>Dados da empresa</div></div>
    <form class="visual-modal" method="post" action="actions/configuracao-empresa-salvar.php">
        <?= $csrf->field() ?>
        <?php return_to_field(); ?>
        <div class="form-section">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Razao social</label><input class="form-control-os" name="razao_social" value="<?= h((string) ($company['razao_social'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="form-group"><label class="form-label">Nome fantasia</label><input class="form-control-os" name="nome_fantasia" value="<?= h((string) ($company['nome_fantasia'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="form-group"><label class="form-label">CPF/CNPJ</label><input class="form-control-os" name="documento" value="<?= h((string) ($company['documento'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Telefone</label><input class="form-control-os" name="telefone" value="<?= h((string) ($company['telefone'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="form-group"><label class="form-label">Endereco</label><input class="form-control-os" name="endereco" value="<?= h((string) ($company['endereco'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="form-group"><label class="form-label">Logo</label><input class="form-control-os" name="logo" value="<?= h((string) ($company['logo'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            </div>
        </div>
        <?php if ($canEdit): ?><div class="modal-footer"><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button></div><?php endif; ?>
    </form>
</section>
</div>
