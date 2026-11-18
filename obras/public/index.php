<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\{Auth,Csrf,Modules};
use App\Controllers\{AuthController,ModuleController,DashboardController,ReportsController,MapController,UsersController};
try {
 $route=(string)($_GET['r']??'');
 if ($route==='') $route=Auth::user() ? 'dashboard' : 'login';
 if ($route==='login'){AuthController::login();return;}
 if ($route==='apresentacao'){\render('shared/presentation',['pageTitle'=>'Apresentação institucional']);return;}
 Auth::requireLogin();
 if ($_SERVER['REQUEST_METHOD']==='POST' && $route==='logout'){AuthController::logout();return;}
 if (($user=Auth::user()) && (bool)$user['must_change_password'] && $route!=='profile') \redirect('profile');
 match($route) {
  'dashboard'=>DashboardController::index(),
  'planejamento'=>DashboardController::planning(),
  'relatorios'=>ReportsController::index(),
  'mapa'=>MapController::index(),
  'download'=>ModuleController::download(),
  'usuarios'=>UsersController::index(),
  'auditoria'=>UsersController::audit(),
  'profile'=>AuthController::profile(),
  default=>Modules::isValid($route)?ModuleController::index($route):\error_response(404,'Página não encontrada.')
 };
} catch(\PDOException $e) {
 error_log('OBRAS360 DB: '.$e->getMessage());
 \error_response(503,'Banco de dados indisponível. Confira a configuração e a instalação descritas no README.');
} catch(\Throwable $e) {
 error_log('OBRAS360: '.$e->getMessage());
 \error_response(500,'Ocorreu um erro interno. Consulte os logs do servidor.');
}
