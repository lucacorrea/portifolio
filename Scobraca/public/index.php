<?php
require_once dirname(__DIR__) . '/bootstrap/app.php';

if (empty($_SESSION['usuario'])) {
    redirect('/login.php');
}

if (($_SESSION['usuario']['tipo'] ?? '') === 'platform_admin') {
    redirect('/admin/dashboard.php');
}

redirect('/app/dashboard.php');
