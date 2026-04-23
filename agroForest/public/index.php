<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Helpers/url.php';
require dirname(__DIR__) . '/app/Helpers/auth.php';
require dirname(__DIR__) . '/app/Core/Router.php';
require dirname(__DIR__) . '/app/Core/Controller.php';
require dirname(__DIR__) . '/app/Core/Model.php';
require dirname(__DIR__) . '/app/Core/Session.php';

Session::start();

date_default_timezone_set((require dirname(__DIR__) . '/app/Config/app.php')['timezone'] ?? 'America/Manaus');

$area = $_GET['area'] ?? 'recepcao';
$pagina = $_GET['pagina'] ?? 'dashboard';
$view = Router::resolve($area, $pagina);

if ($view === null) {
    http_response_code(404);
    require dirname(__DIR__) . '/app/Views/errors/404.php';
    exit;
}

require dirname(__DIR__) . '/app/Views/' . $view . '.php';
