<?php

declare(strict_types=1);

namespace App\Dashboard\Repository;

use App\Company\DTO\CompanyScope;
use PDO;

final class DashboardRepository
{
    public function __construct(
        private readonly PDO $connection,
        private readonly CompanyScope $companyScope
    )
    {
    }

    /** @return array<string,int> */
    public function operationalIndicators(): array
    {
        $ordersStatement = $this->connection->prepare(
            "SELECT
                SUM(CASE WHEN status IN ('rascunho','aberta') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status IN ('em_deslocamento','em_execucao') THEN 1 ELSE 0 END) AS in_service,
                SUM(CASE WHEN status = 'aguardando_peca' THEN 1 ELSE 0 END) AS waiting_part,
                SUM(CASE WHEN agendado_inicio >= DATE_SUB(CURRENT_DATE, INTERVAL WEEKDAY(CURRENT_DATE) DAY)
                          AND agendado_inicio < DATE_ADD(DATE_SUB(CURRENT_DATE, INTERVAL WEEKDAY(CURRENT_DATE) DAY), INTERVAL 7 DAY)
                          AND status <> 'cancelada' THEN 1 ELSE 0 END) AS week_services
             FROM ordens_servico
             WHERE empresa_id = :empresa_id AND excluida_em IS NULL"
        );
        $ordersStatement->execute(['empresa_id' => $this->companyScope->id()]);
        $orders = $ordersStatement->fetch() ?: [];

        $budgetStatement = $this->connection->prepare(
            "SELECT COUNT(*) FROM orcamentos
              WHERE empresa_id = :empresa_id
                AND status IN ('enviado','aguardando_aprovacao')
                AND excluido_em IS NULL"
        );
        $budgetStatement->execute(['empresa_id' => $this->companyScope->id()]);
        $waitingBudgets = $budgetStatement->fetchColumn();
        $stockStatement = $this->connection->prepare(
            "SELECT COUNT(*) FROM produtos
              WHERE empresa_id = :empresa_id AND status = 'ativo' AND excluido_em IS NULL AND estoque <= estoque_minimo"
        );
        $stockStatement->execute(['empresa_id' => $this->companyScope->id()]);
        $lowStock = $stockStatement->fetchColumn();

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
        $accountsStatement = $this->connection->prepare(
            "SELECT COUNT(*) AS pending_accounts, COALESCE(SUM(saldo), 0) AS pending_balance
               FROM contas_receber
              WHERE empresa_id = :empresa_id AND status IN ('pendente','parcial','vencida')"
        );
        $accountsStatement->execute(['empresa_id' => $this->companyScope->id()]);
        $accounts = $accountsStatement->fetch() ?: [];
        $receivedStatement = $this->connection->prepare(
            "SELECT COALESCE(SUM(CASE
                        WHEN tipo = 'entrada' THEN valor
                        WHEN tipo = 'estorno_entrada' THEN -valor
                        ELSE 0
                    END), 0)
              FROM caixa_movimentacoes
              WHERE empresa_id = :empresa_id
                AND data_movimento >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
                AND data_movimento < DATE_ADD(LAST_DAY(CURRENT_DATE), INTERVAL 1 DAY)
                AND origem_tipo IN ('os_pagamento', 'conta_receber_pagamento')"
        );
        $receivedStatement->execute(['empresa_id' => $this->companyScope->id()]);
        $received = $receivedStatement->fetchColumn();

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
        $statement = $this->connection->prepare(
            "SELECT os.id, os.numero, os.agendado_inicio, os.status, os.prioridade,
                    c.nome AS cliente_nome,
                    COALESCE(
                        (SELECT MIN(osi.descricao) FROM ordem_servico_itens osi
                          WHERE osi.empresa_id = :empresa_item AND osi.ordem_servico_id = os.id AND osi.tipo = 'servico'),
                        os.equipamento_tipo,
                        'Serviço não informado'
                    ) AS servico,
                    (SELECT GROUP_CONCAT(f.nome ORDER BY osf.principal DESC, f.nome SEPARATOR ', ')
                       FROM ordem_servico_funcionarios osf
                       JOIN funcionarios f ON f.id = osf.funcionario_id AND f.empresa_id = osf.empresa_id
                      WHERE osf.empresa_id = :empresa_team AND osf.ordem_servico_id = os.id AND osf.ativo = 1) AS equipe
               FROM ordens_servico os
               JOIN clientes c ON c.id = os.cliente_id AND c.empresa_id = os.empresa_id
              WHERE os.empresa_id = :empresa_order
                AND os.agendado_inicio >= DATE_SUB(CURRENT_DATE, INTERVAL WEEKDAY(CURRENT_DATE) DAY)
                AND os.agendado_inicio < DATE_ADD(DATE_SUB(CURRENT_DATE, INTERVAL WEEKDAY(CURRENT_DATE) DAY), INTERVAL 7 DAY)
                AND os.status <> 'cancelada'
                AND os.excluida_em IS NULL
           ORDER BY os.agendado_inicio ASC, os.id ASC
              LIMIT " . $limit
        );
        $statement->execute([
            'empresa_item' => $this->companyScope->id(),
            'empresa_team' => $this->companyScope->id(),
            'empresa_order' => $this->companyScope->id(),
        ]);
        return $statement->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function latestOrders(bool $includeValues, int $limit = 6): array
    {
        $limit = max(1, min($limit, 20));
        $totalColumn = $includeValues ? 'os.total' : '0.00';
        $statement = $this->connection->prepare(
            "SELECT os.id, os.numero, os.status, " . $totalColumn . " AS total, os.criado_em,
                    c.nome AS cliente_nome,
                    COALESCE(
                        (SELECT MIN(osi.descricao) FROM ordem_servico_itens osi
                          WHERE osi.empresa_id = :empresa_item AND osi.ordem_servico_id = os.id AND osi.tipo = 'servico'),
                        os.equipamento_tipo,
                        'Serviço não informado'
                    ) AS servico,
                    (SELECT GROUP_CONCAT(f.nome ORDER BY osf.principal DESC, f.nome SEPARATOR ', ')
                       FROM ordem_servico_funcionarios osf
                       JOIN funcionarios f ON f.id = osf.funcionario_id AND f.empresa_id = osf.empresa_id
                      WHERE osf.empresa_id = :empresa_team AND osf.ordem_servico_id = os.id AND osf.ativo = 1) AS equipe
               FROM ordens_servico os
               JOIN clientes c ON c.id = os.cliente_id AND c.empresa_id = os.empresa_id
              WHERE os.empresa_id = :empresa_order AND os.excluida_em IS NULL
           ORDER BY os.criado_em DESC, os.id DESC
              LIMIT " . $limit
        );
        $statement->execute([
            'empresa_item' => $this->companyScope->id(),
            'empresa_team' => $this->companyScope->id(),
            'empresa_order' => $this->companyScope->id(),
        ]);
        return $statement->fetchAll();
    }
}
