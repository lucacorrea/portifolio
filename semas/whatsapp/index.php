<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/guard.php';

header('Location: ' . (whatsapp_auth_check() ? 'dashboard.php' : 'login.php'), true, 302);
exit;
