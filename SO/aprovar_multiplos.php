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

$fornecedor_post = $_POST['fornecedor_id'] ?? null;
if (!is_scalar($fornecedor_post) || !ctype_digit((string)$fornecedor_post) || (int)$fornecedor_post <= 0) {
    flash_message('warning', 'Selecione um fornecedor válido para concluir a operação em lote.');
    header("Location: {$return_url}", true, 303);
    exit;
}
$fornecedor_id = (int)$fornecedor_post;

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

    $stmt_fornecedor = $pdo->prepare("SELECT id, nome FROM fornecedores WHERE id = ? FOR UPDATE");
    $stmt_fornecedor->execute([$fornecedor_id]);
    $fornecedor = $stmt_fornecedor->fetch(PDO::FETCH_ASSOC);

    if (!$fornecedor) {
        $pdo->rollBack();
        flash_message('warning', 'O fornecedor selecionado não existe mais. Atualize a página e tente novamente.');
        header("Location: {$return_url}", true, 303);
        exit;
    }

    $stmt_aquisicoes = $pdo->prepare("SELECT oficio_id FROM aquisicoes WHERE oficio_id IN ($placeholders) FOR UPDATE");
    $stmt_aquisicoes->execute($ids);
    $oficios_com_aquisicao = array_fill_keys(
        array_map('intval', $stmt_aquisicoes->fetchAll(PDO::FETCH_COLUMN)),
        true
    );

    $stmt = $pdo->prepare("
        SELECT id, numero, status
        FROM oficios
        WHERE id IN ($placeholders)
        ORDER BY id
        FOR UPDATE
    ");
    $stmt->execute($ids);
    $oficios_encontrados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $oficios_elegiveis = [];
    $com_aquisicao = 0;
    foreach ($oficios_encontrados as $oficio) {
        $oficio_id = (int)$oficio['id'];
        if (!in_array((string)$oficio['status'], ['ENVIADO', 'APROVADO'], true)) {
            continue;
        }

        if (isset($oficios_com_aquisicao[$oficio_id])) {
            $com_aquisicao++;
            continue;
        }

        $oficios_elegiveis[$oficio_id] = $oficio;
    }

    $itens_por_oficio = [];
    if (!empty($oficios_elegiveis)) {
        $ids_elegiveis = array_keys($oficios_elegiveis);
        $placeholders_elegiveis = implode(',', array_fill(0, count($ids_elegiveis), '?'));
        $stmt_itens = $pdo->prepare("
            SELECT id, oficio_id, produto, quantidade, valor_unitario
            FROM itens_oficio
            WHERE oficio_id IN ($placeholders_elegiveis)
            ORDER BY oficio_id, id
            FOR UPDATE
        ");
        $stmt_itens->execute($ids_elegiveis);

        foreach ($stmt_itens->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itens_por_oficio[(int)$item['oficio_id']][] = $item;
        }
    }

    $ids_aprovar = [];
    $ids_aprovados = [];
    $oficios_processar = [];
    $sem_itens = 0;
    foreach ($oficios_elegiveis as $oficio_id => $oficio) {
        if (empty($itens_por_oficio[$oficio_id])) {
            $sem_itens++;
            continue;
        }

        $oficios_processar[$oficio_id] = $oficio;
        if ((string)$oficio['status'] === 'ENVIADO') {
            $ids_aprovar[] = $oficio_id;
        } else {
            $ids_aprovados[] = $oficio_id;
        }
    }

    if (empty($oficios_processar)) {
        $pdo->rollBack();
        $ignorados = count($ids) - $com_aquisicao - $sem_itens;
        $mensagem = 'Nenhum ofício selecionado pôde gerar aquisição automaticamente.';
        if ($com_aquisicao > 0) {
            $mensagem .= ' ' . $com_aquisicao . ' já possuía(m) aquisição.';
        }
        if ($sem_itens > 0) {
            $mensagem .= ' ' . $sem_itens . ' não possui(em) itens para gerar a aquisição.';
        }
        if ($ignorados > 0) {
            $mensagem .= ' ' . $ignorados . ' item(ns) com outro status foram ignorados.';
        }
        flash_message('warning', $mensagem);
        header("Location: {$return_url}", true, 303);
        exit;
    }

    if (!empty($ids_aprovar)) {
        $update_placeholders = implode(',', array_fill(0, count($ids_aprovar), '?'));
        $stmt_update = $pdo->prepare("
            UPDATE oficios
            SET status = 'APROVADO', fornecedor_indicado_id = ?
            WHERE status = 'ENVIADO' AND id IN ($update_placeholders)
        ");
        $stmt_update->execute(array_merge([$fornecedor_id], $ids_aprovar));
    }

    if (!empty($ids_aprovados)) {
        $update_placeholders = implode(',', array_fill(0, count($ids_aprovados), '?'));
        $stmt_update = $pdo->prepare("
            UPDATE oficios
            SET fornecedor_indicado_id = ?
            WHERE status = 'APROVADO' AND id IN ($update_placeholders)
        ");
        $stmt_update->execute(array_merge([$fornecedor_id], $ids_aprovados));
    }

    $stmt_aquisicao = $pdo->prepare("
        INSERT INTO aquisicoes (numero_aq, codigo_entrega, oficio_id, fornecedor_id, valor_total)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt_item_aquisicao = $pdo->prepare("
        INSERT INTO itens_aquisicao (aquisicao_id, oficio_item_id, produto, quantidade, valor_unitario)
        VALUES (?, ?, ?, ?, ?)
    ");
    $numeros_oficio = [];
    foreach ($oficios_processar as $oficio_id => $oficio) {
        $itens = $itens_por_oficio[$oficio_id];
        $valor_total = 0.0;
        foreach ($itens as $item) {
            $valor_total += (float)$item['quantidade'] * (float)($item['valor_unitario'] ?? 0);
        }

        $stmt_aquisicao->execute([
            generate_aquisicao_number($pdo),
            generate_unique_code($pdo),
            $oficio_id,
            $fornecedor_id,
            $valor_total,
        ]);
        $aquisicao_id = (int)$pdo->lastInsertId();

        foreach ($itens as $item) {
            $stmt_item_aquisicao->execute([
                $aquisicao_id,
                (int)$item['id'],
                $item['produto'],
                (float)$item['quantidade'],
                (float)($item['valor_unitario'] ?? 0),
            ]);
        }
        $numeros_oficio[] = (string)$oficio['numero'];
    }

    log_action(
        $pdo,
        'APROVACAO_GERACAO_AQUISICAO_MULTIPLOS_OFICIOS',
        count($ids_aprovar) . ' solicitação(ões) aprovada(s), '
            . count($oficios_processar) . ' aquisição(ões) gerada(s) para '
            . $fornecedor['nome'] . ': ' . implode(', ', $numeros_oficio)
    );

    $pdo->commit();
    $_SESSION['csrf_aprovacao_multipla'] = bin2hex(random_bytes(32));

    $ignorados = count($ids) - count($oficios_processar) - $com_aquisicao - $sem_itens;
    $mensagem = count($ids_aprovar) . ' solicitação(ões) aprovada(s) e '
        . count($oficios_processar) . ' aquisição(ões) gerada(s) para ' . $fornecedor['nome'] . '.';
    if ($com_aquisicao > 0) {
        $mensagem .= ' ' . $com_aquisicao . ' já possuía(m) aquisição e foi(ram) preservado(s).';
    }
    if ($sem_itens > 0) {
        $mensagem .= ' ' . $sem_itens . ' não possui(em) itens e não gerou(ram) aquisição.';
    }
    if ($ignorados > 0) {
        $mensagem .= ' ' . $ignorados . ' item(ns) com outro status foram ignorados.';
    }
    flash_message('success', $mensagem);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_message('danger', 'Não foi possível concluir a aprovação múltipla. Tente novamente.');
}

header("Location: {$return_url}", true, 303);
exit;
