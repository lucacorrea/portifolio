<?php

declare(strict_types=1);

use App\Catalog\Entity\ServiceDefinition;

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/servico-action-common.php';

$serviceCatalog = $application->serviceManagement();
$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
];
$services = $serviceCatalog->listServices($filters);
$summary = $serviceCatalog->serviceSummary();
$allServices = $serviceCatalog->listServices();
$categories = array_values(array_unique(array_filter(array_map(
    static fn(ServiceDefinition $service): string => (string) $service->category(),
    $allServices
))));
sort($categories);

$canCreate = $authorization->can('servico.criar');
$canEdit = $authorization->can('servico.editar');
$canChangePrice = $authorization->can('servico.alterar_preco');
$canDelete = $authorization->can('servico.excluir');
$recovery = service_consume_form_recovery();

function service_recovery_data(?array $recovery, string $modal): array
{
    return $recovery !== null
        && ($recovery['modal'] ?? '') === $modal
        && isset($recovery['data'])
        && is_array($recovery['data'])
        ? $recovery['data']
        : [];
}

function service_recovery_error(?array $recovery, string $modal): ?string
{
    return $recovery !== null
        && ($recovery['modal'] ?? '') === $modal
        && isset($recovery['error'])
        && is_string($recovery['error'])
        ? $recovery['error']
        : null;
}

function service_value(array $data, string $key, string $default = ''): string
{
    $value = $data[$key] ?? $default;

    return is_scalar($value) ? (string) $value : $default;
}

