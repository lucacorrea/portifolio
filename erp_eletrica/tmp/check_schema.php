<?php
require_once 'config.php';
$stmt = $pdo->query("DESCRIBE notas_fiscais");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
