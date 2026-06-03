<?php
namespace App\Controllers;

use App\Services\InventoryService;

class InventoryController extends BaseController {
    // Ajustar `InventoryController.php` para processar parâmetro `ordem`
    private $service;

    public function __construct() {
        $this->service = new InventoryService();
    }

    private function productUploadDir(): string {
        $appRoot = dirname(__DIR__, 3);
        $dir = getenv('ERP_PRODUCT_UPLOAD_DIR');
        if (!$dir) {
            $dir = dirname(dirname($appRoot)) . DIRECTORY_SEPARATOR . 'erp_eletrica_uploads' . DIRECTORY_SEPARATOR . 'produtos';
        }
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function index() {
        $productModel = new \App\Models\Product();
        $movementModel = new \App\Models\StockMovement();

        $filialId = $_SESSION['filial_id'] ?? null;
        $isMatriz = $_SESSION['is_matriz'] ?? false;

        $stats = [
            'total_itens' => $this->sum('produtos', 'quantidade'),
            'valor_custo' => $this->sum('produtos', 'preco_custo * quantidade'),
            'itens_criticos' => count($productModel->getCriticalStock(!$isMatriz ? $filialId : null)),
            'mov_mes' => $this->count('movimentacao_estoque', "MONTH(data_movimento) = MONTH(CURRENT_DATE)", 'deposito_id')
        ];

        $page = (int)($_GET['page'] ?? 1);
        $filters = [
            'q' => $_GET['q'] ?? '',
            'categoria' => $_GET['categoria'] ?? '',
            'ordem' => $_GET['ordem'] ?? 'codigo_desc'
        ];
        
        // Mapeamento de Ordenação
        $orderSql = "categoria ASC, nome ASC"; // Default
        if ($filters['ordem'] === 'codigo_desc') $orderSql = "CAST(codigo AS UNSIGNED) DESC";
        if ($filters['ordem'] === 'codigo_asc') $orderSql = "CAST(codigo AS UNSIGNED) ASC";
        if ($filters['ordem'] === 'nome_asc') $orderSql = "nome ASC";
        
        $pagination = $productModel->paginate(15, $page, $orderSql, $filters);
        $products = $pagination['data'];
        $allProducts = $productModel->all("nome ASC");
        $movements = $movementModel->getHistory(null, 20);
        $categories = $productModel->getCategories();
        
        $supplierModel = new \App\Models\Supplier();
        $suppliers = $supplierModel->all("nome_fantasia ASC");

        $nextCode = $productModel->getNextCode();

        // Buscar filiais ativas para replicação do catálogo
        $db = \App\Config\Database::getInstance()->getConnection();
        $branches = $db->query("SELECT id, nome FROM filiais WHERE principal = 0 ORDER BY nome ASC")->fetchAll();

        $this->render('inventory', [
            'stats' => $stats,
            'products' => $products,
            'allProducts' => $allProducts,
            'pagination' => $pagination,
            'movements' => $movements,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'filters' => $filters,
            'nextCode' => $nextCode,
            'branches' => $branches
        ]);
    }

    public function save() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            validateCsrf($_POST['csrf_token'] ?? '');
            $model = new \App\Models\Product();
            $data = $_POST;
            $data['ncm'] = preg_replace('/\D+/', '', (string)($data['ncm'] ?? ''));
            if (strlen($data['ncm']) !== 8) {
                $this->redirect('estoque.php?error=' . urlencode('Informe um NCM valido com 8 digitos para cadastrar o produto.'));
            }
            
            // Fix: ensure product is linked to the correct filial
            if (empty($data['filial_id'])) {
                $data['filial_id'] = $_SESSION['filial_id'] ?? 1;
            }

            // LEITURA INTELIGENTE DE IMAGEM (Qualquer formato válido)
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $dir = $this->productUploadDir();
                if (!is_dir($dir)) mkdir($dir, 0777, true);

                $tmpName = $_FILES['foto']['tmp_name'];

                // Instancia o detector inteligente de tipo de arquivo
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmpName);
                finfo_close($finfo);

