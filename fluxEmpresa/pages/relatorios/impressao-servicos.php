<?php

declare(strict_types=1);

/**
 * @var array<string,mixed> $reportData
 * @var string $companyName
 * @var string $generatedAt
 * @var string $returnUrl
 * @var string $sourceSection
 * @var bool $canViewFinancial
 */

$period = is_array(
    $reportData['period']
        ?? null
)
    ? $reportData['period']
    : [];

$filters = is_array(
    $reportData['filters']
        ?? null
)
    ? $reportData['filters']
    : [];

$options = is_array(
    $reportData['options']
        ?? null
)
    ? $reportData['options']
    : [];

$summary = is_array(
    $reportData['summary']
        ?? null
)
    ? $reportData['summary']
    : [];

$groups = is_array(
    $reportData['groups']
        ?? null
)
    ? $reportData['groups']
    : [];

$formatDecimal = static function (
    mixed $value,
    int $scale = 2
): string {
    $normalized = trim(
        (string) $value
    );

    if (
        $normalized === ''
        || preg_match(
            '/^-?\d+(?:\.\d+)?$/',
            $normalized
        ) !== 1
    ) {
        $normalized = '0';
    }

    $negative = str_starts_with(
        $normalized,
        '-'
    );

    $unsigned = ltrim(
        $normalized,
        '-'
    );

    [
        $integer,
        $fraction,
    ] = array_pad(
        explode(
            '.',
            $unsigned,
            2
        ),
        2,
        ''
    );

    $integer = ltrim(
        $integer,
        '0'
    );

    $integer = $integer === ''
        ? '0'
        : $integer;

    $fraction = substr(
        str_pad(
            $fraction,
            $scale,
            '0'
        ),
        0,
        $scale
    );

    return ($negative ? '-' : '')
        . number_format(
            (int) $integer,
            0,
            ',',
            '.'
        )
        . (
            $scale > 0
            ? ',' . $fraction
            : ''
        );
};

$formatMoney = static fn(
    mixed $value
): string => 'R$ '
    . $formatDecimal(
        $value,
        2
    );

$formatQuantity = static function (
    mixed $value
) use (
    $formatDecimal
): string {
    $formatted = $formatDecimal(
        $value,
        3
    );

    $formatted = preg_replace(
        '/,?0+$/',
        '',
        $formatted
    ) ?? $formatted;

    return rtrim(
        $formatted,
        ','
    );
};

$formatDateTime = static function (
    mixed $value
): string {
    $text = trim(
        (string) $value
    );

    $timestamp = $text === ''
        ? false
        : strtotime($text);

    return $timestamp === false
        ? '—'
        : date(
            'd/m/Y H:i',
            $timestamp
        );
};

$formatCountLabel = static function (
    int $count,
    string $singular,
    string $plural
): string {
    return number_format(
        $count,
        0,
        ',',
        '.'
    )
        . ' '
        . (
            $count === 1
            ? $singular
            : $plural
        );
};

$clients = is_array(
    $options['clients']
        ?? null
)
    ? $options['clients']
    : [];

$secretariats = is_array(
    $options['secretariats']
        ?? null
)
    ? $options['secretariats']
    : [];

$locations = is_array(
    $options['locations']
        ?? null
)
    ? $options['locations']
    : [];

$selectedClientId = isset(
    $filters['client_id']
)
    ? (int) $filters['client_id']
    : 0;

$selectedSecretariat = (string) (
    $filters['secretariat']
    ?? ''
);

$selectedLocation = (string) (
    $filters['location']
    ?? ''
);

$selectedSearch = (string) (
    $filters['search']
    ?? ''
);

$periodLabel = (string) (
    $period['label']
    ?? 'Período não informado'
);

$displayStart = (string) (
    $period['display_start']
    ?? ''
);

$displayEnd = (string) (
    $period['display_end']
    ?? ''
);

$clientLabel = (string) (
    $filters['client_label']
    ?? 'Todos os clientes'
);

