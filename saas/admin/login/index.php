<?php
declare(strict_types=1);

$config = require dirname(__DIR__, 2) . '/app/Config/app.php';
$basePath = rtrim((string)($config['base_path'] ?? ''), '/');
if ($basePath === '') {
    $basePath = '/saas';
}

$_SERVER['SCRIPT_NAME'] = $basePath . '/index.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
