<?php
namespace App\Controllers;

abstract class BaseController {
    protected function render($view, $data = [], $layout = 'layouts/main') {
        extract($data);
        $viewPath = __DIR__ . "/../../../views/{$view}.view.php";
        
        if (!file_exists($viewPath)) {
            die("View {$view} not found at {$viewPath}");
        }

        if ($layout) {
            ob_start();
            require $viewPath;
            $content = ob_get_clean();
            
            $layoutPath = __DIR__ . "/../../../views/{$layout}.view.php";
            if (file_exists($layoutPath)) {
                require $layoutPath;
            } else {
                die("Layout {$layout} not found at {$layoutPath}");
            }
        } else {
            require $viewPath;
        }
    }

    protected function redirect($url) {
        header("Location: {$url}");
        exit;
    }
}
