<?php
$pageTitle = 'Dashboard';
$pageSubtitle = 'Visão geral do sistema';
$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OSmais — Dashboard</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
  <div class="os-wrapper">
    <?php require __DIR__ . '/includes/menu.php'; ?>

    <main class="os-main">
      <?php require __DIR__ . '/includes/topbar.php'; ?>
      <?php require __DIR__ . '/pages/dashboard.php'; ?>
    </main>
  </div>

  <?php require __DIR__ . '/includes/modal-nova-os.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
  <script src="assets/js/dashboard.js"></script>
</body>
</html>
