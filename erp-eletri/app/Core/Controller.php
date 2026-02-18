<?php

namespace App\Core;

class Controller
{
    public function view($view, $data = [])
    {
        extract($data);
        
        $viewFile = __DIR__ . "/../../app/Views/{$view}.php";
        
        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            die("View does not exist: " . $viewFile);
        }
    }

    public function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    public function redirect($url)
    {
        header("Location: " . $url);
        exit;
    }
}
