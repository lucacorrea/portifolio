<?php
declare(strict_types=1);

/**
 * FRONT CONTROLLER (porta de entrada do sistema)
 */

require_once __DIR__ . '/../bootstrap/app.php';

/**
 * Rota simples inicial (sem router ainda)
 * Depois vamos evoluir para Router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// remove /public se existir
$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$uri = '/' . trim(str_replace($base, '', $uri), '/');

if ($uri === '/' || $uri === '/dashboard') {

    require_once base_path('app/Modules/Dashboard/Controllers/DashboardController.php');

    $controller = new \App\Modules\Dashboard\Controllers\DashboardController();

    echo $controller->index();
    exit;
}

/**
 * 404 simples
 */
http_response_code(404);
echo "Página não encontrada";