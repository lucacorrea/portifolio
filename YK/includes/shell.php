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
