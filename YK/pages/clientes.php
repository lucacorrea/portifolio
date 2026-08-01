<?php

declare(strict_types=1);

use App\CRM\Entity\Client;
use App\Sales\Entity\Budget;

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/cliente-action-common.php';

function client_query_filter(string $key, int $maximumLength): string
{
    $raw = $_GET[$key] ?? '';
    if (!is_string($raw)) return '';
    $value = trim($raw);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    return $length <= $maximumLength && !str_contains($value, "\0") ? $value : '';
}

$clientService = $application->clientManagement();
$budgetService = $application->budgetManagement();
$filters = [
    'search' => client_query_filter('search', 150),
    'type' => client_query_filter('type', 20),
    'city' => client_query_filter('city', 100),
    'status' => client_query_filter('status', 20),
];
if (!in_array($filters['type'], ['', 'fisica', 'juridica'], true)) $filters['type'] = '';
if (!in_array($filters['status'], ['', 'ativo', 'inativo'], true)) $filters['status'] = '';
$listFilters = $filters;
$listFilters['limit'] = 101;
$clients = $clientService->listClients($listFilters);
$initialHasMore = count($clients) > 100;
$clients = array_slice($clients, 0, 100);
$summary = $clientService->clientSummary();
$allClients = $clientService->listClients();
$cities = array_values(array_unique(array_filter(array_map(static fn(Client $client): string => (string) $client->city(), $allClients))));
sort($cities);
$canCreate = $authorization->can('cliente.criar');
$canImport = $authorization->can('cliente.importar');
$canEdit = $authorization->can('cliente.editar');
$canStatus = $authorization->can('cliente.desativar');
$canDelete = $authorization->can('cliente.excluir');
$canHistory = $authorization->can('cliente.visualizar_historico');
$canViewBudget = $authorization->can('orcamento.visualizar');
$clientBudgets = [];
if ($canHistory && $clients !== []) {
    $clientIds = array_map(static fn(Client $client): int => $client->id(), $clients);
    foreach ($budgetService->budgetsByClients($clientIds) as $budget) {
        $clientBudgets[$budget->clientId()][] = $budget;
    }
}
$recovery = client_consume_form_recovery();
$importPreview = $canImport ? client_import_preview() : null;

function client_data(?array $recovery, string $modal): array
{
    return $recovery !== null && ($recovery['modal'] ?? '') === $modal && is_array($recovery['data'] ?? null) ? $recovery['data'] : [];
}

function client_error(?array $recovery, string $modal): ?string
{
    return $recovery !== null && ($recovery['modal'] ?? '') === $modal && is_string($recovery['error'] ?? null) ? $recovery['error'] : null;
}

