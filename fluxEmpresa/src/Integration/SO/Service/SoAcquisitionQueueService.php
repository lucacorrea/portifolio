<?php

declare(strict_types=1);

namespace App\Integration\SO\Service;

use App\Company\DTO\CompanyScope;
use App\Integration\SO\Repository\SoAcquisitionIntegrationRepository;
use InvalidArgumentException;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Prepara aquisições do Flux Empresas para envio ao SO.
 *
 * Esta classe não faz comunicação HTTP.
 *
 * Responsabilidades:
 * - validar orçamento ou OS;
 * - garantir isolamento por empresa;
 * - localizar o fornecedor correspondente no SO;
 * - montar o payload;
 * - gerar chave de idempotência;
 * - gerar hash do payload;
 * - registrar integração e outbox;
 * - impedir que OS de orçamento gere uma segunda aquisição;
 * - impedir que OS importada do SO retorne ao SO.
 */
final class SoAcquisitionQueueService
{
    private const MAX_ITEMS = 200;

    private const MAX_PAYLOAD_BYTES = 1048576;

    public function __construct(
        private readonly PDO $connection,
        private readonly CompanyScope $companyScope,
        private readonly SoAcquisitionIntegrationRepository $integrations
    ) {
    }

    /**
     * Enfileira uma aquisição originada de orçamento aprovado.
     *
     * Quando já existir uma OS vinculada ao orçamento, ela herdará
     * a mesma integração.
     *
     * @return array<string, mixed>
     */
    public function queueApprovedBudget(
        int $budgetId,
        int $userId
    ): array {
        $this->assertActor(
            $userId
        );

        $this->assertPositiveId(
            $budgetId,
            'Orçamento'
        );

        return $this->transactional(
            function () use (
                $budgetId,
                $userId
            ): array {
                $companyIntegration =
                    $this->lockCompanyIntegration();

                $budget = $this->lockApprovedBudget(
                    $budgetId
                );

                $items = $this->lockBudgetItems(
                    $budgetId
                );

                $idempotencyKey =
                    $this->budgetIdempotencyKey(
                        $budgetId
                    );

                $payload = $this->buildPayload(
                    origin: 'orcamento',
                    sourceId: $budgetId,
                    sourceNumber: (string) (
                        $budget['numero']
                        ?: sprintf(
                            'ORC-%06d',
                            $budgetId
                        )
                    ),
                    clientName: (string) $budget['cliente_nome'],
                    clientDocument: isset(
                        $budget['cliente_documento']
                    )
                        ? (string) $budget['cliente_documento']
                        : '',
                    description: $this->budgetDescription(
                        $budget
                    ),
                    total: (string) $budget['total'],
                    sourceItems: $items,
                    supplierSoId: $companyIntegration[
                        'fornecedor_so_id'
                    ],
                    userId: $userId,
                    idempotencyKey: $idempotencyKey
                );

                $snapshot = $this->encodePayload(
                    $payload
                );

                $payloadHash = hash(
                    'sha256',
                    $snapshot
                );

                $integration =
                    $this->integrations
                        ->queueBudgetCreation(
                            budgetId: $budgetId,
                            supplierSoId: $companyIntegration[
                                'fornecedor_so_id'
                            ],
                            userId: $userId,
                            idempotencyKey: $idempotencyKey,
                            payloadHash: $payloadHash,
                            payloadSnapshot: $snapshot,
                            priority: 5
                        );

                /*
                 * O fluxo atual já pode criar uma OS automaticamente
                 * ao aprovar o orçamento. Quando isso acontecer,
                 * vinculamos a OS à mesma aquisição.
                 */
                $serviceOrderId =
                    $this->findOperationalOrderForBudget(
                        $budgetId
                    );

                if ($serviceOrderId !== null) {
                    $this->integrations
                        ->attachServiceOrderToBudgetIntegration(
                            budgetId: $budgetId,
                            serviceOrderId: $serviceOrderId
                        );

                    $updatedIntegration =
                        $this->integrations
                            ->findByBudget(
                                $budgetId
                            );

                    if ($updatedIntegration !== null) {
                        return $updatedIntegration;
                    }
                }

                return $integration;
            }
        );
    }

