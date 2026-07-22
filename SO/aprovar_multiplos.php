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

    $stmt = $pdo->prepare("
        SELECT
            o.id,
            o.numero,
            o.status,
            o.fornecedor_indicado_id,
            EXISTS (SELECT 1 FROM aquisicoes a WHERE a.oficio_id = o.id) AS possui_aquisicao
        FROM oficios o
        WHERE o.id IN ($placeholders)
        ORDER BY o.id
        FOR UPDATE
    ");
    $stmt->execute($ids);
    $oficios_encontrados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ids_aprovar = [];
    $ids_atualizar_fornecedor = [];
    $numeros_alterados = [];
    $sem_alteracao = 0;
    $com_aquisicao = 0;

    foreach ($oficios_encontrados as $oficio) {
        $oficio_id = (int)$oficio['id'];
        $status = (string)$oficio['status'];

        if ($status === 'ENVIADO') {
            $ids_aprovar[] = $oficio_id;
            $numeros_alterados[] = (string)$oficio['numero'];
            continue;
        }

        if ($status === 'APROVADO') {
            if (!empty($oficio['possui_aquisicao'])) {
                $com_aquisicao++;
                continue;
            }

            if ((int)($oficio['fornecedor_indicado_id'] ?? 0) === $fornecedor_id) {
                $sem_alteracao++;
                continue;
            }

            $ids_atualizar_fornecedor[] = $oficio_id;
            $numeros_alterados[] = (string)$oficio['numero'];
        }
    }

    if (empty($ids_aprovar) && empty($ids_atualizar_fornecedor)) {
        $pdo->rollBack();
        $ignorados = count($ids) - $sem_alteracao - $com_aquisicao;
        if ($com_aquisicao > 0) {
            $mensagem = $com_aquisicao . ' ofício(s) aprovado(s) já possui(em) aquisição. Altere o fornecedor na própria aquisição.';
        } elseif ($sem_alteracao > 0) {
            $mensagem = 'Os ofícios aprovados selecionados já possuem esse fornecedor indicado.';
        } else {
            $mensagem = 'Nenhuma solicitação selecionada está com status ENVIADO ou APROVADO.';
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
            WHERE status = 'ENVIADO'
              AND id IN ($update_placeholders)
        ");
        $stmt_update->execute(array_merge([$fornecedor_id], $ids_aprovar));
    }

    if (!empty($ids_atualizar_fornecedor)) {
        $update_placeholders = implode(',', array_fill(0, count($ids_atualizar_fornecedor), '?'));
        $stmt_update = $pdo->prepare("
            UPDATE oficios
            SET fornecedor_indicado_id = ?
            WHERE status = 'APROVADO'
              AND id IN ($update_placeholders)
        ");
        $stmt_update->execute(array_merge([$fornecedor_id], $ids_atualizar_fornecedor));
    }

    log_action(
        $pdo,
        'APROVACAO_FORNECEDOR_MULTIPLOS_OFICIOS',
        count($ids_aprovar) . ' solicitação(ões) aprovada(s) e '
            . count($ids_atualizar_fornecedor) . ' fornecedor(es) atualizado(s) para '
            . $fornecedor['nome'] . ': ' . implode(', ', $numeros_alterados)
    );

    $pdo->commit();
    $_SESSION['csrf_aprovacao_multipla'] = bin2hex(random_bytes(32));

    $ignorados = count($ids) - count($ids_aprovar) - count($ids_atualizar_fornecedor) - $sem_alteracao - $com_aquisicao;
    $mensagem = count($ids_aprovar) . ' solicitação(ões) aprovada(s) e '
        . count($ids_atualizar_fornecedor) . ' fornecedor(es) atualizado(s) com sucesso.';
    if ($sem_alteracao > 0) {
        $mensagem .= ' ' . $sem_alteracao . ' já possuía(m) o fornecedor indicado.';
    }
    if ($com_aquisicao > 0) {
        $mensagem .= ' ' . $com_aquisicao . ' já possuía(m) aquisição e deve(m) ser alterado(s) pela tela da aquisição.';
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
