<?php
namespace App\Controllers;

use App\Models\BaseModel;

class ProductivityController extends BaseController {
    public function index() {
        checkAuth(['admin', 'gerente']);
        
        $db = \App\Config\Database::getInstance()->getConnection();
        
        // 1. Fetch Sales and Commissions data for Charts (Last 7 days)
        $sqlCharts = "
            SELECT 
                DATE(v.data_venda) as dia,
                SUM(v.valor_total) as total_vendas,
                SUM(vi.valor_comissao) as total_comissoes
            FROM vendas v
            LEFT JOIN vendas_itens vi ON v.id = vi.venda_id
            WHERE v.data_venda >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND v.status != 'cancelado'
            GROUP BY DATE(v.data_venda)
            ORDER BY dia ASC";
        $chartData = $db->query($sqlCharts)->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Fetch Employee Rankings (Sales vs Commission)
        $sqlRanking = "
            SELECT 
                u.nome,
                u.nivel,
                SUM(v.valor_total) as vendas_montante,
                SUM(vi.valor_comissao) as comissao_montante,
                COUNT(DISTINCT v.id) as total_vendas
            FROM usuarios u
            LEFT JOIN vendas v ON u.id = v.usuario_id AND v.status != 'cancelado'
            LEFT JOIN vendas_itens vi ON v.id = vi.venda_id
            GROUP BY u.id
            ORDER BY vendas_montante DESC";
        $rankings = $db->query($sqlRanking)->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Paginated Audit Logs
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 10;
        $offset = ($page - 1) * $perPage;
        
        $sqlAuditTotal = "SELECT COUNT(*) FROM audit_logs";
        $totalAudit = $db->query($sqlAuditTotal)->fetchColumn();
        $pages = ceil($totalAudit / $perPage);

        $sqlAudit = "
            SELECT a.*, u.nome as usuario_nome
            FROM audit_logs a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            ORDER BY a.created_at DESC
            LIMIT $perPage OFFSET $offset";
        $auditLogs = $db->query($sqlAudit)->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('productivity', [
            'chartData' => $chartData,
            'rankings' => $rankings,
            'auditLogs' => $auditLogs,
            'pagination' => [
                'current' => $page,
                'pages' => $pages,
                'total' => $totalAudit
            ],
            'title' => 'Gestão de Produtividade',
            'pageTitle' => 'Relatórios de Produtividade & Auditoria'
        ]);
    }
}
