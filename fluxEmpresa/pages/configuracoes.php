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
<?php if ($authorization->canAny(['nota_fiscal.configurar', 'nota_fiscal.gerenciar_credenciais'])): ?>
<div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span><i class="bi bi-shield-lock me-2"></i>Certificado A1, CSC e homologação ficam em uma área fiscal protegida.</span>
    <a class="btn-filter btn-filter-primary" href="configuracoes-fiscais.php">Abrir configuração fiscal</a>
</div>
<?php endif; ?>
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
                    <input class="form-control-os" id="company-trade-name" name="nome_fantasia" maxlength="150" value="<?= h((string) ($company['nome_fantasia'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
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
                    <label class="form-label" for="company-email">E-mail</label>
                    <input class="form-control-os" id="company-email" type="email" name="email" maxlength="150" value="<?= h((string) ($company['email'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>
            </div>

            <h3 class="form-section-title">Dados fiscais do emitente</h3>
            <p class="text-muted">Estes dados serão usados na validação antes da comunicação com a SEFAZ.</p>
            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label" for="company-ie">Inscrição estadual</label>
                    <input class="form-control-os" id="company-ie" name="inscricao_estadual" maxlength="40" value="<?= h((string) ($company['inscricao_estadual'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>
                <div class="form-group">
                    <label class="form-label" for="company-im">Inscrição municipal</label>
                    <input class="form-control-os" id="company-im" name="inscricao_municipal" maxlength="40" value="<?= h((string) ($company['inscricao_municipal'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>
                <div class="form-group">
                    <label class="form-label" for="company-crt">Regime tributário (CRT)</label>
                    <select class="form-select form-control-os" id="company-crt" name="crt" <?= $canEdit ? '' : 'disabled' ?>>
                        <option value="">Selecione</option>
                        <?php foreach ([1 => 'Simples Nacional', 2 => 'Simples Nacional - excesso', 3 => 'Regime normal', 4 => 'MEI'] as $crt => $label): ?>
                            <option value="<?= $crt ?>" <?= (int) ($company['crt'] ?? 0) === $crt ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="company-cnae">CNAE principal</label>
                    <input class="form-control-os" id="company-cnae" inputmode="numeric" name="cnae_principal" maxlength="10" value="<?= h((string) ($company['cnae_principal'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>
                <div class="form-group">
                    <label class="form-label" for="company-address">Endereço completo (documentos legados)</label>
                    <input class="form-control-os" id="company-address" name="endereco" maxlength="255" value="<?= h((string) ($company['endereco'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                </div>
            </div>
            <div class="form-row-3">
                <div class="form-group"><label class="form-label" for="company-street">Logradouro</label><input class="form-control-os" id="company-street" name="endereco_logradouro" maxlength="150" value="<?= h((string) ($company['endereco_logradouro'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="form-group"><label class="form-label" for="company-number">Número</label><input class="form-control-os" id="company-number" name="endereco_numero" maxlength="30" value="<?= h((string) ($company['endereco_numero'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="form-group"><label class="form-label" for="company-complement">Complemento</label><input class="form-control-os" id="company-complement" name="endereco_complemento" maxlength="100" value="<?= h((string) ($company['endereco_complemento'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            </div>
            <div class="form-row-3">
                <div class="form-group"><label class="form-label" for="company-district">Bairro</label><input class="form-control-os" id="company-district" name="endereco_bairro" maxlength="100" value="<?= h((string) ($company['endereco_bairro'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="form-group"><label class="form-label" for="company-city">Cidade</label><input class="form-control-os" id="company-city" name="endereco_cidade" maxlength="100" value="<?= h((string) ($company['endereco_cidade'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="form-group"><label class="form-label" for="company-state">UF</label><input class="form-control-os" id="company-state" name="endereco_uf" maxlength="2" value="<?= h((string) ($company['endereco_uf'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label" for="company-postal-code">CEP</label><input class="form-control-os" id="company-postal-code" inputmode="numeric" name="endereco_cep" maxlength="9" value="<?= h((string) ($company['endereco_cep'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                <div class="form-group"><label class="form-label" for="company-city-code">Código do município (IBGE)</label><input class="form-control-os" id="company-city-code" inputmode="numeric" name="codigo_municipio_ibge" maxlength="7" value="<?= h((string) ($company['codigo_municipio_ibge'] ?? '')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
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
