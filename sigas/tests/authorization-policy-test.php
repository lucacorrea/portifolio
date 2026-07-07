<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Domain\UserStatus;
use App\Models\AccessLevel;
use App\Models\User;
use App\Repositories\AccessLevelRepository;
use App\Repositories\UserRepository;
use App\Services\AuthorizationService;
use App\Services\PermissionService;
use App\Services\UserAdministrationPolicy;

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures++;
        echo "FAIL: {$message}" . PHP_EOL;
    }
}

function make_permission_service(): PermissionService
{
    $reflection = new ReflectionClass(PermissionService::class);
    /** @var PermissionService $service */
    $service = $reflection->newInstanceWithoutConstructor();
    $cache = $reflection->getProperty('cache');
    $cache->setAccessible(true);
    $cache->setValue($service, [
        1 => ['usuarios.aprovar', 'usuarios.promover_administrador', 'usuarios.alterar_nivel'],
        2 => ['usuarios.aprovar', 'usuarios.alterar_nivel'],
        6 => [],
    ]);

    return $service;
}

final class PolicyAccessLevelPdo extends PDO
{
    /** @param array<int, array<string, mixed>> $levels */
    public function __construct(private readonly array $levels)
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new PolicyAccessLevelStatement($this, $query);
    }

    /** @return array<string, mixed>|null */
    public function level(int $id): ?array
    {
        return $this->levels[$id] ?? null;
    }
}

final class PolicyAccessLevelStatement extends PDOStatement
{
    /** @var array<string, mixed> */
    private array $params = [];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    public function __construct(
        private readonly PolicyAccessLevelPdo $pdo,
        private readonly string $query,
    ) {
    }

    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            $this->params = $params;
        }

        $this->rows = [];

        if (str_contains($this->query, 'FROM niveis_acesso')) {
            $id = (int) ($this->params['id'] ?? 0);
            $level = $this->pdo->level($id);
            $this->rows = $level === null ? [] : [$level];
        }

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?: false;
    }
}

function access_level_row(int $id, string $name, string $slug, int $priority): array
{
    return [
        'id' => $id,
        'nome' => $name,
        'slug' => $slug,
        'descricao' => null,
        'prioridade' => $priority,
        'ativo' => 1,
        'criado_em' => '2026-01-01 00:00:00',
        'atualizado_em' => null,
    ];
}

function make_user(int $id, int $levelId, int $sectorId = 1): User
{
    return new User(
        $id,
        $sectorId,
        null,
        $levelId,
        'Usuario',
        '52998224725',
        null,
        null,
        'usuario@exemplo.gov.br',
        null,
        'hash',
        UserStatus::ACTIVE,
        false,
        0,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        1,
        new DateTimeImmutable(),
        null,
        null
    );
}

$authorization = new AuthorizationService(
    make_permission_service(),
    new AccessLevelRepository(new PolicyAccessLevelPdo([
        1 => access_level_row(1, 'Administrador', 'administrador', 10),
        2 => access_level_row(2, 'Suporte', 'suporte', 20),
        6 => access_level_row(6, 'Leitura', 'leitura', 60),
    ]))
);
$admin = make_user(1, 1);
$support = make_user(2, 2);
$adminLevel = new AccessLevel(1, 'Administrador', 'administrador', null, 10, true, new DateTimeImmutable(), null);
$supportLevel = new AccessLevel(2, 'Suporte', 'suporte', null, 20, true, new DateTimeImmutable(), null);
$readLevel = new AccessLevel(6, 'Leitura', 'leitura', null, 60, true, new DateTimeImmutable(), null);

assert_true($authorization->can($admin, 'usuarios.promover_administrador'), 'admin pode promover');
assert_true($authorization->canAccessOperationalSector($support, 1), 'suporte acessa setor operacional');
assert_true($authorization->canAssignLevel($support, $readLevel), 'suporte atribui leitura');
assert_true(!$authorization->canAssignLevel($support, $adminLevel), 'suporte nao atribui admin');
assert_true(!$authorization->canAssignLevel($support, $supportLevel), 'suporte nao atribui suporte');

$userRepoReflection = new ReflectionClass(UserRepository::class);
/** @var UserRepository $userRepository */
$userRepository = $userRepoReflection->newInstanceWithoutConstructor();
$policy = new UserAdministrationPolicy($authorization, $userRepository);
$selfChangeBlocked = false;

try {
    $policy->assertCanAssignLevel($support, $support, $readLevel);
} catch (Throwable) {
    $selfChangeBlocked = true;
}

assert_true($selfChangeBlocked, 'suporte nao altera proprio nivel');

echo $failures === 0 ? 'PASS authorization-policy-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
