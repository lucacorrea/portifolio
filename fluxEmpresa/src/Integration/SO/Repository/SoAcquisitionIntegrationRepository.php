<?php

declare(strict_types=1);

namespace App\Integration\SO\Repository;

use App\Company\DTO\CompanyScope;
use InvalidArgumentException;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class SoAcquisitionIntegrationRepository
{
    public const EVENT_CREATE_ACQUISITION =
        'so.aquisicao.criar';

    public const DIRECTION_FLUX_TO_SO =
        'flux_para_so';

    public const DIRECTION_SO_TO_FLUX =
        'so_para_flux';

    public const ORIGIN_BUDGET =
        'orcamento_flux';

    public const ORIGIN_SERVICE_ORDER =
        'os_flux';

    public const ORIGIN_SO_ACQUISITION =
        'aquisicao_so';

    private const MAX_SNAPSHOT_BYTES = 1048576;

    public function __construct(
        private readonly PDO $connection,
        private readonly CompanyScope $companyScope
    ) {
    }

    /**
     * Registra a criação de aquisição originada por orçamento.
     *
     * Cria, na mesma transação:
     *
     * - integracao_so_aquisicoes;
     * - integracao_outbox.
     *
     * @return array<string, mixed>
     */
    public function queueBudgetCreation(
        int $budgetId,
        int $supplierSoId,
        int $userId,
        string $idempotencyKey,
        string $payloadHash,
        string $payloadSnapshot,
        int $priority = 5
    ): array {
        $this->assertPositiveId(
            $budgetId,
            'Orçamento'
        );

        return $this->queueOutboundCreation(
            origin: self::ORIGIN_BUDGET,
            budgetId: $budgetId,
            serviceOrderId: null,
            supplierSoId: $supplierSoId,
            userId: $userId,
            idempotencyKey: $idempotencyKey,
            payloadHash: $payloadHash,
            payloadSnapshot: $payloadSnapshot,
            priority: $priority
        );
    }

    /**
     * Registra a criação de aquisição originada por OS direta.
     *
     * @return array<string, mixed>
     */
    public function queueServiceOrderCreation(
        int $serviceOrderId,
        int $supplierSoId,
        int $userId,
        string $idempotencyKey,
        string $payloadHash,
        string $payloadSnapshot,
        int $priority = 5
    ): array {
        $this->assertPositiveId(
            $serviceOrderId,
            'Ordem de serviço'
        );

        return $this->queueOutboundCreation(
            origin: self::ORIGIN_SERVICE_ORDER,
            budgetId: null,
            serviceOrderId: $serviceOrderId,
            supplierSoId: $supplierSoId,
            userId: $userId,
            idempotencyKey: $idempotencyKey,
            payloadHash: $payloadHash,
            payloadSnapshot: $payloadSnapshot,
            priority: $priority
        );
    }

    /**
     * Vincula a OS gerada por um orçamento à integração já existente.
     *
     * A OS herdará a aquisição criada pela aprovação do orçamento,
     * impedindo uma segunda aquisição no SO.
     */
    public function attachServiceOrderToBudgetIntegration(
        int $budgetId,
        int $serviceOrderId
    ): void {
        $this->assertPositiveId(
            $budgetId,
            'Orçamento'
        );

        $this->assertPositiveId(
            $serviceOrderId,
            'Ordem de serviço'
        );

        $this->transactional(
            function () use (
                $budgetId,
                $serviceOrderId
            ): void {
                $integration = $this->findByBudgetForUpdate(
                    $budgetId
                );

                if ($integration === null) {
                    throw new RuntimeException(
                        'A integração do orçamento com o SO não foi encontrada.'
                    );
                }

                $currentServiceOrderId = isset(
                    $integration['ordem_servico_id']
                )
                    ? (int) $integration['ordem_servico_id']
                    : 0;

                if ($currentServiceOrderId === $serviceOrderId) {
                    return;
                }

                if ($currentServiceOrderId > 0) {
                    throw new RuntimeException(
                        'A integração já está vinculada a outra ordem de serviço.'
                    );
                }

                $serviceOrderIntegration =
                    $this->findByServiceOrderForUpdate(
                        $serviceOrderId
                    );

                if (
                    $serviceOrderIntegration !== null
                    && (int) $serviceOrderIntegration['id']
                        !== (int) $integration['id']
                ) {
                    throw new RuntimeException(
                        'A ordem de serviço já possui outra integração com o SO.'
                    );
                }

                $statement = $this->connection->prepare(
                    'UPDATE integracao_so_aquisicoes
                        SET ordem_servico_id = :ordem_servico_id,
                            atualizado_em = CURRENT_TIMESTAMP
                      WHERE id = :id
                        AND empresa_id = :empresa_id
                        AND ordem_servico_id IS NULL'
                );

                $statement->execute([
                    'ordem_servico_id' => $serviceOrderId,
                    'id' => (int) $integration['id'],
                    'empresa_id' => $this->companyScope->id(),
                ]);

                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Não foi possível vincular a ordem de serviço à aquisição.'
                    );
                }
            }
        );
    }

    /**
     * Registra uma aquisição que já nasceu no SO e foi convertida
     * em ordem de serviço no Flux.
     *
     * Essa integração já nasce sincronizada e não gera outbox.
     *
     * @return array<string, mixed>
     */
    public function registerImportedAcquisition(
        int $serviceOrderId,
        int $supplierSoId,
        int $acquisitionSoId,
        string $acquisitionNumber,
        ?string $deliveryCode,
        string $statusSo,
        int $userId,
        string $idempotencyKey,
        string $payloadHash,
        string $payloadSnapshot
    ): array {
        $this->assertPositiveId(
            $serviceOrderId,
            'Ordem de serviço'
        );

        $this->assertPositiveId(
            $supplierSoId,
            'Fornecedor do SO'
        );

        $this->assertPositiveId(
            $acquisitionSoId,
            'Aquisição do SO'
        );

        $this->assertPositiveId(
            $userId,
            'Usuário'
        );

        $acquisitionNumber = $this->normalizeRequiredString(
            $acquisitionNumber,
            50,
            'Número da aquisição'
        );

        $statusSo = $this->normalizeRequiredString(
            $statusSo,
            80,
            'Status da aquisição'
        );

        $deliveryCode = $this->normalizeOptionalString(
            $deliveryCode,
            50
        );

        $this->assertHash(
            $idempotencyKey,
            'Chave de idempotência'
        );

        $this->assertHash(
            $payloadHash,
            'Hash do payload'
        );

        $this->assertJsonSnapshot(
            $payloadSnapshot
        );

        return $this->transactional(
            function () use (
                $serviceOrderId,
                $supplierSoId,
                $acquisitionSoId,
                $acquisitionNumber,
                $deliveryCode,
                $statusSo,
                $userId,
                $idempotencyKey,
                $payloadHash,
                $payloadSnapshot
            ): array {
                $existing = $this->findImportedExistingForUpdate(
                    $serviceOrderId,
                    $acquisitionSoId,
                    $idempotencyKey
                );

                if ($existing !== null) {
                    $this->assertImportedCompatibility(
                        $existing,
                        $serviceOrderId,
                        $supplierSoId,
                        $acquisitionSoId,
                        $idempotencyKey,
                        $payloadHash
                    );

                    return $this->findDetailed(
                        (int) $existing['id']
                    ) ?? $existing;
                }

                try {
                    $statement = $this->connection->prepare(
                        "INSERT INTO integracao_so_aquisicoes (
                            empresa_id,
                            orcamento_id,
                            ordem_servico_id,
                            fornecedor_so_id,
                            aquisicao_so_id,
                            numero_aquisicao_so,
                            codigo_entrega_so,
                            direcao,
                            origem,
                            status_integracao,
                            status_so,
                            chave_idempotencia,
                            payload_hash,
                            payload_snapshot,
                            resposta_snapshot,
                            tentativas,
                            criado_por,
                            criado_em,
                            atualizado_em,
                            sincronizado_em
                        ) VALUES (
                            :empresa_id,
                            NULL,
                            :ordem_servico_id,
                            :fornecedor_so_id,
                            :aquisicao_so_id,
                            :numero_aquisicao_so,
                            :codigo_entrega_so,
                            'so_para_flux',
                            'aquisicao_so',
                            'sincronizado',
                            :status_so,
                            :chave_idempotencia,
                            :payload_hash,
                            :payload_snapshot,
                            NULL,
                            0,
                            :criado_por,
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        )"
                    );

                    $statement->execute([
                        'empresa_id' => $this->companyScope->id(),
                        'ordem_servico_id' => $serviceOrderId,
                        'fornecedor_so_id' => $supplierSoId,
                        'aquisicao_so_id' => $acquisitionSoId,
                        'numero_aquisicao_so' => $acquisitionNumber,
                        'codigo_entrega_so' => $deliveryCode,
                        'status_so' => $statusSo,
                        'chave_idempotencia' => strtolower(
                            $idempotencyKey
                        ),
                        'payload_hash' => strtolower(
                            $payloadHash
                        ),
                        'payload_snapshot' => $payloadSnapshot,
                        'criado_por' => $userId,
                    ]);

                    $integrationId = (int) $this
                        ->connection
                        ->lastInsertId();
                } catch (PDOException $exception) {
                    if (!$this->isDuplicateKey($exception)) {
                        throw $exception;
                    }

                    $existing = $this->findImportedExistingForUpdate(
                        $serviceOrderId,
                        $acquisitionSoId,
                        $idempotencyKey
                    );

                    if ($existing === null) {
                        throw $exception;
                    }

                    $this->assertImportedCompatibility(
                        $existing,
                        $serviceOrderId,
                        $supplierSoId,
                        $acquisitionSoId,
                        $idempotencyKey,
                        $payloadHash
                    );

                    $integrationId = (int) $existing['id'];
                }

                $integration = $this->findDetailed(
                    $integrationId
                );

                if ($integration === null) {
                    throw new RuntimeException(
                        'A integração criada não pôde ser localizada.'
                    );
                }

                return $integration;
            }
        );
    }

    /**
     * Reserva o próximo evento disponível da outbox.
     *
     * O workerToken deve ser um SHA-256 aleatório.
     *
     * @return array<string, mixed>|null
     */
    public function claimNextOutbox(
        string $workerToken,
        int $leaseSeconds = 120
    ): ?array {
        $this->assertHash(
            $workerToken,
            'Token do worker'
        );

        $leaseSeconds = max(
            30,
            min(
                600,
                $leaseSeconds
            )
        );

        $blockedUntil = date(
            'Y-m-d H:i:s',
            time() + $leaseSeconds
        );

        return $this->transactional(
            function () use (
                $workerToken,
                $blockedUntil
            ): ?array {
                $statement = $this->connection->prepare(
                    "UPDATE integracao_outbox
                        SET status = 'processando',
                            tentativas = tentativas + 1,
                            worker_token = :worker_token,
                            bloqueado_ate = :bloqueado_ate,
                            atualizado_em = CURRENT_TIMESTAMP
                      WHERE id = (
                            SELECT candidato.id
                              FROM (
                                    SELECT id
                                      FROM integracao_outbox
                                     WHERE empresa_id = :empresa_candidata
                                       AND evento = :evento
                                       AND disponivel_em <= CURRENT_TIMESTAMP
                                       AND tentativas < max_tentativas
                                       AND (
                                            status IN ('pendente', 'falha')
                                            OR (
                                                status = 'processando'
                                                AND bloqueado_ate IS NOT NULL
                                                AND bloqueado_ate < CURRENT_TIMESTAMP
                                            )
                                       )
                                     ORDER BY prioridade ASC, id ASC
                                     LIMIT 1
                              ) AS candidato
                      )
                        AND empresa_id = :empresa_atualizacao
                        AND tentativas < max_tentativas
                        AND (
                            status IN ('pendente', 'falha')
                            OR (
                                status = 'processando'
                                AND bloqueado_ate IS NOT NULL
                                AND bloqueado_ate < CURRENT_TIMESTAMP
                            )
                        )"
                );

                $statement->execute([
                    'worker_token' => strtolower(
                        $workerToken
                    ),
                    'bloqueado_ate' => $blockedUntil,
                    'empresa_candidata' => $this->companyScope->id(),
                    'evento' => self::EVENT_CREATE_ACQUISITION,
                    'empresa_atualizacao' => $this->companyScope->id(),
                ]);

                if ($statement->rowCount() !== 1) {
                    return null;
                }

                $claim = $this->connection->prepare(
                    'SELECT
                        o.id AS outbox_id,
                        o.empresa_id,
                        o.integracao_id,
                        o.evento,
                        o.chave_idempotencia,
                        o.payload,
                        o.status AS outbox_status,
                        o.prioridade,
                        o.tentativas,
                        o.max_tentativas,
                        o.disponivel_em,
                        o.bloqueado_ate,
                        o.worker_token,
                        i.orcamento_id,
                        i.ordem_servico_id,
                        i.fornecedor_so_id,
                        i.origem,
                        i.direcao,
                        i.status_integracao
                     FROM integracao_outbox AS o
                     INNER JOIN integracao_so_aquisicoes AS i
                        ON i.id = o.integracao_id
                       AND i.empresa_id = o.empresa_id
                     WHERE o.empresa_id = :empresa_id
                       AND o.worker_token = :worker_token
                       AND o.status = \'processando\'
                     ORDER BY o.id DESC
                     LIMIT 1'
                );

                $claim->execute([
                    'empresa_id' => $this->companyScope->id(),
                    'worker_token' => strtolower(
                        $workerToken
                    ),
                ]);

                $row = $claim->fetch();

                if (!is_array($row)) {
                    throw new RuntimeException(
                        'O evento reservado não pôde ser localizado.'
                    );
                }

                $updateIntegration = $this->connection->prepare(
                    "UPDATE integracao_so_aquisicoes
                        SET status_integracao = 'processando',
                            tentativas = :tentativas,
                            atualizado_em = CURRENT_TIMESTAMP
                      WHERE id = :integracao_id
                        AND empresa_id = :empresa_id
                        AND status_integracao IN (
                            'pendente',
                            'falha',
                            'processando'
                        )"
                );

                $updateIntegration->execute([
                    'tentativas' => (int) $row['tentativas'],
                    'integracao_id' => (int) $row['integracao_id'],
                    'empresa_id' => $this->companyScope->id(),
                ]);

                return $row;
            }
        );
    }

    /**
     * Finaliza com sucesso um evento reservado pelo worker.
     */
    public function markSynchronized(
        int $outboxId,
        string $workerToken,
        int $acquisitionSoId,
        string $acquisitionNumber,
        ?string $deliveryCode,
        string $statusSo,
        string $responseSnapshot
    ): void {
        $this->assertPositiveId(
            $outboxId,
            'Evento da outbox'
        );

        $this->assertPositiveId(
            $acquisitionSoId,
            'Aquisição do SO'
        );

        $this->assertHash(
            $workerToken,
            'Token do worker'
        );

        $acquisitionNumber = $this->normalizeRequiredString(
            $acquisitionNumber,
            50,
            'Número da aquisição'
        );

        $deliveryCode = $this->normalizeOptionalString(
            $deliveryCode,
            50
        );

        $statusSo = $this->normalizeRequiredString(
            $statusSo,
            80,
            'Status da aquisição'
        );

        $this->assertJsonSnapshot(
            $responseSnapshot
        );

        $this->transactional(
            function () use (
                $outboxId,
                $workerToken,
                $acquisitionSoId,
                $acquisitionNumber,
                $deliveryCode,
                $statusSo,
                $responseSnapshot
            ): void {
                $outbox = $this->lockOwnedOutbox(
                    $outboxId,
                    $workerToken
                );

                $integrationId = (int) $outbox['integracao_id'];

                $integrationStatement = $this->connection->prepare(
                    "UPDATE integracao_so_aquisicoes
                        SET aquisicao_so_id = :aquisicao_so_id,
                            numero_aquisicao_so = :numero_aquisicao_so,
                            codigo_entrega_so = :codigo_entrega_so,
                            status_integracao = 'sincronizado',
                            status_so = :status_so,
                            resposta_snapshot = :resposta_snapshot,
                            tentativas = :tentativas,
                            ultimo_erro_codigo = NULL,
                            ultimo_erro_mensagem = NULL,
                            ultimo_erro_em = NULL,
                            sincronizado_em = CURRENT_TIMESTAMP,
                            cancelado_em = NULL,
                            atualizado_em = CURRENT_TIMESTAMP
                      WHERE id = :integracao_id
                        AND empresa_id = :empresa_id"
                );

                $integrationStatement->execute([
                    'aquisicao_so_id' => $acquisitionSoId,
                    'numero_aquisicao_so' => $acquisitionNumber,
                    'codigo_entrega_so' => $deliveryCode,
                    'status_so' => $statusSo,
                    'resposta_snapshot' => $responseSnapshot,
                    'tentativas' => (int) $outbox['tentativas'],
                    'integracao_id' => $integrationId,
                    'empresa_id' => $this->companyScope->id(),
                ]);

                if ($integrationStatement->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Não foi possível atualizar a integração com o SO.'
                    );
                }

                $outboxStatement = $this->connection->prepare(
                    "UPDATE integracao_outbox
                        SET status = 'processado',
                            bloqueado_ate = NULL,
                            worker_token = NULL,
                            ultimo_erro_codigo = NULL,
                            ultimo_erro_mensagem = NULL,
                            ultimo_erro_em = NULL,
                            processado_em = CURRENT_TIMESTAMP,
                            atualizado_em = CURRENT_TIMESTAMP
                      WHERE id = :id
                        AND empresa_id = :empresa_id
                        AND worker_token = :worker_token
                        AND status = 'processando'"
                );

                $outboxStatement->execute([
                    'id' => $outboxId,
                    'empresa_id' => $this->companyScope->id(),
                    'worker_token' => strtolower(
                        $workerToken
                    ),
                ]);

                if ($outboxStatement->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Não foi possível finalizar o evento da integração.'
                    );
                }
            }
        );
    }

    /**
     * Registra uma falha da comunicação com o SO.
     *
     * A próxima tentativa recebe backoff exponencial.
     */
    public function markFailed(
        int $outboxId,
        string $workerToken,
        string $errorCode,
        string $errorMessage
    ): void {
        $this->assertPositiveId(
            $outboxId,
            'Evento da outbox'
        );

        $this->assertHash(
            $workerToken,
            'Token do worker'
        );

        $errorCode = $this->normalizeRequiredString(
            $errorCode,
            100,
            'Código do erro'
        );

        $errorMessage = $this->normalizeRequiredString(
            $errorMessage,
            1000,
            'Mensagem do erro'
        );

        $this->transactional(
            function () use (
                $outboxId,
                $workerToken,
                $errorCode,
                $errorMessage
            ): void {
                $outbox = $this->lockOwnedOutbox(
                    $outboxId,
                    $workerToken
                );

                $attempts = (int) $outbox['tentativas'];

                $maximumAttempts = (int) $outbox['max_tentativas'];

                $retryAvailable = $attempts < $maximumAttempts;

                $backoffSeconds = $retryAvailable
                    ? $this->calculateBackoff(
                        $attempts
                    )
                    : 86400;

                $availableAt = date(
                    'Y-m-d H:i:s',
                    time() + $backoffSeconds
                );

                $outboxStatement = $this->connection->prepare(
                    "UPDATE integracao_outbox
                        SET status = 'falha',
                            disponivel_em = :disponivel_em,
                            bloqueado_ate = NULL,
                            worker_token = NULL,
                            ultimo_erro_codigo = :erro_codigo,
                            ultimo_erro_mensagem = :erro_mensagem,
                            ultimo_erro_em = CURRENT_TIMESTAMP,
                            atualizado_em = CURRENT_TIMESTAMP
                      WHERE id = :id
                        AND empresa_id = :empresa_id
                        AND worker_token = :worker_token
                        AND status = 'processando'"
                );

                $outboxStatement->execute([
                    'disponivel_em' => $availableAt,
                    'erro_codigo' => $errorCode,
                    'erro_mensagem' => $errorMessage,
                    'id' => $outboxId,
                    'empresa_id' => $this->companyScope->id(),
                    'worker_token' => strtolower(
                        $workerToken
                    ),
                ]);

                if ($outboxStatement->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Não foi possível registrar a falha da outbox.'
                    );
                }

                $integrationStatement = $this->connection->prepare(
                    "UPDATE integracao_so_aquisicoes
                        SET status_integracao = 'falha',
                            tentativas = :tentativas,
                            ultimo_erro_codigo = :erro_codigo,
                            ultimo_erro_mensagem = :erro_mensagem,
                            ultimo_erro_em = CURRENT_TIMESTAMP,
                            atualizado_em = CURRENT_TIMESTAMP
                      WHERE id = :integracao_id
                        AND empresa_id = :empresa_id"
                );

                $integrationStatement->execute([
                    'tentativas' => $attempts,
                    'erro_codigo' => $errorCode,
                    'erro_mensagem' => $errorMessage,
                    'integracao_id' => (int) $outbox['integracao_id'],
                    'empresa_id' => $this->companyScope->id(),
                ]);
            }
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(
        int $integrationId
    ): ?array {
        $this->assertPositiveId(
            $integrationId,
            'Integração'
        );

        return $this->findDetailed(
            $integrationId
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByBudget(
        int $budgetId
    ): ?array {
        $this->assertPositiveId(
            $budgetId,
            'Orçamento'
        );

        $statement = $this->connection->prepare(
            'SELECT id
               FROM integracao_so_aquisicoes
              WHERE empresa_id = :empresa_id
                AND orcamento_id = :orcamento_id
              LIMIT 1'
        );

        $statement->execute([
            'empresa_id' => $this->companyScope->id(),
            'orcamento_id' => $budgetId,
        ]);

        $integrationId = $statement->fetchColumn();

        return $integrationId === false
            ? null
            : $this->findDetailed(
                (int) $integrationId
            );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByServiceOrder(
        int $serviceOrderId
    ): ?array {
        $this->assertPositiveId(
            $serviceOrderId,
            'Ordem de serviço'
        );

        $statement = $this->connection->prepare(
            'SELECT id
               FROM integracao_so_aquisicoes
              WHERE empresa_id = :empresa_id
                AND ordem_servico_id = :ordem_servico_id
              LIMIT 1'
        );

        $statement->execute([
            'empresa_id' => $this->companyScope->id(),
            'ordem_servico_id' => $serviceOrderId,
        ]);

        $integrationId = $statement->fetchColumn();

        return $integrationId === false
            ? null
            : $this->findDetailed(
                (int) $integrationId
            );
    }

    /**
     * @param int[] $serviceOrderIds
     *
     * @return int[]
     */
    public function findImportedServiceOrderIds(
        array $serviceOrderIds
    ): array {
        $serviceOrderIds = array_values(array_unique(array_map(
            static fn(mixed $serviceOrderId): int => (int) $serviceOrderId,
            $serviceOrderIds
        )));
        $serviceOrderIds = array_values(array_filter(
            $serviceOrderIds,
            static fn(int $serviceOrderId): bool => $serviceOrderId > 0
        ));

        if ($serviceOrderIds === []) {
            return [];
        }

        $placeholders = [];
        $parameters = [
            'empresa_id' => $this->companyScope->id(),
        ];
        foreach ($serviceOrderIds as $index => $serviceOrderId) {
            $parameter = 'ordem_servico_id_' . $index;
            $placeholders[] = ':' . $parameter;
            $parameters[$parameter] = $serviceOrderId;
        }

        $statement = $this->connection->prepare(
            "SELECT ordem_servico_id
               FROM integracao_so_aquisicoes
              WHERE empresa_id = :empresa_id
                AND ordem_servico_id IN (" . implode(', ', $placeholders) . ")
                AND direcao = 'so_para_flux'
                AND origem = 'aquisicao_so'"
        );
        $statement->execute($parameters);

        return array_map(
            static fn(mixed $serviceOrderId): int => (int) $serviceOrderId,
            $statement->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByExternalAcquisition(
        int $acquisitionSoId
    ): ?array {
        $this->assertPositiveId(
            $acquisitionSoId,
            'Aquisição do SO'
        );

        $statement = $this->connection->prepare(
            'SELECT id
               FROM integracao_so_aquisicoes
              WHERE empresa_id = :empresa_id
                AND aquisicao_so_id = :aquisicao_so_id
              LIMIT 1'
        );

        $statement->execute([
            'empresa_id' => $this->companyScope->id(),
            'aquisicao_so_id' => $acquisitionSoId,
        ]);

        $integrationId = $statement->fetchColumn();

        return $integrationId === false
            ? null
            : $this->findDetailed(
                (int) $integrationId
            );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdempotencyKey(
        string $idempotencyKey
    ): ?array {
        $this->assertHash(
            $idempotencyKey,
            'Chave de idempotência'
        );

        $statement = $this->connection->prepare(
            'SELECT id
               FROM integracao_so_aquisicoes
              WHERE empresa_id = :empresa_id
                AND chave_idempotencia = :chave_idempotencia
              LIMIT 1'
        );

        $statement->execute([
            'empresa_id' => $this->companyScope->id(),
            'chave_idempotencia' => strtolower(
                $idempotencyKey
            ),
        ]);

        $integrationId = $statement->fetchColumn();

        return $integrationId === false
            ? null
            : $this->findDetailed(
                (int) $integrationId
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function queueOutboundCreation(
        string $origin,
        ?int $budgetId,
        ?int $serviceOrderId,
        int $supplierSoId,
        int $userId,
        string $idempotencyKey,
        string $payloadHash,
        string $payloadSnapshot,
        int $priority
    ): array {
        if (
            !in_array(
                $origin,
                [
                    self::ORIGIN_BUDGET,
                    self::ORIGIN_SERVICE_ORDER,
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Origem da integração inválida.'
            );
        }

        $this->assertPositiveId(
            $supplierSoId,
            'Fornecedor do SO'
        );

        $this->assertPositiveId(
            $userId,
            'Usuário'
        );

        $this->assertHash(
            $idempotencyKey,
            'Chave de idempotência'
        );

        $this->assertHash(
            $payloadHash,
            'Hash do payload'
        );

        $this->assertJsonSnapshot(
            $payloadSnapshot
        );

        if (
            $priority < 1
            || $priority > 10
        ) {
            throw new InvalidArgumentException(
                'Prioridade da integração inválida.'
            );
        }

        return $this->transactional(
            function () use (
                $origin,
                $budgetId,
                $serviceOrderId,
                $supplierSoId,
                $userId,
                $idempotencyKey,
                $payloadHash,
                $payloadSnapshot,
                $priority
            ): array {
                $existing = $this->findOutboundExistingForUpdate(
                    $budgetId,
                    $serviceOrderId,
                    $idempotencyKey
                );

                if ($existing !== null) {
                    $this->assertOutboundCompatibility(
                        $existing,
                        $origin,
                        $budgetId,
                        $serviceOrderId,
                        $supplierSoId,
                        $idempotencyKey,
                        $payloadHash
                    );

                    $outboxId = $this->ensureCreateOutbox(
                        (int) $existing['id'],
                        $idempotencyKey,
                        $payloadSnapshot,
                        $priority
                    );

                    return $this->withOutboxId(
                        $this->findDetailed(
                            (int) $existing['id']
                        ) ?? $existing,
                        $outboxId
                    );
                }

                try {
                    $statement = $this->connection->prepare(
                        'INSERT INTO integracao_so_aquisicoes (
                            empresa_id,
                            orcamento_id,
                            ordem_servico_id,
                            fornecedor_so_id,
                            aquisicao_so_id,
                            numero_aquisicao_so,
                            codigo_entrega_so,
                            direcao,
                            origem,
                            status_integracao,
                            status_so,
                            chave_idempotencia,
                            payload_hash,
                            payload_snapshot,
                            resposta_snapshot,
                            tentativas,
                            criado_por,
                            criado_em,
                            atualizado_em
                        ) VALUES (
                            :empresa_id,
                            :orcamento_id,
                            :ordem_servico_id,
                            :fornecedor_so_id,
                            NULL,
                            NULL,
                            NULL,
                            :direcao,
                            :origem,
                            \'pendente\',
                            NULL,
                            :chave_idempotencia,
                            :payload_hash,
                            :payload_snapshot,
                            NULL,
                            0,
                            :criado_por,
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        )'
                    );

                    $statement->execute([
                        'empresa_id' => $this->companyScope->id(),
                        'orcamento_id' => $budgetId,
                        'ordem_servico_id' => $serviceOrderId,
                        'fornecedor_so_id' => $supplierSoId,
                        'direcao' => self::DIRECTION_FLUX_TO_SO,
                        'origem' => $origin,
                        'chave_idempotencia' => strtolower(
                            $idempotencyKey
                        ),
                        'payload_hash' => strtolower(
                            $payloadHash
                        ),
                        'payload_snapshot' => $payloadSnapshot,
                        'criado_por' => $userId,
                    ]);

                    $integrationId = (int) $this
                        ->connection
                        ->lastInsertId();
                } catch (PDOException $exception) {
                    if (!$this->isDuplicateKey($exception)) {
                        throw $exception;
                    }

                    $existing = $this->findOutboundExistingForUpdate(
                        $budgetId,
                        $serviceOrderId,
                        $idempotencyKey
                    );

                    if ($existing === null) {
                        throw $exception;
                    }

                    $this->assertOutboundCompatibility(
                        $existing,
                        $origin,
                        $budgetId,
                        $serviceOrderId,
                        $supplierSoId,
                        $idempotencyKey,
                        $payloadHash
                    );

                    $integrationId = (int) $existing['id'];
                }

                if ($integrationId <= 0) {
                    throw new RuntimeException(
                        'Não foi possível registrar a integração com o SO.'
                    );
                }

                $outboxId = $this->ensureCreateOutbox(
                    $integrationId,
                    $idempotencyKey,
                    $payloadSnapshot,
                    $priority
                );

                $integration = $this->findDetailed(
                    $integrationId
                );

                if ($integration === null) {
                    throw new RuntimeException(
                        'A integração criada não pôde ser localizada.'
                    );
                }

                return $this->withOutboxId(
                    $integration,
                    $outboxId
                );
            }
        );
    }

    private function ensureCreateOutbox(
        int $integrationId,
        string $idempotencyKey,
        string $payloadSnapshot,
        int $priority
    ): int {
        $statement = $this->connection->prepare(
            "INSERT INTO integracao_outbox (
                empresa_id,
                integracao_id,
                evento,
                chave_idempotencia,
                payload,
                status,
                prioridade,
                tentativas,
                max_tentativas,
                disponivel_em,
                criado_em,
                atualizado_em
            ) VALUES (
                :empresa_id,
                :integracao_id,
                :evento,
                :chave_idempotencia,
                :payload,
                'pendente',
                :prioridade,
                0,
                10,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                integracao_id = VALUES(integracao_id),
                empresa_id = VALUES(empresa_id),
                payload = CASE
                    WHEN status IN ('pendente', 'falha')
                    THEN VALUES(payload)
                    ELSE payload
                END,
                prioridade = LEAST(
                    prioridade,
                    VALUES(prioridade)
                ),
                atualizado_em = CURRENT_TIMESTAMP"
        );

        $statement->execute([
            'empresa_id' => $this->companyScope->id(),
            'integracao_id' => $integrationId,
            'evento' => self::EVENT_CREATE_ACQUISITION,
            'chave_idempotencia' => strtolower(
                $idempotencyKey
            ),
            'payload' => $payloadSnapshot,
            'prioridade' => $priority,
        ]);

        $outboxId = (int) $this
            ->connection
            ->lastInsertId();

        if ($outboxId > 0) {
            return $outboxId;
        }

        $findStatement = $this->connection->prepare(
            'SELECT id
               FROM integracao_outbox
              WHERE empresa_id = :empresa_id
                AND evento = :evento
                AND chave_idempotencia = :chave_idempotencia
              LIMIT 1'
        );

        $findStatement->execute([
            'empresa_id' => $this->companyScope->id(),
            'evento' => self::EVENT_CREATE_ACQUISITION,
            'chave_idempotencia' => strtolower(
                $idempotencyKey
            ),
        ]);

        $existingId = $findStatement->fetchColumn();

        if ($existingId === false) {
            throw new RuntimeException(
                'Não foi possível localizar o evento da integração.'
            );
        }

        return (int) $existingId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDetailed(
        int $integrationId
    ): ?array {
        $statement = $this->connection->prepare(
            'SELECT
                i.id,
                i.empresa_id,
                i.orcamento_id,
                i.ordem_servico_id,
                i.fornecedor_so_id,
                i.aquisicao_so_id,
                i.numero_aquisicao_so,
                i.codigo_entrega_so,
                i.direcao,
                i.origem,
                i.status_integracao,
                i.status_so,
                i.chave_idempotencia,
                i.payload_hash,
                i.tentativas,
                i.ultimo_erro_codigo,
                i.ultimo_erro_mensagem,
                i.ultimo_erro_em,
                i.criado_por,
                i.criado_em,
                i.atualizado_em,
                i.sincronizado_em,
                i.cancelado_em,
                o.id AS outbox_id,
                o.status AS outbox_status,
                o.prioridade AS outbox_prioridade,
                o.tentativas AS outbox_tentativas,
                o.max_tentativas AS outbox_max_tentativas,
                o.disponivel_em AS outbox_disponivel_em,
                o.bloqueado_ate AS outbox_bloqueado_ate,
                o.processado_em AS outbox_processado_em
             FROM integracao_so_aquisicoes AS i
             LEFT JOIN integracao_outbox AS o
                ON o.integracao_id = i.id
               AND o.empresa_id = i.empresa_id
               AND o.evento = :evento
             WHERE i.id = :id
               AND i.empresa_id = :empresa_id
             LIMIT 1'
        );

        $statement->execute([
            'evento' => self::EVENT_CREATE_ACQUISITION,
            'id' => $integrationId,
            'empresa_id' => $this->companyScope->id(),
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOutboundExistingForUpdate(
        ?int $budgetId,
        ?int $serviceOrderId,
        string $idempotencyKey
    ): ?array {
        $conditions = [
            'chave_idempotencia = :chave_idempotencia',
        ];

        $parameters = [
            'empresa_id' => $this->companyScope->id(),
            'chave_idempotencia' => strtolower(
                $idempotencyKey
            ),
        ];

        if ($budgetId !== null) {
            $conditions[] = 'orcamento_id = :orcamento_id';
            $parameters['orcamento_id'] = $budgetId;
        }

        if ($serviceOrderId !== null) {
            $conditions[] =
                'ordem_servico_id = :ordem_servico_id';

            $parameters['ordem_servico_id'] =
                $serviceOrderId;
        }

        $statement = $this->connection->prepare(
            'SELECT *
               FROM integracao_so_aquisicoes
              WHERE empresa_id = :empresa_id
                AND (
                    ' . implode(
                        ' OR ',
                        $conditions
                    ) . '
                )
              ORDER BY id ASC
              LIMIT 1
              FOR UPDATE'
        );

        $statement->execute(
            $parameters
        );

        $row = $statement->fetch();

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findImportedExistingForUpdate(
        int $serviceOrderId,
        int $acquisitionSoId,
        string $idempotencyKey
    ): ?array {
        $statement = $this->connection->prepare(
            'SELECT *
               FROM integracao_so_aquisicoes
              WHERE empresa_id = :empresa_id
                AND (
                    ordem_servico_id = :ordem_servico_id
                    OR aquisicao_so_id = :aquisicao_so_id
                    OR chave_idempotencia = :chave_idempotencia
                )
              ORDER BY id ASC
              LIMIT 1
              FOR UPDATE'
        );

        $statement->execute([
            'empresa_id' => $this->companyScope->id(),
            'ordem_servico_id' => $serviceOrderId,
            'aquisicao_so_id' => $acquisitionSoId,
            'chave_idempotencia' => strtolower(
                $idempotencyKey
            ),
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByBudgetForUpdate(
        int $budgetId
    ): ?array {
        $statement = $this->connection->prepare(
            'SELECT *
               FROM integracao_so_aquisicoes
              WHERE empresa_id = :empresa_id
                AND orcamento_id = :orcamento_id
              LIMIT 1
              FOR UPDATE'
        );

        $statement->execute([
            'empresa_id' => $this->companyScope->id(),
            'orcamento_id' => $budgetId,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByServiceOrderForUpdate(
        int $serviceOrderId
    ): ?array {
        $statement = $this->connection->prepare(
            'SELECT *
               FROM integracao_so_aquisicoes
              WHERE empresa_id = :empresa_id
                AND ordem_servico_id = :ordem_servico_id
              LIMIT 1
              FOR UPDATE'
        );

        $statement->execute([
            'empresa_id' => $this->companyScope->id(),
            'ordem_servico_id' => $serviceOrderId,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function lockOwnedOutbox(
        int $outboxId,
        string $workerToken
    ): array {
        $statement = $this->connection->prepare(
            'SELECT
                id,
                empresa_id,
                integracao_id,
                status,
                tentativas,
                max_tentativas,
                worker_token
             FROM integracao_outbox
             WHERE id = :id
               AND empresa_id = :empresa_id
               AND worker_token = :worker_token
               AND status = \'processando\'
             LIMIT 1
             FOR UPDATE'
        );

        $statement->execute([
            'id' => $outboxId,
            'empresa_id' => $this->companyScope->id(),
            'worker_token' => strtolower(
                $workerToken
            ),
        ]);

        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new RuntimeException(
                'O evento não pertence ao worker atual ou já foi processado.'
            );
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $existing
     */
    private function assertOutboundCompatibility(
        array $existing,
        string $origin,
        ?int $budgetId,
        ?int $serviceOrderId,
        int $supplierSoId,
        string $idempotencyKey,
        string $payloadHash
    ): void {
        $compatible = (
            (int) $existing['empresa_id']
                === $this->companyScope->id()
            && (string) $existing['direcao']
                === self::DIRECTION_FLUX_TO_SO
            && (string) $existing['origem']
                === $origin
            && (int) $existing['fornecedor_so_id']
                === $supplierSoId
            && strtolower(
                (string) $existing['chave_idempotencia']
            ) === strtolower($idempotencyKey)
            && strtolower(
                (string) $existing['payload_hash']
            ) === strtolower($payloadHash)
            && (
                $budgetId === null
                    ? $existing['orcamento_id'] === null
                    : (int) $existing['orcamento_id']
                        === $budgetId
            )
            && (
                $serviceOrderId === null
                    ? $existing['ordem_servico_id'] === null
                    : (int) $existing['ordem_servico_id']
                        === $serviceOrderId
            )
        );

        if (!$compatible) {
            throw new RuntimeException(
                'Conflito de idempotência: a origem já possui outra integração.'
            );
        }
    }

    /**
     * @param array<string, mixed> $existing
     */
    private function assertImportedCompatibility(
        array $existing,
        int $serviceOrderId,
        int $supplierSoId,
        int $acquisitionSoId,
        string $idempotencyKey,
        string $payloadHash
    ): void {
        $compatible = (
            (int) $existing['empresa_id']
                === $this->companyScope->id()
            && (string) $existing['direcao']
                === self::DIRECTION_SO_TO_FLUX
            && (string) $existing['origem']
                === self::ORIGIN_SO_ACQUISITION
            && (int) $existing['ordem_servico_id']
                === $serviceOrderId
            && (int) $existing['fornecedor_so_id']
                === $supplierSoId
            && (int) $existing['aquisicao_so_id']
                === $acquisitionSoId
            && strtolower(
                (string) $existing['chave_idempotencia']
            ) === strtolower($idempotencyKey)
            && strtolower(
                (string) $existing['payload_hash']
            ) === strtolower($payloadHash)
        );

        if (!$compatible) {
            throw new RuntimeException(
                'A aquisição do SO já está vinculada a outra origem.'
            );
        }
    }

    /**
     * @param array<string, mixed> $integration
     *
     * @return array<string, mixed>
     */
    private function withOutboxId(
        array $integration,
        int $outboxId
    ): array {
        $integration['outbox_id'] = $outboxId;

        return $integration;
    }

    private function calculateBackoff(
        int $attempts
    ): int {
        $exponent = max(
            0,
            min(
                6,
                $attempts - 1
            )
        );

        $seconds = min(
            3600,
            30 * (2 ** $exponent)
        );

        try {
            $jitter = random_int(
                0,
                15
            );
        } catch (Throwable) {
            $jitter = 0;
        }

        return $seconds + $jitter;
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

    private function assertHash(
        string $hash,
        string $field
    ): void {
        if (
            preg_match(
                '/^[a-f0-9]{64}$/Di',
                trim($hash)
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                $field . ' inválida.'
            );
        }
    }

    private function assertJsonSnapshot(
        string $snapshot
    ): void {
        if (
            $snapshot === ''
            || strlen($snapshot)
                > self::MAX_SNAPSHOT_BYTES
            || str_contains(
                $snapshot,
                "\0"
            )
        ) {
            throw new InvalidArgumentException(
                'Snapshot da integração inválido.'
            );
        }

        try {
            $decoded = json_decode(
                $snapshot,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Snapshot da integração não contém JSON válido.',
                0,
                $exception
            );
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException(
                'Snapshot da integração deve conter um objeto JSON.'
            );
        }
    }

    private function normalizeRequiredString(
        string $value,
        int $maximumLength,
        string $field
    ): string {
        $value = trim(
            preg_replace(
                '/[\r\n\t]+/u',
                ' ',
                $value
            ) ?? ''
        );

        if (
            $value === ''
            || str_contains(
                $value,
                "\0"
            )
            || strlen($value) > $maximumLength
        ) {
            throw new InvalidArgumentException(
                $field . ' inválido.'
            );
        }

        return $value;
    }

    private function normalizeOptionalString(
        ?string $value,
        int $maximumLength
    ): ?string {
        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return null;
        }

        if (
            str_contains(
                $value,
                "\0"
            )
            || strlen($value) > $maximumLength
        ) {
            throw new InvalidArgumentException(
                'Valor opcional inválido.'
            );
        }

        return $value;
    }

    private function isDuplicateKey(
        PDOException $exception
    ): bool {
        return (int) (
            $exception->errorInfo[1]
            ?? 0
        ) === 1062;
    }

    /**
     * Executa uma operação respeitando uma transação já aberta.
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
