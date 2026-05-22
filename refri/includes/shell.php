<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OSmais — <?= htmlspecialchars($pageTitle ?? 'Sistema', ENT_QUOTES, 'UTF-8') ?></title>

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
      <?php require $pageContent; ?>
    </main>
  </div>

  <?php if (($includeDashboardModal ?? false) === true) require __DIR__ . '/modal-nova-os.php'; ?>

  <div id="app-modal-root"></div>
  <?php if (($includeDashboardModal ?? false) !== true): ?>
  <div class="toast-container" id="toast-container"></div>
  <?php endif; ?>
  <div id="pdf-workspace" class="pdf-workspace" aria-hidden="true"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
  <?php if (($includePdfVendor ?? false) === true): ?>
  <script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
  <?php endif; ?>
  <?php foreach (($pageScripts ?? []) as $script): ?>
  <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
  <?php endforeach; ?>
</body>
</html>
