<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/security/auth.php';

logoutUser();

header('Location: login.php');
exit;
