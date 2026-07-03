<?php
declare(strict_types=1);

/* ======================
   HEADER JSON
====================== */
header('Content-Type: application/json; charset=utf-8');

/* ======================
   CONEXÃO (usando __DIR__ como seus outros arquivos)
====================== */
require_once __DIR__ . '/assets/conexao.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode([
        'erro' => true,
        'mensagem' => 'Conexão com o banco não encontrada'
    ]);
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {

    /* ======================
       RECEBE JSON
    ====================== */
    $raw = file_get_contents('php://input');
    $dados = json_decode($raw, true);

    if (!is_array($dados)) {
        throw new Exception('JSON inválido');
    }

    $cpf = preg_replace('/\D/', '', $dados['cpf'] ?? '');
    $retorno = ['existe' => false];

    /* ======================
       VALIDA CPF
    ====================== */
    if (strlen($cpf) !== 11) {
        echo json_encode($retorno);
        exit;
    }

    /* ======================
       CONSULTA NO BANCO
    ====================== */
    $sql = "SELECT 1 FROM solicitantes WHERE cpf = :cpf LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':cpf', $cpf, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->fetch()) {
        $retorno['existe'] = true;
    }

    echo json_encode($retorno);

} catch (Throwable $e) {

    http_response_code(500);
    echo json_encode([
        'erro' => true,
        'mensagem' => $e->getMessage()
    ]);
}