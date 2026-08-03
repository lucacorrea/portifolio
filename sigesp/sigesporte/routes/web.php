<?php
declare(strict_types=1);

use Sigesp\Core\{Request, Response, View};
use Sigesp\Demo\Controllers\{DemoAtletaController, DemoAuthController, DemoDashboardController, DemoModuleController};
use Sigesp\Modules\Auth\Controllers\AuthController;
use Sigesp\Modules\Atletas\Controllers\AtletaController;
use Sigesp\Modules\Dashboard\Controllers\DashboardController;
use Sigesp\Modules\Shared\Controllers\ModuleController;

$router = $app->router();
$router->get('/', static fn () => Response::redirect('/dashboard'));

if ($app->isDemoMode()) {
    $router->get('/login', [DemoAuthController::class, 'loginForm']);
    $router->post('/login', [DemoAuthController::class, 'login']);
    $router->post('/logout', [DemoAuthController::class, 'logout']);
    $router->get('/recuperar-senha', [DemoAuthController::class, 'forgot']);
    $router->get('/redefinir-senha', static fn () => new Response(View::render('auth/reset', ['demoMode' => true], 'layouts/auth')));
    $router->get('/senha-alterada', static fn () => new Response(View::render('auth/senha-alterada', ['demoMode' => true], 'layouts/auth')));
    $router->get('/sessao-expirada', static fn () => new Response(View::render('auth/sessao-expirada', ['demoMode' => true], 'layouts/auth')));
    $router->get('/dashboard', [DemoDashboardController::class, 'index']);

    $router->get('/atletas', [DemoAtletaController::class, 'index']);
    $router->get('/atletas/novo', [DemoAtletaController::class, 'create']);
    $router->post('/atletas', [DemoAtletaController::class, 'store']);
    $router->get('/atletas/{id}/editar', [DemoAtletaController::class, 'edit']);
    $router->post('/atletas/{id}', [DemoAtletaController::class, 'update']);
    $router->get('/atletas/{id}/documentos', [DemoAtletaController::class, 'documents']);
    $router->get('/atletas/{id}/carteira', [DemoAtletaController::class, 'wallet']);
    $router->get('/atletas/{id}', [DemoAtletaController::class, 'show']);

    $moduleScreens = [
        'responsaveis' => ['index', 'novo', 'perfil'],
        'documentos' => ['index', 'analise', 'tipos', 'perfil'],
        'modalidades' => ['index', 'novo', 'perfil'],
        'categorias' => ['index', 'novo'],
        'equipes' => ['index', 'novo', 'perfil'],
        'treinos' => ['index', 'novo', 'perfil'],
        'frequencias' => ['index', 'registrar', 'relatorio'],
        'avaliacoes' => ['index', 'novo', 'perfil'],
        'eventos' => ['index', 'novo', 'perfil'],
        'competicoes' => ['index', 'novo', 'perfil'],
        'inscricoes' => ['index', 'novo'],
        'resultados' => ['index', 'novo'],
        'beneficios' => ['index', 'novo', 'perfil'],
        'espacos-esportivos' => ['index', 'novo', 'perfil'],
        'reservas' => ['index', 'calendario', 'novo'],
        'materiais' => ['index', 'novo', 'perfil', 'movimentacoes'],
        'relatorios' => ['index', 'visualizar'],
        'usuarios' => ['index', 'novo', 'perfil'],
        'permissoes' => ['index'],
        'auditoria' => ['index', 'perfil'],
        'configuracoes' => ['index'],
    ];
    foreach ($moduleScreens as $module => $screens) {
        foreach ($screens as $screen) {
            $path = '/' . $module . ($screen === 'index' ? '' : '/' . $screen);
            $router->get($path, static fn (Request $request) => (new DemoModuleController())->page($request, $module, $screen));
        }
        $router->post('/' . $module, static fn (Request $request) => (new DemoModuleController())->simulate($request, $module));
    }
    $router->get('/carteiras', static fn (Request $request) => (new DemoModuleController())->page($request, 'carteiras-digitais'));
    $router->get('/carteiras-digitais', static fn (Request $request) => (new DemoModuleController())->page($request, 'carteiras-digitais'));
} else {
    $router->get('/login', [AuthController::class, 'loginForm']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->get('/recuperar-senha', [AuthController::class, 'forgot']);
    $router->get('/redefinir-senha', static fn () => new Response(View::render('auth/reset', [], 'layouts/auth')));
    $router->get('/senha-alterada', static fn () => new Response(View::render('auth/senha-alterada', [], 'layouts/auth')));
    $router->get('/sessao-expirada', static fn () => new Response(View::render('auth/sessao-expirada', [], 'layouts/auth')));
    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->get('/atletas', [AtletaController::class, 'index']);
    $router->get('/atletas/novo', [AtletaController::class, 'create']);
    $router->post('/atletas', [AtletaController::class, 'store']);
    $router->get('/atletas/{id}', [AtletaController::class, 'show']);
    $moduleScreens = [
        'responsaveis' => ['index', 'novo', 'perfil'], 'documentos' => ['index', 'analise', 'tipos', 'perfil'],
        'modalidades' => ['index', 'novo', 'perfil'], 'categorias' => ['index', 'novo'], 'equipes' => ['index', 'novo', 'perfil'],
        'treinos' => ['index', 'novo', 'perfil'], 'frequencias' => ['index', 'registrar', 'relatorio'], 'avaliacoes' => ['index', 'novo', 'perfil'],
        'eventos' => ['index', 'novo', 'perfil'], 'competicoes' => ['index', 'perfil'], 'inscricoes' => ['index', 'novo'],
        'resultados' => ['index', 'novo'], 'beneficios' => ['index', 'novo', 'perfil'], 'espacos-esportivos' => ['index', 'novo', 'perfil'],
        'reservas' => ['index', 'calendario', 'novo'], 'materiais' => ['index', 'novo', 'perfil', 'movimentacoes'],
        'relatorios' => ['index', 'visualizar'], 'usuarios' => ['index', 'novo', 'perfil'], 'permissoes' => ['index'],
        'auditoria' => ['index', 'perfil'], 'configuracoes' => ['index'],
    ];
    foreach ($moduleScreens as $module => $screens) {
        foreach ($screens as $screen) {
            $path = '/' . $module . ($screen === 'index' ? '' : '/' . $screen);
            $router->get($path, static fn (Request $request) => (new ModuleController())->page($request, $module, $screen));
        }
    }
}
