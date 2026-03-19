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
        $caixaAberto = $cashierModel->getOpenForOperador($_SESSION['usuario_id'], $_SESSION['filial_id'] ?? 1);

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
            $caixaAberto = $cashierModel->getOpenForOperador($_SESSION['usuario_id'], $_SESSION['filial_id'] ?? 1);
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

            // Automated Client Registration/Selection
            if (empty($data['cliente_id']) && !empty($data['nome_cliente_avulso'])) {
                $nome = trim($data['nome_cliente_avulso']);
                // Check if client with this EXACT name exists to avoid duplicates
                $stmt = \App\Config\Database::getInstance()->getConnection()->prepare("SELECT id FROM clientes WHERE nome = ? AND filial_id = ? LIMIT 1");
                $stmt->execute([$nome, $data['filial_id']]);
                $existingId = $stmt->fetchColumn();

                if ($existingId) {
                    $data['cliente_id'] = $existingId;
                } else {
                    // Create new quick client
                    $newClientId = $clientModel->create([
                        'nome' => $nome,
                        'filial_id' => $data['filial_id']
                    ]);
                    $data['cliente_id'] = $newClientId;
                }
            }

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
        $term = trim($_GET['term'] ?? '');
        $model = new PreSale();
        
        $filialId = $_SESSION['filial_id'] ?? 1;
        $isMatriz = $_SESSION['is_matriz'] ?? false;
        
        $avulsoCol = $model->columnExists('nome_cliente_avulso') ? 'pv.nome_cliente_avulso' : "''";

        $sql = "
            SELECT pv.id, pv.codigo, pv.valor_total, pv.status, pv.created_at, pv.cpf_cliente,
                   COALESCE(c.nome, $avulsoCol, 'Consumidor') as cliente_nome, 
                   u.nome as vendedor_nome 
            FROM pre_vendas pv 
            LEFT JOIN clientes c ON pv.cliente_id = c.id 
            LEFT JOIN usuarios u ON pv.usuario_id = u.id
            WHERE 1=1 ";
        
        $params = [];
        
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
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage(), 'sql' => $sql]);
        }
        exit;
    }
}
