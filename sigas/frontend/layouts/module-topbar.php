<?php declare(strict_types=1); ?>
<header class="module-topbar frontend-topbar">
    <button class="btn btn-light btn-icon d-lg-none" type="button" data-frontend-menu-toggle aria-expanded="false" aria-controls="moduleSidebar" aria-label="Abrir menu"><i class="bi bi-list"></i></button>
    <div><span class="module-topbar-kicker">SIGAS / <?= $environment['kind'] === 'module' ? 'Módulo' : 'Setor' ?></span><strong><?= sigas_frontend_escape($environment['name']) ?></strong></div>
    <a class="btn btn-outline-secondary btn-sm module-switch-button ms-auto" href="portal.php"><i class="bi bi-grid"></i><span>Trocar setor ou módulo</span></a>
    <div class="frontend-user-chip"><span class="avatar"><?= sigas_frontend_escape($frontendContext['user']['initials'] ?? 'U') ?></span><span><strong><?= sigas_frontend_escape($frontendContext['user']['name'] ?? 'Usuário') ?></strong><small><?= sigas_frontend_escape($frontendContext['user']['jobTitle'] ?? 'Usuário') ?></small></span></div>
    <form method="post" action="<?= sigas_frontend_escape($frontendContext['urls']['logout'] ?? 'sair.php') ?>"><input type="hidden" name="_csrf" value="<?= sigas_frontend_escape($frontendContext['csrf']['logout'] ?? '') ?>"><button class="btn btn-light btn-icon" type="submit" aria-label="Sair"><i class="bi bi-box-arrow-right"></i></button></form>
</header>
