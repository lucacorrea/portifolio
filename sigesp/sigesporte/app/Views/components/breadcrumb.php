<?php use Sigesp\Core\View; $items = $items ?? []; ?>
<nav class="breadcrumb" aria-label="Caminho de navegação"><a href="<?= View::e(View::url('/dashboard')) ?>">SIGESP</a><?php foreach ($items as $item): ?><span aria-hidden="true">/</span><span><?= View::e($item) ?></span><?php endforeach; ?></nav>
