<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Repository\FiscalDocumentRepository;
use App\Fiscal\Storage\FiscalDocumentStorage;
use InvalidArgumentException;
use NFePHP\NFe\Complements;
use RuntimeException;
use Throwable;

final class FiscalAuthorizationService
{
    private readonly SefazResponseParser $responseParser;

    public function __construct(
        private readonly FiscalDocumentRepository $documents,
        private readonly FiscalDocumentXmlBuilder $builder,
        private readonly FiscalToolsFactory $toolsFactory,
        private readonly FiscalDocumentStorage $storage,
        ?SefazResponseParser $responseParser = null
    ) {
        $this->responseParser = $responseParser ?? new SefazResponseParser();
    }

    /** @return array{status:string,cstat:string,reason:string} */
    public function transmit(int $documentId, int $userId): array
    {
        $document = $this->documents->getById($documentId);
        $status = (string) $document['processamento_status'];
        if ($status === 'autorizado') {
            return ['status'=>'autorizado','cstat'=>(string)$document['cstat'],'reason'=>(string)$document['xmotivo']];
        }
        if (in_array($status, ['processando','pendente_reconsulta'], true)) {
            return $this->reconcile($documentId, $userId);
        }
        if ($status !== 'preparado') {
            throw new InvalidArgumentException('Este documento não está disponível para transmissão.');
        }

        $tools = $this->toolsFactory->create((int) $document['configuracao_id'], (string) $document['modelo']);
        $built = $this->builder->build($document);
        $signed = $tools->signNFe($built['xml']);
        $generatedArtifact = $this->storage->store(
            (string) $document['ambiente'], (string) $document['modelo'], $documentId, 'gerado', $built['xml']
        );
        $artifact = $this->storage->store(
            (string) $document['ambiente'], (string) $document['modelo'], $documentId, 'assinado', $signed
        );
        $batchId = str_pad((string) random_int(1, 999999999999999), 15, '0', STR_PAD_LEFT);
        $this->documents->transaction(function () use ($documentId, $built, $batchId, $generatedArtifact, $artifact, $userId): void {
            $locked = $this->documents->lockDocument($documentId);
            $this->documents->markSignedForTransmission($documentId, $built['key'], $batchId, $artifact);
            $this->documents->createTransmissionAttempt(
                $documentId,
                ((int)$locked['tentativas']) + 1,
                (string)$locked['snapshot_json'],
                $built['key'],
                $batchId,
                $generatedArtifact,
                $artifact,
                $userId
            );
            $this->documents->addEvent(
                $documentId, 'xml_assinado', (string) $locked['processamento_status'], 'processando', $userId,
                ['artifact_path'=>$artifact['reference'], 'artifact_hash'=>$artifact['sha256']]
            );
        });

        try {
            $response = $tools->sefazEnviaLote([$signed], $batchId, 1);
        } catch (Throwable $exception) {
            $this->documents->transaction(function () use ($documentId, $userId): void {
                $this->documents->markPendingReconciliation($documentId, 'Comunicação interrompida; reconsulta obrigatória.');
                $this->documents->updateLatestTransmissionAttempt(
                    $documentId, 'pendente_reconsulta', null, null, '', 'Comunicação interrompida; reconsulta obrigatória.'
                );
                $this->documents->addEvent(
                    $documentId, 'transmissao_inconclusiva', 'processando', 'pendente_reconsulta', $userId
                );
            });
            error_log('Fiscal transmission inconclusive [' . get_class($exception) . '].');
            return ['status'=>'pendente_reconsulta','cstat'=>'','reason'=>'A transmissão ficou inconclusiva e será reconsultada sem gerar outra nota.'];
        }

        return $this->applyResponse($document, $signed, $response, $userId);
    }

