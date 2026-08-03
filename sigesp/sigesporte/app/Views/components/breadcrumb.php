<?php use Sigesp\Core\View; $items = $items ?? []; ?>
<nav class="breadcrumb" aria-label="Caminho de navegação"><a href="/dashboard">SIGESP</a><?php foreach ($items as $item): ?><span aria-hidden="true">/</span><span><?= View::e($item) ?></span><?php endforeach; ?></nav>
