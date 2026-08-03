<?php use Sigesp\Core\View; $items = $items ?? []; ?>
<ol class="timeline"><?php foreach ($items as $item): ?><li><span></span><div><strong><?= View::e($item['title']) ?></strong><p><?= View::e($item['detail']) ?></p></div></li><?php endforeach; ?></ol>
