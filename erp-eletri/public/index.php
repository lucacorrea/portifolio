<?php
// public/index.php

use App\Core\Router;

session_start();

require_once __DIR__ . '/../app/Core/Autoload.php';

// Helper functions if needed
require_once __DIR__ . '/../app/Core/Helpers.php'; // We might create this later

try {
    $router = require_once __DIR__ . '/../app/routes/web.php';
    $router->dispatch();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
