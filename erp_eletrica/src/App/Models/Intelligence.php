<?php
namespace App\Models;

class Intelligence extends BaseModel {
    
    public function getTopProducts(int $filial_id, string $inicio, string $fim, int $limit = 10) {
        $sql = "SELECT p.id, p.nome, SUM(vi.quantidade) as total_qtd, SUM(vi.preco_total) as total_receita
                FROM venda_itens vi
                JOIN vendas v ON vi.venda_id = v.id
                JOIN produtos p ON vi.produto_id = p.id
                WHERE v.filial_id = ? AND v.data_venda BETWEEN ? AND ? AND v.status = 'concluido'
                GROUP BY p.id
                ORDER BY total_qtd DESC
                LIMIT $limit";
        return $this->query($sql, [$filial_id, $inicio, $fim])->fetchAll();
    }

    public function getStagnantProducts(int $filial_id, int $days = 30) {
        $sql = "SELECT p.*, MAX(v.data_venda) as ultima_venda
                FROM produtos p
                LEFT JOIN venda_itens vi ON p.id = vi.produto_id
                LEFT JOIN vendas v ON vi.venda_id = v.id AND v.filial_id = ?
                WHERE p.filial_id = ? AND p.estoque > 0
                GROUP BY p.id
                HAVING ultima_venda IS NULL OR ultima_venda < DATE_SUB(NOW(), INTERVAL $days DAY)";
        return $this->query($sql, [$filial_id, $filial_id])->fetchAll();
    }

    public function calculateABC(int $filial_id, string $inicio, string $fim) {
        // 1. Get total revenue per product
        $sql = "SELECT produto_id, SUM(preco_total) as receita 
                FROM venda_itens vi
                JOIN vendas v ON vi.venda_id = v.id
                WHERE v.filial_id = ? AND v.data_venda BETWEEN ? AND ? AND v.status = 'concluido'
                GROUP BY produto_id
                ORDER BY receita DESC";
        $products = $this->query($sql, [$filial_id, $inicio, $fim])->fetchAll();
        
        $totalRevenue = array_sum(array_column($products, 'receita'));
        $accumulated = 0;
        $results = [];

        foreach ($products as $p) {
            $accumulated += $p['receita'];
            $percent = ($accumulated / $totalRevenue) * 100;
            
            if ($percent <= 80) $class = 'A';
            elseif ($percent <= 95) $class = 'B';
            else $class = 'C';
            
            $results[] = [
                'produto_id' => $p['produto_id'],
                'classificacao' => $class
            ];
        }
        return $results;
    }

    public function getReplenishmentAlerts(int $filial_id) {
        // Average sales last 3 months
        $sql = "SELECT p.id, p.nome, p.estoque, 
                (SELECT SUM(quantidade) FROM venda_itens vi 
                JOIN vendas v ON vi.venda_id = v.id 
                WHERE vi.produto_id = p.id AND v.filial_id = ? AND v.data_venda > DATE_SUB(NOW(), INTERVAL 90 DAY)) / 3 as media_mensal
                FROM produtos p
                WHERE p.filial_id = ?
                HAVING p.estoque < media_mensal";
        return $this->query($sql, [$filial_id, $filial_id])->fetchAll();
    }
}
