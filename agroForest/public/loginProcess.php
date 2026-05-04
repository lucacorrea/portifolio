<?php
require dirname(__DIR__) . '/app/bootstrap.php';

$controller = new AuthController();
$controller->processarLogin();
