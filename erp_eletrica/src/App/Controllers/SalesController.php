<?php
namespace App\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Client;

class SalesController extends BaseController {
    public function index() {
        $saleModel = new Sale();
        $sales = $saleModel->getRecent();

        $cashierModel = new \App\Models\Cashier();
        $caixaAberto = $cashierModel->getOpenForOperador($_SESSION['usuario_id'], $_SESSION['filial_id'] ?? 1);

        $this->render('sales', [
            'sales' => $sales,
            'caixaAberto' => $caixaAberto,
            'title' => 'Ponto de Venda & Checkout',
            'pageTitle' => 'Terminal de Vendas (PDV)'
        ]);
    }

    public function search() {
        $term = trim($_GET['term'] ?? '');
        if (empty($term)) {
            echo json_encode([]);
            exit;
        }

        $db = \App\Config\Database::getInstance()->getConnection();
        $filialId = $_SESSION['filial_id'] ?? 1;
        $isMatriz = $_SESSION['is_matriz'] ?? false;
        
        $results = [];

        // 1. Search Products
        $sqlProd = "SELECT id, nome, preco_venda, unidade, imagens, codigo, 'product' as type 
                    FROM produtos 
                    WHERE (nome LIKE ? OR codigo LIKE ? OR codigo = ?) ";
        $paramsProd = ["%$term%", "%$term%", $term];
        
        if (!$isMatriz) {
            $sqlProd .= " AND filial_id = ?";
            $paramsProd[] = $filialId;
        }
        $sqlProd .= " ORDER BY (CASE WHEN codigo = ? THEN 1 WHEN codigo LIKE ? THEN 2 ELSE 3 END), nome ASC LIMIT 10";
        $paramsProd[] = $term;
        $paramsProd[] = "$term%";

        $stmtProd = $db->prepare($sqlProd);
        $stmtProd->execute($paramsProd);
        $products = $stmtProd->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($products as $p) $results[] = $p;

        // 2. Search Pre-Sales (Pending)
        $modelPV = new \App\Models\PreSale();
        $avulsoCol = $modelPV->columnExists('nome_cliente_avulso') ? 'pv.nome_cliente_avulso' : "''";
        
        $sqlPV = "
            SELECT pv.id, pv.codigo, pv.valor_total as preco_venda, 
                   COALESCE(c.nome, $avulsoCol, 'Consumidor') as nome, 
                   'UN' as unidade, '' as imagens, 'pre_sale' as type
            FROM pre_vendas pv 
            LEFT JOIN clientes c ON pv.cliente_id = c.id 
            WHERE pv.status = 'pendente' ";
            
        $paramsPV = [];
        if (!$isMatriz) {
            $sqlPV .= " AND pv.filial_id = ? ";
            $paramsPV[] = $filialId;
        }

        $termLike = "%$term%";
        $termInt = (int)$term;
        $sqlPV .= " AND (LOWER(pv.codigo) LIKE ? OR pv.id = ? OR LOWER(c.nome) LIKE ? OR LOWER($avulsoCol) LIKE ?) ";
        $paramsPV[] = strtolower($termLike);
        $paramsPV[] = $termInt;
        $paramsPV[] = strtolower($termLike);
        $paramsPV[] = strtolower($termLike);
        
        $sqlPV .= " LIMIT 5";
        
        $stmtPV = $db->prepare($sqlPV);
        $stmtPV->execute($paramsPV);
        $pvs = $stmtPV->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($pvs as $pv) {
            $pv['nome'] = "PRÉ-VENDA: " . $pv['codigo'] . " (" . $pv['nome'] . ")";
            $results[] = $pv;
        }

        // Sort: PVs first if they match PV code, then products
        usort($results, function($a, $b) use ($term) {
            if ($a['type'] === 'pre_sale' && str_contains(strtolower($a['codigo'] ?? ''), strtolower($term))) return -1;
            if ($b['type'] === 'pre_sale' && str_contains(strtolower($b['codigo'] ?? ''), strtolower($term))) return 1;
            return 0;
        });

        echo json_encode(array_slice($results, 0, 15));
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

            // --- Fiscal sanitizer helpers (same approach as acainhadinhos) ---
            $saneNCM = function(?string $v): string {
                $v = preg_replace('/\D+/', '', (string)$v);
                if (in_array($v, ['', '0', '00', '00000000', '000', '000000'], true)) return '21069090';
                if (preg_match('/^([0-9]{2}|[0-9]{8})$/', $v)) return $v;
                return '21069090';
            };
            $saneCFOP = function(?string $v): string {
                $v = preg_replace('/\D+/', '', (string)$v);
                if (in_array($v, ['', '0', '00', '000', '0000'], true)) return '5102';
                if (preg_match('/^[123567][0-9]{3}$/', $v)) return $v;
                return '5102';
            };
            $saneEAN = function(?string $v): string {
                $d = preg_replace('/\D+/', '', (string)$v);
                if ($d === '' || !in_array(strlen($d), [8, 12, 13, 14], true)) return 'SEM GTIN';
                return $d;
            };
            $saneUN = function(?string $v): string {
                $v = strtoupper(trim((string)$v));
                return ($v === '') ? 'UN' : $v;
            };
            $saneOrigem = function($v): int {
                return preg_match('/^[0-8]$/', (string)$v) ? (int)$v : 0;
            };
            $saneCSOSN = function($v): string {
                $v = (string)$v;
                return preg_match('/^(101|102|103|300|400|500|900)$/', $v) ? $v : '102';
            };
            $saneCEST = function(?string $v): ?string {
                $v = preg_replace('/\D+/', '', (string)$v);
                return ($v === '' || $v === '0000000') ? null : $v;
            };

            try {
                $db->beginTransaction();

                // Validation: Cashier Open Check
                $cashierModel = new \App\Models\Cashier();
                $caixaAberto = $cashierModel->getOpenForOperador($_SESSION['usuario_id'], $_SESSION['filial_id'] ?? 1);
                if (!$caixaAberto) {
                    throw new \Exception("É necessário abrir o caixa antes de realizar vendas.");
                }

                // Validation: Discount Limit
                $maxDiscount = 100;
                try {
                    $stmtUser = $db->prepare("SELECT desconto_maximo FROM usuarios WHERE id = ?");
                    $stmtUser->execute([$_SESSION['usuario_id']]);
                    $maxDiscount = $stmtUser->fetchColumn();
                    if ($maxDiscount === false) $maxDiscount = 0;
                } catch (\PDOException $e) {
                    $maxDiscount = 100;
                }

                $requestedDiscount = $data['discount_percent'] ?? 0;
                $supervisorId = null;
                $authCode = $data['auth_code'] ?? null;

                if ($requestedDiscount > 0 && $_SESSION['usuario_nivel'] !== 'admin') {
                    $isValid = false;

                    if ($authCode) {
                        $authService = new \App\Services\AuthorizationService();
                        if ($authService->validateAndUse($authCode, 'desconto', $_SESSION['filial_id'] ?? 1)) {
                            $isValid = true;
                            $supervisorId = 0;
                            $audit = new \App\Services\AuditLogService();
                            $audit->record('Uso de código de desconto', 'vendas', null, null, $authCode);
                        }
                    }

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

                // tipo_nota: 'fiscal' or 'nao_fiscal'
                $tipoNota = (isset($data['tipo_nota']) && $data['tipo_nota'] === 'fiscal') ? 'fiscal' : 'nao_fiscal';

                $saleData = [
                    'cliente_id'          => $data['cliente_id'] ?? null,
                    'nome_cliente_avulso' => $data['nome_cliente_avulso'] ?? null,
                    'usuario_id'          => $_SESSION['usuario_id'],
                    'filial_id'           => $_SESSION['filial_id'] ?? 1,
                    'valor_total'         => $data['total'],
                    'desconto_total'      => ($data['subtotal'] * ($data['discount_percent'] / 100)),
                    'forma_pagamento'     => $data['pagamento'],
                    'autorizado_por'      => $supervisorId,
                    'tipo_nota'           => $tipoNota,
                ];

                $saleId = $saleModel->create($saleData);

                // Automatic accounts receivable for 'fiado'
                if ($data['pagamento'] === 'fiado') {
                    if (empty($data['cliente_id'])) {
                        throw new \Exception("Vendas a prazo (fiado) exigem um cliente cadastrado.");
                    }

                    $entrada = (float)($data['entrada_valor'] ?? 0);
                    $valorDivida = (float)$data['total'] - $entrada;

                    $receivableModel = new \App\Models\AccountReceivable();
                    $receivableModel->create([
                        'venda_id'        => $saleId,
                        'cliente_id'      => $data['cliente_id'],
                        'valor'           => $data['total'],
                        'valor_pago'      => $entrada,
                        'saldo'           => $valorDivida,
                        'status'          => 'pendente',
                        'data_vencimento' => date('Y-m-d', strtotime('+30 days')),
                        'filial_id'       => $_SESSION['filial_id'] ?? 1,
                    ]);

                    $audit = new \App\Services\AuditLogService();
                    $audit->record('Venda fiado criada', 'vendas', $saleId, null, json_encode([
                        'total'        => $data['total'],
                        'entrada'      => $entrada,
                        'saldo_devedor'=> $valorDivida,
                    ]));
                }

                // Se houver ID de pré-venda, marca como finalizado
                if (!empty($data['pv_id'])) {
                    $pvModel = new \App\Models\PreSale();
                    $pvModel->markAsFinalized($data['pv_id']);
                }

                // Check which fiscal columns exist in vendas_itens
                $stmtCols = $db->query("DESCRIBE vendas_itens");
                $existingCols = array_column($stmtCols->fetchAll(\PDO::FETCH_ASSOC), 'Field');
                $hasFiscalCols = in_array('ncm', $existingCols);

                foreach ($data['items'] as $item) {
                    // For fiscal sales, pull fiscal data from the produto record
                    if ($tipoNota === 'fiscal' && $hasFiscalCols) {
                        $stmtProd = $db->prepare(
                            "SELECT ncm, cean, cest, cfop, csosn, origem, unidade FROM produtos WHERE id = ? LIMIT 1"
                        );
                        $stmtProd->execute([$item['id']]);
                        $prod = $stmtProd->fetch(\PDO::FETCH_ASSOC) ?: [];

                        $db->prepare(
                            "INSERT INTO vendas_itens (venda_id, produto_id, quantidade, preco_unitario, ncm, cean, cest, cfop, origem, csosn, unidade)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        )->execute([
                            $saleId,
                            $item['id'],
                            $item['qty'],
                            $item['price'],
                            $saneNCM($prod['ncm']  ?? null),
                            $saneEAN($prod['cean'] ?? null),
                            $saneCEST($prod['cest'] ?? null),
                            $saneCFOP($prod['cfop'] ?? null),
                            $saneOrigem($prod['origem'] ?? 0),
                            $saneCSOSN($prod['csosn'] ?? null),
                            $saneUN($prod['unidade'] ?? null),
                        ]);
                    } else {
                        // Non-fiscal: simple insert (original behaviour)
                        $db->prepare("INSERT INTO vendas_itens (venda_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)")
                           ->execute([$saleId, $item['id'], $item['qty'], $item['price']]);
                    }

                    $productModel->updateStock($item['id'], $item['qty'], 'saida');
                }

                $db->commit();

                // Record Cashier Movement
                $movementModel = new \App\Models\CashierMovement();

                if ($data['pagamento'] !== 'fiado') {
                    $movementModel->create([
                        'caixa_id'    => $caixaAberto['id'],
                        'tipo'        => 'entrada',
                        'valor'       => $data['total'],
                        'motivo'      => "Venda #$saleId (" . strtoupper($data['pagamento']) . ")",
                        'operador_id' => $_SESSION['usuario_id'],
                    ]);
                } else if ($data['pagamento'] === 'fiado' && ($data['entrada_valor'] ?? 0) > 0) {
                    $movementModel->create([
                        'caixa_id'    => $caixaAberto['id'],
                        'tipo'        => 'entrada',
                        'valor'       => (float)$data['entrada_valor'],
                        'motivo'      => "Entrada Venda #$saleId (Fiado)",
                        'operador_id' => $_SESSION['usuario_id'],
                    ]);
                }

                echo json_encode(['success' => true, 'sale_id' => $saleId, 'tipo_nota' => $tipoNota]);
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

    public function check_client_completeness() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['is_complete' => false, 'missing' => ['id']]);
            exit;
        }

        $model = new Client();
        $client = $model->find($id);
        
        if (!$client) {
            echo json_encode(['is_complete' => false, 'error' => 'Cliente não encontrado']);
            exit;
        }

        $missing = [];
        if (empty($client['cpf_cnpj'])) $missing[] = 'cpf_cnpj';
        if (empty($client['endereco'])) $missing[] = 'endereco';
        if (empty($client['telefone'])) $missing[] = 'telefone';

        echo json_encode([
            'is_complete' => empty($missing),
            'missing' => $missing,
            'client' => $client
        ]);
        exit;
    }

    public function update_client_quick() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID ausente']);
                exit;
            }

            try {
                $model = new Client();
                $model->save($data);
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
    }
}
