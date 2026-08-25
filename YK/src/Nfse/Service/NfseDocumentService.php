<?php

declare(strict_types=1);

namespace App\Nfse\Service;

use App\Fiscal\Repository\FiscalDocumentRepository;
use App\Fiscal\Service\FiscalRuntimeReadiness;
use App\Fiscal\Service\FiscalPaymentAllocator;
use App\Fiscal\Tax\Decimal;
use App\Nfse\Repository\NfseDocumentRepository;
use App\Nfse\Storage\NfseDocumentStorage;
use InvalidArgumentException;
use Throwable;

final class NfseDocumentService
{
    public function __construct(
        private readonly NfseDocumentRepository $documents,
        private readonly FiscalDocumentRepository $fiscalData,
        private readonly NfseProviderFactory $providers,
        private readonly NfseDocumentStorage $storage,
        private readonly FiscalRuntimeReadiness $runtime
    ) {
    }

    /** @return array<int,array{id:int,created:bool,status:string}> */
    public function prepareFromServiceOrder(int $orderId, string $environment, string $token, int $userId): array
    {
        if ($orderId <= 0 || $userId <= 0 || !in_array($environment, ['homologacao','producao'], true)
            || preg_match('/^[a-f0-9]{64}$/', $token) !== 1
        ) throw new InvalidArgumentException('Dados de preparação NFS-e inválidos.');
        $runtime = $this->runtime->inspect();
        if ($environment === 'producao' && !$runtime['production_allowed']) throw new InvalidArgumentException('A emissão NFS-e em produção está bloqueada.');

        return $this->documents->transaction(function () use ($orderId,$environment,$token,$userId): array {
            $order = $this->fiscalData->lockServiceOrderSnapshot($orderId);
            if (($order['status'] ?? '') !== 'finalizada' || $order['excluida_em'] !== null) {
                throw new InvalidArgumentException('A NFS-e exige uma OS finalizada e ativa.');
            }
            $items = $this->fiscalData->fiscalServiceItems($orderId);
            if ($items === []) throw new InvalidArgumentException('Esta OS não possui serviços para NFS-e.');
            $company = $this->fiscalData->companySnapshot();
            $municipality = preg_replace('/\D+/', '', (string)($company['codigo_municipio_ibge'] ?? '')) ?? '';
            $profile = $this->documents->lockConfigurationAndSeries($environment, $municipality);
            $groups = $this->groupItems($items);
            ksort($groups, SORT_STRING);
            $payments = $this->fiscalData->activePayments($orderId);
            $productOffset = 0;
            foreach ($this->fiscalData->fiscalProductItems($orderId) as $product) {
                $productOffset += Decimal::moneyToCents((string)$product['subtotal']);
            }
            $serviceOffset = $productOffset;
            $results = [];
            foreach ($groups as $hash=>$groupItems) {
                $total = 0;
                foreach ($groupItems as $item) $total += Decimal::moneyToCents((string)$item['subtotal']);
                $allocatedPayments = (new FiscalPaymentAllocator())->allocate($payments, $serviceOffset, $total);
                $groupToken = hash('sha256', $token . ':' . $hash);
                $existing = $this->documents->findByIdempotency($groupToken, true);
                if ($existing !== null) {
                    $results[] = ['id'=>(int)$existing['id'],'created'=>false,'status'=>(string)$existing['status']];
                    $serviceOffset += $total;
                    continue;
                }
                $series = $profile['series'];
                $number = (int)$series['proximo_numero'];
                $this->documents->reserveNumber((int)$series['id'], $number);
                $profile['series']['proximo_numero'] = $number + 1;
                $snapshot = [
                    'schema'=>1,'captured_at'=>date(DATE_ATOM),'company'=>$company,'customer'=>$order,
                    'service_order'=>['id'=>$orderId,'number'=>(string)$order['numero']],
                    'items'=>$groupItems,
                    'payments'=>$allocatedPayments,
                    'fiscal'=>[
                        'environment'=>$environment,'schema_version'=>(string)$profile['configuration']['schema_versao'],
                        'provider_version'=>(string)$profile['configuration']['provider_versao'],
                        'series'=>(string)$series['serie_dps'],'number'=>(string)$number,
                        'municipality'=>$municipality,'issued_at'=>date(DATE_ATOM),
                        'competence_date'=>date('Y-m-d'),
                        'dps_id'=>$this->dpsId($municipality, (string)$company['documento'], (string)$series['serie_dps'], $number),
                    ],
                    'totals'=>['services'=>Decimal::formatCents($total)],
                ];
                $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
                $id = $this->documents->insertPrepared([
                    'order_id'=>$orderId,'configuration_id'=>$profile['configuration']['id'],
                    'series_id'=>$series['id'],'series'=>$series['serie_dps'],'number'=>$number,
                    'group_hash'=>$hash,'idempotency_key'=>$groupToken,'environment'=>$environment,
                    'municipality'=>$municipality,'value'=>Decimal::formatCents($total),
                    'snapshot'=>$snapshotJson,'user_id'=>$userId,
                ], $groupItems);
                $this->documents->addEvent($id, 'dps_preparada', null, 'preparado', $userId);
                $this->fiscalData->persistPaymentAllocations($orderId, 'nfse', $id, $allocatedPayments);
                $results[] = ['id'=>$id,'created'=>true,'status'=>'preparado'];
                $serviceOffset += $total;
            }
            return $results;
        });
    }

