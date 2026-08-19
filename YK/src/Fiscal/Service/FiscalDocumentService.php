<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Repository\FiscalDocumentRepository;
use App\Fiscal\Tax\FiscalIdentityValidator;
use App\Fiscal\Tax\IbsCbsApplicabilityResolver;
use InvalidArgumentException;

final class FiscalDocumentService
{
    private const MODELS = ['55', '65'];
    private const ENVIRONMENTS = ['homologacao', 'producao'];

    private readonly IbsCbsApplicabilityResolver $ibsCbsApplicability;

    public function __construct(
        private readonly FiscalDocumentRepository $documents,
        private readonly FiscalConfigurationService $configuration,
        private readonly FiscalRuntimeReadiness $runtime,
        ?IbsCbsApplicabilityResolver $ibsCbsApplicability = null
    ) {
        $this->ibsCbsApplicability = $ibsCbsApplicability ?? new IbsCbsApplicabilityResolver();
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
        $this->assertRejectedRetryModel($existingByOrigin, $model);
        if ($existingByOrigin !== null
            && (string) $existingByOrigin['processamento_status'] !== 'rejeitado'
        ) {
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
            $this->assertRejectedRetryModel($existingByOrigin, $model);
            if ($existingByOrigin !== null
                && (string) $existingByOrigin['processamento_status'] !== 'rejeitado'
            ) {
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
            $issueDate = date('Y-m-d');
            $crt = (int) $company['crt'];
            $applicability = $this->ibsCbsApplicability->resolve(
                $issueDate,
                $crt,
                $model,
                $environment,
                strtoupper((string) $company['endereco_uf'])
            );
            $requiresIbsCbs = $applicability['required'];
            $this->assertItems($items, $crt, $requiresIbsCbs);
            $operation = $this->resolveOperation($company, $order, $items, $model);
            if ($requiresIbsCbs) {
                foreach ($items as $index => $item) {
                    $rule = $this->documents->resolveIbsCbsRule(
                        (string) $item['cst_ibs_cbs'],
                        (string) $item['classificacao_tributaria_ibs_cbs'],
                        $issueDate
                    );
                    if ($rule === null) {
                        throw new InvalidArgumentException(
                            'A combinação CST IBS/CBS + cClassTrib do produto ' . (string) $item['descricao']
                            . ' não possui regra fiscal vigente cadastrada.'
                        );
                    }
                    $this->ibsCbsApplicability->assertCatalogRuleSupports($rule, $model);
                    $items[$index]['ibs_cbs_rule'] = $rule;
                }
            }

            $profile = $this->documents->lockActiveConfigurationAndSeries($environment, $model);
            $series = $profile['series'];
            $configuration = $profile['configuration'];
            $exactReadiness = $this->configuration->readiness(
                $environment,
                $model,
                (int) $configuration['id']
            );
            if (!$exactReadiness['ready']) {
                throw new InvalidArgumentException(
                    (string) ($exactReadiness['errors'][0] ?? 'A configuração fiscal ativa não está pronta.')
                );
            }
            $isRetry = $existingByOrigin !== null;
            if ($isRetry) {
                $number = (int) $existingByOrigin['numero'];
                $series = [
                    'id' => (int) $existingByOrigin['serie_id'],
                    'serie' => (int) $existingByOrigin['serie'],
                ];
                $cnf = (string) $existingByOrigin['cnf'];
            } else {
                $number = (int) $series['proximo_numero'];
                if ($number <= 0 || $number > 999999999) {
                    throw new InvalidArgumentException('A numeração da série fiscal é inválida.');
                }
                $this->documents->reserveSeriesNumber((int) $series['id'], $number, $userId);
                do {
                    $cnf = str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
                } while ((int) $cnf === $number);
            }

            $productsValue = $this->sumItemCents($items);
            if ($productsValue <= 0) {
                throw new InvalidArgumentException('O valor fiscal das peças/produtos deve ser maior que zero.');
            }
            $allocatedPayments = (new FiscalPaymentAllocator())->allocate($payments, 0, $productsValue);
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
                'payments' => array_map(fn(array $payment): array => $this->sanitizeSnapshot($payment), $allocatedPayments),
                'operation' => $operation,
                'tax_applicability' => ['ibs_cbs' => $applicability],
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
                    'issued_at' => date(DATE_ATOM),
                ],
            ];

            $preparedData = [
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
            ];
            if ($isRetry) {
                $documentId = (int) $existingByOrigin['id'];
                $this->documents->resetRejectedForRetry($documentId, $preparedData);
            } else {
                $documentId = $this->documents->insertPrepared($preparedData);
            }
            $this->documents->addEvent(
                $documentId,
                $isRetry ? 'rejeicao_corrigida' : 'documento_preparado',
                $isRetry ? 'rejeitado' : null,
                'preparado',
                $userId
            );
            $this->documents->persistPaymentAllocations(
                $orderId,
                $model === '55' ? 'nfe' : 'nfce',
                $documentId,
                $allocatedPayments
            );

            return ['id' => $documentId, 'created' => !$isRetry, 'status' => 'preparado'];
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
        if (!FiscalIdentityValidator::isValidCnpj(
            FiscalIdentityValidator::normalizeTaxId((string) ($company['documento'] ?? ''))
        )) {
            throw new InvalidArgumentException('O CNPJ da empresa é inválido para emissão fiscal.');
        }
        $state = strtoupper(trim((string) $company['endereco_uf']));
        if (!FiscalConfigurationService::isValidIbgeCityCode((string) $company['codigo_municipio_ibge'], $state)
            || !FiscalConfigurationService::isValidStateRegistration((string) $company['inscricao_estadual'], $state)
            || preg_match('/^\d{8}$/', FiscalIdentityValidator::normalizeTaxId((string) $company['endereco_cep'])) !== 1
        ) {
            throw new InvalidArgumentException('CNPJ, IE, CEP ou município do emitente é incompatível com a emissão fiscal.');
        }
    }

