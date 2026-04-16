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
            // Prepared statements for main stats
            $where_filial_query = $is_matriz ? "" : " AND filial_id = :filial_id";
            $params = $is_matriz ? [] : [':filial_id' => $filial_id];

            $stmt_hoje = $db->prepare("SELECT SUM(valor_total) FROM vendas WHERE DATE(data_venda) = CURRENT_DATE $where_filial_query");
            $stmt_hoje->execute($params);
            $vendas_hoje = $stmt_hoje->fetchColumn() ?: 0;

            $stmt_mes = $db->prepare("SELECT SUM(valor_total) FROM vendas WHERE MONTH(data_venda) = :mes AND YEAR(data_venda) = :ano $where_filial_query");
            $stmt_mes->execute(array_merge($params, [':mes' => $mes_atual, ':ano' => $ano_atual]));
            $vendas_mes = $stmt_mes->fetchColumn() ?: 0;

            $stmt_critico = $db->prepare("SELECT COUNT(*) FROM produtos WHERE quantidade <= estoque_minimo $where_filial_query");
            $stmt_critico->execute($params);
            $estoque_critico = $stmt_critico->fetchColumn() ?: 0;

            $stmt_ticket = $db->prepare("SELECT AVG(valor_total) FROM vendas WHERE MONTH(data_venda) = :mes $where_filial_query");
            $stmt_ticket->execute(array_merge($params, [':mes' => $mes_atual]));
            $ticket_medio = $stmt_ticket->fetchColumn() ?: 0;

            $sql_margem = "
                SELECT (SUM(vi.preco_unitario * vi.quantidade) - SUM(p.preco_custo * vi.quantidade)) / NULLIF(SUM(vi.preco_unitario * vi.quantidade), 0) * 100
                FROM vendas_itens vi
                JOIN produtos p ON vi.produto_id = p.id
                JOIN vendas v ON vi.venda_id = v.id
                WHERE MONTH(v.data_venda) = :mes " . ($is_matriz ? "" : "AND v.filial_id = :filial_id");
            $stmt_margem = $db->prepare($sql_margem);
            $stmt_margem->execute(array_merge([':mes' => $mes_atual], $params));
            $margem_lucro = $stmt_margem->fetchColumn() ?: 0;

            $stats = [
                'vendas_hoje' => $vendas_hoje,
                'vendas_mes' => $vendas_mes,
                'estoque_critico' => $estoque_critico,
                'ticket_medio' => $ticket_medio,
                'margem_lucro' => $margem_lucro
            ];

            // Billing History (Last 6 months)
            $sql_history = "
                SELECT DATE_FORMAT(data_venda, '%b') as mes, SUM(valor_total) as total
                FROM vendas
                WHERE data_venda >= DATE_SUB(NOW(), INTERVAL 6 MONTH) " . ($is_matriz ? "" : "AND filial_id = :filial_id") . "
                GROUP BY DATE_FORMAT(data_venda, '%Y-%m')
                ORDER BY data_venda ASC
            ";
            $stmt_history = $db->prepare($sql_history);
            $stmt_history->execute($params);
            $faturamento_historico = $stmt_history->fetchAll();

            // Traduzir meses para o gráfico
            $monthMap = [
                'Jan' => 'Jan', 'Feb' => 'Fev', 'Mar' => 'Mar', 'Apr' => 'Abr',
                'May' => 'Mai', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
                'Sep' => 'Set', 'Oct' => 'Out', 'Nov' => 'Nov', 'Dec' => 'Dez'
            ];
            foreach ($faturamento_historico as &$h) {
                if (isset($monthMap[$h['mes']])) {
                    $h['mes'] = $monthMap[$h['mes']];
                }
            }

            // Top Vendas
            $sql_top = "
                SELECT p.nome, SUM(vi.quantidade) as total_vendido, SUM(vi.quantidade * vi.preco_unitario) as receita
                FROM vendas_itens vi
                JOIN produtos p ON vi.produto_id = p.id
                JOIN vendas v ON vi.venda_id = v.id
                " . ($is_matriz ? "" : "WHERE v.filial_id = :filial_id") . "
                GROUP BY vi.produto_id
                ORDER BY total_vendido DESC
                LIMIT 5
            ";
            $stmt_top = $db->prepare($sql_top);
            $stmt_top->execute($params);
            $top_produtos = $stmt_top->fetchAll();

            \App\Services\CacheService::set($cacheKey, [
                'stats' => $stats,
                'history' => $faturamento_historico,
                'top_products' => $top_produtos
            ], 600); // 10 minutes cache
        }
        
        // --- REAL TIME STATS (ALWAYS FRESH) ---
        $sql_fiado = "
            SELECT SUM(COALESCE(valor, 0) - COALESCE(valor_pago, 0)) 
            FROM contas_receber 
            WHERE status != 'pago' " . ($is_matriz ? "" : "AND filial_id = :filial_id");
        $stmt_fiado = $db->prepare($sql_fiado);
        $stmt_fiado->execute($is_matriz ? [] : [':filial_id' => $filial_id]);
        $stats['fiado_pendente'] = $stmt_fiado->fetchColumn() ?: 0;

        $cashierModel = new \App\Models\Cashier();
        $caixaAberto = $cashierModel->getOpenForFilial($filial_id);
        $cashierSummary = $caixaAberto ? $cashierModel->getSummary($caixaAberto['id']) : null;
        // --------------------------------------

        $sql_recentes = "
            SELECT v.*, c.nome as cliente_nome 
            FROM vendas v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            " . ($is_matriz ? "" : "WHERE v.filial_id = :filial_id") . "
            ORDER BY v.data_venda DESC LIMIT 5
        ";
        $stmt_recentes = $db->prepare($sql_recentes);
        $stmt_recentes->execute($is_matriz ? [] : [':filial_id' => $filial_id]);
        $recentes_vendas = $stmt_recentes->fetchAll();

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
        $sql_fiado = "
            SELECT SUM(COALESCE(valor, 0) - COALESCE(valor_pago, 0)) 
            FROM contas_receber 
            WHERE status != 'pago' " . ($is_matriz ? "" : "AND filial_id = :filial_id");
        $stmt_fiado = $db->prepare($sql_fiado);
        $stmt_fiado->execute($is_matriz ? [] : [':filial_id' => $filial_id]);
        $fiado_pendente = $stmt_fiado->fetchColumn() ?: 0;

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
