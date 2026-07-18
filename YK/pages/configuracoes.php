<?php

declare(strict_types=1);

use App\Company\Service\CompanyBranding;

require_once __DIR__ . '/../includes/ui.php';

$company = $application->companySettings()->get();
$canEdit = $authorization->can('configuracao.editar');
$hasCompanyLogoReference = trim((string) ($company['logo'] ?? '')) !== '';
$companyLogo = CompanyBranding::safeLogoUrl($company['logo'] ?? null);
?>

<div class="page-body settings-page">
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><i class="bi bi-building"></i>Dados da empresa</div>
    </div>
    <form
        class="visual-modal"
        method="post"
        action="actions/configuracao-empresa-salvar.php"
        enctype="multipart/form-data"
        data-company-logo-form
        data-current-logo-url="<?= h($companyLogo ?? '') ?>"
    >
        <?= $csrf->field() ?>
        <?php return_to_field(); ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="5242880">

        <div class="form-section">
            <h3 class="form-section-title">Dados cadastrais</h3>
            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label" for="company-legal-name">Razão social</label>
                    <input class="form-control-os" id="company-legal-name" name="razao_social" maxlength="150" value="<?= h((string) ($company['razao_social'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>
                <div class="form-group">
                    <label class="form-label" for="company-trade-name">Nome fantasia</label>
                    <input class="form-control-os" id="company-trade-name" name="nome_fantasia" maxlength="150" value="<?= h((string) ($company['nome_fantasia'] ?? '')) ?>" aria-describedby="company-trade-name-help" <?= $canEdit ? '' : 'disabled' ?>>
                    <small class="text-muted" id="company-trade-name-help">As três primeiras palavras serão exibidas abaixo da logo no menu.</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="company-document">CPF/CNPJ</label>
                    <input class="form-control-os" id="company-document" name="documento" maxlength="30" value="<?= h((string) ($company['documento'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="company-phone">Telefone</label>
                    <input class="form-control-os" id="company-phone" type="tel" name="telefone" maxlength="30" value="<?= h((string) ($company['telefone'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>
                <div class="form-group">
                    <label class="form-label" for="company-address">Endereço</label>
                    <input class="form-control-os" id="company-address" name="endereco" maxlength="255" value="<?= h((string) ($company['endereco'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>
            </div>

            <h3 class="form-section-title">Identidade visual</h3>
            <div class="company-logo-editor">
                <div class="company-logo-preview" data-company-logo-preview>
                    <img class="<?= $companyLogo !== null ? 'show' : '' ?>"<?= $companyLogo !== null ? ' src="' . h($companyLogo) . '"' : '' ?> alt="Prévia da logo da empresa">
                    <span class="<?= $companyLogo !== null ? 'd-none' : '' ?>" aria-hidden="true"><i class="bi bi-image"></i></span>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="company-logo-file">Logo da empresa</label>
                    <input
                        class="form-control-os"
                        id="company-logo-file"
                        type="file"
                        name="logo_file"
                        accept="image/jpeg,image/png,image/webp"
                        aria-describedby="company-logo-help company-logo-status"
                        <?= $canEdit ? '' : 'disabled' ?>
                    >
                    <small class="text-muted" id="company-logo-help">JPEG, PNG ou WebP, até 5 MB. A imagem será ajustada sem recorte.</small>
                    <span class="visually-hidden" id="company-logo-status" aria-live="polite"></span>
                    <?php if ($canEdit && $hasCompanyLogoReference): ?>
                        <label class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_logo" value="1" data-remove-company-logo>
                            <span class="form-check-label">Remover logo atual</span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($canEdit): ?>
            <div class="modal-footer">
                <button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
            </div>
        <?php endif; ?>
    </form>
</section>
</div>
