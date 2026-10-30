<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/materials.php';
require_once __DIR__ . '/catalog.php';

try {
    $path = route_path();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') {
        verify_csrf();
    }

    if ($path === '/' && $method === 'GET') catalog_index();
    elseif ($path === '/entrar' && $method === 'GET') auth_login_form();
    elseif ($path === '/entrar' && $method === 'POST') auth_login();
    elseif ($path === '/cadastro' && $method === 'GET') auth_register_form();
    elseif ($path === '/cadastro' && $method === 'POST') auth_register();
    elseif ($path === '/sair' && $method === 'POST') auth_logout();
    elseif ($path === '/painel' && $method === 'GET') dashboard();
    elseif ($path === '/admin' && $method === 'GET') admin_index();
    elseif ($path === '/admin/usuarios' && $method === 'POST') admin_user();
    elseif ($path === '/admin/instituicoes' && $method === 'POST') admin_institution();
    elseif ($path === '/admin/disciplinas' && $method === 'POST') admin_subject();
    elseif ($path === '/admin/habilidades' && $method === 'POST') admin_skill();
    elseif ($path === '/professor/materiais' && $method === 'GET') materials_index();
    elseif ($path === '/professor/materiais/novo' && $method === 'GET') materials_form();
    elseif ($path === '/professor/materiais' && $method === 'POST') materials_save();
    elseif (preg_match('~^/professor/materiais/([0-9]+)/editar$~', $path, $m) && $method === 'GET') materials_form((int)$m[1]);
    elseif (preg_match('~^/professor/materiais/([0-9]+)/editar$~', $path, $m) && $method === 'POST') materials_save((int)$m[1]);
    elseif (preg_match('~^/professor/materiais/([0-9]+)/excluir$~', $path, $m) && $method === 'POST') materials_delete((int)$m[1]);
    elseif (preg_match('~^/materiais/([0-9]+)$~', $path, $m) && $method === 'GET') catalog_show((int)$m[1]);
    elseif (preg_match('~^/materiais/([0-9]+)/midia/(arquivo|capa)$~', $path, $m) && $method === 'GET') catalog_media((int)$m[1], $m[2]);
    else abort_page(404);
} catch (PDOException $e) {
    error_log('[PCCT] Database: ' . $e->getMessage());
    abort_page(500, 'Erro no banco. Confira config/local.php e a importação do SQL.');
} catch (Throwable $e) {
    error_log('[PCCT] ' . $e->getMessage());
    abort_page(500, 'Não foi possível concluir a operação. Confira o log do PHP.');
}
