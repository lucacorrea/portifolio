<?php
namespace App\Controllers;

use App\Models\PreSale;
use App\Models\Product;
use App\Models\Client;

class PreSaleController extends BaseController {
    public function index() {
        $model = new PreSale();
        $recent = $model->getRecent();

        $cashierModel = new \App\Models\Cashier();
        $caixaAberto = $cashierModel->getOpenForFilial($_SESSION['filial_id'] ?? 1);

        $this->render('pre_sales', [
            'recent' => $recent,
            'caixaAberto' => $caixaAberto,
            'title' => 'Terminal de Pré-Venda',
            'pageTitle' => 'Geração de Orçamentos e Fichas'
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validation: Cashier Open Check
            $cashierModel = new \App\Models\Cashier();
            $caixaAberto = $cashierModel->getOpenForFilial($_SESSION['filial_id'] ?? 1);
            if (!$caixaAberto) {
                echo json_encode(['success' => false, 'error' => "É necessário abrir o caixa antes de gerar pré-vendas."]);
                exit;
            }

            $model = new PreSale();
            $productModel = new Product();
            $clientModel = new Client();
            
            // Validation: Stock Check
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    if (!$productModel->hasEnoughStock($item['id'], $item['qty'])) {
                        $stmtProd = \App\Config\Database::getInstance()->getConnection()->prepare("SELECT nome FROM produtos WHERE id = ?");
                        $stmtProd->execute([$item['id']]);
                        $productName = $stmtProd->fetchColumn();
                        echo json_encode(['success' => false, 'error' => "Estoque insuficiente para a pré-venda: $productName."]);
                        exit;
                    }
                }
            }

            $data['usuario_id'] = $_SESSION['usuario_id'];
            $data['filial_id'] = $_SESSION['filial_id'] ?? 1;

            // Automated Client Registration/Selection / Intelligent Cross-Referencing
            if (empty($data['cliente_id'])) {
                $db = \App\Config\Database::getInstance()->getConnection();
                
                $searchTerm = !empty($data['nome_cliente_avulso']) ? trim($data['nome_cliente_avulso']) : '';
                $cpfTerm = !empty($data['cpf_cliente']) ? trim($data['cpf_cliente']) : '';
                
                // Extrai apenas dígitos para cruzamento por telefone ou documento
                $cleanDigits = preg_replace('/\D/', '', $cpfTerm ?: $searchTerm);
                
                $foundClient = null;
                
                // 1. Busca por CPF/CNPJ (exato, ignorando pontuação do banco)
                if (strlen($cleanDigits) === 11 || strlen($cleanDigits) === 14) {
                    $stmt = $db->prepare("SELECT id, nome, cpf_cnpj FROM clientes WHERE REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') = ? AND filial_id = ? LIMIT 1");
                    $stmt->execute([$cleanDigits, $data['filial_id']]);
                    $foundClient = $stmt->fetch(\PDO::FETCH_ASSOC);
                }
                
                // 2. Busca por Telefone (exato, ignorando pontuação do banco)
                if (!$foundClient && (strlen($cleanDigits) === 10 || strlen($cleanDigits) === 11)) {
                    $stmt = $db->prepare("SELECT id, nome, cpf_cnpj FROM clientes WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') = ? AND filial_id = ? LIMIT 1");
                    $stmt->execute([$cleanDigits, $data['filial_id']]);
                    $foundClient = $stmt->fetch(\PDO::FETCH_ASSOC);
                }
                
                // 3. Busca por Nome Exato ou Similar (caso não seja "Consumidor Final")
                if (!$foundClient && !empty($searchTerm) && strtolower($searchTerm) !== 'consumidor final') {
                    $stmt = $db->prepare("SELECT id, nome, cpf_cnpj FROM clientes WHERE (nome = ? OR nome LIKE ?) AND filial_id = ? LIMIT 1");
                    $stmt->execute([$searchTerm, $searchTerm, $data['filial_id']]);
                    $foundClient = $stmt->fetch(\PDO::FETCH_ASSOC);
                }
                
                // 4. Busca por CPF/CNPJ original com formatação
                if (!$foundClient && !empty($cpfTerm)) {
                    $stmt = $db->prepare("SELECT id, nome, cpf_cnpj FROM clientes WHERE cpf_cnpj = ? AND filial_id = ? LIMIT 1");
                    $stmt->execute([$cpfTerm, $data['filial_id']]);
                    $foundClient = $stmt->fetch(\PDO::FETCH_ASSOC);
                }
                
                if ($foundClient) {
                    // Encontrou! Associa ao cliente do banco de dados automaticamente
                    $data['cliente_id'] = $foundClient['id'];
                    $data['nome_cliente_avulso'] = null;
                    $data['cpf_cliente'] = $foundClient['cpf_cnpj'];
                } else {
                    // Não encontrou cadastro correspondente
                    // Se o termo digitado parece ser um CPF/CNPJ, define-o como cpf_cliente
                    if (strlen($cleanDigits) === 11 || strlen($cleanDigits) === 14) {
                        $data['cpf_cliente'] = $cpfTerm ?: $searchTerm;
                        if (empty($data['nome_cliente_avulso']) || strtolower($data['nome_cliente_avulso']) === 'consumidor final') {
                            $data['nome_cliente_avulso'] = 'Consumidor Final';
                        }
                    }
                    
                    // Se for um Nome novo (não "Consumidor Final"), cria um cadastro rápido para evitar perda de dados
                    if (!empty($data['nome_cliente_avulso']) && strtolower($data['nome_cliente_avulso']) !== 'consumidor final') {
                        $nome = trim($data['nome_cliente_avulso']);
                        $newClientId = $clientModel->create([
                            'nome' => $nome,
                            'cpf_cnpj' => $data['cpf_cliente'] ?? null,
                            'filial_id' => $data['filial_id']
                        ]);
                        $data['cliente_id'] = $newClientId;
                        $data['nome_cliente_avulso'] = null;
                    }
                }
            }

            try {
                if (!empty($data['id'])) {
                    $model->update($data['id'], $data);
                    echo json_encode(['success' => true, 'id' => $data['id'], 'codigo' => $data['codigo'] ?? 'PV-EDITED']);
                } else {
                    $result = $model->create($data);
                    echo json_encode(['success' => true, 'id' => $result['id'], 'codigo' => $result['codigo']]);
                }
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

    public function delete() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID não informado']);
            exit;
        }
        
        $model = new PreSale();
        try {
            $success = $model->delete($id);
            echo json_encode(['success' => $success]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function list_pending() {
        $db = \App\Config\Database::getInstance()->getConnection();
        $term = trim($_GET['term'] ?? '');
        $tipo = trim($_GET['tipo'] ?? '');
        $model = new PreSale();
        
        $filialId = $_SESSION['filial_id'] ?? 1;
        $isMatriz = $_SESSION['is_matriz'] ?? false;
        
        $avulsoCol = $model->columnExists('nome_cliente_avulso') ? 'pv.nome_cliente_avulso' : "''";
        $cpfCol = $model->columnExists('cpf_cliente') ? 'pv.cpf_cliente' : "''";

        $sql = "
            SELECT pv.id, pv.codigo, pv.valor_total, pv.status, pv.created_at, 
                   $cpfCol as cpf_cliente,
                   COALESCE(c.nome, $avulsoCol, 'Consumidor') as cliente_nome, 
                   u.nome as vendedor_nome 
            FROM pre_vendas pv 
            LEFT JOIN clientes c ON pv.cliente_id = c.id 
            LEFT JOIN usuarios u ON pv.usuario_id = u.id
            WHERE 1=1 ";
        
        $params = [];

        if ($tipo === 'orcamento') {
            $sql .= " AND pv.codigo LIKE 'ORC-%' ";
        } else if ($tipo === 'pre_venda') {
            $sql .= " AND pv.codigo LIKE 'PV-%' ";
        }
        
        if (!$isMatriz) {
            $sql .= " AND pv.filial_id = ? ";
            $params[] = $filialId;
        }

        if ($term) {
            $termLike = "%" . strtolower($term) . "%";
            $termInt = (int)$term;
            
            $sql .= " AND (
                LOWER(TRIM(COALESCE(c.nome, ''))) LIKE ? 
                OR LOWER(TRIM(COALESCE(c.cpf_cnpj, ''))) LIKE ? 
                OR LOWER(TRIM(COALESCE($avulsoCol, ''))) LIKE ? 
                OR LOWER(TRIM(COALESCE(u.nome, ''))) LIKE ?
                OR LOWER(TRIM(pv.codigo)) LIKE ? 
                OR pv.id = ? 
                OR EXISTS (
                    SELECT 1 FROM pre_venda_itens pvi 
                    INNER JOIN produtos p ON pvi.produto_id = p.id 
                    WHERE pvi.pre_venda_id = pv.id 
                    AND (LOWER(TRIM(p.nome)) LIKE ? OR LOWER(TRIM(p.codigo)) LIKE ? OR p.id = ? OR LOWER(TRIM(p.codigo_barras)) LIKE ?)
                )
            )";
            $params[] = $termLike; // c.nome
            $params[] = $termLike; // c.cpf_cnpj
            $params[] = $termLike; // avulso
            $params[] = $termLike; // u.nome
            $params[] = $termLike; // pv.codigo
            $params[] = $termInt;  // pv.id
            $params[] = $termLike; // p.nome
            $params[] = $termLike; // p.codigo
            $params[] = $termInt;  // p.id
            $params[] = $termLike; // p.codigo_barras
        } else {
            $sql .= " AND pv.status = 'pendente' ";
        }

        $sql .= " ORDER BY pv.created_at DESC LIMIT 30";
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine()]);
        }
        exit;
    }
}
