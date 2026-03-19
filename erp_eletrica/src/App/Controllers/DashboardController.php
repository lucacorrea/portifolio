<?php
namespace App\Controllers;

use App\Config\Database;
use PDO;

class DashboardController extends BaseController {
    public function index() {
        $db = Database::getInstance()->getConnection();
        
        $mes_atual = date('m');
        $ano_atual = date('Y');
        $filial_id = $_SESSION['filial_id'] ?? null;
        $is_matriz = $_SESSION['is_matriz'] ?? false;
        
        $where_filial = "";
        if (!$is_matriz && $filial_id) {
            $where_filial = " AND filial_id = $filial_id";
        }

        $cacheKey = "dash_main_" . ($is_matriz ? "matriz" : "filial_$filial_id");
        $cached = \App\Services\CacheService::get($cacheKey);

        if ($cached) {
            $stats = $cached['stats'];
            $faturamento_historico = $cached['history'];
            $top_produtos = $cached['top_products'];
        } else {
            $stats = [
                'vendas_hoje' => $db->query("SELECT SUM(valor_total) FROM vendas WHERE DATE(data_venda) = CURRENT_DATE $where_filial")->fetchColumn() ?: 0,
                'vendas_mes' => $db->query("SELECT SUM(valor_total) FROM vendas WHERE MONTH(data_venda) = $mes_atual AND YEAR(data_venda) = $ano_atual $where_filial")->fetchColumn() ?: 0,
                'estoque_critico' => $db->query("SELECT COUNT(*) FROM produtos WHERE quantidade <= estoque_minimo $where_filial")->fetchColumn(),
                'ticket_medio' => $db->query("SELECT AVG(valor_total) FROM vendas WHERE MONTH(data_venda) = $mes_atual $where_filial")->fetchColumn() ?: 0,
                'margem_lucro' => $db->query("
                    SELECT (SUM(vi.preco_unitario * vi.quantidade) - SUM(p.preco_custo * vi.quantidade)) / SUM(vi.preco_unitario * vi.quantidade) * 100
                    FROM vendas_itens vi
                    JOIN produtos p ON vi.produto_id = p.id
                    JOIN vendas v ON vi.venda_id = v.id
                    WHERE MONTH(v.data_venda) = $mes_atual " . ($is_matriz ? "" : "AND v.filial_id = $filial_id") . "
                ")->fetchColumn() ?: 0
            ];

            // Billing History (Last 6 months)
            $faturamento_historico = $db->query("
                SELECT DATE_FORMAT(data_venda, '%b') as mes, SUM(valor_total) as total
                FROM vendas
                WHERE data_venda >= DATE_SUB(NOW(), INTERVAL 6 MONTH) " . ($is_matriz ? "" : "AND filial_id = $filial_id") . "
                GROUP BY DATE_FORMAT(data_venda, '%Y-%m')
                ORDER BY data_venda ASC
            ")->fetchAll();

            // Top Vendas
            $top_produtos = $db->query("
                SELECT p.nome, SUM(vi.quantidade) as total_vendido, SUM(vi.quantidade * vi.preco_unitario) as receita
                FROM vendas_itens vi
                JOIN produtos p ON vi.produto_id = p.id
                JOIN vendas v ON vi.venda_id = v.id
                " . ($is_matriz ? "" : "WHERE v.filial_id = $filial_id") . "
                GROUP BY vi.produto_id
                ORDER BY total_vendido DESC
                LIMIT 5
            ")->fetchAll();

            \App\Services\CacheService::set($cacheKey, [
                'stats' => $stats,
                'history' => $faturamento_historico,
                'top_products' => $top_produtos
            ], 600); // 10 minutes cache
        }
        
        // --- REAL TIME STATS (ALWAYS FRESH) ---
        $stats['fiado_pendente'] = $db->query("
            SELECT SUM(COALESCE(valor, 0) - COALESCE(valor_pago, 0)) 
            FROM contas_receber 
            WHERE status != 'pago' " . ($is_matriz ? "" : "AND filial_id = $filial_id") . "
        ")->fetchColumn() ?: 0;

        $cashierModel = new \App\Models\Cashier();
        $caixaAberto = $cashierModel->getOpenForFilial($filial_id);
        $cashierSummary = $caixaAberto ? $cashierModel->getSummary($caixaAberto['id']) : null;
        // --------------------------------------

        $recentes_vendas = $db->query("
            SELECT v.*, c.nome as cliente_nome 
            FROM vendas v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            " . ($is_matriz ? "" : "WHERE v.filial_id = $filial_id") . "
            ORDER BY v.data_venda DESC LIMIT 5
        ")->fetchAll();

        $this->render('dashboard', [
            'stats' => $stats,
            'top_produtos' => $top_produtos,
            'recentes_vendas' => $recentes_vendas,
            'faturamento_historico' => $faturamento_historico,
            'caixaAberto' => $caixaAberto,
            'cashierSummary' => $cashierSummary,
            'title' => 'Gestão de Materiais Elétricos',
            'pageTitle' => 'Painel de Operações Comerciais'
        ]);
    }

    public function getRealtimeStats() {
        $db = Database::getInstance()->getConnection();
        $filial_id = $_SESSION['filial_id'] ?? null;
        $is_matriz = $_SESSION['is_matriz'] ?? false;
        
        $where_filial = "";
        if (!$is_matriz && $filial_id) {
            $where_filial = " AND filial_id = $filial_id";
        }

        // Get Cashier Summary (Realtime)
        $cashierModel = new \App\Models\Cashier();
        $caixaAberto = $cashierModel->getOpenForFilial($filial_id);
        $summary = $caixaAberto ? $cashierModel->getSummary($caixaAberto['id']) : null;

        // Correct Fiado Pending (Total from all time)
        $fiado_pendente = $db->query("
            SELECT SUM(COALESCE(valor, 0) - COALESCE(valor_pago, 0)) 
            FROM contas_receber 
            WHERE status != 'pago' " . ($is_matriz ? "" : "AND filial_id = $filial_id") . "
        ")->fetchColumn() ?: 0;

        $saldo_caixa = 0;
        if ($caixaAberto && $summary) {
            $saldo_caixa = ($caixaAberto['valor_abertura'] ?? 0) + ($summary['vendas_dinheiro'] ?? 0) + ($summary['suprimentos'] ?? 0) - ($summary['sangrias'] ?? 0);
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        echo json_encode([
            'ok' => true,
            'stats' => [
                'saldo_caixa' => (float)$saldo_caixa,
                'vendido_total' => (float)($summary['total_bruto'] ?? 0),
                'fiado_pendente' => (float)$fiado_pendente,
                'sangrias' => (float)($summary['sangrias'] ?? 0),
                'suprimentos' => (float)($summary['suprimentos'] ?? 0),
                'caixa_aberto' => !!$caixaAberto
            ]
        ]);
        exit;
    }
}
