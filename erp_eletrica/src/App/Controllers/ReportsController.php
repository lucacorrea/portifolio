<?php

namespace App\Controllers;

use App\Config\Database;
use PDO;

class ReportsController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function index()
    {
        $nivel = $_SESSION['usuario_nivel'] ?? 'vendedor';
        $isAdmin = ($nivel === 'admin');
        $filial_id = $isAdmin ? ($_GET['filial_id'] ?? 'all') : ($_SESSION['filial_id'] ?? 0);
        
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $data = [
            'kpis' => $this->getKPIs($filial_id, $startDate, $endDate),
            'sales_chart' => $this->getSalesChartData($filial_id, $startDate, $endDate),
            'top_products' => $this->getTopProducts($filial_id, $startDate, $endDate),
            'top_sellers' => $this->getTopSellers($filial_id, $startDate, $endDate),
            'categories_chart' => $this->getCategoriesChartData($filial_id, $startDate, $endDate),
            'branches' => $isAdmin ? $this->getBranches() : [],
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filial_id' => $filial_id,
                'is_admin' => $isAdmin
            ]
        ];

        return $data;
    }

    private function getKPIs($filial_id, $start, $end)
    {
        $where = "WHERE v.data_venda BETWEEN :start AND :end AND v.status = 'pago'";
        $params = [':start' => $start . ' 00:00:00', ':end' => $end . ' 23:59:59'];

        if ($filial_id !== 'all') {
            $where .= " AND v.filial_id = :filial_id";
            $params[':filial_id'] = $filial_id;
        }

        $sql = "SELECT 
                    COUNT(v.id) as total_vendas,
                    SUM(v.valor_total) as faturamento,
                    SUM(v.desconto) as total_descontos,
                    AVG(v.valor_total) as ticket_medio
                FROM vendas v 
                $where";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $kpis = $stmt->fetch(PDO::FETCH_ASSOC);

        // Cálculo de Lucro Estimado (Faturamento - Custo dos itens)
        $sqlLucro = "SELECT SUM(vi.quantidade * vi.preco_custo) as total_custo 
                     FROM vendas_itens vi 
                     JOIN vendas v ON vi.venda_id = v.id 
                     $where";
        $stmtLucro = $this->db->prepare($sqlLucro);
        $stmtLucro->execute($params);
        $custo = $stmtLucro->fetch(PDO::FETCH_ASSOC);
        
        $kpis['lucro_estimado'] = $kpis['faturamento'] - ($custo['total_custo'] ?? 0);
        
        return $kpis;
    }

    private function getSalesChartData($filial_id, $start, $end)
    {
        $where = "WHERE v.data_venda BETWEEN :start AND :end AND v.status = 'pago'";
        $params = [':start' => $start . ' 00:00:00', ':end' => $end . ' 23:59:59'];

        if ($filial_id !== 'all') {
            $where .= " AND v.filial_id = :filial_id";
            $params[':filial_id'] = $filial_id;
        }

        $sql = "SELECT DATE(v.data_venda) as data, SUM(v.valor_total) as total 
                FROM vendas v 
                $where 
                GROUP BY DATE(v.data_venda) 
                ORDER BY DATE(v.data_venda) ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTopProducts($filial_id, $start, $end)
    {
        $where = "WHERE v.data_venda BETWEEN :start AND :end AND v.status = 'pago'";
        $params = [':start' => $start . ' 00:00:00', ':end' => $end . ' 23:59:59'];

        if ($filial_id !== 'all') {
            $where .= " AND v.filial_id = :filial_id";
            $params[':filial_id'] = $filial_id;
        }

        $sql = "SELECT vi.produto_id, p.nome, SUM(vi.quantidade) as total_qtd, SUM(vi.subtotal) as total_valor 
                FROM vendas_itens vi 
                JOIN vendas v ON vi.venda_id = v.id 
                JOIN produtos p ON vi.produto_id = p.id
                $where 
                GROUP BY vi.produto_id 
                ORDER BY total_valor DESC 
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTopSellers($filial_id, $start, $end)
    {
        $where = "WHERE v.data_venda BETWEEN :start AND :end AND v.status = 'pago'";
        $params = [':start' => $start . ' 00:00:00', ':end' => $end . ' 23:59:59'];

        if ($filial_id !== 'all') {
            $where .= " AND v.filial_id = :filial_id";
            $params[':filial_id'] = $filial_id;
        }

        $sql = "SELECT u.nome, SUM(v.valor_total) as total_vendas, COUNT(v.id) as qtd_vendas 
                FROM vendas v 
                JOIN usuarios u ON v.usuario_id = u.id 
                $where 
                GROUP BY v.usuario_id 
                ORDER BY total_vendas DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCategoriesChartData($filial_id, $start, $end)
    {
        $where = "WHERE v.data_venda BETWEEN :start AND :end AND v.status = 'pago'";
        $params = [':start' => $start . ' 00:00:00', ':end' => $end . ' 23:59:59'];

        if ($filial_id !== 'all') {
            $where .= " AND v.filial_id = :filial_id";
            $params[':filial_id'] = $filial_id;
        }

        $sql = "SELECT p.categoria, SUM(vi.subtotal) as total 
                FROM vendas_itens vi 
                JOIN vendas v ON vi.venda_id = v.id 
                JOIN produtos p ON vi.produto_id = p.id
                $where 
                GROUP BY p.categoria 
                ORDER BY total DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getBranches()
    {
        return $this->db->query("SELECT id, nome FROM filiais ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}
