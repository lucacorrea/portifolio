<?php

declare(strict_types=1);

/**
 * @var array<string,mixed> $reportData
 * @var string $reportView
 * @var bool $reportCanViewFinancial
 * @var bool $reportCanOpenOrders
 * @var callable(array<string,mixed>):(?string) $reportOrderUrl
 */

$reportView = $reportView ?? 'list';
$reportData = is_array($reportData ?? null) ? $reportData : [];
$reportCanViewFinancial = (bool) ($reportCanViewFinancial ?? false);
$reportCanOpenOrders = (bool) ($reportCanOpenOrders ?? false);

$formatDecimal = static function (mixed $value, int $scale = 2): string {
    $normalized = trim((string) $value);

    if (
        $normalized === ''
        || preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) !== 1
    ) {
        $normalized = '0';
    }

    $negative = str_starts_with($normalized, '-');
    $unsigned = ltrim($normalized, '-');

    [$integer, $fraction] = array_pad(
        explode('.', $unsigned, 2),
        2,
        ''
    );

    $integer = ltrim($integer, '0');
    $integer = $integer === '' ? '0' : $integer;

    $fraction = substr(
        str_pad($fraction, $scale, '0'),
        0,
        $scale
    );

    return ($negative ? '-' : '')
        . number_format((int) $integer, 0, ',', '.')
        . ($scale > 0 ? ',' . $fraction : '');
};

$formatMoney = static fn(mixed $value): string =>
    'R$ ' . $formatDecimal($value, 2);

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

$formatDateTime = static function (mixed $value): string {
    $text = trim((string) $value);
    $timestamp = $text === '' ? false : strtotime($text);

    return $timestamp === false
        ? '—'
        : date('d/m/Y H:i', $timestamp);
};

$paginationHtml = static function (
    array $pagination,
    string $context = 'list'
): string {
    $page = max(
        1,
        (int) ($pagination['page'] ?? 1)
    );

    $lastPage = max(
        1,
        (int) ($pagination['last_page'] ?? 1)
    );

    $total = max(
        0,
        (int) ($pagination['total'] ?? 0)
    );

    $perPage = max(
        1,
        (int) ($pagination['per_page'] ?? 20)
    );

    if ($total === 0) {
        return '';
    }

    $start = (($page - 1) * $perPage) + 1;
    $end = min($page * $perPage, $total);

    $buttonAttribute = $context === 'details'
        ? 'data-report-detail-page'
        : 'data-report-page-number';

    $pages = [
        1,
        $lastPage,
    ];

    for (
        $candidate = max(1, $page - 2);
        $candidate <= min($lastPage, $page + 2);
        ++$candidate
    ) {
        $pages[] = $candidate;
    }

    $pages = array_values(
        array_unique($pages)
    );

    sort($pages);

    $html = '<div class="pagination-bar">';

    $html .= '<span>Exibindo '
        . h((string) $start)
        . '–'
        . h((string) $end)
        . ' de '
        . h((string) $total)
        . ' registros</span>';

    $html .= '<div class="pagination-controls" aria-label="Paginação">';

    $html .= '<button class="page-btn" type="button" '
        . $buttonAttribute
        . '="'
        . h((string) max(1, $page - 1))
        . '"'
        . ($page <= 1 ? ' disabled' : '')
        . ' aria-label="Página anterior">'
        . '<i class="bi bi-chevron-left"></i>'
        . '</button>';

    $previousRendered = 0;

    foreach ($pages as $candidate) {
        if (
            $previousRendered > 0
            && $candidate > $previousRendered + 1
        ) {
            $html .= '<span class="px-1 text-muted" aria-hidden="true">…</span>';
        }

        $html .= '<button class="page-btn'
            . ($candidate === $page ? ' active' : '')
            . '" type="button" '
            . $buttonAttribute
            . '="'
            . h((string) $candidate)
            . '"'
            . ($candidate === $page ? ' aria-current="page"' : '')
            . '>'
            . h((string) $candidate)
            . '</button>';

        $previousRendered = $candidate;
    }

    $html .= '<button class="page-btn" type="button" '
        . $buttonAttribute
        . '="'
        . h((string) min($lastPage, $page + 1))
        . '"'
        . ($page >= $lastPage ? ' disabled' : '')
        . ' aria-label="Próxima página">'
        . '<i class="bi bi-chevron-right"></i>'
        . '</button>';

    $html .= '</div></div>';

    return $html;
};

$sortButton = static function (
    string $key,
    string $label,
    string $currentSort,
    string $currentDirection,
    string $defaultDirection = 'desc'
): string {
    $active = $currentSort === $key;

    $direction = $active
        ? $currentDirection
        : (
            $defaultDirection === 'asc'
                ? 'desc'
                : 'asc'
        );

    $icon = 'bi-arrow-down-up';

    if ($active) {
        $icon = $currentDirection === 'asc'
            ? 'bi-arrow-up'
            : 'bi-arrow-down';
    }

    return '<button'
        . ' class="btn btn-sm btn-link text-decoration-none text-dark p-0 fw-semibold"'
        . ' type="button"'
        . ' data-report-sort="' . h($key) . '"'
        . ' data-report-direction="' . h($direction) . '"'
        . ' aria-label="Ordenar por ' . h($label) . '"'
        . '>'
        . h($label)
        . ' <i class="bi ' . h($icon) . '" aria-hidden="true"></i>'
        . '</button>';
};

