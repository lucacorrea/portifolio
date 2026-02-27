<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';

try {
  $pdo = pdo();
  $pdo->query("SELECT 1")->fetch();
  json_out(['ok'=>true,'msg'=>'Conexão OK e PDO funcionando.']);
} catch (Throwable $e) {
  json_out(['ok'=>false,'msg'=>'Falha: '.$e->getMessage()], 500);
}

?>