    /** @param array<string,mixed> $order */
    private function assertClientSnapshot(array $order, string $model): void
    {
        $document = FiscalIdentityValidator::normalizeTaxId((string) ($order['cliente_documento'] ?? ''));
        if ($model === '65' && $document === '') {
            return;
        }
        if (!FiscalIdentityValidator::isValidCpfOrCnpj($document)) {
            throw new InvalidArgumentException('O CPF/CNPJ do cliente é inválido para emissão fiscal.');
        }
        if ($model === '65') {
            if (trim((string) ($order['cliente_nome'] ?? '')) === '') {
                throw new InvalidArgumentException('Consumidor identificado exige nome válido na NFC-e.');
            }
            $addressFields = ['endereco', 'cliente_numero', 'bairro', 'cidade', 'uf', 'cep', 'cliente_codigo_municipio'];
            $hasAddress = false;
            foreach ($addressFields as $field) {
                $hasAddress = $hasAddress || trim((string) ($order[$field] ?? '')) !== '';
            }
            if ($hasAddress) {
                foreach ($addressFields as $field) {
                    if (trim((string) ($order[$field] ?? '')) === '') {
                        throw new InvalidArgumentException('Complete ou remova o endereço parcial do consumidor da NFC-e.');
                    }
                }
                $state = strtoupper(trim((string) $order['uf']));
                if (!FiscalConfigurationService::isValidIbgeCityCode((string) $order['cliente_codigo_municipio'], $state)
                    || preg_match('/^\d{8}$/', FiscalIdentityValidator::normalizeTaxId((string) $order['cep'])) !== 1
                ) {
                    throw new InvalidArgumentException('O endereço do consumidor da NFC-e possui CEP ou município inválido.');
                }
            }
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
        $state = strtoupper(trim((string) $order['uf']));
        if (!FiscalConfigurationService::isValidIbgeCityCode((string) $order['cliente_codigo_municipio'], $state)
            || preg_match('/^\d{8}$/', FiscalIdentityValidator::normalizeTaxId((string) $order['cep'])) !== 1
        ) {
            throw new InvalidArgumentException('O CEP ou município do cliente é incompatível com a UF informada.');
        }
        $indicator = (string) ($order['indicador_ie'] ?? 'nao_contribuinte');
        if ($indicator === 'contribuinte'
            && !FiscalConfigurationService::isValidStateRegistration((string) ($order['inscricao_estadual'] ?? ''), $state)
        ) {
            throw new InvalidArgumentException('Cliente contribuinte exige inscrição estadual válida.');
        }
    }

    /** @param array<int,array<string,mixed>> $items */
    private function assertItems(array $items, int $crt, bool $requiresIbsCbs): void
    {
        foreach ($items as $item) {
            if (preg_match('/^\d{8}$/', (string) ($item['ncm'] ?? '')) !== 1
                || preg_match('/^\d{4}$/', (string) ($item['cfop_padrao'] ?? '')) !== 1
                || preg_match('/^[0-8]$/', (string) ($item['origem_mercadoria'] ?? '')) !== 1
                || preg_match('/^\d{2}$/', (string) ($item['cst_pis'] ?? '')) !== 1
                || preg_match('/^\d{2}$/', (string) ($item['cst_cofins'] ?? '')) !== 1
                || trim((string) ($item['unidade_tributavel'] ?? '')) === ''
            ) {
                throw new InvalidArgumentException('Complete NCM, CFOP, origem, PIS, COFINS, IBS/CBS, cClassTrib e unidade tributável de todas as peças.');
            }
            $icmsField = in_array($crt, [1, 2, 4], true) ? 'csosn' : 'cst_icms';
            if (trim((string) ($item[$icmsField] ?? '')) === '') {
                throw new InvalidArgumentException('Complete o CST/CSOSN das peças utilizadas na OS.');
            }
            if (in_array($crt, [1, 2, 4], true)) {
                if (!in_array((string) $item['csosn'], ['102', '103', '300', '400'], true)) {
                    throw new InvalidArgumentException('O CSOSN informado exige regra ainda não implementada.');
                }
            } elseif (!in_array($this->normalIcmsCst($item), ['00', '40', '41', '50'], true)) {
                throw new InvalidArgumentException('O CST ICMS informado exige regra ainda não implementada.');
            }
            $this->assertPisCofinsItem($item, $crt);
            if ($requiresIbsCbs && (
                preg_match('/^\d{3}$/', (string) ($item['cst_ibs_cbs'] ?? '')) !== 1
                || preg_match('/^\d{6}$/', (string) ($item['classificacao_tributaria_ibs_cbs'] ?? '')) !== 1
            )) {
                throw new InvalidArgumentException(
                    'IBS/CBS é exigido para esta emissão. Complete CST IBS/CBS e cClassTrib '
                    . 'das peças utilizadas na OS antes de transmitir para a SEFAZ.'
                );
            }
        }
    }

    /**
     * Validação centralizada de PIS/COFINS.
     *
     * Regras efetivamente suportadas por este emissor:
     * - CST 01/02: tributação por alíquota percentual;
     * - CST 04..09: grupos não tributados;
     * - CST 99 no CRT 1: "Outras Operações", cálculo em valor zerado.
     *
     * CST 03 continua bloqueado porque exige qBCProd/vAliqProd reais.
     *
     * @param array<string,mixed> $item
     */
    private function assertPisCofinsItem(array $item, int $crt): void
    {
        $description = trim((string) ($item['descricao'] ?? ''));

        if ($description === '') {
            $description = trim((string) ($item['codigo'] ?? ''));
        }

        if ($description === '') {
            $description = 'produto sem descrição';
        }

        foreach (
            [
                ['field' => 'cst_pis', 'rate' => 'aliquota_pis', 'label' => 'PIS'],
                ['field' => 'cst_cofins', 'rate' => 'aliquota_cofins', 'label' => 'COFINS'],
            ] as $rule
        ) {
            $field = (string) $rule['field'];
            $rateField = (string) $rule['rate'];
            $label = (string) $rule['label'];

            $cst = trim((string) ($item[$field] ?? ''));
            $rateRaw = trim((string) ($item[$rateField] ?? '0'));
            $rate = is_numeric($rateRaw) ? (float) $rateRaw : 0.0;

            if (in_array($cst, ['01', '02'], true)) {
                continue;
            }

            if (in_array($cst, ['04', '05', '06', '07', '08', '09'], true)) {
                continue;
            }

            if ($crt === 1 && $cst === '99') {
                if (abs($rate) > 0.0000001) {
                    throw new InvalidArgumentException(
                        'Produto "' . $description . '": CST ' . $label
                        . ' 99 no Simples Nacional está implementado com cálculo em valor zerado. '
                        . 'A alíquota cadastrada deve ser 0,0000.'
                    );
                }

                continue;
            }

            if ($cst === '03') {
                throw new InvalidArgumentException(
                    'Produto "' . $description . '": CST ' . $label
                    . ' 03 exige tributação por quantidade (qBCProd/vAliqProd), '
                    . 'ainda não suportada pelo cadastro atual.'
                );
            }

            throw new InvalidArgumentException(
                'Produto "' . $description . '": CST ' . $label . ' '
                . ($cst !== '' ? $cst : '(vazio)')
                . ' não possui regra segura implementada para o CRT ' . $crt . '.'
            );
        }
    }

    /**
     * @param array<string,mixed> $company
     * @param array<string,mixed> $order
     * @param array<int,array<string,mixed>> $items
     * @return array{nature:string,destination:int,final_consumer:int,presence:int}
     */
    private function resolveOperation(array $company, array $order, array $items, string $model): array
    {
        $issuerUf = strtoupper((string) $company['endereco_uf']);
        $customerUf = strtoupper(trim((string) ($order['uf'] ?? '')));
        if ($customerUf !== '' && $customerUf !== $issuerUf) {
            throw new InvalidArgumentException(
                'Operação interestadual ainda não possui regra CFOP/ICMS homologada neste emissor.'
            );
        }
        foreach ($items as $item) {
            if (!str_starts_with((string) $item['cfop_padrao'], '5')) {
                throw new InvalidArgumentException('A operação interna exige CFOP iniciado por 5 em todas as peças.');
            }
        }
        return [
            'nature' => 'VENDA DE MERCADORIA',
            'destination' => 1,
            'final_consumer' => 1,
            'presence' => $model === '65' ? 1 : 9,
        ];
    }

    /** @param array<string,mixed> $item */
    private function normalIcmsCst(array $item): string
    {
        $cst = trim((string) ($item['cst_icms'] ?? ''));
        $origin = (string) ($item['origem_mercadoria'] ?? '');
        return preg_match('/^\d{3}$/', $cst) === 1 && $cst[0] === $origin
            ? substr($cst, 1)
            : $cst;
    }

    /** @param array<string,mixed>|null $existing */
    private function assertRejectedRetryModel(?array $existing, string $requestedModel): void
    {
        if ($existing !== null
            && (string) $existing['processamento_status'] === 'rejeitado'
            && (string) $existing['modelo'] !== $requestedModel
        ) {
            throw new InvalidArgumentException(
                'Documento rejeitado deve ser corrigido e reenviado no mesmo modelo fiscal.'
            );
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
