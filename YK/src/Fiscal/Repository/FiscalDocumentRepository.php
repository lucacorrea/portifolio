<?php

declare(strict_types=1);

namespace App\Fiscal\Repository;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class FiscalDocumentRepository
{
    public function __construct(private readonly PDO $connection) {}

    /** @return array<string,mixed>|null */
    public function findByIdempotencyKey(string $key, bool $lock = false): ?array
    {
        $sql = 'SELECT * FROM documentos_fiscais WHERE idempotency_key = :key LIMIT 1';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->connection->prepare($sql);
        $statement->execute(['key' => $key]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function findNormalByOrder(int $orderId, string $environment, bool $lock = false): ?array
    {
        $sql = 'SELECT * FROM documentos_fiscais
                 WHERE ordem_servico_id = :order_id AND ambiente = :environment
                   AND finalidade = \'normal\'
                 LIMIT 1';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->connection->prepare($sql);
        $statement->execute(['order_id' => $orderId, 'environment' => $environment]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed> */
    public function getById(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Documento fiscal inválido.');
        }
        $statement = $this->connection->prepare(
            'SELECT document.*, service_order.numero AS os_numero,
                    client.nome AS cliente_nome, client.documento AS cliente_documento
               FROM documentos_fiscais document
          LEFT JOIN ordens_servico service_order ON service_order.id = document.ordem_servico_id
          LEFT JOIN clientes client ON client.id = service_order.cliente_id
              WHERE document.id = :id
              LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('Documento fiscal não encontrado.');
        }

        return $row;
    }

    /** @param array<string,mixed> $filters @return array<int,array<string,mixed>> */
    public function listDocuments(array $filters = [], int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $where = [];
        $params = [];
        foreach (['ambiente' => ['homologacao', 'producao'], 'modelo' => ['55', '65']] as $field => $allowed) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '' && in_array($value, $allowed, true)) {
                $where[] = 'document.' . $field . ' = :' . $field;
                $params[$field] = $value;
            }
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'document.processamento_status = :status';
            $params['status'] = $status;
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            if (strlen($search) > 120 || str_contains($search, "\0")) {
                throw new InvalidArgumentException('Filtro fiscal inválido.');
            }
            $where[] = '(document.chave LIKE :search_key OR service_order.numero LIKE :search_order OR client.nome LIKE :search_client)';
            $params['search_key'] = '%' . addcslashes($search, '%_\\') . '%';
            $params['search_order'] = $params['search_key'];
            $params['search_client'] = $params['search_key'];
        }

        $sql = 'SELECT document.id, document.ambiente, document.modelo, document.serie,
                       document.numero, document.processamento_status, document.valor_nota,
                       document.chave, document.protocolo, document.cstat, document.xmotivo,
                       document.emitido_em, document.autorizado_em,
                       service_order.numero AS os_numero, client.nome AS cliente_nome
                  FROM documentos_fiscais document
             LEFT JOIN ordens_servico service_order ON service_order.id = document.ordem_servico_id
             LEFT JOIN clientes client ON client.id = service_order.cliente_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY document.emitido_em DESC, document.id DESC LIMIT ' . $limit;
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @param int[] $orderIds @return array<int,array<int,array<string,mixed>>> */
    public function listByOrderIds(array $orderIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds), static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = 'order_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $statement = $this->connection->prepare(
            'SELECT id, ordem_servico_id, ambiente, modelo, serie, numero,
                    processamento_status, chave, protocolo, valor_nota
               FROM documentos_fiscais
              WHERE ordem_servico_id IN (' . implode(', ', $placeholders) . ')
              ORDER BY ordem_servico_id, id DESC'
        );
        $statement->execute($params);
        $grouped = [];
        foreach ($statement->fetchAll() as $document) {
            $grouped[(int) $document['ordem_servico_id']][] = $document;
        }

        return $grouped;
    }