if ($reportView === 'details'):
    $rows = is_array($reportData['rows'] ?? null)
        ? $reportData['rows']
        : [];

    $pagination = is_array($reportData['pagination'] ?? null)
        ? $reportData['pagination']
        : [];
    ?>

    <div
        class="report-detail-content"
        data-report-detail-kind="servico"
    >
        <?php if ($rows === []): ?>
            <?php
            empty_state(
                'Nenhuma execução encontrada',
                'O serviço não possui execuções no período selecionado.'
            );
            ?>
        <?php else: ?>
            <div class="table-panel-wrap">
                <table class="os-table">
                    <thead>
                    <tr>
                        <th>OS</th>
                        <th>Cliente</th>
                        <th>Execução</th>
                        <th>Descrição executada</th>
                        <th>Equipe</th>
                        <th>Quantidade</th>

                        <?php if ($reportCanViewFinancial): ?>
                            <th>Valor unitário</th>
                            <th>Desconto</th>
                            <th>Subtotal</th>
                        <?php endif; ?>

                        <?php if ($reportCanOpenOrders): ?>
                            <th>Ação</th>
                        <?php endif; ?>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $row = is_array($row)
                            ? $row
                            : [];

                        $financial = is_array($row['financial'] ?? null)
                            ? $row['financial']
                            : [];

                        $orderUrl = is_callable($reportOrderUrl ?? null)
                            ? $reportOrderUrl($row)
                            : null;
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= h((string) ($row['order_number'] ?? '—')) ?>
                                </strong>
                            </td>

                            <td>
                                <?= h((string) ($row['client_name'] ?? '—')) ?>
                            </td>

                            <td>
                                <?= h($formatDateTime($row['executed_at'] ?? '')) ?>
                            </td>

                            <td>
                                <?= h(
                                    (string) (
                                        $row['historical_description']
                                        ?? '—'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    (string) (
                                        $row['team_members']
                                        ?? 'Equipe não informada'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $formatQuantity(
                                        $row['quantity']
                                        ?? '0'
                                    )
                                ) ?>

                                <?= h((string) ($row['unit'] ?? '')) ?>
                            </td>

                            <?php if ($reportCanViewFinancial): ?>
                                <td>
                                    <?= h(
                                        $formatMoney(
                                            $financial['unit_value']
                                            ?? '0'
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $formatMoney(
                                            $financial['discount']
                                            ?? '0'
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= h(
                                            $formatMoney(
                                                $financial['subtotal']
                                                ?? '0'
                                            )
                                        ) ?>
                                    </strong>
                                </td>
                            <?php endif; ?>

                            <?php if ($reportCanOpenOrders): ?>
                                <td class="table-actions-cell">
                                    <?php
                                    if (
                                        is_string($orderUrl)
                                        && $orderUrl !== ''
                                    ):
                                        ?>
                                        <a
                                            class="btn-filter btn-filter-ghost"
                                            href="<?= h($orderUrl) ?>"
                                        >
                                            <i class="bi bi-box-arrow-up-right"></i>
                                            Abrir OS
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= $paginationHtml($pagination, 'details') ?>
        <?php endif; ?>
    </div>

    <?php
    return;
endif;

$rows = is_array($reportData['rows'] ?? null)
    ? $reportData['rows']
    : [];

$filters = is_array($reportData['filters'] ?? null)
    ? $reportData['filters']
    : [];

$pagination = is_array($reportData['pagination'] ?? null)
    ? $reportData['pagination']
    : [];

$period = is_array($reportData['period'] ?? null)
    ? $reportData['period']
    : [];

$currentSort = (string) (
    $filters['sort']
    ?? (
        $reportCanViewFinancial
            ? 'revenue'
            : 'quantity'
    )
);

$currentDirection = (string) (
    $filters['direction']
    ?? 'desc'
);
?>

<div class="report-service-list">
    <form
        class="filter-bar"
        data-report-section-filter
        autocomplete="off"
    >
        <div class="search-wrap">
            <i class="bi bi-search" aria-hidden="true"></i>

            <input
                class="search-input"
                type="search"
                name="busca"
                value="<?= h((string) ($filters['search'] ?? '')) ?>"
                maxlength="120"
                placeholder="Buscar descrição, nome ou código do serviço"
                aria-label="Buscar serviços no relatório"
            >
        </div>

        <button
            class="btn-filter btn-filter-primary"
            type="submit"
        >
            <i class="bi bi-search"></i>
            Buscar
        </button>

        <button
            class="btn-filter btn-filter-ghost"
            type="reset"
            data-report-clear-section-filter
        >
            <i class="bi bi-x-lg"></i>
            Limpar
        </button>

        <span class="section-note ms-auto mb-0">
            Período:
            <strong>
                <?= h((string) ($period['label'] ?? '—')) ?>
            </strong>
        </span>
    </form>

    <?php if ($rows === []): ?>
        <?php
        empty_state(
            'Nenhum serviço encontrado',
            'Não existem serviços executados para os filtros aplicados.'
        );
        ?>
    <?php else: ?>
        <div class="table-panel-wrap">
            <table class="os-table">
                <thead>
                <tr>
                    <th>
                        <?= $sortButton(
                            'description',
                            'Serviço',
                            $currentSort,
                            $currentDirection,
                            'asc'
                        ) ?>
                    </th>

                    <th>Origem</th>

                    <th>
                        <?= $sortButton(
                            'quantity',
                            'Quantidade',
                            $currentSort,
                            $currentDirection
                        ) ?>
                    </th>

                    <th>
                        <?= $sortButton(
                            'orders',
                            'OS',
                            $currentSort,
                            $currentDirection
                        ) ?>
                    </th>

                    <th>
                        <?= $sortButton(
                            'clients',
                            'Clientes',
                            $currentSort,
                            $currentDirection
                        ) ?>
                    </th>

                    <th>Primeira execução</th>

                    <th>
                        <?= $sortButton(
                            'last_execution',
                            'Última execução',
                            $currentSort,
                            $currentDirection
                        ) ?>
                    </th>

                    <?php if ($reportCanViewFinancial): ?>
                        <th>
                            <?= $sortButton(
                                'revenue',
                                'Faturamento',
                                $currentSort,
                                $currentDirection
                            ) ?>
                        </th>

                        <th>Valor médio</th>
                        <th>Ticket por OS</th>
                        <th>Descontos</th>
                    <?php endif; ?>

                    <th>Ações</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $row = is_array($row)
                        ? $row
                        : [];

                    $financial = is_array($row['financial'] ?? null)
                        ? $row['financial']
                        : [];

                    $description = trim(
                        (string) (
                            $row['historical_description']
                            ?? ''
                        )
                    );

                    $currentName = trim(
                        (string) (
                            $row['current_name']
                            ?? ''
                        )
                    );

                    $code = trim(
                        (string) (
                            $row['code']
                            ?? ''
                        )
                    );

                    $origin = (string) (
                        $row['origin']
                        ?? 'manual'
                    );
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= h(
                                    $description !== ''
                                        ? $description
                                        : (
                                            $currentName !== ''
                                                ? $currentName
                                                : 'Serviço sem descrição'
                                        )
                                ) ?>
                            </strong>

                            <?php
                            if (
                                $currentName !== ''
                                && $currentName !== $description
                            ):
                                ?>
                                <br>

                                <small class="text-muted">
                                    Cadastro atual:
                                    <?= h($currentName) ?>
                                </small>
                            <?php endif; ?>

                            <?php if ($code !== ''): ?>
                                <br>

                                <small class="text-muted">
                                    <?= h($code) ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= ui_badge(
                                $origin === 'registered'
                                    ? 'Cadastrado'
                                    : 'Manual'
                            ) ?>
                        </td>

                        <td>
                            <strong>
                                <?= h(
                                    $formatQuantity(
                                        $row['quantity_total']
                                        ?? '0'
                                    )
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= h((string) ($row['order_count'] ?? 0)) ?>
                        </td>

                        <td>
                            <?= h((string) ($row['client_count'] ?? 0)) ?>
                        </td>

                        <td>
                            <?= h(
                                $formatDateTime(
                                    $row['first_executed_at']
                                    ?? ''
                                )
                            ) ?>
                        </td>

                        <td>
                            <?= h(
                                $formatDateTime(
                                    $row['last_executed_at']
                                    ?? ''
                                )
                            ) ?>
                        </td>

                        <?php if ($reportCanViewFinancial): ?>
                            <td>
                                <strong>
                                    <?= h(
                                        $formatMoney(
                                            $financial['revenue_total']
                                            ?? '0'
                                        )
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= h(
                                    $formatMoney(
                                        $financial['average_unit_value']
                                        ?? '0'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $formatMoney(
                                        $financial['average_order_ticket']
                                        ?? '0'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $formatMoney(
                                        $financial['discount_total']
                                        ?? '0'
                                    )
                                ) ?>
                            </td>
                        <?php endif; ?>

                        <td class="table-actions-cell">
                            <button
                                class="btn-filter btn-filter-ghost"
                                type="button"
                                data-report-service-executions
                                data-group-key="<?= h(
                                    (string) ($row['group_key'] ?? '')
                                ) ?>"
                            >
                                <i class="bi bi-list-check"></i>
                                Ver execuções
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= $paginationHtml($pagination) ?>
    <?php endif; ?>
</div>