<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/financial-registration-action-common.php';

function supplier_filter(string $key, int $maximumLength): string
{
    $raw = $_GET[$key] ?? '';
    if (!is_string($raw)) return '';
    $value = trim($raw);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    return $length <= $maximumLength && !str_contains($value, "\0") ? $value : '';
}

function supplier_value(array $supplier, string $key, string $default = ''): string
{
    $value = $supplier[$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

function supplier_status_label(string $status): string { return $status === 'ativo' ? 'Ativo' : 'Inativo'; }
function supplier_status_badge(string $status): string { return $status === 'ativo' ? 'green' : 'gray'; }

function supplier_status_url(array $filters, string $status): string
{
    $query = array_filter($filters, static fn(string $value): bool => $value !== '');
    if ($status === '') unset($query['status']);
    else $query['status'] = $status;
    return 'fornecedores.php' . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
}

function supplier_form_fields(string $prefix, array $data = []): void
{
    ?>
    <section class="form-section">
        <h3 class="form-section-title">Identificação</h3>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-tipo">Tipo de pessoa</label><select class="form-control-os" id="<?= h($prefix) ?>-tipo" name="tipo_pessoa" required><option value="fisica" <?= supplier_value($data, 'tipo_pessoa', 'juridica') === 'fisica' ? 'selected' : '' ?>>Pessoa Física</option><option value="juridica" <?= supplier_value($data, 'tipo_pessoa', 'juridica') === 'juridica' ? 'selected' : '' ?>>Pessoa Jurídica</option></select></div>
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-nome">Nome / Razão social</label><input class="form-control-os" id="<?= h($prefix) ?>-nome" name="nome" value="<?= h(supplier_value($data, 'nome')) ?>" maxlength="150" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-fantasia">Nome fantasia</label><input class="form-control-os" id="<?= h($prefix) ?>-fantasia" name="nome_fantasia" value="<?= h(supplier_value($data, 'nome_fantasia')) ?>" maxlength="150"></div>
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-documento">CPF / CNPJ</label><input class="form-control-os" id="<?= h($prefix) ?>-documento" name="documento" value="<?= h(supplier_value($data, 'documento')) ?>" maxlength="20" inputmode="numeric"></div>
        </div>
        <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-inscricao-estadual">Inscrição estadual</label><input class="form-control-os" id="<?= h($prefix) ?>-inscricao-estadual" name="inscricao_estadual" value="<?= h(supplier_value($data, 'inscricao_estadual')) ?>" maxlength="30"></div>
    </section>
    <section class="form-section">
        <h3 class="form-section-title">Contato</h3>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-contato">Pessoa de contato</label><input class="form-control-os" id="<?= h($prefix) ?>-contato" name="contato" value="<?= h(supplier_value($data, 'contato')) ?>" maxlength="120"></div>
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-telefone">Telefone</label><input class="form-control-os" id="<?= h($prefix) ?>-telefone" name="telefone" type="tel" value="<?= h(supplier_value($data, 'telefone')) ?>" maxlength="30"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-whatsapp">WhatsApp</label><input class="form-control-os" id="<?= h($prefix) ?>-whatsapp" name="whatsapp" type="tel" value="<?= h(supplier_value($data, 'whatsapp')) ?>" maxlength="30"></div>
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-email">E-mail</label><input class="form-control-os" id="<?= h($prefix) ?>-email" name="email" type="email" value="<?= h(supplier_value($data, 'email')) ?>" maxlength="150"></div>
        </div>
    </section>
    <section class="form-section">
        <h3 class="form-section-title">Endereço</h3>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-cep">CEP</label><input class="form-control-os" id="<?= h($prefix) ?>-cep" name="cep" value="<?= h(supplier_value($data, 'cep')) ?>" maxlength="10" inputmode="numeric"></div>
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-endereco">Endereço</label><input class="form-control-os" id="<?= h($prefix) ?>-endereco" name="endereco" value="<?= h(supplier_value($data, 'endereco')) ?>" maxlength="180"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-numero">Número</label><input class="form-control-os" id="<?= h($prefix) ?>-numero" name="numero" value="<?= h(supplier_value($data, 'numero')) ?>" maxlength="20"></div>
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-complemento">Complemento</label><input class="form-control-os" id="<?= h($prefix) ?>-complemento" name="complemento" value="<?= h(supplier_value($data, 'complemento')) ?>" maxlength="100"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-bairro">Bairro</label><input class="form-control-os" id="<?= h($prefix) ?>-bairro" name="bairro" value="<?= h(supplier_value($data, 'bairro')) ?>" maxlength="100"></div>
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-cidade">Cidade</label><input class="form-control-os" id="<?= h($prefix) ?>-cidade" name="cidade" value="<?= h(supplier_value($data, 'cidade')) ?>" maxlength="100"></div>
        </div>
        <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-estado">UF</label><input class="form-control-os" id="<?= h($prefix) ?>-estado" name="estado" value="<?= h(supplier_value($data, 'estado')) ?>" maxlength="2"></div>
    </section>
    <section class="form-section">
        <h3 class="form-section-title">Observações</h3>
        <div class="form-group mb-0"><label class="form-label" for="<?= h($prefix) ?>-observacao">Observação</label><textarea class="form-control-os" id="<?= h($prefix) ?>-observacao" name="observacao" maxlength="1000" rows="3"><?= h(supplier_value($data, 'observacao')) ?></textarea></div>
    </section>
    <?php
}

$service = $application->supplierManagement();
$filters = [
    'search' => supplier_filter('search', 150),
    'type' => supplier_filter('type', 20),
    'city' => supplier_filter('city', 100),
    'status' => supplier_filter('status', 20),
];
if (!in_array($filters['type'], ['', 'fisica', 'juridica'], true)) $filters['type'] = '';
if (!in_array($filters['status'], ['', 'ativo', 'inativo'], true)) $filters['status'] = '';

$suppliers = $service->listSuppliers($filters);
$hasMoreSuppliers = count($suppliers) > 200;
$suppliers = array_slice($suppliers, 0, 200);
$summary = $service->summary();
$cities = $service->cities();

$canCreate = $authorization->can('fornecedor.criar');
$canEdit = $authorization->can('fornecedor.editar');
$canStatus = $authorization->can('fornecedor.desativar');
$recovery = financial_registration_consume_recovery('supplier_form_recovery');
$createData = ($recovery['mode'] ?? '') === 'create' ? $recovery['data'] : [];
$editData = ($recovery['mode'] ?? '') === 'edit' ? $recovery['data'] : [];
$statusButtons = [['', 'Todos', 'all'], ['ativo', 'Ativos', 'green'], ['inativo', 'Inativos', 'gray']];
?>

<div class="page-body suppliers-page">
<?php metric_grid([
    ['Total de fornecedores', (string) ($summary['total'] ?? 0), 'bi-truck', '#2563EB', 'cadastrados'],
    ['Fornecedores ativos', (string) ($summary['active'] ?? $summary['ativos'] ?? 0), 'bi-building-check', '#16A34A', 'disponíveis'],
    ['Fornecedores inativos', (string) ($summary['inactive'] ?? $summary['inativos'] ?? 0), 'bi-building-dash', '#64748B', 'inativos'],
]); ?>

<form class="filter-bar" method="get" action="fornecedores.php" data-live-filter="suppliers" data-live-regions="metrics results">
    <div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" type="search" name="search" value="<?= h($filters['search']) ?>" placeholder="Buscar código, nome, CPF/CNPJ, telefone ou e-mail" maxlength="150" aria-label="Pesquisar fornecedores"></div>
    <select class="filter-select" name="type" aria-label="Tipo de pessoa"><option value="">Todos os tipos</option><option value="fisica" <?= $filters['type'] === 'fisica' ? 'selected' : '' ?>>Pessoa Física</option><option value="juridica" <?= $filters['type'] === 'juridica' ? 'selected' : '' ?>>Pessoa Jurídica</option></select>
    <select class="filter-select" name="city" aria-label="Cidade"><option value="">Todas as cidades</option><?php foreach ($cities as $city): ?><option value="<?= h($city) ?>" <?= $filters['city'] === $city ? 'selected' : '' ?>><?= h($city) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="status" aria-label="Status"><option value="">Todos os status</option><option value="ativo" <?= $filters['status'] === 'ativo' ? 'selected' : '' ?>>Ativos</option><option value="inativo" <?= $filters['status'] === 'inativo' ? 'selected' : '' ?>>Inativos</option></select>
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="fornecedores.php" data-live-filter-clear><i class="bi bi-x-lg"></i> Limpar filtros</a>
</form>

<section class="panel" data-live-region="results">
    <div class="panel-header budget-panel-header">
        <div class="budget-panel-heading"><div class="panel-title"><i class="bi bi-truck"></i>Fornecedores cadastrados</div><nav class="budget-status-filters" aria-label="Filtrar fornecedores por status"><?php foreach ($statusButtons as [$status, $label, $color]): ?><a class="budget-status-filter budget-status-filter-<?= h($color) ?> js-supplier-status-filter<?= $filters['status'] === $status ? ' active' : '' ?>" href="<?= h(supplier_status_url($filters, $status)) ?>" data-status="<?= h($status) ?>" <?= $filters['status'] === $status ? 'aria-current="true"' : '' ?>><?= h($label) ?></a><?php endforeach; ?></nav></div>
        <?php if ($canCreate): ?><button class="btn-new-os" type="button" data-bs-toggle="modal" data-bs-target="#modal-fornecedor"><i class="bi bi-truck"></i><span>Novo fornecedor</span></button><?php endif; ?>
    </div>
    <?php if ($hasMoreSuppliers): ?><div class="px-3 py-2 text-muted small border-bottom" role="status">Exibindo os primeiros 200 fornecedores. Refine os filtros para localizar os demais.</div><?php endif; ?>
    <?php if ($suppliers === []): ?><?php empty_state('Nenhum fornecedor encontrado', 'Cadastre o primeiro fornecedor ou ajuste os filtros.'); ?><?php else: ?>
    <div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Código</th><th>Fornecedor</th><th>CPF/CNPJ</th><th>Contato</th><th>Cidade/UF</th><th>Status</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($suppliers as $supplier): $payload = json_encode($supplier, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}'; $status = supplier_value($supplier, 'status', 'ativo'); ?>
        <tr><td><strong><?= h(supplier_value($supplier, 'codigo')) ?></strong></td><td><strong><?= h(supplier_value($supplier, 'nome')) ?></strong><?php if (supplier_value($supplier, 'nome_fantasia') !== ''): ?><br><small class="text-muted"><?= h(supplier_value($supplier, 'nome_fantasia')) ?></small><?php endif; ?></td><td><?= h(supplier_value($supplier, 'documento', '-')) ?></td><td><?= h(supplier_value($supplier, 'telefone', '-')) ?><?php if (supplier_value($supplier, 'email') !== ''): ?><br><small class="text-muted"><?= h(supplier_value($supplier, 'email')) ?></small><?php endif; ?></td><td><?= h(trim(supplier_value($supplier, 'cidade') . '/' . supplier_value($supplier, 'estado'), '/')) ?: '-' ?></td><td><span class="badge-soft badge-<?= h(supplier_status_badge($status)) ?>"><?= h(supplier_status_label($status)) ?></span></td>
        <td class="table-actions-cell"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do fornecedor <?= h(supplier_value($supplier, 'nome')) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item js-supplier-view" type="button" data-supplier="<?= h($payload) ?>" data-bs-toggle="modal" data-bs-target="#modal-fornecedor-view"><i class="bi bi-eye"></i> Visualizar</button></li><?php if ($canEdit): ?><li><button class="dropdown-item js-supplier-edit" type="button" data-supplier="<?= h($payload) ?>" data-bs-toggle="modal" data-bs-target="#modal-fornecedor-edit"><i class="bi bi-pencil"></i> Editar</button></li><?php endif; ?><?php if ($canStatus): ?><li><hr class="dropdown-divider"></li><li><button class="dropdown-item js-supplier-status <?= $status === 'ativo' ? 'text-danger' : '' ?>" type="button" data-supplier="<?= h($payload) ?>" data-bs-toggle="modal" data-bs-target="#modal-fornecedor-status"><i class="bi <?= $status === 'ativo' ? 'bi-building-dash' : 'bi-building-check' ?>"></i> <?= $status === 'ativo' ? 'Desativar' : 'Ativar' ?></button></li><?php endif; ?></ul></div></td></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
</div>

<?php if ($canCreate): ?><div class="modal fade" id="modal-fornecedor" tabindex="-1" aria-hidden="true" <?= ($recovery['mode'] ?? '') === 'create' ? 'data-recovery-open="true"' : '' ?>><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/fornecedor-salvar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Novo fornecedor</h2><p class="text-muted small mb-0">O código será gerado automaticamente.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="operation" value="create"><?php if (($recovery['mode'] ?? '') === 'create'): ?><div class="alert alert-danger" role="alert"><?= h((string) $recovery['error']) ?></div><?php endif; ?><?php supplier_form_fields('create-supplier', $createData); ?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Cadastrar fornecedor</button></div></form></div></div><?php endif; ?>

<div class="modal fade" id="modal-fornecedor-view" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content visual-modal"><div class="modal-header"><div><h2 class="modal-title fs-5">Dados do fornecedor</h2><p class="text-muted small mb-0" id="supplier-view-subtitle"></p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body" id="supplier-view-content"></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Fechar</button></div></div></div></div>

<?php if ($canEdit): ?><div class="modal fade" id="modal-fornecedor-edit" tabindex="-1" aria-hidden="true" <?= ($recovery['mode'] ?? '') === 'edit' ? 'data-recovery-open="true"' : '' ?>><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/fornecedor-salvar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Editar fornecedor</h2><p class="text-muted small mb-0" id="supplier-edit-subtitle"><?= h(supplier_value($editData, 'codigo')) ?></p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="operation" value="update"><input type="hidden" name="id" value="<?= h(supplier_value($editData, 'id')) ?>"><?php if (($recovery['mode'] ?? '') === 'edit'): ?><div class="alert alert-danger" role="alert"><?= h((string) $recovery['error']) ?></div><?php endif; ?><?php supplier_form_fields('edit-supplier', $editData); ?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar alterações</button></div></form></div></div><?php endif; ?>

<?php if ($canStatus): ?><div class="modal fade" id="modal-fornecedor-status" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/fornecedor-status.php"><div class="modal-header"><h2 class="modal-title fs-5" id="supplier-status-title">Alterar fornecedor</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id"><input type="hidden" name="status"><p class="mb-0" id="supplier-status-message"></p></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit" id="supplier-status-submit">Confirmar</button></div></form></div></div></div><?php endif; ?>
