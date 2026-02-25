<?php
namespace App\Controllers;

use App\Config\Database;
use PDO;

class DashboardController extends BaseController {
    public function index() {
        $db = Database::getInstance()->getConnection();
        
        $mes_atual = date('m');
        $ano_atual = date('Y');

        $stats = [
            'vendas_hoje' => $db->query("SELECT SUM(valor_total) FROM vendas WHERE DATE(data_venda) = CURRENT_DATE")->fetchColumn() ?: 0,
            'vendas_mes' => $db->query("SELECT SUM(valor_total) FROM vendas WHERE MONTH(data_venda) = $mes_atual AND YEAR(data_venda) = $ano_atual")->fetchColumn() ?: 0,
            'estoque_critico' => $db->query("SELECT COUNT(*) FROM produtos WHERE quantidade <= estoque_minimo")->fetchColumn(),
            'ticket_medio' => $db->query("SELECT AVG(valor_total) FROM vendas WHERE MONTH(data_venda) = $mes_atual")->fetchColumn() ?: 0,
            'margem_lucro' => $db->query("
                SELECT (SUM(vi.preco_unitario * vi.quantidade) - SUM(p.preco_custo * vi.quantidade)) / SUM(vi.preco_unitario * vi.quantidade) * 100
                FROM vendas_itens vi
                JOIN produtos p ON vi.produto_id = p.id
                JOIN vendas v ON vi.venda_id = v.id
                WHERE MONTH(v.data_venda) = $mes_atual
            ")->fetchColumn() ?: 0
        ];

        // Billing History (Last 6 months)
        $faturamento_historico = $db->query("
            SELECT DATE_FORMAT(data_venda, '%b') as mes, SUM(valor_total) as total
            FROM vendas
            WHERE data_venda >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(data_venda, '%Y-%m')
            ORDER BY data_venda ASC
        ")->fetchAll();

        // Top Vendas (Materiais mais vendidos)
        $top_produtos = $db->query("
            SELECT p.nome, SUM(vi.quantidade) as total_vendido, SUM(vi.quantidade * vi.preco_unitario) as receita
            FROM vendas_itens vi
            JOIN produtos p ON vi.produto_id = p.id
            GROUP BY vi.produto_id
            ORDER BY total_vendido DESC
            LIMIT 5
        ")->fetchAll();

        $recentes_vendas = $db->query("
            SELECT v.*, c.nome as cliente_nome 
            FROM vendas v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            ORDER BY v.data_venda DESC LIMIT 5
        ")->fetchAll();

        // Capture view content
        ob_start();
        $data = [
            'stats' => $stats,
            'top_produtos' => $top_produtos,
            'recentes_vendas' => $recentes_vendas,
            'faturamento_historico' => $faturamento_historico
        ];
        extract($data);
        require __DIR__ . "/../../../views/dashboard.view.php";
        $content = ob_get_clean();

        $this->render('layouts/main', [
            'title' => 'Gestão de Materiais Elétricos',
            'pageTitle' => 'Painel de Operações Comerciais',
            'content' => $content
        ]);
    }
}
