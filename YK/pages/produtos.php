<?php

declare(strict_types=1);

use App\Catalog\Entity\Product;

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/produto-action-common.php';

/*
|--------------------------------------------------------------------------
| Dados
|--------------------------------------------------------------------------
*/

$productService = $application->productManagement();

$filters = [
    'search' => trim(
        (string) ($_GET['search'] ?? '')
    ),

    'category' => trim(
        (string) ($_GET['category'] ?? '')
    ),

    'status' => trim(
        (string) ($_GET['status'] ?? '')
    ),

    'stock_situation' => trim(
        (string) ($_GET['stock_situation'] ?? '')
    ),
];

$products = $productService->listProducts(
    $filters
);

$summary = $productService->productSummary();

$allProducts =
    $productService->listProducts();

$categories = array_values(
    array_unique(
        array_filter(
            array_map(
                static fn (
                    Product $product
                ): string =>
                    (string) $product->category(),

                $allProducts
            )
        )
    )
);

sort($categories);

/*
|--------------------------------------------------------------------------
| Permissões
|--------------------------------------------------------------------------
*/

$canCreate =
    $authorization->can(
        'produto.criar'
    );

$canEdit =
    $authorization->can(
        'produto.editar'
    );

$canDelete =
    $authorization->can(
        'produto.excluir'
    );

$canCost =
    $authorization->can(
        'produto.visualizar_preco_custo'
    );

$canSale =
    $authorization->can(
        'produto.visualizar_preco_venda'
    );

$canProfit =
    $canCost
    && $canSale
    && $authorization->can(
        'financeiro.visualizar_lucro'
    );

$recovery =
    product_consume_form_recovery();

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function product_recovery_data(
    ?array $recovery,
    string $modal
): array {
    return (
        $recovery !== null
        && ($recovery['modal'] ?? '') === $modal
        && isset($recovery['data'])
        && is_array($recovery['data'])
    )
        ? $recovery['data']
        : [];
}

function product_recovery_error(
    ?array $recovery,
    string $modal
): ?string {
    return (
        $recovery !== null
        && ($recovery['modal'] ?? '') === $modal
        && isset($recovery['error'])
        && is_string($recovery['error'])
    )
        ? $recovery['error']
        : null;
}

function product_value(
    array $data,
    string $key,
    string $default = ''
): string {
    $value =
        $data[$key]
        ?? $default;

    return is_scalar($value)
        ? (string) $value
        : $default;
}

function product_date(
    string $value
): string {
    try {
        return (
            new DateTimeImmutable($value)
        )->format(
            'd/m/Y H:i'
        );
    } catch (Throwable) {
        return '-';
    }
}

function product_decimal(
    string $value,
    int $scale = 3
): string {
    return number_format(
        (float) $value,
        $scale,
        ',',
        '.'
    );
}

function product_percent(
    ?string $value
): string {
    if (
        $value === null
        || trim($value) === ''
    ) {
        return 'Não disponível';
    }

    return number_format(
        (float) $value,
        2,
        ',',
        '.'
    ) . '%';
}

function product_stock_label(
    string $situation
): string {
    return match ($situation) {
        'sem_estoque' =>
            'Sem estoque',

        'estoque_baixo' =>
            'Estoque baixo',

        default =>
            'Em estoque',
    };
}

function product_stock_class(
    string $situation
): string {
    return match ($situation) {
        'sem_estoque' =>
            'red',

        'estoque_baixo' =>
            'amber',

        default =>
            'green',
    };
}

/*
|--------------------------------------------------------------------------
| Validação visual de prontidão fiscal
|--------------------------------------------------------------------------
|
| Essa função NÃO define tributação.
| Apenas verifica se os campos mínimos foram preenchidos.
|
*/

function product_fiscal_ready(
    Product $product
): bool {
    $ncm =
        $product->ncm();

    $cfop =
        $product->defaultCfop();

    $pis =
        $product->pisCst();

    $cofins =
        $product->cofinsCst();

    $taxUnit =
        $product->taxUnit();

    $icms =
        $product->icmsCst();

    $csosn =
        $product->csosn();

    return (
        $ncm !== null
        && preg_match(
            '/^\d{8}$/',
            $ncm
        ) === 1

        && $product->origin() !== null

        && $cfop !== null
        && preg_match(
            '/^\d{4}$/',
            $cfop
        ) === 1

        && $pis !== null
        && preg_match(
            '/^\d{2}$/',
            $pis
        ) === 1

        && $cofins !== null
        && preg_match(
            '/^\d{2}$/',
            $cofins
        ) === 1

        && $taxUnit !== null
        && trim($taxUnit) !== ''

        && (
            (
                $icms !== null
                && trim($icms) !== ''
            )
            ||
            (
                $csosn !== null
                && trim($csosn) !== ''
            )
        )
    );
}

function product_fiscal_label(
    Product $product
): string {
    return product_fiscal_ready(
        $product
    )
        ? 'Fiscal completo'
        : 'Fiscal pendente';
}

function product_fiscal_class(
    Product $product
): string {
    return product_fiscal_ready(
        $product
    )
        ? 'green'
        : 'red';
}

/*
|--------------------------------------------------------------------------
| Origem da mercadoria
|--------------------------------------------------------------------------
*/

function product_origin_options(
    string $selected
): void {
    $options = [
        '0' =>
            '0 - Nacional',

        '1' =>
            '1 - Estrangeira - importação direta',

        '2' =>
            '2 - Estrangeira - adquirida no mercado interno',

        '3' =>
            '3 - Nacional - conteúdo importado > 40% e ≤ 70%',

        '4' =>
            '4 - Nacional - processo produtivo básico',

        '5' =>
            '5 - Nacional - conteúdo importado ≤ 40%',

        '6' =>
            '6 - Estrangeira - importação direta sem similar nacional',

        '7' =>
            '7 - Estrangeira - mercado interno sem similar nacional',

        '8' =>
            '8 - Nacional - conteúdo importado > 70%',
    ];

    ?>
    <option value="">
        Selecione
    </option>

    <?php foreach ($options as $value => $label): ?>
        <option
            value="<?= h($value) ?>"
            <?= $selected === $value ? 'selected' : '' ?>
        >
            <?= h($label) ?>
        </option>
    <?php endforeach; ?>
    <?php
}

/*
|--------------------------------------------------------------------------
| Campos do formulário
|--------------------------------------------------------------------------
*/