    /**
     * Enfileira uma aquisição originada de uma OS aprovada.
     *
     * Regras:
     *
     * - OS direta: cria uma nova pendência para o SO;
     * - OS de orçamento: herda a aquisição do orçamento;
     * - OS importada do SO: não gera nova aquisição;
     * - OS já vinculada: retorna o vínculo existente.
     *
     * @return array<string, mixed>
     */
    public function queueApprovedServiceOrder(
        int $serviceOrderId,
        int $userId
    ): array {
        $this->assertActor(
            $userId
        );

        $this->assertPositiveId(
            $serviceOrderId,
            'Ordem de serviço'
        );

        /*
         * Primeiro identificamos se a OS veio de um orçamento.
         *
         * A leitura continua isolada pela empresa.
         */
        $budgetId = $this->findBudgetIdForOrder(
            $serviceOrderId
        );

        if ($budgetId !== null) {
            /*
             * O orçamento é a origem oficial da aquisição.
             *
             * Se a integração ainda não existir, ela será criada.
             */
            $this->queueApprovedBudget(
                $budgetId,
                $userId
            );

            return $this->transactional(
                function () use (
                    $serviceOrderId,
                    $budgetId
                ): array {
                    $order = $this->lockApprovedServiceOrder(
                        $serviceOrderId
                    );

                    if (
                        (int) $order['orcamento_id']
                        !== $budgetId
                    ) {
                        throw new RuntimeException(
                            'O orçamento da ordem de serviço foi alterado.'
                        );
                    }

                    $this->integrations
                        ->attachServiceOrderToBudgetIntegration(
                            budgetId: $budgetId,
                            serviceOrderId: $serviceOrderId
                        );

                    $integration =
                        $this->integrations
                            ->findByServiceOrder(
                                $serviceOrderId
                            );

                    if ($integration === null) {
                        throw new RuntimeException(
                            'Não foi possível localizar a integração herdada do orçamento.'
                        );
                    }

                    return $integration;
                }
            );
        }

        return $this->transactional(
            function () use (
                $serviceOrderId,
                $userId
            ): array {
                $companyIntegration =
                    $this->lockCompanyIntegration();

                $order = $this->lockApprovedServiceOrder(
                    $serviceOrderId
                );

                /*
                 * Uma integração existente pode representar:
                 *
                 * - aquisição já criada;
                 * - aquisição pendente;
                 * - OS importada do SO.
                 *
                 * Em todos esses casos não devemos criar outra.
                 */
                $existing =
                    $this->integrations
                        ->findByServiceOrder(
                            $serviceOrderId
                        );

                if ($existing !== null) {
                    return $existing;
                }

                if ($order['orcamento_id'] !== null) {
                    throw new RuntimeException(
                        'A ordem de serviço possui um orçamento de origem e não pode gerar outra aquisição.'
                    );
                }

                $items = $this->lockServiceOrderItems(
                    $serviceOrderId
                );

                $idempotencyKey =
                    $this->serviceOrderIdempotencyKey(
                        $serviceOrderId
                    );

                $payload = $this->buildPayload(
                    origin: 'ordem_servico',
                    sourceId: $serviceOrderId,
                    sourceNumber: (string) (
                        $order['numero']
                        ?: sprintf(
                            'OS-%06d',
                            $serviceOrderId
                        )
                    ),
                    clientName: (string) $order['cliente_nome'],
                    clientDocument: isset(
                        $order['cliente_documento']
                    )
                        ? (string) $order['cliente_documento']
                        : '',
                    description: $this->serviceOrderDescription(
                        $order
                    ),
                    total: (string) $order['total'],
                    sourceItems: $items,
                    supplierSoId: $companyIntegration[
                        'fornecedor_so_id'
                    ],
                    userId: $userId,
                    idempotencyKey: $idempotencyKey
                );

                $snapshot = $this->encodePayload(
                    $payload
                );

                $payloadHash = hash(
                    'sha256',
                    $snapshot
                );

                return $this->integrations
                    ->queueServiceOrderCreation(
                        serviceOrderId: $serviceOrderId,
                        supplierSoId: $companyIntegration[
                            'fornecedor_so_id'
                        ],
                        userId: $userId,
                        idempotencyKey: $idempotencyKey,
                        payloadHash: $payloadHash,
                        payloadSnapshot: $snapshot,
                        priority: 5
                    );
            }
        );
    }

