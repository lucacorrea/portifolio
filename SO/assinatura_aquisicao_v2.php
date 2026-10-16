<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

if (!usuario_pode_assinar_aquisicao($pdo)) {
    http_response_code(403);
    exit('Apenas os usuários suporte e amandha podem assinar aquisições.');
}

function h_assinar_aquisicao($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$idRaw = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? '') : ($_GET['id'] ?? '');
$id = is_scalar($idRaw) && ctype_digit((string)$idRaw) ? (int)$idRaw : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Aquisição inválida.');
}

if (empty($_SESSION['csrf_assinatura_aquisicao'])) {
    $_SESSION['csrf_assinatura_aquisicao'] = bin2hex(random_bytes(32));
}

$stmt = $pdo->prepare('
    SELECT a.id, a.numero_aq, f.nome AS fornecedor
    FROM aquisicoes a
    INNER JOIN fornecedores f ON f.id = a.fornecedor_id
    WHERE a.id = ?
    LIMIT 1
');
$stmt->execute([$id]);
$aq = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$aq) {
    http_response_code(404);
    exit('Aquisição não encontrada.');
}

try {
    $stmt = $pdo->prepare('
        SELECT aa.id, aa.assinado_por, aa.assinado_em, aa.assinatura_id, s.nome
        FROM assinaturas_aquisicoes aa
        INNER JOIN assinaturas_sistema s ON s.id = aa.assinatura_id
        WHERE aa.aquisicao_id = ?
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $registrada = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT id, nome
        FROM assinaturas_sistema
        WHERE finalidade = 'AUTORIZACAO_FORNECEDOR' AND ativo = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute();
    $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(503);
    exit('A assinatura não está configurada no banco. Solicite ao suporte a instalação de sql/assinatura_aquisicoes.sql.');
}

$usuario = trim((string)($_SESSION['user_nome'] ?? ''));
if ($usuario === '') {
    $stmt = $pdo->prepare('SELECT nome FROM usuarios WHERE id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $usuario = (string)$stmt->fetchColumn();
}
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenPostado = $_POST['csrf_token'] ?? null;
    if (!is_string($tokenPostado) || !hash_equals((string)$_SESSION['csrf_assinatura_aquisicao'], $tokenPostado)) {
        http_response_code(403);
        exit('Token de segurança inválido.');
    }
    if ($registrada) {
        header('Location: aquisicoes_visualizar.php?id=' . $id . '&assinatura=ja_assinada', true, 303);
        exit;
    }
    if (!$assinatura) {
        $erro = 'Nenhuma assinatura ativa está cadastrada. Solicite ao suporte que configure a assinatura.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('
                INSERT INTO assinaturas_aquisicoes (aquisicao_id, assinatura_id, assinado_por)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$id, (int)$assinatura['id'], $usuario]);
            log_action($pdo, 'ASSINAR_AQUISICAO', 'Aquisição ' . $aq['numero_aq'] . ' assinada por ' . $usuario);
            $pdo->commit();
            unset($_SESSION['csrf_assinatura_aquisicao']);
            header('Location: aquisicoes_visualizar.php?id=' . $id . '&assinatura=ok', true, 303);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ((string)$e->getCode() === '23000') {
                header('Location: aquisicoes_visualizar.php?id=' . $id . '&assinatura=ja_assinada', true, 303);
                exit;
            }
            $erro = 'Não foi possível registrar a assinatura. Tente novamente.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = 'Não foi possível registrar a assinatura. Tente novamente.';
        }
    }
}

?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assinar aquisição <?= h_assinar_aquisicao($aq['numero_aq']) ?></title>
    <link rel="stylesheet" href="assets/css/assinatura_aquisicao.css">
</head>
<body>
<main class="signature-card">
    <div class="signature-heading">
        <span class="signature-eyebrow">Sistema SO • Autorização de fornecimento</span>
        <h1>Assinar aquisição</h1>
        <p>Confirme a autorização. O sistema registrará quem assinou e a data/hora da assinatura.</p>
    </div>
    <dl class="signature-details">
        <div><dt>Aquisição</dt><dd><?= h_assinar_aquisicao($aq['numero_aq']) ?></dd></div>
        <div><dt>Fornecedor</dt><dd><?= h_assinar_aquisicao($aq['fornecedor']) ?></dd></div>
        <div><dt>Usuário responsável</dt><dd><?= h_assinar_aquisicao($usuario) ?></dd></div>
    </dl>
    <?php if ($erro !== ''): ?>
        <div class="signature-alert signature-error"><?= h_assinar_aquisicao($erro) ?></div>
    <?php endif; ?>

    <?php if ($registrada): ?>
        <div class="signature-alert signature-ok">
            Esta aquisição já foi assinada por <strong><?= h_assinar_aquisicao($registrada['assinado_por']) ?></strong>,
            em <?= h_assinar_aquisicao(date('d/m/Y H:i', strtotime((string)$registrada['assinado_em']))) ?>.
        </div>
        <div class="signature-preview">
            <span>Assinatura registrada</span>
            <img src="assinatura_suporte.php?aquisicao=<?= $id ?>" alt="Assinatura da autorização">
        </div>
    <?php elseif ($assinatura): ?>
        <div class="signature-preview">
            <span>Assinatura que será aplicada</span>
            <img src="assinatura_suporte.php?id=<?= (int)$assinatura['id'] ?>" alt="Assinatura ativa de autorização">
        </div>
    <?php else: ?>
        <div class="signature-alert signature-error">Nenhuma assinatura ativa. O suporte precisa cadastrar ou ativar uma assinatura em Configurações.</div>
    <?php endif; ?>

    <form method="post" onsubmit="return confirm('Confirma a assinatura desta aquisição? Esta ação ficará registrada e não poderá ser repetida.');">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="csrf_token" value="<?= h_assinar_aquisicao($_SESSION['csrf_assinatura_aquisicao']) ?>">
        <div class="signature-actions">
            <a href="aquisicoes_visualizar.php?id=<?= $id ?>" class="signature-button signature-back">Voltar à aquisição</a>
            <?php if (!$registrada): ?>
                <button type="submit" class="signature-button signature-submit" <?= $assinatura ? '' : 'disabled' ?>>Confirmar assinatura</button>
            <?php endif; ?>
        </div>
    </form>
</main>
</body>
</html>
