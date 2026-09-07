<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Repositories\PermissionRepository;
use App\Services\PermissionService;

$failures = [];

function iaj_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

final class IndividualAccessMemoryPdo extends PDO
{
    /**
     * @param list<string> $levelPermissions
     * @param array<string,bool|null> $permissionOverrides
     * @param array<string,bool|null> $moduleOverrides
     * @param array<string,bool> $sectorRules
     */
    public function __construct(
        public array $levelPermissions,
        public array $permissionOverrides = [],
        public array $moduleOverrides = [],
        public array $sectorRules = [],
        public bool $sectorConfigured = true,
    ) {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new IndividualAccessMemoryStatement($this, $query);
    }
}

final class IndividualAccessMemoryStatement extends PDOStatement
{
    /** @var array<string,mixed> */
    private array $params = [];
    /** @var list<array<string,mixed>> */
    private array $rows = [];
    /** @var list<mixed> */
    private array $columns = [];

    public function __construct(
        private readonly IndividualAccessMemoryPdo $pdo,
        private readonly string $query,
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $this->params = $params ?? [];
        $this->rows = [];
        $this->columns = [];

        if (str_contains($this->query, 'INNER JOIN nivel_permissoes')) {
            foreach ($this->pdo->levelPermissions as $index => $slug) {
                $this->rows[] = [
                    'id' => $index + 1,
                    'nome' => $slug,
                    'slug' => $slug,
                    'descricao' => null,
                    'modulo' => str_starts_with($slug, 'comida_mesa.') ? 'comida_mesa' : 'primeiro_emprego',
                    'ativo' => 1,
                    'criado_em' => '2026-01-01 00:00:00',
                ];
            }
            return true;
        }

        if (str_contains($this->query, 'SELECT modulo') && str_contains($this->query, 'FROM permissoes')) {
            $slug = (string) ($this->params['slug'] ?? '');
            if (str_starts_with($slug, 'comida_mesa.')) {
                $this->columns = ['comida_mesa'];
            } elseif (str_starts_with($slug, 'primeiro_emprego.')) {
                $this->columns = ['primeiro_emprego'];
            }
            return true;
        }

        if (str_contains($this->query, 'FROM usuario_modulo_excecoes')) {
            $module = (string) ($this->params['modulo'] ?? '');
            $value = $this->pdo->moduleOverrides[$module] ?? null;
            if ($value !== null) {
                $this->columns = [$value ? 1 : 0];
            }
            return true;
        }

        if (str_contains($this->query, 'FROM usuario_permissao_excecoes')) {
            $slug = (string) ($this->params['slug'] ?? '');
            $value = $this->pdo->permissionOverrides[$slug] ?? null;
            if ($value !== null) {
                $this->columns = [$value ? 1 : 0];
            }
            return true;
        }

        if (str_contains($this->query, 'FROM setor_modulos')) {
            $module = $this->params['modulo'] ?? null;
            if ($module === null) {
                if ($this->pdo->sectorConfigured) {
                    $this->columns = [1];
                }
            } else {
                $allowed = $this->pdo->sectorRules[(string) $module] ?? false;
                if ($allowed) {
                    $this->columns = [1];
                }
            }
            return true;
        }

        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->columns[$column] ?? false;
    }
}

function iaj_service(
    array $levelPermissions,
    array $permissionOverrides = [],
    array $moduleOverrides = [],
    array $sectorRules = ['comida-mesa' => true],
    bool $sectorConfigured = true,
): PermissionService {
    return new PermissionService(new PermissionRepository(new IndividualAccessMemoryPdo(
        $levelPermissions,
        $permissionOverrides,
        $moduleOverrides,
        $sectorRules,
        $sectorConfigured,
    )));
}

$permission = 'comida_mesa.editar';

$service = iaj_service([$permission]);
iaj_assert(
    $service->hasPermissionForUser(10, 3, 5, $permission),
    'permissão do nível permanece válida quando setor e módulo permitem'
);

$service = iaj_service([$permission], [$permission => true], ['comida-mesa' => false]);
iaj_assert(
    !$service->hasPermissionForUser(10, 3, 5, $permission),
    'bloqueio individual do módulo vence até uma liberação individual da ação'
);

$service = iaj_service([$permission], [], [], ['comida-mesa' => false]);
iaj_assert(
    !$service->hasPermissionForUser(10, 3, 5, $permission),
    'setor sem acesso ao módulo não consegue chamar ação diretamente pela API'
);

$service = iaj_service([$permission], [], ['comida-mesa' => true], ['comida-mesa' => false]);
iaj_assert(
    $service->hasPermissionForUser(10, 3, 5, $permission),
    'exceção positiva de módulo pode superar regra negativa do setor'
);

$service = iaj_service([], [$permission => true], [], ['comida-mesa' => true]);
iaj_assert(
    $service->hasPermissionForUser(10, 3, 5, $permission),
    'exceção positiva da ação pode liberar usuário cujo nível não possui a ação'
);

$service = iaj_service([$permission], [$permission => false], [], ['comida-mesa' => true]);
iaj_assert(
    !$service->hasPermissionForUser(10, 3, 5, $permission),
    'exceção negativa da ação pode restringir apenas uma pessoa sem alterar o nível'
);

