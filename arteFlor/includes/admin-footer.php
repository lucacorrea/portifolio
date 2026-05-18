  </main>
</div>
<div class="toast-root" data-toast-root aria-live="polite" aria-atomic="true"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/admin.js') ?>"></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
<script src="<?= asset($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