function client_value(array $data, string $key, string $default = ''): string
{
    $value = $data[$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

function client_date(string $value): string
{
    try { return (new DateTimeImmutable($value))->format('d/m/Y H:i'); } catch (Throwable) { return '-'; }
}

function client_document(?string $document, string $type): string
{
    if ($document === null || $document === '') return '-';
    if ($type === 'juridica' && strlen($document) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $document) ?? $document;
    if (strlen($document) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $document) ?? $document;
    return $document;
}

function client_status_label(string $status): string
{
    return $status === 'ativo' ? 'Ativo' : 'Inativo';
}

function client_status_class(string $status): string
{
    return $status === 'ativo' ? 'green' : 'gray';
}

function client_status_filter_url(array $filters, string $status): string
{
    $query = array_filter($filters, static fn(mixed $value): bool => is_scalar($value) && (string) $value !== '');
    if ($status === '') unset($query['status']);
    else $query['status'] = $status;
    return 'clientes.php' . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
}

$statusFilterButtons = [
    ['', 'Todos', 'all'],
    ['ativo', 'Ativos', 'green'],
    ['inativo', 'Inativos', 'gray'],
];

function client_address(Client $client): string
{
    $address = trim(implode(', ', array_filter([$client->address(), $client->number(), $client->district()])));
    $cityState = trim(implode('/', array_filter([$client->city(), $client->state()])));
    $parts = array_filter([$address, $cityState]);
    return $parts === [] ? 'Endereço não informado' : implode(', ', $parts);
}

$createData = client_data($recovery, 'create');
$editData = client_data($recovery, 'edit');
$createError = client_error($recovery, 'create');
$editError = client_error($recovery, 'edit');
?>

<div class="page-body clients-page">
<?php metric_grid([
    ['Total de clientes', (string) ($summary['total'] ?? 0), 'bi-people', '#2563EB', 'cadastrados'],
    ['Clientes ativos', (string) ($summary['active'] ?? 0), 'bi-person-check', '#16A34A', 'aptos para orçamento'],
    ['Clientes inativos', (string) ($summary['inactive'] ?? 0), 'bi-person-dash', '#64748B', 'sem novos orçamentos'],
    ['Novos no mês', (string) ($summary['new_month'] ?? 0), 'bi-person-plus', '#0EA5E9', 'cadastros recentes'],
]); ?>

<form class="filter-bar" id="client-filter-form" method="get" action="clientes.php">
    <div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" id="client-search-input" type="search" name="search" value="<?= h($filters['search']) ?>" placeholder="Buscar código, nome, CPF/CNPJ, telefone ou e-mail" aria-label="Pesquisar clientes" maxlength="150" autocomplete="off"></div>
    <select class="filter-select" name="type" aria-label="Tipo de pessoa"><option value="">Todos os tipos</option><option value="fisica" <?= $filters['type'] === 'fisica' ? 'selected' : '' ?>>Pessoa Física</option><option value="juridica" <?= $filters['type'] === 'juridica' ? 'selected' : '' ?>>Pessoa Jurídica</option></select>
    <select class="filter-select" name="city" aria-label="Cidade"><option value="">Todas as cidades</option><?php foreach ($cities as $city): ?><option value="<?= h($city) ?>" <?= $filters['city'] === $city ? 'selected' : '' ?>><?= h($city) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="status" aria-label="Status"><option value="">Todos os status</option><option value="ativo" <?= $filters['status'] === 'ativo' ? 'selected' : '' ?>>Ativos</option><option value="inativo" <?= $filters['status'] === 'inativo' ? 'selected' : '' ?>>Inativos</option></select>
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" id="client-filter-clear" href="clientes.php"><i class="bi bi-x-lg"></i> Limpar filtros</a>
</form>

<section class="panel">
    <div class="panel-header budget-panel-header">
        <div class="budget-panel-heading">
            <div class="panel-title"><i class="bi bi-people"></i>Clientes cadastrados</div>
            <nav class="budget-status-filters" aria-label="Filtrar clientes por status">
                <?php foreach ($statusFilterButtons as [$statusValue, $statusLabel, $statusClass]): ?>
                    <?php $isActiveStatus = $filters['status'] === $statusValue; ?>
                    <a
                        class="budget-status-filter budget-status-filter-<?= h($statusClass) ?> js-client-status-filter<?= $isActiveStatus ? ' active' : '' ?>"
                        href="<?= h(client_status_filter_url($filters, $statusValue)) ?>"
                        data-status="<?= h($statusValue) ?>"
                        <?= $isActiveStatus ? 'aria-current="true"' : '' ?>
                    ><?= h($statusLabel) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="d-flex flex-wrap gap-2"><?php if ($canImport): ?><button class="btn-filter btn-filter-ghost" type="button" data-bs-toggle="modal" data-bs-target="#modal-client-import"><i class="bi bi-file-earmark-pdf"></i><span>Adicionar pelo PDF</span></button><?php endif; ?><?php if ($canCreate): ?><button class="btn-new-os" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente"><i class="bi bi-person-plus"></i><span>Novo cliente</span></button><?php endif; ?></div>
    </div>
    <div class="px-3 pt-3" id="client-search-feedback" role="status" aria-live="polite"><?= h($initialHasMore ? 'Exibindo os primeiros 100 clientes. Refine a pesquisa.' : count($clients) . ' cliente(s) encontrado(s).') ?></div>
    <div class="alert alert-danger mx-3 mt-3 mb-0 d-none" id="client-search-error" role="alert"><span></span> <button class="btn btn-link alert-link p-0 align-baseline" id="client-search-retry" type="button">Tentar novamente</button></div>
    <div class="table-panel-wrap">
        <table class="os-table clients-table" id="clients-table" aria-busy="false">
            <thead><tr><th>Código</th><th>Cliente</th><th>CPF/CNPJ</th><th>Contato</th><th>Endereço</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody id="clients-table-body">
            <?php if ($clients === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Nenhum cliente encontrado.</td></tr>
            <?php else: ?>
            <?php foreach ($clients as $client): ?>
                <?php $history = $clientBudgets[$client->id()] ?? []; ?>
                <tr>
                    <td><strong><?= h($client->displayCode()) ?></strong></td>
                    <td>
                        <button class="table-inline-action js-client-actions" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente-actions" data-client-id="<?= h((string) $client->id()) ?>" aria-label="Abrir ações de <?= h($client->name()) ?>"><?= h($client->name()) ?></button>
                        <br><small class="text-muted"><?= h($client->personTypeLabel()) ?></small>
                    </td>
                    <td><?= h(client_document($client->document(), $client->personType())) ?></td>
                    <td><span><?= h($client->phone() ?? '-') ?></span><br><small><?= h($client->whatsapp() ?? '-') ?></small><?php if ($client->email()): ?><br><small><?= h($client->email()) ?></small><?php endif; ?></td>
                    <td><?= h(client_address($client)) ?></td>
                    <td><span class="badge-soft badge-<?= h(client_status_class($client->status())) ?>"><?= h(client_status_label($client->status())) ?></span></td>
                    <td class="table-actions-cell"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do cliente <?= h($client->name()) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item js-client-view" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente-view" data-client-id="<?= h((string) $client->id()) ?>"><i class="bi bi-eye"></i> Visualizar</button></li>
                        <?php if ($canEdit): ?><li><button class="dropdown-item js-client-edit" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente-edit" data-client-id="<?= h((string) $client->id()) ?>"><i class="bi bi-pencil"></i> Editar</button></li><?php endif; ?>
                        <?php if ($canStatus): ?><li><hr class="dropdown-divider"></li><li><button class="dropdown-item js-client-status <?= $client->status() === 'ativo' ? 'text-danger' : '' ?>" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente-status" data-client-id="<?= h((string) $client->id()) ?>" data-client-name="<?= h($client->name()) ?>" data-client-status="<?= $client->status() === 'ativo' ? 'inativo' : 'ativo' ?>"><i class="bi <?= $client->status() === 'ativo' ? 'bi-person-dash' : 'bi-person-check' ?>"></i> <?= $client->status() === 'ativo' ? 'Desativar' : 'Ativar' ?></button></li><?php endif; ?>
                        <?php if ($canDelete): ?><li><button class="dropdown-item text-danger js-client-delete" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente-delete" data-client-id="<?= h((string) $client->id()) ?>" data-client-name="<?= h($client->name()) ?>"><i class="bi bi-trash3"></i> Excluir cliente</button></li><?php endif; ?>
                    </ul></div></td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

<?php
$clientPayload = [];
foreach ($clients as $client) {
    $clientPayload[(string) $client->id()] = [
        'id' => $client->id(), 'code' => $client->displayCode(), 'person_type' => $client->personType(), 'person_type_label' => $client->personTypeLabel(),
        'name' => $client->name(), 'document' => $client->document(), 'document_label' => client_document($client->document(), $client->personType()),
        'phone' => $client->phone(), 'whatsapp' => $client->whatsapp(), 'email' => $client->email(), 'address' => $client->address(), 'number' => $client->number(),
        'complement' => $client->complement(), 'district' => $client->district(), 'city' => $client->city(), 'state' => $client->state(), 'zip_code' => $client->zipCode(),
        'full_address' => client_address($client), 'notes' => $client->notes(), 'status' => $client->status(), 'status_label' => client_status_label($client->status()),
        'created_at' => client_date($client->createdAt()), 'updated_at' => client_date($client->updatedAt()),
        'budgets' => array_map(static fn(Budget $budget): array => ['id' => $budget->id(), 'number' => $budget->displayNumber(), 'issue_date' => $budget->issueDate(), 'valid_until' => $budget->validUntil(), 'total' => $budget->total(), 'status' => $budget->displayStatus()], $clientBudgets[$client->id()] ?? []),
    ];
}

function client_form_fields(array $data, string $prefix, bool $editing = false): void {
?>
<section class="form-section"><h3 class="form-section-title">Identificação</h3><div class="form-row"><div class="form-group"><label class="form-label">Tipo de pessoa</label><select class="form-control-os js-client-person-type" id="<?= h($prefix) ?>-person-type" name="person_type"><option value="fisica" <?= client_value($data, 'person_type', 'fisica') === 'fisica' ? 'selected' : '' ?>>Pessoa Física</option><option value="juridica" <?= client_value($data, 'person_type') === 'juridica' ? 'selected' : '' ?>>Pessoa Jurídica</option></select></div><div class="form-group"><label class="form-label">Nome / Razão social</label><input class="form-control-os" id="<?= h($prefix) ?>-name" name="name" value="<?= h(client_value($data, 'name')) ?>" maxlength="150" required></div></div><div class="form-row"><div class="form-group"><label class="form-label js-client-document-label" for="<?= h($prefix) ?>-document">CPF</label><input class="form-control-os js-client-document" id="<?= h($prefix) ?>-document" name="document" value="<?= h(client_value($data, 'document')) ?>" maxlength="20"></div><?php if (!$editing): ?><div class="form-group"><label class="form-label">Status</label><select class="form-control-os" id="<?= h($prefix) ?>-status" name="status"><option value="ativo" <?= client_value($data, 'status', 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option><option value="inativo" <?= client_value($data, 'status') === 'inativo' ? 'selected' : '' ?>>Inativo</option></select></div><?php endif; ?></div></section>
<section class="form-section"><h3 class="form-section-title">Contato e endereço</h3><div class="form-row"><div class="form-group"><label class="form-label">Telefone</label><input class="form-control-os" id="<?= h($prefix) ?>-phone" name="phone" value="<?= h(client_value($data, 'phone')) ?>" maxlength="30"></div><div class="form-group"><label class="form-label">WhatsApp</label><input class="form-control-os" id="<?= h($prefix) ?>-whatsapp" name="whatsapp" value="<?= h(client_value($data, 'whatsapp')) ?>" maxlength="30"></div><div class="form-group"><label class="form-label">E-mail</label><input class="form-control-os" id="<?= h($prefix) ?>-email" type="email" name="email" value="<?= h(client_value($data, 'email')) ?>" maxlength="150"></div></div><div class="form-row"><div class="form-group"><label class="form-label">CEP</label><input class="form-control-os" id="<?= h($prefix) ?>-zip-code" name="zip_code" value="<?= h(client_value($data, 'zip_code')) ?>" maxlength="10"></div><div class="form-group"><label class="form-label">Endereço</label><input class="form-control-os" id="<?= h($prefix) ?>-address" name="address" value="<?= h(client_value($data, 'address')) ?>" maxlength="150"></div><div class="form-group"><label class="form-label">Número</label><input class="form-control-os" id="<?= h($prefix) ?>-number" name="number" value="<?= h(client_value($data, 'number')) ?>" maxlength="30"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Complemento</label><input class="form-control-os" id="<?= h($prefix) ?>-complement" name="complement" value="<?= h(client_value($data, 'complement')) ?>" maxlength="100"></div><div class="form-group"><label class="form-label">Bairro</label><input class="form-control-os" id="<?= h($prefix) ?>-district" name="district" value="<?= h(client_value($data, 'district')) ?>" maxlength="100"></div><div class="form-group"><label class="form-label">Cidade</label><input class="form-control-os" id="<?= h($prefix) ?>-city" name="city" value="<?= h(client_value($data, 'city')) ?>" maxlength="100"></div><div class="form-group"><label class="form-label">UF</label><input class="form-control-os" id="<?= h($prefix) ?>-state" name="state" value="<?= h(client_value($data, 'state')) ?>" maxlength="2"></div></div><div class="form-group"><label class="form-label">Observações</label><textarea class="form-control-os" id="<?= h($prefix) ?>-notes" name="notes" rows="3"><?= h(client_value($data, 'notes')) ?></textarea></div></section>
<?php } ?>

<?php if ($canCreate): ?><div class="modal fade" id="modal-cliente" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/cliente-salvar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Novo cliente</h2><p class="text-muted small mb-0">O código será gerado automaticamente.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><div class="alert alert-danger <?= $createError === null ? 'd-none' : '' ?>" id="create-client-form-error" role="alert"><?= h($createError ?? '') ?></div><?php client_form_fields($createData, 'create-client'); ?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button></div></form></div></div><?php endif; ?>

<?php if ($canImport): ?>
<div class="modal fade" id="modal-client-import" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/clientes-importar-analisar.php" enctype="multipart/form-data"><div class="modal-header"><div><h2 class="modal-title fs-5">Adicionar clientes pelo PDF</h2><p class="text-muted small mb-0">Use o relatório “RELATÓRIO DE CLIENTES 2” exportado pelo A7.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="MAX_FILE_SIZE" value="5242880"><div class="alert alert-info" role="note"><i class="bi bi-shield-check me-1"></i> O PDF será somente analisado nesta etapa. Nenhum cliente será gravado antes da sua confirmação.</div><div class="form-group"><label class="form-label" for="client-import-pdf">Arquivo PDF</label><input class="form-control-os" id="client-import-pdf" type="file" name="client_pdf" accept="application/pdf,.pdf" required><small class="text-muted">Máximo de 5 MB e 200 páginas. O arquivo não será armazenado.</small></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-search"></i> Analisar PDF</button></div></form></div></div>

<?php if ($importPreview !== null): ?>
<?php $importSummary = $importPreview['summary']; ?>
<div class="modal fade" id="modal-client-import-preview" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content visual-modal"><div class="modal-header"><div><h2 class="modal-title fs-5">Revisar importação</h2><p class="text-muted small mb-0"><?= h((string) ($importPreview['source_name'] ?? 'PDF')) ?> · <?= h((string) ($importPreview['pages'] ?? 0)) ?> página(s)</p></div></div><div class="modal-body">
<div class="row g-3 mb-4">
    <div class="col-6 col-lg"><div class="form-section h-100"><small class="text-muted">Encontrados</small><strong class="d-block fs-4"><?= h((string) ($importSummary['total'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-lg"><div class="form-section h-100"><small class="text-muted">Para importar</small><strong class="d-block fs-4 text-success"><?= h((string) ($importSummary['ready'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-lg"><div class="form-section h-100"><small class="text-muted">Já importados</small><strong class="d-block fs-4"><?= h((string) ($importSummary['existing'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-lg"><div class="form-section h-100"><small class="text-muted">Possíveis duplicados</small><strong class="d-block fs-4 text-warning"><?= h((string) ($importSummary['possible_duplicates'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-lg"><div class="form-section h-100"><small class="text-muted">Inválidos</small><strong class="d-block fs-4 text-danger"><?= h((string) ($importSummary['invalid'] ?? 0)) ?></strong></div></div>
</div>
<?php if (($importSummary['warnings'] ?? 0) > 0): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= h((string) $importSummary['warnings']) ?> registro(s) possui(em) nome e telefone repetidos dentro do próprio PDF. Os códigos A7 são diferentes, por isso serão preservados.</div><?php endif; ?>
<div class="alert alert-secondary">Somente códigos A7 já importados e registros inválidos serão ignorados. Possíveis duplicidades serão sinalizadas, mas preservadas por possuírem código de origem próprio. A confirmação grava o lote em uma única transação.</div>
<div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Código</th><th>Cliente</th><th>Telefone</th><th>Cidade</th><th>Análise</th></tr></thead><tbody>
<?php foreach ($importPreview['preview'] as $row): ?>
<?php
$status = (string) ($row['status'] ?? 'invalid');
$statusClass = match ($status) { 'ready' => 'green', 'existing' => 'gray', 'possible_duplicate' => 'amber', default => 'red' };
?>
<tr><td><strong><?= h((string) ($row['code'] ?? '')) ?></strong></td><td><?= h((string) ($row['name'] ?? '')) ?></td><td><?= h((string) ($row['phone'] ?? '-')) ?></td><td><?= h((string) ($row['city'] ?? '-')) ?></td><td><span class="badge-soft badge-<?= h($statusClass) ?>"><?= h((string) ($row['message'] ?? '')) ?></span></td></tr>
<?php endforeach; ?>
</tbody></table></div><p class="text-muted small mt-2 mb-0">A tabela mostra uma amostra da análise; os totais acima consideram o PDF completo.</p>
</div><div class="modal-footer"><form method="post" action="actions/clientes-importar-cancelar.php"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="import_token" value="<?= h((string) $importPreview['token']) ?>"><button class="btn-modal-cancel" type="submit">Descartar análise</button></form><?php if (($importSummary['ready'] ?? 0) > 0): ?><form method="post" action="actions/clientes-importar-confirmar.php"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="import_token" value="<?= h((string) $importPreview['token']) ?>"><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Importar <?= h((string) $importSummary['ready']) ?> cliente(s)</button></form><?php endif; ?></div></div></div></div>
<?php endif; ?>
<?php endif; ?>

<div class="modal fade" id="modal-cliente-actions" tabindex="-1" aria-labelledby="client-actions-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content visual-modal">
    <div class="modal-header"><div><h2 class="modal-title fs-5" id="client-actions-title">Ações do cliente</h2><p class="text-muted small mb-0" id="client-actions-subtitle"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body">
        <div class="form-section mb-3"><div class="d-flex align-items-center justify-content-between gap-3"><div><small class="text-muted d-block">Cliente selecionado</small><strong id="client-actions-name"></strong></div><span class="badge-soft" id="client-actions-status"></span></div></div>
        <div class="d-grid gap-2" role="group" aria-label="Ações disponíveis para o cliente">
            <button class="btn-modal-secondary justify-content-start js-client-view" id="client-actions-view" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente-view"><i class="bi bi-eye"></i> Visualizar dados</button>
            <?php if ($canEdit): ?><button class="btn-modal-secondary justify-content-start js-client-edit" id="client-actions-edit" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente-edit"><i class="bi bi-pencil"></i> Editar cliente</button><?php endif; ?>
            <?php if ($canStatus): ?><button class="btn-modal-secondary justify-content-start js-client-status" id="client-actions-status-button" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente-status"><i class="bi" id="client-actions-status-icon"></i><span id="client-actions-status-label"></span></button><?php endif; ?>
            <?php if ($canDelete): ?><button class="btn-modal-secondary justify-content-start text-danger js-client-delete" id="client-actions-delete" type="button" data-bs-toggle="modal" data-bs-target="#modal-cliente-delete"><i class="bi bi-trash3"></i> Excluir cliente</button><?php endif; ?>
        </div>
    </div>
    <div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Fechar</button></div>
</div></div></div>

<div class="modal fade" id="modal-cliente-view" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content visual-modal"><div class="modal-header"><div><h2 class="modal-title fs-5">Dados do cliente</h2><p class="text-muted small mb-0" id="view-client-subtitle"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><section class="form-section"><h3 class="form-section-title">Cadastro</h3><div class="form-row"><div class="form-group"><label class="form-label">Código</label><div class="form-control-os" id="view-client-code"></div></div><div class="form-group"><label class="form-label">Tipo</label><div class="form-control-os" id="view-client-person-type"></div></div><div class="form-group"><label class="form-label">Status</label><div class="form-control-os" id="view-client-status"></div></div></div><div class="form-row"><div class="form-group"><label class="form-label">Nome</label><div class="form-control-os" id="view-client-name"></div></div><div class="form-group"><label class="form-label">CPF/CNPJ</label><div class="form-control-os" id="view-client-document"></div></div></div><div class="form-row"><div class="form-group"><label class="form-label">Telefone</label><div class="form-control-os" id="view-client-phone"></div></div><div class="form-group"><label class="form-label">WhatsApp</label><div class="form-control-os" id="view-client-whatsapp"></div></div><div class="form-group"><label class="form-label">E-mail</label><div class="form-control-os" id="view-client-email"></div></div></div><div class="form-group"><label class="form-label">Endereço completo</label><div class="form-control-os" id="view-client-address"></div></div><div class="form-group"><label class="form-label">Observações</label><div class="form-control-os" id="view-client-notes"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Cadastrado em</label><div class="form-control-os" id="view-client-created-at"></div></div><div class="form-group"><label class="form-label">Atualizado em</label><div class="form-control-os" id="view-client-updated-at"></div></div></div></section><?php if ($canHistory): ?><section class="form-section"><h3 class="form-section-title">Orçamentos do cliente</h3><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Número</th><th>Emissão</th><th>Validade</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead><tbody id="view-client-budgets"></tbody></table></div></section><?php endif; ?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Fechar</button></div></div></div></div>

<?php if ($canEdit): ?><div class="modal fade" id="modal-cliente-edit" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/cliente-salvar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Editar cliente</h2><p class="text-muted small mb-0" id="edit-client-subtitle"><?= h(client_value($editData, 'code')) ?></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><div class="alert alert-danger <?= $editError === null ? 'd-none' : '' ?>" id="edit-client-form-error" role="alert"><?= h($editError ?? '') ?></div><input type="hidden" name="id" id="edit-client-id" value="<?= h(client_value($editData, 'id')) ?>"><section class="form-section"><h3 class="form-section-title">Código</h3><input class="form-control-os" id="edit-client-code" type="text" value="<?= h(client_value($editData, 'code')) ?>" readonly></section><?php client_form_fields($editData, 'edit-client', true); ?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button></div></form></div></div><?php endif; ?>

<?php if ($canStatus): ?><div class="modal fade" id="modal-cliente-status" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/cliente-status.php"><div class="modal-header"><h2 class="modal-title fs-5" id="client-status-title">Alterar status</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="status-client-id"><input type="hidden" name="status" id="status-client-value"><p id="client-status-message" class="mb-0"></p></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div><?php endif; ?>

<?php if ($canDelete): ?><div class="modal fade" id="modal-cliente-delete" tabindex="-1" aria-labelledby="client-delete-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/cliente-excluir.php"><div class="modal-header"><h2 class="modal-title fs-5" id="client-delete-title">Excluir cliente</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="delete-client-id"><p>Deseja excluir <strong id="delete-client-name"></strong>?</p><div class="alert alert-warning mb-0">O cadastro sairá das telas, mas o histórico será preservado. Orçamentos ou OS em andamento impedem a exclusão.</div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-trash3"></i> Excluir cliente</button></div></form></div></div><?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    let clients = <?= json_encode($clientPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const recoveryModal = <?= json_encode($recovery['modal'] ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
    const canStatus = <?= $canStatus ? 'true' : 'false' ?>;
    const canDelete = <?= $canDelete ? 'true' : 'false' ?>;
    const canViewBudget = <?= $canViewBudget ? 'true' : 'false' ?>;
    const openImportUpload = <?= $canImport && (string) ($_GET['modal'] ?? '') === 'import' ? 'true' : 'false' ?>;
    const openImportPreview = <?= $importPreview !== null && (string) ($_GET['modal'] ?? '') === 'import-preview' ? 'true' : 'false' ?>;
    const filterForm = document.getElementById('client-filter-form');
    const searchInput = document.getElementById('client-search-input');
    const clearFilters = document.getElementById('client-filter-clear');
    const clientsTable = document.getElementById('clients-table');
    const clientsBody = document.getElementById('clients-table-body');
    const searchFeedback = document.getElementById('client-search-feedback');
    const searchError = document.getElementById('client-search-error');
    const searchRetry = document.getElementById('client-search-retry');
    let searchTimer = null;
    let searchController = null;
    let searchSequence = 0;
    function text(id, value) { const element = document.getElementById(id); if (element) element.textContent = value || '-'; }
    function val(id, value) { const element = document.getElementById(id); if (element) element.value = value || ''; }
    function money(value) { return Number.parseFloat(value || '0').toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }
    function element(tag, className, value) { const node = document.createElement(tag); if (className) node.className = className; if (value !== undefined && value !== null) node.textContent = value; return node; }
    function updateDocumentLabel(select) { const modal = select.closest('.modal'); if (!modal) return; const label = modal.querySelector('.js-client-document-label'); if (label) label.textContent = select.value === 'juridica' ? 'CNPJ' : 'CPF'; }
    document.querySelectorAll('.js-client-person-type').forEach(function (select) { updateDocumentLabel(select); select.addEventListener('change', function () { updateDocumentLabel(select); }); });
    function prepareClientActions(client) {
        text('client-actions-subtitle', client.code + ' · ' + client.person_type_label); text('client-actions-name', client.name); text('client-actions-status', client.status_label);
        const statusBadge = document.getElementById('client-actions-status'); if (statusBadge) statusBadge.className = 'badge-soft badge-' + (client.status === 'ativo' ? 'green' : 'gray');
        ['view', 'edit', 'delete'].forEach(function (action) { const actionButton = document.getElementById('client-actions-' + action); if (actionButton) { actionButton.dataset.clientId = client.id; actionButton.dataset.clientName = client.name; } });
        const statusButton = document.getElementById('client-actions-status-button'); if (statusButton) { const activate = client.status !== 'ativo'; statusButton.dataset.clientId = client.id; statusButton.dataset.clientName = client.name; statusButton.dataset.clientStatus = activate ? 'ativo' : 'inativo'; statusButton.classList.toggle('text-danger', !activate); text('client-actions-status-label', activate ? 'Ativar cliente' : 'Desativar cliente'); const icon = document.getElementById('client-actions-status-icon'); if (icon) icon.className = 'bi ' + (activate ? 'bi-person-check' : 'bi-person-dash'); }
    }
    function prepareClientView(client) {
        text('view-client-subtitle', client.code); text('view-client-code', client.code); text('view-client-person-type', client.person_type_label); text('view-client-status', client.status_label); text('view-client-name', client.name); text('view-client-document', client.document_label); text('view-client-phone', client.phone); text('view-client-whatsapp', client.whatsapp); text('view-client-email', client.email); text('view-client-address', client.full_address); text('view-client-notes', client.notes); text('view-client-created-at', client.created_at); text('view-client-updated-at', client.updated_at);
        const tbody = document.getElementById('view-client-budgets'); if (tbody) { tbody.replaceChildren(); if (!client.budgets.length) { const row = document.createElement('tr'); const cell = document.createElement('td'); cell.colSpan = 6; cell.textContent = 'Nenhum orçamento vinculado.'; row.appendChild(cell); tbody.appendChild(row); } client.budgets.forEach(function (budget) { const row = document.createElement('tr'); ['number','issue_date','valid_until'].forEach(function (key) { const cell = document.createElement('td'); cell.textContent = budget[key] || '-'; row.appendChild(cell); }); const total = document.createElement('td'); total.textContent = money(budget.total); row.appendChild(total); const status = document.createElement('td'); status.textContent = budget.status; row.appendChild(status); const actions = document.createElement('td'); if (canViewBudget) { const link = document.createElement('a'); link.className = 'btn-filter btn-filter-ghost'; link.href = 'orcamentos.php?search=' + encodeURIComponent(budget.number); link.textContent = 'Abrir'; actions.appendChild(link); } row.appendChild(actions); tbody.appendChild(row); }); }
    }
    function prepareClientEdit(client) {
        ['id','code','person-type','name','document','phone','whatsapp','email','address','number','complement','district','city','state','zip-code','notes','status'].forEach(function (field) { const key = field.replaceAll('-', '_'); val('edit-client-' + field, client[key]); }); text('edit-client-subtitle', client.code); document.querySelectorAll('#modal-cliente-edit .js-client-person-type').forEach(updateDocumentLabel);
    }
    function prepareClientStatus(button) { const activate = button.dataset.clientStatus === 'ativo'; val('status-client-id', button.dataset.clientId); val('status-client-value', button.dataset.clientStatus); text('client-status-title', activate ? 'Ativar cliente' : 'Desativar cliente'); text('client-status-message', (activate ? 'Deseja ativar ' : 'Deseja desativar ') + (button.dataset.clientName || 'este cliente') + '?'); }
    function prepareClientDelete(button) { val('delete-client-id', button.dataset.clientId); text('delete-client-name', button.dataset.clientName || 'este cliente'); }
    document.addEventListener('click', function (event) {
        const button = event.target.closest('.js-client-status-filter, .js-client-actions, .js-client-view, .js-client-edit, .js-client-status, .js-client-delete');
        if (!button) return;
        if (button.classList.contains('js-client-status-filter')) {
            event.preventDefault();
            const statusSelect = filterForm?.querySelector('select[name="status"]');
            if (!statusSelect) {
                window.location.assign(button.href);
                return;
            }
            statusSelect.value = button.dataset.status || '';
            searchClients();
            return;
        }
        const client = clients[String(button.dataset.clientId || '')];
        if (button.classList.contains('js-client-actions') && client) prepareClientActions(client);
        else if (button.classList.contains('js-client-view') && client) prepareClientView(client);
        else if (button.classList.contains('js-client-edit') && client) prepareClientEdit(client);
        else if (button.classList.contains('js-client-status')) prepareClientStatus(button);
        else if (button.classList.contains('js-client-delete')) prepareClientDelete(button);
    });
    function actionItem(label, iconClass, className, target, client) {
        const item = element('li'); const button = element('button', 'dropdown-item ' + className);
        button.type = 'button'; button.dataset.bsToggle = 'modal'; button.dataset.bsTarget = target; button.dataset.clientId = String(client.id);
        button.appendChild(element('i', 'bi ' + iconClass)); button.appendChild(document.createTextNode(' ' + label)); item.appendChild(button); return item;
    }
    function renderClients(rows) {
        window.OSMais?.refreshActionTables?.();
        const fragment = document.createDocumentFragment();
        clients = Object.fromEntries(rows.map(function (client) { return [String(client.id), client]; }));
        if (rows.length === 0) {
            const row = element('tr'); const cell = element('td', 'text-center text-muted py-4', 'Nenhum cliente encontrado.'); cell.colSpan = 7; row.appendChild(cell); fragment.appendChild(row);
        }
        rows.forEach(function (client) {
            const row = element('tr');
            const codeCell = element('td'); codeCell.appendChild(element('strong', '', client.code)); row.appendChild(codeCell);
            const clientCell = element('td'); const nameButton = element('button', 'table-inline-action js-client-actions', client.name); nameButton.type = 'button'; nameButton.dataset.bsToggle = 'modal'; nameButton.dataset.bsTarget = '#modal-cliente-actions'; nameButton.dataset.clientId = String(client.id); nameButton.setAttribute('aria-label', 'Abrir ações de ' + client.name); clientCell.appendChild(nameButton); clientCell.appendChild(document.createElement('br')); clientCell.appendChild(element('small', 'text-muted', client.person_type_label)); row.appendChild(clientCell);
            row.appendChild(element('td', '', client.document_label || '-'));
            const contactCell = element('td'); contactCell.appendChild(element('span', '', client.phone || '-')); contactCell.appendChild(document.createElement('br')); contactCell.appendChild(element('small', '', client.whatsapp || '-')); if (client.email) { contactCell.appendChild(document.createElement('br')); contactCell.appendChild(element('small', '', client.email)); } row.appendChild(contactCell);
            row.appendChild(element('td', '', client.full_address || 'Endereço não informado'));
            const statusCell = element('td'); statusCell.appendChild(element('span', 'badge-soft badge-' + (client.status === 'ativo' ? 'green' : 'gray'), client.status_label)); row.appendChild(statusCell);
            const actionsCell = element('td', 'table-actions-cell'); const dropdown = element('div', 'dropdown table-action-dropdown'); const toggle = element('button', 'btn-action'); toggle.type = 'button'; toggle.dataset.bsToggle = 'dropdown'; toggle.setAttribute('aria-expanded', 'false'); toggle.setAttribute('aria-label', 'Ações do cliente ' + client.name); toggle.appendChild(element('i', 'bi bi-three-dots-vertical')); dropdown.appendChild(toggle);
            const menu = element('ul', 'dropdown-menu dropdown-menu-end'); menu.appendChild(actionItem('Visualizar', 'bi-eye', 'js-client-view', '#modal-cliente-view', client)); if (canEdit) menu.appendChild(actionItem('Editar', 'bi-pencil', 'js-client-edit', '#modal-cliente-edit', client)); if (canStatus || canDelete) { const dividerItem = element('li'); dividerItem.appendChild(element('hr', 'dropdown-divider')); menu.appendChild(dividerItem); } if (canStatus) { const statusItem = actionItem(client.status === 'ativo' ? 'Desativar' : 'Ativar', client.status === 'ativo' ? 'bi-person-dash' : 'bi-person-check', 'js-client-status' + (client.status === 'ativo' ? ' text-danger' : ''), '#modal-cliente-status', client); const statusButton = statusItem.querySelector('button'); statusButton.dataset.clientName = client.name; statusButton.dataset.clientStatus = client.status === 'ativo' ? 'inativo' : 'ativo'; menu.appendChild(statusItem); } if (canDelete) { const deleteItem = actionItem('Excluir cliente', 'bi-trash3', 'js-client-delete text-danger', '#modal-cliente-delete', client); deleteItem.querySelector('button').dataset.clientName = client.name; menu.appendChild(deleteItem); }
            dropdown.appendChild(menu); actionsCell.appendChild(dropdown); row.appendChild(actionsCell); fragment.appendChild(row);
        });
        clientsBody.replaceChildren(fragment);
        window.OSMais?.refreshActionTables?.();
    }
    function currentFilterParams() { const params = new URLSearchParams(new FormData(filterForm)); Array.from(params.keys()).forEach(function (key) { if (!params.get(key)) params.delete(key); }); return params; }
    function syncClientStatusButtons(status) { document.querySelectorAll('.js-client-status-filter').forEach(function (button) { const active = (button.dataset.status || '') === status; button.classList.toggle('active', active); if (active) button.setAttribute('aria-current', 'true'); else button.removeAttribute('aria-current'); }); }
    function showSearchError(message) { const label = searchError?.querySelector('span'); if (label) label.textContent = message; searchError?.classList.remove('d-none'); }
    function clearSearchError() { searchError?.classList.add('d-none'); }
    async function searchClients() {
        if (!filterForm || !clientsTable || !clientsBody) return;
        window.clearTimeout(searchTimer); searchController?.abort(); searchController = new AbortController(); const sequence = ++searchSequence; const params = currentFilterParams();
        clientsTable.setAttribute('aria-busy', 'true'); if (searchFeedback) searchFeedback.textContent = 'Pesquisando clientes…'; clearSearchError();
        try {
            const response = await fetch('actions/clientes-buscar.php?' + params.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: searchController.signal });
            const payload = await response.json().catch(function () { return null; });
            if (!response.ok || !payload?.ok || !Array.isArray(payload.clients)) throw new Error(payload?.error || 'Não foi possível pesquisar os clientes.');
            if (sequence !== searchSequence) return;
            renderClients(payload.clients);
            syncClientStatusButtons(params.get('status') || '');
            if (searchFeedback) searchFeedback.textContent = payload.has_more ? 'Exibindo os primeiros 100 clientes. Refine a pesquisa.' : payload.count + ' cliente(s) encontrado(s).';
            const query = params.toString(); const returnTarget = 'clientes.php' + (query ? '?' + query : ''); window.history.replaceState(null, '', returnTarget); document.querySelectorAll('input[name="return_to"]').forEach(function (field) { field.value = returnTarget; });
        } catch (error) {
            if (error.name !== 'AbortError' && sequence === searchSequence) { showSearchError(error.message || 'Não foi possível pesquisar os clientes.'); if (searchFeedback) searchFeedback.textContent = 'Os resultados anteriores foram mantidos.'; }
        } finally {
            if (sequence === searchSequence) clientsTable.setAttribute('aria-busy', 'false');
        }
    }
    function scheduleSearch() { window.clearTimeout(searchTimer); searchController?.abort(); searchController = null; ++searchSequence; searchTimer = window.setTimeout(searchClients, 300); }
    searchInput?.addEventListener('input', scheduleSearch);
    filterForm?.querySelectorAll('select').forEach(function (select) { select.addEventListener('change', searchClients); });
    filterForm?.addEventListener('submit', function (event) { event.preventDefault(); searchClients(); });
    clearFilters?.addEventListener('click', function (event) { event.preventDefault(); filterForm.querySelectorAll('input[name], select[name]').forEach(function (field) { field.value = ''; }); searchClients(); searchInput?.focus(); });
    searchRetry?.addEventListener('click', searchClients);
    window.addEventListener('popstate', function () { const params = new URL(window.location.href).searchParams; filterForm?.querySelectorAll('[name]').forEach(function (field) { field.value = params.get(field.name) || ''; }); searchClients(); });
    const targets = { create: 'modal-cliente', edit: 'modal-cliente-edit' };
    if (recoveryModal && targets[recoveryModal] && window.bootstrap) { const modal = document.getElementById(targets[recoveryModal]); if (modal) bootstrap.Modal.getOrCreateInstance(modal).show(); }
    if (openImportUpload && window.bootstrap) { const modal = document.getElementById('modal-client-import'); if (modal) bootstrap.Modal.getOrCreateInstance(modal).show(); }
    if (openImportPreview && window.bootstrap) { const modal = document.getElementById('modal-client-import-preview'); if (modal) bootstrap.Modal.getOrCreateInstance(modal).show(); }
});
</script>
