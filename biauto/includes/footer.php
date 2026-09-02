<?php
$jsPath = dirname(__DIR__) . '/assets/js/app.js';
$jsVersion = is_file($jsPath) ? (string) filemtime($jsPath) : (string) time();
?>
    </main>
</div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script src="assets/js/app.js?v=<?= h($jsVersion) ?>"></script>
</body>
</html>
