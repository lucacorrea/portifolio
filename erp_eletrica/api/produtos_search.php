<?php
require_once '../config.php';
checkAuth();

header('Content-Type: application/json');

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    $q = $_GET['q'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);

    $sql = "SELECT id, nome, codigo FROM produtos";
    $params = [];

    if (!empty($q)) {
        $sql .= " WHERE nome LIKE ? OR codigo LIKE ?";
        $params = ["%$q%", "%$q%"];
    }

    $sql .= " ORDER BY nome ASC LIMIT $limit";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($produtos);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
