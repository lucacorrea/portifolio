<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Repository\FiscalDocumentRepository;
use InvalidArgumentException;

final class FiscalDocumentService
{
    private const MODELS = ['55', '65'];
    private const ENVIRONMENTS = ['homologacao', 'producao'];

    public function __construct(
        private readonly FiscalDocumentRepository $documents,
        private readonly FiscalConfigurationService $configuration,
        private readonly FiscalRuntimeReadiness $runtime
    ) {
    }

    /** @return array{id:int,created:bool,status:string} */
    public function prepareFromServiceOrder(
        int $orderId,
        string $model,
        string $environment,
        string $idempotencyKey,
        int $userId
    ): array {
        if ($orderId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('OS ou usuário inválido para emissão fiscal.');
        }
        $model = trim($model);
        $environment = trim($environment);
        if (!in_array($model, self::MODELS, true)) {
            throw new InvalidArgumentException('Escolha NF-e (55) ou NFC-e (65).');
        }
        if (!in_array($environment, self::ENVIRONMENTS, true)) {
            throw new InvalidArgumentException('Ambiente fiscal inválido.');
        }
        $idempotencyKey = strtolower(trim($idempotencyKey));
        if (preg_match('/^[a-f0-9]{64}$/', $idempotencyKey) !== 1) {
            throw new InvalidArgumentException('Token de emissão fiscal inválido. Atualize a tela e tente novamente.');
        }

        $existing = $this->documents->findByIdempotencyKey($idempotencyKey);
        if ($existing !== null) {
            $this->assertSameRequest($existing, $orderId, $model, $environment);
            return [
                'id' => (int) $existing['id'],
                'created' => false,
                'status' => (string) $existing['processamento_status'],
            ];
        }

        $existingByOrigin = $this->documents->findNormalByOrder($orderId, $environment);
        if ($existingByOrigin !== null) {
            return [
                'id' => (int) $existingByOrigin['id'],
                'created' => false,
                'status' => (string) $existingByOrigin['processamento_status'],
            ];
        }

        $readiness = $this->configuration->readiness($environment, $model);
        if (!$readiness['ready']) {
            $message = (string) ($readiness['errors'][0] ?? 'A configuração fiscal possui pendências.');
            throw new InvalidArgumentException($message);
        }
        $runtime = $this->runtime->inspect();
        if (!$runtime['homologation_ready']) {
            throw new InvalidArgumentException('O servidor ainda não possui todos os requisitos técnicos para emissão fiscal.');
        }
        if ($environment === 'producao' && !$runtime['production_allowed']) {
            throw new InvalidArgumentException('A emissão em produção está bloqueada pelo servidor.');
        }

        return $this->documents->transaction(function () use (
            $orderId,
            $model,
            $environment,
            $idempotencyKey,
            $userId
        ): array {
            $duplicate = $this->documents->findByIdempotencyKey($idempotencyKey, true);
            if ($duplicate !== null) {
                $this->assertSameRequest($duplicate, $orderId, $model, $environment);
                return [
                    'id' => (int) $duplicate['id'],
                    'created' => false,
                    'status' => (string) $duplicate['processamento_status'],
                ];
            }

            $existingByOrigin = $this->documents->findNormalByOrder($orderId, $environment, true);
            if ($existingByOrigin !== null) {
                return [
                    'id' => (int) $existingByOrigin['id'],
                    'created' => false,
                    'status' => (string) $existingByOrigin['processamento_status'],
                ];
            }

            $order = $this->documents->lockServiceOrderSnapshot($orderId);
            $this->assertOrderEligible($order);
            $items = $this->documents->fiscalProductItems($orderId);
            $services = $this->documents->fiscalServiceItems($orderId);
            $payments = $this->documents->activePayments($orderId);
            if ($items === []) {
                throw new InvalidArgumentException(
                    'Esta OS não possui peças/produtos para NF-e ou NFC-e. '
                    . 'Serviços exigem NFS-e; use o comprovante não fiscal enquanto essa integração municipal não estiver configurada.'
                );
            }
            $company = $this->documents->companySnapshot();
            $this->assertCompanySnapshot($company);
            $this->assertClientSnapshot($order, $model);
            $this->assertItems($items, (int) $company['crt']);

            $profile = $this->documents->lockActiveConfigurationAndSeries($environment, $model);
            $series = $profile['series'];
            $configuration = $profile['configuration'];
            $number = (int) $series['proximo_numero'];
            if ($number <= 0 || $number > 999999999) {
                throw new InvalidArgumentException('A numeração da série fiscal é inválida.');
            }
            $this->documents->reserveSeriesNumber((int) $series['id'], $number, $userId);
            do {
                $cnf = str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
            } while ((int) $cnf === $number);

            $productsValue = $this->sumItemCents($items);
            if ($productsValue <= 0) {
                throw new InvalidArgumentException('O valor fiscal das peças/produtos deve ser maior que zero.');
            }
            $snapshot = [
                'schema' => 1,
                'captured_at' => date(DATE_ATOM),
                'company' => $this->sanitizeSnapshot($company),
                'customer' => $this->sanitizeSnapshot($order),
                'service_order' => [
                    'id' => (int) $order['id'],
                    'number' => (string) ($order['numero'] ?: sprintf('OS-%06d', $orderId)),
                    'finalized_at' => $order['finalizada_em'],
                    'receivable_id' => $order['conta_receber_id'] === null ? null : (int) $order['conta_receber_id'],
                    'receivable_status' => $order['conta_status'],
                    'receivable_total' => $order['valor_total'],
                    'received_total' => $order['valor_recebido'],
                    'balance' => $order['saldo'],
                ],
                'items' => array_map(fn(array $item): array => $this->sanitizeSnapshot($item), $items),
                'services' => array_map(fn(array $item): array => $this->sanitizeSnapshot($item), $services),
                'payments' => array_map(fn(array $payment): array => $this->sanitizeSnapshot($payment), $payments),
                'totals' => [
                    'products' => $this->formatCents($productsValue),
                    'invoice' => $this->formatCents($productsValue),
                ],
                'fiscal' => [
                    'environment' => $environment,
                    'model' => $model,
                    'configuration_id' => (int) $configuration['id'],
                    'configuration_version' => (int) $configuration['versao'],
                    'series_id' => (int) $series['id'],
                    'series' => (int) $series['serie'],
                    'number' => $number,
                    'cnf' => $cnf,
                ],
            ];

            $documentId = $this->documents->insertPrepared([
                'order_id' => $orderId,
                'receivable_id' => $order['conta_receber_id'],
                'payment_id' => count($payments) === 1 ? (int) $payments[0]['id'] : null,
                'environment' => $environment,
                'model' => $model,
                'configuration_id' => $configuration['id'],
                'series_id' => $series['id'],
                'series' => (string) $series['serie'],
                'number' => $number,
                'cnf' => $cnf,
                'idempotency_key' => $idempotencyKey,
                'products_value' => $this->formatCents($productsValue),
                'invoice_value' => $this->formatCents($productsValue),
                'snapshot_json' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'user_id' => $userId,
            ]);
            $this->documents->addEvent(
                $documentId,
                'documento_preparado',
                null,
                'preparado',
                $userId
            );

            return ['id' => $documentId, 'created' => true, 'status' => 'preparado'];
        });
    }

