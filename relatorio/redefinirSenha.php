<?php
declare(strict_types=1);
session_start();

$token = trim((string)($_GET['token'] ?? ''));
$erro  = $_SESSION['flash_erro'] ?? '';
$ok    = $_SESSION['flash_ok'] ?? '';
unset($_SESSION['flash_erro'], $_SESSION['flash_ok']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Nova senha - SIGRelatórios</title>
  <link rel="stylesheet" href="./vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="./css/vertical-layout-light/style.css">
</head>
<body>
<div class="container mt-5" style="max-width:420px;">
  <h4>Definir nova senha</h4>
  <p class="text-muted">Crie uma nova senha para sua conta.</p>

  <?php if ($erro): ?>
    <div class="alert alert-danger"><?= h($erro) ?></div>
  <?php endif; ?>
  <?php if ($ok): ?>
    <div class="alert alert-success"><?= h($ok) ?></div>
  <?php endif; ?>

  <?php if ($token): ?>
  <form method="post" action="./controle/auth/resetarSenha.php">
    <input type="hidden" name="token" value="<?= h($token) ?>">

    <div class="form-group">
      <label>Nova senha</label>
      <input type="password" name="senha" class="form-control" minlength="6" required>
    </div>

    <div class="form-group">
      <label>Confirmar senha</label>
      <input type="password" name="senha2" class="form-control" minlength="6" required>
    </div>

    <button class="btn btn-primary btn-block">Salvar nova senha</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
