<?php
namespace App\Controllers;

use App\Models\BaseModel;

class ProductivityController extends BaseController {
    public function index() {
        checkAuth(['admin', 'gerente']);
        
        $db = \App\Config\Database::getInstance()->getConnection();
        
        // Capture filters
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
        $to = $_GET['to'] ?? date('Y-m-d');
        $userFilter = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;

        // Fetch user list for filter
        $users = $db->query("SELECT id, nome FROM usuarios ORDER BY nome ASC")->fetchAll(\PDO::FETCH_ASSOC);
        
        // 1. Fetch Sales and Commissions data for Charts (Filtered)
        $whereCharts = "WHERE v.data_venda BETWEEN ? AND ? AND v.status != 'cancelado'";
        $paramsCharts = [$from . ' 00:00:00', $to . ' 23:59:59'];
        
        if ($userFilter) {
            $whereCharts .= " AND v.usuario_id = ?";
            $paramsCharts[] = $userFilter;
        }

        $sqlCharts = "
            SELECT 
                DATE(v.data_venda) as dia,
                SUM(v.valor_total) as total_vendas,
                SUM(vi.valor_comissao) as total_comissoes
            FROM vendas v
            LEFT JOIN vendas_itens vi ON v.id = vi.venda_id
            $whereCharts
            GROUP BY DATE(v.data_venda)
            ORDER BY dia ASC";
        
        $stmtCharts = $db->prepare($sqlCharts);
        $stmtCharts->execute($paramsCharts);
        $chartData = $stmtCharts->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Fetch Employee Rankings (Filtered)
        $whereRanking = "AND v.data_venda BETWEEN ? AND ? ";
        $paramsRanking = [$from . ' 00:00:00', $to . ' 23:59:59'];
        
        $sqlRanking = "
            SELECT 
                u.nome,
                u.nivel,
                COALESCE(SUM(v.valor_total), 0) as vendas_montante,
                COALESCE(SUM(vi.valor_comissao), 0) as comissao_montante,
                COUNT(DISTINCT v.id) as total_vendas
            FROM usuarios u
            LEFT JOIN vendas v ON u.id = v.usuario_id AND v.status != 'cancelado' $whereRanking
            LEFT JOIN vendas_itens vi ON v.id = vi.venda_id
            GROUP BY u.id
            ORDER BY vendas_montante DESC";
        
        $stmtRanking = $db->prepare($sqlRanking);
        $stmtRanking->execute($paramsRanking);
        $rankings = $stmtRanking->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Paginated Audit Logs (Filtered)
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 10;
        $offset = ($page - 1) * $perPage;
        
        $whereAudit = "WHERE 1=1";
        $paramsAudit = [];

        if ($from) {
            $whereAudit .= " AND a.created_at >= ?";
            $paramsAudit[] = $from . ' 00:00:00';
        }
        if ($to) {
            $whereAudit .= " AND a.created_at <= ?";
            $paramsAudit[] = $to . ' 23:59:59';
        }
        if ($userFilter) {
            $whereAudit .= " AND a.usuario_id = ?";
            $paramsAudit[] = $userFilter;
        }

        $sqlAuditTotal = "SELECT COUNT(*) FROM audit_logs a $whereAudit";
        $stmtTotal = $db->prepare($sqlAuditTotal);
        $stmtTotal->execute($paramsAudit);
        $totalAudit = $stmtTotal->fetchColumn();
        $pages = ceil($totalAudit / $perPage);

        $sqlAudit = "
            SELECT a.*, u.nome as usuario_nome
            FROM audit_logs a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            $whereAudit
            ORDER BY a.created_at DESC
            LIMIT $perPage OFFSET $offset";
        
        $stmtAudit = $db->prepare($sqlAudit);
        $stmtAudit->execute($paramsAudit);
        $auditLogs = $stmtAudit->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('productivity', [
            'chartData' => $chartData,
            'rankings' => $rankings,
            'auditLogs' => $auditLogs,
            'users' => $users,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'user_id' => $userFilter
            ],
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
