<?php

declare(strict_types=1);

namespace App\Nfse\Repository;

use InvalidArgumentException;
use PDO;
use Throwable;

final class NfseDocumentRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @template T @param callable():T $operation @return T */
    public function transaction(callable $operation): mixed
    {
        $this->connection->beginTransaction();
        try {
            $result = $operation();
            $this->connection->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    /** @return array<string,mixed>|null */
    public function findByIdempotency(string $key, bool $lock = false): ?array
    {
        $sql = 'SELECT * FROM nfse_documentos WHERE idempotency_key=:key LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $statement = $this->connection->prepare($sql);
        $statement->execute(['key'=>$key]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @return array{configuration:array<string,mixed>,series:array<string,mixed>} */
    public function lockConfigurationAndSeries(string $environment, string $municipality): array
    {
        $statement = $this->connection->prepare(
            'SELECT config.*, cert.arquivo_referencia, cert.senha_ciphertext, cert.senha_nonce,
                    cert.senha_tag, cert.cifra_algoritmo, cert.chave_versao,
                    cert.status AS certificado_status, cert.valido_ate
               FROM nfse_configuracoes config JOIN fiscal_certificados cert ON cert.id=config.certificado_id
              WHERE config.provedor=\'betha_fly\' AND config.ambiente=:environment
                AND config.municipio_ibge=:municipality AND config.status=\'ativa\'
              LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['environment'=>$environment,'municipality'=>$municipality]);
        $configuration = $statement->fetch();
        if ($configuration === false) throw new InvalidArgumentException('Ative a configuração NFS-e Betha para este ambiente e município.');
        $series = $this->connection->prepare(
            'SELECT * FROM nfse_series WHERE configuracao_id=:configuration_id AND status=\'ativa\'
              ORDER BY id LIMIT 1 FOR UPDATE'
        );
        $series->execute(['configuration_id'=>$configuration['id']]);
        $seriesRow = $series->fetch();
        if ($seriesRow === false) throw new InvalidArgumentException('Cadastre uma série DPS ativa.');
        return ['configuration'=>$configuration,'series'=>$seriesRow];
    }

    public function reserveNumber(int $seriesId, int $number): void
    {
        $statement = $this->connection->prepare(
            'UPDATE nfse_series SET ultimo_numero_reservado=:number, proximo_numero=:next
              WHERE id=:id AND proximo_numero=:number'
        );
        $statement->execute(['id'=>$seriesId,'number'=>$number,'next'=>$number+1]);
        if ($statement->rowCount() !== 1) throw new InvalidArgumentException('A numeração DPS foi alterada por outra emissão.');
    }

    /** @param array<string,mixed> $data @param array<int,array<string,mixed>> $items */
    public function insertPrepared(array $data, array $items): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO nfse_documentos
                (ordem_servico_id,configuracao_id,serie_id,serie_dps,numero_dps,grupo_fiscal_hash,
                 idempotency_key,provedor,ambiente,municipio_ibge,status,valor_servicos,snapshot_json,emitido_por)
             VALUES (:order_id,:configuration_id,:series_id,:series,:number,:group_hash,
                 :idempotency_key,\'betha_fly\',:environment,:municipality,\'preparado\',:value,:snapshot,:user_id)'
        );
        $statement->execute($data);
        $id = (int)$this->connection->lastInsertId();
        $itemStatement = $this->connection->prepare(
            'INSERT INTO nfse_documento_itens
                (nfse_documento_id,ordem_servico_item_id,servico_id,valor,snapshot_json)
             VALUES (:document_id,:order_item_id,:service_id,:value,:snapshot)'
        );
        foreach ($items as $item) {
            $itemStatement->execute([
                'document_id'=>$id,'order_item_id'=>$item['id'],'service_id'=>$item['servico_id'] ?: null,
                'value'=>$item['subtotal'],'snapshot'=>json_encode($item, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            ]);
        }
        return $id;
    }

    /** @return array<string,mixed> */
    public function get(int $id, bool $lock = false): array
    {
        $statement = $this->connection->prepare('SELECT * FROM nfse_documentos WHERE id=:id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['id'=>$id]);
        $row = $statement->fetch();
        if ($row === false) throw new InvalidArgumentException('Documento NFS-e não encontrado.');
        return $row;
    }

    /** @return array<string,mixed> */
    public function transmissionProfile(int $documentId): array
    {
        $statement = $this->connection->prepare(
            'SELECT config.*, cert.arquivo_referencia, cert.senha_ciphertext, cert.senha_nonce,
                    cert.senha_tag, cert.cifra_algoritmo, cert.chave_versao,
                    cert.status AS certificado_status, cert.valido_ate
               FROM nfse_documentos doc JOIN nfse_configuracoes config ON config.id=doc.configuracao_id
               JOIN fiscal_certificados cert ON cert.id=config.certificado_id
              WHERE doc.id=:id LIMIT 1'
        );
        $statement->execute(['id'=>$documentId]);
        $row = $statement->fetch();
        if ($row === false) throw new InvalidArgumentException('Configuração do documento NFS-e não encontrada.');
        return $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function list(int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        return $this->connection->query(
            'SELECT doc.*, os.numero AS os_numero, client.nome AS cliente_nome
               FROM nfse_documentos doc JOIN ordens_servico os ON os.id=doc.ordem_servico_id
               JOIN clientes client ON client.id=os.cliente_id
              ORDER BY doc.criado_em DESC, doc.id DESC LIMIT ' . $limit
        )->fetchAll();
    }

    public function markSubmitted(int $id, string $protocol, string $status, string $message): void
    {
        $statement = $this->connection->prepare(
            'UPDATE nfse_documentos SET protocolo=:protocol,status=:status,status_provedor=:provider_status,
                    mensagem=:message WHERE id=:id AND status IN (\'preparado\',\'assinado\',\'erro_tecnico\')'
        );
        $statement->execute(['id'=>$id,'protocol'=>$protocol,'status'=>$status,'provider_status'=>$status,'message'=>substr($message,0,500)]);
    }

    public function markTechnicalFailure(int $id, string $message): void
    {
        $statement = $this->connection->prepare(
            'UPDATE nfse_documentos SET status=\'erro_tecnico\',status_provedor=\'inconclusivo\',mensagem=:message
              WHERE id=:id AND status=\'preparado\''
        );
        $statement->execute(['id'=>$id,'message'=>substr($message,0,500)]);
    }

    public function updateAttempt(int $attemptId, string $status, ?string $protocol, ?string $code, string $message): void
    {
        $statement = $this->connection->prepare(
            'UPDATE nfse_tentativas SET status=:status,protocolo=:protocol,codigo=:code,mensagem=:message,
                    finalizado_em=CASE WHEN :terminal=1 THEN CURRENT_TIMESTAMP ELSE finalizado_em END
              WHERE id=:id'
        );
        $statement->execute([
            'id'=>$attemptId,
            'status'=>$status,
            'protocol'=>$protocol,
            'code'=>$code,
            'message'=>substr($message,0,500),
            'terminal'=>in_array($status, ['autorizado','rejeitado_estrutura','rejeitado_regra','erro_tecnico'], true) ? 1 : 0,
        ]);
    }

    public function updateLatestAttempt(int $documentId, string $status, ?string $protocol, ?string $code, string $message): void
    {
        $statement = $this->connection->prepare(
            'SELECT id FROM nfse_tentativas WHERE nfse_documento_id=:document_id
              ORDER BY numero_tentativa DESC LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['document_id'=>$documentId]);
        $attemptId = (int)$statement->fetchColumn();
        if ($attemptId > 0) $this->updateAttempt($attemptId, $status, $protocol, $code, $message);
    }

    /** @param array<string,mixed> $data */
    public function markQueried(int $id, array $data): void
    {
        $statement = $this->connection->prepare(
            'UPDATE nfse_documentos SET status=:status,status_provedor=:provider_status,mensagem=:message,
                    id_dps_provedor=COALESCE(:provider_id,id_dps_provedor),
                    chave_nfse=COALESCE(:access_key,chave_nfse),numero_nfse=COALESCE(:invoice_number,numero_nfse),
                    pdf_url=COALESCE(:pdf_url,pdf_url),
                    autorizado_em=CASE WHEN :authorized=1 THEN CURRENT_TIMESTAMP ELSE autorizado_em END
              WHERE id=:id'
        );
        $statement->execute($data + ['id'=>$id,'authorized'=>$data['status']==='autorizado'?1:0]);
    }

    /** @param array<string,mixed> $details */
    public function addEvent(int $id, string $type, ?string $previous, string $next, int $userId, array $details=[]): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO nfse_eventos (nfse_documento_id,tipo,status_anterior,status_novo,codigo,mensagem,usuario_id)
             VALUES (:id,:type,:previous,:next,:code,:message,:user_id)'
        );
        $statement->execute(['id'=>$id,'type'=>$type,'previous'=>$previous,'next'=>$next,
            'code'=>$details['code']??null,'message'=>$details['message']??null,'user_id'=>$userId]);
    }

    /** @param array{reference:string,sha256:string} $artifact */
    public function addArtifact(int $documentId, ?int $attemptId, string $type, array $artifact): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO nfse_artifacts (nfse_documento_id,tentativa_id,tipo,path,sha256)
             VALUES (:document_id,:attempt_id,:type,:path,:sha256)'
        );
        $statement->execute(['document_id'=>$documentId,'attempt_id'=>$attemptId,'type'=>$type,
            'path'=>$artifact['reference'],'sha256'=>$artifact['sha256']]);
    }

    public function createAttempt(int $documentId, string $snapshot, int $userId): int
    {
        $counter = $this->connection->prepare(
            'SELECT proxima_tentativa FROM nfse_documentos WHERE id=:id LIMIT 1 FOR UPDATE'
        );
        $counter->execute(['id'=>$documentId]);
        $number = (int)$counter->fetchColumn();
        if ($number <= 0) throw new InvalidArgumentException('Contador de tentativas NFS-e inválido.');
        $advance = $this->connection->prepare(
            'UPDATE nfse_documentos SET proxima_tentativa=:next WHERE id=:id AND proxima_tentativa=:number'
        );
        $advance->execute(['id'=>$documentId,'number'=>$number,'next'=>$number+1]);
        if ($advance->rowCount() !== 1) throw new InvalidArgumentException('A tentativa NFS-e foi alterada por outra transmissão.');
        $statement = $this->connection->prepare(
            'INSERT INTO nfse_tentativas (nfse_documento_id,numero_tentativa,snapshot_json,status,criado_por)
             VALUES (:document_id,:number,:snapshot,\'preparado\',:user_id)'
        );
        $statement->execute(['document_id'=>$documentId,'number'=>$number,'snapshot'=>$snapshot,'user_id'=>$userId]);
        return (int)$this->connection->lastInsertId();
    }
}
