<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo '<h1>Index carregou</h1>';

require_once __DIR__ . '/bootstrap/app.php';

echo '<p>Bootstrap carregado</p>';

require_once base_path('app/Modules/Dashboard/Controllers/DashboardController.php');

echo '<p>Controller carregado</p>';

$controller = new \App\Modules\Dashboard\Controllers\DashboardController();

echo $controller->index();