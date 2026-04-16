<?php
require_once __DIR__ . '/../src/App/Config/Database.php';
$db = \App\Config\Database::getInstance()->getConnection();
$st = $db->query("PRAGMA table_info(vendas)");
$cols = $st->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) {
    echo $c['name'] . "\n";
}
