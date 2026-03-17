<?php
namespace App\Services;

class FinancialService extends BaseService {
    public function getDRE($month, $year) {
        // 1. Receita Bruta (Total Vendas)
        $receitaBruta = $this->db->query("
            SELECT SUM(valor_total) 
            FROM vendas 
            WHERE MONTH(data_venda) = $month AND YEAR(data_venda) = $year
        ")->fetchColumn() ?: 0;

        // 2. CMV (Custo de Mercadoria Vendida)
        $cmv = $this->db->query("
            SELECT SUM(p.preco_custo * vi.quantidade)
            FROM vendas_itens vi
            JOIN produtos p ON vi.produto_id = p.id
            JOIN vendas v ON vi.venda_id = v.id
            WHERE MONTH(v.data_venda) = $month AND YEAR(v.data_venda) = $year
        ")->fetchColumn() ?: 0;

        // 3. Lucro Bruto
        $lucroBruto = $receitaBruta - $cmv;

        // 4. Despesas Operacionais (Contas a Pagar pagas no mês)
        $despesas = $this->db->query("
            SELECT SUM(valor) 
            FROM financeiro_contas 
            WHERE tipo = 'despesa' 
            AND status = 'pago'
            AND MONTH(data_pagamento) = $month 
            AND YEAR(data_pagamento) = $year
        ")->fetchColumn() ?: 0;

        // 5. Resultado Líquido
        $resultadoLiquido = $lucroBruto - $despesas;

        return [
            'receita_bruta' => $receitaBruta,
            'cmv' => $cmv,
            'lucro_bruto' => $lucroBruto,
            'despesas' => $despesas,
            'resultado_liquido' => $resultadoLiquido,
            'margem_liquida' => $receitaBruta > 0 ? ($resultadoLiquido / $receitaBruta) * 100 : 0
        ];
    }

    public function getProductABCCurve() {
        // ABC Curve based on revenue
        return $this->db->query("
            SELECT p.id, p.nome, SUM(vi.quantidade * vi.preco_unitario) as total_revenue,
                   (SUM(vi.quantidade * vi.preco_unitario) / (SELECT SUM(valor_total) FROM vendas)) * 100 as percentage
            FROM vendas_itens vi
            JOIN produtos p ON vi.produto_id = p.id
            GROUP BY p.id
            ORDER BY total_revenue DESC
        ")->fetchAll();
    }

    public function getDelinquencyReport() {
        return $this->db->query("
            SELECT v.id, v.data_venda, c.nome as cliente_nome, v.valor_total, 
                   DATEDIFF(CURRENT_DATE, v.data_venda) as dias_atraso
            FROM vendas v
            JOIN clientes c ON v.cliente_id = c.id
            WHERE v.status = 'pendente' AND v.data_venda < DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
            ORDER BY dias_atraso DESC
        ")->fetchAll();
    }
}
