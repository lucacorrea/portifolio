<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ComidaMesaRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\SectorRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\ComidaMesaService;
use App\Services\PermissionService;

function cm_h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cm_db(): PDO
{
    return Database::connection();
}

/** @return array<string,mixed> */
function cm_app(): array
{
    static $context = null;
    if (is_array($context)) {
        return $context;
    }

    $pdo = cm_db();
    $levels = new AccessLevelRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService(new UserRepository($pdo), new UserSessionRepository($pdo), $levels, $audit);
    $user = $auth->requireUser();
    $authorization = new AuthorizationService(new PermissionService(new PermissionRepository($pdo)), $levels);
    $level = $user->nivelId === null ? null : $levels->findById($user->nivelId);
    $sector = $user->setorId === null ? null : (new SectorRepository($pdo))->findById($user->setorId);
    $repository = new ComidaMesaRepository($pdo);
    $service = new ComidaMesaService($repository);

    $context = [
        'pdo' => $pdo,
        'user' => $user,
        'authorization' => $authorization,
        'audit' => $audit,
        'level' => $level,
        'sector' => $sector,
        'repository' => $repository,
        'service' => $service,
    ];

    return $context;
}

function cm_can(string $permission): bool
{
    $app = cm_app();
    return $app['authorization']->can($app['user'], $permission);
}

function cm_require(string $permission): void
{
    $app = cm_app();
    $app['authorization']->requirePermission($app['user'], $permission);
}

function cm_csrf(string $name): string
{
    return Csrf::token($name);
}

function cm_format_cpf(mixed $value): string
{
    $digits = preg_replace('/\D+/', '', (string) ($value ?? '')) ?: '';
    if (strlen($digits) !== 11) {
        return trim((string) ($value ?? '')) ?: '—';
    }
    return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
}

function cm_selected(mixed $actual, mixed $expected): string
{
    return (string) $actual === (string) $expected ? ' selected' : '';
}

function cm_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part !== '') $letters .= mb_substr($part, 0, 1);
        if (mb_strlen($letters) >= 2) break;
    }
    return mb_strtoupper($letters !== '' ? $letters : 'U');
}

function cm_location(array $row): string
{
    $parts = array_filter([$row['bairro'] ?? null, $row['comunidade'] ?? null, $row['zona'] ?? null], static fn($v) => $v !== null && trim((string)$v) !== '');
    return $parts ? implode(' - ', array_map('strval', $parts)) : 'Sem localidade';
}

function cm_date(mixed $value, bool $withTime = false): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($value))->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
    } catch (Throwable) {
        return '—';
    }
}

function cm_money(mixed $value): string
{
    return 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
}

function cm_month_label(int $month, int $year): string
{
    $months = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
    return ($months[$month] ?? 'Mês') . ' de ' . $year;
}

function cm_program_label(string $status): string
{
    return match ($status) {
        'ativa' => 'Beneficiária ativa',
        'em_analise' => 'Em análise',
        'lista_espera' => 'Lista de espera',
        'suspensa' => 'Suspensa',
        'bloqueada' => 'Bloqueada',
        'encerrada' => 'Encerrada',
        default => 'Não informado',
    };
}

function cm_competence_label(string $status): string
{
    return match ($status) {
        'planejada' => 'Planejada',
        'aberta' => 'Aberta',
        'encerrada' => 'Encerrada',
        'cancelada' => 'Cancelada',
        default => 'Não informado',
    };
}

function cm_tone(string $status): string
{
    $value = mb_strtolower(trim($status), 'UTF-8');
    if (in_array($value, ['ativa', 'ativo', 'aberta', 'entregue', 'recebida', 'concluído', 'concluido'], true)) return 'success';
    if (in_array($value, ['em análise', 'em analise', 'lista de espera', 'aguardando retirada', 'planejada', 'pendente'], true)) return 'warning';
    if (in_array($value, ['bloqueada', 'suspensa', 'cancelada', 'inativo', 'vencido'], true)) return 'danger';
    if (in_array($value, ['encerrada', 'encerrado', 'indisponível', 'indisponivel'], true)) return 'muted';
    return 'info';
}

