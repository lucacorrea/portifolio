<?php

declare(strict_types=1);

namespace App\Report\Service;

use App\Report\Repository\ServiceExecutionPrintRepository;
use InvalidArgumentException;

final class ServiceExecutionPrintService
{
    public function __construct(
        private readonly ServiceExecutionPrintRepository $repository
    ) {
    }

    /**
     * @param array<string,mixed> $period
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function report(
        array $period,
        array $input,
        bool $canViewFinancial
    ): array {
        $start = trim(
            (string) ($period['start'] ?? '')
        );

        $endExclusive = trim(
            (string) ($period['end_exclusive'] ?? '')
        );

        if (
            $start === ''
            || $endExclusive === ''
        ) {
            throw new InvalidArgumentException(
                'Período inválido para impressão do relatório.'
            );
        }

        $clientId = $this->optionalPositiveInt(
            $input['cliente_id']
                ?? $input['client_id']
                ?? null,
            'Cliente inválido.'
        );

        $secretariat = $this->filterText(
            $input['secretaria'] ?? '',
            150,
            'O filtro de secretaria é muito longo.'
        );

        $location = $this->filterText(
            $input['local'] ?? '',
            200,
            'O filtro de local é muito longo.'
        );

        $search = $this->normalizedSearch(
            $input['busca']
                ?? $input['search']
                ?? ''
        );

        $clients = $this->repository->clients(
            $start,
            $endExclusive
        );

        $secretariats = $this->repository->secretariats(
            $start,
            $endExclusive,
            $clientId
        );

        $locations = $this->repository->locations(
            $start,
            $endExclusive,
            $clientId,
            $secretariat
        );

        $rows = $this->repository->rows(
            $start,
            $endExclusive,
            $clientId,
            $secretariat,
            $location,
            $search
        );

        $clientLabel = 'Todos os clientes';

        if ($clientId !== null) {
            $clientLabel = 'Cliente não encontrado';

            foreach ($clients as $client) {
                if (
                    (int) ($client['id'] ?? 0)
                    === $clientId
                ) {
                    $clientLabel = trim(
                        (string) ($client['name'] ?? '')
                    );

                    break;
                }
            }
        }

        [
            $groups,
            $summary,
        ] = $this->groupRows(
            $rows,
            $canViewFinancial
        );

        return [
            'period' => $period,

            'filters' => [
                'client_id' => $clientId,
                'client_label' => $clientLabel,
                'secretariat' => $secretariat,
                'location' => $location,
                'search' => $search,
            ],

            'options' => [
                'clients' => $clients,

                'secretariats' => array_map(
                    fn(string $value): array => [
                        'value' => $value,
                        'label' => $this->displayText(
                            $value
                        ),
                    ],
                    $secretariats
                ),

                'locations' => array_map(
                    fn(string $value): array => [
                        'value' => $value,
                        'label' => $this->displayText(
                            $value
                        ),
                    ],
                    $locations
                ),
            ],

            'summary' => $summary,
            'groups' => array_values($groups),
            'can_view_financial' => $canViewFinancial,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     *
     * @return array{
     *     0:array<string,array<string,mixed>>,
     *     1:array<string,mixed>
     * }
     */
    private function groupRows(
        array $rows,
        bool $canViewFinancial
    ): array {
        $groups = [];

        $seenOrders = [];
        $seenClients = [];
        $seenSecretariats = [];
        $seenLocations = [];
        $seenEmployees = [];

        $summaryItems = 0;
        $summaryQuantityMilli = 0;
        $summaryExecutedCents = 0;
        $summaryItemCents = 0;

        foreach ($rows as $row) {
            $secretariatName = $this->displayText(
                (string) ($row['secretariat'] ?? '')
            );

            if ($secretariatName === '') {
                $secretariatName =
                    'SECRETARIA / CLIENTE NÃO INFORMADO';
            }

            $locationName = $this->displayText(
                (string) ($row['location_name'] ?? '')
            );

            if ($locationName === '') {
                $locationName = 'LOCAL NÃO INFORMADO';
            }

            $secretariatKey = $this->groupKey(
                $secretariatName
            );

            $locationKey = $this->groupKey(
                $locationName
            );

            $orderId = (int) (
                $row['order_id']
                ?? 0
            );

            $finalizationId = (int) (
                $row['finalization_id']
                ?? 0
            );

            $orderKey = $finalizationId > 0
                ? 'finalization:' . $finalizationId
                : 'order:' . $orderId;

            if (!isset($groups[$secretariatKey])) {
                $groups[$secretariatKey] = [
                    'name' => $secretariatName,

                    'is_public' => (bool) (
                        $row['is_public_client']
                        ?? false
                    ),

                    'orders_count' => 0,
                    'locations_count' => 0,
                    'items_count' => 0,
                    'quantity_total' => '0.000',

                    'executed_total' =>
                        $canViewFinancial
                            ? '0.00'
                            : null,

                    '_quantity_milli' => 0,
                    '_executed_cents' => 0,
                    '_orders' => [],

                    'locations' => [],
                ];
            }

            if (
                !isset(
                    $groups[$secretariatKey]
                    ['locations']
                    [$locationKey]
                )
            ) {
                $groups[$secretariatKey]
                ['locations']
                [$locationKey] = [
                    'name' => $locationName,
                    'orders_count' => 0,
                    'items_count' => 0,
                    'quantity_total' => '0.000',

                    'executed_total' =>
                        $canViewFinancial
                            ? '0.00'
                            : null,

                    '_quantity_milli' => 0,
                    '_executed_cents' => 0,

                    'orders' => [],
                ];

                ++$groups[$secretariatKey]
                    ['locations_count'];
            }

            if (
                !isset(
                    $groups[$secretariatKey]
                    ['locations']
                    [$locationKey]
                    ['orders']
                    [$orderKey]
                )
            ) {
                $order = [
                    'order_id' => $orderId,

                    'order_number' => trim(
                        (string) (
                            $row['order_number']
                            ?? ''
                        )
                    ),

                    'finalized_at' => (string) (
                        $row['finalized_at']
                        ?? ''
                    ),

                    'client_id' => (int) (
                        $row['client_id']
                        ?? 0
                    ),

                    'client_code' => trim(
                        (string) (
                            $row['client_code']
                            ?? ''
                        )
                    ),

                    'client_name' => $this->displayText(
                        (string) (
                            $row['client_name']
                            ?? ''
                        )
                    ),

                    'client_document' => trim(
                        (string) (
                            $row['client_document']
                            ?? ''
                        )
                    ),

                    'client_phone' => trim(
                        (string) (
                            $row['client_phone']
                            ?? ''
                        )
                    ),

                    'team_members' => $this->displayText(
                        (string) (
                            $row['team_members']
                            ?? 'EQUIPE NÃO INFORMADA'
                        )
                    ),

                    'reported_problem' => $this->displayText(
                        (string) (
                            $row['reported_problem']
                            ?? ''
                        )
                    ),

                    'identified_problem' => $this->displayText(
                        (string) (
                            $row['identified_problem']
                            ?? ''
                        )
                    ),

                    'diagnosis' => $this->displayText(
                        (string) (
                            $row['diagnosis']
                            ?? ''
                        )
                    ),

                    'solution' => $this->displayText(
                        (string) (
                            $row['solution']
                            ?? ''
                        )
                    ),

                    'recommendation' => $this->displayText(
                        (string) (
                            $row['recommendation']
                            ?? ''
                        )
                    ),

                    'items' => [],
                ];

                $executedCents =
                    self::databaseMoneyToCents(
                        (string) (
                            $row['executed_total']
                            ?? '0'
                        )
                    );

                if ($canViewFinancial) {
                    $order['financial'] = [
                        'service_total' =>
                            self::centsToDecimal(
                                self::databaseMoneyToCents(
                                    (string) (
                                        $row['service_total']
                                        ?? '0'
                                    )
                                )
                            ),

                        'product_total' =>
                            self::centsToDecimal(
                                self::databaseMoneyToCents(
                                    (string) (
                                        $row['product_total']
                                        ?? '0'
                                    )
                                )
                            ),

                        'other_total' =>
                            self::centsToDecimal(
                                self::databaseMoneyToCents(
                                    (string) (
                                        $row['other_total']
                                        ?? '0'
                                    )
                                )
                            ),

                        'discount' =>
                            self::centsToDecimal(
                                self::databaseMoneyToCents(
                                    (string) (
                                        $row['order_discount']
                                        ?? '0'
                                    )
                                )
                            ),

                        'addition' =>
                            self::centsToDecimal(
                                self::databaseMoneyToCents(
                                    (string) (
                                        $row['order_addition']
                                        ?? '0'
                                    )
                                )
                            ),

                        'executed_total' =>
                            self::centsToDecimal(
                                $executedCents
                            ),
                    ];
                }

                $groups[$secretariatKey]
                    ['locations']
                    [$locationKey]
                    ['orders']
                    [$orderKey] = $order;

                ++$groups[$secretariatKey]
                    ['locations']
                    [$locationKey]
                    ['orders_count'];

                if (
                    !isset(
                        $groups[$secretariatKey]
                        ['_orders']
                        [$orderKey]
                    )
                ) {
                    $groups[$secretariatKey]
                        ['_orders']
                        [$orderKey] = true;

                    ++$groups[$secretariatKey]
                        ['orders_count'];
                }

                if (!isset($seenOrders[$orderKey])) {
                    $seenOrders[$orderKey] = true;

                    $summaryExecutedCents +=
                        $executedCents;
                }

                $rowClientId = (int) (
                    $row['client_id']
                    ?? 0
                );

                if ($rowClientId > 0) {
                    $seenClients[$rowClientId] = true;
                }

                $employeeIds = explode(
                    ',',
                    (string) (
                        $row['employee_ids']
                        ?? ''
                    )
                );

                foreach ($employeeIds as $employeeId) {
                    $employeeId = (int) trim(
                        $employeeId
                    );

                    if ($employeeId > 0) {
                        $seenEmployees[$employeeId] = true;
                    }
                }

                $groups[$secretariatKey]
                    ['_executed_cents'] +=
                    $executedCents;

                $groups[$secretariatKey]
                    ['locations']
                    [$locationKey]
                    ['_executed_cents'] +=
                    $executedCents;
            }

            $quantityMilli =
                self::databaseQuantityToMilli(
                    (string) (
                        $row['item_quantity']
                        ?? '0'
                    )
                );

            $itemSubtotalCents =
                self::databaseMoneyToCents(
                    (string) (
                        $row['item_subtotal']
                        ?? '0'
                    )
                );

            $itemType = (string) (
                $row['item_type']
                ?? 'servico'
            );

            $item = [
                'item_id' => (int) (
                    $row['item_id']
                    ?? 0
                ),

                'type' => $itemType,

                'origin_label' =>
                    $itemType === 'outro'
                        ? 'ITEM / OUTRO'
                        : 'SERVIÇO',

                'description' => $this->displayText(
                    (string) (
                        $row['item_description']
                        ?? ''
                    )
                ),

                'quantity' => self::milliToDecimal(
                    $quantityMilli
                ),

                'unit' => trim(
                    (string) (
                        $row['item_unit']
                        ?? ''
                    )
                ),
            ];

            if ($canViewFinancial) {
                $item['financial'] = [
                    'unit_value' =>
                        self::centsToDecimal(
                            self::databaseMoneyToCents(
                                (string) (
                                    $row['item_unit_value']
                                    ?? '0'
                                )
                            )
                        ),

                    'discount' =>
                        self::centsToDecimal(
                            self::databaseMoneyToCents(
                                (string) (
                                    $row['item_discount']
                                    ?? '0'
                                )
                            )
                        ),

                    'subtotal' =>
                        self::centsToDecimal(
                            $itemSubtotalCents
                        ),
                ];
            }

            $groups[$secretariatKey]
                ['locations']
                [$locationKey]
                ['orders']
                [$orderKey]
                ['items'][] = $item;

            ++$groups[$secretariatKey]
                ['items_count'];

            ++$groups[$secretariatKey]
                ['locations']
                [$locationKey]
                ['items_count'];

            $groups[$secretariatKey]
                ['_quantity_milli'] +=
                $quantityMilli;

            $groups[$secretariatKey]
                ['locations']
                [$locationKey]
                ['_quantity_milli'] +=
                $quantityMilli;

            ++$summaryItems;

            $summaryQuantityMilli +=
                $quantityMilli;

            $summaryItemCents +=
                $itemSubtotalCents;

            $seenSecretariats[$secretariatKey] = true;

            $seenLocations[
                $secretariatKey
                . '|'
                . $locationKey
            ] = true;
        }

        foreach ($groups as &$group) {
            $group['quantity_total'] =
                self::milliToDecimal(
                    (int) $group['_quantity_milli']
                );

            if ($canViewFinancial) {
                $group['executed_total'] =
                    self::centsToDecimal(
                        (int) $group['_executed_cents']
                    );
            }

            foreach (
                $group['locations']
                as &$groupLocation
            ) {
                $groupLocation['quantity_total'] =
                    self::milliToDecimal(
                        (int) $groupLocation[
                            '_quantity_milli'
                        ]
                    );

                if ($canViewFinancial) {
                    $groupLocation['executed_total'] =
                        self::centsToDecimal(
                            (int) $groupLocation[
                                '_executed_cents'
                            ]
                        );
                }

                $groupLocation['orders'] =
                    array_values(
                        $groupLocation['orders']
                    );

                unset(
                    $groupLocation['_quantity_milli'],
                    $groupLocation['_executed_cents']
                );
            }

            unset($groupLocation);

            $group['locations'] =
                array_values(
                    $group['locations']
                );

            unset(
                $group['_quantity_milli'],
                $group['_executed_cents'],
                $group['_orders']
            );
        }

        unset($group);

        $summary = [
            'orders' => count($seenOrders),
            'clients' => count($seenClients),

            'secretariats' =>
                count($seenSecretariats),

            'locations' =>
                count($seenLocations),

            'items' => $summaryItems,

            'quantity_total' =>
                self::milliToDecimal(
                    $summaryQuantityMilli
                ),

            'employees' =>
                count($seenEmployees),

            'executed_total' =>
                $canViewFinancial
                    ? self::centsToDecimal(
                        $summaryExecutedCents
                    )
                    : null,

            'items_total' =>
                $canViewFinancial
                    ? self::centsToDecimal(
                        $summaryItemCents
                    )
                    : null,
        ];

        return [
            $groups,
            $summary,
        ];
    }

