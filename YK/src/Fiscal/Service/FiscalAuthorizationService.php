<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Repository\FiscalDocumentRepository;
use App\Fiscal\Storage\FiscalDocumentStorage;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use NFePHP\NFe\Complements;
use RuntimeException;
use Throwable;

final class FiscalAuthorizationService
{
    public function __construct(
        private readonly FiscalDocumentRepository $documents,
        private readonly FiscalDocumentXmlBuilder $builder,
        private readonly FiscalToolsFactory $toolsFactory,
        private readonly FiscalDocumentStorage $storage
    ) {
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
        $signed = $this->storage->read(
            (string) $document['xml_assinado_path'], (string) $document['xml_assinado_sha256']
        );
        $tools = $this->toolsFactory->create((int) $document['configuracao_id'], (string) $document['modelo']);
        try {
            if (trim((string) $document['recibo_sefaz']) !== '') {
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
        if ((string) $document['processamento_status'] !== 'autorizado'
            || preg_match('/^\d{44}$/', (string) $document['chave']) !== 1
            || trim((string) $document['protocolo']) === ''
        ) {
            throw new InvalidArgumentException('Somente documento fiscal autorizado pode ser cancelado.');
        }
        $tools = $this->toolsFactory->create((int) $document['configuracao_id'], (string) $document['modelo']);
        try {
            $response = $tools->sefazCancela(
                (string) $document['chave'], $justification, (string) $document['protocolo']
            );
        } catch (Throwable $exception) {
            error_log('Fiscal cancellation inconclusive [' . get_class($exception) . '].');
            throw new InvalidArgumentException(
                'O cancelamento ficou inconclusivo. Consulte a situação na SEFAZ antes de repetir.'
            );
        }
        $event = $this->parseCancellation($response);
        $artifact = $this->storage->store(
            (string) $document['ambiente'], (string) $document['modelo'], $documentId, 'cancelamento', $response
        );
        if (!in_array($event['cstat'], ['135','136','155'], true)) {
            $this->documents->addEvent(
                $documentId, 'cancelamento_rejeitado', 'autorizado', 'autorizado', $userId,
                ['cstat'=>$event['cstat'], 'reason'=>$event['reason'],
                    'artifact_path'=>$artifact['reference'], 'artifact_hash'=>$artifact['sha256']]
            );
            return ['status'=>'autorizado','cstat'=>$event['cstat'],'reason'=>$event['reason']];
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

    /** @return array{cstat:string,reason:string,protocol:string} */
    private function parseCancellation(string $xml): array
    {
        $dom = new DOMDocument();
        if ($xml === '' || !@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException('A SEFAZ retornou XML de cancelamento inválido.');
        }
        foreach ($dom->getElementsByTagName('infEvento') as $node) {
            if (!$node instanceof DOMElement) continue;
            $code = $this->child($node, 'cStat');
            if ($code === '') continue;
            return [
                'cstat'=>$code,
                'reason'=>substr($this->child($node, 'xMotivo'), 0, 255),
                'protocol'=>$this->child($node, 'nProt'),
            ];
        }
        throw new InvalidArgumentException('A SEFAZ retornou cancelamento sem protocolo de evento.');
    }
    /** @param array<string,mixed> $document @return array{status:string,cstat:string,reason:string} */
    private function applyResponse(array $document, string $signed, string $response, int $userId): array
    {
        $result = $this->parseResponse($response);
        $responseArtifact = $this->storage->store(
            (string) $document['ambiente'], (string) $document['modelo'], (int) $document['id'], 'resposta', $response
        );
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

    /** @return array{authorized:bool,pending:bool,cstat:string,reason:string,protocol:string,receipt:?string} */
    private function parseResponse(string $xml): array
    {
        $dom = new DOMDocument();
        if ($xml === '' || !@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException('A SEFAZ retornou XML inválido.');
        }
        $protocolNode = $dom->getElementsByTagName('infProt')->item(0);
        $protocolCode = $protocolNode instanceof DOMElement ? $this->child($protocolNode, 'cStat') : '';
        $outerCode = '';
        $outerReason = '';
        foreach ($dom->getElementsByTagName('cStat') as $node) {
            if (($node->parentNode?->localName ?? '') !== 'infProt') {
                $outerCode = trim((string) $node->nodeValue);
                $parent = $node->parentNode;
                $outerReason = $parent instanceof DOMElement ? $this->child($parent, 'xMotivo') : '';
                break;
            }
        }
        $code = $protocolCode !== '' ? $protocolCode : $outerCode;
        $reason = $protocolNode instanceof DOMElement ? $this->child($protocolNode, 'xMotivo') : $outerReason;
        $reason = substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($reason)) ?? ''), 0, 255);
        $receiptNode = $dom->getElementsByTagName('nRec')->item(0);
        $receipt = $receiptNode === null ? null : trim((string) $receiptNode->nodeValue);
        return [
            'authorized'=>in_array($protocolCode, ['100','150'], true),
            'pending'=>in_array($outerCode, ['103','105'], true),
            'cstat'=>$code, 'reason'=>$reason === '' ? 'Resposta fiscal sem motivo informado.' : $reason,
            'protocol'=>$protocolNode instanceof DOMElement ? $this->child($protocolNode, 'nProt') : '',
            'receipt'=>$receipt === '' ? null : $receipt,
        ];
    }

    private function child(DOMElement $element, string $name): string
    {
        $node = $element->getElementsByTagName($name)->item(0);
        return $node === null ? '' : trim((string) $node->nodeValue);
    }
}
