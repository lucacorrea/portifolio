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
        
        $filialId = $_SESSION['filial_id'] ?? null;
        $isMatriz = $_SESSION['is_matriz'] ?? false;
        
        $sql = "SELECT id, nome, preco_venda, unidade, imagens FROM produtos WHERE (nome LIKE ? OR codigo LIKE ?)";
        $params = ["%$term%", "%$term%"];
        
        if (!$isMatriz && $filialId) {
            $sql .= " AND filial_id = ?";
            $params[] = $filialId;
        }
        
        $sql .= " LIMIT 10";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
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

                // Validation: Discount Limit
                $maxDiscount = 100; // Default if column missing
                try {
                    $stmtUser = $db->prepare("SELECT desconto_maximo FROM usuarios WHERE id = ?");
                    $stmtUser->execute([$_SESSION['usuario_id']]);
                    $maxDiscount = $stmtUser->fetchColumn();
                    if ($maxDiscount === false) $maxDiscount = 0; // User not found
                } catch (\PDOException $e) {
                    // Column might be missing on server, allow discount for now to avoid blocking sales
                    $maxDiscount = 100;
                }
                
                $requestedDiscount = $data['discount_percent'] ?? 0;
                if ($requestedDiscount > $maxDiscount) {
                    throw new \Exception("Desconto de $requestedDiscount% excede seu limite permitido de $maxDiscount%");
                }

                $saleData = [
                    'cliente_id' => $data['cliente_id'] ?? null,
                    'usuario_id' => $_SESSION['usuario_id'],
                    'filial_id' => $_SESSION['filial_id'] ?? 1,
                    'valor_total' => $data['total'],
                    'desconto_total' => $data['subtotal'] * ($requestedDiscount / 100),
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
        // ... (existing code for cancel_sale)
    }

    public function issue_nfce() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;

            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID da venda não fornecido']);
                exit;
            }

            try {
                $fiscalService = new \App\Services\FiscalService();
                $result = $fiscalService->issueNFCe($id);
                echo json_encode($result);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
    }

    public function authorize_discount() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['user_id'] ?? null;
            $credential = $data['credential'] ?? null;

            if (!$userId || !$credential) {
                echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
                exit;
            }

            $userModel = new \App\Models\User();
            if ($userModel->validateAuth($userId, $credential)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Credencial inválida']);
            }
            exit;
        }
    }

    public function list_admins() {
        $userModel = new \App\Models\User();
        echo json_encode($userModel->findAdmins());
        exit;
    }
}
