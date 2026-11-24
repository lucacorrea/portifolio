<?php
require dirname(__DIR__) . '/app/data.php';
$loggedUser = require_login();
if (!can_manage_users($loggedUser)) { http_response_code(403); exit('Acesso restrito à administração.'); }
$idText = (string)($_GET['id'] ?? '');
if ($idText !== '' && !preg_match('/^[1-9][0-9]{0,15}$/D',$idText)) { http_response_code(400); exit('Usuário inválido.'); }
$edit = [];
if ($idText !== '') {
    $st=db()->prepare('SELECT id,name,username,email,role FROM users WHERE id=? LIMIT 1');
    $st->execute([(int)$idText]);
    $edit=$st->fetch() ?: [];
    if (!$edit) { http_response_code(404); exit('Usuário não encontrado.'); }
    if ($loggedUser['role'] !== 'suporte' && $edit['role'] === 'suporte') { http_response_code(403); exit('Acesso restrito ao suporte.'); }
}
$formUser=$_SESSION['user_form_draft'] ?? null;
if (!is_array($formUser) || (string)($formUser['id'] ?? '') !== $idText) $formUser=$edit;
unset($_SESSION['user_form_draft']);
$formError=$_SESSION['user_form_error'] ?? '';
unset($_SESSION['user_form_error']);
$serverData=collect_data();
$serverUser=public_user($loggedUser);
$csrf=csrf_token();
$assetVersion=(string)max((int)@filemtime(__DIR__.'/assets/app.js'),(int)@filemtime(__DIR__.'/assets/styles.css'));
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#112e42"><title><?= $edit ? 'Editar' : 'Novo' ?> usuário · OBRAS360 Coari</title>
<link rel="stylesheet" href="assets/styles.css?v=<?= $assetVersion ?>"><link rel="stylesheet" href="assets/interactive.css"></head>
<body data-page="usuario-form"><div id="app"></div>
<script nonce="<?= htmlspecialchars($OBRAS_CSP_NONCE,ENT_QUOTES,'UTF-8') ?>">window.OBRAS_BOOTSTRAP=<?php echo json_encode([
    'records'=>$serverData,'user'=>$serverUser,'csrf'=>$csrf,'formUser'=>$formUser,
    'userFormError'=>$formError
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="assets/app.js?v=<?= $assetVersion ?>" defer></script></body></html>
