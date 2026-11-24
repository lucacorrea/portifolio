<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/user_management.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') redirect_to('usuarios.php');
$u = require_login();
if (!can_manage_users($u)) { http_response_code(403); exit('Acesso restrito à administração.'); }
$id = $_POST['id'] ?? '';
$returnTo = 'cadastro-usuario.php' . ((is_string($id) && preg_match('/^[1-9][0-9]{0,15}$/D',$id)) ? '?id='.urlencode($id) : '');
try {
    verify_csrf();
    $newId = save_managed_user($u, $_POST);
    $_SESSION['users_notice'] = ($id === '' ? 'Usuário cadastrado' : 'Usuário atualizado') . ' com sucesso.';
    unset($_SESSION['user_form_error'],$_SESSION['user_form_draft']);
    redirect_to('usuarios.php');
} catch (HttpError $e) {
    $_SESSION['user_form_error'] = $e->getMessage();
} catch (Throwable $e) {
    $ref=bin2hex(random_bytes(4));
    error_log('[OBRAS360 USER SAVE] Ref '.$ref.': '.$e->getMessage());
    $_SESSION['user_form_error']='O cadastro não pôde ser salvo. Consulte o administrador (ref. '.$ref.').';
}
$_SESSION['user_form_draft'] = [
    'id' => is_scalar($id) ? (string)$id : '',
    'name' => is_string($_POST['name'] ?? null) ? substr($_POST['name'],0,600) : '',
    'username' => is_string($_POST['username'] ?? null) ? substr($_POST['username'],0,50) : '',
    'email' => is_string($_POST['email'] ?? null) ? substr($_POST['email'],0,190) : '',
    'role' => is_string($_POST['role'] ?? null) ? substr($_POST['role'],0,30) : '',
];
redirect_to($returnTo);
