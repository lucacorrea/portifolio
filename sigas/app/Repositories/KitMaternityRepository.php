<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\RepositoryException;
use PDO;
use PDOException;

final class KitMaternityRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string,mixed> $data */
    public function insertRequest(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO kit_maternidade_solicitacoes
                    (beneficio_solicitacao_id, pessoa_id, dum, dpp,
                     idade_gestacional_inicial_semanas, prenatal_iniciado, unidade_prenatal,
                     gestacao_risco, risco_descricao, numero_gestacao, numero_partos,
                     responsavel_tecnico_id, acompanhamento_iniciado_em,
                     status, observacao, criado_por, atualizado_por)
                 VALUES
                    (:beneficio_solicitacao_id, :pessoa_id, :dum, :dpp,
                     :idade_gestacional_inicial_semanas, :prenatal_iniciado, :unidade_prenatal,
                     :gestacao_risco, :risco_descricao, :numero_gestacao, :numero_partos,
                     :responsavel_tecnico_id, :acompanhamento_iniciado_em,
                     :status, :observacao, :usuario_id, :usuario_id)'
            );
            $stmt->execute($data);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw new RepositoryException('Falha ao registrar a solicitação do Kit Maternidade.', 0, $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function find(int $kitRequestId): ?array
    {
        try {
            $stmt = $this->pdo->prepare($this->baseSelect() . ' WHERE k.id = :id LIMIT 1');
            $stmt->execute(['id' => $kitRequestId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            if ($this->missingTable($exception)) {
                return null;
            }
            throw new RepositoryException('Falha ao consultar o acompanhamento do Kit Maternidade.', 0, $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findByBenefitRequest(int $benefitRequestId): ?array
    {
        try {
            $stmt = $this->pdo->prepare($this->baseSelect() . ' WHERE k.beneficio_solicitacao_id = :id LIMIT 1');
            $stmt->execute(['id' => $benefitRequestId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            if ($this->missingTable($exception)) {
                return null;
            }
            throw new RepositoryException('Falha ao consultar o acompanhamento do Kit Maternidade.', 0, $exception);
        }
    }

    /** @return list<array<string,mixed>> */
    public function list(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        try {
            $rows = $this->pdo->query(
                $this->baseSelect() . ' ORDER BY
                    CASE WHEN k.gestacao_risco = 1 THEN 0 ELSE 1 END,
                    k.dpp ASC, k.id DESC LIMIT ' . $limit
            )->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            if ($this->missingTable($exception)) {
                return [];
            }
            throw new RepositoryException('Falha ao listar as candidatas do Kit Maternidade.', 0, $exception);
        }
    }

    /** @return array<string,int> */
    public function dashboard(): array
    {
        try {
            $sql = "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN k.status IN ('solicitado','em_acompanhamento','em_analise') THEN 1 ELSE 0 END) AS em_acompanhamento,
                        SUM(CASE WHEN k.gestacao_risco = 1 AND k.status NOT IN ('encerrado','entregue','cancelado') THEN 1 ELSE 0 END) AS risco,
                        SUM(CASE WHEN bs.decisao = 'apto' THEN 1 ELSE 0 END) AS aptas,
                        SUM(CASE WHEN bs.decisao = 'nao_apto' THEN 1 ELSE 0 END) AS nao_aptas,
                        SUM(CASE WHEN e.id IS NOT NULL THEN 1 ELSE 0 END) AS entregues,
                        SUM(CASE WHEN k.dpp BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                                  AND e.id IS NULL THEN 1 ELSE 0 END) AS dpp_30_dias
                    FROM kit_maternidade_solicitacoes k
                    INNER JOIN beneficio_solicitacoes bs ON bs.id = k.beneficio_solicitacao_id
                    LEFT JOIN kit_maternidade_entregas e ON e.kit_solicitacao_id = k.id";
            $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return $this->emptyDashboard();
            }
            return array_map('intval', $row);
        } catch (PDOException $exception) {
            if ($this->missingTable($exception)) {
                return $this->emptyDashboard();
            }
            throw new RepositoryException('Falha ao carregar indicadores do Kit Maternidade.', 0, $exception);
        }
    }

    /** @param array<string,mixed> $data */
    public function insertFollowUp(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO kit_maternidade_acompanhamentos
                    (kit_solicitacao_id, tipo, data_evento, idade_gestacional_semanas,
                     participacao, risco_identificado, risco_descricao,
                     observacao, proxima_acao, proxima_acao_em, usuario_id)
                 VALUES
                    (:kit_solicitacao_id, :tipo, :data_evento, :idade_gestacional_semanas,
                     :participacao, :risco_identificado, :risco_descricao,
                     :observacao, :proxima_acao, :proxima_acao_em, :usuario_id)'
            );
            $stmt->execute($data);

            $update = $this->pdo->prepare(
                "UPDATE kit_maternidade_solicitacoes
                 SET status = CASE WHEN status = 'solicitado' THEN 'em_acompanhamento' ELSE status END,
                     responsavel_tecnico_id = COALESCE(responsavel_tecnico_id, :usuario_id),
                     acompanhamento_iniciado_em = COALESCE(acompanhamento_iniciado_em, CURRENT_TIMESTAMP),
                     atualizado_por = :usuario_id
                 WHERE id = :id"
            );
            $update->execute(['id' => $data['kit_solicitacao_id'], 'usuario_id' => $data['usuario_id']]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw new RepositoryException('Falha ao registrar o acompanhamento do Kit Maternidade.', 0, $exception);
        }
    }

    /** @return list<array<string,mixed>> */
    public function followUps(int $kitRequestId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT a.*, u.nome AS usuario_nome
                 FROM kit_maternidade_acompanhamentos a
                 INNER JOIN usuarios u ON u.id = a.usuario_id
                 WHERE a.kit_solicitacao_id = :id
                 ORDER BY a.data_evento DESC, a.id DESC'
            );
            $stmt->execute(['id' => $kitRequestId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            if ($this->missingTable($exception)) {
                return [];
            }
            throw new RepositoryException('Falha ao consultar acompanhamentos.', 0, $exception);
        }
    }

    /** @param array<string,mixed> $data */
    public function insertEvaluation(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO kit_maternidade_avaliacoes
                    (kit_solicitacao_id, resultado, parecer_tecnico, pendencias_json, usuario_id)
                 VALUES
                    (:kit_solicitacao_id, :resultado, :parecer_tecnico, :pendencias_json, :usuario_id)'
            );
            $stmt->execute($data);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw new RepositoryException('Falha ao registrar a avaliação do Kit Maternidade.', 0, $exception);
        }
    }

    /** @param array<string,mixed> $data */
    public function insertDelivery(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO kit_maternidade_entregas
                    (kit_solicitacao_id, entregue_em, lote, termo_referencia,
                     recebedor_nome, recebedor_cpf, observacao, entregue_por)
                 VALUES
                    (:kit_solicitacao_id, :entregue_em, :lote, :termo_referencia,
                     :recebedor_nome, :recebedor_cpf, :observacao, :entregue_por)'
            );
            $stmt->execute($data);

            $update = $this->pdo->prepare(
                "UPDATE kit_maternidade_solicitacoes
                 SET status = 'entregue', atualizado_por = :usuario_id
                 WHERE id = :id"
            );
            $update->execute(['id' => $data['kit_solicitacao_id'], 'usuario_id' => $data['entregue_por']]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw new RepositoryException('Falha ao registrar a entrega do Kit Maternidade.', 0, $exception);
        }
    }

    public function setStatus(int $kitRequestId, string $status, int $userId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE kit_maternidade_solicitacoes
                 SET status = :status,
                     acompanhamento_encerrado_em = CASE WHEN :encerrar = 1 THEN CURRENT_TIMESTAMP ELSE acompanhamento_encerrado_em END,
                     atualizado_por = :usuario_id
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $kitRequestId,
                'status' => $status,
                'encerrar' => in_array($status, ['encerrado', 'cancelado'], true) ? 1 : 0,
                'usuario_id' => $userId,
            ]);
        } catch (PDOException $exception) {
            throw new RepositoryException('Falha ao atualizar o fluxo do Kit Maternidade.', 0, $exception);
        }
    }

    private function baseSelect(): string
    {
        return "SELECT
                    k.id,
                    k.beneficio_solicitacao_id,
                    k.pessoa_id,
                    p.nome AS pessoa_nome,
                    p.cpf AS pessoa_cpf,
                    p.telefone,
                    f.bairro,
                    k.dum,
                    k.dpp,
                    CASE
                        WHEN k.dum IS NOT NULL THEN GREATEST(0, TIMESTAMPDIFF(WEEK, k.dum, CURDATE()))
                        WHEN k.dpp IS NOT NULL THEN GREATEST(0, 40 - TIMESTAMPDIFF(WEEK, CURDATE(), k.dpp))
                        ELSE k.idade_gestacional_inicial_semanas
                    END AS semanas_gestacao,
                    k.prenatal_iniciado,
                    k.unidade_prenatal,
                    k.gestacao_risco,
                    k.risco_descricao,
                    k.responsavel_tecnico_id,
                    rt.nome AS responsavel_tecnico_nome,
                    k.status,
                    bs.status AS beneficio_status,
                    bs.decisao,
                    bs.decisao_motivo,
                    ps.id AS socioeconomico_id,
                    ps.data_entrevista AS socioeconomico_entrevista,
                    ps.origem AS socioeconomico_origem,
                    COALESCE(ac.visitas, 0) AS visitas,
                    COALESCE(ac.reunioes, 0) AS reunioes,
                    COALESCE(ac.reunioes_presentes, 0) AS reunioes_presentes,
                    COALESCE(ac.total_acompanhamentos, 0) AS total_acompanhamentos,
                    ac.ultimo_acompanhamento,
                    av.resultado AS ultima_avaliacao,
                    av.avaliado_em AS ultima_avaliacao_em,
                    e.entregue_em,
                    e.lote
                FROM kit_maternidade_solicitacoes k
                INNER JOIN beneficio_solicitacoes bs ON bs.id = k.beneficio_solicitacao_id
                INNER JOIN pessoas p ON p.id = k.pessoa_id
                LEFT JOIN familias f ON f.responsavel_pessoa_id = p.id
                LEFT JOIN pessoa_socioeconomico ps ON ps.pessoa_id = p.id
                LEFT JOIN usuarios rt ON rt.id = k.responsavel_tecnico_id
                LEFT JOIN (
                    SELECT kit_solicitacao_id,
                           SUM(CASE WHEN tipo = 'visita' THEN 1 ELSE 0 END) AS visitas,
                           SUM(CASE WHEN tipo = 'reuniao' THEN 1 ELSE 0 END) AS reunioes,
                           SUM(CASE WHEN tipo = 'reuniao' AND participacao = 'presente' THEN 1 ELSE 0 END) AS reunioes_presentes,
                           COUNT(*) AS total_acompanhamentos,
                           MAX(data_evento) AS ultimo_acompanhamento
                    FROM kit_maternidade_acompanhamentos
                    GROUP BY kit_solicitacao_id
                ) ac ON ac.kit_solicitacao_id = k.id
                LEFT JOIN kit_maternidade_avaliacoes av ON av.id = (
                    SELECT av2.id FROM kit_maternidade_avaliacoes av2
                    WHERE av2.kit_solicitacao_id = k.id
                    ORDER BY av2.avaliado_em DESC, av2.id DESC LIMIT 1
                )
                LEFT JOIN kit_maternidade_entregas e ON e.kit_solicitacao_id = k.id";
    }

    /** @return array<string,int> */
    private function emptyDashboard(): array
    {
        return [
            'total' => 0,
            'em_acompanhamento' => 0,
            'risco' => 0,
            'aptas' => 0,
            'nao_aptas' => 0,
            'entregues' => 0,
            'dpp_30_dias' => 0,
        ];
    }

    private function missingTable(PDOException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, 'doesn\'t exist') || str_contains($message, 'does not exist');
    }
}