    /** @return array{status:string,cstat:string,reason:string} */
    public function reconcile(int $documentId, int $userId): array
    {
        $document = $this->documents->getById($documentId);
        if ($document['processamento_status'] === 'autorizado') {
            return ['status'=>'autorizado','cstat'=>(string)$document['cstat'],'reason'=>(string)$document['xmotivo']];
        }
        if (!in_array((string) $document['processamento_status'], ['processando','pendente_reconsulta'], true)) {
            throw new InvalidArgumentException('Este documento não está aguardando reconsulta.');
        }
        if ((string) ($document['cstat'] ?? '') === '539') {
            return [
                'status'=>'pendente_reconsulta',
                'cstat'=>'539',
                'reason'=>'Duplicidade com diferença na chave exige conferência manual da sequência; nenhuma retransmissão foi feita.',
            ];
        }
        $signed = $this->storage->read(
            (string) $document['xml_assinado_path'], (string) $document['xml_assinado_sha256']
        );
        $tools = $this->toolsFactory->create((int) $document['configuracao_id'], (string) $document['modelo']);
        try {
            if ((string) ($document['cstat'] ?? '') === '106'
                && preg_match('/^\d{44}$/', (string) $document['chave']) === 1
            ) {
                $response = $tools->sefazConsultaChave((string) $document['chave']);
            } elseif (trim((string) $document['recibo_sefaz']) !== '') {
                $response = $tools->sefazConsultaRecibo((string) $document['recibo_sefaz']);
            } elseif (preg_match('/^\d{44}$/', (string) $document['chave']) === 1) {
                $response = $tools->sefazConsultaChave((string) $document['chave']);
            } else {
                throw new RuntimeException('Fiscal document has no receipt or access key.');
            }
        } catch (Throwable $exception) {
            $this->documents->markPendingReconciliation($documentId, 'SEFAZ indisponível para reconsulta; tente novamente.');
            error_log('Fiscal reconciliation failed [' . get_class($exception) . '].');
            return ['status'=>'pendente_reconsulta','cstat'=>'','reason'=>'A SEFAZ ainda não respondeu à reconsulta.'];
        }
        return $this->applyResponse($document, $signed, $response, $userId);
    }

    /** @return array{status:string,cstat:string,reason:string} */
    public function cancel(int $documentId, string $justification, int $userId): array
    {
        $justification = trim($justification);
        if (mb_strlen($justification) < 15 || mb_strlen($justification) > 255
            || str_contains($justification, "\0") || $justification !== strip_tags($justification)
        ) {
            throw new InvalidArgumentException('Informe uma justificativa de cancelamento entre 15 e 255 caracteres.');
        }
        $document = $this->documents->getById($documentId);
        if ((string) $document['processamento_status'] === 'cancelado') {
            return ['status'=>'cancelado','cstat'=>(string)$document['cstat'],'reason'=>(string)$document['xmotivo']];
        }
        if (($document['cancelamento_status'] ?? 'nenhum') === 'pendente') {
            return $this->reconcileCancellation($documentId, $userId);
        }
        if ((string) $document['processamento_status'] !== 'autorizado'
            || preg_match('/^\d{44}$/', (string) $document['chave']) !== 1
            || trim((string) $document['protocolo']) === ''
        ) {
            throw new InvalidArgumentException('Somente documento fiscal autorizado pode ser cancelado.');
        }
        $tools = $this->toolsFactory->create((int) $document['configuracao_id'], (string) $document['modelo']);
        if (!$this->documents->claimCancellation($documentId)) {
            return $this->reconcileCancellation($documentId, $userId);
        }
        try {
            $response = $tools->sefazCancela(
                (string) $document['chave'], $justification, (string) $document['protocolo']
            );
        } catch (Throwable $exception) {
            $this->documents->markCancellationPending(
                $documentId,
                'Cancelamento inconclusivo; consulta de situação obrigatória antes de repetir.'
            );
            $this->documents->addEvent(
                $documentId,
                'cancelamento_inconclusivo',
                'autorizado',
                'autorizado',
                $userId
            );
            error_log('Fiscal cancellation inconclusive [' . get_class($exception) . '].');
            throw new InvalidArgumentException(
                'O cancelamento ficou inconclusivo. Consulte a situação na SEFAZ antes de repetir.'
            );
        }
        $artifact = $this->storage->store(
            (string) $document['ambiente'], (string) $document['modelo'], $documentId, 'cancelamento', $response
        );
        try {
            $event = $this->responseParser->cancellation($response);
        } catch (InvalidArgumentException) {
            $this->documents->markCancellationPending(
                $documentId,
                'Resposta de cancelamento inconclusiva; confirme o evento por consulta.',
                $artifact
            );
            return [
                'status'=>'autorizado',
                'cstat'=>'',
                'reason'=>'A resposta do cancelamento ficou inconclusiva; nenhuma nova solicitação será enviada.',
            ];
        }
        if (!$event['terminal']) {
            $type = $event['pending'] ? 'cancelamento_pendente_vinculo' : 'cancelamento_rejeitado';
            $this->documents->addEvent(
                $documentId, $type, 'autorizado', 'autorizado', $userId,
                ['cstat'=>$event['cstat'], 'reason'=>$event['reason'],
                    'artifact_path'=>$artifact['reference'], 'artifact_hash'=>$artifact['sha256']]
            );
            $reason = $event['pending']
                ? 'Evento recebido, mas o vínculo do cancelamento ainda precisa ser confirmado por consulta.'
                : $event['reason'];
            if ($event['pending']) {
                $this->documents->markCancellationPending($documentId, $event['reason'], $artifact);
            } else {
                $this->documents->releaseCancellationClaim($documentId, $event['reason'], $artifact);
            }
            return ['status'=>'autorizado','cstat'=>$event['cstat'],'reason'=>$reason];
        }
        $this->documents->transaction(function () use ($documentId, $event, $artifact, $userId): void {
            $this->documents->markCancelled(
                $documentId, $event['protocol'], $event['cstat'], $event['reason'], $artifact
            );
            $this->documents->addEvent(
                $documentId, 'cancelamento_autorizado', 'autorizado', 'cancelado', $userId,
                ['cstat'=>$event['cstat'], 'reason'=>$event['reason'],
                    'artifact_path'=>$artifact['reference'], 'artifact_hash'=>$artifact['sha256']]
            );
        });
        return ['status'=>'cancelado','cstat'=>$event['cstat'],'reason'=>$event['reason']];
    }

