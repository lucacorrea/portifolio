<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Csrf,Database,Audit};
use InvalidArgumentException;
final class UsersController {
 public static function index(): void {
  Auth::requireLogin();Auth::requireAdmin();$db=Database::pdo();
  if ($_SERVER['REQUEST_METHOD']==='POST') {
   Csrf::verify();$task=(string)($_POST['task']??'');$id=(int)($_POST['id']??0);
   try {
    if ($task==='create') {
     $name=trim((string)($_POST['name']??''));$email=strtolower(trim((string)($_POST['email']??'')));$role=(string)($_POST['role']??'consulta');
     if ($name===''||strlen($name)>150||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||!in_array($role,['admin','gestor','tecnico','consulta'],true))throw new InvalidArgumentException('Confira nome, e-mail e perfil.');
     $password=Auth::generatePassword();$q=$db->prepare('INSERT INTO users(name,email,password_hash,role,active,must_change_password) VALUES (?,?,?,?,1,1)');
     $q->execute([$name,$email,Auth::hash($password),$role]);Audit::log('user_create','users',(int)$db->lastInsertId(),['email'=>$email,'role'=>$role]);
     $_SESSION['new_user_password']=['email'=>$email,'password'=>$password];\flash('Usuário criado. Copie a senha temporária, exibida uma única vez.');\redirect('usuarios');
    }
    $q=$db->prepare('SELECT id,name,email,role,active FROM users WHERE id=?');$q->execute([$id]);$person=$q->fetch();if (!$person)throw new InvalidArgumentException('Usuário não encontrado.');
    if ($task==='reset') {
     $password=Auth::generatePassword();$db->prepare('UPDATE users SET password_hash=?,must_change_password=1,locked_until=NULL,failed_attempts=0 WHERE id=?')->execute([Auth::hash($password),$id]);
     Audit::log('user_password_reset','users',$id);$_SESSION['new_user_password']=['email'=>$person['email'],'password'=>$password];\flash('Senha temporária redefinida.');\redirect('usuarios');
    }
    if ($task==='toggle') {
     if ((int)Auth::id()===$id)throw new InvalidArgumentException('Você não pode desativar sua própria conta.');
     if ($person['role']==='admin' && (int)$person['active']===1){
      $admins=(int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1")->fetchColumn();
      if ($admins<=1)throw new InvalidArgumentException('Mantenha pelo menos um administrador ativo.');
     }
     $db->prepare('UPDATE users SET active=? WHERE id=?')->execute([(int)!$person['active'],$id]);Audit::log('user_toggle','users',$id,['active'=>(int)!$person['active']]);\flash('Situação do usuário alterada.');\redirect('usuarios');
    }
    throw new InvalidArgumentException('Ação inválida.');
   } catch(\PDOException $e){\flash('E-mail já cadastrado ou falha no banco.','error');\redirect('usuarios');}
   catch(InvalidArgumentException $e){\flash($e->getMessage(),'error');\redirect('usuarios');}
  }
  $users=$db->query('SELECT id,name,email,role,active,must_change_password,last_login_at,created_at FROM users ORDER BY id')->fetchAll();
  $credential=$_SESSION['new_user_password']??null;unset($_SESSION['new_user_password']);
  \render('users/index',compact('users','credential')+['pageTitle'=>'Usuários e permissões']);
 }
 public static function audit(): void {
  Auth::requireLogin();Auth::requireAdmin();
  $q=Database::pdo()->query('SELECT a.*,u.name AS actor FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 250');
  \render('users/audit',['logs'=>$q->fetchAll(),'pageTitle'=>'Trilha de auditoria']);
 }
}
