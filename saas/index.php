<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap/app.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$uri  = '/' . trim(str_replace($base, '', $uri), '/');

if ($uri === '//') {
    $uri = '/';
}

if ($uri === '/' || $uri === '/dashboard') {
    require_once base_path('app/Modules/Dashboard/Controllers/DashboardController.php');

    $controller = new \App\Modules\Dashboard\Controllers\DashboardController();
    echo $controller->index();
    exit;
}

http_response_code(404);
echo 'Página não encontrada';