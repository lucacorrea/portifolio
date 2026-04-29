<?php
if (!isset($tituloPagina) || $tituloPagina === '') {
    $tituloPagina = 'Sistema';
}

if (!isset($cssPagina)) {
    $cssPagina = '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina) ?></title>

    <?php if ($cssPagina !== ''): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($cssPagina) ?>">
    <?php endif; ?>
</head>
<body>