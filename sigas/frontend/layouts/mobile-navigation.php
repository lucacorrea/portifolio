<?php
declare(strict_types=1);

$menuSurface = 'mobile';
$menuPageKey = $pageKey;
require dirname(__DIR__, 2) . '/' . $environment['menu'];
