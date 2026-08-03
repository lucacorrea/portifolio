<?php use Sigesp\Core\View; $name = (string) ($name ?? 'SIGESP'); ?>
<span class="avatar" aria-hidden="true"><?= View::e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