    /** @return array<string,mixed> */
    public function lockServiceOrderSnapshot(int $orderId): array
    {
        $statement = $this->connection->prepare(
            'SELECT service_order.id, service_order.numero, service_order.status,
                    service_order.finalizada_em, service_order.excluida_em,
                    receivable.id AS conta_receber_id, receivable.status AS conta_status,
                    receivable.valor_total, receivable.valor_recebido, receivable.saldo,
                    client.id AS cliente_id, client.tipo_pessoa, client.nome AS cliente_nome,
                    client.documento AS cliente_documento, client.inscricao_estadual,
                    client.indicador_ie, client.email AS cliente_email,
                    client.telefone,
                    client.endereco, client.numero AS cliente_numero,
                    client.complemento, client.bairro, client.cidade, client.uf, client.cep,
                    client.codigo_municipio_ibge AS cliente_codigo_municipio
               FROM ordens_servico service_order
               JOIN clientes client ON client.id = service_order.cliente_id
          LEFT JOIN contas_receber receivable ON receivable.ordem_servico_id = service_order.id
              WHERE service_order.id = :id
              LIMIT 1
              FOR UPDATE'
        );
        $statement->execute(['id' => $orderId]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('Ordem de Serviço não encontrada.');
        }

        return $row;
    }

  /**
 * @return array<int,array<string,mixed>>
 */
public function fiscalProductItems(int $orderId): array
{
    $statement = $this->connection->prepare(
        'SELECT
            item.id,
            item.referencia_id AS produto_id,
            item.descricao,
            item.unidade,
            item.quantidade,
            item.valor_unitario,
            item.desconto,
            item.subtotal,

            product.codigo,
            product.nome,
            product.ncm,
            product.cest,
            product.origem_mercadoria,
            product.cfop_padrao,
            product.cst_icms,
            product.csosn,
            product.cst_pis,
            product.cst_cofins,
            product.aliquota_icms,
            product.aliquota_pis,
            product.aliquota_cofins,

            product.cst_ibs_cbs,
            product.classificacao_tributaria_ibs_cbs,

            product.codigo_barras,
            product.gtin_tributavel,
            product.unidade_tributavel

         FROM ordem_servico_itens AS item

         INNER JOIN produtos AS product
            ON product.id = item.referencia_id

         WHERE item.ordem_servico_id = :order_id
           AND item.tipo = \'produto\'
           AND item.subtotal > 0

         ORDER BY
            item.ordem ASC,
            item.id ASC'
    );

    $statement->execute([
        'order_id' => $orderId,
    ]);

    return $statement->fetchAll();
}

