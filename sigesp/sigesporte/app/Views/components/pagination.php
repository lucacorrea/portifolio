<?php
use Sigesp\Core\View;
$page = max(1, (int) ($page ?? 1));
$pages = max(1, (int) ($pages ?? 1));
$path = (string) ($path ?? '/');
$params = is_array($params ?? null) ? $params : [];
$pageUrl = static fn (int $number): string => View::url($path) . '?' . http_build_query([...$params, 'page' => $number]);
?>
<nav class="pagination" aria-label="Paginação"><a href="<?= View::e($pageUrl(max(1, $page - 1))) ?>" aria-label="Página anterior">Anterior</a><?php for ($number = max(1, $page - 2); $number <= min($pages, $page + 2); $number++): ?><a href="<?= View::e($pageUrl($number)) ?>" class="<?= $number === $page ? 'is-current' : '' ?>" aria-current="<?= $number === $page ? 'page' : 'false' ?>"><?= $number ?></a><?php endfor; ?><a href="<?= View::e($pageUrl(min($pages, $page + 1))) ?>" aria-label="Próxima página">Próxima</a></nav>