    /** @return array{status:string,cstat:string,reason:string} */
    public function reconcileCancellation(int $documentId, int $userId): array
    {
        $document = $this->documents->getById($documentId);
        if ((string) $document['processamento_status'] === 'cancelado') {
            return ['status'=>'cancelado','cstat'=>(string)$document['cstat'],'reason'=>(string)$document['xmotivo']];
        }
        if ((string) $document['processamento_status'] !== 'autorizado'
            || ($document['cancelamento_status'] ?? 'nenhum') !== 'pendente'
            || preg_match('/^\d{44}$/', (string) $document['chave']) !== 1
        ) {
            throw new InvalidArgumentException('Não existe cancelamento pendente para reconsulta.');
        }
        $tools = $this->toolsFactory->create((int) $document['configuracao_id'], (string) $document['modelo']);
        try {
            $response = $tools->sefazConsultaChave((string) $document['chave']);
        } catch (Throwable $exception) {
            FiscalSafeLogger::record($exception, 'cancellation_reconciliation');
            return [
                'status'=>'autorizado',
                'cstat'=>'',
                'reason'=>'A consulta do cancelamento continua inconclusiva; nenhuma nova solicitação foi enviada.',
            ];
        }
        $artifact = $this->storage->store(
            (string) $document['ambiente'], (string) $document['modelo'], $documentId, 'cancelamento', $response
        );
        try {
            $event = $this->responseParser->cancellation($response);
        } catch (InvalidArgumentException) {
            $this->documents->addEvent(
                $documentId, 'cancelamento_ainda_nao_confirmado', 'autorizado', 'autorizado', $userId,
                ['artifact_path'=>$artifact['reference'], 'artifact_hash'=>$artifact['sha256']]
            );
            return ['status'=>'autorizado','cstat'=>'','reason'=>'A SEFAZ ainda não confirmou o vínculo do cancelamento.'];
        }
        if (!$event['terminal']) {
            return ['status'=>'autorizado','cstat'=>$event['cstat'],'reason'=>$event['reason']];
        }
        $this->documents->transaction(function () use ($documentId, $event, $artifact, $userId): void {
            $this->documents->markCancelled(
                $documentId, $event['protocol'], $event['cstat'], $event['reason'], $artifact
            );
            $this->documents->addEvent(
                $documentId, 'cancelamento_confirmado_consulta', 'autorizado', 'cancelado', $userId,
                ['cstat'=>$event['cstat'], 'reason'=>$event['reason'],
                    'artifact_path'=>$artifact['reference'], 'artifact_hash'=>$artifact['sha256']]
            );
        });
        return ['status'=>'cancelado','cstat'=>$event['cstat'],'reason'=>$event['reason']];
    }

