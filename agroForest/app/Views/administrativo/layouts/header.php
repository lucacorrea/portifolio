<?php
if (!isset($tituloPagina) || $tituloPagina === '') {
    $tituloPagina = 'Sistema';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina) ?></title>

   <link rel="stylesheet" href="../../app/assets/css/administrativo/style.css">
</head>
<body>