function cm_status(string $label): string
{
    return '<span class="cm-status cm-status--' . cm_h(cm_tone($label)) . '">' . cm_h($label) . '</span>';
}

function cm_schema_ready(): bool
{
    try {
        $pdo = cm_db();
        foreach (['comida_mesa_inscricoes', 'comida_mesa_competencias', 'comida_mesa_entregas', 'comida_mesa_polos', 'comida_mesa_documentos', 'comida_mesa_historico', 'familias', 'pessoas'] as $table) {
            $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
        }
        return true;
    } catch (Throwable) {
        return false;
    }
}

/** @return array<string,mixed> */
function cm_frontend_context(array $base): array
{
    $app = cm_app();
    $repository = $app['repository'];
    $service = $app['service'];
    $competences = $repository->listCompetences();
    $defaultCompetence = $repository->findDefaultCompetence();

    $base['csrf'] = array_merge($base['csrf'] ?? [], [
        'consultarCpf' => cm_csrf('comida_mesa_consultar_cpf'),
        'salvarCadastro' => cm_csrf('comida_mesa_salvar_cadastro'),
        'salvarCompetencia' => cm_csrf('comida_mesa_salvar_competencia'),
        'salvarPolo' => cm_csrf('comida_mesa_salvar_polo'),
        'registrarEntrega' => cm_csrf('comida_mesa_registrar_entrega'),
        'cancelarEntrega' => cm_csrf('comida_mesa_cancelar_entrega'),
        'enviarDocumento' => cm_csrf('comida_mesa_enviar_documento'),
    ]);

    $base['comidaMesa'] = [
        'competenciaId' => $defaultCompetence ? (int) $defaultCompetence['id'] : null,
        'competenceLabel' => $defaultCompetence ? $service->formatCompetence((int) $defaultCompetence['mes'], (int) $defaultCompetence['ano']) : 'Sem competência',
        'competences' => array_map(static fn (array $item): array => [
            'id' => (int) $item['id'],
            'month' => (int) $item['mes'],
            'year' => (int) $item['ano'],
            'status' => (string) $item['status'],
            'startsAt' => $item['inicio_entregas'] ?? '',
            'endsAt' => $item['fim_entregas'] ?? '',
            'observation' => $item['observacao'] ?? '',
        ], $competences),
        'permissions' => [
            'consultCpf' => cm_can('comida_mesa.consultar_cpf'),
            'create' => cm_can('comida_mesa.cadastrar'),
            'import' => cm_can('comida_mesa.importar'),
            'edit' => cm_can('comida_mesa.editar'),
            'deliver' => cm_can('comida_mesa.entregar'),
            'cancelDelivery' => cm_can('comida_mesa.cancelar_entrega'),
            'viewDocuments' => cm_can('comida_mesa.documentos_visualizar'),
            'sendDocuments' => cm_can('comida_mesa.documentos_enviar'),
            'viewHistory' => cm_can('comida_mesa.historico_visualizar'),
            'manageCompetences' => cm_can('comida_mesa.competencias_gerenciar'),
            'managePoles' => cm_can('comida_mesa.polos_gerenciar'),
        ],
        'urls' => [
            'painel' => 'comida-mesa/index.php',
            'beneficiarios' => 'comida-mesa/beneficiarios.php',
            'novaInscricao' => 'comida-mesa/nova-inscricao.php',
            'importarBeneficiarios' => 'comida-mesa/importar-beneficiarios.php',
            'consultaCpf' => 'comida-mesa/consulta-cpf.php',
            'registrarEntrega' => 'comida-mesa/registrar-entrega.php',
            'competencias' => 'comida-mesa/competencias.php',
            'polos' => 'comida-mesa/polos.php',
            'documentos' => 'comida-mesa/documentos.php',
            'historico' => 'comida-mesa/historico.php',
            'relatorios' => 'comida-mesa/relatorios.php',
        ],
    ];

    return $base;
}
