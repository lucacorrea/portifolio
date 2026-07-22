<?php

require_once 'config/database.php';
require_once 'config/functions.php';

login_check();

function approval_return_url(array $source): string
{
    $safe = [];
    $allowed_statuses = ['PENDENTE_ITENS', 'ENVIADO', 'EM_ANALISE', 'APROVADO', 'REPROVADO', 'ARQUIVADO'];

    if (isset($source['busca']) && is_scalar($source['busca'])) {
        $safe['busca'] = substr(trim((string)$source['busca']), 0, 120);
    }

    if (isset($source['status']) && is_scalar($source['status']) && in_array((string)$source['status'], $allowed_statuses, true)) {
        $safe['status'] = (string)$source['status'];
    }

    foreach (['secretaria_id', 'fornecedor_id', 'page'] as $integer_key) {
        if (isset($source[$integer_key]) && is_numeric($source[$integer_key]) && (int)$source[$integer_key] > 0) {
            $safe[$integer_key] = (int)$source[$integer_key];
        }
    }

    foreach (['data_inicio', 'data_fim'] as $date_key) {
        if (isset($source[$date_key]) && is_scalar($source[$date_key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$source[$date_key])) {
            $safe[$date_key] = (string)$source[$date_key];
        }
    }

    $query = http_build_query($safe);
    return 'oficios_lista.php' . ($query !== '' ? '?' . $query : '');
}

$return_source = [];
parse_str((string)($_POST['return_query'] ?? ''), $return_source);
$return_url = approval_return_url($return_source);
$nivel = strtoupper($_SESSION['nivel'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$return_url}");
    exit;
}

if (!in_array($nivel, ['ADMIN', 'SUPORTE'], true)) {
    flash_message('danger', 'Você não possui permissão para aprovar solicitações.');
    header("Location: {$return_url}");
    exit;
}

$csrf_token = (string)($_POST['csrf_token'] ?? '');
$session_token = (string)($_SESSION['csrf_aprovacao_multipla'] ?? '');
if ($session_token === '' || $csrf_token === '' || !hash_equals($session_token, $csrf_token)) {
    flash_message('danger', 'A sessão de aprovação expirou. Atualize a página e tente novamente.');
    header("Location: {$return_url}");
    exit;
}

$posted_ids = $_POST['oficios'] ?? [];
if (!is_array($posted_ids)) {
    $posted_ids = [];
}

$ids = [];
foreach ($posted_ids as $posted_id) {
    if (is_scalar($posted_id) && ctype_digit((string)$posted_id) && (int)$posted_id > 0) {
        $ids[(int)$posted_id] = (int)$posted_id;
    }
}
$ids = array_values($ids);

if (empty($ids) || count($ids) > 1000) {
    flash_message('warning', 'Selecione entre 1 e 1000 solicitações válidas para aprovar.');
    header("Location: {$return_url}");
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, numero
        FROM oficios
        WHERE status = 'ENVIADO'
          AND id IN ($placeholders)
        ORDER BY id
        FOR UPDATE
    ");
    $stmt->execute($ids);
    $oficios_aprovaveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($oficios_aprovaveis)) {
        $pdo->rollBack();
        flash_message('warning', 'Nenhuma das solicitações selecionadas está com status ENVIADO.');
        header("Location: {$return_url}");
        exit;
    }

    $ids_aprovaveis = array_map('intval', array_column($oficios_aprovaveis, 'id'));
    $update_placeholders = implode(',', array_fill(0, count($ids_aprovaveis), '?'));
    $stmt_update = $pdo->prepare("UPDATE oficios SET status = 'APROVADO' WHERE status = 'ENVIADO' AND id IN ($update_placeholders)");
    $stmt_update->execute($ids_aprovaveis);

    $numeros = array_map(static function (array $oficio): string {
        return (string)$oficio['numero'];
    }, $oficios_aprovaveis);
    log_action(
        $pdo,
        'APROVACAO_MULTIPLA_OFICIOS',
        count($ids_aprovaveis) . ' solicitação(ões) aprovada(s): ' . implode(', ', $numeros)
    );

    $pdo->commit();

    $ignorados = count($ids) - count($ids_aprovaveis);
    $mensagem = count($ids_aprovaveis) . ' solicitação(ões) aprovada(s) com sucesso.';
    if ($ignorados > 0) {
        $mensagem .= ' ' . $ignorados . ' item(ns) não foi(ram) alterado(s) porque não estava(m) com status ENVIADO.';
    }
    flash_message('success', $mensagem);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_message('danger', 'Não foi possível concluir a aprovação múltipla. Tente novamente.');
}

header("Location: {$return_url}");
exit;
