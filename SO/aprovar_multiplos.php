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
    $por_pagina_options = [6, 10, 25, 50, 100];
    if (isset($source['por_pagina']) && is_scalar($source['por_pagina']) && ctype_digit((string)$source['por_pagina']) && in_array((int)$source['por_pagina'], $por_pagina_options, true)) {
        $safe['por_pagina'] = (int)$source['por_pagina'];
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

    $stmt_aquisicoes = $pdo->prepare("SELECT oficio_id FROM aquisicoes WHERE oficio_id IN ($placeholders) FOR UPDATE");
    $stmt_aquisicoes->execute($ids);
    $com_aquisicao_map = array_fill_keys(array_map('intval', $stmt_aquisicoes->fetchAll(PDO::FETCH_COLUMN)), true);

    $stmt_oficios = $pdo->prepare("
        SELECT
            o.id,
            o.numero,
            o.status,
            o.criado_em,
            o.fornecedor_indicado_id,
            f.nome AS fornecedor_nome
        FROM oficios o
        LEFT JOIN fornecedores f ON f.id = o.fornecedor_indicado_id
        WHERE o.id IN ($placeholders)
        ORDER BY o.id
        FOR UPDATE
    ");
    $stmt_oficios->execute($ids);
    $oficios_encontrados = $stmt_oficios->fetchAll(PDO::FETCH_ASSOC);

    $oficios_elegiveis = [];
    $com_aquisicao = 0;
    $sem_fornecedor = 0;
    $status_ignorado = 0;

    foreach ($oficios_encontrados as $oficio) {
        $oficio_id = (int)$oficio['id'];
        if (!in_array((string)$oficio['status'], ['ENVIADO', 'APROVADO'], true)) {
            $status_ignorado++;
            continue;
        }
        if (isset($com_aquisicao_map[$oficio_id])) {
            $com_aquisicao++;
            continue;
        }
        if ((int)($oficio['fornecedor_indicado_id'] ?? 0) <= 0 || empty($oficio['fornecedor_nome'])) {
            $sem_fornecedor++;
            continue;
        }
        $oficios_elegiveis[$oficio_id] = $oficio;
    }

    $itens_por_oficio = [];
    if (!empty($oficios_elegiveis)) {
        $ids_elegiveis = array_keys($oficios_elegiveis);
        $ph_itens = implode(',', array_fill(0, count($ids_elegiveis), '?'));
        $stmt_itens = $pdo->prepare("
            SELECT id, oficio_id, produto, quantidade, valor_unitario
            FROM itens_oficio
            WHERE oficio_id IN ($ph_itens)
            ORDER BY oficio_id, id
            FOR UPDATE
        ");
        $stmt_itens->execute($ids_elegiveis);
        foreach ($stmt_itens->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itens_por_oficio[(int)$item['oficio_id']][] = $item;
        }
    }

    $oficios_processar = [];
    $sem_itens = 0;
    foreach ($oficios_elegiveis as $oficio_id => $oficio) {
        if (empty($itens_por_oficio[$oficio_id])) {
            $sem_itens++;
            continue;
        }
        $oficios_processar[$oficio_id] = $oficio;
    }

    if (empty($oficios_processar)) {
        $pdo->rollBack();
        $mensagem = 'Nenhuma solicitação selecionada pôde gerar aquisição.';
        if ($sem_fornecedor > 0) $mensagem .= ' ' . $sem_fornecedor . ' sem fornecedor definido.';
        if ($sem_itens > 0) $mensagem .= ' ' . $sem_itens . ' sem itens.';
        if ($com_aquisicao > 0) $mensagem .= ' ' . $com_aquisicao . ' já possuía(m) aquisição.';
        if ($status_ignorado > 0) $mensagem .= ' ' . $status_ignorado . ' com status não elegível.';
        flash_message('warning', $mensagem);
        header("Location: {$return_url}", true, 303);
        exit;
    }

    $stmt_update = $pdo->prepare("UPDATE oficios SET status = 'APROVADO' WHERE id = ? AND status = 'ENVIADO'");
    $stmt_aquisicao = $pdo->prepare("
        INSERT INTO aquisicoes (numero_aq, codigo_entrega, oficio_id, fornecedor_id, valor_total, criado_em)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt_item_aquisicao = $pdo->prepare("
        INSERT INTO itens_aquisicao (aquisicao_id, oficio_item_id, produto, quantidade, valor_unitario)
        VALUES (?, ?, ?, ?, ?)
    ");

    $aprovados_agora = 0;
    $gerados = 0;
    $por_fornecedor = [];
    $numeros_oficio = [];

    foreach ($oficios_processar as $oficio_id => $oficio) {
        if ((string)$oficio['status'] === 'ENVIADO') {
            $stmt_update->execute([$oficio_id]);
            if ($stmt_update->rowCount() === 1) {
                $aprovados_agora++;
            }
        }

        $itens = $itens_por_oficio[$oficio_id];
        $valor_total = 0.0;
        foreach ($itens as $item) {
            $valor_total += (float)$item['quantidade'] * (float)($item['valor_unitario'] ?? 0);
        }

        $fornecedor_id = (int)$oficio['fornecedor_indicado_id'];
        $stmt_aquisicao->execute([
            generate_aquisicao_number($pdo),
            generate_unique_code($pdo),
            $oficio_id,
            $fornecedor_id,
            $valor_total,
            $oficio['criado_em'],
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

        $gerados++;
        $nome_fornecedor = (string)$oficio['fornecedor_nome'];
        $por_fornecedor[$nome_fornecedor] = ($por_fornecedor[$nome_fornecedor] ?? 0) + 1;
        $numeros_oficio[] = (string)$oficio['numero'];
    }

    $resumo_fornecedores = [];
    foreach ($por_fornecedor as $nome => $qtd) {
        $resumo_fornecedores[] = $nome . ' (' . $qtd . ')';
    }

    log_action(
        $pdo,
        'APROVACAO_GERACAO_AQUISICAO_MULTIPLOS_OFICIOS',
        $aprovados_agora . ' solicitação(ões) aprovada(s), ' . $gerados . ' aquisição(ões) gerada(s) usando fornecedores previamente definidos: ' . implode(', ', $resumo_fornecedores) . '. Solicitações: ' . implode(', ', $numeros_oficio)
    );

    $pdo->commit();
    $_SESSION['csrf_aprovacao_multipla'] = bin2hex(random_bytes(32));

    $mensagem = $aprovados_agora . ' solicitação(ões) aprovada(s) e ' . $gerados . ' aquisição(ões) gerada(s) com os fornecedores previamente definidos.';
    if ($sem_fornecedor > 0) $mensagem .= ' ' . $sem_fornecedor . ' sem fornecedor foi(ram) ignorada(s).';
    if ($sem_itens > 0) $mensagem .= ' ' . $sem_itens . ' sem itens foi(ram) ignorada(s).';
    if ($com_aquisicao > 0) $mensagem .= ' ' . $com_aquisicao . ' já possuía(m) aquisição e foi(ram) preservada(s).';
    if ($status_ignorado > 0) $mensagem .= ' ' . $status_ignorado . ' com status não elegível foi(ram) ignorada(s).';
    flash_message('success', $mensagem);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_message('danger', 'Não foi possível concluir a aprovação múltipla. Nenhuma alteração parcial foi mantida.');
}

header("Location: {$return_url}", true, 303);
exit;
