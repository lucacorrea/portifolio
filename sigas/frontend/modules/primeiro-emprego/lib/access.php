<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 4) . '/bootstrap.php';

/** @return array{user:App\Models\User,authorization:AuthorizationService} */
function pe_access_app(): array
{
    static $context = null;

    if (is_array($context)) {
        return $context;
    }

    $pdo = Database::connection();
    $levels = new AccessLevelRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService(
        new UserRepository($pdo),
        new UserSessionRepository($pdo),
        $levels,
        $audit
    );
    $user = $auth->requireUser();
    $authorization = new AuthorizationService(
        new PermissionService(new PermissionRepository($pdo)),
        $levels
    );

    $context = [
        'user' => $user,
        'authorization' => $authorization,
    ];

    return $context;
}

function pe_can(string $permission): bool
{
    $app = pe_access_app();

    // Administrador e Suporte são níveis globais do SIGAS. Mantemos esse
    // comportamento também durante a implantação de novas permissões, evitando
    // indisponibilidade entre o deploy do código e a execução da migration.
    if (
        $app['authorization']->isAdministrator($app['user'])
        || $app['authorization']->isSupport($app['user'])
    ) {
        return true;
    }

    return $app['authorization']->can($app['user'], $permission);
}

/** @return array<string,string> */
function pe_page_permissions(): array
{
    return [
        'painel' => 'primeiro_emprego.visualizar',
        'candidatos' => 'primeiro_emprego.visualizar',
        'novo-candidato' => 'primeiro_emprego.cadastrar',
        'importar-candidatos' => 'primeiro_emprego.importar',
        'vagas' => 'primeiro_emprego.visualizar',
        'parceiros' => 'primeiro_emprego.visualizar',
        'lotacoes' => 'primeiro_emprego.visualizar',
        'encaminhamentos' => 'primeiro_emprego.visualizar',
        'documentacao' => 'primeiro_emprego.visualizar',
        'frequencia' => 'primeiro_emprego.visualizar',
        'bolsas' => 'primeiro_emprego.visualizar',
        'capacitacoes' => 'primeiro_emprego.visualizar',
        'acompanhamentos' => 'primeiro_emprego.visualizar',
        'relatorios' => 'primeiro_emprego.visualizar',
        'configuracoes' => 'primeiro_emprego.configuracoes_gerenciar',
    ];
}

function pe_page_permission(string $pageKey): ?string
{
    $permissions = pe_page_permissions();

    return $permissions[$pageKey] ?? null;
}

function pe_can_page(string $pageKey): bool
{
    $permission = pe_page_permission($pageKey);

    return $permission !== null && pe_can($permission);
}

/**
 * Permissão exigida antes de qualquer POST do módulo.
 * Retornar null significa que a página não deveria receber mutações.
 */
function pe_post_permission(string $pageKey, string $action = ''): ?string
{
    if ($pageKey === 'candidatos') {
        return $action === 'delete_candidate'
            ? 'primeiro_emprego.excluir'
            : 'primeiro_emprego.editar';
    }

    return match ($pageKey) {
        'novo-candidato' => 'primeiro_emprego.cadastrar',
        'importar-candidatos' => 'primeiro_emprego.importar',
        'vagas' => 'primeiro_emprego.vagas_gerenciar',
        'parceiros' => 'primeiro_emprego.parceiros_gerenciar',
        'lotacoes' => 'primeiro_emprego.lotacoes_gerenciar',
        'encaminhamentos' => 'primeiro_emprego.encaminhamentos_gerenciar',
        'documentacao' => 'primeiro_emprego.documentos_gerenciar',
        'frequencia' => 'primeiro_emprego.frequencia_gerenciar',
        'bolsas' => 'primeiro_emprego.bolsas_gerenciar',
        'capacitacoes' => 'primeiro_emprego.capacitacoes_gerenciar',
        'acompanhamentos' => 'primeiro_emprego.acompanhamentos_gerenciar',
        'configuracoes' => 'primeiro_emprego.configuracoes_gerenciar',
        default => null,
    };
}

/** @return list<string> */
function pe_visible_page_keys(): array
{
    $visible = [];

    foreach (pe_page_permissions() as $pageKey => $permission) {
        if (pe_can($permission)) {
            $visible[] = $pageKey;
        }
    }

    return $visible;
}

/** @return array<string,string> */
function pe_page_routes(): array
{
    return [
        'painel' => 'primeiro-emprego/index.php',
        'candidatos' => 'primeiro-emprego/candidatos.php',
        'novo-candidato' => 'primeiro-emprego/cadastro-candidato.php',
        'importar-candidatos' => 'primeiro-emprego/importar-candidatos.php',
        'vagas' => 'primeiro-emprego/vagas.php',
        'parceiros' => 'primeiro-emprego/parceiros.php',
        'lotacoes' => 'primeiro-emprego/lotacoes.php',
        'encaminhamentos' => 'primeiro-emprego/encaminhamentos.php',
        'documentacao' => 'primeiro-emprego/documentacao.php',
        'frequencia' => 'primeiro-emprego/frequencia.php',
        'bolsas' => 'primeiro-emprego/bolsas.php',
        'capacitacoes' => 'primeiro-emprego/capacitacoes.php',
        'acompanhamentos' => 'primeiro-emprego/acompanhamentos.php',
        'relatorios' => 'primeiro-emprego/relatorios.php',
        'configuracoes' => 'primeiro-emprego/configuracoes.php',
    ];
}

/** @return array<string,mixed> */
function pe_access_snapshot(string $pageKey): array
{
    $visiblePages = pe_visible_page_keys();
    $routes = pe_page_routes();
    $allowedRoutes = [];

    foreach ($visiblePages as $visiblePage) {
        if (isset($routes[$visiblePage])) {
            $allowedRoutes[] = $routes[$visiblePage];
        }
    }

    $currentActionPermission = pe_post_permission($pageKey, '__default__');

    return [
        'currentPage' => $pageKey,
        'visiblePages' => $visiblePages,
        'allRoutes' => array_values($routes),
        'allowedRoutes' => $allowedRoutes,
        'canCurrentAction' => $currentActionPermission !== null && pe_can($currentActionPermission),
        'canDeleteCandidate' => pe_can('primeiro_emprego.excluir'),
    ];
}
