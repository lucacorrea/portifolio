<?php
declare(strict_types=1);
if (!isset($application)) {
    $bootstrap = require dirname(__DIR__, 2) . '/bootstrap.php';
    $application = $bootstrap['application'];
}
$session = $application->session();
$session->start();
$csrf = $application->csrf();
$authorization = $application->authorization();
$authorization->requireLogin();
$currentUser = $authorization->currentUser();
if (!$currentUser->isPlatformAdministrator()) { header('Location: ' . $application->redirect()->applicationUrl('acesso-negado.php')); exit; }
function admin_url(string $path = 'index.php'): string { global $application; return $application->redirect()->applicationUrl('adm/' . ltrim($path, '/')); }
function app_url(string $path): string { global $application; $path = ltrim($path, '/'); if ($path === '' || str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) return $application->redirect()->applicationUrl('dashboard.php'); return rtrim(dirname($application->redirect()->applicationUrl('dashboard.php')), '/') . '/' . $path; }
function asset_url(string $path): string { global $application; $base = rtrim(dirname($application->redirect()->applicationUrl('dashboard.php')), '/'); return $base . '/' . ltrim($path, '/'); }
function admin_h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
