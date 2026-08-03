<?php use Sigesp\Core\View; ?>
<article class="document-card"><span aria-hidden="true">▣</span><div><strong><?= View::e($name ?? 'Documento') ?></strong><small><?= View::e($detail ?? 'Ainda não enviado') ?></small></div><?php View::component('badge', ['label' => $status ?? 'Pendente', 'tone' => $tone ?? 'warning']); ?></article>
