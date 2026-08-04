<?php

declare(strict_types=1);

/**
 * Variáveis fornecidas por:
 *
 * actions/relatorio-secao-carregar.php
 *
 * @var array<string,mixed> $reportData
 * @var bool $reportCanViewFinancial
 */

$reportData = is_array(
    $reportData
    ?? null
)
    ? $reportData
    : [];

$reportCanViewFinancial = (bool) (
    $reportCanViewFinancial
    ?? false
);

$period = is_array(
    $reportData['period']
    ?? null
)
    ? $reportData['period']
    : [];

$summary = is_array(
    $reportData['summary']
    ?? null
)
    ? $reportData['summary']
    : [];

$comparison = is_array(
    $reportData['comparison']
    ?? null
)
    ? $reportData['comparison']
    : [];

$dailyEvolution = is_array(
    $reportData['daily_evolution']
    ?? null
)
    ? $reportData['daily_evolution']
    : [];

$rankings = is_array(
    $reportData['rankings']
    ?? null
)
    ? $reportData['rankings']
    : [];

/*
 * =========================================================
 * FORMATADORES LOCAIS
 * =========================================================
 */

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

/*
 * Converte uma string DECIMAL do banco para centavos.
 *
 * Utilizado somente para calcular a altura relativa
 * das barras do gráfico.
 */
