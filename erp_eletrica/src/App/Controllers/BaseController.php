<?php
namespace App\Controllers;

abstract class BaseController {
    protected function render($view, $data = []) {
        extract($data);
        $viewPath = __DIR__ . "/../../../views/{$view}.view.php";
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            die("View {$view} not found at {$viewPath}");
        }
    }

    protected function redirect($url) {
        header("Location: {$url}");
        exit;
    }
}
