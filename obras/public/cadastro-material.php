<?php
require dirname(__DIR__) . '/app/data.php';
$loggedUser = require_login();
$serverData = collect_data();
$serverUser = public_user($loggedUser);
$csrf = csrf_token();
?>
<!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#112e42"><meta name="description" content="OBRAS360 · Protótipo visual interativo para apresentação. Dados fictícios, sem conexão com sistema oficial.">
<title>Cadastrar material · OBRAS360 Coari</title>
<link rel="stylesheet" href="assets/styles.css"><link rel="stylesheet" href="assets/interactive.css">
</head><body data-page="cadastro" data-module="materiais"><div id="app"></div><script nonce="<?= htmlspecialchars($OBRAS_CSP_NONCE, ENT_QUOTES, 'UTF-8') ?>">window.OBRAS_BOOTSTRAP = <?php echo json_encode(['records'=>$serverData,'user'=>$serverUser,'csrf'=>$csrf], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script><script src="assets/app.js" defer></script></body></html>
