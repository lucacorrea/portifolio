<?php
declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;

$app = require dirname(__DIR__) . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$session = $application->session();
$session->start();
$csrf = $application->csrf();

try {
  $authorization = $application->authorization();
  $currentUser = $authorization->requireLogin();
} catch (AuthenticationException $exception) {
  $session->flash('warning', 'Sua sessão expirou. Entre novamente.');
  $currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? 'dashboard.php', PHP_URL_PATH) ?: 'dashboard.php');
  header('Location: login.php?next=' . rawurlencode($application->redirect()->sanitize($currentPage)), true, 303);
  exit;
} catch (Throwable $exception) {
  $session->flash('danger', 'Não foi possível manter o acesso ao sistema. Entre em contato com o administrador.');
  header('Location: login.php', true, 303);
  exit;
}

try {
  if (isset($requiredPermission) && is_string($requiredPermission) && $requiredPermission !== '') {
    $authorization->requirePermission($requiredPermission);
  } elseif (isset($requiredAllPermissions) && is_array($requiredAllPermissions) && $requiredAllPermissions !== []) {
    foreach ($requiredAllPermissions as $permission) {
      $authorization->requirePermission((string) $permission);
    }
  } elseif (isset($requiredAnyPermission) && is_array($requiredAnyPermission) && $requiredAnyPermission !== []) {
    $authorization->requireAnyPermission($requiredAnyPermission);
  } else {
    error_log('Internal page without required permission declaration: ' . ($_SERVER['SCRIPT_NAME'] ?? 'unknown'));
    throw new AuthorizationException('Acesso negado.');
  }
} catch (AuthorizationException $exception) {
  header('Location: acesso-negado.php', true, 303);
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>K. Yamaguchi — <?= htmlspecialchars($pageTitle ?? 'Sistema', ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
  <div class="os-wrapper">
    <?php require __DIR__ . '/menu.php'; ?>

    <main class="os-main">
      <?php require __DIR__ . '/topbar.php'; ?>
      <?php $flashMessages = $session->consumeFlashMessages(); ?>
      <?php if ($flashMessages !== []): ?>
        <div class="flash-stack" role="status" aria-live="polite">
          <?php foreach ($flashMessages as $message): ?>
            <?php $type = in_array(($message['type'] ?? ''), ['success', 'info', 'warning', 'danger'], true) ? $message['type'] : 'info'; ?>
            <div class="alert alert-<?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?> mb-2">
              <?= htmlspecialchars((string) ($message['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php require $pageContent; ?>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/osmais-app.js"></script>
  <?php foreach (($pageScripts ?? []) as $script): ?>
  <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
  <?php endforeach; ?>
</body>
</html>