$decimalToCents = static function (
    mixed $value
): int {
    $normalized = trim(
        (string) $value
    );

    if (
        preg_match(
            '/^-?\d+(?:\.\d+)?$/',
            $normalized
        ) !== 1
    ) {
        return 0;
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

    $fraction = substr(
        str_pad(
            $fraction,
            2,
            '0'
        ),
        0,
        2
    );

    $cents = (
        (int) $integer
        * 100
    ) + (int) $fraction;

    return $negative
        ? -$cents
        : $cents;
};

/**
 * Renderiza a comparação com o período anterior.
 */
$comparisonMarkup = static function (
    mixed $value,
    string $previousLabel
) use (
    $formatDecimal
): string {
    $data = is_array($value)
        ? $value
        : [];

    $direction = (string) (
        $data['direction']
        ?? 'stable'
    );

    $comparable = (bool) (
        $data['comparable']
        ?? false
    );

    $percentage = $data['percentage']
        ?? null;

    if ($direction === 'stable') {
        return '<span class="report-comparison is-neutral">'
            . '<i class="bi bi-dash" aria-hidden="true"></i> '
            . 'Sem variação em relação a '
            . h($previousLabel)
            . '</span>';
    }

    $positive = $direction === 'up';

    $class = $positive
        ? 'is-positive'
        : 'is-negative';

    $icon = $positive
        ? 'bi-arrow-up-right'
        : 'bi-arrow-down-right';

    if (
        !$comparable
        || $percentage === null
    ) {
        $text = $positive
            ? 'Acima do período anterior, que estava zerado'
            : 'Abaixo do período anterior, que estava zerado';

        return '<span class="report-comparison '
            . $class
            . '">'
            . '<i class="bi '
            . $icon
            . '" aria-hidden="true"></i> '
            . h($text)
            . '</span>';
    }

    return '<span class="report-comparison '
        . $class
        . '">'
        . '<i class="bi '
        . $icon
        . '" aria-hidden="true"></i> '
        . ($positive ? '+' : '-')
        . h(
            $formatDecimal(
                $percentage,
                2
            )
        )
        . '% em relação a '
        . h($previousLabel)
        . '</span>';
};

/*
 * =========================================================
 * DADOS DO PERÍODO
 * =========================================================
 */

$periodLabel = (string) (
    $period['label']
    ?? 'Período selecionado'
);

$previousLabel = (string) (
    $period['previous_label']
    ?? 'período anterior'
);

/*
 * =========================================================
 * CARDS PRINCIPAIS
 * =========================================================
 */

$metrics = [
    [
        'label' => 'OS finalizadas',

        'value' => (string) (
            $summary['orders']
            ?? 0
        ),

        'icon' => 'bi-check2-circle',

        'comparison' => $comparison['orders']
            ?? null,
    ],

    [
        'label' => 'Clientes atendidos',

        'value' => (string) (
            $summary['unique_clients']
            ?? 0
        ),

        'icon' => 'bi-people',

        'comparison' => $comparison['unique_clients']
            ?? null,
    ],
];

if ($reportCanViewFinancial) {
    $metrics[] = [
        'label' => 'Faturamento consolidado',

        'value' => $formatMoney(
            $summary['company_total']
            ?? '0'
        ),

        'icon' => 'bi-cash-stack',

        'comparison' => $comparison['company_total']
            ?? null,
    ];

    $metrics[] = [
        'label' => 'Ticket médio',

        'value' => $formatMoney(
            $summary['average_ticket']
            ?? '0'
        ),

        'icon' => 'bi-receipt',

        'note' => 'Faturamento dividido pelas OS finalizadas',
    ];

    $metrics[] = [
        'label' => 'Total recebido',

        'value' => $formatMoney(
            $summary['received_total']
            ?? '0'
        ),

        'icon' => 'bi-wallet2',

        'note' => 'Recebimentos consolidados das OS do período',
    ];

    $metrics[] = [
        'label' => 'Saldo a receber',

        'value' => $formatMoney(
            $summary['receivable_balance']
            ?? '0'
        ),

        'icon' => 'bi-hourglass-split',

        'note' => 'Saldo ainda pendente nas contas vinculadas',
    ];

    $metrics[] = [
        'label' => 'Contas pendentes',

        'value' => (string) (
            $summary['pending_accounts']
            ?? 0
        ),

        'icon' => 'bi-clock-history',

        'note' => 'Pendentes, parciais ou vencidas com saldo',
    ];

    $metrics[] = [
        'label' => 'Contas vencidas',

        'value' => (string) (
            $summary['overdue_accounts']
            ?? 0
        ),

        'icon' => 'bi-exclamation-triangle',

        'note' => 'Contas com vencimento ultrapassado e saldo',
    ];
}

/*
 * =========================================================
 * EVOLUÇÃO DIÁRIA
 * =========================================================
 */

$maxDailyValue = 0;

foreach ($dailyEvolution as $day) {
    if (!is_array($day)) {
        continue;
    }

    $value = $reportCanViewFinancial
        ? $decimalToCents(
            $day['company_total']
            ?? '0'
        )
        : (int) (
            $day['orders']
            ?? 0
        );

    $maxDailyValue = max(
        $maxDailyValue,
        $value
    );
}

/*
 * =========================================================
 * RANKINGS
 * =========================================================
 */

$clientsRanking = is_array(
    $rankings['clients_by_revenue']
    ?? null
)
    ? $rankings['clients_by_revenue']
    : [];

$servicesRevenueRanking = is_array(
    $rankings['services_by_revenue']
    ?? null
)
    ? $rankings['services_by_revenue']
    : [];

$servicesQuantityRanking = is_array(
    $rankings['services_by_quantity']
    ?? null
)
    ? $rankings['services_by_quantity']
    : [];
?>

<div class="report-company-content">
    <div class="report-metric-grid">
        <?php foreach ($metrics as $metric): ?>
            <article class="report-metric-card">
                <div class="report-metric-head">
                    <span class="report-metric-label">
                        <?= h(
                            (string) $metric['label']
                        ) ?>
                    </span>

                    <span class="report-metric-icon">
                        <i
                            class="bi <?= h(
                                (string) $metric['icon']
                            ) ?>"
                            aria-hidden="true"
                        ></i>
                    </span>
                </div>

                <strong class="report-metric-value">
                    <?= h(
                        (string) $metric['value']
                    ) ?>
                </strong>

                <?php if (isset($metric['comparison'])): ?>
                    <?= $comparisonMarkup(
                        $metric['comparison'],
                        $previousLabel
                    ) ?>
                <?php elseif (isset($metric['note'])): ?>
                    <span class="report-metric-note">
                        <?= h(
                            (string) $metric['note']
                        ) ?>
                    </span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($reportCanViewFinancial): ?>
        <div class="report-breakdown-grid">
            <article class="report-breakdown-item">
                <span>Serviços</span>

                <strong>
                    <?= h(
                        $formatMoney(
                            $summary['service_total']
                            ?? '0'
                        )
                    ) ?>
                </strong>
            </article>

            <article class="report-breakdown-item">
                <span>Produtos</span>

                <strong>
                    <?= h(
                        $formatMoney(
                            $summary['product_total']
                            ?? '0'
                        )
                    ) ?>
                </strong>
            </article>

            <article class="report-breakdown-item">
                <span>Outros</span>

                <strong>
                    <?= h(
                        $formatMoney(
                            $summary['other_total']
                            ?? '0'
                        )
                    ) ?>
                </strong>
            </article>

            <article class="report-breakdown-item">
                <span>Descontos</span>

                <strong>
                    <?= h(
                        $formatMoney(
                            $summary['discount_total']
                            ?? '0'
                        )
                    ) ?>
                </strong>
            </article>

            <article class="report-breakdown-item">
                <span>Acréscimos</span>

                <strong>
                    <?= h(
                        $formatMoney(
                            $summary['addition_total']
                            ?? '0'
                        )
                    ) ?>
                </strong>
            </article>
        </div>
    <?php endif; ?>

    <div class="report-dashboard-grid">
        <section class="report-subpanel">
            <h3>Evolução diária</h3>

            <p>
                <?= $reportCanViewFinancial
                    ? 'Faturamento consolidado por data de finalização.'
                    : 'Quantidade de OS finalizadas por dia.' ?>
            </p>

            <?php if ($dailyEvolution === []): ?>
                <div class="report-section-state">
                    <i
                        class="bi bi-bar-chart"
                        aria-hidden="true"
                    ></i>

                    <strong>
                        Sem evolução disponível
                    </strong>

                    <p>
                        Não existem registros para montar a evolução diária.
                    </p>
                </div>
            <?php else: ?>
                <div class="report-evolution-scroll">
                    <div
                        class="report-evolution-chart"
                        role="img"
                        aria-label="Evolução diária de <?= h(
                            $periodLabel
                        ) ?>"
                    >
                        <?php foreach ($dailyEvolution as $day): ?>
                            <?php
                            if (!is_array($day)) {
                                continue;
                            }

                            $rawValue = $reportCanViewFinancial
                                ? $decimalToCents(
                                    $day['company_total']
                                    ?? '0'
                                )
                                : (int) (
                                    $day['orders']
                                    ?? 0
                                );

                            $height = $maxDailyValue > 0
                                ? max(
                                    3,
                                    min(
                                        100,
                                        intdiv(
                                            $rawValue * 100,
                                            $maxDailyValue
                                        )
                                    )
                                )
                                : 3;

                            $displayValue = $reportCanViewFinancial
                                ? $formatMoney(
                                    $day['company_total']
                                    ?? '0'
                                )
                                : (
                                    (string) (
                                        $day['orders']
                                        ?? 0
                                    )
                                    . ' OS'
                                );
                            ?>

                            <div
                                class="report-evolution-day"
                                title="<?= h(
                                    (string) (
                                        $day['label']
                                        ?? ''
                                    )
                                ) ?> — <?= h(
                                    $displayValue
                                ) ?>"
                            >
                                <div class="report-evolution-bar-wrap">
                                    <span
                                        class="report-evolution-bar"
                                        style="height: <?= h(
                                            (string) $height
                                        ) ?>%;"
                                        aria-hidden="true"
                                    ></span>
                                </div>

                                <strong>
                                    <?= h(
                                        (string) (
                                            $day['label']
                                            ?? '—'
                                        )
                                    ) ?>
                                </strong>

                                <small>
                                    <?= h($displayValue) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="report-subpanel">
            <h3>
                Serviços mais executados
            </h3>

            <p>
                Ranking por quantidade executada no período.
            </p>

            <?php if ($servicesQuantityRanking === []): ?>
                <div class="report-section-state">
                    <i
                        class="bi bi-tools"
                        aria-hidden="true"
                    ></i>

                    <strong>
                        Sem serviços executados
                    </strong>

                    <p>
                        Nenhum serviço foi encontrado no período.
                    </p>
                </div>
            <?php else: ?>
                <div class="report-ranking-list">
                    <?php
                    foreach (
                        $servicesQuantityRanking
                        as $index => $item
                    ):
                        ?>
                        <?php
                        if (!is_array($item)) {
                            continue;
                        }

                        $description = trim(
                            (string) (
                                $item['description']
                                ?? ''
                            )
                        );

                        $description = $description !== ''
                            ? $description
                            : 'Serviço sem descrição';

                        $origin = (
                            (string) (
                                $item['origin']
                                ?? 'manual'
                            )
                        ) === 'registered'
                            ? 'Cadastrado'
                            : 'Manual';
                        ?>

                        <div class="report-ranking-item">
                            <span>
                                <strong>
                                    <?= h(
                                        (string) (
                                            $index + 1
                                        )
                                    ) ?>.
                                    <?= h($description) ?>
                                </strong>

                                <small>
                                    <?= h(
                                        (string) (
                                            $item['orders']
                                            ?? 0
                                        )
                                    ) ?>
                                    OS · <?= h($origin) ?>
                                </small>
                            </span>

                            <span class="report-ranking-value">
                                <?= h(
                                    $formatQuantity(
                                        $item['quantity_total']
                                        ?? '0'
                                    )
                                ) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($reportCanViewFinancial): ?>
        <div class="report-dashboard-grid">
            <section class="report-subpanel">
                <h3>
                    Clientes por faturamento
                </h3>

                <p>
                    Clientes com maior valor executado no período.
                </p>

                <?php if ($clientsRanking === []): ?>
                    <div class="report-section-state">
                        <i
                            class="bi bi-people"
                            aria-hidden="true"
                        ></i>

                        <strong>
                            Sem clientes no ranking
                        </strong>

                        <p>
                            Nenhum cliente foi encontrado no período.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="report-ranking-list">
                        <?php
                        foreach (
                            $clientsRanking
                            as $index => $item
                        ):
                            ?>
                            <?php
                            if (!is_array($item)) {
                                continue;
                            }
                            ?>

                            <div class="report-ranking-item">
                                <span>
                                    <strong>
                                        <?= h(
                                            (string) (
                                                $index + 1
                                            )
                                        ) ?>.
                                        <?= h(
                                            (string) (
                                                $item['name']
                                                ?? 'Cliente não informado'
                                            )
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= h(
                                            (string) (
                                                $item['orders']
                                                ?? 0
                                            )
                                        ) ?>
                                        OS

                                        <?php
                                        if (
                                            trim(
                                                (string) (
                                                    $item['code']
                                                    ?? ''
                                                )
                                            ) !== ''
                                        ):
                                            ?>
                                            · <?= h(
                                                (string) $item['code']
                                            ) ?>
                                        <?php endif; ?>
                                    </small>
                                </span>

                                <span class="report-ranking-value">
                                    <?= h(
                                        $formatMoney(
                                            $item['revenue_total']
                                            ?? '0'
                                        )
                                    ) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="report-subpanel">
                <h3>
                    Serviços por faturamento
                </h3>

                <p>
                    Serviços com maior subtotal executado no período.
                </p>

                <?php if ($servicesRevenueRanking === []): ?>
                    <div class="report-section-state">
                        <i
                            class="bi bi-tools"
                            aria-hidden="true"
                        ></i>

                        <strong>
                            Sem serviços no ranking
                        </strong>

                        <p>
                            Nenhum serviço foi encontrado no período.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="report-ranking-list">
                        <?php
                        foreach (
                            $servicesRevenueRanking
                            as $index => $item
                        ):
                            ?>
                            <?php
                            if (!is_array($item)) {
                                continue;
                            }

                            $description = trim(
                                (string) (
                                    $item['description']
                                    ?? ''
                                )
                            );

                            $description = $description !== ''
                                ? $description
                                : 'Serviço sem descrição';

                            $origin = (
                                (string) (
                                    $item['origin']
                                    ?? 'manual'
                                )
                            ) === 'registered'
                                ? 'Cadastrado'
                                : 'Manual';
                            ?>

                            <div class="report-ranking-item">
                                <span>
                                    <strong>
                                        <?= h(
                                            (string) (
                                                $index + 1
                                            )
                                        ) ?>.
                                        <?= h($description) ?>
                                    </strong>

                                    <small>
                                        <?= h(
                                            (string) (
                                                $item['orders']
                                                ?? 0
                                            )
                                        ) ?>
                                        OS · <?= h($origin) ?>
                                    </small>
                                </span>

                                <span class="report-ranking-value">
                                    <?= h(
                                        $formatMoney(
                                            $item['revenue_total']
                                            ?? '0'
                                        )
                                    ) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php else: ?>
        <div
            class="alert alert-info mt-3 mb-0"
            role="note"
        >
            <i
                class="bi bi-shield-lock me-1"
                aria-hidden="true"
            ></i>

            Os valores financeiros, recebimentos e rankings por
            faturamento exigem a permissão

            <strong>
                relatorio.financeiro
            </strong>.
        </div>
    <?php endif; ?>
</div>