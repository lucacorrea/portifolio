<?php
namespace App\Controllers;

use App\Models\PreSale;
use App\Models\Product;
use App\Models\Client;

class PreSaleController extends BaseController {
    public function index() {
        $model = new PreSale();
        $recent = $model->getRecent();

        $this->render('pre_sales', [
            'recent' => $recent,
            'title' => 'Terminal de Pré-Venda',
            'pageTitle' => 'Geração de Orçamentos e Fichas'
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $model = new PreSale();
            
            $data['usuario_id'] = $_SESSION['usuario_id'];
            $data['filial_id'] = $_SESSION['filial_id'] ?? 1;

            try {
                $result = $model->create($data);
                echo json_encode(['success' => true, 'id' => $result['id'], 'codigo' => $result['codigo']]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
    }

    public function get_by_code() {
        $code = $_GET['code'] ?? '';
        $model = new PreSale();
        $pv = $model->findByCode($code);
        echo json_encode($pv);
        exit;
    }

    public function list_pending() {
        $db = \App\Config\Database::getInstance()->getConnection();
        $recent = $db->query("
            SELECT pv.id, pv.codigo, pv.valor_total, c.nome as cliente_nome, u.nome as vendedor_nome 
            FROM pre_vendas pv 
            LEFT JOIN clientes c ON pv.cliente_id = c.id 
            LEFT JOIN usuarios u ON pv.usuario_id = u.id
            WHERE pv.status = 'pendente' 
            ORDER BY pv.created_at DESC LIMIT 20
        ")->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode($recent);
        exit;
    }
}
