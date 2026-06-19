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
$next = $redirect->sanitize($_POST['next'] ?? 'dashboard.php');

try {
    $application->csrf()->requireValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);

    $identifier = substr(trim((string) ($_POST['identifier'] ?? '')), 0, 150);
    $password = substr((string) ($_POST['password'] ?? ''), 0, 4096);

    $result = $application->authentication()->attempt($identifier, $password);
    if ($result->success()) {
        header('Location: ' . $next, true, 303);
        exit;
    }

    $session->flash('danger', $result->message());
} catch (Throwable $exception) {
    $session->flash('danger', 'Não foi possível concluir a operação. Tente novamente.');
}

header('Location: ../login.php?next=' . rawurlencode($next), true, 303);
exit;
