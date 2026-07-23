<?php

declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$app = require dirname(__DIR__) . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$session = $application->session();
$session->start();
$competence = trim((string) ($_POST['competencia'] ?? ''));
$redirectCompetence = preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/', $competence) === 1
    ? $competence
    : date('Y-m');
$redirect = static function () use ($application, $redirectCompetence): never {
    $target = action_return_target(
        $application,
        'relatorios.php?competencia=' . rawurlencode($redirectCompetence)
    );
    header('Location: ' . $application->redirect()->applicationUrl($target), true, 303);
    exit;
};

try {
    $application->csrf()->requireValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);
    $authorization = $application->authorization();
    $user = $authorization->requireLogin();
    $authorization->requirePermission('relatorio.meta_comissao.configurar');
} catch (AuthenticationException $exception) {
    $session->flash('warning', 'Sua sessão expirou. Entre novamente.');
    header('Location: ' . $application->redirect()->loginUrl(), true, 303);
    exit;
} catch (AuthorizationException $exception) {
    header('Location: ' . $application->redirect()->applicationUrl('acesso-negado.php'), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Report goal access validation failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível validar a operação. Atualize a página e tente novamente.');
    $redirect();
}

try {
    $application->reports()->saveMonthlyGoal($_POST, $user->id());
    $session->flash('success', 'Meta de ' . $redirectCompetence . ' configurada com sucesso.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Monthly report goal save failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível salvar a meta. Tente novamente.');
}

$redirect();
