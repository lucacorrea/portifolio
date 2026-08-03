<?php
declare(strict_types=1);
use Sigesp\Modules\Auth\Controllers\AuthController;
use Sigesp\Modules\Atletas\Controllers\AtletaController;
use Sigesp\Modules\Dashboard\Controllers\DashboardController;

$router = $app->router();
$router->get('/', static fn () => \Sigesp\Core\Response::redirect('/dashboard'));
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/recuperar-senha', [AuthController::class, 'forgot']);
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/atletas', [AtletaController::class, 'index']);
$router->get('/atletas/novo', [AtletaController::class, 'create']);
$router->post('/atletas', [AtletaController::class, 'store']);
$router->get('/atletas/{id}', [AtletaController::class, 'show']);
$futureModules = ['responsaveis'=>'Responsáveis','documentos'=>'Documentos','modalidades'=>'Modalidades','categorias'=>'Categorias','equipes'=>'Equipes','treinos'=>'Treinos','frequencias'=>'Frequência','avaliacoes'=>'Avaliações','eventos'=>'Eventos','competicoes'=>'Competições','inscricoes'=>'Inscrições','resultados'=>'Resultados','beneficios'=>'Benefícios','espacos-esportivos'=>'Espaços esportivos','reservas'=>'Reservas','materiais'=>'Materiais','relatorios'=>'Relatórios','usuarios'=>'Usuários','auditoria'=>'Auditoria','configuracoes'=>'Configurações'];
foreach ($futureModules as $path => $label) $router->get('/' . $path, static fn () => new \Sigesp\Core\Response(\Sigesp\Core\View::render('shared/module', ['title' => $label, 'label' => $label])));
