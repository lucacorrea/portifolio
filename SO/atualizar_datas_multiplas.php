<?php

require_once 'config/database.php';
require_once 'config/functions.php';

login_check();

function datas_lote_return_url(array $source): string
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
$return_url = datas_lote_return_url($return_source);
$nivel = strtoupper($_SESSION['nivel'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$return_url}");
    exit;
}

if (!in_array($nivel, ['ADMIN', 'SUPORTE'], true)) {
    flash_message('danger', 'Você não possui permissão para editar datas em lote.');
    header("Location: {$return_url}");
    exit;
}

$csrf_token = (string)($_POST['csrf_data_token'] ?? '');
$session_token = (string)($_SESSION['csrf_datas_multiplas'] ?? '');
if ($session_token === '' || $csrf_token === '' || !hash_equals($session_token, $csrf_token)) {
    flash_message('danger', 'A sessão de edição expirou. Atualize a página e tente novamente.');
    header("Location: {$return_url}");
    exit;
}

$nova_data = trim((string)($_POST['nova_data'] ?? ''));
$data_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $nova_data);
$data_erros = DateTimeImmutable::getLastErrors();
$data_invalida = $data_obj === false
    || ($data_erros !== false && ($data_erros['warning_count'] > 0 || $data_erros['error_count'] > 0))
    || $data_obj->format('Y-m-d') !== $nova_data;

if ($data_invalida) {
    flash_message('warning', 'Informe uma data válida para realizar a alteração.');
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
    flash_message('warning', 'Selecione entre 1 e 1000 solicitações válidas para editar.');
    header("Location: {$return_url}");
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    $pdo->beginTransaction();

    $stmt_oficios = $pdo->prepare("
        SELECT id, numero
        FROM oficios
        WHERE id IN ($placeholders)
        ORDER BY id
        FOR UPDATE
    ");
    $stmt_oficios->execute($ids);
    $oficios = $stmt_oficios->fetchAll(PDO::FETCH_ASSOC);

    if (empty($oficios)) {
        $pdo->rollBack();
        flash_message('warning', 'Nenhum dos ofícios selecionados foi encontrado.');
        header("Location: {$return_url}");
        exit;
    }

    $ids_encontrados = array_map('intval', array_column($oficios, 'id'));
    $placeholders_encontrados = implode(',', array_fill(0, count($ids_encontrados), '?'));

    $stmt_aquisicoes = $pdo->prepare("
        SELECT id
        FROM aquisicoes
        WHERE oficio_id IN ($placeholders_encontrados)
        ORDER BY id
        FOR UPDATE
    ");
    $stmt_aquisicoes->execute($ids_encontrados);
    $total_aquisicoes = count($stmt_aquisicoes->fetchAll(PDO::FETCH_COLUMN));

    $params_oficios = array_merge([$nova_data], $ids_encontrados);
    $stmt_update_oficios = $pdo->prepare("
        UPDATE oficios
        SET criado_em = CONCAT(?, ' ', COALESCE(TIME(criado_em), '00:00:00'))
        WHERE id IN ($placeholders_encontrados)
    ");
    $stmt_update_oficios->execute($params_oficios);

    $params_aquisicoes = array_merge([$nova_data], $ids_encontrados);
    $stmt_update_aquisicoes = $pdo->prepare("
        UPDATE aquisicoes
        SET criado_em = CONCAT(?, ' ', COALESCE(TIME(criado_em), '00:00:00'))
        WHERE oficio_id IN ($placeholders_encontrados)
    ");
    $stmt_update_aquisicoes->execute($params_aquisicoes);

    $numeros = array_map(static function (array $oficio): string {
        return (string)$oficio['numero'];
    }, $oficios);
    log_action(
        $pdo,
        'EDICAO_DATA_MULTIPLA_OFICIOS',
        count($ids_encontrados) . ' ofício(s) e ' . $total_aquisicoes . ' aquisição(ões) atualizados para ' . $data_obj->format('d/m/Y') . ': ' . implode(', ', $numeros)
    );

    $pdo->commit();

    flash_message(
        'success',
        'Data atualizada em ' . count($ids_encontrados) . ' ofício(s) e ' . $total_aquisicoes . ' aquisição(ões) vinculada(s).'
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_message('danger', 'Não foi possível atualizar as datas. Nenhum registro foi alterado.');
}

header("Location: {$return_url}");
exit;
