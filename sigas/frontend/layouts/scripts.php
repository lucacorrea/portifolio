<?php declare(strict_types=1); ?>
<div class="toast-container position-fixed top-0 end-0 p-3" id="frontendToastContainer" aria-live="polite"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?= App\Core\PageContext::script($frontendContext) ?>
<script src="<?= sigas_frontend_asset('assets/js/frontend-modules.js') ?>"></script>
<?php if (isset($environment['assets']['js']) && is_file(dirname(__DIR__, 2) . '/' . $environment['assets']['js'])): ?>
    <script src="<?= sigas_frontend_asset($environment['assets']['js']) ?>"></script>
<?php endif; ?>
<?php foreach ($extraScripts ?? [] as $extraScript): ?>
    <?php if (is_string($extraScript) && is_file(dirname(__DIR__, 2) . '/' . $extraScript)): ?><script src="<?= sigas_frontend_asset($extraScript) ?>"></script><?php endif; ?>
<?php endforeach; ?>
