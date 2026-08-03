<?php
declare(strict_types=1);
use Sigesp\Modules\Auth\Controllers\AuthController;
use Sigesp\Modules\Atletas\Controllers\AtletaController;
use Sigesp\Modules\Dashboard\Controllers\DashboardController;
use Sigesp\Modules\Shared\Controllers\ModuleController;

$router = $app->router();
$router->get('/', static fn () => \Sigesp\Core\Response::redirect('/dashboard'));
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/recuperar-senha', [AuthController::class, 'forgot']);
$router->get('/redefinir-senha', static fn () => new \Sigesp\Core\Response(\Sigesp\Core\View::render('auth/reset', [], 'layouts/auth')));
$router->get('/senha-alterada', static fn () => new \Sigesp\Core\Response(\Sigesp\Core\View::render('auth/senha-alterada', [], 'layouts/auth')));
$router->get('/sessao-expirada', static fn () => new \Sigesp\Core\Response(\Sigesp\Core\View::render('auth/sessao-expirada', [], 'layouts/auth')));
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/atletas', [AtletaController::class, 'index']);
$router->get('/atletas/novo', [AtletaController::class, 'create']);
$router->post('/atletas', [AtletaController::class, 'store']);
$router->get('/atletas/{id}', [AtletaController::class, 'show']);
$moduleScreens = [
    'responsaveis'=>['index','novo','perfil'], 'documentos'=>['index','analise','tipos','perfil'],
    'modalidades'=>['index','novo','perfil'], 'categorias'=>['index','novo'], 'equipes'=>['index','novo','perfil'],
    'treinos'=>['index','novo','perfil'], 'frequencias'=>['index','registrar','relatorio'], 'avaliacoes'=>['index','novo','perfil'],
    'eventos'=>['index','novo','perfil'], 'competicoes'=>['index','perfil'], 'inscricoes'=>['index','novo'],
    'resultados'=>['index','novo'], 'beneficios'=>['index','novo','perfil'], 'espacos-esportivos'=>['index','novo','perfil'],
    'reservas'=>['index','calendario','novo'], 'materiais'=>['index','novo','perfil','movimentacoes'],
    'relatorios'=>['index','visualizar'], 'usuarios'=>['index','novo','perfil'], 'permissoes'=>['index'],
    'auditoria'=>['index','perfil'], 'configuracoes'=>['index'],
];
foreach ($moduleScreens as $module => $screens) {
    foreach ($screens as $screen) {
        $path = '/' . $module . ($screen === 'index' ? '' : '/' . $screen);
        $router->get($path, static fn (\Sigesp\Core\Request $request) => (new ModuleController())->page($request, $module, $screen));
    }
}
