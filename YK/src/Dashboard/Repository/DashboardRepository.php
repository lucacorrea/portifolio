<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use PDO;

final class DashboardRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array<string,int> */
    public function operationalIndicators(): array
    {
        $orders = $this->connection->query(
            "SELECT
                SUM(CASE WHEN status IN ('rascunho','aberta') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status IN ('em_deslocamento','em_execucao') THEN 1 ELSE 0 END) AS in_service,
                SUM(CASE WHEN status = 'aguardando_peca' THEN 1 ELSE 0 END) AS waiting_part,
                SUM(CASE WHEN agendado_inicio >= DATE_SUB(CURRENT_DATE, INTERVAL WEEKDAY(CURRENT_DATE) DAY)
                          AND agendado_inicio < DATE_ADD(DATE_SUB(CURRENT_DATE, INTERVAL WEEKDAY(CURRENT_DATE) DAY), INTERVAL 7 DAY)
                          AND status <> 'cancelada' THEN 1 ELSE 0 END) AS week_services
             FROM ordens_servico
             WHERE excluida_em IS NULL"
        )->fetch() ?: [];

        $waitingBudgets = $this->connection->query(
            "SELECT COUNT(*) FROM orcamentos
              WHERE status IN ('enviado','aguardando_aprovacao')"
        )->fetchColumn();
        $lowStock = $this->connection->query(
            "SELECT COUNT(*) FROM produtos
              WHERE status = 'ativo' AND estoque <= estoque_minimo"
        )->fetchColumn();

        return [
            'open_count' => (int) ($orders['open_count'] ?? 0),
            'in_service' => (int) ($orders['in_service'] ?? 0),
            'week_services' => (int) ($orders['week_services'] ?? 0),
            'waiting_part' => (int) ($orders['waiting_part'] ?? 0),
            'waiting_budgets' => (int) $waitingBudgets,
            'low_stock' => (int) $lowStock,
        ];
    }

    /** @return array{pending_accounts:int,pending_balance:string,received_month:string} */
    public function financialIndicators(): array
    {
        $accounts = $this->connection->query(
            "SELECT COUNT(*) AS pending_accounts, COALESCE(SUM(saldo), 0) AS pending_balance
               FROM contas_receber
              WHERE status IN ('pendente','parcial','vencida')"
        )->fetch() ?: [];
        $received = $this->connection->query(
            "SELECT COALESCE(SUM(CASE
                        WHEN tipo = 'entrada' THEN valor
                        WHEN tipo = 'estorno_entrada' THEN -valor
                        ELSE 0
                    END), 0)
              FROM caixa_movimentacoes
              WHERE data_movimento >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
                AND data_movimento < DATE_ADD(LAST_DAY(CURRENT_DATE), INTERVAL 1 DAY)
                AND origem_tipo IN ('os_pagamento', 'conta_receber_pagamento')"
        )->fetchColumn();

        return [
            'pending_accounts' => (int) ($accounts['pending_accounts'] ?? 0),
            'pending_balance' => number_format((float) ($accounts['pending_balance'] ?? 0), 2, '.', ''),
            'received_month' => number_format((float) $received, 2, '.', ''),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function weeklyOrders(int $limit = 8): array
    {
        $limit = max(1, min($limit, 20));
        return $this->connection->query(
            "SELECT os.id, os.numero, os.agendado_inicio, os.status, os.prioridade,
                    c.nome AS cliente_nome,
                    COALESCE(
                        (SELECT MIN(osi.descricao) FROM ordem_servico_itens osi
                          WHERE osi.ordem_servico_id = os.id AND osi.tipo = 'servico'),
                        os.equipamento_tipo,
                        'Serviço não informado'
                    ) AS servico,
                    (SELECT GROUP_CONCAT(f.nome ORDER BY osf.principal DESC, f.nome SEPARATOR ', ')
                       FROM ordem_servico_funcionarios osf
                       JOIN funcionarios f ON f.id = osf.funcionario_id
                      WHERE osf.ordem_servico_id = os.id AND osf.ativo = 1) AS equipe
               FROM ordens_servico os
               JOIN clientes c ON c.id = os.cliente_id
              WHERE os.agendado_inicio >= DATE_SUB(CURRENT_DATE, INTERVAL WEEKDAY(CURRENT_DATE) DAY)
                AND os.agendado_inicio < DATE_ADD(DATE_SUB(CURRENT_DATE, INTERVAL WEEKDAY(CURRENT_DATE) DAY), INTERVAL 7 DAY)
                AND os.status <> 'cancelada'
                AND os.excluida_em IS NULL
           ORDER BY os.agendado_inicio ASC, os.id ASC
              LIMIT " . $limit
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function latestOrders(bool $includeValues, int $limit = 6): array
    {
        $limit = max(1, min($limit, 20));
        $totalColumn = $includeValues ? 'os.total' : '0.00';
        return $this->connection->query(
            "SELECT os.id, os.numero, os.status, " . $totalColumn . " AS total, os.criado_em,
                    c.nome AS cliente_nome,
                    COALESCE(
                        (SELECT MIN(osi.descricao) FROM ordem_servico_itens osi
                          WHERE osi.ordem_servico_id = os.id AND osi.tipo = 'servico'),
                        os.equipamento_tipo,
                        'Serviço não informado'
                    ) AS servico,
                    (SELECT GROUP_CONCAT(f.nome ORDER BY osf.principal DESC, f.nome SEPARATOR ', ')
                       FROM ordem_servico_funcionarios osf
                       JOIN funcionarios f ON f.id = osf.funcionario_id
                      WHERE osf.ordem_servico_id = os.id AND osf.ativo = 1) AS equipe
               FROM ordens_servico os
               JOIN clientes c ON c.id = os.cliente_id
              WHERE os.excluida_em IS NULL
           ORDER BY os.criado_em DESC, os.id DESC
              LIMIT " . $limit
        )->fetchAll();
    }
}
