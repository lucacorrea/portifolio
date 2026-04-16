<?php
declare(strict_types=1);

final class View
{
    public static function render(string $viewPath, array $data = []): void
    {
        $fullPath = APP_PATH . '/Modules/' . $viewPath . '.php';

        if (!file_exists($fullPath)) {
            http_response_code(500);
            echo 'View não encontrada: ' . htmlspecialchars($viewPath, ENT_QUOTES, 'UTF-8');
            exit;
        }

        extract($data, EXTR_SKIP);

        require $fullPath;
    }
}