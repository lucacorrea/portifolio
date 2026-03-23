<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$layout = dirname(__DIR__, 2) . '/resources/views/layouts/admin.php';
$view   = dirname(__DIR__, 2) . '/resources/views/admin/dashboard.php';

if (!file_exists($layout)) {
    die('Layout não encontrado: ' . $layout);
}

if (!file_exists($view)) {
    die('View não encontrada: ' . $view);
}

$pageTitle = 'Dashboard Admin';
$contentView = $view;

include $layout;