    /** @return array<string,mixed>|null */
    public function resolveIbsCbsRule(string $cst, string $classification, string $issueDate): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT codigo_cst, codigo_classificacao, descricao, modo_calculo,
                    aliquota_ibs_uf, aliquota_ibs_municipio, aliquota_cbs,
                    indicadores_json, fonte, versao_fonte, vigencia_inicio, vigencia_fim
               FROM fiscal_ibs_cbs_classificacoes
              WHERE codigo_cst = :cst AND codigo_classificacao = :classification
                AND status = \'ativo\' AND vigencia_inicio <= :issue_date_start
                AND (vigencia_fim IS NULL OR vigencia_fim >= :issue_date_end)
              ORDER BY vigencia_inicio DESC, id DESC LIMIT 1'
        );
        $statement->execute([
            'cst' => $cst,
            'classification' => $classification,
            'issue_date_start' => $issueDate,
            'issue_date_end' => $issueDate,
        ]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'cst' => (string) $row['codigo_cst'],
            'classification' => (string) $row['codigo_classificacao'],
            'description' => (string) $row['descricao'],
            'calculation_mode' => (string) $row['modo_calculo'],
            'ibs_uf_rate' => (string) $row['aliquota_ibs_uf'],
            'ibs_city_rate' => (string) $row['aliquota_ibs_municipio'],
            'cbs_rate' => (string) $row['aliquota_cbs'],
            'indicators' => json_decode((string) ($row['indicadores_json'] ?? '{}'), true) ?: [],
            'source' => (string) $row['fonte'],
            'source_version' => (string) $row['versao_fonte'],
            'valid_from' => (string) $row['vigencia_inicio'],
            'valid_until' => $row['vigencia_fim'] === null ? null : (string) $row['vigencia_fim'],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function fiscalServiceItems(int $orderId): array
    {
        $statement = $this->connection->prepare(
            'SELECT item.id, item.referencia_id AS servico_id, item.descricao,
                    item.unidade, item.quantidade, item.valor_unitario,
                    item.desconto, item.subtotal, service.codigo, service.nome,
                    service.codigo_tributacao_nacional, service.nbs, service.descricao_fiscal,
                    service.municipio_incidencia_ibge, service.tributacao_iss,
                    service.iss_retido, service.aliquota_iss, service.regime_especial,
                    service.exigibilidade_iss, service.cst_pis_servico,
                    service.cst_cofins_servico, service.aliquota_pis_servico,
                    service.aliquota_cofins_servico, service.cst_ibs_cbs,
                    service.classificacao_tributaria_ibs_cbs, service.cindop,
                    service.finalidade_nfse, service.tipo_operacao
               FROM ordem_servico_itens item
          LEFT JOIN servicos service ON service.id = item.referencia_id
              WHERE item.ordem_servico_id = :order_id
                AND item.tipo = \'servico\' AND item.subtotal > 0
              ORDER BY item.ordem, item.id'
        );
        $statement->execute(['order_id' => $orderId]);
        return $statement->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function activePayments(int $orderId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, valor, forma_pagamento, quantidade_parcelas, recebido_em
               FROM ordem_servico_pagamentos
              WHERE ordem_servico_id = :order_id AND status = \'ativo\'
              ORDER BY recebido_em, id'
        );
        $statement->execute(['order_id' => $orderId]);
        return $statement->fetchAll();
    }

    /** @param array<int,array<string,mixed>> $allocations */
    public function persistPaymentAllocations(
        int $orderId,
        string $documentType,
        int $documentId,
        array $allocations
    ): void {
        if (!in_array($documentType, ['nfe','nfce','nfse'], true) || $documentId <= 0) {
            throw new InvalidArgumentException('Documento da alocação fiscal inválido.');
        }
        $statement = $this->connection->prepare(
            'INSERT INTO fiscal_pagamento_alocacoes
                (ordem_servico_id,pagamento_id,tipo_documento,documento_id,valor_alocado)
             VALUES (:order_id,:payment_id,:document_type,:document_id,:value)'
        );
        foreach ($allocations as $allocation) {
            $statement->execute([
                'order_id'=>$orderId,
                'payment_id'=>(int)$allocation['id'],
                'document_type'=>$documentType,
                'document_id'=>$documentId,
                'value'=>(string)$allocation['valor'],
            ]);
        }
    }
    /** @return array<string,mixed> */
    public function companySnapshot(): array
    {
        $row = $this->connection->query(
            'SELECT razao_social, nome_fantasia, documento, inscricao_estadual,
                    inscricao_municipal, crt, cnae_principal, email, telefone,
                    endereco_logradouro, endereco_numero, endereco_complemento,
                    endereco_bairro, endereco_cidade, endereco_uf, endereco_cep,
                    codigo_municipio_ibge
               FROM configuracoes_empresa
              WHERE id = 1'
        )->fetch();

        return $row === false ? [] : $row;
    }

    /** @return array<string,mixed> */
    public function lockActiveConfigurationAndSeries(string $environment, string $model): array
    {
        $configuration = $this->connection->prepare(
            'SELECT id, ambiente, modelo, versao, uf, schema_versao, qr_code_versao,
                    certificado_id, csc_id
               FROM fiscal_configuracoes
              WHERE ambiente = :environment AND modelo = :model AND status = \'ativa\'
              ORDER BY versao DESC
              LIMIT 1
              FOR UPDATE'
        );
        $configuration->execute(['environment' => $environment, 'model' => $model]);
        $configurationRow = $configuration->fetch();
        if ($configurationRow === false) {
            throw new InvalidArgumentException('Ative uma configuração fiscal para o ambiente e modelo escolhidos.');
        }

        $series = $this->connection->prepare(
            'SELECT id, serie, proximo_numero
               FROM fiscal_series
              WHERE ambiente = :environment AND modelo = :model AND status = \'ativa\'
              ORDER BY serie
              LIMIT 1
              FOR UPDATE'
        );
        $series->execute(['environment' => $environment, 'model' => $model]);
        $seriesRow = $series->fetch();
        if ($seriesRow === false) {
            throw new InvalidArgumentException('Cadastre uma série fiscal ativa para o ambiente e modelo escolhidos.');
        }

        return ['configuration' => $configurationRow, 'series' => $seriesRow];
    }

    public function reserveSeriesNumber(
        int $seriesId,
        int $number,
        int $userId
    ): void {
        if (
            $seriesId <= 0
            || $number <= 0
            || $userId <= 0
        ) {
            throw new InvalidArgumentException(
                'Dados inválidos para reserva da numeração fiscal.'
            );
        }

        $statement =
            $this->connection->prepare(
                'UPDATE fiscal_series

                SET
                    ultimo_numero_reservado =
                        :reserved_number,

                    proximo_numero =
                        :next_number,

                    atualizado_por =
                        :user_id

              WHERE id = :series_id

                AND proximo_numero =
                    :expected_number'
            );

        $statement->execute([
            'reserved_number' =>
            $number,

            'next_number' =>
            $number + 1,

            'user_id' =>
            $userId,

            'series_id' =>
            $seriesId,

            'expected_number' =>
            $number,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException(
                'A numeração fiscal foi alterada por outra emissão. '
                    . 'Atualize a tela e tente novamente.'
            );
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insertPrepared(array $data): int
    {
        $statement =
            $this->connection->prepare(
                'INSERT INTO documentos_fiscais
                (
                    origem_tipo,
                    origem_id,
                    ordem_servico_id,
                    conta_receber_id,
                    pagamento_id,
                    ambiente,
                    modelo,
                    configuracao_id,
                    serie_id,
                    serie,
                    numero,
                    cnf,
                    finalidade,
                    idempotency_key,
                    status,
                    processamento_status,
                    valor_produtos,
                    valor_nota,
                    snapshot_json,
                    emitido_por
                )
             VALUES
                (
                    \'ordem_servico\',
                    :origin_order_id,
                    :service_order_id,
                    :receivable_id,
                    :payment_id,
                    :environment,
                    :model,
                    :configuration_id,
                    :series_id,
                    :series,
                    :number,
                    :cnf,
                    \'normal\',
                    :idempotency_key,
                    \'rascunho\',
                    \'preparado\',
                    :products_value,
                    :invoice_value,
                    :snapshot_json,
                    :user_id
                )'
            );

        $statement->execute([
            'origin_order_id' =>
            $data['order_id'],

            'service_order_id' =>
            $data['order_id'],

            'receivable_id' =>
            $data['receivable_id'],

            'payment_id' =>
            $data['payment_id'],

            'environment' =>
            $data['environment'],

            'model' =>
            $data['model'],

            'configuration_id' =>
            $data['configuration_id'],

            'series_id' =>
            $data['series_id'],

            'series' =>
            $data['series'],

            'number' =>
            $data['number'],

            'cnf' =>
            $data['cnf'],

            'idempotency_key' =>
            $data['idempotency_key'],

            'products_value' =>
            $data['products_value'],

            'invoice_value' =>
            $data['invoice_value'],

            'snapshot_json' =>
            $data['snapshot_json'],

            'user_id' =>
            $data['user_id'],
        ]);

        $id =
            (int) $this->connection
                ->lastInsertId();

        if ($id <= 0) {
            throw new RuntimeException(
                'Falha ao obter o ID do documento fiscal preparado.'
            );
        }

        return $id;
    }

    /** @param array<string,mixed> $details */
    public function addEvent(int $documentId, string $type, ?string $previous, string $next, int $userId, array $details = []): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO fiscal_documento_eventos
                (documento_fiscal_id, tipo, status_anterior, status_novo,
                 cstat, xmotivo, artefato_path, artefato_sha256, usuario_id)
             VALUES
                (:document_id, :type, :previous_status, :next_status,
                 :cstat, :reason, :artifact_path, :artifact_hash, :user_id)'
        );
        $statement->execute([
            'document_id' => $documentId,
            'type' => $type,
            'previous_status' => $previous,
            'next_status' => $next,
            'cstat' => $details['cstat'] ?? null,
            'reason' => $details['reason'] ?? null,
            'artifact_path' => $details['artifact_path'] ?? null,
            'artifact_hash' => $details['artifact_hash'] ?? null,
            'user_id' => $userId,
        ]);
    }

    /** @return array<string,mixed> */
    public function lockDocument(int $id): array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM documentos_fiscais WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('Documento fiscal não encontrado.');
        }
        return $row;
    }

    /** @param array{reference:string,sha256:string} $artifact */
    public function markSignedForTransmission(
        int $id,
        string $key,
        string $batchId,
        array $artifact,
        string $expectedStatus = 'preparado'
    ): void {
        $statement = $this->connection->prepare(
            'UPDATE documentos_fiscais
                SET chave = :access_key, lote_id = :batch_id,
                    xml_assinado_path = :path, xml_assinado_sha256 = :hash,
                    status = \'emitida\', processamento_status = \'processando\',
                    tentativas = tentativas + 1, processando_em = CURRENT_TIMESTAMP,
                    reconsulta_apos = NULL, xmotivo = NULL
              WHERE id = :id AND processamento_status = :expected_status'
        );
        $statement->execute([
            'id' => $id,
            'access_key' => $key,
            'batch_id' => $batchId,
            'path' => $artifact['reference'],
            'hash' => $artifact['sha256'],
            'expected_status' => $expectedStatus,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('O documento fiscal já está sendo processado.');
        }
    }

    /** @param array{reference:string,sha256:string} $artifact */
    public function storeResponse(int $id, array $artifact, ?string $receipt, string $cstat, string $reason): void
    {
        $statement = $this->connection->prepare(
            'UPDATE documentos_fiscais
                SET ultima_resposta_path = :path, ultima_resposta_sha256 = :hash,
                    recibo_sefaz = COALESCE(:receipt, recibo_sefaz),
                    cstat = :cstat, xmotivo = :reason
              WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'path' => $artifact['reference'],
            'hash' => $artifact['sha256'],
            'receipt' => $receipt,
            'cstat' => $cstat,
            'reason' => substr($reason, 0, 255),
        ]);
    }

    public function markPendingReconciliation(int $id, string $reason): void
    {
        $statement = $this->connection->prepare(
            'UPDATE documentos_fiscais
                SET processamento_status = \'pendente_reconsulta\', status = \'emitida\',
                    xmotivo = :reason, reconsulta_apos = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 2 MINUTE)
              WHERE id = :id AND processamento_status IN (\'processando\', \'pendente_reconsulta\')'
        );
        $statement->execute(['id' => $id, 'reason' => substr($reason, 0, 255)]);
    }

    /** @param array{reference:string,sha256:string} $artifact */
    public function markAuthorized(
        int $id,
        string $protocol,
        string $cstat,
        string $reason,
        array $artifact
    ): void {
        $statement = $this->connection->prepare(
            'UPDATE documentos_fiscais
                SET status = \'autorizada\', processamento_status = \'autorizado\',
                    protocolo = :protocol, cstat = :cstat, xmotivo = :reason,
                    xml_autorizado_path = :path, xml_autorizado_sha256 = :hash,
                    autorizado_em = CURRENT_TIMESTAMP, reconsulta_apos = NULL
              WHERE id = :id AND processamento_status IN (\'processando\', \'pendente_reconsulta\', \'autorizado\')'
        );
        $statement->execute([
            'id' => $id,
            'protocol' => $protocol,
            'cstat' => $cstat,
            'reason' => substr($reason, 0, 255),
            'path' => $artifact['reference'],
            'hash' => $artifact['sha256'],
        ]);
    }

    /** @param array{reference:string,sha256:string} $artifact */
    public function markCancelled(int $id, string $protocol, string $cstat, string $reason, array $artifact): void
    {
        $statement = $this->connection->prepare(
            'UPDATE documentos_fiscais
                SET status = \'cancelada\', processamento_status = \'cancelado\',
                    cstat = :cstat, xmotivo = :reason, cancelado_em = CURRENT_TIMESTAMP,
                    cancelamento_protocolo = :protocol,
                    cancelamento_xml_path = :path, cancelamento_xml_sha256 = :hash
              WHERE id = :id AND processamento_status = \'autorizado\''
        );
        $statement->execute([
            'id' => $id,
            'protocol' => $protocol,
            'cstat' => $cstat,
            'reason' => substr($reason, 0, 255),
            'path' => $artifact['reference'],
            'hash' => $artifact['sha256'],
        ]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('O documento fiscal não está autorizado para cancelamento.');
        }
    }
    public function markRejected(int $id, string $status, string $cstat, string $reason): void
    {
        if (!in_array($status, ['rejeitado', 'denegado', 'erro_tecnico'], true)) {
            throw new InvalidArgumentException('Estado fiscal de falha inválido.');
        }
        $localStatus = $status === 'denegado' ? 'rejeitada' : 'rejeitada';
        $statement = $this->connection->prepare(
            'UPDATE documentos_fiscais
                SET status = :local_status, processamento_status = :processing_status,
                    cstat = :cstat, xmotivo = :reason, reconsulta_apos = NULL
              WHERE id = :id AND processamento_status IN (\'processando\', \'pendente_reconsulta\')'
        );
        $statement->execute([
            'id' => $id,
            'local_status' => $localStatus,
            'processing_status' => $status,
            'cstat' => $cstat,
            'reason' => substr($reason, 0, 255),
        ]);
    }
    public function transaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->connection->inTransaction();
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
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param array{reference:string,sha256:string} $generated
     * @param array{reference:string,sha256:string} $signed
     */
    public function createTransmissionAttempt(
        int $documentId,
        int $number,
        string $snapshot,
        string $key,
        string $batchId,
        array $generated,
        array $signed,
        int $userId
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO fiscal_documento_tentativas
                (documento_fiscal_id,numero_tentativa,snapshot_json,
                 xml_gerado_path,xml_gerado_sha256,xml_assinado_path,xml_assinado_sha256,
                 chave,lote_id,status,criado_por)
             VALUES (:document_id,:number,:snapshot,:generated_path,:generated_hash,
                     :signed_path,:signed_hash,:access_key,:batch_id,\'enviado\',:user_id)'
        );
        $statement->execute([
            'document_id'=>$documentId,
            'number'=>$number,
            'snapshot'=>$snapshot,
            'generated_path'=>$generated['reference'],
            'generated_hash'=>$generated['sha256'],
            'signed_path'=>$signed['reference'],
            'signed_hash'=>$signed['sha256'],
            'access_key'=>$key,
            'batch_id'=>$batchId,
            'user_id'=>$userId,
        ]);
        return (int)$this->connection->lastInsertId();
    }

    /** @param array{reference:string,sha256:string}|null $response */
    public function updateLatestTransmissionAttempt(
        int $documentId,
        string $status,
        ?array $response,
        ?string $receipt,
        string $cstat,
        string $reason
    ): void {
        if (!in_array($status, ['pendente_reconsulta','autorizado','rejeitado','denegado','erro_tecnico'], true)) {
            throw new InvalidArgumentException('Estado da tentativa fiscal inválido.');
        }
        $statement = $this->connection->prepare(
            'UPDATE fiscal_documento_tentativas
                SET resposta_path=COALESCE(:response_path,resposta_path),
                    resposta_sha256=COALESCE(:response_hash,resposta_sha256),
                    recibo_sefaz=COALESCE(:receipt,recibo_sefaz),cstat=:cstat,xmotivo=:reason,status=:status,
                    finalizado_em=CASE WHEN :terminal=1 THEN CURRENT_TIMESTAMP ELSE finalizado_em END
              WHERE documento_fiscal_id=:document_id
              ORDER BY numero_tentativa DESC LIMIT 1'
        );
        $statement->execute([
            'document_id'=>$documentId,
            'status'=>$status,
            'response_path'=>$response['reference'] ?? null,
            'response_hash'=>$response['sha256'] ?? null,
            'receipt'=>$receipt,
            'cstat'=>$cstat === '' ? null : $cstat,
            'reason'=>substr($reason,0,255),
            'terminal'=>in_array($status, ['autorizado','rejeitado','denegado'], true) ? 1 : 0,
        ]);
    }
}