    /** @return array{status:string,protocol:?string,message:string} */
    public function transmit(int $documentId, int $userId): array
    {
        $runtimeReadiness = $this->runtime->inspect();
        if (!$runtimeReadiness['homologation_ready']) {
            throw new InvalidArgumentException('A transmissão NFS-e está bloqueada até o servidor fiscal ficar pronto.');
        }
        $document = $this->documents->get($documentId);
        if ($document['ambiente'] === 'producao' && !$runtimeReadiness['production_allowed']) {
            throw new InvalidArgumentException('A transmissão NFS-e em produção está bloqueada.');
        }
        if ($document['status'] === 'autorizado') return ['status'=>'autorizado','protocol'=>$document['protocolo'],'message'=>(string)$document['mensagem']];
        if ($document['status'] === 'aguardando_validacao') return $this->reconcile($documentId, $userId);
        if ($document['status'] !== 'preparado') throw new InvalidArgumentException('Esta DPS não está disponível para transmissão.');
        $snapshot = json_decode((string)$document['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        $profile = $this->documents->transmissionProfile($documentId);
        $runtime = $this->providers->create($profile);
        $xml = (new DpsXmlBuilder())->build($snapshot);
        (new DpsSchemaValidator())->validate($xml, $runtime['schema_path']);
        $signed = (new DpsXmlSigner())->sign($xml, $runtime['certificate']);
        (new DpsSchemaValidator())->validate($signed, $runtime['schema_path']);
        $generated = $this->storage->store((string)$document['ambiente'], $documentId, 'dps_gerada', $xml);
        $signedArtifact = $this->storage->store((string)$document['ambiente'], $documentId, 'dps_assinada', $signed);
        $attempt = $this->documents->transaction(function () use ($document,$documentId,$userId,$generated,$signedArtifact): int {
            $locked = $this->documents->get($documentId, true);
            if ($locked['status'] !== 'preparado') throw new InvalidArgumentException('A DPS já está sendo processada.');
            $attempt = $this->documents->createAttempt($documentId, (string)$document['snapshot_json'], $userId);
            $this->documents->addArtifact($documentId, $attempt, 'dps_gerada', $generated);
            $this->documents->addArtifact($documentId, $attempt, 'dps_assinada', $signedArtifact);
            return $attempt;
        });
        try {
            $result = $runtime['provider']->submit($signed);
        } catch (Throwable) {
            $message = 'Transmissão inconclusiva. Não reenvie: confirme o protocolo com a Betha antes de nova tentativa.';
            $this->documents->transaction(function () use ($documentId,$attempt,$message,$userId): void {
                $this->documents->markTechnicalFailure($documentId, $message);
                $this->documents->updateAttempt($attempt, 'erro_tecnico', null, null, $message);
                $this->documents->addEvent($documentId, 'transmissao_inconclusiva', 'preparado', 'erro_tecnico', $userId);
            });
            return ['status'=>'erro_tecnico','protocol'=>null,'message'=>$message];
        }
        $response = $this->storage->store((string)$document['ambiente'], $documentId, 'resposta_recepcao', $result->rawResponse);
        $this->documents->transaction(function () use ($documentId,$attempt,$result,$response,$userId): void {
            $this->documents->addArtifact($documentId, $attempt, 'resposta_recepcao', $response);
            $this->documents->markSubmitted($documentId, (string)$result->protocol, $result->status, $result->message);
            $this->documents->updateAttempt($attempt, $result->status, $result->protocol, $result->code, $result->message);
            $this->documents->addEvent($documentId, 'dps_recebida', 'preparado', $result->status, $userId,
                ['code'=>$result->code,'message'=>$result->message]);
        });
        return ['status'=>$result->status,'protocol'=>$result->protocol,'message'=>$result->message];
    }

    /** @return array{status:string,protocol:?string,message:string} */
    public function reconcile(int $documentId, int $userId): array
    {
        $document = $this->documents->get($documentId);
        if ($document['status'] === 'autorizado') return ['status'=>'autorizado','protocol'=>$document['protocolo'],'message'=>(string)$document['mensagem']];
        if ($document['status'] !== 'aguardando_validacao' || trim((string)$document['protocolo']) === '') {
            throw new InvalidArgumentException('Esta DPS não está aguardando consulta.');
        }
        $profile = $this->documents->transmissionProfile($documentId);
        $runtime = $this->providers->create($profile);
        $snapshot = json_decode((string)$document['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        $result = $runtime['provider']->query([
            'environment'=>(string)$document['ambiente'],'municipality'=>(string)$document['municipio_ibge'],
            'provider_document'=>(string)$snapshot['company']['documento'],'protocol'=>(string)$document['protocolo'],
        ]);
        $response = $this->storage->store((string)$document['ambiente'], $documentId, 'resposta_consulta', $result->rawResponse);
        $this->documents->transaction(function () use ($documentId,$document,$result,$response,$userId): void {
            $this->documents->addArtifact($documentId, null, 'resposta_consulta', $response);
            $this->documents->markQueried($documentId, [
                'status'=>$result->status,'provider_status'=>$result->status,'message'=>$result->message,
                'provider_id'=>$result->providerDpsId,'access_key'=>$result->accessKey,
                'invoice_number'=>$result->invoiceNumber,'pdf_url'=>$result->pdfUrl,
            ]);
            $this->documents->updateLatestAttempt($documentId, $result->status, $result->protocol, $result->code, $result->message);
            $this->documents->addEvent($documentId, 'dps_consultada', (string)$document['status'], $result->status, $userId,
                ['code'=>$result->code,'message'=>$result->message]);
        });
        return ['status'=>$result->status,'protocol'=>$result->protocol,'message'=>$result->message];
    }

    /** @return array<int,array<string,mixed>> */
    public function list(): array { return $this->documents->list(); }

    /** @param array<int,array<string,mixed>> $items @return array<string,array<int,array<string,mixed>>> */
    private function groupItems(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            foreach (['codigo_tributacao_nacional','nbs','municipio_incidencia_ibge'] as $field) {
                if (trim((string)($item[$field] ?? '')) === '') throw new InvalidArgumentException('Serviço sem dado fiscal NFS-e: ' . $field . '.');
            }
            $profile = [];
            foreach (['codigo_tributacao_nacional','nbs','municipio_incidencia_ibge','aliquota_iss','iss_retido',
                'cst_pis_servico','cst_cofins_servico','aliquota_pis_servico','aliquota_cofins_servico',
                'cst_ibs_cbs','classificacao_tributaria_ibs_cbs','cindop','finalidade_nfse','tipo_operacao'] as $field) {
                $profile[$field] = $item[$field] ?? null;
            }
            $groups[hash('sha256', json_encode($profile, JSON_THROW_ON_ERROR))][] = $item;
        }
        return $groups;
    }

    private function dpsId(string $municipality, string $document, string $series, int $number): string
    {
        $document = preg_replace('/\D+/', '', $document) ?? '';
        if (strlen($document) !== 14 || preg_match('/^\d{1,5}$/', $series) !== 1 || $number <= 0) {
            throw new InvalidArgumentException('CNPJ, série ou número incompatível com o identificador DPS Betha.');
        }
        return 'DPS' . $municipality . '2' . $document
            . str_pad($series, 5, '0', STR_PAD_LEFT) . str_pad((string)$number, 15, '0', STR_PAD_LEFT);
    }
}