$service = iaj_service([$permission], [], [], [], false);
iaj_assert(
    $service->hasPermissionForUser(10, 3, 5, $permission),
    'setor ainda sem matriz configurada mantém fallback compatível'
);

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/database/migrations/20260907_015_acesso_individual_rastreabilidade_pessoas.sql') ?: '';
$authorization = file_get_contents($root . '/app/Services/AuthorizationService.php') ?: '';
$governancePage = file_get_contents($root . '/frontend/modules/gestao-acessos/pages/usuarios.php') ?: '';
$governanceComponent = file_get_contents($root . '/frontend/modules/gestao-acessos/components/user-access-overrides.php') ?: '';
$journeyPage = file_get_contents($root . '/historico-pessoa.php') ?: '';
$journeyRepository = file_get_contents($root . '/app/Repositories/PersonJourneyRepository.php') ?: '';
$comidaSave = file_get_contents($root . '/api/comida-mesa/salvar-cadastro.php') ?: '';
$peJourney = file_get_contents($root . '/frontend/modules/primeiro-emprego/lib/person-journey.php') ?: '';
$peNewCandidate = file_get_contents($root . '/frontend/modules/primeiro-emprego/pages/novo-candidato.php') ?: '';

foreach (['usuario_permissao_excecoes', 'pessoa_atendimentos', 'pessoa_movimentacoes'] as $table) {
    iaj_assert(str_contains($migration, $table), 'migration deve criar/usar ' . $table);
}

iaj_assert(str_contains($migration, 'information_schema.COLUMNS'), 'migration deve validar coluna existente de forma idempotente no MariaDB');
iaj_assert(str_contains($migration, 'information_schema.STATISTICS'), 'migration deve validar índice existente de forma idempotente no MariaDB');
iaj_assert(str_contains($migration, 'idx_pessoa_atendimentos_fila_atual'), 'migration deve manter índice composto da fila operacional');
iaj_assert(str_contains($migration, 'fk_pe_candidatos_pessoa'), 'Primeiro Emprego deve possuir FK para a pessoa central');
iaj_assert(!str_contains($migration, 'START TRANSACTION'), 'migration com DDL não deve simular rollback transacional no MariaDB');
iaj_assert(str_contains($migration, 'HAVING COUNT(*) = 1'), 'backfill deve vincular somente CPF único entre candidatos');
iaj_assert(str_contains($migration, 'COALESCE(c.revisao_cpf, 0) = 0'), 'backfill deve ignorar CPF em revisão');
iaj_assert(str_contains($migration, 'COALESCE(c.cpf_duplicado, 0) = 0'), 'backfill deve ignorar CPF marcado como duplicado');
iaj_assert(str_contains($authorization, '$user->setorId'), 'autorização efetiva deve considerar setor do usuário');
iaj_assert(str_contains($governancePage, 'accessProfile'), 'Governança deve carregar perfil efetivo do usuário');
iaj_assert(str_contains($governanceComponent, 'save_overrides'), 'Governança deve permitir salvar exceções individuais');
iaj_assert(str_contains($governanceComponent, 'Herdar do nível'), 'editor deve distinguir herança de exceção individual');
iaj_assert(str_contains($journeyPage, "value=\"receber\""), 'trajetória deve permitir recebimento do encaminhamento');
iaj_assert(str_contains($journeyPage, "value=\"encaminhar\""), 'trajetória deve permitir encaminhamento');
iaj_assert(str_contains($journeyPage, "value=\"concluir\""), 'trajetória deve permitir conclusão');
iaj_assert(str_contains($journeyPage, 'NULL AS nis, NULL AS telefone'), 'consulta transversal não deve carregar NIS/telefone para perfil operacional');
iaj_assert(str_contains($journeyPage, "'Restrito'"), 'tela transversal deve indicar dados pessoais restritos ao perfil operacional');
iaj_assert(str_contains($journeyPage, 'não pertence à pessoa consultada'), 'POST da trajetória deve vincular atendimento à pessoa consultada');
iaj_assert(!str_contains($journeyPage, 'SELECT setor_atual_id FROM pessoa_atendimentos WHERE id = :id LIMIT 1'), 'tela não deve executar consulta N+1 por atendimento');
iaj_assert(str_contains($journeyRepository, 'a.setor_atual_id'), 'consulta principal da trajetória deve trazer setor_atual_id');
iaj_assert(str_contains($comidaSave, 'new PersonJourneyService'), 'Comida na Mesa deve iniciar trajetória ao cadastrar');
iaj_assert(str_contains($peJourney, 'pe_link_person_and_start_journey'), 'Primeiro Emprego deve possuir integração com pessoa central');
iaj_assert(str_contains($peNewCandidate, 'pe_link_person_and_start_journey'), 'triagem manual deve iniciar rastreabilidade');

if ($failures === []) {
    echo 'PASS individual-access-journey-test' . PHP_EOL;
    exit(0);
}

foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}

echo 'FAILURES: ' . count($failures) . PHP_EOL;
exit(1);
