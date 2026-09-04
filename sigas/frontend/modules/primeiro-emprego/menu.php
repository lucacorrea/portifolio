<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/access.php';

$menuEnvironmentKey = 'primeiro-emprego';
$menuVisiblePageKeys = pe_visible_page_keys();

require dirname(__DIR__, 2) . '/navigation/module-menu.php';
