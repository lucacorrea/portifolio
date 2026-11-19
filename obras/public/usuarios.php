<?php
require dirname(__DIR__) . '/app/data.php';
$loggedUser = require_login();
if (!can_manage_users($loggedUser)) { http_response_code(403); exit('Acesso restrito à administração.'); }
$serverData = collect_data();
$serverUser = public_user($loggedUser);
$csrf = csrf_token();
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#112e42"><meta name="description" content="Protótipo navegável demonstrativo do sistema OBRAS360 para a Secretaria Municipal de Obras e Planejamento de Coari, Amazonas.">
<title>Usuários e permissões · OBRAS360 Coari</title><link rel="stylesheet" href="assets/styles.css"><link rel="stylesheet" href="assets/interactive.css"></head>
<body data-page="usuarios"><div id="app"></div><script nonce="<?= htmlspecialchars($OBRAS_CSP_NONCE, ENT_QUOTES, 'UTF-8') ?>">window.OBRAS_BOOTSTRAP = <?php echo json_encode(['records'=>$serverData,'user'=>$serverUser,'csrf'=>$csrf], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script><script src="assets/app.js" defer></script></body></html>