$summaryOrdersCount = (int) (
    $summary['orders']
    ?? 0
);

$summaryItemsCount = (int) (
    $summary['items']
    ?? 0
);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Relatório detalhado de serviços realizados
    </title>

    <style>
        @page {
            size: A4 landscape;
            margin: 7mm;
        }

        * {
            box-sizing: border-box;
        }

        :root {
            --navy: #1f4e78;
            --blue-soft: #d9eaf7;
            --green-soft: #e8f3e8;
            --slate-950: #0f172a;
            --slate-800: #1e293b;
            --slate-700: #334155;
            --slate-600: #475569;
            --slate-500: #64748b;
            --slate-300: #cbd5e1;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;
            --slate-50: #f8fafc;
        }

        body {
            margin: 0;
            background: #eaf0f6;
            color: var(--slate-800);
            font-family:
                Arial,
                "DejaVu Sans",
                sans-serif;
            font-size: 9px;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 13px 18px;
            background: #ffffff;
            border-bottom: 1px solid var(--slate-300);
            box-shadow:
                0 4px 16px rgba(15, 23, 42, .08);
        }

        .toolbar-title {
            color: var(--slate-950);
            font-size: 14px;
            font-weight: 800;
        }

        .toolbar-subtitle {
            margin-top: 3px;
            color: var(--slate-500);
            font-size: 11px;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .toolbar-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 8px 13px;
            border: 1px solid var(--slate-300);
            border-radius: 8px;
            background: #ffffff;
            color: var(--slate-700);
            text-decoration: none;
            font-weight: 800;
            cursor: pointer;
        }

        .toolbar-button.primary {
            border-color: var(--navy);
            background: var(--navy);
            color: #ffffff;
        }

        .filter-panel {
            max-width: 1500px;
            margin: 14px auto 0;
            padding: 14px;
            background: #ffffff;
            border: 1px solid var(--slate-300);
            border-radius: 12px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns:
                repeat(6,
                    minmax(150px, 1fr));
            gap: 10px;
            align-items: end;
        }

        .field label {
            display: block;
            margin-bottom: 5px;
            color: var(--slate-700);
            font-weight: 800;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 38px;
            padding: 8px 10px;
            border: 1px solid var(--slate-300);
            border-radius: 8px;
            background: #ffffff;
            color: var(--slate-800);
        }

        .filter-actions {
            display: flex;
            gap: 7px;
        }

        .filter-actions .toolbar-button {
            flex: 1 1 auto;
        }

        .report-wrap {
            padding: 12px;
            overflow-x: auto;
        }

        .report-page {
            width: 283mm;
            max-width: none;
            min-height: 190mm;
            margin: 0 auto;
            padding: 5mm;
            background: #ffffff;
            box-shadow:
                0 10px 30px rgba(15, 23, 42, .12);
        }

        .report-title {
            padding: 8px 10px;
            border: 1px solid var(--navy);
            background: var(--navy);
            color: #ffffff;
            text-align: center;
            font-size: 15px;
            font-weight: 900;
            letter-spacing: .35px;
            text-transform: uppercase;
        }

        .company-line {
            padding: 7px 10px;
            border: 1px solid var(--slate-300);
            border-top: 0;
            color: var(--slate-950);
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .metadata-table,
        .summary-table,
        .group-summary,
        .items-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .metadata-table {
            margin-top: 7px;
        }

        .metadata-table td {
            border: 1px solid #aab5c3;
            padding: 5px 6px;
            vertical-align: top;
            line-height: 1.35;
        }

        .metadata-table strong {
            color: var(--slate-950);
        }

        .summary-table {
            margin-top: 7px;
            margin-bottom: 10px;
        }

        .summary-table th,
        .group-summary th {
            border: 1px solid #aab5c3;
            padding: 5px;
            background: #e5e7eb;
            color: var(--slate-700);
            text-align: center;
            font-size: 8px;
            text-transform: uppercase;
        }

        .summary-table td,
        .group-summary td {
            border: 1px solid #aab5c3;
            padding: 6px;
            color: var(--slate-950);
            text-align: center;
            font-size: 9px;
            font-weight: 800;
        }

        .secretariat-group {
            margin-top: 11px;
            page-break-after: always;
        }

        .secretariat-group:last-child {
            page-break-after: auto;
        }

        .secretariat-header {
            padding: 7px 9px;
            border: 1px solid #7fa6c9;
            background: var(--blue-soft);
            color: var(--slate-950);
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            page-break-after: avoid;
        }

        .group-summary {
            margin-top: 4px;
            margin-bottom: 8px;
        }

        .location-block {
            margin-top: 9px;
            page-break-inside: auto;
        }

        .location-header {
            padding: 6px 8px;
            border: 1px solid #9ab89a;
            background: var(--green-soft);
            color: var(--slate-950);
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            page-break-after: avoid;
        }

        .location-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 5px 8px;
            border: 1px solid var(--slate-300);
            border-top: 0;
            background: var(--slate-50);
            color: var(--slate-600);
            font-size: 8px;
        }

        .location-summary strong {
            color: var(--slate-950);
        }

        .order-block {
            margin-top: 7px;
            border: 1px solid #9aa8b8;
            page-break-inside: avoid;
        }

        .order-header {
            display: grid;
            grid-template-columns:
                1.1fr 1fr 2.6fr 1.3fr;
            gap: 0;
            background: #d9e2f3;
            border-bottom: 1px solid #9aa8b8;
        }

        .order-header>div {
            min-width: 0;
            padding: 6px 7px;
            border-right: 1px solid #9aa8b8;
            line-height: 1.35;
        }

        .order-header>div:last-child {
            border-right: 0;
        }

        .order-header span {
            display: block;
            margin-bottom: 2px;
            color: var(--slate-600);
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .order-header strong {
            display: block;
            color: var(--slate-950);
            font-size: 8.5px;
            overflow-wrap: anywhere;
        }

        .items-table th {
            border: 1px solid #7f8fa6;
            border-top: 0;
            padding: 4px;
            background: #2f5597;
            color: #ffffff;
            text-align: center;
            font-size: 7.5px;
            text-transform: uppercase;
        }

        .items-table td {
            border: 1px solid #b6c2d1;
            padding: 4px 5px;
            vertical-align: top;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .items-table tbody tr:nth-child(even) td {
            background: #f7f9fc;
        }

        .col-type {
            width: 10%;
            text-align: center;
        }

        .col-description {
            width: 46%;
        }

        .col-quantity {
            width: 9%;
            text-align: center;
        }

        .col-unit {
            width: 7%;
            text-align: center;
        }

        .col-money {
            width: 11%;
            text-align: right;
        }

        .report-summary-strip {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
            padding: 8px 10px;
            border: 1px solid #9aa8b8;
            background: #f8fafc;
            color: var(--slate-700);
            font-size: 8.5px;
            break-inside: avoid;
        }

        .report-summary-strip>strong {
            margin-right: auto;
            color: var(--slate-950);
            font-size: 8.5px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .report-summary-strip span {
            white-space: nowrap;
        }

        .report-summary-strip span+span {
            padding-left: 10px;
            border-left: 1px solid #cbd5e1;
        }

        .report-summary-strip .summary-financial {
            color: var(--slate-950);
            font-weight: 900;
        }

        .report-summary-strip.is-group {
            background: #e8eef5;
            border-color: #94a3b8;
        }

        .report-summary-strip.is-grand {
            margin-top: 12px;
            padding: 10px 12px;
            border-color: var(--navy);
            background: var(--navy);
            color: #ffffff;
            font-size: 10px;
        }

        .report-summary-strip.is-grand>strong,
        .report-summary-strip.is-grand .summary-financial {
            color: #ffffff;
        }

        .report-summary-strip.is-grand span+span {
            border-left-color: rgba(255, 255, 255, .35);
        }

        .empty-state {
            padding: 32px;
            border: 1px solid var(--slate-300);
            background: var(--slate-50);
            color: var(--slate-600);
            text-align: center;
            font-size: 11px;
        }

        .signatures {
            display: grid;
            grid-template-columns:
                repeat(2,
                    minmax(0, 1fr));
            gap: 35mm;
            margin-top: 20mm;
            page-break-inside: avoid;
        }

        .signature-line {
            padding-top: 5px;
            border-top: 1px solid #475569;
            text-align: center;
            color: var(--slate-700);
            font-size: 8px;
        }

        .print-footer {
            margin-top: 10px;
            padding-top: 5px;
            border-top: 1px solid var(--slate-300);
            color: var(--slate-500);
            text-align: center;
            font-size: 7px;
        }

        @media (max-width: 1100px) {
            .filter-grid {
                grid-template-columns:
                    repeat(2,
                        minmax(0, 1fr));
            }

            .print-toolbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 700px) {
            .report-summary-strip {
                align-items: flex-start;
                flex-direction: column;
            }

            .report-summary-strip>strong {
                margin-right: 0;
            }

            .report-summary-strip span+span {
                padding-left: 0;
                border-left: 0;
            }
        }

        @media print {
            body {
                background: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none !important;
            }

            .report-wrap {
                padding: 0 !important;
                overflow: visible !important;
            }

            .report-page {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }

            .order-block,
            .items-table tr,
            .report-summary-strip {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <header class="print-toolbar no-print">
        <div>
            <div class="toolbar-title">
                Relatório detalhado de serviços realizados
            </div>

            <div class="toolbar-subtitle">
                Agrupado por secretaria ou cliente, local e ordem de serviço.
            </div>
        </div>

        <div class="toolbar-actions">
            <a
                class="toolbar-button"
                href="<?= h($returnUrl) ?>">
                ← Voltar aos relatórios
            </a>

            <button
                class="toolbar-button primary"
                type="button"
                onclick="window.print()">
                Imprimir / Salvar PDF
            </button>
        </div>
    </header>

    <section class="filter-panel no-print">
        <form
            method="get"
            action="relatorio-imprimir.php"
            autocomplete="off">
            <input
                type="hidden"
                name="modo"
                value="periodo">

            <input
                type="hidden"
                name="source_section"
                value="<?= h($sourceSection) ?>">

            <div class="filter-grid">
                <div class="field">
                    <label for="print-start">
                        Data inicial
                    </label>

                    <input
                        id="print-start"
                        type="date"
                        name="data_inicial"
                        value="<?= h($displayStart) ?>"
                        required>
                </div>

                <div class="field">
                    <label for="print-end">
                        Data final
                    </label>

                    <input
                        id="print-end"
                        type="date"
                        name="data_final"
                        value="<?= h($displayEnd) ?>"
                        required>
                </div>

                <div class="field">
                    <label for="print-client">
                        Cliente
                    </label>

                    <select
                        id="print-client"
                        name="cliente_id">
                        <option value="">
                            Todos os clientes
                        </option>

                        <?php foreach ($clients as $client): ?>
                            <?php
                            $clientId = (int) (
                                $client['id']
                                ?? 0
                            );

                            $clientName = trim(
                                (string) (
                                    $client['name']
                                    ?? ''
                                )
                            );

                            $clientCode = trim(
                                (string) (
                                    $client['code']
                                    ?? ''
                                )
                            );
                            ?>

                            <option
                                value="<?= h((string) $clientId) ?>"
                                <?= $clientId === $selectedClientId ? 'selected' : '' ?>>
                                <?= h(
                                    $clientName
                                        . (
                                            $clientCode !== ''
                                            ? ' — ' . $clientCode
                                            : ''
                                        )
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="print-secretariat">
                        Secretaria / cliente
                    </label>

                    <select
                        id="print-secretariat"
                        name="secretaria">
                        <option value="">
                            Todas
                        </option>

                        <?php foreach ($secretariats as $option): ?>
                            <?php
                            $value = (string) (
                                $option['value']
                                ?? ''
                            );

                            $label = (string) (
                                $option['label']
                                ?? $value
                            );
                            ?>

                            <option
                                value="<?= h($value) ?>"
                                <?= $value === $selectedSecretariat ? 'selected' : '' ?>>
                                <?= h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="print-location">
                        Local
                    </label>

                    <select
                        id="print-location"
                        name="local">
                        <option value="">
                            Todos os locais
                        </option>

                        <?php foreach ($locations as $option): ?>
                            <?php
                            $value = (string) (
                                $option['value']
                                ?? ''
                            );

                            $label = (string) (
                                $option['label']
                                ?? $value
                            );
                            ?>

                            <option
                                value="<?= h($value) ?>"
                                <?= $value === $selectedLocation ? 'selected' : '' ?>>
                                <?= h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="print-search">
                        Busca
                    </label>

                    <input
                        id="print-search"
                        type="search"
                        name="busca"
                        value="<?= h($selectedSearch) ?>"
                        maxlength="120"
                        placeholder="OS, cliente, serviço ou item">
                </div>

                <div class="filter-actions">
                    <button
                        class="toolbar-button primary"
                        type="submit">
                        Aplicar filtros
                    </button>

                    <?php
                    $clearQuery = http_build_query(
                        [
                            'modo' => 'periodo',
                            'data_inicial' => $displayStart,
                            'data_final' => $displayEnd,
                            'source_section' => $sourceSection,
                        ],
                        '',
                        '&',
                        PHP_QUERY_RFC3986
                    );
                    ?>

                    <a
                        class="toolbar-button"
                        href="relatorio-imprimir.php?<?= h($clearQuery) ?>">
                        Limpar
                    </a>
                </div>
            </div>
        </form>
    </section>

    <main class="report-wrap">
        <article class="report-page">
            <div class="report-title">
                Relatório detalhado de serviços realizados
            </div>

            <div class="company-line">
                <?= h($companyName) ?>
            </div>

            <table class="metadata-table">
                <tr>
                    <td>
                        <strong>Período:</strong>
                        <?= h($periodLabel) ?>
                    </td>

                    <td>
                        <strong>Cliente:</strong>
                        <?= h($clientLabel) ?>
                    </td>

                    <td>
                        <strong>Emitido em:</strong>
                        <?= h($generatedAt) ?>
                    </td>
                </tr>

                <tr>
                    <td>
                        <strong>
                            Secretaria / cliente:
                        </strong>

                        <?= h(
                            $selectedSecretariat !== ''
                                ? $selectedSecretariat
                                : 'Todas'
                        ) ?>
                    </td>

                    <td>
                        <strong>Local:</strong>

                        <?= h(
                            $selectedLocation !== ''
                                ? $selectedLocation
                                : 'Todos'
                        ) ?>
                    </td>

                    <td>
                        <strong>Busca:</strong>

                        <?= h(
                            $selectedSearch !== ''
                                ? $selectedSearch
                                : 'Sem busca textual'
                        ) ?>
                    </td>
                </tr>
            </table>

            <table class="summary-table">
                <tr>
                    <th>OS finalizadas</th>
                    <th>Clientes</th>
                    <th>Secretarias / unidades</th>
                    <th>Locais</th>
                    <th>Serviços / itens</th>
                    <th>Quantidade total</th>
                    <th>Funcionários</th>

                    <?php if ($canViewFinancial): ?>
                        <th>
                            Valor total executado
                        </th>
                    <?php endif; ?>
                </tr>

                <tr>
                    <td>
                        <?= h(
                            (string) (
                                $summary['orders']
                                ?? 0
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= h(
                            (string) (
                                $summary['clients']
                                ?? 0
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= h(
                            (string) (
                                $summary['secretariats']
                                ?? 0
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= h(
                            (string) (
                                $summary['locations']
                                ?? 0
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= h(
                            (string) (
                                $summary['items']
                                ?? 0
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= h(
                            $formatQuantity(
                                $summary['quantity_total']
                                    ?? '0'
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= h(
                            (string) (
                                $summary['employees']
                                ?? 0
                            )
                        ) ?>
                    </td>

                    <?php if ($canViewFinancial): ?>
                        <td>
                            <?= h(
                                $formatMoney(
                                    $summary['executed_total']
                                        ?? '0'
                                )
                            ) ?>
                        </td>
                    <?php endif; ?>
                </tr>
            </table>

            <?php if ($groups === []): ?>
                <div class="empty-state">
                    Nenhum serviço ou item executado foi encontrado para os filtros selecionados.
                </div>
            <?php else: ?>
                <?php foreach ($groups as $group): ?>
                    <?php
                    if (!is_array($group)) {
                        continue;
                    }

                    $groupName = (string) (
                        $group['name']
                        ?? 'NÃO INFORMADO'
                    );

                    $groupTypeLabel =
                        !empty($group['is_public'])
                        ? 'SECRETARIA'
                        : 'CLIENTE / UNIDADE';

                    $groupLocations = is_array(
                        $group['locations']
                            ?? null
                    )
                        ? $group['locations']
                        : [];

                    $groupOrdersCount = (int) (
                        $group['orders_count']
                        ?? 0
                    );

                    $groupItemsCount = (int) (
                        $group['items_count']
                        ?? 0
                    );
                    ?>

                    <section class="secretariat-group">
                        <div class="secretariat-header">
                            <?= h($groupTypeLabel) ?>:
                            <?= h($groupName) ?>
                        </div>

                        <table class="group-summary">
                            <tr>
                                <th>OS</th>
                                <th>Locais</th>
                                <th>Serviços / itens</th>
                                <th>Quantidade</th>

                                <?php if ($canViewFinancial): ?>
                                    <th>Valor executado</th>
                                <?php endif; ?>

                                <th>Período</th>
                            </tr>

                            <tr>
                                <td>
                                    <?= h(
                                        (string) (
                                            $group['orders_count']
                                            ?? 0
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        (string) (
                                            $group['locations_count']
                                            ?? 0
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        (string) (
                                            $group['items_count']
                                            ?? 0
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $formatQuantity(
                                            $group['quantity_total']
                                                ?? '0'
                                        )
                                    ) ?>
                                </td>

                                <?php if ($canViewFinancial): ?>
                                    <td>
                                        <?= h(
                                            $formatMoney(
                                                $group['executed_total']
                                                    ?? '0'
                                            )
                                        ) ?>
                                    </td>
                                <?php endif; ?>

                                <td>
                                    <?= h($periodLabel) ?>
                                </td>
                            </tr>
                        </table>

                        <?php foreach ($groupLocations as $location): ?>
                            <?php
                            if (!is_array($location)) {
                                continue;
                            }

                            $orders = is_array(
                                $location['orders']
                                    ?? null
                            )
                                ? $location['orders']
                                : [];

                            $locationOrdersCount = (int) (
                                $location['orders_count']
                                ?? 0
                            );

                            $locationItemsCount = (int) (
                                $location['items_count']
                                ?? 0
                            );
                            ?>

                            <section class="location-block">
                                <div class="location-header">
                                    Local:
                                    <?= h(
                                        (string) (
                                            $location['name']
                                            ?? 'LOCAL NÃO INFORMADO'
                                        )
                                    ) ?>
                                </div>

                                <div class="location-summary">
                                    <span>
                                        <strong>OS:</strong>

                                        <?= h(
                                            (string) (
                                                $location['orders_count']
                                                ?? 0
                                            )
                                        ) ?>
                                    </span>

                                    <span>
                                        <strong>
                                            Serviços / itens:
                                        </strong>

                                        <?= h(
                                            (string) (
                                                $location['items_count']
                                                ?? 0
                                            )
                                        ) ?>
                                    </span>

                                    <span>
                                        <strong>
                                            Quantidade:
                                        </strong>

                                        <?= h(
                                            $formatQuantity(
                                                $location['quantity_total']
                                                    ?? '0'
                                            )
                                        ) ?>
                                    </span>

                                    <?php if ($canViewFinancial): ?>
                                        <span>
                                            <strong>
                                                Valor executado:
                                            </strong>

                                            <?= h(
                                                $formatMoney(
                                                    $location['executed_total']
                                                        ?? '0'
                                                )
                                            ) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    if (!is_array($order)) {
                                        continue;
                                    }

                                    $items = is_array(
                                        $order['items']
                                            ?? null
                                    )
                                        ? $order['items']
                                        : [];

                                    $financial = is_array(
                                        $order['financial']
                                            ?? null
                                    )
                                        ? $order['financial']
                                        : [];
                                    ?>

                                    <article class="order-block">
                                        <div class="order-header">
                                            <div>
                                                <span>
                                                    Ordem de serviço
                                                </span>

                                                <strong>
                                                    <?= h(
                                                        (string) (
                                                            $order['order_number']
                                                            ?? '—'
                                                        )
                                                    ) ?>
                                                </strong>
                                            </div>

                                            <div>
                                                <span>
                                                    Finalização
                                                </span>

                                                <strong>
                                                    <?= h(
                                                        $formatDateTime(
                                                            $order['finalized_at']
                                                                ?? ''
                                                        )
                                                    ) ?>
                                                </strong>
                                            </div>

                                            <div>
                                                <span>
                                                    Cliente
                                                </span>

                                                <strong>
                                                    <?= h(
                                                        (string) (
                                                            $order['client_name']
                                                            ?? '—'
                                                        )
                                                    ) ?>
                                                </strong>
                                            </div>

                                            <div>
                                                <span>
                                                    <?= $canViewFinancial
                                                        ? 'Total executado'
                                                        : 'Itens executados' ?>
                                                </span>

                                                <strong>
                                                    <?php if ($canViewFinancial): ?>
                                                        <?= h(
                                                            $formatMoney(
                                                                $financial['executed_total']
                                                                    ?? '0'
                                                            )
                                                        ) ?>
                                                    <?php else: ?>
                                                        <?= h(
                                                            $formatCountLabel(
                                                                count($items),
                                                                'item executado',
                                                                'itens executados'
                                                            )
                                                        ) ?>
                                                    <?php endif; ?>
                                                </strong>
                                            </div>
                                        </div>

                                        <table class="items-table">
                                            <thead>
                                                <tr>
                                                    <th class="col-type">
                                                        Tipo
                                                    </th>

                                                    <th class="col-description">
                                                        Serviço / item executado
                                                    </th>

                                                    <th class="col-quantity">
                                                        Quantidade
                                                    </th>

                                                    <th class="col-unit">
                                                        Unidade
                                                    </th>

                                                    <?php if ($canViewFinancial): ?>
                                                        <th class="col-money">
                                                            Valor unitário
                                                        </th>

                                                        <th class="col-money">
                                                            Desconto
                                                        </th>

                                                        <th class="col-money">
                                                            Subtotal
                                                        </th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <?php foreach ($items as $item): ?>
                                                    <?php
                                                    if (!is_array($item)) {
                                                        continue;
                                                    }

                                                    $itemFinancial = is_array(
                                                        $item['financial']
                                                            ?? null
                                                    )
                                                        ? $item['financial']
                                                        : [];
                                                    ?>

                                                    <tr>
                                                        <td class="col-type">
                                                            <?= h(
                                                                (string) (
                                                                    $item['origin_label']
                                                                    ?? 'SERVIÇO'
                                                                )
                                                            ) ?>
                                                        </td>

                                                        <td class="col-description">
                                                            <?= h(
                                                                (string) (
                                                                    $item['description']
                                                                    ?? 'DESCRIÇÃO NÃO INFORMADA'
                                                                )
                                                            ) ?>
                                                        </td>

                                                        <td class="col-quantity">
                                                            <?= h(
                                                                $formatQuantity(
                                                                    $item['quantity']
                                                                        ?? '0'
                                                                )
                                                            ) ?>
                                                        </td>

                                                        <td class="col-unit">
                                                            <?= h(
                                                                (string) (
                                                                    $item['unit']
                                                                    ?? '—'
                                                                )
                                                            ) ?>
                                                        </td>

                                                        <?php if ($canViewFinancial): ?>
                                                            <td class="col-money">
                                                                <?= h(
                                                                    $formatMoney(
                                                                        $itemFinancial['unit_value']
                                                                            ?? '0'
                                                                    )
                                                                ) ?>
                                                            </td>

                                                            <td class="col-money">
                                                                <?= h(
                                                                    $formatMoney(
                                                                        $itemFinancial['discount']
                                                                            ?? '0'
                                                                    )
                                                                ) ?>
                                                            </td>

                                                            <td class="col-money">
                                                                <strong>
                                                                    <?= h(
                                                                        $formatMoney(
                                                                            $itemFinancial['subtotal']
                                                                                ?? '0'
                                                                        )
                                                                    ) ?>
                                                                </strong>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>

                                    </article>
                                <?php endforeach; ?>

                                <div class="report-summary-strip is-location">
                                    <strong>
                                        Resumo do local
                                    </strong>

                                    <span>
                                        <?= h(
                                            $formatCountLabel(
                                                $locationOrdersCount,
                                                'ordem de serviço',
                                                'ordens de serviço'
                                            )
                                        ) ?>
                                    </span>

                                    <span>
                                        <?= h(
                                            $formatCountLabel(
                                                $locationItemsCount,
                                                'item executado',
                                                'itens executados'
                                            )
                                        ) ?>
                                    </span>

                                    <?php if ($canViewFinancial): ?>
                                        <span class="summary-financial">
                                            Valor executado:

                                            <?= h(
                                                $formatMoney(
                                                    $location['executed_total']
                                                        ?? '0'
                                                )
                                            ) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>

                        <?php if (count($groupLocations) > 1): ?>
                            <div class="report-summary-strip is-group">
                                <strong>
                                    Consolidado da secretaria / unidade
                                </strong>

                                <span>
                                    <?= h(
                                        $formatCountLabel(
                                            $groupOrdersCount,
                                            'ordem de serviço',
                                            'ordens de serviço'
                                        )
                                    ) ?>
                                </span>

                                <span>
                                    <?= h(
                                        $formatCountLabel(
                                            $groupItemsCount,
                                            'item executado',
                                            'itens executados'
                                        )
                                    ) ?>
                                </span>

                                <?php if ($canViewFinancial): ?>
                                    <span class="summary-financial">
                                        Valor executado:

                                        <?= h(
                                            $formatMoney(
                                                $group['executed_total']
                                                    ?? '0'
                                            )
                                        ) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>

                <div class="report-summary-strip is-grand">
                    <strong>
                        Consolidado geral do período
                    </strong>

                    <span>
                        <?= h(
                            $formatCountLabel(
                                $summaryOrdersCount,
                                'ordem de serviço',
                                'ordens de serviço'
                            )
                        ) ?>
                    </span>

                    <span>
                        <?= h(
                            $formatCountLabel(
                                $summaryItemsCount,
                                'item executado',
                                'itens executados'
                            )
                        ) ?>
                    </span>

                    <?php if ($canViewFinancial): ?>
                        <span class="summary-financial">
                            Valor total executado:

                            <?= h(
                                $formatMoney(
                                    $summary['executed_total']
                                        ?? '0'
                                )
                            ) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="signatures">
                    <div class="signature-line">
                        Responsável pela emissão
                    </div>

                    <div class="signature-line">
                        Responsável pela secretaria / unidade atendida
                    </div>
                </div>
            <?php endif; ?>

            <footer class="print-footer">
                Documento gerado pelo Flux Empresas em
                <?= h($generatedAt) ?>.

                Considera somente finalizações ativas e ordens de serviço não excluídas.
            </footer>
        </article>
    </main>
</body>

</html>