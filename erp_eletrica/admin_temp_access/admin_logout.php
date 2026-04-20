<?php

declare(strict_types=1);

session_start();
unset($_SESSION['admin_auth'], $_SESSION['passkey_enroll_user_id'], $_SESSION['passkey_challenge'], $_SESSION['passkey_auth_challenge']);
header('Location: admin_login.php');
exit;
