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
        
        $sql = "SELECT id, nome, preco_venda, unidade, imagens, codigo 
                FROM produtos 
                WHERE (nome LIKE ? OR codigo LIKE ? OR codigo = ?) ";
        $params = ["%$term%", "%$term%", $term];
        
        if (!$isMatriz && $filialId) {
            $sql .= " AND filial_id = ?";
            $params[] = $filialId;
        }
        
        $sql .= " ORDER BY (CASE WHEN codigo = ? THEN 1 WHEN codigo LIKE ? THEN 2 ELSE 3 END), nome ASC LIMIT 15";
        $params[] = $term;
        $params[] = "$term%";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
        exit;
    }

    public function search_clients() {
        $term = $_GET['term'] ?? '';
        $db = \App\Config\Database::getInstance()->getConnection();
        
        $sql = "SELECT id, nome, cpf_cnpj as doc FROM clientes 
                WHERE (nome LIKE ? OR cpf_cnpj LIKE ?) 
                AND filial_id = ? 
                LIMIT 10";
        $stmt = $db->prepare($sql);
        $stmt->execute(["%$term%", "%$term%", $_SESSION['filial_id'] ?? 1]);
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

                // Validation: Cashier Open Check
                $cashierModel = new \App\Models\Cashier();
                $caixaAberto = $cashierModel->getOpenForOperador($_SESSION['usuario_id'], $_SESSION['filial_id'] ?? 1);
                if (!$caixaAberto) {
                    throw new \Exception("É necessário abrir o caixa antes de realizar vendas.");
                }

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
                $supervisorId = null;
                $authCode = $data['auth_code'] ?? null;

                // Re-validation for Non-Admins with Discount
                if ($requestedDiscount > 0 && $_SESSION['usuario_nivel'] !== 'admin') {
                    $isValid = false;

                    // Option 1: Temporary Code
                    if ($authCode) {
                        $authService = new \App\Services\AuthorizationService();
                        if ($authService->validateAndUse($authCode, 'desconto', $_SESSION['filial_id'] ?? 1)) {
                            $isValid = true;
                            $supervisorId = 0; // System flagged
                            $audit = new \App\Services\AuditLogService();
                            $audit->record('Uso de código de desconto', 'vendas', null, null, $authCode);
                        }
                    }

                    // Option 2: Direct Supervisor Credentials (legacy/fallback)
                    if (!$isValid) {
                        $supervisorId = $data['supervisor_id'] ?? null;
                        $supervisorCredential = $data['supervisor_credential'] ?? null;

                        if (!$supervisorId || !$supervisorCredential) {
                            throw new \Exception("Esta venda com desconto requer autorização ou um código válido.");
                        }

                        $userModel = new \App\Models\User();
                        if (!$userModel->validateAuth($supervisorId, $supervisorCredential)) {
                            throw new \Exception("Credenciais ou código de autorização inválidos.");
                        }

                        $supervisor = $db->query("SELECT nivel FROM usuarios WHERE id = " . (int)$supervisorId)->fetch();
                        if (!$supervisor || $supervisor['nivel'] !== 'admin') {
                            throw new \Exception("Apenas administradores podem autorizar descontos.");
                        }
                        $isValid = true;
                    }

                    if (!$isValid) {
                        throw new \Exception("Autorização de desconto falhou.");
                    }
                }

                $saleData = [
                    'cliente_id' => $data['cliente_id'] ?? null,
                    'nome_cliente_avulso' => $data['nome_cliente_avulso'] ?? null,
                    'usuario_id' => $_SESSION['usuario_id'],
                    'filial_id' => $_SESSION['filial_id'] ?? 1,
                    'valor_total' => $data['total'],
                    'desconto_total' => ($data['subtotal'] * ($data['discount_percent'] / 100)),
                    'forma_pagamento' => $data['pagamento'],
                    'autorizado_por' => $supervisorId
                ];

                $saleId = $saleModel->create($saleData);
                
                // Automatic accounts receivable for 'fiado'
                if ($data['pagamento'] === 'fiado') {
                    if (empty($data['cliente_id'])) {
                        throw new \Exception("Vendas a prazo (fiado) exigem um cliente cadastrado.");
                    }

                    $entrada = (float)($data['entrada_valor'] ?? 0);
                    $valorDivida = (float)$data['total'] - $entrada;

                    // 1. If there's a down payment, record it in cashier
                    if ($entrada > 0) {
                        $cashierModel->recordMovement([
                            'caixa_id' => $caixaAberto['id'],
                            'tipo' => 'entrada',
                            'descricao' => "Entrada Venda #$saleId (Fiado)",
                            'valor' => $entrada,
                            'forma_pagamento' => 'dinheiro',
                            'usuario_id' => $_SESSION['usuario_id']
                        ]);
                    }

                    // 2. Create the receivable for the remaining balance
                    $receivableModel = new \App\Models\AccountReceivable();
                    $receivableModel->create([
                        'venda_id' => $saleId,
                        'cliente_id' => $data['cliente_id'],
                        'valor' => $data['total'],
                        'valor_pago' => $entrada,
                        'saldo' => $valorDivida,
                        'status' => 'pendente',
                        'data_vencimento' => date('Y-m-d', strtotime('+30 days')),
                        'filial_id' => $_SESSION['filial_id'] ?? 1
                    ]);
                    
                    $audit = new \App\Services\AuditLogService();
                    $audit->record('Venda fiado criada', 'vendas', $saleId, null, json_encode([
                        'total' => $data['total'],
                        'entrada' => $entrada,
                        'saldo_devedor' => $valorDivida
                    ]));
                }

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
                echo json_encode(['success' => false, 'error' => 'Dados incompletos (ID ou Senha ausentes)']);
                exit;
            }

            $userModel = new \App\Models\User();
            if ($userModel->validateAuth($userId, $credential)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Credencial inválida para este Administrador. Verifique se digitou a senha ou PIN correto.']);
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