    /**
     * @return array{
     *     fornecedor_so_id: int,
     *     empresa_uuid: string
     * }
     */
    private function lockCompanyIntegration(): array
    {
        $statement = $this->connection->prepare(
            "SELECT
                e.id,
                e.uuid,
                e.status,
                ei.id AS integracao_id,
                ei.identificador_externo
             FROM empresas AS e
             INNER JOIN empresa_integracoes AS ei
                ON ei.empresa_id = e.id
               AND ei.sistema = 'SO'
               AND ei.entidade = 'fornecedor'
             WHERE e.id = :empresa_id
             ORDER BY ei.id ASC
             FOR UPDATE"
        );

        $statement->execute([
            'empresa_id' => $this->companyScope->id(),
        ]);

        $rows = $statement->fetchAll();

        if ($rows === []) {
            throw new InvalidArgumentException(
                'A empresa não possui fornecedor vinculado no SO.'
            );
        }

        if (count($rows) !== 1) {
            throw new RuntimeException(
                'A empresa possui mais de um fornecedor vinculado no SO.'
            );
        }

        $row = $rows[0];

        if (
            (string) $row['status']
            !== 'ativo'
        ) {
            throw new InvalidArgumentException(
                'Somente empresas ativas podem criar aquisições no SO.'
            );
        }

        $companyUuid = strtolower(
            trim(
                (string) $row['uuid']
            )
        );

        if (
            preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',
                $companyUuid
            ) !== 1
        ) {
            throw new RuntimeException(
                'O UUID da empresa é inválido.'
            );
        }

