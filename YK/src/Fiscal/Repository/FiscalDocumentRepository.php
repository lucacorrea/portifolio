<?php

declare(strict_types=1);

namespace App\Fiscal\Repository;

use InvalidArgumentException;
use PDO;
use Throwable;

final class FiscalDocumentRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

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
    public function findByOrderModel(int $orderId, string $environment, string $model, bool $lock = false): ?array
    {
        $sql = 'SELECT * FROM documentos_fiscais
                 WHERE ordem_servico_id = :order_id AND ambiente = :environment
                   AND modelo = :model AND finalidade = \'normal\'
                 LIMIT 1';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->connection->prepare($sql);
        $statement->execute(['order_id' => $orderId, 'environment' => $environment, 'model' => $model]);
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

    /** @return array<int,array<string,mixed>> */
    public function fiscalProductItems(int $orderId): array
    {
        $statement = $this->connection->prepare(
            'SELECT item.id, item.referencia_id AS produto_id, item.descricao,
                    item.unidade, item.quantidade, item.valor_unitario,
                    item.desconto, item.subtotal, product.codigo, product.nome,
                    product.ncm, product.cest, product.origem_mercadoria,
                    product.cfop_padrao, product.cst_icms, product.csosn,
                    product.cst_pis, product.cst_cofins, product.aliquota_icms,
                    product.aliquota_pis, product.aliquota_cofins,
                    product.codigo_barras, product.gtin_tributavel,
                    product.unidade_tributavel
               FROM ordem_servico_itens item
               JOIN produtos product ON product.id = item.referencia_id
              WHERE item.ordem_servico_id = :order_id
                AND item.tipo = \'produto\'
                AND item.subtotal > 0
              ORDER BY item.ordem, item.id'
        );
        $statement->execute(['order_id' => $orderId]);

        return $statement->fetchAll();
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

    public function reserveSeriesNumber(int $seriesId, int $number, int $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE fiscal_series
                SET ultimo_numero_reservado = :number,
                    proximo_numero = :next_number,
                    atualizado_por = :user_id
              WHERE id = :id AND proximo_numero = :number'
        );
        $statement->execute([
            'id' => $seriesId,
            'number' => $number,
            'next_number' => $number + 1,
            'user_id' => $userId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('A numeração fiscal foi alterada por outra emissão. Tente novamente.');
        }
    }

    /** @param array<string,mixed> $data */
    public function insertPrepared(array $data): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO documentos_fiscais
                (origem_tipo, origem_id, ordem_servico_id, conta_receber_id, pagamento_id,
                 ambiente, modelo, configuracao_id, serie_id, serie, numero, finalidade,
                 idempotency_key, status, processamento_status, valor_produtos, valor_nota,
                 snapshot_json, emitido_por)
             VALUES
                (\'ordem_servico\', :order_id, :order_id, :receivable_id, :payment_id,
                 :environment, :model, :configuration_id, :series_id, :series, :number, \'normal\',
                 :idempotency_key, \'rascunho\', \'preparado\', :products_value, :invoice_value,
                 :snapshot_json, :user_id)'
        );
        $statement->execute($data);

        return (int) $this->connection->lastInsertId();
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
}
