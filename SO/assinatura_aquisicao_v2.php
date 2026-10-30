<?php
declare(strict_types=1);

require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

// A rota antiga assinatura_aquisicao.php delega para este mesmo handler.
// Abrir por GET nunca grava a assinatura: apenas POST protegido por CSRF.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $getId = $_GET['id'] ?? '';
        $idConsulta = is_scalar($getId) && ctype_digit((string)$getId) ? (int)$getId : 0;
        header('Location: ' . ($idConsulta > 0 ? 'aquisicoes_visualizar.php?id=' . $idConsulta : 'aquisicoes_lista.php'), true, 303);
        exit;
    }
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

if (!usuario_pode_assinar_aquisicao($pdo)) {
    http_response_code(403);
    exit('Apenas os usuários suporte e amandha podem assinar aquisições.');
}

$idRaw = $_POST['id'] ?? '';
$id = is_scalar($idRaw) && ctype_digit((string)$idRaw) ? (int)$idRaw : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Aquisição inválida.');
}

// Token fornecido apenas pelo formulário na tela de visualização da aquisição.
$tokenSession = $_SESSION['csrf_assinatura_aquisicao'] ?? null;
$tokenPostado = $_POST['csrf_token'] ?? null;
if (!is_string($tokenSession)
    || !is_string($tokenPostado)
    || !hash_equals($tokenSession, $tokenPostado)
) {
    http_response_code(403);
    exit('Token de segurança inválido. Atualize a visualização da aquisição e tente novamente.');
}

function assinatura_aquisicao_voltar(int $id, string $status): void
{
    header('Location: aquisicoes_visualizar.php?id=' . $id . '&assinatura=' . urlencode($status), true, 303);
    exit;
}

try {
    // A transação registra autorização + log de auditoria de forma atômica.
    $pdo->beginTransaction();

    $stmtAq = $pdo->prepare('SELECT numero_aq FROM aquisicoes WHERE id = ? LIMIT 1');
    $stmtAq->execute([$id]);
    $numeroAquisicao = $stmtAq->fetchColumn();
    if ($numeroAquisicao === false) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Aquisição não encontrada.');
    }

    $stmtExistente = $pdo->prepare('SELECT id FROM assinaturas_aquisicoes WHERE aquisicao_id = ? LIMIT 1');
    $stmtExistente->execute([$id]);
    if ($stmtExistente->fetchColumn()) {
        $pdo->rollBack();
        assinatura_aquisicao_voltar($id, 'ja_assinada');
    }

    $stmtAssinatura = $pdo->prepare("
        SELECT id
        FROM assinaturas_sistema
        WHERE finalidade = 'AUTORIZACAO_FORNECEDOR' AND ativo = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtAssinatura->execute();
    $assinaturaId = $stmtAssinatura->fetchColumn();
    if (!$assinaturaId) {
        $pdo->rollBack();
        flash_message('danger', 'Não há assinatura de autorização ativa. Solicite ao suporte que configure a assinatura.');
        assinatura_aquisicao_voltar($id, 'indisponivel');
    }

    // Usa os dados atuais da conta autenticada, sem aceitar nome pelo formulário.
    $stmtUsuario = $pdo->prepare('SELECT nome FROM usuarios WHERE id = ? LIMIT 1');
    $stmtUsuario->execute([(int)$_SESSION['user_id']]);
    $nomeUsuario = trim((string)$stmtUsuario->fetchColumn());
    if ($nomeUsuario === '') {
        $pdo->rollBack();
        http_response_code(403);
        exit('Usuário não identificado.');
    }

    $stmtInsert = $pdo->prepare('
        INSERT INTO assinaturas_aquisicoes (aquisicao_id, assinatura_id, assinado_por)
        VALUES (?, ?, ?)
    ');
    $stmtInsert->execute([$id, (int)$assinaturaId, $nomeUsuario]);
    log_action($pdo, 'ASSINAR_AQUISICAO', 'Aquisição ' . (string)$numeroAquisicao . ' assinada por ' . $nomeUsuario);

    $pdo->commit();
    // Impede reenvio por botão Voltar. Outra aba precisará recarregar para usar token novo.
    unset($_SESSION['csrf_assinatura_aquisicao']);
    assinatura_aquisicao_voltar($id, 'ok');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // A chave única do banco impede assinatura duplicada mesmo com dois envios simultâneos.
    if ((string)$e->getCode() === '23000') {
        assinatura_aquisicao_voltar($id, 'ja_assinada');
    }
    error_log('[SO] Falha ao assinar aquisição ' . $id . ': ' . $e->getMessage());
    flash_message('danger', 'Não foi possível registrar a assinatura. Verifique o módulo e tente novamente.');
    assinatura_aquisicao_voltar($id, 'erro');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[SO] Falha ao assinar aquisição ' . $id . ': ' . $e->getMessage());
    flash_message('danger', 'Não foi possível registrar a assinatura. Tente novamente.');
    assinatura_aquisicao_voltar($id, 'erro');
}
