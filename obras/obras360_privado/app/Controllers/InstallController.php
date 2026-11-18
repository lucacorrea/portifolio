<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Csrf,Database,Env};
use App\Services\Installation;
/** Instalação assistida na Hostinger sem SSH. Autorização forte e uso único. */
final class InstallController {
 public static function index(): void {
  header('Cache-Control: no-store, private');
  $enabled=Env::get('INSTALL_ENABLED','false')==='true';
  $token=(string)Env::get('INSTALL_TOKEN','');
  if (!$enabled||strlen($token)<32) \error_response(404,'Instalador desativado.');
  if (Env::get('APP_ENV')==='production' && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') && (($_SERVER['SERVER_PORT']??'')!=='443')) {
   \error_response(403,'Instalação exige HTTPS.');
  }
  if (!extension_loaded('pdo_mysql') && Database::driver()==='mysql')
   \error_response(503,'Esta versão do PHP precisa ter pdo_mysql habilitado.');
  if (!defined('PASSWORD_ARGON2ID')) \error_response(503,'O PHP precisa de suporte a Argon2id.');
  if (($_GET['done']??'')==='1' && !empty($_SESSION['first_credentials'])) {self::page();return;}
  try {
   if (Installation::adminCount() > 0) \error_response(404,'O sistema já está instalado.');
  } catch (\PDOException $e) {error_log('OBRAS360 instalar db: '.get_class($e));\error_response(503,'Não foi possível conectar ao MySQL. Revise seu .env privado.');}
  if ($_SERVER['REQUEST_METHOD']==='POST') {
   Csrf::verify();
   if (($_SESSION['install_attempts']??0)>=6) \error_response(429,'Limite de tentativas da instalação excedido. Feche a sessão e revise a chave.');
   $_SESSION['install_attempts']=(int)($_SESSION['install_attempts']??0)+1;
   $given=(string)($_POST['install_token']??'');
   if (!hash_equals($token,$given)) {\flash('Chave de instalação incorreta.','error');\redirect('instalar');}
   $name=trim((string)($_POST['name']??''));
   $email=trim((string)($_POST['email']??''));
   try {
    Installation::runSchema();
    $password=Installation::createInitialAdmin($name,$email);
    if ($password===null) \error_response(403,'Já existe administrador cadastrado.');
    unset($_SESSION['install_attempts']);
    $_SESSION['first_credentials']=['email'=>$email,'password'=>$password];
    \redirect('instalar',['done'=>'1']);
   } catch (\InvalidArgumentException $e) {\flash($e->getMessage(),'error');\redirect('instalar');}
   catch (\Throwable $e) {error_log('OBRAS360 instalar: '.get_class($e).' '.(string)$e->getCode());\error_response(503,'Falha ao instalar. Confira permissões MySQL e esquema. Consulte os logs do PHP.');}
  }
  self::page();
 }
 private static function page(): void {
  // Credencial apenas na sessão HTTP em que o cadastro ocorreu. A rota é bloqueada depois de criar o usuário.
  $credential=$_SESSION['first_credentials']??null;
  unset($_SESSION['first_credentials']);
  $flash=\take_flash();
  $done=(string)($_GET['done']??'')==='1';
  $title=$done?'Instalação concluída':'Instalação inicial protegida';
  echo '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>OBRAS360 · '.\h($title).'</title><link rel="stylesheet" href="'.\h(\asset_url('css/app.css')).'"></head><body class="auth-body"><main style="min-height:100vh;display:grid;place-items:center;padding:24px"><section class="auth-card" style="width:min(100%,640px)"><span class="eyebrow">OBRAS360 · HOSTINGER</span><h1>'.\h($title).'</h1>';
  if ($flash)echo '<p class="notice notice-error">'.\h($flash['text']).'</p>';
  if ($done) {
   if ($credential)echo '<p><b>Administrador:</b> '.\h($credential['email']).'</p><p><b>Senha temporária (copie agora):</b> <code style="overflow-wrap:anywhere">'.\h($credential['password']).'</code></p>';
   echo '<p><b>Desative agora INSTALL_ENABLED=false e apague INSTALL_TOKEN no .env privado.</b> No primeiro login altere a senha temporária.</p><a class="btn btn-primary" href="'.\h(\url('login')).'">Abrir login</a>';
  } else {
   echo '<p>Esta página só funciona com chave privada definida por você no .env e quando não há administrador.</p><form class="form-stack" method="post" action="'.\h(\url('instalar')).'">'.\csrf_field().'<label class="field"><span>Chave de instalação</span><input type="password" name="install_token" required minlength="32" autocomplete="off"></label><label class="field"><span>Nome do administrador</span><input name="name" required maxlength="150" autocomplete="name"></label><label class="field"><span>E-mail institucional</span><input type="email" name="email" required maxlength="190" autocomplete="email"></label><button class="btn btn-primary btn-block" type="submit">Criar tabelas e administrador</button></form>';
  }
  echo '</section></main></body></html>';
 }
}
