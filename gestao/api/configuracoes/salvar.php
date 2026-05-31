<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';

use App\Controllers\SettingController;
use App\Core\Request;

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);

if (!is_array($payload)) {
    $payload = $_POST;
}

(new SettingController())->save(new Request(), $payload);
