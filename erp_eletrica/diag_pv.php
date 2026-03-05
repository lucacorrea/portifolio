<?php
session_start();
// Mock session if needed for testing
if (!isset($_SESSION['filial_id'])) $_SESSION['filial_id'] = 1;

require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

$term = $_GET['term'] ?? '';
$term = trim($term);

echo "<h1>Diagnóstico de Busca de Pré-Venda</h1>";
echo "Busca por: <strong>$term</strong><br><hr>";

$model = new \App\Models\PreSale();
$avulsoCol = $model->columnExists('nome_cliente_avulso') ? 'pv.nome_cliente_avulso' : "''";

$sql = "
    SELECT pv.id, pv.codigo, pv.valor_total, pv.status, pv.created_at,
           COALESCE(c.nome, $avulsoCol, 'Consumidor') as cliente_nome, 
           u.nome as vendedor_nome 
    FROM pre_vendas pv 
    LEFT JOIN clientes c ON pv.cliente_id = c.id 
    LEFT JOIN usuarios u ON pv.usuario_id = u.id
    WHERE 1=1 ";

$params = [];

// Temporarily ignore filial for diagnostic or use session
$sql .= " AND pv.filial_id = ? ";
$params[] = $_SESSION['filial_id'];

if ($term) {
    $termLike = "%" . strtolower($term) . "%";
    $termInt = (int)$term;
    
    $sql .= " AND (
        LOWER(c.nome) LIKE ? 
        OR LOWER(c.cpf_cnpj) LIKE ? 
        OR LOWER($avulsoCol) LIKE ? 
        OR LOWER(u.nome) LIKE ?
        OR LOWER(pv.codigo) LIKE ? 
        OR pv.id = ? 
        OR EXISTS (
            SELECT 1 FROM pre_venda_itens pvi 
            INNER JOIN produtos p ON pvi.produto_id = p.id 
            WHERE pvi.pre_venda_id = pv.id 
            AND (LOWER(p.nome) LIKE ? OR LOWER(p.codigo) LIKE ? OR p.id = ? OR LOWER(p.codigo_barras) LIKE ?)
        )
    )";
    $params[] = $termLike;
    $params[] = $termLike;
    $params[] = $termLike;
    $params[] = $termLike;
    $params[] = $termLike;
    $params[] = $termInt;
    $params[] = $termLike;
    $params[] = $termLike;
    $params[] = $termInt;
    $params[] = $termLike;
}

echo "<strong>SQL:</strong><pre>$sql</pre>";
echo "<strong>Params:</strong><pre>" . print_r($params, true) . "</pre>";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<strong>Resultados encontrados: " . count($results) . "</strong><br>";
    echo "<pre>" . print_r($results, true) . "</pre>";
} catch (Exception $e) {
    echo "<b style='color:red'>ERRO: " . $e->getMessage() . "</b>";
}