function product_form_fields(
    array $data,
    bool $canCost,
    bool $canSale,
    string $prefix,
    bool $editing = false
): void {
    ?>

    <!-- ================================================================
         DADOS DO PRODUTO
         ================================================================ -->

    <section class="form-section">

        <h3 class="form-section-title">
            Dados do produto
        </h3>

        <div class="form-row">

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-name"
                >
                    Nome
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-name"
                    type="text"
                    name="name"
                    value="<?= h(
                        product_value(
                            $data,
                            'name'
                        )
                    ) ?>"
                    maxlength="150"
                    required
                >

            </div>

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-category"
                >
                    Categoria
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-category"
                    type="text"
                    name="category"
                    value="<?= h(
                        product_value(
                            $data,
                            'category'
                        )
                    ) ?>"
                    maxlength="100"
                >

            </div>

        </div>

        <div class="form-row">

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-manufacturer"
                >
                    Fabricante
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-manufacturer"
                    type="text"
                    name="manufacturer"
                    value="<?= h(
                        product_value(
                            $data,
                            'manufacturer'
                        )
                    ) ?>"
                    maxlength="100"
                >

            </div>

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-unit"
                >
                    Unidade comercial
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-unit"
                    type="text"
                    name="unit"
                    value="<?= h(
                        product_value(
                            $data,
                            'unit',
                            'UN'
                        )
                    ) ?>"
                    maxlength="20"
                    placeholder="UN"
                    required
                >

            </div>

        </div>

        <div class="form-row">

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-barcode"
                >
                    Código de barras
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-barcode"
                    type="text"
                    name="barcode"
                    value="<?= h(
                        product_value(
                            $data,
                            'barcode'
                        )
                    ) ?>"
                    maxlength="100"
                >

            </div>

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-status"
                >
                    Status
                </label>

                <select
                    class="form-control-os"
                    id="<?= h($prefix) ?>-status"
                    name="status"
                >

                    <option
                        value="ativo"
                        <?= product_value(
                            $data,
                            'status',
                            'ativo'
                        ) === 'ativo'
                            ? 'selected'
                            : '' ?>
                    >
                        Ativo
                    </option>

                    <option
                        value="inativo"
                        <?= product_value(
                            $data,
                            'status'
                        ) === 'inativo'
                            ? 'selected'
                            : '' ?>
                    >
                        Inativo
                    </option>

                </select>

            </div>

        </div>

        <div class="form-group">

            <label
                class="form-label"
                for="<?= h($prefix) ?>-description"
            >
                Descrição
            </label>

            <textarea
                class="form-control-os"
                id="<?= h($prefix) ?>-description"
                name="description"
                rows="3"
                maxlength="5000"
            ><?= h(
                product_value(
                    $data,
                    'description'
                )
            ) ?></textarea>

        </div>

    </section>

    <!-- ================================================================
         DADOS FISCAIS
         ================================================================ -->

    <section class="form-section fiscal-product-section">

        <div class="fiscal-section-heading">

            <div>

                <h3 class="form-section-title mb-1">
                    Dados fiscais
                </h3>

                <p class="text-muted small mb-0">
                    Informações utilizadas na emissão de NF-e e NFC-e.
                </p>

            </div>

            <span class="badge-soft badge-amber">
                NF-e / NFC-e
            </span>

        </div>

        <div class="alert alert-info">

            <i class="bi bi-info-circle"></i>

            Não preencha valores tributários por tentativa.
            Utilize os dados definidos pela contabilidade da empresa.

        </div>

        <!-- NCM / CEST -->

        <div class="form-row">

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-ncm"
                >
                    NCM
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-ncm"
                    type="text"
                    name="ncm"
                    value="<?= h(
                        product_value(
                            $data,
                            'ncm'
                        )
                    ) ?>"
                    maxlength="8"
                    inputmode="numeric"
                    pattern="\d{0,8}"
                    placeholder="8 dígitos"
                >

                <small class="text-muted">
                    Para emissão fiscal deve possuir exatamente 8 dígitos.
                </small>

            </div>

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-cest"
                >
                    CEST
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-cest"
                    type="text"
                    name="cest"
                    value="<?= h(
                        product_value(
                            $data,
                            'cest'
                        )
                    ) ?>"
                    maxlength="7"
                    inputmode="numeric"
                    pattern="\d{0,7}"
                    placeholder="Quando aplicável"
                >

            </div>

        </div>

        <!-- ORIGEM / CFOP -->

        <div class="form-row">

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-origin"
                >
                    Origem da mercadoria
                </label>

                <select
                    class="form-control-os"
                    id="<?= h($prefix) ?>-origin"
                    name="origin"
                >
                    <?php
                    product_origin_options(
                        product_value(
                            $data,
                            'origin'
                        )
                    );
                    ?>
                </select>

            </div>

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-default-cfop"
                >
                    CFOP padrão
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-default-cfop"
                    type="text"
                    name="default_cfop"
                    value="<?= h(
                        product_value(
                            $data,
                            'default_cfop'
                        )
                    ) ?>"
                    maxlength="4"
                    inputmode="numeric"
                    pattern="\d{0,4}"
                    placeholder="4 dígitos"
                >

            </div>

        </div>

        <!-- ICMS -->

        <div class="form-row">

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-icms-cst"
                >
                    CST ICMS
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-icms-cst"
                    type="text"
                    name="icms_cst"
                    value="<?= h(
                        product_value(
                            $data,
                            'icms_cst'
                        )
                    ) ?>"
                    maxlength="3"
                    inputmode="numeric"
                    pattern="\d{0,3}"
                    placeholder="Regime normal"
                >

                <small class="text-muted">
                    Usado quando aplicável ao regime tributário.
                </small>

            </div>

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-csosn"
                >
                    CSOSN
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-csosn"
                    type="text"
                    name="csosn"
                    value="<?= h(
                        product_value(
                            $data,
                            'csosn'
                        )
                    ) ?>"
                    maxlength="3"
                    inputmode="numeric"
                    pattern="\d{0,3}"
                    placeholder="Simples Nacional"
                >

            </div>

        </div>

        <!-- PIS / COFINS -->

        <div class="form-row">

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-pis-cst"
                >
                    CST PIS
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-pis-cst"
                    type="text"
                    name="pis_cst"
                    value="<?= h(
                        product_value(
                            $data,
                            'pis_cst'
                        )
                    ) ?>"
                    maxlength="2"
                    inputmode="numeric"
                    pattern="\d{0,2}"
                    placeholder="2 dígitos"
                >

            </div>

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-cofins-cst"
                >
                    CST COFINS
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-cofins-cst"
                    type="text"
                    name="cofins_cst"
                    value="<?= h(
                        product_value(
                            $data,
                            'cofins_cst'
                        )
                    ) ?>"
                    maxlength="2"
                    inputmode="numeric"
                    pattern="\d{0,2}"
                    placeholder="2 dígitos"
                >

            </div>

        </div>

        <!-- UNIDADE TRIBUTÁVEL / GTIN -->

        <div class="form-row">

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-tax-unit"
                >
                    Unidade tributável
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-tax-unit"
                    type="text"
                    name="tax_unit"
                    value="<?= h(
                        product_value(
                            $data,
                            'tax_unit'
                        )
                    ) ?>"
                    maxlength="20"
                    placeholder="UN, PC, MT, KG..."
                >

            </div>

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-tax-gtin"
                >
                    GTIN tributável
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-tax-gtin"
                    type="text"
                    name="tax_gtin"
                    value="<?= h(
                        product_value(
                            $data,
                            'tax_gtin'
                        )
                    ) ?>"
                    maxlength="14"
                    inputmode="numeric"
                    pattern="\d{0,14}"
                >

            </div>

        </div>

        <!-- AVANÇADO -->

        <details class="fiscal-advanced">

            <summary>
                <i class="bi bi-sliders"></i>
                Alíquotas e campos fiscais avançados
            </summary>

            <div class="fiscal-advanced-body">

                <div class="form-row">

                    <div class="form-group">

                        <label
                            class="form-label"
                            for="<?= h($prefix) ?>-icms-rate"
                        >
                            Alíquota ICMS (%)
                        </label>

                        <input
                            class="form-control-os"
                            id="<?= h($prefix) ?>-icms-rate"
                            type="text"
                            name="icms_rate"
                            value="<?= h(
                                product_value(
                                    $data,
                                    'icms_rate'
                                )
                            ) ?>"
                            inputmode="decimal"
                        >

                    </div>

                    <div class="form-group">

                        <label
                            class="form-label"
                            for="<?= h($prefix) ?>-pis-rate"
                        >
                            Alíquota PIS (%)
                        </label>

                        <input
                            class="form-control-os"
                            id="<?= h($prefix) ?>-pis-rate"
                            type="text"
                            name="pis_rate"
                            value="<?= h(
                                product_value(
                                    $data,
                                    'pis_rate'
                                )
                            ) ?>"
                            inputmode="decimal"
                        >

                    </div>

                    <div class="form-group">

                        <label
                            class="form-label"
                            for="<?= h($prefix) ?>-cofins-rate"
                        >
                            Alíquota COFINS (%)
                        </label>

                        <input
                            class="form-control-os"
                            id="<?= h($prefix) ?>-cofins-rate"
                            type="text"
                            name="cofins_rate"
                            value="<?= h(
                                product_value(
                                    $data,
                                    'cofins_rate'
                                )
                            ) ?>"
                            inputmode="decimal"
                        >

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label
                            class="form-label"
                            for="<?= h($prefix) ?>-ibs-cbs-cst"
                        >
                            CST IBS/CBS
                        </label>

                        <input
                            class="form-control-os"
                            id="<?= h($prefix) ?>-ibs-cbs-cst"
                            type="text"
                            name="ibs_cbs_cst"
                            value="<?= h(
                                product_value(
                                    $data,
                                    'ibs_cbs_cst'
                                )
                            ) ?>"
                            maxlength="3"
                            inputmode="numeric"
                            pattern="\d{0,3}"
                        >

                    </div>

                    <div class="form-group">

                        <label
                            class="form-label"
                            for="<?= h($prefix) ?>-ibs-cbs-classification"
                        >
                            Classificação tributária IBS/CBS
                        </label>

                        <input
                            class="form-control-os"
                            id="<?= h($prefix) ?>-ibs-cbs-classification"
                            type="text"
                            name="ibs_cbs_classification"
                            value="<?= h(
                                product_value(
                                    $data,
                                    'ibs_cbs_classification'
                                )
                            ) ?>"
                            maxlength="6"
                            inputmode="numeric"
                            pattern="\d{0,6}"
                        >

                    </div>

                </div>

            </div>

        </details>

    </section>

    <!-- ================================================================
         ESTOQUE / VALORES
         ================================================================ -->

    <section class="form-section">

        <h3 class="form-section-title">
            Estoque e valores
        </h3>

        <div class="form-row">

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-location"
                >
                    Localização
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-location"
                    type="text"
                    name="location"
                    value="<?= h(
                        product_value(
                            $data,
                            'location'
                        )
                    ) ?>"
                    maxlength="100"
                >

            </div>

            <?php if ($canCost): ?>

                <div class="form-group">

                    <label
                        class="form-label"
                        for="<?= h($prefix) ?>-cost-price"
                    >
                        Preço de custo
                    </label>

                    <input
                        class="form-control-os"
                        id="<?= h($prefix) ?>-cost-price"
                        type="text"
                        name="cost_price"
                        value="<?= h(
                            product_value(
                                $data,
                                'cost_price',
                                '0,00'
                            )
                        ) ?>"
                        inputmode="decimal"
                    >

                </div>

            <?php endif; ?>

            <?php if ($canSale): ?>

                <div class="form-group">

                    <label
                        class="form-label"
                        for="<?= h($prefix) ?>-sale-price"
                    >
                        Preço de venda
                    </label>

                    <input
                        class="form-control-os"
                        id="<?= h($prefix) ?>-sale-price"
                        type="text"
                        name="sale_price"
                        value="<?= h(
                            product_value(
                                $data,
                                'sale_price',
                                '0,00'
                            )
                        ) ?>"
                        inputmode="decimal"
                    >

                </div>

            <?php endif; ?>

        </div>

        <div class="form-row">

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-stock"
                >
                    Estoque<?= $editing ? '' : ' inicial' ?>
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-stock"
                    type="text"
                    name="stock"
                    value="<?= h(
                        product_value(
                            $data,
                            'stock',
                            '0'
                        )
                    ) ?>"
                    inputmode="decimal"
                >

            </div>

            <div class="form-group">

                <label
                    class="form-label"
                    for="<?= h($prefix) ?>-minimum-stock"
                >
                    Estoque mínimo
                </label>

                <input
                    class="form-control-os"
                    id="<?= h($prefix) ?>-minimum-stock"
                    type="text"
                    name="minimum_stock"
                    value="<?= h(
                        product_value(
                            $data,
                            'minimum_stock',
                            '0'
                        )
                    ) ?>"
                    inputmode="decimal"
                >

            </div>

        </div>

    </section>

    <?php
}