        $supplierSoId = filter_var(
            $row['identificador_externo'],
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($supplierSoId === false) {
            throw new RuntimeException(
                'O fornecedor vinculado no SO é inválido.'
            );
        }

        if (
            !hash_equals(
                strtolower(
                    $this->companyScope->uuid()
                ),
                $companyUuid
            )
        ) {
            throw new RuntimeException(
                'O contexto da empresa não corresponde ao cadastro atual.'
            );
        }

        return [
            'fornecedor_so_id' => (int) $supplierSoId,
            'empresa_uuid' => $companyUuid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lockApprovedBudget(
        int $budgetId
    ): array {
        $statement = $this->connection->prepare(
            "SELECT
                o.id,
                o.numero,
                o.cliente_id,
                o.status,
                o.observacoes,
                o.total,
                o.aprovado_em,
                o.excluido_em,
                c.nome AS cliente_nome,
                c.documento AS cliente_documento
             FROM orcamentos AS o
             INNER JOIN clientes AS c
                ON c.id = o.cliente_id
               AND c.empresa_id = o.empresa_id
             WHERE o.id = :id
               AND o.empresa_id = :empresa_id
             LIMIT 1
             FOR UPDATE"
        );

        $statement->execute([
            'id' => $budgetId,
            'empresa_id' => $this->companyScope->id(),
        ]);

        $budget = $statement->fetch();

        if (!is_array($budget)) {
            throw new InvalidArgumentException(
                'Orçamento não encontrado.'
            );
        }

        if ($budget['excluido_em'] !== null) {
            throw new InvalidArgumentException(
                'Orçamento excluído não pode gerar aquisição.'
            );
        }

        if (
            (string) $budget['status']
            !== 'aprovado'
        ) {
            throw new InvalidArgumentException(
                'Somente orçamento aprovado pode gerar aquisição.'
            );
        }

        return $budget;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lockBudgetItems(
        int $budgetId
    ): array {
        $statement = $this->connection->prepare(
            'SELECT
                id,
                tipo,
                descricao,
                unidade,
                quantidade,
                valor_unitario,
                desconto,
                subtotal,
                ordem
             FROM orcamento_itens
             WHERE orcamento_id = :orcamento_id
               AND empresa_id = :empresa_id
             ORDER BY ordem ASC, id ASC
             FOR UPDATE'
        );

        $statement->execute([
            'orcamento_id' => $budgetId,
            'empresa_id' => $this->companyScope->id(),
        ]);

        $items = $statement->fetchAll();

        if ($items === []) {
            throw new InvalidArgumentException(
                'O orçamento não possui itens.'
            );
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function lockApprovedServiceOrder(
        int $serviceOrderId
    ): array {
        $statement = $this->connection->prepare(
            "SELECT
                os.id,
                os.numero,
                os.cliente_id,
                os.orcamento_id,
                os.status,
                os.aprovacao_status,
                os.aprovada_em,
                os.aprovada_por,
                os.problema_relatado,
                os.problema_identificado,
                os.diagnostico,
                os.solucao,
                os.observacoes,
                os.total,
                os.excluida_em,
                c.nome AS cliente_nome,
                c.documento AS cliente_documento
             FROM ordens_servico AS os
             INNER JOIN clientes AS c
                ON c.id = os.cliente_id
               AND c.empresa_id = os.empresa_id
             WHERE os.id = :id
               AND os.empresa_id = :empresa_id
             LIMIT 1
             FOR UPDATE"
        );

        $statement->execute([
            'id' => $serviceOrderId,
            'empresa_id' => $this->companyScope->id(),
        ]);

        $order = $statement->fetch();

        if (!is_array($order)) {
            throw new InvalidArgumentException(
                'Ordem de serviço não encontrada.'
            );
        }

        if ($order['excluida_em'] !== null) {
            throw new InvalidArgumentException(
                'Ordem de serviço excluída não pode gerar aquisição.'
            );
        }

        if (
            (string) $order['status']
            === 'cancelada'
        ) {
            throw new InvalidArgumentException(
                'Ordem de serviço cancelada não pode gerar aquisição.'
            );
        }

        if (
            (string) $order['aprovacao_status']
            !== 'aprovada'
        ) {
            throw new InvalidArgumentException(
                'Somente ordem de serviço aprovada pode gerar aquisição.'
            );
        }

        return $order;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lockServiceOrderItems(
        int $serviceOrderId
    ): array {
        $statement = $this->connection->prepare(
            'SELECT
                id,
                tipo,
                descricao,
                unidade,
                quantidade,
                valor_unitario,
                desconto,
                subtotal,
                ordem
             FROM ordem_servico_itens
             WHERE ordem_servico_id = :ordem_servico_id
               AND empresa_id = :empresa_id
             ORDER BY ordem ASC, id ASC
             FOR UPDATE'
        );

        $statement->execute([
            'ordem_servico_id' => $serviceOrderId,
            'empresa_id' => $this->companyScope->id(),
        ]);

        $items = $statement->fetchAll();

        if ($items === []) {
            throw new InvalidArgumentException(
                'A ordem de serviço não possui itens.'
            );
        }

        return $items;
    }

    private function findBudgetIdForOrder(
        int $serviceOrderId
    ): ?int {
        $statement = $this->connection->prepare(
            'SELECT orcamento_id
             FROM ordens_servico
             WHERE id = :id
               AND empresa_id = :empresa_id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $serviceOrderId,
            'empresa_id' => $this->companyScope->id(),
        ]);

        $budgetId = $statement->fetchColumn();

        if ($budgetId === false) {
            throw new InvalidArgumentException(
                'Ordem de serviço não encontrada.'
            );
        }

        if ($budgetId === null) {
            return null;
        }

        $budgetId = (int) $budgetId;

        return $budgetId > 0
            ? $budgetId
            : null;
    }

    private function findOperationalOrderForBudget(
        int $budgetId
    ): ?int {
        $statement = $this->connection->prepare(
            "SELECT id
             FROM ordens_servico
             WHERE orcamento_id = :orcamento_id
               AND empresa_id = :empresa_id
               AND excluida_em IS NULL
               AND status <> 'cancelada'
             ORDER BY id DESC
             LIMIT 1
             FOR UPDATE"
        );

        $statement->execute([
            'orcamento_id' => $budgetId,
            'empresa_id' => $this->companyScope->id(),
        ]);

        $orderId = $statement->fetchColumn();

        return $orderId === false
            ? null
            : (int) $orderId;
    }

    /**
     * @param array<int, array<string, mixed>> $sourceItems
     *
     * @return array<string, mixed>
     */
    private function buildPayload(
        string $origin,
        int $sourceId,
        string $sourceNumber,
        string $clientName,
        string $clientDocument,
        string $description,
        string $total,
        array $sourceItems,
        int $supplierSoId,
        int $userId,
        string $idempotencyKey
    ): array {
        $totalCents = $this->moneyToCents(
            $total,
            'Valor total'
        );

        if ($totalCents <= 0) {
            throw new InvalidArgumentException(
                'O valor total precisa ser maior que zero.'
            );
        }

        $normalizedClientName = $this->requiredText(
            $clientName,
            255,
            'Nome do cliente'
        );

        $clientDocument = preg_replace(
            '/\D+/',
            '',
            $clientDocument
        ) ?? '';

        if (
            !in_array(
                strlen($clientDocument),
                [
                    0,
                    11,
                    14,
                ],
                true
            )
        ) {
            /*
             * Documento é opcional na integração.
             * Um valor legado inválido não deve ser enviado ao SO.
             */
            $clientDocument = '';
        }

        $items = $this->normalizeItems(
            $sourceItems,
            $totalCents
        );

        return [
            'idempotency_key' => $idempotencyKey,

            'empresa_flux_id' => $this->companyScope->id(),

            'empresa_flux_uuid' => strtolower(
                $this->companyScope->uuid()
            ),

            'origem' => $origin,

            'orcamento_flux_id' => $origin === 'orcamento'
                ? $sourceId
                : null,

            'ordem_servico_flux_id' =>
                $origin === 'ordem_servico'
                    ? $sourceId
                    : null,

            'usuario_flux_id' => $userId,

            'fornecedor_so_id' => $supplierSoId,

            'cliente' => [
                'nome' => $normalizedClientName,
                'documento' => $clientDocument,
            ],

            'descricao' => $this->limitText(
                $description,
                2000
            ),

            'valor_total' => $this->formatCents(
                $totalCents
            ),

            'itens' => $items,

            /*
             * Dados internos de rastreabilidade.
             *
             * O endpoint do SO ignora campos desconhecidos.
             */
            'referencia_flux' => [
                'numero' => $this->limitText(
                    $sourceNumber,
                    50
                ),
                'empresa_nome' => $this->limitText(
                    $this->companyScope->name(),
                    180
                ),
            ],
        ];
    }

    /**
     * Normaliza os itens garantindo que o total enviado seja
     * exatamente igual ao total aprovado.
     *
     * O SO atual não possui campos próprios para desconto e acréscimo
     * por item. Por isso cada linha é enviada como um lote financeiro,
     * mantendo a descrição e a quantidade original no texto.
     *
     * @param array<int, array<string, mixed>> $sourceItems
     *
     * @return array<int, array{
     *     descricao: string,
     *     quantidade: string,
     *     valor_unitario: string
     * }>
     */
    private function normalizeItems(
        array $sourceItems,
        int $approvedTotalCents
    ): array {
        $weightedItems = [];

        foreach ($sourceItems as $sourceItem) {
            $description = $this->requiredText(
                (string) (
                    $sourceItem['descricao']
                    ?? ''
                ),
                255,
                'Descrição do item'
            );

            $subtotalCents = $this->moneyToCents(
                (string) (
                    $sourceItem['subtotal']
                    ?? '0'
                ),
                'Subtotal do item',
                true
            );

            if ($subtotalCents <= 0) {
                continue;
            }

            $type = trim(
                (string) (
                    $sourceItem['tipo']
                    ?? 'outro'
                )
            );

            $typeLabel = match ($type) {
                'servico' => 'Serviço',
                'produto' => 'Produto',
                default => 'Item',
            };

            $quantity = trim(
                (string) (
                    $sourceItem['quantidade']
                    ?? '1'
                )
            );

            $unit = trim(
                (string) (
                    $sourceItem['unidade']
                    ?? 'un'
                )
            );

            $itemLabel = $typeLabel
                . ': '
                . $description
                . ' | Qtd. original: '
                . $quantity
                . ' '
                . $unit;

            $weightedItems[] = [
                'description' => $this->limitText(
                    $itemLabel,
                    255
                ),
                'weight' => $subtotalCents,
            ];
        }

        if ($weightedItems === []) {
            throw new InvalidArgumentException(
                'Não há itens com valor positivo para enviar ao SO.'
            );
        }

        if (
            count($weightedItems)
            > self::MAX_ITEMS
        ) {
            throw new InvalidArgumentException(
                'A aquisição excede o limite de 200 itens.'
            );
        }

        $itemCount = count(
            $weightedItems
        );

        if ($approvedTotalCents < $itemCount) {
            throw new InvalidArgumentException(
                'O valor total é insuficiente para distribuir entre os itens.'
            );
        }

        $weightTotal = array_sum(
            array_column(
                $weightedItems,
                'weight'
            )
        );

        if ($weightTotal <= 0) {
            throw new InvalidArgumentException(
                'A soma dos itens é inválida.'
            );
        }

        /*
         * Reservamos um centavo por item e distribuímos o restante
         * proporcionalmente. O último item absorve qualquer diferença
         * de arredondamento.
         */
        $distributable = $approvedTotalCents
            - $itemCount;

        $allocated = 0;

        $normalized = [];

        $lastIndex = $itemCount - 1;

        foreach (
            $weightedItems
            as $index => $weightedItem
        ) {
            if ($index === $lastIndex) {
                $lineCents = $approvedTotalCents
                    - $allocated;
            } else {
                $extra = intdiv(
                    $distributable
                    * (int) $weightedItem['weight'],
                    $weightTotal
                );

                $lineCents = 1 + $extra;

                $allocated += $lineCents;
            }

            if ($lineCents <= 0) {
                throw new RuntimeException(
                    'Não foi possível distribuir o valor da aquisição.'
                );
            }

            $normalized[] = [
                'descricao' => (string) $weightedItem[
                    'description'
                ],

                /*
                 * Quantidade 1 representa o lote financeiro da linha.
                 * A quantidade real permanece descrita no texto.
                 */
                'quantidade' => '1.00',

                'valor_unitario' => $this->formatCents(
                    $lineCents
                ),
            ];
        }

        $calculatedTotal = 0;

        foreach ($normalized as $item) {
            $calculatedTotal += $this->moneyToCents(
                $item['valor_unitario'],
                'Valor normalizado'
            );
        }

        if ($calculatedTotal !== $approvedTotalCents) {
            throw new RuntimeException(
                'O total normalizado não corresponde ao valor aprovado.'
            );
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $budget
     */
    private function budgetDescription(
        array $budget
    ): string {
        $number = (string) (
            $budget['numero']
            ?: sprintf(
                'ORC-%06d',
                (int) $budget['id']
            )
        );

        $parts = [
            'Aquisição originada do orçamento '
                . $number,

            'Cliente: '
                . (string) $budget['cliente_nome'],
        ];

        $notes = $this->cleanText(
            (string) (
                $budget['observacoes']
                ?? ''
            )
        );

        if ($notes !== '') {
            $parts[] = $notes;
        }

        return implode(
            ' — ',
            $parts
        );
    }

    /**
     * @param array<string, mixed> $order
     */
    private function serviceOrderDescription(
        array $order
    ): string {
        $number = (string) (
            $order['numero']
            ?: sprintf(
                'OS-%06d',
                (int) $order['id']
            )
        );

        $parts = [
            'Aquisição originada da ordem de serviço '
                . $number,

            'Cliente: '
                . (string) $order['cliente_nome'],
        ];

        foreach (
            [
                'problema_relatado',
                'problema_identificado',
                'diagnostico',
                'solucao',
                'observacoes',
            ]
            as $field
        ) {
            $value = $this->cleanText(
                (string) (
                    $order[$field]
                    ?? ''
                )
            );

            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode(
            ' — ',
            array_values(
                array_unique(
                    $parts
                )
            )
        );
    }

    private function budgetIdempotencyKey(
        int $budgetId
    ): string {
        return hash(
            'sha256',
            implode(
                ':',
                [
                    'fluxempresa',
                    'empresa',
                    $this->companyScope->id(),
                    'orcamento',
                    $budgetId,
                ]
            )
        );
    }

    private function serviceOrderIdempotencyKey(
        int $serviceOrderId
    ): string {
        return hash(
            'sha256',
            implode(
                ':',
                [
                    'fluxempresa',
                    'empresa',
                    $this->companyScope->id(),
                    'os',
                    $serviceOrderId,
                ]
            )
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodePayload(
        array $payload
    ): string {
        try {
            $snapshot = json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Não foi possível preparar o payload da integração.',
                0,
                $exception
            );
        }

        if (
            strlen($snapshot)
            > self::MAX_PAYLOAD_BYTES
        ) {
            throw new InvalidArgumentException(
                'O payload da integração excede o limite permitido.'
            );
        }

        return $snapshot;
    }

    private function moneyToCents(
        string $value,
        string $field,
        bool $allowZero = false
    ): int {
        $value = trim(
            str_replace(
                ',',
                '.',
                $value
            )
        );

        if (
            preg_match(
                '/^\d{1,13}(?:\.\d{1,2})?$/D',
                $value
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                $field . ' inválido.'
            );
        }

        [$integer, $decimal] = array_pad(
            explode(
                '.',
                $value,
                2
            ),
            2,
            ''
        );

        $decimal = str_pad(
            $decimal,
            2,
            '0',
            STR_PAD_RIGHT
        );

        $cents = (
            (int) $integer
            * 100
        ) + (int) $decimal;

        if (
            !$allowZero
            && $cents <= 0
        ) {
            throw new InvalidArgumentException(
                $field . ' precisa ser maior que zero.'
            );
        }

        return $cents;
    }

    private function formatCents(
        int $cents
    ): string {
        return intdiv(
            $cents,
            100
        )
            . '.'
            . str_pad(
                (string) ($cents % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }

    private function requiredText(
        string $value,
        int $maximumBytes,
        string $field
    ): string {
        $value = $this->cleanText(
            $value
        );

        if ($value === '') {
            throw new InvalidArgumentException(
                $field . ' é obrigatório.'
            );
        }

        return $this->limitText(
            $value,
            $maximumBytes
        );
    }

    private function cleanText(
        string $value
    ): string {
        $value = strip_tags(
            $value
        );

        $value = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u',
            ' ',
            $value
        ) ?? '';

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? '';

        return trim(
            $value
        );
    }

    private function limitText(
        string $value,
        int $maximumBytes
    ): string {
        $value = $this->cleanText(
            $value
        );

        if (
            strlen($value)
            <= $maximumBytes
        ) {
            return $value;
        }

        if (function_exists('mb_strcut')) {
            return rtrim(
                mb_strcut(
                    $value,
                    0,
                    $maximumBytes,
                    'UTF-8'
                )
            );
        }

        return rtrim(
            substr(
                $value,
                0,
                $maximumBytes
            )
        );
    }

    private function assertActor(
        int $userId
    ): void {
        $this->assertPositiveId(
            $userId,
            'Usuário'
        );

        if (
            $userId
            !== $this->companyScope->actorUserId()
        ) {
            throw new InvalidArgumentException(
                'O usuário informado não corresponde ao usuário autenticado.'
            );
        }
    }

    private function assertPositiveId(
        int $id,
        string $field
    ): void {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                $field . ' inválido.'
            );
        }
    }

    /**
     * Respeita uma transação já aberta pelo fluxo de aprovação.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function transactional(
        callable $callback
    ): mixed {
        $ownsTransaction = !$this
            ->connection
            ->inTransaction();

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $result = $callback();

            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if (
                $ownsTransaction
                && $this->connection->inTransaction()
            ) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}