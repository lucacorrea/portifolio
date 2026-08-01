<?php
declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;

if (!isset($application)) {
    $bootstrap = require dirname(__DIR__, 2) . '/bootstrap.php';
    $application = $bootstrap['application'];
}
$session = $application->session();
$session->start();
$csrf = $application->csrf();
$authorization = $application->authorization();
try {
    $currentUser = $authorization->requireLogin();
} catch (AuthenticationException) {
    $session->flash('warning', 'Sua sessão expirou. Entre novamente.');
    $requestPath = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? 'index.php', PHP_URL_PATH));
    $next = $application->redirect()->sanitize('adm/' . ($requestPath !== '' ? $requestPath : 'index.php'));
    header('Location: ' . $application->redirect()->loginUrl() . '?next=' . rawurlencode($next), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Administrative authentication failed: ' . get_class($exception));
    $session->flash('danger', 'Não foi possível validar sua sessão. Entre novamente.');
    header('Location: ' . $application->redirect()->loginUrl(), true, 303);
    exit;
}
if (!$currentUser->isPlatformAdministrator()) { header('Location: ' . $application->redirect()->applicationUrl('acesso-negado.php'), true, 303); exit; }
$activeCompany = $application->activeCompanyContext()->current();
if ($activeCompany !== null) {
    try {
        $application->operationalCompanyContext()->resolve($currentUser);
        $activeCompany = $application->activeCompanyContext()->current();
    } catch (AuthorizationException) {
        $activeCompany = null;
        $session->flash('info', 'O atendimento administrativo anterior foi encerrado por segurança.');
    } catch (Throwable $exception) {
        error_log('Could not validate administrative access: ' . get_class($exception));
        $activeCompany = null;
    }
}
function admin_url(string $path = 'index.php'): string { global $application; return $application->redirect()->applicationUrl('adm/' . ltrim($path, '/')); }
function app_url(string $path): string { global $application; $path = ltrim($path, '/'); if ($path === '' || str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) return $application->redirect()->applicationUrl('dashboard.php'); return rtrim(dirname($application->redirect()->applicationUrl('dashboard.php')), '/') . '/' . $path; }
function asset_url(string $path): string { global $application; $base = rtrim(dirname($application->redirect()->applicationUrl('dashboard.php')), '/'); return $base . '/' . ltrim($path, '/'); }
function admin_h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
