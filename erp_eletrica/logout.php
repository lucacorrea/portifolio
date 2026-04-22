<?php
require_once 'config.php';
$authService = new \App\Services\AuthService();
$authService->logout();
header("Location: login.php?msg=Logout realizado com sucesso");
exit;
?>
