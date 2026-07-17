<?php

declare(strict_types=1);

namespace App\Finance\Service;

use InvalidArgumentException;
use PDO;
use Throwable;

final class ReceiptService
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array{id:int,created:bool} */
    public function emitForPayment(int $paymentId, int $userId): array
    {
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Pagamento inválido para emissão do recibo.');
        }

        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }
        try {
            $payment = $this->lockPayment($paymentId);
            if ($payment['status'] !== 'ativo' || (float) $payment['valor'] <= 0.0) {
                throw new InvalidArgumentException('Somente pagamento ativo pode gerar recibo.');
            }
            if ($payment['os_status'] !== 'finalizada' || $payment['excluida_em'] !== null) {
                throw new InvalidArgumentException('O recibo exige uma OS finalizada e ativa.');
            }

            $existing = $this->lockReceiptByPayment($paymentId);
            if ($existing !== null) {
                if ($existing['status'] !== 'emitido') {
                    throw new InvalidArgumentException('O recibo deste pagamento foi cancelado e não pode ser reemitido.');
                }
                if ($ownsTransaction) {
                    $this->connection->commit();
                }
                return ['id' => (int) $existing['id'], 'created' => false];
            }

            $company = $this->companySnapshot();
            $orderNumber = trim((string) ($payment['os_numero'] ?? ''));
            if ($orderNumber === '') {
                $orderNumber = sprintf('OS-%06d', (int) $payment['ordem_servico_id']);
            }
            $description = sprintf(
                'Recebemos de %s o pagamento referente à %s.',
                $payment['cliente_nome'],
                $orderNumber
            );

            $statement = $this->connection->prepare(
                'INSERT INTO recibos
                    (numero, cliente_id, ordem_servico_id, pagamento_id, cliente_nome, cliente_documento,
                     os_numero, pagamento_recebido_em, empresa_nome, empresa_documento, empresa_telefone,
                     empresa_endereco, empresa_logo, descricao, valor, forma_pagamento, status, emitido_por)
                 VALUES
                    (NULL, :client_id, :order_id, :payment_id, :client_name, :client_document,
                     :order_number, :received_at, :company_name, :company_document, :company_phone,
                     :company_address, :company_logo, :description, :value, :payment_form, "emitido", :user_id)'
            );
            $statement->execute([
                'client_id' => $payment['cliente_id'],
                'order_id' => $payment['ordem_servico_id'],
                'payment_id' => $paymentId,
                'client_name' => $payment['cliente_nome'],
                'client_document' => $payment['cliente_documento'],
                'order_number' => $orderNumber,
                'received_at' => $payment['recebido_em'],
                'company_name' => $company['name'],
                'company_document' => $company['document'],
                'company_phone' => $company['phone'],
                'company_address' => $company['address'],
                'company_logo' => $company['logo'],
                'description' => $description,
                'value' => $payment['valor'],
                'payment_form' => $payment['forma_pagamento'],
                'user_id' => $userId,
            ]);
            $receiptId = (int) $this->connection->lastInsertId();
            $number = sprintf('REC-%06d', $receiptId);
            $this->connection->prepare('UPDATE recibos SET numero = :number WHERE id = :id')
                ->execute(['id' => $receiptId, 'number' => $number]);

            if ($ownsTransaction) {
                $this->connection->commit();
            }
            return ['id' => $receiptId, 'created' => true];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    public function getById(int $receiptId): array
    {
        if ($receiptId <= 0) {
            throw new InvalidArgumentException('Recibo inválido.');
        }
        $statement = $this->connection->prepare(
            'SELECT receipt.*, issuer.nome AS emitido_por_nome, canceler.nome AS cancelado_por_nome
               FROM recibos receipt
               JOIN usuarios issuer ON issuer.id = receipt.emitido_por
          LEFT JOIN usuarios canceler ON canceler.id = receipt.cancelado_por
              WHERE receipt.id = :id
              LIMIT 1'
        );
        $statement->execute(['id' => $receiptId]);
        $receipt = $statement->fetch();
        if ($receipt === false) {
            throw new InvalidArgumentException('Recibo não encontrado.');
        }
        return $receipt;
    }

    /** @return array<int,array<string,mixed>> */
    public function listActivePaymentsForOrder(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }
        $statement = $this->connection->prepare(
            "SELECT payment.id, payment.valor, payment.forma_pagamento, payment.recebido_em,
                    receipt.id AS recibo_id, receipt.numero AS recibo_numero, receipt.status AS recibo_status
               FROM ordem_servico_pagamentos payment
          LEFT JOIN recibos receipt ON receipt.pagamento_id = payment.id
              WHERE payment.ordem_servico_id = :order_id AND payment.status = 'ativo'
              ORDER BY payment.recebido_em, payment.id"
        );
        $statement->execute(['order_id' => $orderId]);
        return $statement->fetchAll();
    }

    /** @param int[] $orderIds @return array<int,array<int,array<string,mixed>>> */
    public function listActivePaymentsForOrders(array $orderIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $orderIds),
            static fn(int $id): bool => $id > 0
        )));
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
            "SELECT payment.id, payment.ordem_servico_id, payment.valor, payment.forma_pagamento,
                    payment.recebido_em, receipt.id AS recibo_id, receipt.numero AS recibo_numero,
                    receipt.status AS recibo_status
               FROM ordem_servico_pagamentos payment
          LEFT JOIN recibos receipt ON receipt.pagamento_id = payment.id
              WHERE payment.ordem_servico_id IN (" . implode(', ', $placeholders) . ")
                AND payment.status = 'ativo'
              ORDER BY payment.ordem_servico_id, payment.recebido_em, payment.id"
        );
        $statement->execute($params);

        $paymentsByOrder = [];
        foreach ($statement->fetchAll() as $payment) {
            $paymentsByOrder[(int) $payment['ordem_servico_id']][] = $payment;
        }
        return $paymentsByOrder;
    }

    /** @return array<string,mixed> */
    private function lockPayment(int $paymentId): array
    {
        $statement = $this->connection->prepare(
            'SELECT payment.id, payment.ordem_servico_id, payment.valor, payment.forma_pagamento,
                    payment.recebido_em, payment.status, service_order.numero AS os_numero,
                    service_order.status AS os_status, service_order.excluida_em,
                    client.id AS cliente_id, client.nome AS cliente_nome,
                    client.documento AS cliente_documento
               FROM ordem_servico_pagamentos payment
               JOIN ordens_servico service_order ON service_order.id = payment.ordem_servico_id
               JOIN clientes client ON client.id = service_order.cliente_id
              WHERE payment.id = :id
              FOR UPDATE'
        );
        $statement->execute(['id' => $paymentId]);
        $payment = $statement->fetch();
        if ($payment === false) {
            throw new InvalidArgumentException('Pagamento não encontrado.');
        }
        return $payment;
    }

    /** @return array<string,mixed>|null */
    private function lockReceiptByPayment(int $paymentId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, status FROM recibos WHERE pagamento_id = :payment_id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['payment_id' => $paymentId]);
        $receipt = $statement->fetch();
        return $receipt === false ? null : $receipt;
    }

    /** @return array{name:?string,document:?string,phone:?string,address:?string,logo:?string} */
    private function companySnapshot(): array
    {
        $company = $this->connection->query(
            'SELECT nome_fantasia, razao_social, documento, telefone, endereco, logo
               FROM configuracoes_empresa WHERE id = 1'
        )->fetch();
        if ($company === false) {
            return ['name' => null, 'document' => null, 'phone' => null, 'address' => null, 'logo' => null];
        }
        $name = trim((string) ($company['nome_fantasia'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($company['razao_social'] ?? ''));
        }
        return [
            'name' => $name === '' ? null : $name,
            'document' => $company['documento'] ?: null,
            'phone' => $company['telefone'] ?: null,
            'address' => $company['endereco'] ?: null,
            'logo' => $company['logo'] ?: null,
        ];
    }
}
