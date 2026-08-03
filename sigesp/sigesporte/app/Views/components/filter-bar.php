<?php use Sigesp\Core\View; $filters = $filters ?? []; ?>
<section class="filter-bar" data-filter-bar><button class="filter-bar__toggle button button--secondary" type="button" data-filter-toggle aria-expanded="false">Filtros <span data-filter-count></span></button><div class="filter-bar__body" data-filter-content><?= $filters ?></div></section>
