<?php
declare(strict_types=1);

$menuSurface = 'sidebar';
$menuPageKey = $pageKey;
require dirname(__DIR__, 2) . '/' . $environment['menu'];
