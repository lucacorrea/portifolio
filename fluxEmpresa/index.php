<?php
declare(strict_types=1);

use App\Core\Application;

$app = require __DIR__ . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$application->session()->start();

try {
    $target = $application->authentication()->isAuthenticated() ? 'dashboard.php' : 'login.php';
} catch (Throwable $exception) {
    $target = 'login.php';
}

header('Location: ' . $target, true, 303);
exit;
