<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$perfil = strtoupper(trim((string)($_SESSION['perfil'] ?? $_SESSION['tipo_usuario'] ?? $_SESSION['nivel'] ?? '')));
$usuario = (string)($_SESSION['nome'] ?? $_SESSION['usuario'] ?? 'SUPORTE');

if ($perfil !== 'SUPORTE') {
    http_response_code(403);
    exit('Acesso permitido somente ao SUPORTE.');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) exit('Aquisição inválida.');

$csrf = $_POST['csrf_token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) exit('Token de segurança inválido.');
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$stmt = $pdo->prepare("SELECT a.id, a.numero_aq, a.assinatura_fornecedor_id, a.assinatura_fornecedor_em, a.assinatura_fornecedor_por, f.nome AS fornecedor FROM aquisicoes a JOIN fornecedores f ON f.id=a.fornecedor_id WHERE a.id=? LIMIT 1");
$stmt->execute([$id]);
$aq = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$aq) exit('Aquisição não encontrada.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT id, nome, arquivo_path FROM assinaturas_sistema WHERE finalidade='AUTORIZACAO_FORNECEDOR' AND ativo=1 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$assinatura) exit('Nenhuma assinatura ativa está cadastrada em Configurações > Assinatura.');

    $stmt = $pdo->prepare("UPDATE aquisicoes SET assinatura_fornecedor_id=?, assinatura_fornecedor_em=NOW(), assinatura_fornecedor_por=? WHERE id=?");
    $stmt->execute([(int)$assinatura['id'], $usuario, $id]);

    header('Location: aquisicoes_visualizar.php?id=' . $id . '&assinatura=ok');
    exit;
}

$stmt = $pdo->prepare("SELECT id, nome, arquivo_path FROM assinaturas_sistema WHERE finalidade='AUTORIZACAO_FORNECEDOR' AND ativo=1 ORDER BY id DESC LIMIT 1");
$stmt->execute();
$assinatura = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Assinar Aquisição</title>
<style>body{font-family:Arial,sans-serif;background:#f5f7fa;margin:0;padding:30px}.box{max-width:620px;margin:auto;background:#fff;padding:28px;border-radius:14px;box-shadow:0 5px 25px #0001}h1{margin-top:0;font-size:22px}.info{background:#f7f7f7;padding:14px;border-radius:10px;line-height:1.7}.sig{margin:22px 0;text-align:center;border:1px solid #ddd;border-radius:10px;padding:18px}.sig img{max-width:360px;max-height:130px;object-fit:contain}.actions{display:flex;gap:10px;justify-content:flex-end}.btn{border:0;border-radius:8px;padding:11px 18px;cursor:pointer;font-weight:700;text-decoration:none}.back{background:#eee;color:#222}.ok{background:#0b65c2;color:#fff}</style></head><body><div class="box">
<h1>Assinar Autorização de Fornecedor</h1>
<div class="info"><b>Aquisição:</b> <?=h($aq['numero_aq'])?><br><b>Fornecedor:</b> <?=h($aq['fornecedor'])?><br><b>Usuário:</b> <?=h($usuario)?></div>
<?php if ($assinatura): ?><div class="sig"><div><b>Assinatura cadastrada</b></div><img src="<?=h($assinatura['arquivo_path'])?>" alt="Assinatura"></div><?php else: ?><div class="sig">Nenhuma assinatura ativa cadastrada.</div><?php endif; ?>
<form method="post" onsubmit="return confirm('Confirmar a assinatura desta autorização de fornecedor?');"><input type="hidden" name="id" value="<?=h($id)"><input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>"><div class="actions"><a class="btn back" href="aquisicoes_visualizar.php?id=<?=h($id)">Cancelar</a><button class="btn ok" type="submit" <?=(!$assinatura?'disabled':'')?>>Confirmar assinatura</button></div></form>
</div></body></html>
