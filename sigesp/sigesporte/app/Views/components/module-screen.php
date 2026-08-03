<?php
declare(strict_types=1);
use Sigesp\Core\View;

$page = is_array($page ?? null) ? $page : [];
$screen = (string) ($page['screen'] ?? 'index');
if ($screen === 'index') {
    View::component('demo-list-page', ['page' => $page]);
} elseif ($screen === 'novo') {
    View::component('demo-form-page', ['page' => $page]);
} else {
    View::component('demo-detail-page', ['page' => $page, 'screen' => $screen]);
}
