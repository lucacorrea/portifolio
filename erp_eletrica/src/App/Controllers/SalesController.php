<?php
namespace App\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Client;

class SalesController extends BaseController {
    public function index() {
        $saleModel = new Sale();
        $sales = $saleModel->getRecent();

        $this->render('sales', [
            'sales' => $sales,
            'title' => 'Ponto de Venda & Checkout',
            'pageTitle' => 'Terminal de Vendas (PDV)'
        ]);
    }

    public function search() {
        $term = $_GET['term'] ?? '';
        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, nome, preco_venda, unidade, imagens FROM produtos WHERE nome LIKE ? OR codigo LIKE ? LIMIT 10");
        $stmt->execute(["%$term%", "%$term%"]);
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
        exit;
    }

    public function checkout() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $saleModel = new Sale();
            $productModel = new Product();
            $db = \App\Config\Database::getInstance()->getConnection();

            try {
                $db->beginTransaction();

                $saleData = [
                    'cliente_id' => $data['cliente_id'] ?? null,
                    'usuario_id' => $_SESSION['usuario_id'],
                    'filial_id' => $_SESSION['filial_id'] ?? 1,
                    'valor_total' => $data['total'],
                    'forma_pagamento' => $data['pagamento']
                ];
                $saleId = $saleModel->create($saleData);

                // Se houver ID de pré-venda, marca como finalizado
                if (!empty($data['pv_id'])) {
                    $pvModel = new \App\Models\PreSale();
                    $pvModel->markAsFinalized($data['pv_id']);
                }

                foreach ($data['items'] as $item) {
                    $db->prepare("INSERT INTO vendas_itens (venda_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)")
                       ->execute([$saleId, $item['id'], $item['qty'], $item['price']]);

                    $productModel->updateStock($item['id'], $item['qty'], 'saida');
                }

                $db->commit();
                echo json_encode(['success' => true, 'sale_id' => $saleId]);
            } catch (\Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
    }

    public function list_recent() {
        $page = $_GET['page'] ?? 1;
        $perPage = 4;
        $saleModel = new Sale();
        
        $sales = $saleModel->getRecentPaginated($page, $perPage);
        $total = $saleModel->getTotalCount();
        
        echo json_encode([
            'sales' => $sales,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ]);
        exit;
    }

    public function get_sale() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit;
        
        $saleModel = new Sale();
        echo json_encode($saleModel->findById($id));
        exit;
    }

    public function cancel_sale() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
                exit;
            }

            $saleModel = new Sale();
            $productModel = new Product();
            $db = \App\Config\Database::getInstance()->getConnection();

            try {
                $db->beginTransaction();
                
                $sale = $saleModel->findById($id);
                if (!$sale || $sale['status'] == 'cancelado') {
                    throw new \Exception("Venda não encontrada ou já cancelada");
                }

                $saleModel->updateStatus($id, 'cancelado');

                foreach ($sale['itens'] as $item) {
                    $productModel->updateStock($item['produto_id'], $item['quantidade'], 'entrada');
                }

                $db->commit();
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
    }
}