/*
|--------------------------------------------------------------------------
| Recovery
|--------------------------------------------------------------------------
*/

$createData =
    product_recovery_data(
        $recovery,
        'create'
    );

$createError =
    product_recovery_error(
        $recovery,
        'create'
    );

$editData =
    product_recovery_data(
        $recovery,
        'edit'
    );

$editError =
    product_recovery_error(
        $recovery,
        'edit'
    );

?>

<style>

.products-table th,
.products-table td {
    white-space: nowrap;
}

.product-fiscal-status {
    min-width: 120px;
    text-align: center;
}

.fiscal-product-section {
    background: #f8fbff;
    border: 1px solid #dbeafe;
}

.fiscal-section-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.fiscal-advanced {
    margin-top: 16px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    overflow: hidden;
}

.fiscal-advanced summary {
    padding: 14px 16px;
    cursor: pointer;
    font-weight: 700;
    color: #334155;
}

.fiscal-advanced-body {
    padding: 0 16px 16px;
}

.product-view-grid {
    display: grid;
    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        );
    gap: 12px;
}

.product-view-item {
    display: grid;
    gap: 4px;

    padding: 11px 13px;

    border: 1px solid #e5e7eb;
    border-radius: 10px;

    background: #fff;
}

.product-view-item span {
    font-size: .72rem;
    font-weight: 700;

    color: #64748b;

    text-transform: uppercase;
}

