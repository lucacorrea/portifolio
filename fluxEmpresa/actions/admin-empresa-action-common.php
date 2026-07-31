<?php
declare(strict_types=1);
use App\Core\Application; use App\Security\SessionManager;
if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) { http_response_code(404); exit; }
function admin_empresa_post():void{if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');exit;}}
/** @return array{0:Application,1:SessionManager,2:\App\Access\DTO\AuthenticatedUser} */
function admin_empresa_context():array{$app=require dirname(__DIR__).'/bootstrap.php';$session=$app['application']->session();$session->start();try{$user=$app['application']->authorization()->requireLogin();$app['application']->platformAdminPolicy()->requireAccess($user);$app['application']->csrf()->requireValid((string)($_POST['csrf_token']??''));return [$app['application'],$session,$user];}catch(\Throwable $e){error_log('Company admin access failed: '.get_class($e));$session->flash('danger','Não foi possível validar a operação.');header('Location: '.$app['application']->redirect()->applicationUrl('admin-empresas.php'),true,303);exit;}}
function admin_empresa_redirect(Application $app,string $target='admin-empresas.php'):never{header('Location: '.$app->redirect()->applicationUrl($target),true,303);exit;}
