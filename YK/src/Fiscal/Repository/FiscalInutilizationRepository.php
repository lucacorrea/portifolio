<?php

declare(strict_types=1);

namespace App\Fiscal\Repository;

use InvalidArgumentException;
use PDO;
use Throwable;

final class FiscalInutilizationRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByIdempotencyKey(string $key): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM fiscal_inutilizacoes WHERE idempotency_key = :key LIMIT 1'
        );
        $statement->execute(['key' => $key]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed> */
    public function getById(int $id): array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM fiscal_inutilizacoes WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new InvalidArgumentException('Inutilização fiscal não encontrada.');
        }
        return $row;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        return $this->transaction(function () use ($data): int {
            $series = $this->connection->prepare(
                'SELECT id, proximo_numero FROM fiscal_series
                  WHERE ambiente = :environment AND modelo = :model AND serie = :series
                    AND status = \'ativa\' LIMIT 1 FOR UPDATE'
            );
            $series->execute([
                'environment' => $data['environment'],
                'model' => $data['model'],
                'series' => $data['series'],
            ]);
            $seriesRow = $series->fetch();
            if ($seriesRow === false) {
                throw new InvalidArgumentException('A série informada não está ativa neste ambiente e modelo.');
            }
            if ((int) $data['end_number'] >= (int) $seriesRow['proximo_numero']) {
                throw new InvalidArgumentException('A faixa deve estar abaixo do próximo número confirmado da série.');
            }

            $documents = $this->connection->prepare(
                'SELECT numero, processamento_status FROM documentos_fiscais
                  WHERE ambiente = :environment AND modelo = :model AND serie = :series
                    AND numero BETWEEN :start_number AND :end_number
                  LIMIT 1 FOR UPDATE'
            );
            $documents->execute([
                'environment' => $data['environment'],
                'model' => $data['model'],
                'series' => $data['series'],
                'start_number' => $data['start_number'],
                'end_number' => $data['end_number'],
            ]);
            if ($documents->fetch() !== false) {
                throw new InvalidArgumentException('A faixa contém número já reservado ou usado pelo sistema.');
            }

            $overlap = $this->connection->prepare(
                'SELECT id FROM fiscal_inutilizacoes
                  WHERE ambiente = :environment AND modelo = :model AND serie = :series AND ano = :year
                    AND status IN (\'processando\',\'pendente_confirmacao\',\'autorizado\')
                    AND numero_inicial <= :end_number AND numero_final >= :start_number
                  LIMIT 1 FOR UPDATE'
            );
            $overlap->execute([
                'environment' => $data['environment'],
                'model' => $data['model'],
                'series' => $data['series'],
                'year' => $data['year'],
                'start_number' => $data['start_number'],
                'end_number' => $data['end_number'],
            ]);
            if ($overlap->fetch() !== false) {
                throw new InvalidArgumentException('A faixa coincide com outra inutilização já registrada.');
            }

            $statement = $this->connection->prepare(
                'INSERT INTO fiscal_inutilizacoes
                    (ambiente,modelo,configuracao_id,serie_id,serie,ano,numero_inicial,numero_final,
                     justificativa,idempotency_key,status,criado_por)
                 VALUES
                    (:environment,:model,:configuration_id,:series_id,:series,:year,:start_number,:end_number,
                     :justification,:idempotency_key,\'processando\',:user_id)'
            );
            $statement->execute([
                'environment' => $data['environment'],
                'model' => $data['model'],
                'configuration_id' => $data['configuration_id'],
                'series_id' => $seriesRow['id'],
                'series' => $data['series'],
                'year' => $data['year'],
                'start_number' => $data['start_number'],
                'end_number' => $data['end_number'],
                'justification' => $data['justification'],
                'idempotency_key' => $data['idempotency_key'],
                'user_id' => $data['user_id'],
            ]);
            return (int) $this->connection->lastInsertId();
        });
    }

    /** @param array{reference:string,sha256:string}|null $request @param array{reference:string,sha256:string}|null $response */
    public function finish(
        int $id,
        string $status,
        string $cstat,
        string $reason,
        string $protocol,
        ?array $request,
        ?array $response
    ): void {
        if (!in_array($status, ['pendente_confirmacao', 'autorizado', 'rejeitado'], true)) {
            throw new InvalidArgumentException('Estado de inutilização inválido.');
        }
        $statement = $this->connection->prepare(
            'UPDATE fiscal_inutilizacoes
                SET status=:status,cstat=:cstat,xmotivo=:reason,protocolo=:protocol,
                    pedido_path=COALESCE(:request_path,pedido_path),
                    pedido_sha256=COALESCE(:request_hash,pedido_sha256),
                    resposta_path=COALESCE(:response_path,resposta_path),
                    resposta_sha256=COALESCE(:response_hash,resposta_sha256),
                    finalizado_em=CASE WHEN :terminal=1 THEN CURRENT_TIMESTAMP ELSE NULL END
              WHERE id=:id AND status IN (\'processando\',\'pendente_confirmacao\')'
        );
        $statement->execute([
            'id' => $id,
            'status' => $status,
            'cstat' => $cstat === '' ? null : $cstat,
            'reason' => substr($reason, 0, 255),
            'protocol' => $protocol === '' ? null : $protocol,
            'request_path' => $request['reference'] ?? null,
            'request_hash' => $request['sha256'] ?? null,
            'response_path' => $response['reference'] ?? null,
            'response_hash' => $response['sha256'] ?? null,
            'terminal' => $status === 'pendente_confirmacao' ? 0 : 1,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(string $environment, string $model, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $statement = $this->connection->prepare(
            'SELECT id,ambiente,modelo,serie,ano,numero_inicial,numero_final,status,cstat,xmotivo,protocolo,criado_em
               FROM fiscal_inutilizacoes
              WHERE ambiente=:environment AND modelo=:model
              ORDER BY id DESC LIMIT ' . $limit
        );
        $statement->execute(['environment' => $environment, 'model' => $model]);
        return $statement->fetchAll();
    }

    private function transaction(callable $callback): mixed
    {
        $this->connection->beginTransaction();
        try {
            $result = $callback();
            $this->connection->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
