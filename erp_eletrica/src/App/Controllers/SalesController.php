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
        $caixaAberto = $cashierModel->getOpenForFilial($_SESSION['filial_id'] ?? 1);

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

        // 1. Search Products (Global catalog for Matriz, Branch-specific for others)
        $join = ((int)$filialId === 1) ? "LEFT JOIN" : "INNER JOIN";

        $sqlProd = "SELECT p.id, p.nome, p.preco_venda, p.preco_venda_2, p.preco_venda_3, p.unidade, p.imagens, p.codigo, 'product' as type,
                    COALESCE(ef.quantidade, 0) as stock_qty
                    FROM produtos p
                    $join estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                    WHERE (p.nome LIKE ? OR p.codigo LIKE ? OR p.codigo = ?) ";
        $paramsProd = [$filialId, "%$term%", "%$term%", $term];

        $sqlProd .= " ORDER BY (CASE WHEN p.codigo = ? THEN 1 WHEN p.codigo LIKE ? THEN 2 ELSE 3 END), p.nome ASC LIMIT 15";
        $paramsProd[] = $term;
        $paramsProd[] = "$term%";

        try {
            $stmtProd = $db->prepare($sqlProd);
            $stmtProd->execute($paramsProd);
            $products = $stmtProd->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($products as $p) $results[] = $p;
        } catch (\PDOException $e) {
            // Fallback: If preco_venda_2 or 3 are missing, try without them
            if (str_contains($e->getMessage(), 'Unknown column')) {
                $sqlProdFallback = "SELECT p.id, p.nome, p.preco_venda, 0 as preco_venda_2, 0 as preco_venda_3, p.unidade, p.imagens, p.codigo, 'product' as type,
                            COALESCE(ef.quantidade, 0) as stock_qty
                            FROM produtos p
                            $join estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                            WHERE (p.nome LIKE ? OR p.codigo LIKE ? OR p.codigo = ?)
                            ORDER BY (CASE WHEN p.codigo = ? THEN 1 WHEN p.codigo LIKE ? THEN 2 ELSE 3 END), p.nome ASC LIMIT 15";
                $stmtProd = $db->prepare($sqlProdFallback);
                $stmtProd->execute($paramsProd);
                $products = $stmtProd->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($products as $p) $results[] = $p;
            } else {
                throw $e;
            }
        }

        // 2. Search Pre-Sales (Pending) - continues normally
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

        header('Content-Type: application/json');
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
                $caixaAberto = $cashierModel->getOpenForFilial($_SESSION['filial_id'] ?? 1);
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

                // Resolve customer data for persistence (Açaidinhos style)
                $cpfPersist = null;
                $nomePersist = $data['nome_cliente_avulso'] ?? null;
                
                if (!empty($data['cliente_id'])) {
                    $stmtC = $db->prepare("SELECT nome, cpf_cnpj FROM clientes WHERE id = ?");
                    $stmtC->execute([$data['cliente_id']]);
                    $cRow = $stmtC->fetch(\PDO::FETCH_ASSOC);
                    if ($cRow) {
                        $cpfPersist = $cRow['cpf_cnpj'];
                        $nomePersist = $cRow['nome'];
                    }
                }
                
                // Fallback for avulso CPF sent from frontend (if implemented there yet)
                if (empty($cpfPersist) && !empty($data['cpf_cliente'])) {
                    $cpfPersist = $data['cpf_cliente'];
                }

                $saleData = [
                    'cliente_id'          => $data['cliente_id'] ?? null,
                    'nome_cliente_avulso' => $data['nome_cliente_avulso'] ?? null,
                    'cpf_cliente'         => $cpfPersist,
                    'cliente_nome'        => $nomePersist,
                    'usuario_id'          => $_SESSION['usuario_id'],
                    'filial_id'           => $_SESSION['filial_id'] ?? 1,
                    'valor_total'         => $data['total'],
                    'desconto_total'      => ($data['subtotal'] * ($data['discount_percent'] / 100)),
                    'forma_pagamento'     => $data['pagamento'],
                    'autorizado_por'      => $supervisorId,
                    'tipo_nota'           => $tipoNota,
                    'valor_recebido'      => isset($data['valor_recebido']) ? (float)$data['valor_recebido'] : null,
                    'troco'               => isset($data['troco']) ? (float)$data['troco'] : null,
                    'taxa_cartao'         => isset($data['taxa_cartao']) ? (float)$data['taxa_cartao'] : 0,
                ];

                // Get Seller Commission Data
                $stmtSeller = $db->prepare("SELECT comissao_ativa, comissao_porcentagem FROM usuarios WHERE id = ?");
                $stmtSeller->execute([$_SESSION['usuario_id']]);
                $seller = $stmtSeller->fetch(\PDO::FETCH_ASSOC);
                $commActive = ($seller['comissao_ativa'] ?? 0) == 1;
                $commRate = (float)($seller['comissao_porcentagem'] ?? 0);

                $saleId = $saleModel->create($saleData);

                // Automatic accounts receivable for 'fiado'
                if ($data['pagamento'] === 'fiado') {
                    if (empty($data['cliente_id'])) {
                        throw new \Exception("Vendas a prazo (fiado) exigem um cliente cadastrado.");
                    }

                    $entrada = (float)($data['entrada_valor'] ?? 0);
                    $entradaMetodo = $data['entrada_metodo'] ?? 'dinheiro';
                    $valorDivida = (float)$data['total'] - $entrada;

                    $receivableModel = new \App\Models\AccountReceivable();
                    $receivableId = $receivableModel->create([
                        'venda_id'        => $saleId,
                        'cliente_id'      => $data['cliente_id'],
                        'valor'           => $data['total'],
                        'valor_pago'      => $entrada,
                        'saldo'           => $valorDivida,
                        'status'          => ($valorDivida <= 0) ? 'pago' : 'pendente',
                        'data_vencimento' => date('Y-m-d', strtotime('+30 days')),
                        'filial_id'       => $_SESSION['filial_id'] ?? 1,
                    ]);

                    if ($entrada > 0) {
                        $paymentModel = new \App\Models\AccountReceivablePayment();
                        $paymentModel->create([
                            'fiado_id' => $receivableId,
                            'valor' => $entrada,
                            'metodo' => strtoupper($entradaMetodo)
                        ]);

                        if (strtolower($entradaMetodo) === 'dinheiro') {
                            $movementModel = new \App\Models\CashierMovement();
                            $movementModel->create([
                                'caixa_id' => $caixaAberto['id'],
                                'tipo' => 'entrada',
                                'valor' => $entrada,
                                'motivo' => "Entrada Venda #{$saleId} Fiado - Cliente: {$nomePersist}",
                                'operador_id' => $_SESSION['usuario_id']
                            ]);
                        }
                    }

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
                $hasCfop = in_array('cfop', $existingCols);

                foreach ($data['items'] as $item) {
                     // Check stock before proceeding
                    if (!$productModel->hasEnoughStock($item['id'], $item['qty'])) {
                        $stmtProd = $db->prepare("SELECT nome FROM produtos WHERE id = ?");
                        $stmtProd->execute([$item['id']]);
                        $productName = $stmtProd->fetchColumn();
                        throw new \Exception("Estoque insuficiente para o produto: $productName. Verifique o saldo atual.");
                    }

                     // Get product data regardless
                    $stmtProd = $db->prepare("SELECT * FROM produtos WHERE id = ? LIMIT 1");
                    $stmtProd->execute([$item['id']]);
                    $prod = $stmtProd->fetch(\PDO::FETCH_ASSOC) ?: [];

                    // Calculate Commission for this item
                    $itemPriceTier = (int)($item['price_tier'] ?? 1);
                    $itemCommVal = 0;
                    if ($commActive && ($itemPriceTier === 2 || $itemPriceTier === 3)) {
                        $itemCommVal = ($item['price'] * $item['qty']) * ($commRate / 100);
                    }

                    if ($tipoNota === 'fiscal' && $hasFiscalCols) {
                        $ncm = $saneNCM($prod['ncm']  ?? null);
                        $ean = $saneEAN($prod['cean'] ?? null);
                        $cest = $saneCEST($prod['cest'] ?? null);
                        $origem = $saneOrigem($prod['origem'] ?? 0);
                        $csosn = $saneCSOSN($prod['csosn'] ?? null);
                        $unidade = $saneUN($prod['unidade'] ?? null);

                        $cfopSql = $hasCfop ? ", cfop" : "";
                        $cfopPlace = $hasCfop ? ", ?" : "";
                        $cfopVal = $hasCfop ? [$saneCFOP($prod['cfop'] ?? null)] : [];

                        $sqlItem = "INSERT INTO vendas_itens (venda_id, produto_id, quantidade, preco_unitario, preco_tier, valor_comissao, comissao_percentual_aplicado, ncm, cean, cest $cfopSql, origem, csosn, unidade)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ? $cfopPlace, ?, ?, ?)";
                        
                        $paramsItem = array_merge([
                            $saleId, $item['id'], $item['qty'], $item['price'], $itemPriceTier, $itemCommVal, $commRate,
                            $ncm, $ean, $cest
                        ], $cfopVal, [$origem, $csosn, $unidade]);

                        $db->prepare($sqlItem)->execute($paramsItem);
                    } else {
                        // Non-fiscal: simple insert
                        $db->prepare("INSERT INTO vendas_itens (venda_id, produto_id, quantidade, preco_unitario, preco_tier, valor_comissao, comissao_percentual_aplicado) VALUES (?, ?, ?, ?, ?, ?, ?)")
                           ->execute([$saleId, $item['id'], $item['qty'], $item['price'], $itemPriceTier, $itemCommVal, $commRate]);
                    }

                    $productModel->updateStock($item['id'], $item['qty'], 'saida', $_SESSION['filial_id'] ?? 1);
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

    public function sold_list() {
        $filterDefaults = [
            'data_inicio' => '',
            'data_fim' => '',
            'status' => '',
            'tipo_nota' => '',
            'forma_pagamento' => ''
        ];
        
        $this->render('vendidos', [
            'filters' => $filterDefaults,
            'title' => 'Histórico de Vendas',
            'pageTitle' => 'Gestão de Vendas Realizadas'
        ]);
    }

    public function sold_search() {
        $filters = $_GET;
        $page = (int)($filters['page'] ?? 1);
        $perPage = (int)($filters['perPage'] ?? 9);
        
        $saleModel = new Sale();
        $sales = $saleModel->getFiltered($filters, $page, $perPage);
        $total = $saleModel->getTotalFiltered($filters);
        
        foreach ($sales as &$s) {
            $s['data_formatada'] = date('d/m/Y H:i', strtotime($s['data_venda']));
            $s['valor_formatado'] = number_format($s['valor_total'], 2, ',', '.');
        }

        echo json_encode([
            'sales' => $sales,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ]);
        exit;
    }

    public function get_sale_detail() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
            exit;
        }
        
        $saleModel = new Sale();
        $sale = $saleModel->findById($id);
        
        if (!$sale) {
            echo json_encode(['success' => false, 'error' => 'Venda não encontrada']);
            exit;
        }

        $sale['data_formatada'] = date('d/m/Y H:i', strtotime($sale['data_venda']));
        foreach ($sale['itens'] as &$item) {
            $item['subtotal'] = $item['quantidade'] * $item['preco_unitario'];
            $item['preco_formatado'] = number_format($item['preco_unitario'], 2, ',', '.');
            $item['subtotal_formatado'] = number_format($item['subtotal'], 2, ',', '.');
        }

        echo json_encode(['success' => true, 'sale' => $sale]);
        exit;
    }

    public function cancel_sale() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
        
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $motivo = $data['motivo'] ?? 'Cancelamento solicitado pelo usuário';

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID da venda não fornecido']);
            exit;
        }

        $authCode = $data['auth_code'] ?? null;
        if (($_SESSION['usuario_nivel'] ?? '') !== 'admin') {
            if (!$authCode) {
                echo json_encode(['success' => false, 'error' => 'Código de autorização de cancelamento é obrigatório.']);
                exit;
            }
            $authService = new \App\Services\AuthorizationService();
            if (!$authService->validateAndUse($authCode, 'cancelamento', $_SESSION['filial_id'] ?? 1)) {
                echo json_encode(['success' => false, 'error' => 'Código de autorização inválido, expirado ou já utilizado. Verifica se o tipo é "Cancelamento".']);
                exit;
            }
        }

        $db = \App\Config\Database::getInstance()->getConnection();
        $saleModel = new Sale();
        $productModel = new Product();
        $movementModel = new \App\Models\CashierMovement();
        $cashierModel = new \App\Models\Cashier();

        try {
            $db->beginTransaction();

            $sale = $saleModel->findById($id);
            if (!$sale) throw new \Exception("Venda não encontrada.");
            
            // Modelo de cancelamento: 'por_chave' (Padrão), 'por_substituicao', 'por_motivo' (Interno)
            $modelo = $data['modelo'] ?? 'por_chave'; 
            $chaveSubst = $data['chave_substituta'] ?? null;

            // 2. Busca qualquer rastro de autorização (100 ou 150) na tabela de notas
            $stF = $db->prepare("SELECT id FROM nfce_emitidas WHERE venda_id = ? AND TRIM(status_sefaz) IN ('100', '150') LIMIT 1");
            $stF->execute([$id]);
            $hasFiscalRecord = (bool)$stF->fetch();

            $isAlreadyCancelled = ($sale['status'] === 'cancelado');
            $requestTipo = $data['tipo'] ?? $sale['tipo_nota'];
            $isFiscalMode = ($modelo !== 'por_motivo' && ($requestTipo === 'fiscal' || $sale['tipo_nota'] === 'fiscal' || $hasFiscalRecord));

            // Fluxo Fiscal (SEFAZ)
            if ($isFiscalMode) {
                try {
                    // Prepara ambiente para carregar as bibliotecas de NF-e
                    $nfceDir = dirname(__DIR__, 3) . '/nfce';
                    $_REQUEST['venda_id'] = $id;
                    if (file_exists($nfceDir . '/config.php')) {
                        require_once $nfceDir . '/config.php';
                    }
                    
                    // Validação do Certificado
                    if (!defined('PFX_PATH') || !is_file(PFX_PATH)) {
                        throw new \Exception("Certificado digital não configurado.");
                    }

                    // Busca protocolo da emissão original
                    $stP = $db->prepare("SELECT * FROM nfce_emitidas WHERE venda_id = ? AND status_sefaz IN ('100', '150') ORDER BY id DESC LIMIT 1");
                    $stP->execute([$id]);
                    $nfceEmitida = $stP->fetch(\PDO::FETCH_ASSOC);

                    if ($nfceEmitida) {
                        $chave = $nfceEmitida['chave'];
                        $protocolo = $nfceEmitida['protocolo'];
                        $xJust = (mb_strlen($motivo) < 15) ? str_pad($motivo, 15, '.') : $motivo;
                        
                        $configJson = json_encode([
                            'atualizacao' => date('Y-m-d H:i:s'),
                            'tpAmb'       => (int)TP_AMB,
                            'razao'       => EMIT_XNOME,
                            'siglaUF'     => EMIT_UF,
                            'cnpj'        => EMIT_CNPJ,
                            'schemes'     => 'PL_009_V4',
                            'versao'      => '4.00'
                        ]);
                        
                        $cert = file_get_contents(PFX_PATH);
                        $tools = new \NFePHP\NFe\Tools($configJson, \NFePHP\Common\Certificate::readPfx($cert, PFX_PASSWORD));
                        $tools->model('65');
                        
                        if ($modelo === 'por_substituicao') {
                            if (!$chaveSubst || strlen($chaveSubst) !== 44) {
                                throw new \Exception("Chave substituta inválida ou não informada.");
                            }
                            $response = $tools->sefazCancelSubst($chave, $protocolo, $chaveSubst, $xJust);
                        } else {
                            $response = $tools->sefazCancela($chave, $xJust, $protocolo);
                        }

                        $std = new \NFePHP\NFe\Common\Standardize();
                        $res = $std->toStd($response);
                        
                        $cStat = (string)($res->retEvento->infEvento->cStat ?? $res->cStat ?? '');
                        $xMotivo = (string)($res->retEvento->infEvento->xMotivo ?? $res->xMotivo ?? 'Erro desconhecido na SEFAZ');

                        $sucessoSefaz = in_array($cStat, ['135', '136', '101', '128', '155']);
                        if (!$sucessoSefaz) {
                            throw new \Exception("SEFAZ Rejeitou: ($cStat) $xMotivo");
                        }

                        $stN = $db->prepare("UPDATE nfce_emitidas SET status_sefaz = '101', mensagem = 'Cancelamento homologado ({$cStat})' WHERE venda_id = ?");
                        $stN->execute([$id]);
                    }
                } catch (\Throwable $sefazError) {
                    throw new \Exception("Erro SEFAZ: " . $sefazError->getMessage());
                }
            }

            // --- REVERSÃO DE ESTOQUE E FINANCEIRO ---
            // SÓ executa se a venda ainda estava 'concluido' (evita duplicidade no re-cancelamento fiscal)
            if (!$isAlreadyCancelled) {
                // 1. Reverter Estoque
                foreach ($sale['itens'] as $item) {
                    $productModel->updateStock($item['produto_id'], $item['quantidade'], 'entrada');
                }

                // 2. Financeiro (Estorno de Caixa)
                if ($sale['forma_pagamento'] !== 'fiado') {
                    $caixaAberto = $cashierModel->getOpenForFilial($sale['filial_id']);
                    if ($caixaAberto) {
                        $movementModel->create([
                            'caixa_id' => $caixaAberto['id'],
                            'tipo' => 'saida',
                            'valor' => $sale['valor_total'],
                            'motivo' => "Estorno Venda #{$id} - Motivo: {$motivo}",
                            'operador_id' => $_SESSION['usuario_id']
                        ]);
                    }
                } else {
                    $db->prepare("UPDATE contas_receber SET status = 'cancelado' WHERE venda_id = ?")->execute([$id]);
                }

                // 3. Atualizar Status Interno
                $saleModel->updateStatus($id, 'cancelado');
            }

            // 4. Auditoria
            $audit = new \App\Services\AuditLogService();
            $auditText = $isAlreadyCancelled ? "Regularização Fiscal (Cancelamento SEFAZ)" : $motivo;
            $audit->record('Cancelamento de venda', 'vendas', $id, null, $auditText . ($isFiscal ? " (Sincronizado SEFAZ)" : ""));

            $db->commit();
            echo json_encode(['success' => true]);

        } catch (\Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function exchange_item() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

        $data = json_decode(file_get_contents('php://input'), true);
        $vendaId = $data['venda_id'] ?? null;
        $itemId = $data['item_id'] ?? null; // ID da linha em vendas_itens
        $newProdId = $data['new_product_id'] ?? null;
        $newQty = $data['new_qty'] ?? 1;
        $newPrice = $data['new_price'] ?? 0;

        if (!$vendaId || !$itemId || !$newProdId) {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos para a troca']);
            exit;
        }

        $db = \App\Config\Database::getInstance()->getConnection();
        $productModel = new Product();
        $saleModel = new Sale();

        try {
            $db->beginTransaction();

            // 1. Obter item atual
            $stmtItem = $db->prepare("SELECT * FROM vendas_itens WHERE id = ? AND venda_id = ?");
            $stmtItem->execute([$itemId, $vendaId]);
            $oldItem = $stmtItem->fetch(\PDO::FETCH_ASSOC);
            if (!$oldItem) throw new \Exception("Item original não encontrado.");

            // 2. Devolver estoque antigo
            $productModel->updateStock($oldItem['produto_id'], $oldItem['quantidade'], 'entrada');

            // 3. Verificar estoque novo
            if (!$productModel->hasEnoughStock($newProdId, $newQty)) {
                throw new \Exception("Estoque insuficiente para o novo produto.");
            }

            // 4. Debitar estoque novo
            $productModel->updateStock($newProdId, $newQty, 'saida');

            // 5. Atualizar vendas_itens
            $db->prepare("UPDATE vendas_itens SET produto_id = ?, quantidade = ?, preco_unitario = ? WHERE id = ?")
               ->execute([$newProdId, $newQty, $newPrice, $itemId]);

            // 6. Recalcular total da venda
            $stmtTotal = $db->prepare("SELECT SUM(quantidade * preco_unitario) as total FROM vendas_itens WHERE venda_id = ?");
            $stmtTotal->execute([$vendaId]);
            $newTotalItems = $stmtTotal->fetchColumn() ?: 0;
            
            $sale = $saleModel->findById($vendaId);
            $newTotalVenda = $newTotalItems - ($sale['desconto_total'] ?? 0);

            $db->prepare("UPDATE vendas SET valor_total = ? WHERE id = ?")->execute([$newTotalVenda, $vendaId]);

            // 7. Financeiro (Ajuste no caixa se necessário)
            $diff = $newTotalVenda - $sale['valor_total'];
            if ($diff != 0 && $sale['forma_pagamento'] !== 'fiado') {
                $cashierModel = new \App\Models\Cashier();
                $caixaAberto = $cashierModel->getOpenForFilial($sale['filial_id']);
                if ($caixaAberto) {
                    $movementModel = new \App\Models\CashierMovement();
                    $movementModel->create([
                        'caixa_id' => $caixaAberto['id'],
                        'tipo' => $diff > 0 ? 'entrada' : 'saida',
                        'valor' => abs($diff),
                        'motivo' => "Ajuste Troca Item Venda #{$vendaId}",
                        'operador_id' => $_SESSION['usuario_id']
                    ]);
                }
            }

            $audit = new \App\Services\AuditLogService();
            $audit->record('Troca de item em venda', 'vendas', $vendaId, null, "Item ID {$itemId} trocado por Prod ID {$newProdId}");

            $db->commit();
            echo json_encode(['success' => true]);

        } catch (\Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
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
