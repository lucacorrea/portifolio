<?php
namespace App\Controllers;

use App\Models\Product;

class StockBaixoController extends BaseController {
    public function index() {
        checkAuth(['admin', 'gerente']);
        
        $productModel = new Product();
        $filialId = $_SESSION['filial_id'] ?? null;
        $isMatriz = $_SESSION['is_matriz'] ?? false;
        
        $targetFilial = !$isMatriz ? $filialId : null;
        
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        
        $filters = [
            'q' => $_GET['q'] ?? '',
            'categoria' => $_GET['categoria'] ?? '',
            'status' => $_GET['status'] ?? ''
        ];

        // Exportação Completa para Excel
        if (isset($_GET['export']) && $_GET['export'] == 'excel') {
            $pagination = $productModel->paginateStockAlarms($filters, 1, 999999, $targetFilial);
            $products = $pagination['data'];
            
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=estoque_baixo_" . date('Y-m-d') . ".xls");
            header("Pragma: no-cache");
            header("Expires: 0");
            
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta charset="utf-8">';
            echo '<style>th {background-color: #1e293b; color: white;} td, th {border: 1px solid #ddd; padding: 5px;}</style>';
            echo '</head><body>';
            echo '<h2>Relatório de Alertas de Estoque</h2>';
            echo '<p><strong>Gerado em:</strong> ' . date('d/m/Y H:i') . '</p>';
            echo '<table>';
            echo '<tr><th>CÓD / MATERIAL</th><th>CATEGORIA</th><th>QUANTIDADE</th><th>MÍNIMO</th><th>STATUS</th><th>SUGERIDO COMPRA</th></tr>';
            
            foreach ($products as $p) {
                $statusText = 'OK';
                if ($p['quantidade'] <= $p['estoque_minimo'] && $p['estoque_minimo'] > 0) {
                    $statusText = 'CRÍTICO';
                } elseif ($p['quantidade'] <= ($p['estoque_minimo'] * 1.5) && $p['estoque_minimo'] > 0) {
                    $statusText = 'BAIXO';
                }
                
                $sugerido = max(0, ($p['estoque_minimo'] * 1.5) - $p['quantidade']);
                $sugText = $sugerido > 0 ? "+ " . number_format($sugerido, 0, ',', '.') : "Suprido";
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($p['codigo'] . ' - ' . $p['nome']) . '</td>';
                echo '<td>' . htmlspecialchars($p['categoria']) . '</td>';
                echo '<td>' . number_format($p['quantidade'], 0, ',', '.') . ' ' . htmlspecialchars($p['unidade']) . '</td>';
                echo '<td>' . number_format($p['estoque_minimo'], 0, ',', '.') . '</td>';
                echo '<td>' . $statusText . '</td>';
                echo '<td>' . $sugText . '</td>';
                echo '</tr>';
            }
            echo '</table></body></html>';
            exit;
        }
        
        $stats = $productModel->getStockStats($targetFilial);
        $pagination = $productModel->paginateStockAlarms($filters, $page, 15, $targetFilial);
        $products = $pagination['data'];
        $allProducts = $productModel->all("nome ASC");
        $categories = $productModel->getCategories();
        
        $this->render('stock_baixo', [
            'products' => $products,
            'allProducts' => $allProducts,
            'stats' => $stats,
            'categories' => $categories,
            'filters' => $filters,
            'pagination' => $pagination
        ]);
    }
}
