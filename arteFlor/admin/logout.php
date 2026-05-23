<?php
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . site_url('admin/dashboard.php'));
    exit;
}

if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Requisição inválida.');
}

admin_logout();

header('Location: ' . site_url('admin/login.php') . '?logged_out=1');
exit;