    private function optionalPositiveInt(
        mixed $value,
        string $message
    ): ?int {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return null;
        }

        $filtered = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if (!is_int($filtered)) {
            throw new InvalidArgumentException(
                $message
            );
        }

        return $filtered;
    }

    private function filterText(
        mixed $value,
        int $maxLength,
        string $message
    ): string {
        $text = trim(
            (string) $value
        );

        if (
            $this->length($text)
            > $maxLength
        ) {
            throw new InvalidArgumentException(
                $message
            );
        }

        return $text;
    }

    private function normalizedSearch(
        mixed $value
    ): string {
        $text = preg_replace(
            '/\s+/u',
            ' ',
            trim((string) $value)
        ) ?? '';

        if ($this->length($text) > 120) {
            throw new InvalidArgumentException(
                'A busca pode ter no máximo 120 caracteres.'
            );
        }

        return $text;
    }

    private function displayText(
        string $value
    ): string {
        return preg_replace(
            '/\s+/u',
            ' ',
            trim($value)
        ) ?? trim($value);
    }

    private function groupKey(
        string $value
    ): string {
        $normalized = $this->displayText(
            $value
        );

        return function_exists('mb_strtolower')
            ? mb_strtolower(
                $normalized,
                'UTF-8'
            )
            : strtolower($normalized);
    }

    private function length(
        string $value
    ): int {
        return function_exists('mb_strlen')
            ? mb_strlen(
                $value,
                'UTF-8'
            )
            : strlen($value);
    }

    private static function databaseMoneyToCents(
        string $value
    ): int {
        $value = trim($value);

        if (
            preg_match(
                '/^-?\d+(?:\.\d+)?$/',
                $value
            ) !== 1
        ) {
            return 0;
        }

        $negative = str_starts_with(
            $value,
            '-'
        );

        $unsigned = ltrim(
            $value,
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
    }

    private static function centsToDecimal(
        int $cents
    ): string {
        $negative = $cents < 0;
        $absolute = abs($cents);

        return ($negative ? '-' : '')
            . intdiv(
                $absolute,
                100
            )
            . '.'
            . str_pad(
                (string) (
                    $absolute % 100
                ),
                2,
                '0',
                STR_PAD_LEFT
            );
    }

    private static function databaseQuantityToMilli(
        string $value
    ): int {
        $value = trim($value);

        if (
            preg_match(
                '/^-?\d+(?:\.\d+)?$/',
                $value
            ) !== 1
        ) {
            return 0;
        }

        $negative = str_starts_with(
            $value,
            '-'
        );

        $unsigned = ltrim(
            $value,
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
                3,
                '0'
            ),
            0,
            3
        );

        $milli = (
            (int) $integer
            * 1000
        ) + (int) $fraction;

        return $negative
            ? -$milli
            : $milli;
    }

    private static function milliToDecimal(
        int $milli
    ): string {
        $negative = $milli < 0;
        $absolute = abs($milli);

        return ($negative ? '-' : '')
            . intdiv(
                $absolute,
                1000
            )
            . '.'
            . str_pad(
                (string) (
                    $absolute % 1000
                ),
                3,
                '0',
                STR_PAD_LEFT
            );
    }
}