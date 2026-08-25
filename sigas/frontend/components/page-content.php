<?php
declare(strict_types=1);
require __DIR__ . '/stat-grid.php';
require __DIR__ . '/filter-bar.php';
?>
<div class="frontend-block-grid">
<?php foreach ($pageDefinition['blocks'] as $block): ?>
    <?php
    $component = match ($block['type'] ?? '') {
        'table' => 'data-table.php',
        'chart' => 'chart-card.php',
        'info' => 'info-grid.php',
        'timeline', 'agenda' => 'timeline.php',
        'settings' => 'settings.php',
        default => null,
    };
    if ($component !== null) {
        require __DIR__ . '/' . $component;
    }
    ?>
<?php endforeach; ?>
</div>
<?php if (!empty($pageDefinition['show_states'])): ?><?php require __DIR__ . '/state-gallery.php'; ?><?php endif; ?>
