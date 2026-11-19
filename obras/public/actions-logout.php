<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
try { verify_csrf(); if (user()) audit_log('auth.logout'); } catch (Throwable $e) { http_response_code(419); exit('Requisição inválida.'); }
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires'=>time()-3600, 'path'=>$params['path'], 'secure'=>$params['secure'], 'httponly'=>true, 'samesite'=>'Lax']);
}
session_destroy();
redirect_to('acesso.php');
