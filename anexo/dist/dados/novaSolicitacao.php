<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/authGuard.php';
auth_guard();

require_once __DIR__ . '/../assets/conexao.php'; // $pdo

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro de conexão.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método inválido.']);
    exit;
}

$solicitante_id = (int)($_POST['solicitante_id'] ?? 0);
$ajuda_tipo_id  = (int)($_POST['ajuda_tipo_id'] ?? 0);
$resumo_caso    = trim((string)($_POST['resumo_caso'] ?? ''));
$dataSolic      = trim((string)($_POST['data_solicitacao'] ?? ''));

// Nome do usuário logado
$nomeLogado =
    ((string)($_SESSION['usuario_nome'] ?? '')) ?:
    ((string)($_SESSION['nome'] ?? '')) ?:
    ((string)($_SESSION['user_nome'] ?? '')) ?:
    ((string)($_SESSION['usuario'] ?? '')) ?:
    ((string)($_SESSION['username'] ?? '')) ?:
    'Sistema';

// Validações
if ($solicitante_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID do solicitante inválido.']);
    exit;
}

if ($ajuda_tipo_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Selecione o tipo de ajuda.']);
    exit;
}

if ($resumo_caso === '') {
    echo json_encode(['ok' => false, 'msg' => 'O resumo do caso é obrigatório.']);
    exit;
}

if ($dataSolic === '') {
    echo json_encode(['ok' => false, 'msg' => 'Data da solicitação inválida.']);
    exit;
}

try {
    // Confere solicitante
    $stm = $pdo->prepare("SELECT id FROM solicitantes WHERE id = ?");
    $stm->execute([$solicitante_id]);
    if (!$stm->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'Solicitante não encontrado.']);
        exit;
    }

    // INSERT com data/hora REAL do dispositivo
    $sql = "
        INSERT INTO solicitacoes
          (solicitante_id, ajuda_tipo_id, resumo_caso, data_solicitacao, created_by, status)
        VALUES
          (:sid, :aid, :res, :data, :usr, 'Aberto')
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':sid'  => $solicitante_id,
        ':aid'  => $ajuda_tipo_id,
        ':res'  => $resumo_caso,
        ':data' => $dataSolic,
        ':usr'  => $nomeLogado
    ]);

    echo json_encode(['ok' => true, 'msg' => 'Solicitação criada com sucesso!']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Erro ao salvar: ' . $e->getMessage()
    ]);
}
?>