    /** @param array<string,mixed> $filters @return array<int,array<string,mixed>> */
    public function listDocuments(array $filters = []): array
    {
        return $this->documents->listDocuments($filters);
    }

    /** @param int[] $orderIds @return array<int,array<int,array<string,mixed>>> */
    public function listByOrderIds(array $orderIds): array
    {
        return $this->documents->listByOrderIds($orderIds);
    }

    /** @return array<string,mixed> */
    public function getById(int $id): array
    {
        return $this->documents->getById($id);
    }

    public function recordAccess(int $documentId, string $type, int $userId): void
    {
        if (!in_array($type, ['download_xml', 'reimpressao'], true) || $documentId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Auditoria de acesso fiscal inválida.');
        }
        $document = $this->documents->getById($documentId);
        $this->documents->addEvent(
            $documentId,
            $type,
            (string) $document['processamento_status'],
            (string) $document['processamento_status'],
            $userId
        );
    }
    /** @param array<string,mixed> $document */
    private function assertSameRequest(array $document, int $orderId, string $model, string $environment): void
    {
        if ((int) ($document['ordem_servico_id'] ?? 0) !== $orderId
            || (string) ($document['modelo'] ?? '') !== $model
            || (string) ($document['ambiente'] ?? '') !== $environment
        ) {
            throw new InvalidArgumentException('O token de emissão fiscal já foi usado em outra operação.');
        }
    }

