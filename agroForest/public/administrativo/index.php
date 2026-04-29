<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Helpers/url.php';

$controller = trim($_GET['controller'] ?? 'dashboard');
$action     = trim($_GET['action'] ?? 'index');

$controller = preg_replace('/[^a-zA-Z0-9_-]/', '', $controller);
$action     = preg_replace('/[^a-zA-Z0-9_-]/', '', $action);

if ($controller === '') {
    $controller = 'dashboard';
}

if ($action === '') {
    $action = 'index';
}

$basePath          = dirname(__DIR__, 2);
$controllersPath   = $basePath . '/app/Controllers/Administrativo/';
$actionsPath       = $basePath . '/app/Actions/Administrativo/';


$controllerClassFile = ucfirst($controller) . 'Controller.php';
$controllerFile      = $controllersPath . $controllerClassFile;


$actionFile = $actionsPath . $controller . '/' . $action . '.php';


if (!is_dir($actionsPath . $controller)) {
    http_response_code(404);
    exit('Pasta de actions do controller não encontrada: ' . $controller);
}

if (!file_exists($actionFile)) {
    http_response_code(404);
    exit('Action não encontrada: ' . $controller . '/' . $action . '.php');
}


if (file_exists($controllerFile)) {
    require_once $controllerFile;
}


$controllerAtual = $controller;
$actionAtual     = $action;
$controllerFileAtual = $controllerFile;
$actionFileAtual     = $actionFile;

require $actionFile;