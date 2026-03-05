<?php
declare(strict_types=1);

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/vendas/_helpers.php';

$pdo = db();

// Auto-patch: Create fiados_pagamentos table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fiados_pagamentos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      fiado_id INT NOT NULL,
      valor DECIMAL(10,2) NOT NULL,
      metodo VARCHAR(50) NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Throwable $e) {}

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'fetch') {
    $di = $_GET['di'] ?? '';
    $df = $_GET['df'] ?? '';
    $canal = $_GET['canal'] ?? 'TODOS';
    $q = $_GET['q'] ?? '';
    
    $where = " WHERE 1=1 ";
    $params = [];
    
    if ($di) { $where .= " AND f.created_at >= :di "; $params['di'] = $di . ' 00:00:00'; }
    if ($df) { $where .= " AND f.created_at <= :df "; $params['df'] = $df . ' 23:59:59'; }
    if ($canal !== 'TODOS') { $where .= " AND v.canal = :canal "; $params['canal'] = $canal; }
    if ($q) {
        $where .= " AND (c.nome LIKE :q OR v.id = :vid) ";
        $params['q'] = '%' . $q . '%';
        $params['vid'] = is_numeric($q) ? (int)$q : -1;
    }
    
    $sql = "SELECT f.*, c.nome as cliente_nome, v.canal, v.created_at as venda_data 
            FROM fiados f
            JOIN clientes c ON f.cliente_id = c.id
            JOIN vendas v ON f.venda_id = v.id
            $where
            ORDER BY f.id DESC";
            
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    
    json_out(['ok' => true, 'data' => $rows]);
}

if ($action === 'get_details') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_out(['ok' => false, 'msg' => 'ID inválido'], 400);
    
    // Get Fiado info
    $stFiado = $pdo->prepare("SELECT f.*, c.nome as cliente_nome FROM fiados f JOIN clientes c ON f.cliente_id = c.id WHERE f.id = ?");
    $stFiado->execute([$id]);
    $fiado = $stFiado->fetch(PDO::FETCH_ASSOC);
    if (!$fiado) json_out(['ok' => false, 'msg' => 'Registro não encontrado'], 404);
    
    // Get Items
    $stItems = $pdo->prepare("SELECT * FROM venda_itens WHERE venda_id = ?");
    $stItems->execute([$fiado['venda_id']]);
    $items = $stItems->fetchAll(PDO::FETCH_ASSOC);
    
    // Get Payments history
    $stPays = $pdo->prepare("SELECT * FROM fiados_pagamentos WHERE fiado_id = ? ORDER BY created_at DESC");
    $stPays->execute([$id]);
    $pays = $stPays->fetchAll(PDO::FETCH_ASSOC);
    
    json_out([
        'ok' => true, 
        'fiado' => $fiado,
        'items' => $items,
        'payments' => $pays
    ]);
}

if ($action === 'pay') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    $valor = (float)($data['valor'] ?? 0);
    $metodo = $data['metodo'] ?? 'DINHEIRO';
    
    if ($id <= 0 || $valor <= 0) json_out(['ok' => false, 'msg' => 'Dados inválidos'], 400);
    
    try {
        $pdo->beginTransaction();
        
        // Check balance
        $stCheck = $pdo->prepare("SELECT valor_restante FROM fiados WHERE id = ? FOR UPDATE");
        $stCheck->execute([$id]);
        $restante = (float)$stCheck->fetchColumn();
        
        if ($valor > ($restante + 0.01)) {
            throw new Exception("Valor do pagamento (R$ " . number_format($valor, 2, ',', '.') . ") é superior ao saldo devedor (R$ " . number_format($restante, 2, ',', '.') . ").");
        }
        
        // Insert payment
        $stIns = $pdo->prepare("INSERT INTO fiados_pagamentos (fiado_id, valor, metodo) VALUES (?, ?, ?)");
        $stIns->execute([$id, $valor, $metodo]);
        
        // Update fiado record
        $novoRestante = max(0, $restante - $valor);
        $status = ($novoRestante < 0.01) ? 'PAGO' : 'ABERTO';
        
        $stUpd = $pdo->prepare("UPDATE fiados SET valor_pago = valor_pago + ?, valor_restante = ?, status = ? WHERE id = ?");
        $stUpd->execute([$valor, $novoRestante, $status, $id]);
        
        $pdo->commit();
        json_out(['ok' => true, 'msg' => 'Pagamento registrado com sucesso!']);
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_out(['ok' => false, 'msg' => $e->getMessage()], 500);
    }
}
