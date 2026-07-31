<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin-guard.php';
function admin_post(): void { global $csrf; if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; } $csrf->requireValid($_POST['csrf_token'] ?? null); }
function admin_action_redirect(string $target): never { global $application; header('Location: ' . $application->redirect()->applicationUrl($target)); exit; }
function admin_action_error(\Throwable $exception, string $target): never { global $session; error_log('Administrative action failed: ' . get_class($exception)); $session->flash('danger', 'Não foi possível concluir a operação.'); admin_action_redirect($target); }
