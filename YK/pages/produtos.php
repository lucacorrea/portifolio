<?php

declare(strict_types=1);

use App\Catalog\Entity\Product;

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/produto-action-common.php';

$productService = $application->productManagement();
$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'category' => trim((string) ($_GET['category'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'stock_situation' => trim((string) ($_GET['stock_situation'] ?? '')),
];

$products = $productService->listProducts($filters);
$summary = $productService->productSummary();
$allProducts = $productService->listProducts();
$categories = array_values(array_unique(array_filter(array_map(
    static fn(Product $product): string => (string) $product->category(),
    $allProducts
))));
sort($categories);

$canCreate = $authorization->can('produto.criar');
$canEdit = $authorization->can('produto.editar');
$canDelete = $authorization->can('produto.excluir');
$canCost = $authorization->can('produto.visualizar_preco_custo');
$canSale = $authorization->can('produto.visualizar_preco_venda');
$canProfit = $canCost && $canSale && $authorization->can('financeiro.visualizar_lucro');
$recovery = product_consume_form_recovery();

function product_recovery_data(?array $recovery, string $modal): array
{
    return $recovery !== null
        && ($recovery['modal'] ?? '') === $modal
        && isset($recovery['data'])
        && is_array($recovery['data'])
        ? $recovery['data']
        : [];
}

function product_recovery_error(?array $recovery, string $modal): ?string
{
    return $recovery !== null
        && ($recovery['modal'] ?? '') === $modal
        && isset($recovery['error'])
        && is_string($recovery['error'])
        ? $recovery['error']
        : null;
}

function product_value(array $data, string $key, string $default = ''): string
{
    $value = $data[$key] ?? $default;

    return is_scalar($value) ? (string) $value : $default;
}

function product_date(string $value): string
{
    try {
        return (new DateTimeImmutable($value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return '-';
    }
}

function product_status_label(string $status): string
{
    return $status === 'ativo' ? 'Ativo' : 'Inativo';
}

function product_stock_label(string $situation): string
{
    return match ($situation) {
        'sem_estoque' => 'Sem estoque',
        'estoque_baixo' => 'Estoque baixo',
        default => 'Em estoque',
    };
}

function product_stock_class(string $situation): string
{
    return match ($situation) {
        'sem_estoque' => 'red',
        'estoque_baixo' => 'amber',
        default => 'green',
    };
}

function product_decimal(string $value, int $scale = 3): string
{
    return number_format((float) $value, $scale, ',', '.');
}

function product_percent(?string $value): string
{
    return $value === null ? 'Não disponível' : number_format((float) $value, 2, ',', '.') . '%';
}

$createData = product_recovery_data($recovery, 'create');
$createError = product_recovery_error($recovery, 'create');
$editData = product_recovery_data($recovery, 'edit');
$editError = product_recovery_error($recovery, 'edit');
?>

<div class="page-body products-page">

<?php
metric_grid([
    ['Total de produtos', (string) ($summary['total'] ?? 0), 'bi-box-seam', '#2563EB', 'cadastrados'],
    ['Produtos ativos', (string) ($summary['active'] ?? 0), 'bi-check-circle', '#16A34A', 'comercializáveis'],
    ['Estoque baixo', (string) ($summary['low_stock'] ?? 0), 'bi-exclamation-triangle', '#D97706', 'atenção'],
    ['Sem estoque', (string) ($summary['out_of_stock'] ?? 0), 'bi-x-octagon', '#DC2626', 'reposição'],
]);
?>

<form class="filter-bar" method="get" action="produtos.php" data-live-filter="products" data-live-regions="metrics results">
    <div class="search-wrap">
        <i class="bi bi-search"></i>
        <input class="search-input" type="search" name="search" value="<?= h($filters['search']) ?>" placeholder="Buscar código, nome, fabricante ou código de barras" maxlength="150">
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

    <select class="filter-select" name="stock_situation" aria-label="Filtrar por situação do estoque">
        <option value="">Todas as situações</option>
        <option value="em_estoque" <?= $filters['stock_situation'] === 'em_estoque' ? 'selected' : '' ?>>Em estoque</option>
        <option value="estoque_baixo" <?= $filters['stock_situation'] === 'estoque_baixo' ? 'selected' : '' ?>>Estoque baixo</option>
        <option value="sem_estoque" <?= $filters['stock_situation'] === 'sem_estoque' ? 'selected' : '' ?>>Sem estoque</option>
    </select>

    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="produtos.php" data-live-filter-clear><i class="bi bi-x-lg"></i> Limpar filtros</a>
</form>

<section class="panel" data-live-region="results">
    <div class="panel-header">
        <div class="panel-title"><i class="bi bi-box-seam"></i>Produtos cadastrados</div>
        <?php if ($canCreate): ?>
            <button class="btn-new-os" type="button" data-bs-toggle="modal" data-bs-target="#modal-produto"><i class="bi bi-box-seam"></i><span>Novo produto</span></button>
        <?php endif; ?>
    </div>

    <?php if ($products === []): ?>
        <?php empty_state('Nenhum produto encontrado', 'Cadastre o primeiro produto ou ajuste os filtros.'); ?>
    <?php else: ?>
        <div class="table-panel-wrap">
            <table class="os-table products-table">
                <thead>
                    <tr>
                        <th>Código</th><th>Produto</th><th>NCM</th><th>Categoria</th><th>Fabricante</th><th>Unidade</th><th>Estoque</th><th>Estoque mínimo</th><th>Situação</th>
                        <?php if ($canCost): ?><th>Preço de custo</th><?php endif; ?>
                        <?php if ($canSale): ?><th>Preço de venda</th><?php endif; ?>
                        <?php if ($canProfit): ?><th>Lucro unit.</th><th>Margem</th><th>Lucro estoque</th><?php endif; ?>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php $situation = $product->stockSituation(); ?>
                        <tr>
                            <td><strong><?= h($product->displayCode()) ?></strong></td>
                            <td><?= h($product->name()) ?></td>
                            <td><?= h($product->ncm() ?? '-') ?></td>
                            <td><?= h($product->category() ?? '-') ?></td>
                            <td><?= h($product->manufacturer() ?? '-') ?></td>
                            <td><?= h($product->unit()) ?></td>
                            <td><?= h(product_decimal($product->stock())) ?></td>
                            <td><?= h(product_decimal($product->minimumStock())) ?></td>
                            <td><span class="badge-soft badge-<?= h(product_stock_class($situation)) ?>"><?= h(product_stock_label($situation)) ?></span></td>
                            <?php if ($canCost): ?><td><?= h(money($product->costPrice())) ?></td><?php endif; ?>
                            <?php if ($canSale): ?><td><?= h(money($product->salePrice())) ?></td><?php endif; ?>
                            <?php if ($canProfit): ?><td><?= h(money($product->unitProfit())) ?></td><td><?= h(product_percent($product->costMarginPercent())) ?></td><td><?= h(money($product->potentialStockProfit())) ?></td><?php endif; ?>
                            <td class="table-actions-cell">
                                <div class="dropdown table-action-dropdown">
                                    <button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do produto <?= h($product->name()) ?>"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button class="dropdown-item js-product-view" type="button" data-bs-toggle="modal" data-bs-target="#modal-produto-view"
                                            data-product-id="<?= h((string) $product->id()) ?>"
                                            data-product-code="<?= h($product->displayCode()) ?>"
                                            data-product-name="<?= h($product->name()) ?>"
                                            data-product-description="<?= h($product->description() ?? '') ?>"
                                            data-product-category="<?= h($product->category() ?? '') ?>"
                                            data-product-manufacturer="<?= h($product->manufacturer() ?? '') ?>"
                                            data-product-unit="<?= h($product->unit()) ?>"
                                            data-product-ncm="<?= h($product->ncm() ?? '') ?>"
                                            data-product-barcode="<?= h($product->barcode() ?? '') ?>"
                                            <?php if ($canCost): ?>data-product-cost-price="<?= h($product->costPrice()) ?>"<?php endif; ?>
                                            <?php if ($canSale): ?>data-product-sale-price="<?= h($product->salePrice()) ?>"<?php endif; ?>
                                            <?php if ($canProfit): ?>data-product-unit-profit="<?= h($product->unitProfit()) ?>" data-product-margin="<?= h($product->costMarginPercent() ?? '') ?>" data-product-potential-profit="<?= h($product->potentialStockProfit()) ?>"<?php endif; ?>
                                            data-product-stock="<?= h($product->stock()) ?>"
                                            data-product-minimum-stock="<?= h($product->minimumStock()) ?>"
                                            data-product-location="<?= h($product->location() ?? '') ?>"
                                            data-product-status="<?= h($product->status()) ?>"
                                            data-product-created-at="<?= h(product_date($product->createdAt())) ?>"
                                            data-product-updated-at="<?= h(product_date($product->updatedAt())) ?>"
                                        ><i class="bi bi-eye"></i> Visualizar</button></li>
                                        <?php if ($canEdit): ?>
                                            <li><button class="dropdown-item js-product-edit" type="button" data-bs-toggle="modal" data-bs-target="#modal-produto-edit"
                                                data-product-id="<?= h((string) $product->id()) ?>"
                                                data-product-code="<?= h($product->displayCode()) ?>"
                                                data-product-name="<?= h($product->name()) ?>"
                                                data-product-description="<?= h($product->description() ?? '') ?>"
                                                data-product-category="<?= h($product->category() ?? '') ?>"
                                                data-product-manufacturer="<?= h($product->manufacturer() ?? '') ?>"
                                                data-product-unit="<?= h($product->unit()) ?>"
                                                data-product-ncm="<?= h($product->ncm() ?? '') ?>"
                                                data-product-barcode="<?= h($product->barcode() ?? '') ?>"
                                                <?php if ($canCost): ?>data-product-cost-price="<?= h($product->costPrice()) ?>"<?php endif; ?>
                                                <?php if ($canSale): ?>data-product-sale-price="<?= h($product->salePrice()) ?>"<?php endif; ?>
                                                data-product-stock="<?= h($product->stock()) ?>"
                                                data-product-minimum-stock="<?= h($product->minimumStock()) ?>"
                                                data-product-location="<?= h($product->location() ?? '') ?>"
                                                data-product-status="<?= h($product->status()) ?>"
                                            ><i class="bi bi-pencil"></i> Editar</button></li>
                                        <?php endif; ?>
                                        <?php if ($canDelete): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item text-danger js-product-delete" type="button" data-bs-toggle="modal" data-bs-target="#modal-produto-delete"
                                                data-product-id="<?= h((string) $product->id()) ?>"
                                                data-product-code="<?= h($product->displayCode()) ?>"
                                                data-product-name="<?= h($product->name()) ?>"
                                            ><i class="bi bi-trash3"></i> Excluir produto</button></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
</div>

<?php
function product_form_fields(array $data, bool $canCost, bool $canSale, string $prefix, bool $editing = false): void {
?>
    <section class="form-section">
        <h3 class="form-section-title">Dados do produto</h3>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Nome</label><input class="form-control-os" id="<?= h($prefix) ?>-name" type="text" name="name" value="<?= h(product_value($data, 'name')) ?>" maxlength="150" required></div>
            <div class="form-group"><label class="form-label">Categoria</label><input class="form-control-os" id="<?= h($prefix) ?>-category" type="text" name="category" value="<?= h(product_value($data, 'category')) ?>" maxlength="100"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Fabricante</label><input class="form-control-os" id="<?= h($prefix) ?>-manufacturer" type="text" name="manufacturer" value="<?= h(product_value($data, 'manufacturer')) ?>" maxlength="100"></div>
            <div class="form-group"><label class="form-label">Unidade</label><input class="form-control-os" id="<?= h($prefix) ?>-unit" type="text" name="unit" value="<?= h(product_value($data, 'unit', 'un')) ?>" maxlength="20" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">NCM</label><input class="form-control-os" id="<?= h($prefix) ?>-ncm" type="text" name="ncm" value="<?= h(product_value($data, 'ncm')) ?>" maxlength="8" inputmode="numeric" pattern="\d{0,8}"></div>
            <div class="form-group"><label class="form-label">Código de barras</label><input class="form-control-os" id="<?= h($prefix) ?>-barcode" type="text" name="barcode" value="<?= h(product_value($data, 'barcode')) ?>" maxlength="100"></div>
        </div>
        <div class="form-group"><label class="form-label">Descrição</label><textarea class="form-control-os" id="<?= h($prefix) ?>-description" name="description" rows="3"><?= h(product_value($data, 'description')) ?></textarea></div>
    </section>
    <section class="form-section">
        <h3 class="form-section-title">Estoque e valores</h3>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Localização</label><input class="form-control-os" id="<?= h($prefix) ?>-location" type="text" name="location" value="<?= h(product_value($data, 'location')) ?>" maxlength="100"></div>
            <?php if ($canCost): ?><div class="form-group"><label class="form-label">Preço de custo</label><input class="form-control-os" id="<?= h($prefix) ?>-cost-price" type="text" name="cost_price" value="<?= h(product_value($data, 'cost_price', '0,00')) ?>"></div><?php endif; ?>
            <?php if ($canSale): ?><div class="form-group"><label class="form-label">Preço de venda</label><input class="form-control-os" id="<?= h($prefix) ?>-sale-price" type="text" name="sale_price" value="<?= h(product_value($data, 'sale_price', '0,00')) ?>"></div><?php endif; ?>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Estoque<?= $editing ? '' : ' inicial' ?></label><input class="form-control-os" id="<?= h($prefix) ?>-stock" type="text" name="stock" value="<?= h(product_value($data, 'stock', '0')) ?>"></div>
            <div class="form-group"><label class="form-label">Estoque mínimo</label><input class="form-control-os" id="<?= h($prefix) ?>-minimum-stock" type="text" name="minimum_stock" value="<?= h(product_value($data, 'minimum_stock', '0')) ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Status</label><select class="form-control-os" id="<?= h($prefix) ?>-status" name="status"><option value="ativo" <?= product_value($data, 'status', 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option><option value="inativo" <?= product_value($data, 'status') === 'inativo' ? 'selected' : '' ?>>Inativo</option></select></div>
    </section>
<?php } ?>

<?php if ($canCreate): ?>
<div class="modal fade" id="modal-produto" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/produto-salvar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Novo produto</h2><p class="text-muted small mb-0">O código será gerado automaticamente.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><div class="alert alert-danger <?= $createError === null ? 'd-none' : '' ?>" id="create-product-form-error" role="alert"><?= h($createError ?? '') ?></div><?php product_form_fields($createData, $canCost, $canSale, 'create-product'); ?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button></div></form></div></div>
<?php endif; ?>

<div class="modal fade" id="modal-produto-view" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content visual-modal"><div class="modal-header"><div><h2 class="modal-title fs-5">Dados do produto</h2><p class="text-muted small mb-0" id="view-product-subtitle"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><section class="form-section"><h3 class="form-section-title">Identificação</h3><div class="form-row"><div class="form-group"><label class="form-label">Código</label><div class="form-control-os" id="view-product-code"></div></div><div class="form-group"><label class="form-label">Nome</label><div class="form-control-os" id="view-product-name"></div></div></div><div class="form-group"><label class="form-label">Descrição</label><div class="form-control-os" id="view-product-description"></div></div></section><section class="form-section"><h3 class="form-section-title">Dados comerciais</h3><div class="form-row"><div class="form-group"><label class="form-label">Categoria</label><div class="form-control-os" id="view-product-category"></div></div><div class="form-group"><label class="form-label">Fabricante</label><div class="form-control-os" id="view-product-manufacturer"></div></div></div><div class="form-row"><div class="form-group"><label class="form-label">Unidade</label><div class="form-control-os" id="view-product-unit"></div></div><div class="form-group"><label class="form-label">NCM</label><div class="form-control-os" id="view-product-ncm"></div></div></div><div class="form-row"><div class="form-group"><label class="form-label">Código de barras</label><div class="form-control-os" id="view-product-barcode"></div></div><div class="form-group"><label class="form-label">Status</label><div class="form-control-os" id="view-product-status"></div></div></div><?php if ($canCost || $canSale): ?><div class="form-row"><?php if ($canCost): ?><div class="form-group"><label class="form-label">Preço de custo</label><div class="form-control-os" id="view-product-cost-price"></div></div><?php endif; ?><?php if ($canSale): ?><div class="form-group"><label class="form-label">Preço de venda</label><div class="form-control-os" id="view-product-sale-price"></div></div><?php endif; ?></div><?php endif; ?><?php if ($canProfit): ?><div class="summary-box compact"><div><span>Lucro unitário</span><strong id="view-product-unit-profit"></strong></div><div><span>Margem sobre custo</span><strong id="view-product-margin"></strong></div><div><span>Lucro potencial do estoque</span><strong id="view-product-potential-profit"></strong></div></div><?php endif; ?><div class="form-row"><div class="form-group"><label class="form-label">Estoque</label><div class="form-control-os" id="view-product-stock"></div></div><div class="form-group"><label class="form-label">Estoque mínimo</label><div class="form-control-os" id="view-product-minimum-stock"></div></div></div><div class="form-row"><div class="form-group"><label class="form-label">Localização</label><div class="form-control-os" id="view-product-location"></div></div></div></section></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Fechar</button></div></div></div></div>

<?php if ($canEdit): ?>
<div class="modal fade" id="modal-produto-edit" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/produto-salvar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Editar produto</h2><p class="text-muted small mb-0" id="edit-product-subtitle"><?= h(product_value($editData, 'code')) ?></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><div class="alert alert-danger <?= $editError === null ? 'd-none' : '' ?>" id="edit-product-form-error" role="alert"><?= h($editError ?? '') ?></div><input type="hidden" name="id" id="edit-product-id" value="<?= h(product_value($editData, 'id')) ?>"><section class="form-section"><h3 class="form-section-title">Código</h3><input class="form-control-os" id="edit-product-code" type="text" value="<?= h(product_value($editData, 'code')) ?>" readonly></section><?php product_form_fields($editData, $canCost, $canSale, 'edit-product', true); ?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button></div></form></div></div>
<?php endif; ?>

<?php if ($canDelete): ?>
<div class="modal fade" id="modal-produto-delete" tabindex="-1" aria-labelledby="delete-product-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/produto-excluir.php"><div class="modal-header"><div><h2 class="modal-title fs-5" id="delete-product-title">Excluir produto</h2><p class="text-muted small mb-0" id="delete-product-subtitle"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="delete-product-id"><p>O produto será removido das telas por exclusão lógica.</p><div class="alert alert-warning">Somente produtos com saldo zero e nunca utilizados podem ser excluídos. Para produtos com histórico, altere o status para Inativo.</div><div class="form-group"><label class="form-label" for="delete-product-reason">Motivo da exclusão</label><textarea class="form-control-os" id="delete-product-reason" name="motivo" maxlength="255" rows="3" required></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-trash3"></i> Excluir produto</button></div></form></div></div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const recoveryModal = <?= json_encode($recovery['modal'] ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const canCost = <?= $canCost ? 'true' : 'false' ?>;
    const canSale = <?= $canSale ? 'true' : 'false' ?>;
    const canProfit = <?= $canProfit ? 'true' : 'false' ?>;
    function text(id, value) { const element = document.getElementById(id); if (element) { element.textContent = value || '-'; } }
    function val(id, value) { const element = document.getElementById(id); if (element) { element.value = value || ''; } }
    function moneyValue(value) { const number = Number.parseFloat(value || '0'); return number.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }
    function percentValue(value) { const number = Number.parseFloat(value || ''); return Number.isFinite(number) ? number.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%' : 'Não disponível'; }
    function fillProduct(prefix, data) {
        val(prefix + '-id', data.productId); val(prefix + '-code', data.productCode); val(prefix + '-name', data.productName); val(prefix + '-description', data.productDescription); val(prefix + '-category', data.productCategory); val(prefix + '-manufacturer', data.productManufacturer); val(prefix + '-unit', data.productUnit); val(prefix + '-ncm', data.productNcm); val(prefix + '-barcode', data.productBarcode); val(prefix + '-stock', data.productStock); val(prefix + '-minimum-stock', data.productMinimumStock); val(prefix + '-location', data.productLocation); val(prefix + '-status', data.productStatus || 'ativo'); if (canCost) { val(prefix + '-cost-price', data.productCostPrice); } if (canSale) { val(prefix + '-sale-price', data.productSalePrice); }
    }
    document.addEventListener('click', function (event) {
        const button = event.target.closest('.js-product-view, .js-product-edit, .js-product-delete');
        if (!button) return;
        if (button.classList.contains('js-product-view')) {
            text('view-product-subtitle', button.dataset.productCode); text('view-product-code', button.dataset.productCode); text('view-product-name', button.dataset.productName); text('view-product-description', button.dataset.productDescription); text('view-product-category', button.dataset.productCategory); text('view-product-manufacturer', button.dataset.productManufacturer); text('view-product-unit', button.dataset.productUnit); text('view-product-ncm', button.dataset.productNcm); text('view-product-barcode', button.dataset.productBarcode); if (canCost) { text('view-product-cost-price', moneyValue(button.dataset.productCostPrice)); } if (canSale) { text('view-product-sale-price', moneyValue(button.dataset.productSalePrice)); } if (canProfit) { text('view-product-unit-profit', moneyValue(button.dataset.productUnitProfit)); text('view-product-margin', percentValue(button.dataset.productMargin)); text('view-product-potential-profit', moneyValue(button.dataset.productPotentialProfit)); } text('view-product-stock', button.dataset.productStock); text('view-product-minimum-stock', button.dataset.productMinimumStock); text('view-product-location', button.dataset.productLocation); text('view-product-status', button.dataset.productStatus === 'ativo' ? 'Ativo' : 'Inativo');
        }
        if (button.classList.contains('js-product-edit')) { text('edit-product-subtitle', button.dataset.productCode); fillProduct('edit-product', button.dataset); }
        if (button.classList.contains('js-product-delete')) { val('delete-product-id', button.dataset.productId); text('delete-product-subtitle', [button.dataset.productCode, button.dataset.productName].filter(Boolean).join(' — ')); val('delete-product-reason', ''); }
    });
    const createModal = document.getElementById('modal-produto');
    if (createModal) { createModal.addEventListener('show.bs.modal', function (event) { if (event.relatedTarget) { const form = createModal.querySelector('form'); if (form) { form.reset(); } text('create-product-form-error', ''); document.getElementById('create-product-form-error')?.classList.add('d-none'); } }); }
    const targets = { create: 'modal-produto', edit: 'modal-produto-edit' };
    if (recoveryModal && targets[recoveryModal] && window.bootstrap) { const modal = document.getElementById(targets[recoveryModal]); if (modal) { bootstrap.Modal.getOrCreateInstance(modal).show(); } }
});
</script>