    /** @param array<string,mixed> $document @return array{status:string,cstat:string,reason:string} */
    private function applyResponse(array $document, string $signed, string $response, int $userId): array
    {
        $responseArtifact = $this->storage->store(
            (string) $document['ambiente'], (string) $document['modelo'], (int) $document['id'], 'resposta', $response
        );
        try {
            $result = $this->responseParser->authorization($response);
        } catch (InvalidArgumentException) {
            $reason = 'A resposta da SEFAZ foi preservada, mas não pôde ser interpretada; reconsulta obrigatória.';
            $this->documents->transaction(function () use ($document, $responseArtifact, $reason, $userId): void {
                $this->documents->markPendingReconciliation((int) $document['id'], $reason);
                $this->documents->updateLatestTransmissionAttempt(
                    (int) $document['id'], 'pendente_reconsulta', $responseArtifact, null, '', $reason
                );
                $this->documents->addEvent(
                    (int) $document['id'], 'resposta_sefaz_invalida',
                    (string) $document['processamento_status'], 'pendente_reconsulta', $userId,
                    ['artifact_path' => $responseArtifact['reference'], 'artifact_hash' => $responseArtifact['sha256']]
                );
            });
            return ['status' => 'pendente_reconsulta', 'cstat' => '', 'reason' => $reason];
        }
        $this->documents->storeResponse(
            (int) $document['id'], $responseArtifact, $result['receipt'], $result['cstat'], $result['reason']
        );
        if ($result['authorized']) {
            $authorized = Complements::toAuthorize($signed, $response);
            $authorizedArtifact = $this->storage->store(
                (string) $document['ambiente'], (string) $document['modelo'], (int) $document['id'], 'autorizado', $authorized
            );
            $this->documents->transaction(function () use ($document, $result, $responseArtifact, $authorizedArtifact, $userId): void {
                $this->documents->markAuthorized(
                    (int) $document['id'], $result['protocol'], $result['cstat'], $result['reason'], $authorizedArtifact
                );
                $this->documents->updateLatestTransmissionAttempt(
                    (int)$document['id'], 'autorizado', $responseArtifact, $result['receipt'], $result['cstat'], $result['reason']
                );
                $this->documents->addEvent(
                    (int) $document['id'], 'autorizacao_sefaz', (string) $document['processamento_status'],
                    'autorizado', $userId,
                    ['cstat'=>$result['cstat'], 'reason'=>$result['reason'],
                        'artifact_path'=>$authorizedArtifact['reference'], 'artifact_hash'=>$authorizedArtifact['sha256']]
                );
            });
            return ['status'=>'autorizado','cstat'=>$result['cstat'],'reason'=>$result['reason']];
        }
        if ($result['pending']) {
            $this->documents->markPendingReconciliation((int) $document['id'], $result['reason']);
            $this->documents->updateLatestTransmissionAttempt(
                (int)$document['id'], 'pendente_reconsulta', $responseArtifact, $result['receipt'], $result['cstat'], $result['reason']
            );
            return ['status'=>'pendente_reconsulta','cstat'=>$result['cstat'],'reason'=>$result['reason']];
        }
        $state = in_array($result['cstat'], ['110','205','301','302'], true) ? 'denegado' : 'rejeitado';
        $this->documents->transaction(function () use ($document, $result, $state, $responseArtifact, $userId): void {
            $this->documents->markRejected((int) $document['id'], $state, $result['cstat'], $result['reason']);
            $this->documents->updateLatestTransmissionAttempt(
                (int)$document['id'], $state, $responseArtifact, $result['receipt'], $result['cstat'], $result['reason']
            );
            $this->documents->addEvent(
                (int) $document['id'], 'retorno_sefaz', (string) $document['processamento_status'], $state, $userId,
                ['cstat'=>$result['cstat'], 'reason'=>$result['reason']]
            );
        });
        return ['status'=>$state,'cstat'=>$result['cstat'],'reason'=>$result['reason']];
    }

}
