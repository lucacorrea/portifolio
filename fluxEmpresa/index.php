<?php
declare(strict_types=1);

use App\Core\Application;

$app = require __DIR__ . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$application->session()->start();

try {
    $currentUser = $application->authentication()->currentUser();
    $target = $currentUser === null
        ? 'login.php'
        : ($currentUser->isPlatformAdministrator() ? 'adm/index.php' : 'dashboard.php');
} catch (Throwable $exception) {
    $target = 'login.php';
}

header('Location: ' . $target, true, 303);
exit;
