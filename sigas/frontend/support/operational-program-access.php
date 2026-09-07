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

/**
 * Camada compartilhada de autorização para os módulos operacionais que ainda
 * não possuem um backend próprio de domínio. O objetivo é manter a mesma regra
 * efetiva usada pelo restante do SIGAS: nível + setor + exceções individuais.
 *
 * @return array{user:App\Models\User,authorization:AuthorizationService}
 */
function sigas_operational_access_app(): array
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

function sigas_operational_can(string $permission): bool
{
    $app = sigas_operational_access_app();

    if (
        $app['authorization']->isAdministrator($app['user'])
        || $app['authorization']->isSupport($app['user'])
    ) {
        return true;
    }

    return $app['authorization']->can($app['user'], $permission);
}

/**
 * @return array<string,array{
 *   view:string,
 *   pages:array<string,string>,
 *   mutation:array<string,string>
 * }>
 */
function sigas_operational_module_permissions(): array
{
    return [
        'kit-maternidade' => [
            'view' => 'kit_maternidade.visualizar',
            'pages' => [
                'painel' => 'kit_maternidade.visualizar',
                'beneficiarias' => 'kit_maternidade.visualizar',
                'cadastro' => 'kit_maternidade.cadastrar',
                'visitas' => 'kit_maternidade.acompanhar',
                'reunioes' => 'kit_maternidade.acompanhar',
                'avaliacao' => 'kit_maternidade.avaliar',
                'entregas' => 'kit_maternidade.entregar',
                'pos-parto' => 'kit_maternidade.acompanhar',
                'relatorios' => 'kit_maternidade.visualizar',
            ],
            'mutation' => [
                'cadastro' => 'kit_maternidade.cadastrar',
                'visitas' => 'kit_maternidade.acompanhar',
                'reunioes' => 'kit_maternidade.acompanhar',
                'avaliacao' => 'kit_maternidade.avaliar',
                'entregas' => 'kit_maternidade.entregar',
                'pos-parto' => 'kit_maternidade.acompanhar',
            ],
        ],
        'aluguel-social' => [
            'view' => 'aluguel_social.visualizar',
            'pages' => [
                'painel' => 'aluguel_social.visualizar',
                'beneficiarios' => 'aluguel_social.visualizar',
                'solicitacoes' => 'aluguel_social.gerenciar',
                'vistorias' => 'aluguel_social.gerenciar',
                'pareceres' => 'aluguel_social.gerenciar',
                'concessoes' => 'aluguel_social.gerenciar',
                'pagamentos' => 'aluguel_social.pagamentos',
                'reavaliacoes' => 'aluguel_social.gerenciar',
                'relatorios' => 'aluguel_social.visualizar',
            ],
            'mutation' => [
                'solicitacoes' => 'aluguel_social.gerenciar',
                'vistorias' => 'aluguel_social.gerenciar',
                'pareceres' => 'aluguel_social.gerenciar',
                'concessoes' => 'aluguel_social.gerenciar',
                'pagamentos' => 'aluguel_social.pagamentos',
                'reavaliacoes' => 'aluguel_social.gerenciar',
            ],
        ],
        'beneficios-eventuais' => [
            'view' => 'beneficios_eventuais.visualizar',
            'pages' => [
                'painel' => 'beneficios_eventuais.visualizar',
                'solicitacoes' => 'beneficios_eventuais.gerenciar',
                'triagem' => 'beneficios_eventuais.gerenciar',
                'analises' => 'beneficios_eventuais.gerenciar',
                'concessoes' => 'beneficios_eventuais.gerenciar',
                'entregas' => 'beneficios_eventuais.entregar',
                'tipos' => 'beneficios_eventuais.gerenciar',
                'relatorios' => 'beneficios_eventuais.visualizar',
            ],
            'mutation' => [
                'solicitacoes' => 'beneficios_eventuais.gerenciar',
                'triagem' => 'beneficios_eventuais.gerenciar',
                'analises' => 'beneficios_eventuais.gerenciar',
                'concessoes' => 'beneficios_eventuais.gerenciar',
                'entregas' => 'beneficios_eventuais.entregar',
                'tipos' => 'beneficios_eventuais.gerenciar',
            ],
        ],
    ];
}

/** @return array{view:string,pages:array<string,string>,mutation:array<string,string>} */
function sigas_operational_module_access_definition(string $moduleKey): array
{
    $all = sigas_operational_module_permissions();

    if (!isset($all[$moduleKey])) {
        throw new RuntimeException('O módulo informado não possui matriz operacional de permissões.');
    }

    return $all[$moduleKey];
}

function sigas_operational_can_page(string $moduleKey, string $pageKey): bool
{
    $definition = sigas_operational_module_access_definition($moduleKey);
    $permission = $definition['pages'][$pageKey] ?? null;

    return is_string($permission) && $permission !== '' && sigas_operational_can($permission);
}

function sigas_operational_can_mutate(string $moduleKey, string $pageKey): bool
{
    $definition = sigas_operational_module_access_definition($moduleKey);
    $permission = $definition['mutation'][$pageKey] ?? null;

    return is_string($permission) && $permission !== '' && sigas_operational_can($permission);
}

/** @return list<string> */
function sigas_operational_visible_pages(string $moduleKey): array
{
    $definition = sigas_operational_module_access_definition($moduleKey);
    $visible = [];

    foreach ($definition['pages'] as $pageKey => $permission) {
        if (sigas_operational_can($permission)) {
            $visible[] = $pageKey;
        }
    }

    return $visible;
}

/**
 * Remove ações de cabeçalho que levam para páginas que o usuário não pode abrir.
 * Botões sem href são removidos quando a página atual não admite mutação.
 *
 * @param array<string,mixed> $pageDefinition
 * @return array<string,mixed>
 */
function sigas_operational_filter_page_definition(
    string $moduleKey,
    string $pageKey,
    array $pageDefinition
): array {
    $pageDefinition['show_states'] = false;

    if (!isset($pageDefinition['actions']) || !is_array($pageDefinition['actions'])) {
        return $pageDefinition;
    }

    $visiblePages = array_fill_keys(sigas_operational_visible_pages($moduleKey), true);
    $canMutateCurrentPage = sigas_operational_can_mutate($moduleKey, $pageKey);

    $pageDefinition['actions'] = array_values(array_filter(
        $pageDefinition['actions'],
        static function ($action) use ($moduleKey, $visiblePages, $canMutateCurrentPage): bool {
            if (!is_array($action)) {
                return false;
            }

            $href = trim((string) ($action['href'] ?? ''));
            if ($href === '') {
                return $canMutateCurrentPage;
            }

            $query = parse_url($href, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
                $targetModule = trim((string) ($params['ambiente'] ?? ''));
                $targetPage = trim((string) ($params['pagina'] ?? ''));

                if ($targetModule === $moduleKey && $targetPage !== '') {
                    return isset($visiblePages[$targetPage]);
                }
            }

            return true;
        }
    ));

    return $pageDefinition;
}