    /** @param array<string,mixed> $order */
    private function assertOrderEligible(array $order): void
    {
        if (($order['status'] ?? '') !== 'finalizada' || $order['excluida_em'] !== null) {
            throw new InvalidArgumentException('A emissão fiscal exige uma OS finalizada e ativa.');
        }
    }

    /** @param array<string,mixed> $company */
    private function assertCompanySnapshot(array $company): void
    {
        foreach ([
            'razao_social', 'documento', 'inscricao_estadual', 'crt',
            'endereco_logradouro', 'endereco_numero', 'endereco_bairro',
            'endereco_cidade', 'endereco_uf', 'endereco_cep', 'codigo_municipio_ibge',
        ] as $field) {
            if (trim((string) ($company[$field] ?? '')) === '') {
                throw new InvalidArgumentException('Complete os dados fiscais da empresa antes da emissão.');
            }
        }
    }

    /** @param array<string,mixed> $order */
    private function assertClientSnapshot(array $order, string $model): void
    {
        if ($model === '65') {
            return;
        }
        foreach ([
            'cliente_nome', 'cliente_documento', 'endereco', 'cliente_numero',
            'bairro', 'cidade', 'uf', 'cep', 'cliente_codigo_municipio',
        ] as $field) {
            if (trim((string) ($order[$field] ?? '')) === '') {
                throw new InvalidArgumentException('Complete a identificação e o endereço fiscal do cliente para emitir NF-e.');
            }
        }
    }

    /** @param array<int,array<string,mixed>> $items */
    private function assertItems(array $items, int $crt): void
    {
        foreach ($items as $item) {
            if (preg_match('/^\d{8}$/', (string) ($item['ncm'] ?? '')) !== 1
                || preg_match('/^\d{4}$/', (string) ($item['cfop_padrao'] ?? '')) !== 1
                || preg_match('/^[0-8]$/', (string) ($item['origem_mercadoria'] ?? '')) !== 1
                || preg_match('/^\d{2}$/', (string) ($item['cst_pis'] ?? '')) !== 1
                || preg_match('/^\d{2}$/', (string) ($item['cst_cofins'] ?? '')) !== 1
                || trim((string) ($item['unidade_tributavel'] ?? '')) === ''
            ) {
                throw new InvalidArgumentException('Complete NCM, CFOP, origem, PIS, COFINS e unidade tributável de todas as peças.');
            }
            $icmsField = in_array($crt, [1, 2, 4], true) ? 'csosn' : 'cst_icms';
            if (trim((string) ($item[$icmsField] ?? '')) === '') {
                throw new InvalidArgumentException('Complete o CST/CSOSN das peças utilizadas na OS.');
            }
        }
    }

    /** @param array<int,array<string,mixed>> $items */
    private function sumItemCents(array $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $this->toCents((string) $item['subtotal']);
        }
        return $total;
    }

    private function toCents(string $value): int
    {
        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Valor fiscal inválido.');
        }
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        return $negative ? -$cents : $cents;
    }

    private function formatCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), abs($cents % 100));
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function sanitizeSnapshot(array $data): array
    {
        unset($data['excluida_em']);
        return $data;
    }
}
