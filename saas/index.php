<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap/app.php';
require_once base_path('app/Helpers/url.php');
require_once base_path('app/Helpers/asset.php');

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '';
$base = '/saas';
if (strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base));
}
$uri = $uri ?: '/';

if ($uri === '/login') {
    require_once base_path('app/Modules/Auth/Controllers/LoginController.php');
    $c = new \App\Modules\Auth\Controllers\LoginController();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $c->authenticate();
        exit;
    }

    echo $c->index();
    exit;
}

if (empty($_SESSION['auth'])) {
    header('Location: ' . url('login'));
    exit;
}

if ($uri === '/' || $uri === '/dashboard') {
    require_once base_path('app/Modules/Dashboard/Controllers/DashboardController.php');
    $c = new \App\Modules\Dashboard\Controllers\DashboardController();
    echo $c->index();
    exit;
}

if ($uri === '/logout') {
    unset($_SESSION['auth']);
    header('Location: ' . url('login'));
    exit;
}

http_response_code(404);
echo '404';