.product-view-item strong {
    color: #0f172a;
    word-break: break-word;
}

@media (max-width: 767.98px) {

    .product-view-grid {
        grid-template-columns: 1fr;
    }

    .fiscal-section-heading {
        flex-direction: column;
    }

}

</style>

<div class="page-body products-page">

<?php

metric_grid([
    [
        'Total de produtos',
        (string) (
            $summary['total']
            ?? 0
        ),
        'bi-box-seam',
        '#2563EB',
        'cadastrados',
    ],

    [
        'Produtos ativos',
        (string) (
            $summary['active']
            ?? 0
        ),
        'bi-check-circle',
        '#16A34A',
        'comercializáveis',
    ],

    [
        'Estoque baixo',
        (string) (
            $summary['low_stock']
            ?? 0
        ),
        'bi-exclamation-triangle',
        '#D97706',
        'atenção',
    ],

    [
        'Sem estoque',
        (string) (
            $summary['out_of_stock']
            ?? 0
        ),
        'bi-x-octagon',
        '#DC2626',
        'reposição',
    ],
]);

?>

<!-- ================================================================
     FILTROS
     ================================================================ -->

<form
    class="filter-bar"
    method="get"
    action="produtos.php"
    data-live-filter="products"
    data-live-regions="metrics results"
>

    <div class="search-wrap">

        <i class="bi bi-search"></i>

        <input
            class="search-input"
            type="search"
            name="search"
            value="<?= h(
                $filters['search']
            ) ?>"
            placeholder="Buscar código, produto, NCM, CFOP ou código de barras"
            maxlength="150"
        >

    </div>

    <select
        class="filter-select"
        name="category"
    >

        <option value="">
            Todas as categorias
        </option>

        <?php foreach ($categories as $category): ?>

            <option
                value="<?= h($category) ?>"
                <?= $filters['category'] === $category
                    ? 'selected'
                    : '' ?>
            >
                <?= h($category) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <select
        class="filter-select"
        name="status"
    >

        <option value="">
            Todos os status
        </option>

        <option
            value="ativo"
            <?= $filters['status'] === 'ativo'
                ? 'selected'
                : '' ?>
        >
            Ativos
        </option>

        <option
            value="inativo"
            <?= $filters['status'] === 'inativo'
                ? 'selected'
                : '' ?>
        >
            Inativos
        </option>

    </select>

    <select
        class="filter-select"
        name="stock_situation"
    >

        <option value="">
            Todas as situações
        </option>

        <option
            value="em_estoque"
            <?= $filters['stock_situation'] === 'em_estoque'
                ? 'selected'
                : '' ?>
        >
            Em estoque
        </option>

        <option
            value="estoque_baixo"
            <?= $filters['stock_situation'] === 'estoque_baixo'
                ? 'selected'
                : '' ?>
        >
            Estoque baixo
        </option>

        <option
            value="sem_estoque"
            <?= $filters['stock_situation'] === 'sem_estoque'
                ? 'selected'
                : '' ?>
        >
            Sem estoque
        </option>

    </select>

    <button
        class="btn-filter btn-filter-primary"
        type="submit"
    >
        <i class="bi bi-funnel"></i>
        Filtrar
    </button>

    <a
        class="btn-filter btn-filter-ghost"
        href="produtos.php"
        data-live-filter-clear
    >
        <i class="bi bi-x-lg"></i>
        Limpar filtros
    </a>

</form>

<!-- ================================================================
     TABELA
     ================================================================ -->

<section
    class="panel"
    data-live-region="results"
