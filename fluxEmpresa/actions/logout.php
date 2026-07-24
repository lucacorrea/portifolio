<?php
declare(strict_types=1);

use App\Core\Application;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

$app = require dirname(__DIR__) . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$session = $application->session();
$session->start();
$redirect = $application->redirect();

try {
    $application->csrf()->requireValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);
} catch (Throwable $exception) {
    http_response_code(403);
    exit;
}

try {
    $application->authentication()->requireAuthenticatedUser();
    $application->authentication()->logout();
} catch (Throwable $exception) {
    header('Location: ' . $redirect->loginUrl(), true, 303);
    exit;
}

$session->start();
$session->flash('info', 'Você saiu do sistema.');
header('Location: ' . $redirect->loginUrl(), true, 303);
exit;
