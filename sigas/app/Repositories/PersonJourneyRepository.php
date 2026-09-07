<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use PDO;
use PDOException;

final class PersonJourneyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByReference(string $module, string $type, int $referenceId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM pessoa_atendimentos
                 WHERE referencia_modulo = :modulo
                   AND referencia_tipo = :tipo
                   AND referencia_id = :referencia_id
                 LIMIT 1'
            );
            $stmt->execute([
                'modulo' => $module,
                'tipo' => $type,
                'referencia_id' => $referenceId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            $this->logCompatibility('findByReference', $exception);
            return null;
        }
    }

    /** @param array<string,mixed> $data */
    public function insertAttendance(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO pessoa_atendimentos
                    (pessoa_id, protocolo, finalidade, beneficio_modulo,
                     setor_origem_id, modulo_origem, setor_atual_id, modulo_atual,
                     usuario_abertura_id, status, referencia_modulo, referencia_tipo,
                     referencia_id, observacao)
                 VALUES
                    (:pessoa_id, :protocolo, :finalidade, :beneficio_modulo,
                     :setor_origem_id, :modulo_origem, :setor_atual_id, :modulo_atual,
                     :usuario_abertura_id, :status, :referencia_modulo, :referencia_tipo,
                     :referencia_id, :observacao)'
            );
            $stmt->execute($data);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw $this->fail('insertAttendance', 'Falha ao iniciar rastreabilidade do atendimento.', $exception);
        }
    }

    /** @param array<string,mixed> $data */
    public function insertMovement(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO pessoa_movimentacoes
                    (atendimento_id, pessoa_id, tipo, setor_origem_id, setor_destino_id,
                     modulo_origem, modulo_destino, usuario_id, observacao)
                 VALUES
                    (:atendimento_id, :pessoa_id, :tipo, :setor_origem_id, :setor_destino_id,
                     :modulo_origem, :modulo_destino, :usuario_id, :observacao)'
            );
            $stmt->execute($data);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw $this->fail('insertMovement', 'Falha ao registrar movimentação da pessoa.', $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findAttendance(int $attendanceId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM pessoa_atendimentos WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $attendanceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            throw $this->fail('findAttendance', 'Falha ao localizar atendimento.', $exception);
        }
    }

    public function updateCurrentLocation(
        int $attendanceId,
        ?int $sectorId,
        string $module,
        string $status,
        bool $complete = false,
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE pessoa_atendimentos
                 SET setor_atual_id = :setor_atual_id,
                     modulo_atual = :modulo_atual,
                     status = :status,
                     concluido_em = CASE WHEN :concluir = 1 THEN CURRENT_TIMESTAMP ELSE concluido_em END
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $attendanceId,
                'setor_atual_id' => $sectorId,
                'modulo_atual' => $module,
                'status' => $status,
                'concluir' => $complete ? 1 : 0,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('updateCurrentLocation', 'Falha ao atualizar localização do atendimento.', $exception);
        }
    }

    /** @return list<array<string,mixed>> */
    public function historyByPerson(int $personId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT
                    a.id AS atendimento_id,
                    a.protocolo,
                    a.finalidade,
                    a.beneficio_modulo,
                    a.status AS atendimento_status,
                    a.aberto_em,
                    a.concluido_em,
                    so.nome AS setor_origem,
                    sa.nome AS setor_atual,
                    a.modulo_origem,
                    a.modulo_atual,
                    m.id AS movimentacao_id,
                    m.tipo AS movimentacao_tipo,
                    m.modulo_origem AS movimentacao_modulo_origem,
                    m.modulo_destino AS movimentacao_modulo_destino,
                    s1.nome AS movimentacao_setor_origem,
                    s2.nome AS movimentacao_setor_destino,
                    u.nome AS movimentacao_usuario,
                    m.observacao AS movimentacao_observacao,
                    m.criado_em AS movimentacao_em
                 FROM pessoa_atendimentos a
                 LEFT JOIN setores so ON so.id = a.setor_origem_id
                 LEFT JOIN setores sa ON sa.id = a.setor_atual_id
                 LEFT JOIN pessoa_movimentacoes m ON m.atendimento_id = a.id
                 LEFT JOIN setores s1 ON s1.id = m.setor_origem_id
                 LEFT JOIN setores s2 ON s2.id = m.setor_destino_id
                 LEFT JOIN usuarios u ON u.id = m.usuario_id
                 WHERE a.pessoa_id = :pessoa_id
                 ORDER BY a.aberto_em DESC, m.criado_em ASC, m.id ASC'
            );
            $stmt->execute(['pessoa_id' => $personId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            $this->logCompatibility('historyByPerson', $exception);
            return [];
        }
    }

    private function logCompatibility(string $operation, PDOException $exception): void
    {
        Logger::application('Person journey compatibility fallback.', [
            'repository' => self::class,
            'operation' => $operation,
            'code' => $exception->getCode(),
        ]);
    }

    private function fail(string $operation, string $message, PDOException $exception): RepositoryException
    {
        Logger::application('Person journey repository failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);
        return new RepositoryException($message, 0, $exception);
    }
}