>

    <div class="panel-header">

        <div class="panel-title">
            <i class="bi bi-box-seam"></i>
            Produtos cadastrados
        </div>

        <?php if ($canCreate): ?>

            <button
                class="btn-new-os"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#modal-produto"
            >
                <i class="bi bi-box-seam"></i>
                <span>Novo produto</span>
            </button>

        <?php endif; ?>

    </div>

    <?php if ($products === []): ?>

        <?php
        empty_state(
            'Nenhum produto encontrado',
            'Cadastre o primeiro produto ou ajuste os filtros.'
        );
        ?>

    <?php else: ?>

        <div class="table-panel-wrap">

            <table class="os-table products-table">

                <thead>

                    <tr>

                        <th>Código</th>
                        <th>Produto</th>
                        <th>NCM</th>
                        <th>CFOP</th>
                        <th>Fiscal</th>
                        <th>Categoria</th>
                        <th>Unidade</th>
                        <th>Estoque</th>
                        <th>Situação</th>

                        <?php if ($canCost): ?>
                            <th>Preço custo</th>
                        <?php endif; ?>

                        <?php if ($canSale): ?>
                            <th>Preço venda</th>
                        <?php endif; ?>

                        <?php if ($canProfit): ?>
                            <th>Lucro unit.</th>
                            <th>Margem</th>
                        <?php endif; ?>

                        <th>Ações</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($products as $product): ?>

                    <?php
                    $situation =
                        $product
                            ->stockSituation();
                    ?>

                    <tr>

                        <td>
                            <strong>
                                <?= h(
                                    $product
                                        ->displayCode()
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= h(
                                $product->name()
                            ) ?>
                        </td>

                        <td>
                            <?= h(
                                $product->ncm()
                                ?? '-'
                            ) ?>
                        </td>

                        <td>
                            <?= h(
                                $product
                                    ->defaultCfop()
                                ?? '-'
                            ) ?>
                        </td>

                        <td>

                            <span
                                class="badge-soft badge-<?= h(
                                    product_fiscal_class(
                                        $product
                                    )
                                ) ?> product-fiscal-status"
                            >
                                <?= h(
                                    product_fiscal_label(
                                        $product
                                    )
                                ) ?>
                            </span>

                        </td>

                        <td>
                            <?= h(
                                $product->category()
                                ?? '-'
                            ) ?>
                        </td>

                        <td>
                            <?= h(
                                $product->unit()
                            ) ?>
                        </td>

                        <td>
                            <?= h(
                                product_decimal(
                                    $product->stock()
                                )
                            ) ?>
                        </td>

                        <td>

                            <span
                                class="badge-soft badge-<?= h(
                                    product_stock_class(
                                        $situation
                                    )
                                ) ?>"
                            >
                                <?= h(
                                    product_stock_label(
                                        $situation
                                    )
                                ) ?>
                            </span>

                        </td>

                        <?php if ($canCost): ?>

                            <td>
                                <?= h(
                                    money(
                                        $product
                                            ->costPrice()
                                    )
                                ) ?>
                            </td>

                        <?php endif; ?>

                        <?php if ($canSale): ?>

                            <td>
                                <?= h(
                                    money(
                                        $product
                                            ->salePrice()
                                    )
                                ) ?>
                            </td>

                        <?php endif; ?>

                        <?php if ($canProfit): ?>

                            <td>
                                <?= h(
                                    money(
                                        $product
                                            ->unitProfit()
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    product_percent(
                                        $product
                                            ->costMarginPercent()
                                    )
                                ) ?>
                            </td>

                        <?php endif; ?>

                        <td class="table-actions-cell">

                            <div class="dropdown table-action-dropdown">

                                <button
                                    class="btn-action"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                >
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>

                                <ul class="dropdown-menu dropdown-menu-end">

                                    <!-- VISUALIZAR -->

                                    <li>

                                        <button
                                            class="dropdown-item js-product-view"
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-produto-view"

                                            data-product-id="<?= h(
                                                (string) $product->id()
                                            ) ?>"

                                            data-product-code="<?= h(
                                                $product->displayCode()
                                            ) ?>"

                                            data-product-name="<?= h(
                                                $product->name()
                                            ) ?>"

                                            data-product-description="<?= h(
                                                $product->description()
                                                ?? ''
                                            ) ?>"

                                            data-product-category="<?= h(
                                                $product->category()
                                                ?? ''
                                            ) ?>"

                                            data-product-manufacturer="<?= h(
                                                $product->manufacturer()
                                                ?? ''
                                            ) ?>"

                                            data-product-unit="<?= h(
                                                $product->unit()
                                            ) ?>"

                                            data-product-ncm="<?= h(
                                                $product->ncm()
                                                ?? ''
                                            ) ?>"

                                            data-product-cest="<?= h(
                                                $product->cest()
                                                ?? ''
                                            ) ?>"

                                            data-product-origin="<?= h(
                                                $product->origin() === null
                                                    ? ''
                                                    : (string) $product->origin()
                                            ) ?>"

                                            data-product-default-cfop="<?= h(
                                                $product->defaultCfop()
                                                ?? ''
                                            ) ?>"

                                            data-product-icms-cst="<?= h(
                                                $product->icmsCst()
                                                ?? ''
                                            ) ?>"

                                            data-product-csosn="<?= h(
                                                $product->csosn()
                                                ?? ''
                                            ) ?>"

                                            data-product-pis-cst="<?= h(
                                                $product->pisCst()
                                                ?? ''
                                            ) ?>"

                                            data-product-cofins-cst="<?= h(
                                                $product->cofinsCst()
                                                ?? ''
                                            ) ?>"

                                            data-product-icms-rate="<?= h(
                                                $product->icmsRate()
                                                ?? ''
                                            ) ?>"

                                            data-product-pis-rate="<?= h(
                                                $product->pisRate()
                                                ?? ''
                                            ) ?>"

                                            data-product-cofins-rate="<?= h(
                                                $product->cofinsRate()
                                                ?? ''
                                            ) ?>"

                                            data-product-tax-gtin="<?= h(
                                                $product->taxGtin()
                                                ?? ''
                                            ) ?>"

                                            data-product-tax-unit="<?= h(
                                                $product->taxUnit()
                                                ?? ''
                                            ) ?>"

                                            data-product-ibs-cbs-cst="<?= h(
                                                $product->ibsCbsCst()
                                                ?? ''
                                            ) ?>"

                                            data-product-ibs-cbs-classification="<?= h(
                                                $product->ibsCbsClassification()
                                                ?? ''
                                            ) ?>"

                                            data-product-barcode="<?= h(
                                                $product->barcode()
                                                ?? ''
                                            ) ?>"

                                            data-product-stock="<?= h(
                                                $product->stock()
                                            ) ?>"

                                            data-product-minimum-stock="<?= h(
                                                $product->minimumStock()
                                            ) ?>"

                                            data-product-location="<?= h(
                                                $product->location()
                                                ?? ''
                                            ) ?>"

                                            data-product-status="<?= h(
                                                $product->status()
                                            ) ?>"

                                            data-product-fiscal-status="<?= h(
                                                product_fiscal_label(
                                                    $product
                                                )
                                            ) ?>"

                                            data-product-created-at="<?= h(
                                                product_date(
                                                    $product->createdAt()
                                                )
                                            ) ?>"

                                            data-product-updated-at="<?= h(
                                                product_date(
                                                    $product->updatedAt()
                                                )
                                            ) ?>"

                                            <?php if ($canCost): ?>
                                                data-product-cost-price="<?= h(
                                                    $product->costPrice()
                                                ) ?>"
                                            <?php endif; ?>

                                            <?php if ($canSale): ?>
                                                data-product-sale-price="<?= h(
                                                    $product->salePrice()
                                                ) ?>"
                                            <?php endif; ?>

                                            <?php if ($canProfit): ?>
                                                data-product-unit-profit="<?= h(
                                                    $product->unitProfit()
                                                ) ?>"

                                                data-product-margin="<?= h(
                                                    $product->costMarginPercent()
                                                    ?? ''
                                                ) ?>"
                                            <?php endif; ?>
                                        >

                                            <i class="bi bi-eye"></i>
                                            Visualizar

                                        </button>

                                    </li>

                                    <!-- EDITAR -->

                                    <?php if ($canEdit): ?>

                                        <li>

                                            <button
                                                class="dropdown-item js-product-edit"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modal-produto-edit"

                                                data-product-id="<?= h(
                                                    (string) $product->id()
                                                ) ?>"

                                                data-product-code="<?= h(
                                                    $product->displayCode()
                                                ) ?>"

                                                data-product-name="<?= h(
                                                    $product->name()
                                                ) ?>"

                                                data-product-description="<?= h(
                                                    $product->description()
                                                    ?? ''
                                                ) ?>"

                                                data-product-category="<?= h(
                                                    $product->category()
                                                    ?? ''
                                                ) ?>"

                                                data-product-manufacturer="<?= h(
                                                    $product->manufacturer()
                                                    ?? ''
                                                ) ?>"

                                                data-product-unit="<?= h(
                                                    $product->unit()
                                                ) ?>"

                                                data-product-ncm="<?= h(
                                                    $product->ncm()
                                                    ?? ''
                                                ) ?>"

                                                data-product-cest="<?= h(
                                                    $product->cest()
                                                    ?? ''
                                                ) ?>"

                                                data-product-origin="<?= h(
                                                    $product->origin() === null
                                                        ? ''
                                                        : (string) $product->origin()
                                                ) ?>"

                                                data-product-default-cfop="<?= h(
                                                    $product->defaultCfop()
                                                    ?? ''
                                                ) ?>"

                                                data-product-icms-cst="<?= h(
                                                    $product->icmsCst()
                                                    ?? ''
                                                ) ?>"

                                                data-product-csosn="<?= h(
                                                    $product->csosn()
                                                    ?? ''
                                                ) ?>"

                                                data-product-pis-cst="<?= h(
                                                    $product->pisCst()
                                                    ?? ''
                                                ) ?>"

                                                data-product-cofins-cst="<?= h(
                                                    $product->cofinsCst()
                                                    ?? ''
                                                ) ?>"

                                                data-product-icms-rate="<?= h(
                                                    $product->icmsRate()
                                                    ?? ''
                                                ) ?>"

                                                data-product-pis-rate="<?= h(
                                                    $product->pisRate()
                                                    ?? ''
                                                ) ?>"

                                                data-product-cofins-rate="<?= h(
                                                    $product->cofinsRate()
                                                    ?? ''
                                                ) ?>"

                                                data-product-tax-gtin="<?= h(
                                                    $product->taxGtin()
                                                    ?? ''
                                                ) ?>"

                                                data-product-tax-unit="<?= h(
                                                    $product->taxUnit()
                                                    ?? ''
                                                ) ?>"

                                                data-product-ibs-cbs-cst="<?= h(
                                                    $product->ibsCbsCst()
                                                    ?? ''
                                                ) ?>"

                                                data-product-ibs-cbs-classification="<?= h(
                                                    $product->ibsCbsClassification()
                                                    ?? ''
                                                ) ?>"

                                                data-product-barcode="<?= h(
                                                    $product->barcode()
                                                    ?? ''
                                                ) ?>"

                                                data-product-stock="<?= h(
                                                    $product->stock()
                                                ) ?>"

                                                data-product-minimum-stock="<?= h(
                                                    $product->minimumStock()
                                                ) ?>"

                                                data-product-location="<?= h(
                                                    $product->location()
                                                    ?? ''
                                                ) ?>"

                                                data-product-status="<?= h(
                                                    $product->status()
                                                ) ?>"

                                                <?php if ($canCost): ?>
                                                    data-product-cost-price="<?= h(
                                                        $product->costPrice()
                                                    ) ?>"
                                                <?php endif; ?>

                                                <?php if ($canSale): ?>
                                                    data-product-sale-price="<?= h(
                                                        $product->salePrice()
                                                    ) ?>"
                                                <?php endif; ?>
                                            >

                                                <i class="bi bi-pencil"></i>
                                                Editar

                                            </button>

                                        </li>

                                    <?php endif; ?>

                                    <!-- EXCLUIR -->

                                    <?php if ($canDelete): ?>

                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>

                                        <li>

                                            <button
                                                class="dropdown-item text-danger js-product-delete"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modal-produto-delete"

                                                data-product-id="<?= h(
                                                    (string) $product->id()
                                                ) ?>"

                                                data-product-code="<?= h(
                                                    $product->displayCode()
                                                ) ?>"

                                                data-product-name="<?= h(
                                                    $product->name()
                                                ) ?>"
                                            >
                                                <i class="bi bi-trash3"></i>
                                                Excluir produto
                                            </button>

                                        </li>

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

