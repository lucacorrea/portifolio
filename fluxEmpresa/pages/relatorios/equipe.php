<?php

declare(strict_types=1);

/**
 * @var array<string,mixed> $reportData
 * @var bool $reportCanViewCommission
 * @var bool $reportCanConfigureGoal
 * @var bool $reportCanOpenOrders
 * @var callable(array<string,mixed>):(?string) $reportOrderUrl
 */

$reportData = is_array($reportData ?? null)
    ? $reportData
    : [];

$reportCanViewCommission = (bool) (
    $reportCanViewCommission
    ?? false
);

$reportCanConfigureGoal = (bool) (
    $reportCanConfigureGoal
    ?? false
);

$reportCanOpenOrders = (bool) (
    $reportCanOpenOrders
    ?? false
);

$formatDecimal = static function (
    mixed $value,
    int $scale = 2
): string {
    $normalized = trim((string) $value);

    if (
        $normalized === ''
        || preg_match('/^-?\d+(?:\.\d+)?$/', $normalized) !== 1
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

    [$integer, $fraction] = array_pad(
        explode('.', $unsigned, 2),
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

$formatMoney = static fn(mixed $value): string =>
    'R$ ' . $formatDecimal($value, 2);

$formatPercent = static fn(mixed $value): string =>
    $formatDecimal($value, 2) . '%';

$formatDateTime = static function (mixed $value): string {
    $text = trim((string) $value);

    $timestamp = $text === ''
        ? false
        : strtotime($text);

    return $timestamp === false
        ? '—'
        : date('d/m/Y H:i', $timestamp);
};

$period = is_array($reportData['period'] ?? null)
    ? $reportData['period']
    : [];

$goal = is_array($reportData['goal'] ?? null)
    ? $reportData['goal']
    : [];

$summary = is_array($reportData['summary'] ?? null)
    ? $reportData['summary']
    : [];

$employees = is_array($reportData['employees'] ?? null)
    ? $reportData['employees']
    : [];

$details = is_array($reportData['details'] ?? null)
    ? $reportData['details']
    : [];

$goalApplicable = (bool) (
    $goal['applicable']
    ?? false
);

$goalConfigured = (bool) (
    $goal['configured']
    ?? false
);

$periodLabel = (string) (
    $period['label']
    ?? 'Período selecionado'
);

$cards = [
    [
        'OS finalizadas',
        (string) ($summary['orders'] ?? 0),
        'bi-check2-circle',
        '#16A34A',
        $periodLabel,
    ],
    [
        'Funcionários avaliados',
        (string) ($summary['employee_count'] ?? 0),
        'bi-people',
        '#2563EB',
        'participantes da apuração',
    ],
];

if ($reportCanViewCommission) {
    $cards[] = [
        'Metas atingidas',
        (string) ($summary['qualified_count'] ?? 0),
        'bi-trophy',
        '#D97706',
        $goalConfigured
            ? 'meta configurada'
            : 'sem meta configurada',
    ];

    $cards[] = [
        'Faturamento creditado',
        $formatMoney(
            $summary['company_total']
            ?? '0'
        ),
        'bi-cash-stack',
        '#7C3AED',
        'cada OS consolidada uma vez',
    ];

    $cards[] = [
        'Serviços executados',
        $formatMoney(
            $summary['service_total']
            ?? '0'
        ),
        'bi-tools',
        '#0EA5E9',
        'subtotal de serviços',
    ];
}
?>

<div class="report-team-content">
    <div class="alert alert-info" role="note">
        <i class="bi bi-info-circle me-1"></i>

        Cada integrante recebe o valor integral da OS em que participou.
        O consolidado da empresa contabiliza cada OS somente uma vez.
    </div>

    <?php metric_grid($cards); ?>

    <section class="panel mb-4 mt-4">
        <div class="panel-header">
            <div class="panel-title">
                <i class="bi bi-bullseye"></i>

                Meta — <?= h($periodLabel) ?>
            </div>

            <?php
            if (!$goalApplicable) {
                echo ui_badge('Sem meta');
            } else {
                echo $goalConfigured
                    ? ui_badge('Ativo')
                    : ui_badge('Pendente');
            }
            ?>
        </div>

        <div class="p-3">
            <?php if (!$goalApplicable): ?>
                <p class="section-note mb-0">
                    Metas e comissão são apuradas somente no modo mensal.
                    Para usar essa regra, selecione um mês de referência.
                </p>
            <?php elseif (!$goalConfigured): ?>
                <p class="section-note mb-0">
                    Nenhuma meta foi configurada para este mês.
                    A produção continua sendo apurada, mas não há prêmio estimado.
                </p>

                <?php if ($reportCanConfigureGoal): ?>
                    <button
                        class="btn-filter btn-filter-ghost mt-3"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#modal-configurar-meta"
                    >
                        <i class="bi bi-bullseye"></i>
                        Configurar meta
                    </button>
                <?php endif; ?>
            <?php elseif ($reportCanViewCommission): ?>
                <div class="summary-box">
                    <div>
                        <span>Meta individual</span>

                        <strong>
                            <?= h(
                                $formatMoney(
                                    $goal['amount']
                                    ?? '0'
                                )
                            ) ?>
                        </strong>
                    </div>

                    <div>
                        <span>Percentual do prêmio</span>

                        <strong>
                            <?= h(
                                $formatPercent(
                                    $goal['percentage']
                                    ?? '0'
                                )
                            ) ?>
                        </strong>
                    </div>

                    <div>
                        <span>Regra</span>

                        <strong>
                            Percentual sobre todo o valor realizado
                        </strong>
                    </div>

                    <?php
                    if (
                        isset($goal['version'])
                        && $goal['version'] !== null
                    ):
                        ?>
                        <div>
                            <span>Versão da configuração</span>

                            <strong>
                                <?= h((string) $goal['version']) ?>
                            </strong>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="section-note mb-0">
                    Existe uma meta configurada para o mês.
                    Os valores, percentuais e prêmios exigem permissão de comissão.
                </p>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel mb-4">
        <div class="panel-header">
            <div class="panel-title">
                <i class="bi bi-person-lines-fill"></i>
                Desempenho por funcionário
            </div>
        </div>

        <?php if ($employees === []): ?>
            <?php
            empty_state(
                'Nenhuma produção no período',
                'Não há funcionários vinculados a OS finalizadas no período selecionado.'
            );
            ?>
        <?php else: ?>
            <div class="table-panel-wrap">
                <table class="os-table">
                    <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th>Função</th>
                        <th>OS</th>

                        <?php if ($reportCanViewCommission): ?>
                            <th>Valor creditado</th>
                            <th>Serviços</th>
                            <th>Progresso</th>
                            <th>Falta / excedente</th>
                            <th>Prêmio estimado</th>
                            <th>Situação</th>
                        <?php endif; ?>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <?php
                        $employee = is_array($employee)
                            ? $employee
                            : [];

                        $qualified = (bool) (
                            $employee['qualified']
                            ?? false
                        );
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= h(
                                        (string) (
                                            $employee['name']
                                            ?? '—'
                                        )
                                    ) ?>
                                </strong>

                                <?php
                                if (
                                    trim(
                                        (string) (
                                            $employee['code']
                                            ?? ''
                                        )
                                    ) !== ''
                                ):
                                    ?>
                                    <br>

                                    <small class="text-muted">
                                        <?= h((string) $employee['code']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= h(
                                    trim(
                                        (string) (
                                            $employee['function']
                                            ?? ''
                                        )
                                    )
                                    ?: '—'
                                ) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= h(
                                        (string) (
                                            $employee['orders']
                                            ?? 0
                                        )
                                    ) ?>
                                </strong>
                            </td>

                            <?php if ($reportCanViewCommission): ?>
                                <td>
                                    <?= h(
                                        $formatMoney(
                                            $employee['realized']
                                            ?? '0'
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $formatMoney(
                                            $employee['service_total']
                                            ?? '0'
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $formatPercent(
                                            $employee['progress_percent']
                                            ?? '0'
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?php if ($qualified): ?>
                                        <?= h(
                                            $formatMoney(
                                                $employee['exceeded']
                                                ?? '0'
                                            )
                                        ) ?>
                                        excedente
                                    <?php else: ?>
                                        <?= h(
                                            $formatMoney(
                                                $employee['remaining']
                                                ?? '0'
                                            )
                                        ) ?>
                                        restante
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= h(
                                            $formatMoney(
                                                $employee['prize']
                                                ?? '0'
                                            )
                                        ) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= ui_badge(
                                        !$goalApplicable
                                        || !$goalConfigured
                                            ? 'Sem meta'
                                            : (
                                                $qualified
                                                    ? 'Meta atingida'
                                                    : 'Em andamento'
                                            )
                                    ) ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <i class="bi bi-list-check"></i>
                OS que compõem a apuração
            </div>
        </div>

        <?php if ($details === []): ?>
            <?php
            empty_state(
                'Sem detalhamento',
                'Nenhuma OS finalizada foi encontrada para o período selecionado.'
            );
            ?>
        <?php else: ?>
            <div class="table-panel-wrap">
                <table class="os-table">
                    <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th>Função</th>
                        <th>OS</th>
                        <th>Cliente</th>
                        <th>Finalização</th>

                        <?php if ($reportCanViewCommission): ?>
                            <th>Serviços</th>
                            <th>Total executado</th>
                        <?php endif; ?>

                        <?php if ($reportCanOpenOrders): ?>
                            <th>Ação</th>
                        <?php endif; ?>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($details as $detail): ?>
                        <?php
                        $detail = is_array($detail)
                            ? $detail
                            : [];

                        $orderUrl = is_callable($reportOrderUrl ?? null)
                            ? $reportOrderUrl($detail)
                            : null;
                        ?>

                        <tr>
                            <td>
                                <?= h(
                                    (string) (
                                        $detail['employee_name']
                                        ?? '—'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    trim(
                                        (string) (
                                            $detail['employee_function']
                                            ?? ''
                                        )
                                    )
                                    ?: '—'
                                ) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= h(
                                        (string) (
                                            $detail['order_number']
                                            ?? '—'
                                        )
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= h(
                                    (string) (
                                        $detail['client_name']
                                        ?? '—'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $formatDateTime(
                                        $detail['finalized_at']
                                        ?? ''
                                    )
                                ) ?>
                            </td>

                            <?php if ($reportCanViewCommission): ?>
                                <td>
                                    <?= h(
                                        $formatMoney(
                                            $detail['service_total']
                                            ?? '0'
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= h(
                                            $formatMoney(
                                                $detail['executed_total']
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
        <?php endif; ?>
    </section>
</div>