                // Mapeamento dos tipos de imagens mais comuns do mercado (incluindo modernos como WebP e AVIF)
                $allowedMimeTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/gif'  => 'gif',
                    'image/webp' => 'webp',
                    'image/avif' => 'avif',
                    'image/bmp'  => 'bmp',
                    'image/x-icon' => 'ico',
                    'image/svg+xml' => 'svg'
                ];

                // Verifica se o arquivo é genuinamente uma imagem
                if (array_key_exists($mimeType, $allowedMimeTypes)) {
                    // Define a extensão com base no que a imagem REALMENTE é
                    $extension = $allowedMimeTypes[$mimeType];
                    
                    // Gera um nome com entropia alta para evitar colisao entre uploads rapidos.
                    try {
                        $filename = bin2hex(random_bytes(16)) . "." . $extension;
                    } catch (\Exception $e) {
                        $filename = str_replace('.', '', uniqid('', true)) . "." . $extension;
                    }
                    
                    if (move_uploaded_file($tmpName, $dir . $filename)) {
                        $data['imagens'] = $filename;
                    }
                } else {
                    // Caso queira abortar se não for imagem, descomente a linha abaixo:
                    // $this->redirect('estoque.php?error=' . urlencode('O arquivo enviado não é uma imagem válida.'));
                }
            }

            $isEdit = isset($data['id']) && !empty($data['id']);
            try {
                $savedId = $model->save($data);
                if (!$isEdit && $savedId) {
                    $savedProduct = $model->find($savedId);
                    if ($savedProduct) {
                        $data['codigo'] = $savedProduct['codigo'] ?? ($data['codigo'] ?? '');
                    }
                }
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000' && stripos($e->getMessage(), 'codigo') !== false) {
                    $this->redirect('estoque.php?error=' . urlencode('Este codigo acabou de ser usado por outro usuario. Atualize a pagina e tente salvar novamente.'));
                }
                throw $e;
            } catch (\Exception $e) {
                $this->redirect('estoque.php?error=' . urlencode('Nao foi possivel salvar o produto: ' . $e->getMessage()));
            }
            
            $codigo = $data['codigo'] ?? '';
            $nome = $data['nome'] ?? '';
            
            if ($isEdit) {
                $msg = "O produto \"$codigo - $nome\" foi alterado com sucesso!";
            } else {
                $msg = "O produto \"$codigo - $nome\" foi cadastrado com sucesso!";
            }
            $this->redirect('estoque.php?msg=' . urlencode($msg));
        }
    }

    public function move() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            validateCsrf($_POST['csrf_token'] ?? '');
            $this->service->recordMovement($_POST);
            $this->redirect('estoque.php?msg=Movimentação realizada com sucesso');
        }
    }

    public function delete() {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $model = new \App\Models\Product();
            $model->delete($id);
            $this->redirect('estoque.php?msg=Material excluído com sucesso');
        }
    }

    public function replicateCatalog() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            validateCsrf($_POST['csrf_token'] ?? '');
            
            // Apenas a Matriz pode replicar catálogo para as filiais
            if (!($_SESSION['is_matriz'] ?? false)) {
                $this->redirect('estoque.php?error=' . urlencode('Acesso restrito à unidade Matriz.'));
            }

            $destinoFilialId = (int)($_POST['destino_filial_id'] ?? 0);
            if ($destinoFilialId <= 0) {
                $this->redirect('estoque.php?error=' . urlencode('Selecione uma filial de destino válida.'));
            }

            try {
                $db = \App\Config\Database::getInstance()->getConnection();
                
                // Query elegante de alta performance para replicar todos os produtos para a filial alvo
                // Quantidade padrão = 1, Estoque mínimo = 1
                $sql = "INSERT INTO estoque_filiais (produto_id, filial_id, quantidade, estoque_minimo)
                        SELECT id, ?, 1, 1 FROM produtos
                        ON DUPLICATE KEY UPDATE quantidade = 1, estoque_minimo = 1";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([$destinoFilialId]);
                
                // Buscar nome da filial destino para a mensagem de sucesso
                $stmtBranch = $db->prepare("SELECT nome FROM filiais WHERE id = ?");
                $stmtBranch->execute([$destinoFilialId]);
                $branchName = $stmtBranch->fetchColumn() ?: "Filial";

                // Registrar ação na auditoria
                $auditLog = new \App\Services\AuditLogService();
                $auditLog->record('replicate_catalog', 'estoque_filiais', $destinoFilialId, null, ['quantidade' => 1, 'estoque_minimo' => 1]);

                $msg = "Catálogo de produtos copiado com sucesso para a filial \"$branchName\"! Quantidades ajustadas para 1 e estoques mínimos para 1.";
                $this->redirect('estoque.php?msg=' . urlencode($msg));
                
            } catch (\Exception $e) {
                $this->redirect('estoque.php?error=' . urlencode('Erro ao replicar catálogo: ' . $e->getMessage()));
            }
        }
    }

    public function movimentacoes() {
        $movementModel = new \App\Models\StockMovement();
        $productModel = new \App\Models\Product();

        $filters = [
            'desde' => $_GET['desde'] ?? date('Y-m-01'),
            'ate' => $_GET['ate'] ?? date('Y-m-d'),
            'produto_id' => $_GET['produto_id'] ?? ''
        ];

        $movements = $movementModel->getHistory($filters, 100);
        $products = $productModel->all("nome ASC");

        $this->render('inventory_movements', [
            'movements' => $movements,
            'products' => $products,
            'filters' => $filters,
            'title' => 'Histórico de Movimentações',
            'pageTitle' => 'Movimentações de Estoque'
        ]);
    }

    private function sum($table, $expression, $filialCol = 'filial_id') {
        $db = \App\Config\Database::getInstance()->getConnection();
        $filialId = $_SESSION['filial_id'] ?? 1;

        if ($table === 'produtos') {
            $expr = str_replace('quantidade', 'COALESCE(ef.quantidade, 0)', $expression);
            $sql = "SELECT SUM($expr) FROM produtos p LEFT JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$filialId]);
            return $stmt->fetchColumn() ?: 0;
        }

        $isMatriz = $_SESSION['is_matriz'] ?? false;
        $where = (!$isMatriz && $filialId) ? " WHERE $filialCol = $filialId" : "";
        return $db->query("SELECT SUM($expression) FROM $table $where")->fetchColumn() ?: 0;
    }

    private function count($table, $condition = "1=1", $filialCol = 'filial_id') {
        $db = \App\Config\Database::getInstance()->getConnection();
        $filialId = $_SESSION['filial_id'] ?? 1;

        $isMatriz = $_SESSION['is_matriz'] ?? false;
        $whereFilial = (!$isMatriz && $filialId) ? " AND $filialCol = $filialId" : "";
        return $db->query("SELECT COUNT(*) FROM $table WHERE ($condition) $whereFilial")->fetchColumn() ?: 0;
    }

    public function problems() {
        $problemModel = new \App\Models\ProductProblem();
        
        $filters = [
            'q' => $_GET['q'] ?? '',
            'status' => $_GET['status'] ?? ''
        ];

        $sql = "SELECT pp.*, p.nome as produto_nome, p.codigo as produto_codigo, u.nome as usuario_nome 
                FROM produtos_problema pp 
                JOIN produtos p ON pp.produto_id = p.id 
                LEFT JOIN usuarios u ON pp.usuario_id = u.id 
                WHERE 1=1";
        
        $params = [];
        $filialId = $_SESSION['filial_id'] ?? null;
        if ($filialId && !($_SESSION['is_matriz'] ?? false)) {
            $sql .= " AND pp.filial_id = ?";
            $params[] = $filialId;
        }

        if ($filters['q']) {
            $sql .= " AND (p.nome LIKE ? OR p.codigo LIKE ? OR pp.motivo LIKE ?)";
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
        }

        if ($filters['status']) {
            $sql .= " AND pp.status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY pp.data_registro DESC";
        $problems = $problemModel->query($sql, $params)->fetchAll();
        
        $statusLabels = $problemModel->getStatusLabels();
        
        $stats = [
            'total' => count($problems),
            'pendente' => 0,
            'devolvido' => 0,
            'descartado' => 0,
            'consertado' => 0
        ];
        foreach ($problems as $p) {
            $stats[$p['status']]++;
        }

        $this->render('inventory_problems', [
            'problems' => $problems,
            'statusLabels' => $statusLabels,
            'filters' => $filters,
            'stats' => $stats,
            'title' => 'Controle de Avarias',
            'pageTitle' => 'Produtos com Problemas / Defeitos'
        ]);
    }

    public function saveProblem() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            validateCsrf($_POST['csrf_token'] ?? '');
            try {
                $this->service->registerProblem($_POST);
                $this->redirect('estoque.php?action=problems&msg=Avaria registrada com sucesso');
            } catch (\Exception $e) {
                $this->redirect('estoque.php?error=' . urlencode($e->getMessage()));
            }
        }
    }

    public function updateProblemStatus() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            validateCsrf($_POST['csrf_token'] ?? '');
            $id = (int)$_POST['id'];
            $status = $_POST['status'];
            
            $problemModel = new \App\Models\ProductProblem();
            $problemModel->update($id, ['status' => $status]);
            
            $this->redirect('estoque.php?action=problems&msg=Status atualizado com sucesso');
        }
    }

    public function deleteProblem() {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $problemModel = new \App\Models\ProductProblem();
            $problemModel->delete($id);
            $this->redirect('estoque.php?action=problems&msg=Registro de avaria removido');
        }
    }
}