<!-- ================================================================
     MODAL CADASTRAR
     ================================================================ -->

<?php if ($canCreate): ?>

<div
    class="modal fade"
    id="modal-produto"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"
    >

        <form
            class="modal-content visual-modal js-product-form"
            method="post"
            action="actions/produto-salvar.php"
            autocomplete="off"
        >

            <div class="modal-header">

                <div>

                    <h2 class="modal-title fs-5">
                        Novo produto
                    </h2>

                    <p class="text-muted small mb-0">
                        O código será gerado automaticamente.
                    </p>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>

            </div>

            <div class="modal-body">

                <?= $csrf->field() ?>

                <?php
                return_to_field();
                ?>

                <div
                    class="alert alert-danger <?= $createError === null ? 'd-none' : '' ?>"
                    id="create-product-form-error"
                    role="alert"
                >
                    <?= h(
                        $createError
                        ?? ''
                    ) ?>
                </div>

                <?php
                product_form_fields(
                    $createData,
                    $canCost,
                    $canSale,
                    'create-product'
                );
                ?>

            </div>

            <div class="modal-footer">

                <button
                    class="btn-modal-cancel"
                    type="button"
                    data-bs-dismiss="modal"
                >
                    Cancelar
                </button>

                <button
                    class="btn-modal-save"
                    type="submit"
                >
                    <i class="bi bi-check-lg"></i>
                    Salvar produto
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>

<!-- ================================================================
     MODAL VISUALIZAR
     ================================================================ -->

<div
    class="modal fade"
    id="modal-produto-view"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"
    >

        <div class="modal-content visual-modal">

            <div class="modal-header">

                <div>

                    <h2 class="modal-title fs-5">
                        Dados do produto
                    </h2>

                    <p
                        class="text-muted small mb-0"
                        id="view-product-subtitle"
                    ></p>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>

            </div>

            <div class="modal-body">

                <section class="form-section">

                    <h3 class="form-section-title">
                        Identificação
                    </h3>

                    <div
                        class="product-view-grid"
                        id="view-product-basic-grid"
                    ></div>

                </section>

                <section class="form-section fiscal-product-section">

                    <div class="fiscal-section-heading">

                        <div>

                            <h3 class="form-section-title mb-1">
                                Dados fiscais
                            </h3>

                            <p class="text-muted small mb-0">
                                Informações usadas na emissão fiscal.
                            </p>

                        </div>

                        <span
                            class="badge-soft badge-gray"
                            id="view-product-fiscal-status"
                        >
                            -
                        </span>

                    </div>

                    <div
                        class="product-view-grid"
                        id="view-product-fiscal-grid"
                    ></div>

                </section>

                <section class="form-section">

                    <h3 class="form-section-title">
                        Estoque e valores
                    </h3>

                    <div
                        class="product-view-grid"
                        id="view-product-stock-grid"
                    ></div>

                </section>

            </div>

            <div class="modal-footer">

                <button
                    class="btn-modal-cancel"
                    type="button"
                    data-bs-dismiss="modal"
                >
                    Fechar
                </button>

            </div>

        </div>

    </div>

</div>

<!-- ================================================================
     MODAL EDITAR
     ================================================================ -->

<?php if ($canEdit): ?>

<div
    class="modal fade"
    id="modal-produto-edit"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"
    >

        <form
            class="modal-content visual-modal js-product-form"
            method="post"
            action="actions/produto-salvar.php"
            autocomplete="off"
        >

            <div class="modal-header">

                <div>

                    <h2 class="modal-title fs-5">
                        Editar produto
                    </h2>

                    <p
                        class="text-muted small mb-0"
                        id="edit-product-subtitle"
                    >
                        <?= h(
                            product_value(
                                $editData,
                                'code'
                            )
                        ) ?>
                    </p>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>

            </div>

            <div class="modal-body">

                <?= $csrf->field() ?>

                <?php
                return_to_field();
                ?>

                <div
                    class="alert alert-danger <?= $editError === null ? 'd-none' : '' ?>"
                    id="edit-product-form-error"
                    role="alert"
                >
                    <?= h(
                        $editError
                        ?? ''
                    ) ?>
                </div>

                <input
                    type="hidden"
                    name="id"
                    id="edit-product-id"
                    value="<?= h(
                        product_value(
                            $editData,
                            'id'
                        )
                    ) ?>"
                >

                <section class="form-section">

                    <h3 class="form-section-title">
                        Código
                    </h3>

                    <input
                        class="form-control-os"
                        id="edit-product-code"
                        type="text"
                        value="<?= h(
                            product_value(
                                $editData,
                                'code'
                            )
                        ) ?>"
                        readonly
                    >

                </section>

                <?php
                product_form_fields(
                    $editData,
                    $canCost,
                    $canSale,
                    'edit-product',
                    true
                );
                ?>

            </div>

            <div class="modal-footer">

                <button
                    class="btn-modal-cancel"
                    type="button"
                    data-bs-dismiss="modal"
                >
                    Cancelar
                </button>

                <button
                    class="btn-modal-save"
                    type="submit"
                >
                    <i class="bi bi-check-lg"></i>
                    Salvar alterações
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>

