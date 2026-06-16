<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ClientAccountRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function connection(): PDO
    {
        return $this->db;
    }

    public function summary(int $empresaId, array $filters = []): array
    {
        $params = [];
        $where = $this->buildWhere($empresaId, $filters, $params);

        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE
                    WHEN cc.status <> 'cancelado' AND cc.saldo_aberto > 0
                    THEN cc.saldo_aberto ELSE 0
                END), 0) AS total_aberto,

                COALESCE(SUM(CASE
                    WHEN cc.status <> 'cancelado'
                     AND cc.saldo_aberto > 0
                     AND cc.vencimento < CURDATE()
                    THEN cc.saldo_aberto ELSE 0
                END), 0) AS total_vencido,

                COALESCE(SUM(CASE
                    WHEN cc.status <> 'cancelado'
                    THEN cc.valor_pago ELSE 0
                END), 0) AS total_pago,

                COUNT(CASE
                    WHEN cc.status <> 'cancelado' AND cc.saldo_aberto > 0
                    THEN 1
                END) AS contas_abertas,

                COUNT(CASE
                    WHEN cc.status <> 'cancelado'
                     AND cc.saldo_aberto > 0
                     AND cc.vencimento < CURDATE()
                    THEN 1
                END) AS contas_vencidas,

                COUNT(DISTINCT CASE
                    WHEN cc.status <> 'cancelado' AND cc.saldo_aberto > 0
                    THEN cc.cliente_id
                END) AS clientes_com_divida
            FROM cliente_contas cc
            INNER JOIN clientes c
                    ON c.id = cc.cliente_id
                   AND c.empresa_id = cc.empresa_id
            WHERE {$where}
        ");

        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'total_aberto' => (float)($row['total_aberto'] ?? 0),
            'total_vencido' => (float)($row['total_vencido'] ?? 0),
            'total_pago' => (float)($row['total_pago'] ?? 0),
            'contas_abertas' => (int)($row['contas_abertas'] ?? 0),
            'contas_vencidas' => (int)($row['contas_vencidas'] ?? 0),
            'clientes_com_divida' => (int)($row['clientes_com_divida'] ?? 0),
        ];
    }

    public function findAll(int $empresaId, array $filters = []): array
    {
        $params = [];
        $where = $this->buildWhere($empresaId, $filters, $params);

        $stmt = $this->db->prepare("
            SELECT
                cc.id,
                cc.empresa_id,
                cc.cliente_id,
                cc.venda_id,
                cc.valor_original,
                cc.valor_pago,
                cc.saldo_aberto,
                cc.vencimento,
                cc.status,
                cc.criado_em,
                cc.atualizado_em,
                c.nome AS cliente_nome,
                c.telefone AS cliente_telefone,
                c.cpf_cnpj AS cliente_documento,
                c.endereco AS cliente_endereco,
                CASE
                    WHEN cc.status = 'cancelado' THEN 'cancelado'
                    WHEN cc.saldo_aberto <= 0 THEN 'pago'
                    WHEN cc.valor_pago > 0 AND cc.saldo_aberto > 0 THEN 'parcial'
                    WHEN cc.vencimento < CURDATE() AND cc.saldo_aberto > 0 THEN 'atrasado'
                    ELSE 'em_aberto'
                END AS status_visual
            FROM cliente_contas cc
            INNER JOIN clientes c
                    ON c.id = cc.cliente_id
                   AND c.empresa_id = cc.empresa_id
            WHERE {$where}
            ORDER BY
                CASE
                    WHEN cc.status <> 'cancelado'
                     AND cc.saldo_aberto > 0
                     AND cc.vencimento < CURDATE()
                    THEN 0
                    ELSE 1
                END,
                cc.vencimento ASC,
                cc.id DESC
        ");

        $stmt->execute($params);

        return array_map([$this, 'mapAccount'], $stmt->fetchAll());
    }

    public function findById(int $empresaId, int $contaId, bool $forUpdate = false): ?array
    {
        $sql = "
            SELECT
                cc.id,
                cc.empresa_id,
                cc.cliente_id,
                cc.venda_id,
                cc.valor_original,
                cc.valor_pago,
                cc.saldo_aberto,
                cc.vencimento,
                cc.status,
                cc.criado_em,
                cc.atualizado_em,
                c.nome AS cliente_nome,
                c.telefone AS cliente_telefone,
                c.cpf_cnpj AS cliente_documento,
                c.endereco AS cliente_endereco,
                CASE
                    WHEN cc.status = 'cancelado' THEN 'cancelado'
                    WHEN cc.saldo_aberto <= 0 THEN 'pago'
                    WHEN cc.valor_pago > 0 AND cc.saldo_aberto > 0 THEN 'parcial'
                    WHEN cc.vencimento < CURDATE() AND cc.saldo_aberto > 0 THEN 'atrasado'
                    ELSE 'em_aberto'
                END AS status_visual
            FROM cliente_contas cc
            INNER JOIN clientes c
                    ON c.id = cc.cliente_id
                   AND c.empresa_id = cc.empresa_id
            WHERE cc.empresa_id = :empresa_id
              AND cc.id = :id
            LIMIT 1
        ";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $contaId,
        ]);

        $account = $stmt->fetch();

        return $account ? $this->mapAccount($account) : null;
    }

    public function payments(int $empresaId, int $contaId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.empresa_id,
                p.conta_id,
                p.cliente_id,
                p.usuario_id,
                p.valor_pago,
                p.forma_pagamento,
                p.observacao,
                p.criado_em
            FROM cliente_conta_pagamentos p
            WHERE p.empresa_id = :empresa_id
              AND p.conta_id = :conta_id
            ORDER BY p.criado_em DESC, p.id DESC
        ");

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':conta_id' => $contaId,
        ]);

        return array_map(static function (array $payment): array {
            return [
                'id' => (int)$payment['id'],
                'empresa_id' => (int)$payment['empresa_id'],
                'conta_id' => (int)$payment['conta_id'],
                'cliente_id' => (int)$payment['cliente_id'],
                'usuario_id' => $payment['usuario_id'] !== null ? (int)$payment['usuario_id'] : null,
                'valor_pago' => (float)$payment['valor_pago'],
                'forma_pagamento' => (string)$payment['forma_pagamento'],
                'observacao' => $payment['observacao'] ?? '',
                'criado_em' => (string)$payment['criado_em'],
            ];
        }, $stmt->fetchAll());
    }

    public function createPayment(int $empresaId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO cliente_conta_pagamentos (
                empresa_id,
                conta_id,
                cliente_id,
                usuario_id,
                valor_pago,
                forma_pagamento,
                observacao
            ) VALUES (
                :empresa_id,
                :conta_id,
                :cliente_id,
                :usuario_id,
                :valor_pago,
                :forma_pagamento,
                :observacao
            )
        ");

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':conta_id' => $data['conta_id'],
            ':cliente_id' => $data['cliente_id'],
            ':usuario_id' => $data['usuario_id'],
            ':valor_pago' => $data['valor_pago'],
            ':forma_pagamento' => $data['forma_pagamento'],
            ':observacao' => $data['observacao'] ?: null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function applyPayment(int $empresaId, int $contaId, float $valorPago): void
    {
        $stmt = $this->db->prepare("
            UPDATE cliente_contas
            SET valor_pago = valor_pago + :valor_pago_add,
                saldo_aberto = GREATEST(saldo_aberto - :valor_pago_saldo, 0),
                status = CASE
                    WHEN GREATEST(saldo_aberto - :valor_pago_status, 0) <= 0 THEN 'pago'
                    ELSE 'parcial'
                END
            WHERE empresa_id = :empresa_id
              AND id = :id
              AND status <> 'cancelado'
              AND saldo_aberto > 0
        ");

        $stmt->execute([
            ':valor_pago_add' => $valorPago,
            ':valor_pago_saldo' => $valorPago,
            ':valor_pago_status' => $valorPago,
            ':empresa_id' => $empresaId,
            ':id' => $contaId,
        ]);
    }

    private function buildWhere(int $empresaId, array $filters, array &$params): string
    {
        $clauses = ['cc.empresa_id = :empresa_id'];
        $params[':empresa_id'] = $empresaId;

        $status = (string)($filters['status'] ?? 'todas');
        if ($status !== '' && $status !== 'todas') {
            if ($status === 'atrasado') {
                $clauses[] = "(
                    cc.status = :status_atrasado
                    OR (
                        cc.status <> 'cancelado'
                        AND cc.saldo_aberto > 0
                        AND cc.vencimento < CURDATE()
                    )
                )";
                $params[':status_atrasado'] = 'atrasado';
            } elseif (in_array($status, ['em_aberto', 'parcial', 'pago', 'cancelado'], true)) {
                $clauses[] = 'cc.status = :status_exato';
                $params[':status_exato'] = $status;
            }
        }

        $clienteId = filter_var($filters['cliente_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($clienteId !== false && $clienteId !== null) {
            $clauses[] = 'cc.cliente_id = :cliente_id';
            $params[':cliente_id'] = (int)$clienteId;
        }

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $clauses[] = '(
                c.nome LIKE :like_nome
                OR c.telefone LIKE :like_telefone
                OR c.cpf_cnpj LIKE :like_documento
            )';

            $like = '%' . $query . '%';

            $params[':like_nome'] = $like;
            $params[':like_telefone'] = $like;
            $params[':like_documento'] = $like;
        }

        $inicio = trim((string)($filters['inicio'] ?? ''));
        if ($this->isDate($inicio)) {
            $clauses[] = 'cc.vencimento >= :inicio';
            $params[':inicio'] = $inicio;
        }

        $fim = trim((string)($filters['fim'] ?? ''));
        if ($this->isDate($fim)) {
            $clauses[] = 'cc.vencimento <= :fim';
            $params[':fim'] = $fim;
        }

        return implode(' AND ', $clauses);
    }

    private function isDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    private function mapAccount(array $account): array
    {
        return [
            'id' => (int)$account['id'],
            'empresa_id' => (int)$account['empresa_id'],
            'cliente_id' => (int)$account['cliente_id'],
            'venda_id' => $account['venda_id'] !== null ? (int)$account['venda_id'] : null,
            'valor_original' => (float)$account['valor_original'],
            'valor_pago' => (float)$account['valor_pago'],
            'saldo_aberto' => (float)$account['saldo_aberto'],
            'vencimento' => (string)$account['vencimento'],
            'status' => (string)$account['status'],
            'status_visual' => (string)($account['status_visual'] ?? $account['status']),
            'criado_em' => (string)$account['criado_em'],
            'atualizado_em' => (string)$account['atualizado_em'],
            'cliente_nome' => (string)$account['cliente_nome'],
            'cliente_telefone' => $account['cliente_telefone'] ?? '',
            'cliente_documento' => $account['cliente_documento'] ?? '',
            'cliente_endereco' => $account['cliente_endereco'] ?? '',
        ];
    }
}