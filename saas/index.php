<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap/app.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (empty($_SESSION['auth']) && $uri !== '/login') {
    header('Location: login');
    exit;
}

if ($uri === '/' || $uri === '/dashboard') {
    require_once base_path('app/Modules/Dashboard/Controllers/DashboardController.php');
    $c = new \App\Modules\Dashboard\Controllers\DashboardController();
    echo $c->index();
    exit;
}

if ($uri === '/login') {
    require_once base_path('app/Modules/Auth/Controllers/LoginController.php');
    $c = new \App\Modules\Auth\Controllers\LoginController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $c->authenticate();
        exit;
    }

    echo $c->index();
    exit;
}

echo "404";