<!-- ================================================================
     MODAL EXCLUIR
     ================================================================ -->

<?php if ($canDelete): ?>

<div
    class="modal fade"
    id="modal-produto-delete"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <form
            class="modal-content visual-modal"
            method="post"
            action="actions/produto-excluir.php"
        >

            <div class="modal-header">

                <div>

                    <h2 class="modal-title fs-5">
                        Excluir produto
                    </h2>

                    <p
                        class="text-muted small mb-0"
                        id="delete-product-subtitle"
                    ></p>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>

            </div>

            <div class="modal-body">

                <?= $csrf->field() ?>

                <?php
                return_to_field();
                ?>

                <input
                    type="hidden"
                    name="id"
                    id="delete-product-id"
                >

                <div class="alert alert-warning mb-0">

                    Somente produtos sem saldo e nunca utilizados
                    podem ser excluídos.

                    Produtos que possuem histórico devem ser
                    marcados como <strong>Inativo</strong>.

                </div>

            </div>

            <div class="modal-footer">

                <button
                    class="btn-modal-cancel"
                    type="button"
                    data-bs-dismiss="modal"
                >
                    Cancelar
                </button>

                <button
                    class="btn-modal-save"
                    type="submit"
                >
                    <i class="bi bi-trash3"></i>
                    Excluir produto
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {
        'use strict';

        const recoveryModal =
            <?= json_encode(
                $recovery['modal']
                ?? null,
                JSON_HEX_TAG
                | JSON_HEX_APOS
                | JSON_HEX_AMP
                | JSON_HEX_QUOT
            ) ?>;

        const canCost =
            <?= $canCost ? 'true' : 'false' ?>;

        const canSale =
            <?= $canSale ? 'true' : 'false' ?>;

        const canProfit =
            <?= $canProfit ? 'true' : 'false' ?>;

        function setValue(
            id,
            value
        ) {
            const element =
                document.getElementById(
                    id
                );

            if (element) {
                element.value =
                    value ?? '';
            }
        }

        function setText(
            id,
            value
        ) {
            const element =
                document.getElementById(
                    id
                );

            if (!element) {
                return;
            }

            element.textContent =
                value === null
                || value === undefined
                || String(value).trim() === ''
                    ? '-'
                    : String(value);
        }

        function moneyValue(
            value
        ) {
            const number =
                Number.parseFloat(
                    value || '0'
                );

            if (!Number.isFinite(number)) {
                return '-';
            }

            return number.toLocaleString(
                'pt-BR',
                {
                    style: 'currency',
                    currency: 'BRL'
                }
            );
        }

        function percentValue(
            value
        ) {
            const number =
                Number.parseFloat(
                    value || ''
                );

            if (!Number.isFinite(number)) {
                return '-';
            }

            return number.toLocaleString(
                'pt-BR',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 4
                }
            ) + '%';
        }

        function originLabel(
            value
        ) {
            const labels = {
                '0': '0 - Nacional',
                '1': '1 - Estrangeira - importação direta',
                '2': '2 - Estrangeira - mercado interno',
                '3': '3 - Nacional - importação > 40% e ≤ 70%',
                '4': '4 - Nacional - processo produtivo básico',
                '5': '5 - Nacional - importação ≤ 40%',
                '6': '6 - Estrangeira sem similar nacional',
                '7': '7 - Estrangeira mercado interno sem similar',
                '8': '8 - Nacional - importação > 70%'
            };

            return labels[
                String(value)
            ] || '-';
        }

        function addViewItem(
            container,
            label,
            value
        ) {
            if (!container) {
                return;
            }

            const item =
                document.createElement(
                    'div'
                );

            item.className =
                'product-view-item';

            const span =
                document.createElement(
                    'span'
                );

            span.textContent =
                label;

            const strong =
                document.createElement(
                    'strong'
                );

            strong.textContent =
                value === null
                || value === undefined
                || String(value).trim() === ''
                    ? '-'
                    : String(value);

            item.append(
                span,
                strong
            );

            container.appendChild(
                item
            );
        }

        function renderView(
            data
        ) {
            setText(
                'view-product-subtitle',
                [
                    data.productCode,
                    data.productName
                ]
                    .filter(Boolean)
                    .join(' — ')
            );

            const basic =
                document.getElementById(
                    'view-product-basic-grid'
                );

            const fiscal =
                document.getElementById(
                    'view-product-fiscal-grid'
                );

            const stock =
                document.getElementById(
                    'view-product-stock-grid'
                );

            basic?.replaceChildren();
            fiscal?.replaceChildren();
            stock?.replaceChildren();

            addViewItem(
                basic,
                'Código',
                data.productCode
            );

            addViewItem(
                basic,
                'Nome',
                data.productName
            );

            addViewItem(
                basic,
                'Descrição',
                data.productDescription
            );

            addViewItem(
                basic,
                'Categoria',
                data.productCategory
            );

            addViewItem(
                basic,
                'Fabricante',
                data.productManufacturer
            );

            addViewItem(
                basic,
                'Unidade',
                data.productUnit
            );

            addViewItem(
                basic,
                'Código de barras',
                data.productBarcode
            );

            addViewItem(
                basic,
                'Status',
                data.productStatus === 'ativo'
                    ? 'Ativo'
                    : 'Inativo'
            );

            addViewItem(
                fiscal,
                'NCM',
                data.productNcm
            );

            addViewItem(
                fiscal,
                'CEST',
                data.productCest
            );

            addViewItem(
                fiscal,
                'Origem',
                originLabel(
                    data.productOrigin
                )
            );

            addViewItem(
                fiscal,
                'CFOP padrão',
                data.productDefaultCfop
            );

            addViewItem(
                fiscal,
                'CST ICMS',
                data.productIcmsCst
            );

            addViewItem(
                fiscal,
                'CSOSN',
                data.productCsosn
            );

            addViewItem(
                fiscal,
                'CST PIS',
                data.productPisCst
            );

            addViewItem(
                fiscal,
                'CST COFINS',
                data.productCofinsCst
            );

            addViewItem(
                fiscal,
                'Unidade tributável',
                data.productTaxUnit
            );

            addViewItem(
                fiscal,
                'GTIN tributável',
                data.productTaxGtin
            );

            addViewItem(
                fiscal,
                'Alíquota ICMS',
                percentValue(
                    data.productIcmsRate
                )
            );

            addViewItem(
                fiscal,
                'Alíquota PIS',
                percentValue(
                    data.productPisRate
                )
            );

            addViewItem(
                fiscal,
                'Alíquota COFINS',
                percentValue(
                    data.productCofinsRate
                )
            );

            addViewItem(
                fiscal,
                'CST IBS/CBS',
                data.productIbsCbsCst
            );

            addViewItem(
                fiscal,
                'Classificação IBS/CBS',
                data.productIbsCbsClassification
            );

            const fiscalStatus =
                document.getElementById(
                    'view-product-fiscal-status'
                );

            if (fiscalStatus) {

                fiscalStatus.textContent =
                    data.productFiscalStatus
                    || '-';

                fiscalStatus.classList.remove(
                    'badge-green',
                    'badge-red',
                    'badge-gray'
                );

                fiscalStatus.classList.add(
                    data.productFiscalStatus
                        === 'Fiscal completo'
                            ? 'badge-green'
                            : 'badge-red'
                );
            }

            addViewItem(
                stock,
                'Estoque',
                data.productStock
            );

            addViewItem(
                stock,
                'Estoque mínimo',
                data.productMinimumStock
            );

            addViewItem(
                stock,
                'Localização',
                data.productLocation
            );

            if (canCost) {
                addViewItem(
                    stock,
                    'Preço de custo',
                    moneyValue(
                        data.productCostPrice
                    )
                );
            }

            if (canSale) {
                addViewItem(
                    stock,
                    'Preço de venda',
                    moneyValue(
                        data.productSalePrice
                    )
                );
            }

            if (canProfit) {

                addViewItem(
                    stock,
                    'Lucro unitário',
                    moneyValue(
                        data.productUnitProfit
                    )
                );

                addViewItem(
                    stock,
                    'Margem',
                    percentValue(
                        data.productMargin
                    )
                );
            }

            addViewItem(
                stock,
                'Criado em',
                data.productCreatedAt
            );

            addViewItem(
                stock,
                'Atualizado em',
                data.productUpdatedAt
            );
        }

        function fillProduct(
            prefix,
            data
        ) {
            const fields = {
                'id':
                    data.productId,

                'code':
                    data.productCode,

                'name':
                    data.productName,

                'description':
                    data.productDescription,

                'category':
                    data.productCategory,

                'manufacturer':
                    data.productManufacturer,

                'unit':
                    data.productUnit,

                'ncm':
                    data.productNcm,

                'cest':
                    data.productCest,

                'origin':
                    data.productOrigin,

                'default-cfop':
                    data.productDefaultCfop,

                'icms-cst':
                    data.productIcmsCst,

                'csosn':
                    data.productCsosn,

                'pis-cst':
                    data.productPisCst,

                'cofins-cst':
                    data.productCofinsCst,

                'icms-rate':
                    data.productIcmsRate,

                'pis-rate':
                    data.productPisRate,

                'cofins-rate':
                    data.productCofinsRate,

                'tax-gtin':
                    data.productTaxGtin,

                'tax-unit':
                    data.productTaxUnit,

                'ibs-cbs-cst':
                    data.productIbsCbsCst,

                'ibs-cbs-classification':
                    data.productIbsCbsClassification,

                'barcode':
                    data.productBarcode,

                'stock':
                    data.productStock,

                'minimum-stock':
                    data.productMinimumStock,

                'location':
                    data.productLocation,

                'status':
                    data.productStatus
                    || 'ativo'
            };

            if (canCost) {
                fields['cost-price'] =
                    data.productCostPrice;
            }

            if (canSale) {
                fields['sale-price'] =
                    data.productSalePrice;
            }

            Object.entries(
                fields
            ).forEach(
                function (
                    [field, value]
                ) {
                    setValue(
                        prefix
                        + '-'
                        + field,
                        value
                    );
                }
            );
        }

        document.addEventListener(
            'click',
            function (event) {

                const button =
                    event.target.closest(
                        '.js-product-view, .js-product-edit, .js-product-delete'
                    );

                if (!button) {
                    return;
                }

                if (
                    button.classList.contains(
                        'js-product-view'
                    )
                ) {
                    renderView(
                        button.dataset
                    );

                    return;
                }

                if (
                    button.classList.contains(
                        'js-product-edit'
                    )
                ) {
                    setText(
                        'edit-product-subtitle',
                        button.dataset.productCode
                    );

                    fillProduct(
                        'edit-product',
                        button.dataset
                    );

                    return;
                }

                if (
                    button.classList.contains(
                        'js-product-delete'
                    )
                ) {
                    setValue(
                        'delete-product-id',
                        button.dataset.productId
                    );

                    setText(
                        'delete-product-subtitle',
                        [
                            button.dataset.productCode,
                            button.dataset.productName
                        ]
                            .filter(Boolean)
                            .join(' — ')
                    );
                }
            }
        );

        /*
         * Evita duplo clique / duplo POST.
         */
        document
            .querySelectorAll(
                '.js-product-form'
            )
            .forEach(
                function (form) {

                    form.addEventListener(
                        'submit',
                        function (event) {

                            if (
                                !form.checkValidity()
                            ) {
                                event.preventDefault();
                                event.stopPropagation();

                                const invalid =
                                    form.querySelector(
                                        ':invalid'
                                    );

                                invalid?.scrollIntoView({
                                    behavior:
                                        'smooth',

                                    block:
                                        'center'
                                });

                                invalid?.focus();

                                form.reportValidity();

                                return;
                            }

                            const submit =
                                form.querySelector(
                                    '[type="submit"]'
                                );

                            if (submit) {

                                submit.disabled =
                                    true;

                                submit.setAttribute(
                                    'aria-busy',
                                    'true'
                                );

                                submit.innerHTML =
                                    '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Salvando...';
                            }
                        }
                    );
                }
            );

        /*
         * Limpa modal de cadastro quando aberto manualmente.
         */
        const createModal =
            document.getElementById(
                'modal-produto'
            );

        if (createModal) {

            createModal.addEventListener(
                'show.bs.modal',
                function (event) {

                    if (!event.relatedTarget) {
                        return;
                    }

                    const form =
                        createModal.querySelector(
                            'form'
                        );

                    form?.reset();

                    setValue(
                        'create-product-unit',
                        'UN'
                    );

                    setValue(
                        'create-product-status',
                        'ativo'
                    );

                    const error =
                        document.getElementById(
                            'create-product-form-error'
                        );

                    if (error) {

                        error.textContent =
                            '';

                        error.classList.add(
                            'd-none'
                        );
                    }
                }
            );
        }

        /*
         * Reabre modal caso o backend tenha retornado
         * erro de validação.
         */
        const targets = {
            create:
                'modal-produto',

            edit:
                'modal-produto-edit'
        };

        if (
            recoveryModal
            && targets[
                recoveryModal
            ]
            && window.bootstrap
        ) {
            const modal =
                document.getElementById(
                    targets[
                        recoveryModal
                    ]
                );

            if (modal) {

                bootstrap.Modal
                    .getOrCreateInstance(
                        modal
                    )
                    .show();
            }
        }
    }
);

</script>