<?php
// public/index.php

// Iniciar Sessão
session_start();

// Carregar Configurações
require_once __DIR__ . '/../config/database.php';

// Autoload Simples (PSR-4 estaria melhor, mas vamos manter simples e robusto)
spl_autoload_register(function ($class_name) {
    // Converter namespaces para caminhos de arquivo
    // Ex: App\Controllers\HomeController -> app/controllers/HomeController.php
    
    $paths = [
        'app/controllers/',
        'app/models/',
        'app/'
    ];

    $parts = explode('\\', $class_name);
    $class = end($parts);

    foreach ($paths as $path) {
        $file = __DIR__ . '/../' . $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Roteamento Básico
$url = isset($_GET['url']) ? $_GET['url'] : 'home/index';
$url = rtrim($url, '/');
$url = filter_var($url, FILTER_SANITIZE_URL);
$url = explode('/', $url);

$controllerName = isset($url[0]) && $url[0] != '' ? ucfirst($url[0]) . 'Controller' : 'HomeController';
$methodName = isset($url[1]) && $url[1] != '' ? $url[1] : 'index';
$params = array_slice($url, 2);

// Verificar se arquivo do controller existe
if (file_exists('../app/controllers/' . $controllerName . '.php')) {
    require_once '../app/controllers/' . $controllerName . '.php';
    
    // Instanciar Controller
    if (class_exists($controllerName)) {
        $controller = new $controllerName();
        
        // Verificar se método existe
        if (method_exists($controller, $methodName)) {
            call_user_func_array([$controller, $methodName], $params);
        } else {
            // Método não encontrado - 404
            echo "Erro 404: Método '$methodName' não encontrado em '$controllerName'.";
        }
    } else {
        echo "Erro: Classe '$controllerName' não encontrada.";
    }
} else {
    // Controller não encontrado - 404
    // Redirecionar para Home ou erro customizado
    echo "Erro 404: Controller '$controllerName' não encontrado.";
}
