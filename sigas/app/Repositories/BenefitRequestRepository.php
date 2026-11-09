<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\RepositoryException;
use PDO;
use PDOException;

final class BenefitRequestRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT b.*, p.nome AS pessoa_nome, p.cpf AS pessoa_cpf,
                        u.nome AS responsavel_nome, s.nome AS setor_origem_nome
                 FROM beneficio_solicitacoes b
                 INNER JOIN pessoas p ON p.id = b.pessoa_id
                 LEFT JOIN usuarios u ON u.id = b.responsavel_usuario_id
                 LEFT JOIN setores s ON s.id = b.setor_origem_id
                 WHERE b.id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            if ($this->missingTable($exception)) {
                return null;
            }
            throw new RepositoryException('Falha ao consultar a solicitação de benefício.', 0, $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findOpenByPersonAndCode(int $personId, string $module, string $benefitCode): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM beneficio_solicitacoes
                 WHERE pessoa_id = :pessoa_id
                   AND modulo = :modulo
                   AND beneficio_codigo = :beneficio_codigo
                   AND status NOT IN ('encerrado','cancelado','indeferido','entregue')
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([
                'pessoa_id' => $personId,
                'modulo' => $module,
                'beneficio_codigo' => $benefitCode,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            if ($this->missingTable($exception)) {
                return null;
            }
            throw new RepositoryException('Falha ao verificar solicitação existente.', 0, $exception);
        }
    }

    /** @param array<string,mixed> $data */
    public function insert(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO beneficio_solicitacoes
                    (pessoa_id, atendimento_id, socioeconomico_id,
                     modulo, beneficio_codigo, beneficio_nome,
                     status, prioridade, setor_origem_id, responsavel_usuario_id,
                     solicitado_em, solicitado_por,
                     referencia_modulo, referencia_tipo, referencia_id, observacao)
                 VALUES
                    (:pessoa_id, :atendimento_id, :socioeconomico_id,
                     :modulo, :beneficio_codigo, :beneficio_nome,
                     :status, :prioridade, :setor_origem_id, :responsavel_usuario_id,
                     :solicitado_em, :solicitado_por,
                     :referencia_modulo, :referencia_tipo, :referencia_id, :observacao)'
            );
            $stmt->execute($data);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw new RepositoryException('Falha ao registrar a solicitação de benefício.', 0, $exception);
        }
    }

    public function attachAttendance(int $requestId, int $attendanceId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE beneficio_solicitacoes
                 SET atendimento_id = :atendimento_id
                 WHERE id = :id AND atendimento_id IS NULL'
            );
            $stmt->execute(['id' => $requestId, 'atendimento_id' => $attendanceId]);
        } catch (PDOException $exception) {
            throw new RepositoryException('Falha ao vincular a trajetória à solicitação.', 0, $exception);
        }
    }

    public function updateStatus(int $requestId, string $status, ?int $responsibleUserId = null): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE beneficio_solicitacoes
                 SET status = :status,
                     responsavel_usuario_id = COALESCE(:responsavel_usuario_id, responsavel_usuario_id)
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $requestId,
                'status' => $status,
                'responsavel_usuario_id' => $responsibleUserId,
            ]);
        } catch (PDOException $exception) {
            throw new RepositoryException('Falha ao atualizar o status da solicitação.', 0, $exception);
        }
    }

    public function decide(
        int $requestId,
        string $decision,
        string $reason,
        int $userId,
        string $status,
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE beneficio_solicitacoes
                 SET decisao = :decisao,
                     decisao_motivo = :motivo,
                     decidido_por = :usuario_id,
                     decidido_em = CURRENT_TIMESTAMP,
                     status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $requestId,
                'decisao' => $decision,
                'motivo' => $reason,
                'usuario_id' => $userId,
                'status' => $status,
            ]);
        } catch (PDOException $exception) {
            throw new RepositoryException('Falha ao registrar a decisão do benefício.', 0, $exception);
        }
    }

    /** @return list<array<string,mixed>> */
    public function listByPerson(int $personId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT b.id, b.modulo, b.beneficio_codigo, b.beneficio_nome,
                        b.status, b.prioridade, b.solicitado_em, b.decisao,
                        b.decidido_em, u.nome AS responsavel_nome,
                        s.nome AS setor_origem_nome
                 FROM beneficio_solicitacoes b
                 LEFT JOIN usuarios u ON u.id = b.responsavel_usuario_id
                 LEFT JOIN setores s ON s.id = b.setor_origem_id
                 WHERE b.pessoa_id = :pessoa_id
                 ORDER BY b.solicitado_em DESC, b.id DESC'
            );
            $stmt->execute(['pessoa_id' => $personId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            if ($this->missingTable($exception)) {
                return [];
            }
            throw new RepositoryException('Falha ao consultar os benefícios da pessoa.', 0, $exception);
        }
    }

    private function missingTable(PDOException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, 'doesn\'t exist') || str_contains($message, 'does not exist');
    }
}
