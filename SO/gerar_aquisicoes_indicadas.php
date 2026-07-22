<?php
require_once 'config/database.php';
require_once 'config/functions.php';

login_check();

function gerar_indicadas_return_url(array $source): string
{
    $safe = [];
    $status_options = ['PENDENTE_ITENS', 'ENVIADO', 'EM_ANALISE', 'APROVADO', 'REPROVADO', 'ARQUIVADO'];

    if (isset($source['busca']) && is_scalar($source['busca'])) {
        $safe['busca'] = substr(trim((string)$source['busca']), 0, 120);
    }
    if (isset($source['status']) && is_scalar($source['status']) && in_array((string)$source['status'], $status_options, true)) {
        $safe['status'] = (string)$source['status'];
    }
    foreach (['secretaria_id', 'fornecedor_id', 'page'] as $key) {
        if (isset($source[$key]) && is_numeric($source[$key]) && (int)$source[$key] > 0) {
            $safe[$key] = (int)$source[$key];
        }
    }
    foreach (['data_inicio', 'data_fim'] as $key) {
        if (isset($source[$key]) && is_scalar($source[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$source[$key])) {
            $safe[$key] = (string)$source[$key];
        }
    }

    $query = http_build_query($safe);
    return 'oficios_lista.php' . ($query !== '' ? '?' . $query : '');
}

$return_source = [];
parse_str((string)($_POST['return_query'] ?? ''), $return_source);
$return_url = gerar_indicadas_return_url($return_source);
$nivel = strtoupper($_SESSION['nivel'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$return_url}", true, 303);
    exit;
}

if (!in_array($nivel, ['ADMIN', 'SUPORTE'], true)) {
    flash_message('danger', 'Você não possui permissão para gerar aquisições indicadas.');
    header("Location: {$return_url}", true, 303);
    exit;
}

$csrf_token = is_scalar($_POST['csrf_token'] ?? null) ? (string)$_POST['csrf_token'] : '';
$session_token = (string)($_SESSION['csrf_gerar_aquisicoes_indicadas'] ?? '');
if ($csrf_token === '' || $session_token === '' || !hash_equals($session_token, $csrf_token)) {
    flash_message('danger', 'A sessão expirou. Atualize a página e tente novamente.');
    header("Location: {$return_url}", true, 303);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt_oficios = $pdo->query("
        SELECT o.id, o.numero, o.fornecedor_indicado_id, f.nome AS fornecedor
        FROM oficios o
        INNER JOIN fornecedores f ON f.id = o.fornecedor_indicado_id
        WHERE o.status = 'APROVADO'
          AND o.fornecedor_indicado_id IS NOT NULL
        ORDER BY o.id
        LIMIT 500
        FOR UPDATE
    ");
    $oficios_indicados = $stmt_oficios->fetchAll(PDO::FETCH_ASSOC);

    if (empty($oficios_indicados)) {
        $pdo->rollBack();
        flash_message('warning', 'Não há ofícios aprovados com fornecedor indicado para processar.');
        header("Location: {$return_url}", true, 303);
        exit;
    }

    $ids = array_map('intval', array_column($oficios_indicados, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt_existentes = $pdo->prepare("SELECT oficio_id FROM aquisicoes WHERE oficio_id IN ($placeholders) FOR UPDATE");
    $stmt_existentes->execute($ids);
    $oficios_com_aquisicao = array_fill_keys(
        array_map('intval', $stmt_existentes->fetchAll(PDO::FETCH_COLUMN)),
        true
    );

    $oficios_processar = [];
    $ja_gerados = 0;
    foreach ($oficios_indicados as $oficio) {
        $oficio_id = (int)$oficio['id'];
        if (isset($oficios_com_aquisicao[$oficio_id])) {
            $ja_gerados++;
            continue;
        }
        $oficios_processar[$oficio_id] = $oficio;
    }

    if (empty($oficios_processar)) {
        $pdo->rollBack();
        flash_message('warning', 'Todos os ofícios indicados já possuem aquisição vinculada.');
        header("Location: {$return_url}", true, 303);
        exit;
    }

    $ids_processar = array_keys($oficios_processar);
    $placeholders_processar = implode(',', array_fill(0, count($ids_processar), '?'));
    $stmt_itens = $pdo->prepare("
        SELECT id, oficio_id, produto, quantidade, valor_unitario
        FROM itens_oficio
        WHERE oficio_id IN ($placeholders_processar)
        ORDER BY oficio_id, id
        FOR UPDATE
    ");
    $stmt_itens->execute($ids_processar);

    $itens_por_oficio = [];
    foreach ($stmt_itens->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $itens_por_oficio[(int)$item['oficio_id']][] = $item;
    }

    $stmt_aquisicao = $pdo->prepare("
        INSERT INTO aquisicoes (numero_aq, codigo_entrega, oficio_id, fornecedor_id, valor_total)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt_item = $pdo->prepare("
        INSERT INTO itens_aquisicao (aquisicao_id, oficio_item_id, produto, quantidade, valor_unitario)
        VALUES (?, ?, ?, ?, ?)
    ");

    $geradas = 0;
    $sem_itens = 0;
    $numeros = [];
    foreach ($oficios_processar as $oficio_id => $oficio) {
        $itens = $itens_por_oficio[$oficio_id] ?? [];
        if (empty($itens)) {
            $sem_itens++;
            continue;
        }

        $valor_total = 0.0;
        foreach ($itens as $item) {
            $valor_total += (float)$item['quantidade'] * (float)($item['valor_unitario'] ?? 0);
        }

        $stmt_aquisicao->execute([
            generate_aquisicao_number($pdo),
            generate_unique_code($pdo),
            $oficio_id,
            (int)$oficio['fornecedor_indicado_id'],
            $valor_total,
        ]);
        $aquisicao_id = (int)$pdo->lastInsertId();

        foreach ($itens as $item) {
            $stmt_item->execute([
                $aquisicao_id,
                (int)$item['id'],
                $item['produto'],
                (float)$item['quantidade'],
                (float)($item['valor_unitario'] ?? 0),
            ]);
        }

        $geradas++;
        $numeros[] = (string)$oficio['numero'];
    }

    if ($geradas === 0) {
        $pdo->rollBack();
        flash_message('warning', 'Os ofícios indicados encontrados não possuem itens para gerar aquisição.');
        header("Location: {$return_url}", true, 303);
        exit;
    }

    $numeros_log = implode(', ', array_slice($numeros, 0, 30));
    if (count($numeros) > 30) {
        $numeros_log .= ', ...';
    }
    log_action(
        $pdo,
        'GERAR_AQUISICOES_FORNECEDOR_INDICADO',
        $geradas . ' aquisição(ões) gerada(s) para ofícios com fornecedor indicado: ' . $numeros_log
    );
    $pdo->commit();
    $_SESSION['csrf_gerar_aquisicoes_indicadas'] = bin2hex(random_bytes(32));

    $mensagem = $geradas . ' aquisição(ões) gerada(s) a partir dos fornecedores indicados.';
    if ($ja_gerados > 0) {
        $mensagem .= ' ' . $ja_gerados . ' ofício(s) já possuía(m) aquisição e foi(ram) preservado(s).';
    }
    if ($sem_itens > 0) {
        $mensagem .= ' ' . $sem_itens . ' ofício(s) não possui(em) itens e não foi(ram) gerado(s).';
    }
    flash_message('success', $mensagem);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_message('danger', 'Não foi possível gerar as aquisições indicadas. Tente novamente.');
}

header("Location: {$return_url}", true, 303);
exit;
