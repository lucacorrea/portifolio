<?php
use Sigesp\Core\View;

$filters = $filters ?? [];
static $filterInstance = 0;
$filterInstance++;
$contentId = 'filter-content-' . $filterInstance;
$buttonId = 'filter-toggle-' . $filterInstance;
?>
<section class="filter-bar" data-filter-bar>
    <button class="filter-bar__toggle button button--secondary" id="<?= View::e($buttonId) ?>"
        type="button" data-filter-toggle aria-expanded="false" aria-controls="<?= View::e($contentId) ?>">
        Filtros <span data-filter-count aria-live="polite"></span>
    </button>
    <div class="filter-bar__body" id="<?= View::e($contentId) ?>" data-filter-content
        role="region" aria-labelledby="<?= View::e($buttonId) ?>"><?= $filters ?></div>
</section>
