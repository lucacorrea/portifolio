<?php
declare(strict_types=1);

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/vendas/_helpers.php';

$pdo = db();

function json_out(array $data, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

$action = $_GET['action'] ?? '';

try {
  if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') json_out(['ok' => true, 'items' => []]);

    $st = $pdo->prepare("SELECT id, nome, cpf, telefone, endereco FROM clientes WHERE nome LIKE ? OR cpf LIKE ? LIMIT 10");
    $st->execute(["%$q%", "%$q%"]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    json_out(['ok' => true, 'items' => $rows]);
  }

  if ($action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) json_out(['ok' => false, 'msg' => 'Dados inválidos.'], 400);

    $nome = trim($data['nome'] ?? '');
    $cpf = trim($data['cpf'] ?? '');
    $tel = trim($data['telefone'] ?? '');
    $end = trim($data['endereco'] ?? '');

    if ($nome === '') json_out(['ok' => false, 'msg' => 'Nome é obrigatório.'], 400);

    // Check if table exists, if not create it (auto-patch as requested)
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nome VARCHAR(255) NOT NULL,
      cpf VARCHAR(20),
      telefone VARCHAR(20),
      endereco TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $st = $pdo->prepare("INSERT INTO clientes (nome, cpf, telefone, endereco) VALUES (?, ?, ?, ?)");
    $st->execute([$nome, $cpf, $tel, $end]);
    $id = (int)$pdo->lastInsertId();

    json_out(['ok' => true, 'msg' => 'Cliente cadastrado com sucesso!', 'id' => $id]);
  }

  json_out(['ok' => false, 'msg' => 'Ação inválida.'], 400);
} catch (Throwable $e) {
  json_out(['ok' => false, 'msg' => $e->getMessage()], 500);
}
