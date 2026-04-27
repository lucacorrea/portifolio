<?php
namespace App\Controllers;

abstract class BaseController {
    protected function render($view, $data = [], $layout = 'layouts/main') {
        // Inject CSRF token globally for views
        $data['csrf_token'] = $_SESSION['csrf_token'] ?? '';
        
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

    protected function validatePost() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!validateCsrf($token)) {
                die("Erro de validação CSRF. Requisição bloqueada por segurança.");
            }
            // Auto-sanitize all POST data
            $_POST = sanitizeInput($_POST);
        }
    }

    protected function jsonResponse($success, $message, $data = [], $code = 200) {
        return jsonResponse($success, $message, $data, $code);
    }

    protected function redirect($url) {
        header("Location: $url");
        exit;
    }

    protected function executeSafe(callable $action, $isAjax = true) {
        try {
            return $action();
        } catch (\Exception $e) {
            error_log("Controller Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            if ($isAjax || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                $this->jsonResponse(false, $e->getMessage(), [], 500);
            } else {
                setFlash('danger', 'Ocorreu um erro: ' . $e->getMessage());
                $this->redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
            }
        }
    }
}