function service_date(string $value): string
{
    try {
        return (new DateTimeImmutable($value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return '-';
    }
}

function service_duration(int $minutes): string
{
    if ($minutes <= 0) {
        return '0min';
    }

    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;

    if ($hours > 0 && $remaining > 0) {
        return $hours . 'h' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT);
    }

    if ($hours > 0) {
        return $hours . 'h';
    }

    return $remaining . 'min';
}

$createData = service_recovery_data($recovery, 'create');
$createError = service_recovery_error($recovery, 'create');
$editData = service_recovery_data($recovery, 'edit');
$editError = service_recovery_error($recovery, 'edit');
?>

<div class="page-body services-page">

<?php
metric_grid([
    ['Total de serviços', (string) ($summary['total'] ?? 0), 'bi-tools', '#2563EB', 'cadastrados'],
    ['Serviços ativos', (string) ($summary['active'] ?? 0), 'bi-check-circle', '#16A34A', 'disponíveis'],
    ['Serviços inativos', (string) ($summary['inactive'] ?? 0), 'bi-pause-circle', '#D97706', 'indisponíveis'],
]);
?>

<form class="filter-bar" method="get" action="servicos.php" data-live-filter="services" data-live-regions="metrics results">
    <div class="search-wrap">
        <i class="bi bi-search"></i>
        <input class="search-input" type="search" name="search" value="<?= h($filters['search']) ?>" placeholder="Buscar código, serviço, categoria ou equipamento" maxlength="150">
    </div>
    <select class="filter-select" name="category" aria-label="Filtrar por categoria">
        <option value="">Todas as categorias</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?= h($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= h($category) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="status" aria-label="Filtrar por status">
        <option value="">Todos os status</option>
        <option value="ativo" <?= $filters['status'] === 'ativo' ? 'selected' : '' ?>>Ativos</option>
        <option value="inativo" <?= $filters['status'] === 'inativo' ? 'selected' : '' ?>>Inativos</option>
    </select>
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="servicos.php" data-live-filter-clear><i class="bi bi-x-lg"></i> Limpar filtros</a>
</form>

<section class="panel" data-live-region="results">
    <div class="panel-header">
        <div class="panel-title"><i class="bi bi-tools"></i>Serviços cadastrados</div>
        <?php if ($canCreate): ?>
            <button class="btn-new-os" type="button" data-bs-toggle="modal" data-bs-target="#modal-servico"><i class="bi bi-tools"></i><span>Novo serviço</span></button>
        <?php endif; ?>
    </div>
    <?php if ($services === []): ?>
        <?php empty_state('Nenhum serviço encontrado', 'Cadastre o primeiro serviço ou ajuste os filtros.'); ?>
    <?php else: ?>
        <div class="table-panel-wrap">
            <table class="os-table services-table">
                <thead><tr><th>Código</th><th>Serviço</th><th>Categoria</th><th>Equipamentos compatíveis</th><th>Duração estimada</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td><strong><?= h($service->displayCode()) ?></strong></td>
                            <td><?= h($service->name()) ?></td>
                            <td><?= h($service->category() ?? '-') ?></td>
                            <td><?= h($service->compatibleEquipment() ?? '-') ?></td>
                            <td><?= h(service_duration($service->durationMinutes())) ?></td>
                            <td><?= h(money($service->value())) ?></td>
                            <td><span class="badge-soft badge-<?= $service->status() === 'ativo' ? 'green' : 'gray' ?>"><?= h($service->status() === 'ativo' ? 'Ativo' : 'Inativo') ?></span></td>
                            <td class="table-actions-cell"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do serviço <?= h($service->name()) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end">
                                <li><button class="dropdown-item js-service-view" type="button" data-bs-toggle="modal" data-bs-target="#modal-servico-view"
                                    data-service-id="<?= h((string) $service->id()) ?>"
                                    data-service-code="<?= h($service->displayCode()) ?>"
                                    data-service-name="<?= h($service->name()) ?>"
                                    data-service-category="<?= h($service->category() ?? '') ?>"
                                    data-service-compatible-equipment="<?= h($service->compatibleEquipment() ?? '') ?>"
                                    data-service-duration-minutes="<?= h((string) $service->durationMinutes()) ?>"
                                    data-service-duration="<?= h(service_duration($service->durationMinutes())) ?>"
                                    data-service-value="<?= h($service->value()) ?>"
                                    data-service-description="<?= h($service->description() ?? '') ?>"
                                    data-service-status="<?= h($service->status()) ?>"
                                    data-service-created-at="<?= h(service_date($service->createdAt())) ?>"
                                    data-service-updated-at="<?= h(service_date($service->updatedAt())) ?>"
                                ><i class="bi bi-eye"></i> Visualizar</button></li>
                                <?php if ($canEdit): ?>
                                    <li><button class="dropdown-item js-service-edit" type="button" data-bs-toggle="modal" data-bs-target="#modal-servico-edit"
                                        data-service-id="<?= h((string) $service->id()) ?>"
                                        data-service-code="<?= h($service->displayCode()) ?>"
                                        data-service-name="<?= h($service->name()) ?>"
                                        data-service-category="<?= h($service->category() ?? '') ?>"
                                        data-service-compatible-equipment="<?= h($service->compatibleEquipment() ?? '') ?>"
                                        data-service-duration-minutes="<?= h((string) $service->durationMinutes()) ?>"
                                        <?php if ($canChangePrice): ?>data-service-value="<?= h($service->value()) ?>"<?php endif; ?>
                                        data-service-description="<?= h($service->description() ?? '') ?>"
                                        data-service-status="<?= h($service->status()) ?>"
                                    ><i class="bi bi-pencil"></i> Editar</button></li>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><button class="dropdown-item text-danger js-service-delete" type="button" data-bs-toggle="modal" data-bs-target="#modal-servico-delete" data-service-id="<?= h((string) $service->id()) ?>" data-service-name="<?= h($service->name()) ?>"><i class="bi bi-trash3"></i> Excluir serviço</button></li>
                                <?php endif; ?>
                            </ul></div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
</div>

<?php
function service_form_fields(array $data, bool $canChangePrice, string $prefix): void {
?>
    <section class="form-section"><h3 class="form-section-title">Dados do serviço</h3>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Nome</label><input class="form-control-os" id="<?= h($prefix) ?>-name" type="text" name="name" value="<?= h(service_value($data, 'name')) ?>" maxlength="150" required></div>
            <div class="form-group"><label class="form-label">Categoria</label><input class="form-control-os" id="<?= h($prefix) ?>-category" type="text" name="category" value="<?= h(service_value($data, 'category')) ?>" maxlength="100"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Equipamentos compatíveis</label><input class="form-control-os" id="<?= h($prefix) ?>-compatible-equipment" type="text" name="compatible_equipment" value="<?= h(service_value($data, 'compatible_equipment')) ?>" maxlength="255"></div>
            <div class="form-group"><label class="form-label">Duração em minutos</label><input class="form-control-os" id="<?= h($prefix) ?>-duration-minutes" type="number" min="0" name="duration_minutes" value="<?= h(service_value($data, 'duration_minutes', '0')) ?>"></div>
        </div>
        <?php if ($canChangePrice): ?><div class="form-group"><label class="form-label">Valor</label><input class="form-control-os" id="<?= h($prefix) ?>-value" type="text" name="value" value="<?= h(service_value($data, 'value', '0,00')) ?>"></div><?php endif; ?>
        <div class="form-group"><label class="form-label">Status</label><select class="form-control-os" id="<?= h($prefix) ?>-status" name="status"><option value="ativo" <?= service_value($data, 'status', 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option><option value="inativo" <?= service_value($data, 'status') === 'inativo' ? 'selected' : '' ?>>Inativo</option></select></div>
        <div class="form-group"><label class="form-label">Descrição</label><textarea class="form-control-os" id="<?= h($prefix) ?>-description" name="description" rows="3"><?= h(service_value($data, 'description')) ?></textarea></div>
    </section>
<?php } ?>

<?php if ($canCreate): ?>
<div class="modal fade" id="modal-servico" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/servico-salvar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Novo serviço</h2><p class="text-muted small mb-0">O código será gerado automaticamente.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><div class="alert alert-danger <?= $createError === null ? 'd-none' : '' ?>" id="create-service-form-error" role="alert"><?= h($createError ?? '') ?></div><?php service_form_fields($createData, $canChangePrice, 'create-service'); ?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button></div></form></div></div>
<?php endif; ?>

<div class="modal fade" id="modal-servico-view" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content visual-modal"><div class="modal-header"><div><h2 class="modal-title fs-5">Dados do serviço</h2><p class="text-muted small mb-0" id="view-service-subtitle"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><section class="form-section"><h3 class="form-section-title">Identificação</h3><div class="form-row"><div class="form-group"><label class="form-label">Código</label><div class="form-control-os" id="view-service-code"></div></div><div class="form-group"><label class="form-label">Nome</label><div class="form-control-os" id="view-service-name"></div></div></div><div class="form-row"><div class="form-group"><label class="form-label">Categoria</label><div class="form-control-os" id="view-service-category"></div></div><div class="form-group"><label class="form-label">Equipamentos compatíveis</label><div class="form-control-os" id="view-service-compatible-equipment"></div></div></div><div class="form-row"><div class="form-group"><label class="form-label">Duração</label><div class="form-control-os" id="view-service-duration"></div></div><div class="form-group"><label class="form-label">Valor</label><div class="form-control-os" id="view-service-value"></div></div></div><div class="form-row"><div class="form-group"><label class="form-label">Status</label><div class="form-control-os" id="view-service-status"></div></div><div class="form-group"><label class="form-label">Cadastrado em</label><div class="form-control-os" id="view-service-created-at"></div></div></div><div class="form-group"><label class="form-label">Atualizado em</label><div class="form-control-os" id="view-service-updated-at"></div></div><div class="form-group"><label class="form-label">Descrição</label><div class="form-control-os" id="view-service-description"></div></div></section></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Fechar</button></div></div></div></div>

<?php if ($canEdit): ?>
<div class="modal fade" id="modal-servico-edit" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/servico-salvar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Editar serviço</h2><p class="text-muted small mb-0" id="edit-service-subtitle"><?= h(service_value($editData, 'code')) ?></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><div class="alert alert-danger <?= $editError === null ? 'd-none' : '' ?>" id="edit-service-form-error" role="alert"><?= h($editError ?? '') ?></div><input type="hidden" name="id" id="edit-service-id" value="<?= h(service_value($editData, 'id')) ?>"><section class="form-section"><h3 class="form-section-title">Código</h3><input class="form-control-os" id="edit-service-code" type="text" value="<?= h(service_value($editData, 'code')) ?>" readonly></section><?php service_form_fields($editData, $canChangePrice, 'edit-service'); ?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button></div></form></div></div>
<?php endif; ?>

<?php if ($canDelete): ?><div class="modal fade" id="modal-servico-delete" tabindex="-1" aria-labelledby="service-delete-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/servico-excluir.php"><div class="modal-header"><h2 class="modal-title fs-5" id="service-delete-title">Excluir serviço</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="delete-service-id"><p>Deseja excluir <strong id="delete-service-name"></strong>?</p><div class="alert alert-warning mb-0">O serviço sairá dos novos cadastros, mas continuará registrado nos orçamentos e OS anteriores.</div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-trash3"></i> Excluir serviço</button></div></form></div></div><?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const recoveryModal = <?= json_encode($recovery['modal'] ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const canChangePrice = <?= $canChangePrice ? 'true' : 'false' ?>;
    function text(id, value) { const element = document.getElementById(id); if (element) { element.textContent = value || '-'; } }
    function val(id, value) { const element = document.getElementById(id); if (element) { element.value = value || ''; } }
    function moneyValue(value) { const number = Number.parseFloat(value || '0'); return number.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }
    document.addEventListener('click', function (event) {
        const button = event.target.closest('.js-service-view, .js-service-edit, .js-service-delete');
        if (!button) return;
        if (button.classList.contains('js-service-view')) { text('view-service-subtitle', button.dataset.serviceCode); text('view-service-code', button.dataset.serviceCode); text('view-service-name', button.dataset.serviceName); text('view-service-category', button.dataset.serviceCategory); text('view-service-compatible-equipment', button.dataset.serviceCompatibleEquipment); text('view-service-duration', button.dataset.serviceDuration); text('view-service-value', moneyValue(button.dataset.serviceValue)); text('view-service-status', button.dataset.serviceStatus === 'ativo' ? 'Ativo' : 'Inativo'); text('view-service-created-at', button.dataset.serviceCreatedAt); text('view-service-updated-at', button.dataset.serviceUpdatedAt); text('view-service-description', button.dataset.serviceDescription); }
        if (button.classList.contains('js-service-edit')) { text('edit-service-subtitle', button.dataset.serviceCode); val('edit-service-id', button.dataset.serviceId); val('edit-service-code', button.dataset.serviceCode); val('edit-service-name', button.dataset.serviceName); val('edit-service-category', button.dataset.serviceCategory); val('edit-service-compatible-equipment', button.dataset.serviceCompatibleEquipment); val('edit-service-duration-minutes', button.dataset.serviceDurationMinutes); if (canChangePrice) { val('edit-service-value', button.dataset.serviceValue); } val('edit-service-description', button.dataset.serviceDescription); val('edit-service-status', button.dataset.serviceStatus || 'ativo'); }
        if (button.classList.contains('js-service-delete')) { val('delete-service-id', button.dataset.serviceId); text('delete-service-name', button.dataset.serviceName || 'este serviço'); }
    });
    const createModal = document.getElementById('modal-servico');
    if (createModal) { createModal.addEventListener('show.bs.modal', function (event) { if (event.relatedTarget) { const form = createModal.querySelector('form'); if (form) { form.reset(); } text('create-service-form-error', ''); document.getElementById('create-service-form-error')?.classList.add('d-none'); } }); }
    const targets = { create: 'modal-servico', edit: 'modal-servico-edit' };
    if (recoveryModal && targets[recoveryModal] && window.bootstrap) { const modal = document.getElementById(targets[recoveryModal]); if (modal) { bootstrap.Modal.getOrCreateInstance(modal).show(); } }
});
</script>
