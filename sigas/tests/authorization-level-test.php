<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Domain\UserStatus;
use App\Exceptions\AuthorizationException;
use App\Models\AccessLevel;
use App\Models\User;
use App\Repositories\AccessLevelRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Services\AuthorizationService;
use App\Services\PermissionService;
use App\Services\UserAdministrationPolicy;

$failures = [];

function fail(string $message): void
{
    global $failures;
    $failures[] = $message;
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fail($message);
    }
}

function assert_throws(callable $callback, string $message): void
{
    try {
        $callback();
        fail($message);
    } catch (AuthorizationException) {
    }
}

final class MemoryPdo extends PDO
{
    /** @param array<int, array<string, mixed>> $levels */
    public function __construct(
        public array $levels,
        public array $permissionsByLevel,
        private readonly int $activeAdministrators = 1,
    ) {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new MemoryStatement($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $statement = new MemoryStatement($this, $query);
        $statement->execute();

        return $statement;
    }

    public function activeAdministrators(): int
    {
        return $this->activeAdministrators;
    }
}

final class MemoryStatement extends PDOStatement
{
    /** @var array<string, mixed> */
    private array $params = [];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var list<mixed> */
    private array $columns = [];

    public function __construct(
        private readonly MemoryPdo $pdo,
        private readonly string $query,
    ) {
    }

    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            $this->params = $params;
        }

        $this->rows = [];
        $this->columns = [];

        if (str_contains($this->query, 'FROM niveis_acesso')) {
            $id = (int) ($this->params['id'] ?? 0);
            $level = $this->pdo->levels[$id] ?? null;
            $this->rows = $level === null ? [] : [$level];
            return true;
        }

        if (str_contains($this->query, 'FROM permissoes p')) {
            $levelId = (int) ($this->params['level_id'] ?? 0);
            $permissions = $this->pdo->permissionsByLevel[$levelId] ?? [];

            foreach ($permissions as $index => $slug) {
                $this->rows[] = [
                    'id' => $index + 1,
                    'nome' => $slug,
                    'slug' => $slug,
                    'descricao' => null,
                    'modulo' => 'usuarios',
                    'ativo' => 1,
                    'criado_em' => '2026-01-01 00:00:00',
                ];
            }

            return true;
        }

        if (str_contains($this->query, 'COUNT(*)')
            && str_contains($this->query, 'FROM usuarios u')
            && str_contains($this->query, "n.slug = 'administrador'")) {
            $this->columns = [$this->pdo->activeAdministrators()];
            return true;
        }

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?: false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->columns[$column] ?? false;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->params[ltrim((string) $param, ':')] = $value;

        return true;
    }
}

function level_row(int $id, string $slug, bool $active = true): array
{
    return [
        'id' => $id,
        'nome' => ucfirst($slug),
        'slug' => $slug,
        'descricao' => null,
        'prioridade' => $id * 10,
        'ativo' => $active ? 1 : 0,
        'criado_em' => '2026-01-01 00:00:00',
        'atualizado_em' => null,
    ];
}

function make_user(int $id, ?int $levelId, ?int $sectorId = 10, UserStatus $status = UserStatus::ACTIVE): User
{
    return new User(
        $id,
        $sectorId,
        null,
        $levelId,
        'Usuario ' . $id,
        '52998224725',
        null,
        null,
        'usuario' . $id . '@exemplo.gov.br',
        null,
        'hash',
        $status,
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
        new DateTimeImmutable('2026-01-01 00:00:00'),
        null,
        null
    );
}

function make_authorization(array $permissionsByLevel, ?array $levelOverrides = null, int $activeAdministrators = 1): AuthorizationService
{
    $levels = $levelOverrides ?? [
        1 => level_row(1, 'administrador'),
        2 => level_row(2, 'suporte'),
        3 => level_row(3, 'gestor'),
        4 => level_row(4, 'personalizado'),
        5 => level_row(5, 'tecnico'),
        6 => level_row(6, 'administrador', false),
    ];

    $pdo = new MemoryPdo($levels, $permissionsByLevel, $activeAdministrators);

    return new AuthorizationService(
        new PermissionService(new PermissionRepository($pdo)),
        new AccessLevelRepository($pdo)
    );
}

function policy_for(AuthorizationService $authorization, int $activeAdministrators = 1): UserAdministrationPolicy
{
    $pdo = new MemoryPdo([], [], $activeAdministrators);

    return new UserAdministrationPolicy($authorization, new UserRepository($pdo));
}

