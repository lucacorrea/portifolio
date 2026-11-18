<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Audit,Csrf,Database};
use InvalidArgumentException;
final class AuthController {
 public static function login(): void {
  if (Auth::user()) \redirect('dashboard');
  if ($_SERVER['REQUEST_METHOD']==='POST'){
   Csrf::verify();$email=(string)($_POST['email']??'');$password=(string)($_POST['password']??'');
   try {if (Auth::attempt($email,$password)) {\redirect('dashboard');}}
   catch(\Throwable $e){error_log('Falha de login: '.$e->getMessage());}
   usleep(130000);\flash('Não foi possível entrar. Confira as credenciais ou aguarde o desbloqueio da conta.','error');\redirect('login');
  }
  \render('auth/login',['pageTitle'=>'Entrar']);
 }
 public static function logout(): never {Csrf::verify();Auth::logout();\redirect('login');}
 public static function profile(): void {
  Auth::requireLogin();$user=Auth::user();
  if ($_SERVER['REQUEST_METHOD']==='POST'){
   Csrf::verify();$old=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
   $q=Database::pdo()->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$user['id']]);$hash=(string)$q->fetchColumn();
   if (!Auth::verify($old,$hash))\flash('Senha atual incorreta.','error');
   elseif ($new!==$confirm)\flash('A confirmação da nova senha não corresponde.','error');
   elseif (!Auth::validPassword($new))\flash('Use senha de 15 a 256 caracteres, evitando combinações triviais.','error');
   elseif (Auth::verify($new,$hash))\flash('Escolha uma senha diferente da atual.','error');
   else {Auth::changePassword((int)$user['id'],$new);\flash('Senha alterada e sessão renovada com sucesso.');\redirect('dashboard');}
   \redirect('profile');
  }
  \render('auth/profile',['pageTitle'=>'Meu perfil']);
 }
}
