<?php if ($result['pages'] > 1): ?>
<nav class="pc-pagination" aria-label="Paginação">
  <span>Página <?= (int)$result['page'] ?> de <?= (int)$result['pages'] ?></span>
  <div>
    <?php
    $base = $_GET;
    if ($result['page'] > 1): $base['page'] = $result['page'] - 1; ?>
      <a class="btn btn-sm btn-outline-secondary" href="?<?= pc_h(http_build_query($base)) ?>">Anterior</a>
    <?php endif; ?>
    <?php if ($result['page'] < $result['pages']): $base['page'] = $result['page'] + 1; ?>
      <a class="btn btn-sm btn-outline-secondary" href="?<?= pc_h(http_build_query($base)) ?>">Próxima</a>
    <?php endif; ?>
  </div>
</nav>
<?php endif; ?>
