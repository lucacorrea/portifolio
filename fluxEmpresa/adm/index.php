<?php
declare(strict_types=1);

use App\Core\Application;

$app = require dirname(__DIR__) . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$session = $application->session();
$session->start();
$csrf = $application->csrf();

try {
    $currentUser = $application->authorization()->requireLogin();
    if (!$currentUser->isPlatformAdministrator()) {
        http_response_code(403);
        header('Location: ' . $application->redirect()->applicationUrl('acesso-negado.php'), true, 303);
        exit;
    }
} catch (Throwable) {
    $session->flash('warning', 'Sua sessão expirou. Entre novamente.');
    header('Location: ' . $application->redirect()->loginUrl(), true, 303);
    exit;
}

$cards = [
    ['Usuários', 'Gerencie acessos e contas internas.', '../usuarios.php', 'bi-person-gear'],
    ['Perfis de acesso', 'Defina as permissões de cada perfil.', '../perfis-acesso.php', 'bi-shield-lock'],
    ['Configurações', 'Acesse as configurações gerais do Flux.', '../configuracoes.php', 'bi-sliders'],
    ['Painel operacional', 'Abrir o painel principal do sistema.', '../dashboard.php', 'bi-grid-1x2'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Flux Empresas — Administração</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/css/dashboard.css') ?>">
</head>
<body>
<main class="page-body dashboard-page" style="max-width:1180px;margin:0 auto;padding-top:2rem">
  <header class="panel mb-4">
    <div class="panel-header">
      <div class="panel-title"><i class="bi bi-buildings"></i> Administração Flux Empresas</div>
      <form method="post" action="../actions/logout.php">
        <?= $csrf->field() ?>
        <button class="btn-filter btn-filter-ghost" type="submit"><i class="bi bi-box-arrow-right"></i> Sair</button>
      </form>
    </div>
    <p class="mb-0 text-muted">Olá, <?= htmlspecialchars($currentUser->name(), ENT_QUOTES, 'UTF-8') ?>. Você está na área administrativa da plataforma.</p>
  </header>
  <section class="quick-actions panel">
    <div class="panel-header"><div class="panel-title"><i class="bi bi-speedometer2"></i> Acessos administrativos</div></div>
    <div class="quick-grid">
      <?php foreach ($cards as [$label, $description, $href, $icon]): ?>
      <a class="quick-action text-decoration-none" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
        <i class="bi <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>
        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
        <small><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></small>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
</main>
</body>
</html>