$basePermissions = [
    1 => ['usuarios.alterar_nivel', 'usuarios.promover_administrador', 'usuarios.bloquear', 'usuarios.editar'],
    2 => ['usuarios.alterar_nivel', 'usuarios.bloquear', 'usuarios.editar', 'prontuarios.editar'],
    3 => [],
    4 => ['usuarios.promover_administrador', 'usuarios.aprovar'],
    5 => [],
    6 => ['usuarios.promover_administrador'],
];

$authorization = make_authorization($basePermissions);
$admin = make_user(1, 1, 10);
$support = make_user(2, 2, 20);
$manager = make_user(3, 3, 30);
$custom = make_user(4, 4, 40);
$withoutLevel = make_user(5, null, 50);
$inactiveAdminLevelUser = make_user(6, 6, 60);

$adminLevel = new AccessLevel(1, 'Administrador', 'administrador', null, 10, true, new DateTimeImmutable('2026-01-01'), null);
$supportLevel = new AccessLevel(2, 'Suporte', 'suporte', null, 20, true, new DateTimeImmutable('2026-01-01'), null);
$technicianLevel = new AccessLevel(5, 'Tecnico', 'tecnico', null, 50, true, new DateTimeImmutable('2026-01-01'), null);

assert_true($authorization->isAdministrator($admin), 'nivel Administrador e reconhecido como Administrador');
assert_true($authorization->isSupport($support), 'nivel Suporte e reconhecido como Suporte');
assert_true(!$authorization->isAdministrator($manager), 'Gestor nao e Administrador');
assert_true(!$authorization->isSupport($manager), 'Gestor nao e Suporte');
assert_true(!$authorization->isAdministrator($custom), 'nivel personalizado com promover nao vira Administrador');
assert_true(!$authorization->isSupport($custom), 'nivel personalizado com aprovar nao vira Suporte');
assert_true($authorization->canAccessOperationalSector($admin, 999), 'Administrador ativo possui escopo global de setor');
assert_true($authorization->canAccessOperationalSector($support, 999), 'Suporte ativo possui escopo operacional global');
assert_true($authorization->can($support, 'prontuarios.editar'), 'Suporte com permissao operacional pode editar prontuarios');
assert_true($authorization->canAccessOperationalSector($manager, 30), 'Gestor acessa proprio setor');
assert_true(!$authorization->canAccessOperationalSector($manager, 31), 'Gestor nao acessa outro setor');

$adminWithoutPromote = make_authorization([1 => ['usuarios.alterar_nivel']]);
assert_true(!$adminWithoutPromote->canPromoteAdministrator($admin), 'Administrador sem promover nao pode promover');
assert_true($authorization->canPromoteAdministrator($admin), 'Administrador com permissao pode promover');
assert_true(!$authorization->canAssignLevel($support, $adminLevel), 'Suporte nao pode atribuir Administrador');
assert_true(!$authorization->canAssignLevel($support, $supportLevel), 'Suporte nao pode atribuir Suporte');
assert_true($authorization->canAssignLevel($support, $technicianLevel), 'Suporte pode atribuir Tecnico');
assert_true(!$authorization->isAdministrator($withoutLevel), 'usuario sem nivel nao e Administrador');
assert_true(!$authorization->isSupport($withoutLevel), 'usuario sem nivel nao e Suporte');
assert_true(!$authorization->isAdministrator($inactiveAdminLevelUser), 'nivel inativo nao concede identidade administrativa');

$noBlockAuthorization = make_authorization([1 => ['usuarios.editar', 'usuarios.promover_administrador']]);
assert_throws(
    static fn () => policy_for($noBlockAuthorization)->assertCanBlock($admin, $manager),
    'operador sem usuarios.bloquear nao pode bloquear'
);

$noEditAuthorization = make_authorization([1 => ['usuarios.bloquear', 'usuarios.promover_administrador']]);
assert_throws(
    static fn () => policy_for($noEditAuthorization)->assertCanInactivate($admin, $manager),
    'operador sem usuarios.editar nao pode inativar'
);

assert_throws(
    static fn () => policy_for($authorization, 1)->assertCanBlock($admin, make_user(7, 1, 70)),
    'ultimo Administrador ativo continua protegido'
);

if ($failures === []) {
    echo 'PASS authorization-level-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}

echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
