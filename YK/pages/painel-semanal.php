<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

require_once __DIR__
  . '/../actions/painel-semanal-action-common.php';

/**
 * Retorna um valor escalar seguro de um registro.
 */
function weekly_value(
  array $row,
  string $key,
  string $default = ''
): string {
  $value = $row[$key] ?? $default;

  return is_scalar($value)
    ? (string) $value
    : $default;
}

/**
 * Converte um valor recuperado do formulário.
 */
function weekly_recovery_value(
  array $data,
  string $key,
  string $default = ''
): string {
  $value = $data[$key] ?? $default;

  return is_scalar($value)
    ? (string) $value
    : $default;
}

function weekly_status_label(
  string $status
): string {
  return [
    'aguardando_confirmacao' =>
    'Aguardando confirmação',

    'confirmado' =>
    'OS gerada',

    'cancelado' =>
    'Cancelado',
  ][$status] ?? $status;
}

function weekly_status_badge(
  string $status
): string {
  return [
    'aguardando_confirmacao' => 'amber',
    'confirmado' => 'green',
    'cancelado' => 'gray',
  ][$status] ?? 'gray';
}
function weekly_order_status_label(
  string $status
): string {
  return [
    'rascunho' => 'Rascunho',
    'aberta' => 'Aberta',

    'aguardando_agendamento' =>
    'Aguardando agendamento',

    'agendada' => 'Agendada',

    'em_deslocamento' =>
    'Em deslocamento',

    'em_execucao' =>
    'Em execução',

    'aguardando_peca' =>
    'Aguardando peça',

    'finalizada' =>
    'Finalizada',

    'cancelada' =>
    'Cancelada',
  ][$status] ?? $status;
}

function weekly_order_status_badge(
  string $status
): string {
  return [
    'rascunho' => 'gray',
    'aberta' => 'blue',

    'aguardando_agendamento' =>
    'amber',

    'agendada' => 'blue',

    'em_deslocamento' =>
    'blue',

    'em_execucao' =>
    'green',

    'aguardando_peca' =>
    'amber',

    'finalizada' =>
    'green',

    'cancelada' =>
    'gray',
  ][$status] ?? 'gray';
}

function weekly_priority_label(
  string $priority
): string {
  return [
    'baixa' => 'Baixa',
    'media' => 'Média',
    'alta' => 'Alta',
    'urgente' => 'Urgente',
  ][$priority] ?? 'Média';
}

function weekly_datetime(
  ?string $value,
  string $format = 'd/m/Y H:i'
): string {
  if (
    $value === null
    || trim($value) === ''
  ) {
    return '—';
  }

  try {
    return (
      new DateTimeImmutable($value)
    )->format($format);
  } catch (Throwable) {
    return '—';
  }
}

function weekly_time_range(
  ?string $start,
  ?string $end
): string {
  if (
    $start === null
    || $end === null
    || $start === ''
    || $end === ''
  ) {
    return 'Horário não informado';
  }

  try {
    return (
      new DateTimeImmutable($start)
    )->format('H:i')
      . ' – '
      . (
        new DateTimeImmutable($end)
      )->format('H:i');
  } catch (Throwable) {
    return 'Horário inválido';
  }
}

function weekly_team_name(
  array $planning
): string {
  $primary = trim(
    weekly_value(
      $planning,
      'funcionario_principal_nome'
    )
  );

  $support = trim(
    weekly_value(
      $planning,
      'funcionario_apoio_nome'
    )
  );

  $names = array_values(
    array_filter(
      [
        $primary,
        $support,
      ],
      static fn(string $name): bool =>
      $name !== ''
    )
  );

  return $names === []
    ? 'Equipe não definida'
    : implode(' / ', $names);
}

/**
 * Normaliza o nome do funcionário para exibição no quadro.
 */
function weekly_employee_name(
  string $value
): string {
  $value = trim($value);

  if ($value === '') {
    return '';
  }

  /*
   * Remove o código visual "FUN-000001 —" quando o valor
   * vier da entidade de OS. O quadro precisa priorizar o nome.
   */
  $clean = preg_replace(
    '/^[^—]+—\\s*/u',
    '',
    $value
  );

  return is_string($clean)
    ? trim($clean)
    : $value;
}

/**
 * Cria uma chave estável para a equipe/dupla.
 * A composição da equipe é o que define a coluna do quadro.
 * A ordem dos IDs não cria colunas duplicadas.
 *
 * @param int[] $employeeIds
 */
function weekly_team_key(
  array $employeeIds
): string {
  $ids = array_values(
    array_unique(
      array_filter(
        array_map('intval', $employeeIds),
        static fn(int $id): bool => $id > 0
      )
    )
  );

  sort($ids, SORT_NUMERIC);

  return $ids === []
    ? 'team:unassigned'
    : 'team:' . implode('-', $ids);
}

/**
 * @param array<int,array{id:int,name:string}> $members
 * @return array{key:string,label:string,employee_ids:array<int,int>}
 */
function weekly_team_descriptor(
  array $members
): array {
  $ids = [];
  $names = [];

  foreach ($members as $member) {
    $id = (int) ($member['id'] ?? 0);
    $name = weekly_employee_name(
      (string) ($member['name'] ?? '')
    );

    if ($id > 0) {
      $ids[] = $id;
    }

    if ($name !== '' && !in_array($name, $names, true)) {
      $names[] = $name;
    }
  }

  return [
    'key' => weekly_team_key($ids),
    'label' => $names === []
      ? 'Sem equipe definida'
      : implode(' / ', $names),
    'employee_ids' => array_values(
      array_unique(
        array_filter(
          array_map('intval', $ids),
          static fn(int $id): bool => $id > 0
        )
      )
    ),
  ];
}

function weekly_planning_team_descriptor(
  array $planning
): array {
  return weekly_team_descriptor([
    [
      'id' => (int) weekly_value(
        $planning,
        'funcionario_principal_id',
        '0'
      ),
      'name' => weekly_value(
        $planning,
        'funcionario_principal_nome'
      ),
    ],
    [
      'id' => (int) weekly_value(
        $planning,
        'funcionario_apoio_id',
        '0'
      ),
      'name' => weekly_value(
        $planning,
        'funcionario_apoio_nome'
      ),
    ],
  ]);
}

/**
 * @param array<int,object> $members
 */
function weekly_order_team_descriptor(
  object $order,
  array $members
): array {
  $team = [];

  foreach ($members as $member) {
    if (
      !method_exists($member, 'employeeId')
      || !method_exists($member, 'employeeName')
    ) {
      continue;
    }

    $team[] = [
      'id' => (int) $member->employeeId(),
      'name' => (string) $member->employeeName(),
      'primary' => method_exists($member, 'primary')
        ? (bool) $member->primary()
        : false,
    ];
  }

  if ($team !== []) {
    usort(
      $team,
      static fn(array $a, array $b): int =>
        ((int) ($b['primary'] ?? 0))
        <=> ((int) ($a['primary'] ?? 0))
    );

    return weekly_team_descriptor($team);
  }

  $primaryId = method_exists($order, 'primaryEmployeeId')
    ? $order->primaryEmployeeId()
    : null;

  $supportId = method_exists($order, 'supportEmployeeId')
    ? $order->supportEmployeeId()
    : null;

  $primaryName = method_exists($order, 'displayPrimaryEmployee')
    ? (string) ($order->displayPrimaryEmployee() ?? '')
    : '';

  $supportName = method_exists($order, 'displaySupportEmployee')
    ? (string) ($order->displaySupportEmployee() ?? '')
    : '';

  return weekly_team_descriptor([
    [
      'id' => (int) ($primaryId ?? 0),
      'name' => $primaryName,
    ],
    [
      'id' => (int) ($supportId ?? 0),
      'name' => $supportName,
    ],
  ]);
}

/**
 * Indica se o atendimento está acontecendo neste momento.
 */
function weekly_is_current_slot(
  ?string $start,
  ?string $end
): bool {
  if (
    $start === null
    || $end === null
    || trim($start) === ''
    || trim($end) === ''
  ) {
    return false;
  }

  try {
    $now = new DateTimeImmutable('now');
    $startAt = new DateTimeImmutable($start);
    $endAt = new DateTimeImmutable($end);

    return $now >= $startAt
      && $now < $endAt;
  } catch (Throwable) {
    return false;
  }
}

function weekly_client_options(
  array $clients,
  string $selected = ''
): void {
  foreach ($clients as $client) {
    $id = (string) $client->id();

?>
    <option
      value="<?= h($id) ?>"
      <?= $selected === $id ? 'selected' : '' ?>>
      <?= h($client->name()) ?>
    </option>
  <?php
  }
}

function weekly_service_options(
  array $services,
  string $selected = ''
): void {
  foreach ($services as $service) {
    $id = (string) $service->id();

  ?>
    <option
      value="<?= h($id) ?>"
      data-duration="<?= h(
                        (string) $service->durationMinutes()
                      ) ?>"
      <?= $selected === $id ? 'selected' : '' ?>>
      <?= h(
        $service->displayCode()
          . ' — '
          . $service->name()
      ) ?>
    </option>
  <?php
  }
}

function weekly_employee_options(
  array $employees,
  string $selected = ''
): void {
  foreach ($employees as $employee) {
    $id = (string) $employee->id();

  ?>
    <option
      value="<?= h($id) ?>"
      <?= $selected === $id ? 'selected' : '' ?>>
      <?= h(
        $employee->displayCode()
          . ' — '
          . $employee->name()
      ) ?>
    </option>
  <?php
  }
}

function weekly_return_fields(
  DateTimeImmutable $weekStart
): void {
  return_to_field();

  ?>
  <input
    type="hidden"
    name="return_week"
    value="<?= h(
              $weekStart->format('Y-m-d')
            ) ?>">
<?php
}

/*
 * Semana selecionada.
 */
$rawWeek = trim(
  (string) (
    $_GET['week']
    ?? date('Y-m-d')
  )
);

$baseWeek = DateTimeImmutable::createFromFormat(
  '!Y-m-d',
  $rawWeek
);

if (
  $baseWeek === false
  || $baseWeek->format('Y-m-d')
  !== $rawWeek
) {
  $baseWeek =
    new DateTimeImmutable('today');
}

$weekStart = $baseWeek
  ->modify('monday this week')
  ->setTime(0, 0);

$weekEnd = $weekStart
  ->modify('+7 days');

$previousWeek = $weekStart
  ->modify('-7 days');

$nextWeek = $weekStart
  ->modify('+7 days');

/*
 * Filtros.
 */
$search = trim(
  (string) ($_GET['search'] ?? '')
);

if (
  str_contains($search, "\0")
  || mb_strlen($search, 'UTF-8') > 150
) {
  $search = '';
}

$status = trim(
  (string) ($_GET['status'] ?? '')
);

$allowedStatuses = [
  '',
  'aguardando_confirmacao',
  'ordens',
  'agendada',
  'em_deslocamento',
  'em_execucao',
  'aguardando_peca',
  'finalizada',
  'cancelada',
];

if (
  !in_array(
    $status,
    $allowedStatuses,
    true
  )
) {
  $status = '';
}

$employeeId = trim(
  (string) (
    $_GET['employee_id']
    ?? ''
  )
);

if (
  $employeeId !== ''
  && (
    !ctype_digit($employeeId)
    || (int) $employeeId <= 0
  )
) {
  $employeeId = '';
}

$filters = [
  'search' => $search,
  'status' => $status,
  'employee_id' => $employeeId,
];

/*
 * Serviços usados pela página.
 */
$planningService =
  $application->weeklyServicePlanning();

$orderService =
  $application->serviceOrderManagement();

$employeeService =
  $application->employeeManagement();

$clientService =
  $application->clientManagement();

$catalogService =
  $application->serviceManagement();

$employees =
    $employeeService->listActiveEmployees();

$clients =
  $clientService->listClients();

$services =
  $catalogService->listServices([
    'status' => 'ativo',
  ]);

/*
 * A tela trabalha com duas fontes:
 *
 * 1. Planejamentos ainda não confirmados;
 * 2. Ordens de Serviço reais agendadas na semana.
 *
 * Planejamentos confirmados não são listados novamente,
 * porque a OS vinculada já será exibida.
 */
$showPlannings = in_array(
  $status,
  [
    '',
    'aguardando_confirmacao',
  ],
  true
);

$showOrders =
  $status !== 'aguardando_confirmacao';

$plannings = [];

if ($showPlannings) {
  $plannings =
    $planningService->listBetween(
      $weekStart,
      $weekEnd,
      [
        'search' => $search,
        'employee_id' => $employeeId,

        /*
                 * Mostra somente os planejamentos que
                 * ainda não viraram OS.
                 */
        'status' =>
        'aguardando_confirmacao',
      ]
    );
}

/*
 * Filtros aceitos pelo repositório de OS.
 */
$orderFilters = [
  'search' => $search,
  'employee_id' => $employeeId,
];

if (
  $status !== ''
  && $status !== 'ordens'
  && $status !== 'aguardando_confirmacao'
) {
  $orderFilters['status'] = $status;
}

/*
 * Carrega também todas as OS já agendadas na semana,
 * inclusive as criadas diretamente no módulo de OS.
 */
$orderWeekGroups = [];

if ($showOrders) {
  $orderWeekGroups =
    $orderService->weekSchedule(
      $weekStart,
      $orderFilters
    );
}

$orders = [];

foreach ($orderWeekGroups as $dayOrders) {
  foreach ($dayOrders as $order) {
    $orders[] = $order;
  }
}

/*
 * Carrega os membros reais das equipes das OS em uma única consulta.
 * Isso evita N+1 e mantém a coluna correta mesmo quando a OS possui
 * mais de um técnico de apoio.
 */
$teamsByOrder = $orders === []
  ? []
  : $orderService->teamMembersForOrders($orders);

/*
 * Registros unificados do calendário.
 *
 * Cada entrada informa sua origem para que o HTML
 * saiba renderizar planejamento ou OS.
 */
$weekGroups = [];

foreach ($plannings as $planning) {
  $start = weekly_value(
    $planning,
    'agendado_inicio'
  );

  try {
    $dateKey = (
      new DateTimeImmutable($start)
    )->format('Y-m-d');
  } catch (Throwable) {
    continue;
  }

  $teamDescriptor =
    weekly_planning_team_descriptor(
      $planning
    );

  $weekGroups[$dateKey][] = [
    'kind' => 'planning',
    'start' => $start,
    'planning' => $planning,
    'team_key' => $teamDescriptor['key'],
    'team_label' => $teamDescriptor['label'],
    'team_employee_ids' =>
      $teamDescriptor['employee_ids'],
  ];
}

foreach ($orderWeekGroups as $dateKey => $dayOrders) {
  foreach ($dayOrders as $order) {
    /*
         * Segurança adicional: somente OS realmente
         * agendada entra no calendário semanal.
         */
    if ($order->scheduledStart() === null) {
      continue;
    }

    $teamDescriptor =
      weekly_order_team_descriptor(
        $order,
        $teamsByOrder[$order->id()] ?? []
      );

    $weekGroups[$dateKey][] = [
      'kind' => 'order',
      'start' => $order->scheduledStart(),
      'order' => $order,
      'team_key' => $teamDescriptor['key'],
      'team_label' => $teamDescriptor['label'],
      'team_employee_ids' =>
        $teamDescriptor['employee_ids'],
    ];
  }
}

/*
 * Planejamentos e OS aparecem juntos em ordem de horário.
 */
foreach ($weekGroups as &$dayRecords) {
  usort(
    $dayRecords,
    static function (
      array $left,
      array $right
    ): int {
      return strcmp(
        (string) (
          $left['start']
          ?? ''
        ),
        (string) (
          $right['start']
          ?? ''
        )
      );
    }
  );
}

unset($dayRecords);

/*
 * Matriz visual da semana.
 *
 * Linhas: dias da semana.
 * Colunas: funcionário/equipe (dupla ou equipe completa).
 *
 * Um registro aparece uma única vez na coluna da composição real
 * da equipe. Isso reproduz o modelo operacional usado pela empresa
 * sem duplicar a mesma OS em várias colunas.
 */
$teamColumns = [];
$weeklyTeamMatrix = [];

foreach ($weekGroups as $dateKey => $dayRecords) {
  foreach ($dayRecords as $record) {
    $teamKey = (string) (
      $record['team_key']
      ?? 'team:unassigned'
    );

    $teamLabel = trim(
      (string) (
        $record['team_label']
        ?? 'Sem equipe definida'
      )
    );

    if ($teamLabel === '') {
      $teamLabel = 'Sem equipe definida';
    }

    if (!isset($teamColumns[$teamKey])) {
      $teamColumns[$teamKey] = [
        'key' => $teamKey,
        'label' => $teamLabel,
        'employee_ids' => array_values(
          array_filter(
            array_map(
              'intval',
              (array) (
                $record['team_employee_ids']
                ?? []
              )
            ),
            static fn(int $id): bool => $id > 0
          )
        ),
        'appointments' => 0,
      ];
    }

    $teamColumns[$teamKey]['appointments']++;
    $weeklyTeamMatrix[$dateKey][$teamKey][] = $record;
  }
}

uasort(
  $teamColumns,
  static function (array $left, array $right): int {
    if (
      $left['key'] === 'team:unassigned'
      && $right['key'] === 'team:unassigned'
    ) {
      return 0;
    }

    if ($left['key'] === 'team:unassigned') {
      return 1;
    }

    if ($right['key'] === 'team:unassigned') {
      return -1;
    }

    return strnatcasecmp(
      (string) $left['label'],
      (string) $right['label']
    );
  }
);

$visibleTeamCount = count($teamColumns);
$boardColumnCount = max(1, $visibleTeamCount);
$boardMinWidth = 154 + ($boardColumnCount * 286);

/*
 * Indicadores.
 */
$summary = [
  'total' =>
  count($plannings)
    + count($orders),

  'awaiting' =>
  count($plannings),

  'orders' =>
  count($orders),

  'urgent' => 0,
];

foreach ($plannings as $planning) {
  if (
    weekly_value(
      $planning,
      'prioridade',
      'media'
    ) === 'urgente'
  ) {
    $summary['urgent']++;
  }
}

foreach ($orders as $order) {
  if (
    $order->priority() === 'urgente'
    && !in_array(
      $order->status(),
      ['finalizada', 'cancelada'],
      true
    )
  ) {
    $summary['urgent']++;
  }
}

/*
 * Permissões.
 */
$canCreate = $authorization->can(
  'painel_semanal.adicionar'
);

$canConfirm = $authorization->can(
  'os.criar'
);

$canViewOrder = $authorization->can(
  'os.visualizar'
);

/*
 * Recuperação de formulário após erro.
 */
$recovery =
  os_consume_form_recovery();

$recoveryModal = is_array($recovery)
  ? (string) (
    $recovery['modal']
    ?? ''
  )
  : '';

$recoveryError = is_array($recovery)
  ? (string) (
    $recovery['error']
    ?? ''
  )
  : '';

$recoveryData = (
  is_array($recovery)
  && is_array(
    $recovery['data']
      ?? null
  )
)
  ? $recovery['data']
  : [];

$createRecoveryData =
  $recoveryModal === 'create'
  ? $recoveryData
  : [];

$days = [
  'Monday' => 'Segunda',
  'Tuesday' => 'Terça',
  'Wednesday' => 'Quarta',
  'Thursday' => 'Quinta',
  'Friday' => 'Sexta',
  'Saturday' => 'Sábado',
  'Sunday' => 'Domingo',
];

$pageData = json_encode(
  [
    'recoveryModal' => $recoveryModal,
    'recoveryError' => $recoveryError,
    'recoveryData' => $recoveryData,
  ],
  JSON_THROW_ON_ERROR
    | JSON_HEX_TAG
    | JSON_HEX_APOS
    | JSON_HEX_AMP
    | JSON_HEX_QUOT
);
?>

<style>
  .weekly-planning-page {
    --weekly-border: #dfe6ee;
    --weekly-muted: #64748b;
    --weekly-surface: #ffffff;
    --weekly-soft: #f7f9fc;
    --weekly-blue: #0b63ce;
    --weekly-blue-soft: #eef6ff;
    --weekly-green: #15803d;
  }

  .weekly-navigation {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .weekly-panel-title-wrap {
    display: grid;
    gap: 3px;
  }

  .weekly-panel-subtitle {
    color: var(--weekly-muted);
    font-size: .74rem;
    font-weight: 600;
  }

  .weekly-board-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    padding: 10px 14px;
    border-bottom: 1px solid var(--weekly-border);
    background: #fbfdff;
  }

  .weekly-board-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 28px;
    padding: 5px 9px;
    border: 1px solid #dbe5f0;
    border-radius: 999px;
    background: #fff;
    color: #475569;
    font-size: .69rem;
    font-weight: 800;
  }

  .weekly-board-chip i {
    color: var(--weekly-blue);
  }

  .weekly-planning-scroll {
    position: relative;
    overflow: auto;
    max-width: 100%;
    overscroll-behavior-inline: contain;
    scrollbar-gutter: stable;
  }

  .weekly-resource-grid {
    display: grid;
    grid-template-columns: 154px repeat(var(--weekly-team-count), minmax(270px, 1fr));
    align-items: stretch;
    background: var(--weekly-border);
    gap: 1px;
  }

  .weekly-resource-corner,
  .weekly-team-column-header {
    position: sticky;
    top: 0;
    z-index: 12;
    min-height: 76px;
    background: #f8fbff;
    border-bottom: 1px solid #cfd9e5;
  }

  .weekly-resource-corner {
    left: 0;
    z-index: 14;
    display: grid;
    place-items: center;
    padding: 12px;
    color: #475569;
    text-align: center;
  }

  .weekly-resource-corner strong {
    color: #0f172a;
    font-size: .76rem;
    letter-spacing: .06em;
    text-transform: uppercase;
  }

  .weekly-resource-corner span {
    color: #94a3b8;
    font-size: .66rem;
    font-weight: 700;
  }

  .weekly-team-column-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 13px;
  }

  .weekly-team-avatar {
    display: grid;
    place-items: center;
    flex: 0 0 38px;
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: #e8f2ff;
    color: #0b63ce;
    font-size: .75rem;
    font-weight: 900;
    letter-spacing: .02em;
  }

  .weekly-team-header-text {
    min-width: 0;
    display: grid;
    gap: 3px;
  }

  .weekly-team-header-text strong {
    overflow: hidden;
    color: #10233f;
    font-size: .8rem;
    font-weight: 900;
    line-height: 1.22;
    text-overflow: ellipsis;
    text-transform: uppercase;
  }

  .weekly-team-header-text span {
    color: #64748b;
    font-size: .68rem;
    font-weight: 700;
  }

  .weekly-day-label {
    position: sticky;
    left: 0;
    z-index: 9;
    display: flex;
    min-height: 156px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 3px;
    padding: 14px 10px;
    background: linear-gradient(160deg, #0d65c8, #1678e6);
    color: #fff;
    text-align: center;
  }

  .weekly-day-label.is-today {
    background: linear-gradient(160deg, #0754ad, #0d6fdb);
    box-shadow: inset -4px 0 0 #fbbf24;
  }

  .weekly-day-label strong {
    font-size: .88rem;
    font-weight: 900;
    letter-spacing: .025em;
    text-transform: uppercase;
  }

  .weekly-day-label .weekly-day-date {
    font-size: .8rem;
    font-weight: 800;
  }

  .weekly-today-badge {
    margin-top: 5px;
    padding: 3px 7px;
    border-radius: 999px;
    background: #fbbf24;
    color: #713f12;
    font-size: .58rem;
    font-weight: 900;
    letter-spacing: .04em;
  }

  .weekly-team-cell {
    min-width: 0;
    min-height: 156px;
    padding: 8px;
    background: #fff;
  }

  .weekly-team-cell.is-today {
    background: #fbfdff;
  }

  .weekly-team-cell-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 8px;
    align-items: start;
  }

  .weekly-cell-empty {
    display: flex;
    min-height: 138px;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: 1px dashed #d9e2ec;
    border-radius: 10px;
    background: #fafcfe;
    color: #94a3b8;
    font-size: .7rem;
    font-weight: 700;
  }

  .weekly-cell-empty i {
    color: #22a06b;
  }

  .weekly-planning-card {
    position: relative;
    display: grid;
    gap: 6px;
    min-width: 0;
    padding: 10px 11px;
    border: 1px solid #dfe6ee;
    border-left: 4px solid #2563eb;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 3px 10px rgba(15, 23, 42, .055);
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
  }

  .weekly-planning-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 7px 17px rgba(15, 23, 42, .09);
  }

  .weekly-planning-card.is-awaiting {
    border-left-color: #f59e0b;
  }

  .weekly-planning-card.is-confirmed,
  .weekly-planning-card.is-order-finalized {
    border-left-color: #16a34a;
  }

  .weekly-planning-card.is-order-canceled {
    border-left-color: #94a3b8;
    opacity: .78;
  }

  .weekly-planning-card.is-urgent {
    box-shadow: 0 0 0 2px rgba(220, 38, 38, .1);
  }

  .weekly-planning-card.is-current {
    border-color: #22a06b;
    border-left-color: #16a34a;
    box-shadow: 0 0 0 2px rgba(22, 163, 74, .11);
  }

  .weekly-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
  }

  .weekly-card-time-block {
    min-width: 0;
    display: grid;
    gap: 2px;
  }

  .weekly-card-time {
    color: #0b63ce;
    font-size: .86rem;
    font-weight: 900;
    line-height: 1.15;
  }

  .weekly-card-code {
    color: #64748b;
    font-size: .64rem;
    font-weight: 800;
    letter-spacing: .025em;
  }

  .weekly-card-badges {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    flex-wrap: wrap;
  }

  .weekly-now-badge {
    display: inline-flex;
    align-items: center;
    min-height: 20px;
    padding: 3px 6px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    font-size: .58rem;
    font-weight: 900;
    letter-spacing: .04em;
  }

  .weekly-card-client {
    overflow: hidden;
    color: #0f172a;
    font-size: .82rem;
    font-weight: 900;
    line-height: 1.3;
    text-overflow: ellipsis;
  }

  .weekly-card-service {
    color: #334155;
    font-size: .73rem;
    font-weight: 650;
    line-height: 1.35;
  }

  .weekly-card-info {
    display: grid;
    gap: 3px;
    color: #64748b;
    font-size: .66rem;
  }

  .weekly-card-info span {
    display: flex;
    align-items: flex-start;
    gap: 5px;
    min-width: 0;
  }

  .weekly-card-info i {
    flex: 0 0 auto;
    margin-top: 1px;
  }

  .weekly-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 7px;
    flex-wrap: wrap;
    padding-top: 6px;
    border-top: 1px solid #eef2f7;
  }

  .weekly-card-actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
  }

  .weekly-confirm-button,
  .weekly-order-link {
    min-height: 30px;
    padding: 5px 8px;
    font-size: .66rem;
  }

  .weekly-priority {
    color: #64748b;
    font-size: .61rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .035em;
  }

  .weekly-priority.is-urgent {
    color: #dc2626;
  }

  .weekly-confirm-summary {
    display: grid;
    gap: 10px;
  }

  .weekly-confirm-item {
    display: grid;
    gap: 3px;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #f8fafc;
  }

  .weekly-confirm-item span {
    color: #64748b;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
  }

  .weekly-confirm-item strong {
    color: #0f172a;
    font-size: .86rem;
  }

  @media (max-width: 991.98px) {
    .weekly-resource-grid {
      grid-template-columns: 122px repeat(var(--weekly-team-count), minmax(250px, 1fr));
    }

    .weekly-day-label {
      min-height: 148px;
    }
  }

  @media (max-width: 767.98px) {
    .weekly-navigation {
      width: 100%;
    }

    .weekly-navigation > * {
      flex: 1 1 auto;
      text-align: center;
    }

    .weekly-resource-grid {
      grid-template-columns: 96px repeat(var(--weekly-team-count), minmax(238px, 1fr));
    }

    .weekly-resource-corner {
      padding: 8px 5px;
    }

    .weekly-resource-corner span {
      display: none;
    }

    .weekly-day-label {
      min-height: 142px;
      padding-inline: 6px;
    }

    .weekly-day-label strong {
      font-size: .72rem;
    }

    .weekly-team-column-header {
      min-height: 68px;
      padding: 9px;
    }

    .weekly-team-avatar {
      flex-basis: 32px;
      width: 32px;
      height: 32px;
      border-radius: 10px;
    }

    .weekly-team-cell {
      min-height: 142px;
      padding: 6px;
    }
  }
</style>

<div class="page-body weekly-planning-page">
  <?php

    metric_grid([
        [
            'Atendimentos na semana',
            (string) $summary['total'],
            'bi-calendar-week',
            '#2563EB',
            'planejados e OS',
        ],
        [
            'Aguardando confirmação',
            (string) $summary['awaiting'],
            'bi-hourglass-split',
            '#D97706',
            'ainda sem OS',
        ],
        [
            'Ordens de Serviço',
            (string) $summary['orders'],
            'bi-clipboard-check',
            '#15803D',
            'agendadas na semana',
        ],
        [
            'Urgentes pendentes',
            (string) $summary['urgent'],
            'bi-exclamation-triangle',
            '#DC2626',
            'prioridade',
        ],
    ]);
  ?>

  <form
    class="filter-bar"
    method="get"
    action="painel-semanal.php"
    data-live-filter="weekly-planning"
    data-live-regions="metrics results">
    <input
      type="hidden"
      name="week"
      value="<?= h(
                $weekStart->format('Y-m-d')
              ) ?>">

    <div class="search-wrap">
      <i class="bi bi-search"></i>

      <input
        class="search-input"
        type="search"
        name="search"
        value="<?= h($search) ?>"
        maxlength="150"
        placeholder="Cliente, serviço, código ou OS"
        aria-label="Pesquisar serviços semanais">
    </div>

    <select
      class="filter-select"
      name="status"
      aria-label="Status">
      <option value="">
    Todos os registros
</option>

<option
    value="aguardando_confirmacao"
    <?= $status === 'aguardando_confirmacao' ? 'selected' : '' ?>
>
    Aguardando confirmação
</option>

<option
    value="ordens"
    <?= $status === 'ordens' ? 'selected' : '' ?>
>
    Todas as Ordens de Serviço
</option>

<option
    value="agendada"
    <?= $status === 'agendada' ? 'selected' : '' ?>
>
    Agendadas
</option>

<option
    value="em_deslocamento"
    <?= $status === 'em_deslocamento' ? 'selected' : '' ?>
>
    Em deslocamento
</option>

<option
    value="em_execucao"
    <?= $status === 'em_execucao' ? 'selected' : '' ?>
>
    Em execução
</option>

<option
    value="aguardando_peca"
    <?= $status === 'aguardando_peca' ? 'selected' : '' ?>
>
    Aguardando peça
</option>

<option
    value="finalizada"
    <?= $status === 'finalizada' ? 'selected' : '' ?>
>
    Finalizadas
</option>

<option
    value="cancelada"
    <?= $status === 'cancelada' ? 'selected' : '' ?>
>
    Canceladas
</option>
    </select>

    <select
      class="filter-select"
      name="employee_id"
      aria-label="Funcionário">
      <option value="">
        Todos os funcionários
      </option>

      <?php
      weekly_employee_options(
        $employees,
        $employeeId
      );
      ?>
    </select>

    <button
      class="btn-filter btn-filter-primary"
      type="submit">
      <i class="bi bi-funnel"></i>
      Filtrar
    </button>

    <a
      class="btn-filter btn-filter-ghost"
      href="painel-semanal.php?week=<?= h(
                                      $weekStart->format('Y-m-d')
                                    ) ?>"
      data-live-filter-clear>
      <i class="bi bi-x-lg"></i>
      Limpar
    </a>
  </form>

  <section
    class="panel"
    data-live-region="results">
    <div class="panel-header">
      <div class="weekly-panel-title-wrap">
        <div class="panel-title">
          <i class="bi bi-calendar-week"></i>
          Serviços da semana —
          <?= h($weekStart->format('d/m/Y')) ?>
          a
          <?= h($weekEnd->modify('-1 day')->format('d/m/Y')) ?>
        </div>
        <span class="weekly-panel-subtitle">
          Cada coluna representa a equipe responsável; os serviços ficam organizados pelo horário.
        </span>
      </div>

      <nav
        class="weekly-navigation"
        aria-label="Navegação semanal">
        <a
          class="btn-filter btn-filter-ghost"
          href="painel-semanal.php?week=<?= h(
                                          $previousWeek->format('Y-m-d')
                                        ) ?>">
          <i class="bi bi-chevron-left"></i>
          Anterior
        </a>

        <a
          class="btn-filter btn-filter-primary"
          href="painel-semanal.php?week=<?= h(
                                          date('Y-m-d')
                                        ) ?>">
          Hoje
        </a>

        <a
          class="btn-filter btn-filter-ghost"
          href="painel-semanal.php?week=<?= h(
                                          $nextWeek->format('Y-m-d')
                                        ) ?>">
          Próxima
          <i class="bi bi-chevron-right"></i>
        </a>
      </nav>
    </div>

    <div class="weekly-board-meta" aria-label="Resumo do quadro semanal">
      <span class="weekly-board-chip">
        <i class="bi bi-people-fill"></i>
        <?= h((string) $visibleTeamCount) ?>
        <?= $visibleTeamCount === 1 ? 'equipe na agenda' : 'equipes na agenda' ?>
      </span>

      <span class="weekly-board-chip">
        <i class="bi bi-clock-history"></i>
        Horários organizados por equipe
      </span>

      <span class="weekly-board-chip">
        <i class="bi bi-check-circle"></i>
        Célula livre = sem atendimento
      </span>
    </div>

    <?php if ($teamColumns === []): ?>
      <div class="weekly-empty py-5">
        Nenhum atendimento encontrado para os filtros desta semana.
      </div>
    <?php else: ?>
      <div
        class="weekly-planning-scroll"
        role="region"
        aria-label="Quadro semanal separado por funcionário e equipe"
        tabindex="0">
        <div
          class="weekly-resource-grid"
          style="--weekly-team-count: <?= h((string) $boardColumnCount) ?>; min-width: <?= h((string) $boardMinWidth) ?>px;">
          <div class="weekly-resource-corner">
            <strong>Dia</strong>
            <span>Semana</span>
          </div>

          <?php foreach ($teamColumns as $teamColumn): ?>
            <?php
            $teamLabel = (string) $teamColumn['label'];
            $teamNames = array_values(
              array_filter(
                array_map(
                  'trim',
                  explode('/', $teamLabel)
                ),
                static fn(string $name): bool => $name !== ''
              )
            );

            $initials = '';
            foreach (array_slice($teamNames, 0, 2) as $name) {
              $parts = preg_split('/\\s+/u', trim($name)) ?: [];
              $initials .= mb_strtoupper(
                mb_substr(
                  (string) ($parts[0] ?? ''),
                  0,
                  1,
                  'UTF-8'
                ),
                'UTF-8'
              );
            }
            if ($initials === '') {
              $initials = '—';
            }
            ?>

            <header class="weekly-team-column-header">
              <span class="weekly-team-avatar" aria-hidden="true">
                <?= h($initials) ?>
              </span>

              <div class="weekly-team-header-text">
                <strong title="<?= h($teamLabel) ?>">
                  <?= h($teamLabel) ?>
                </strong>

                <span>
                  <?= h((string) $teamColumn['appointments']) ?>
                  <?= (int) $teamColumn['appointments'] === 1 ? 'atendimento' : 'atendimentos' ?>
                </span>
              </div>
            </header>
          <?php endforeach; ?>

          <?php for ($dayIndex = 0; $dayIndex < 7; $dayIndex++): ?>
            <?php
            $day = $weekStart->modify(
              '+' . $dayIndex . ' days'
            );
            $dateKey = $day->format('Y-m-d');
            $isToday = $dateKey === date('Y-m-d');
            ?>

            <div class="weekly-day-label<?= $isToday ? ' is-today' : '' ?>">
              <strong>
                <?= h($days[$day->format('l')]) ?>
              </strong>

              <span class="weekly-day-date">
                <?= h($day->format('d/m')) ?>
              </span>

              <?php if ($isToday): ?>
                <span class="weekly-today-badge">HOJE</span>
              <?php endif; ?>
            </div>

            <?php foreach ($teamColumns as $teamKey => $teamColumn): ?>
              <?php
              $cellRecords =
                $weeklyTeamMatrix[$dateKey][$teamKey]
                ?? [];
              ?>

              <div class="weekly-team-cell<?= $isToday ? ' is-today' : '' ?>">
                <?php if ($cellRecords === []): ?>
                  <div class="weekly-cell-empty">
                    <i class="bi bi-check2-circle"></i>
                    Livre na agenda
                  </div>
                <?php else: ?>
                  <div class="weekly-team-cell-content">
                    <?php foreach ($cellRecords as $record): ?>
                      <?php if (($record['kind'] ?? '') === 'planning'): ?>
                        <?php
                        $planning = $record['planning'];
                        $planningId = weekly_value(
                          $planning,
                          'id'
                        );
                        $planningCode = weekly_value(
                          $planning,
                          'codigo',
                          'SEM'
                        );
                        $priority = weekly_value(
                          $planning,
                          'prioridade',
                          'media'
                        );
                        $clientName = weekly_value(
                          $planning,
                          'cliente_nome',
                          'Cliente não informado'
                        );
                        $serviceName = weekly_value(
                          $planning,
                          'servico_nome',
                          'Serviço não informado'
                        );
                        $start = weekly_value(
                          $planning,
                          'agendado_inicio'
                        );
                        $end = weekly_value(
                          $planning,
                          'agendado_fim'
                        );
                        $location = weekly_value(
                          $planning,
                          'local_servico'
                        );
                        $teamName = (string) (
                          $record['team_label']
                          ?? weekly_team_name($planning)
                        );
                        $isCurrent = weekly_is_current_slot(
                          $start,
                          $end
                        );
                        ?>

                        <article class="weekly-planning-card is-awaiting<?= $priority === 'urgente' ? ' is-urgent' : '' ?><?= $isCurrent ? ' is-current' : '' ?>">
                          <div class="weekly-card-header">
                            <div class="weekly-card-time-block">
                              <span class="weekly-card-time">
                                <?= h(weekly_time_range($start, $end)) ?>
                              </span>
                              <strong class="weekly-card-code">
                                <?= h($planningCode) ?>
                              </strong>
                            </div>

                            <div class="weekly-card-badges">
                              <?php if ($isCurrent): ?>
                                <span class="weekly-now-badge">AGORA</span>
                              <?php endif; ?>
                              <span class="badge-soft badge-amber">
                                Pendente
                              </span>
                            </div>
                          </div>

                          <div class="weekly-card-client">
                            <?= h($clientName) ?>
                          </div>

                          <div class="weekly-card-service">
                            <?= h($serviceName) ?>
                          </div>

                          <div class="weekly-card-info">
                            <?php if ($location !== ''): ?>
                              <span>
                                <i class="bi bi-geo-alt"></i>
                                <?= h($location) ?>
                              </span>
                            <?php endif; ?>

                            <span>
                              <i class="bi bi-people"></i>
                              <?= h($teamName) ?>
                            </span>
                          </div>

                          <div class="weekly-card-footer">
                            <span class="weekly-priority<?= $priority === 'urgente' ? ' is-urgent' : '' ?>">
                              <?= h(weekly_priority_label($priority)) ?>
                            </span>

                            <?php if ($canConfirm): ?>
                              <button
                                class="btn-filter btn-filter-primary weekly-confirm-button js-weekly-confirm"
                                type="button"
                                data-planning-id="<?= h($planningId) ?>"
                                data-planning-code="<?= h($planningCode) ?>"
                                data-client-name="<?= h($clientName) ?>"
                                data-service-name="<?= h($serviceName) ?>"
                                data-scheduled-start="<?= h($start) ?>"
                                data-scheduled-end="<?= h($end) ?>"
                                data-team-name="<?= h($teamName) ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#modal-week-confirm">
                                <i class="bi bi-check2-circle"></i>
                                Confirmar
                              </button>
                            <?php endif; ?>
                          </div>
                        </article>
                      <?php elseif (($record['kind'] ?? '') === 'order'): ?>
                        <?php
                        $order = $record['order'];
                        $orderStatus = $order->status();
                        $orderPriority = $order->priority();
                        $orderCardClass =
                          'weekly-planning-card is-order';

                        if ($orderStatus === 'finalizada') {
                          $orderCardClass .=
                            ' is-order-finalized';
                        }

                        if ($orderStatus === 'cancelada') {
                          $orderCardClass .=
                            ' is-order-canceled';
                        }

                        $orderStart = $order->scheduledStart();
                        $orderEnd = $order->scheduledEnd();
                        $isCurrent = weekly_is_current_slot(
                          $orderStart,
                          $orderEnd
                        );
                        ?>

                        <article class="<?= h($orderCardClass) ?><?= $orderPriority === 'urgente' ? ' is-urgent' : '' ?><?= $isCurrent ? ' is-current' : '' ?>">
                          <div class="weekly-card-header">
                            <div class="weekly-card-time-block">
                              <span class="weekly-card-time">
                                <?= h(weekly_time_range($orderStart, $orderEnd)) ?>
                              </span>
                              <strong class="weekly-card-code">
                                <?= h($order->displayNumber()) ?>
                              </strong>
                            </div>

                            <div class="weekly-card-badges">
                              <?php if ($isCurrent): ?>
                                <span class="weekly-now-badge">AGORA</span>
                              <?php endif; ?>

                              <span class="badge-soft badge-<?= h(weekly_order_status_badge($orderStatus)) ?>">
                                <?= h(weekly_order_status_label($orderStatus)) ?>
                              </span>
                            </div>
                          </div>

                          <div class="weekly-card-client">
                            <?= h($order->clientName()) ?>
                          </div>

                          <div class="weekly-card-service">
                            <?= h(
                              $order->mainService()
                              ?? 'Serviço não informado'
                            ) ?>
                          </div>

                          <div class="weekly-card-info">
                            <?php if (
                              $order->equipmentLocation() !== null
                              && trim($order->equipmentLocation()) !== ''
                            ): ?>
                              <span>
                                <i class="bi bi-geo-alt"></i>
                                <?= h($order->equipmentLocation()) ?>
                              </span>
                            <?php endif; ?>

                            <span>
                              <i class="bi bi-cash-coin"></i>
                              <?= money($order->total()) ?>
                            </span>
                          </div>

                          <div class="weekly-card-footer">
                            <span class="weekly-priority<?= $orderPriority === 'urgente' ? ' is-urgent' : '' ?>">
                              <?= h($order->displayPriority()) ?>
                            </span>

                            <?php if ($canViewOrder): ?>
                              <a
                                class="btn-filter btn-filter-ghost weekly-order-link"
                                href="ordens-servico.php?search=<?= h(rawurlencode($order->displayNumber())) ?>">
                                <i class="bi bi-box-arrow-up-right"></i>
                                Abrir OS
                              </a>
                            <?php endif; ?>
                          </div>
                        </article>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
    </div>
  </section>
</div>

<?php if ($canCreate): ?>
  <div
    class="modal fade"
    id="modal-week-create"
    tabindex="-1"
    aria-hidden="true"
    aria-labelledby="week-create-title">
    <div
      class="modal-dialog modal-lg modal-dialog-scrollable">
      <form
        class="modal-content visual-modal"
        method="post"
        action="actions/painel-semanal-servico-salvar.php"
        autocomplete="off">
        <div class="modal-header">
          <div>
            <h2
              class="modal-title fs-5"
              id="week-create-title">
              Planejar serviço
            </h2>

            <p class="text-muted small mb-0">
              Este cadastro não criará uma
              Ordem de Serviço.
            </p>
          </div>

          <button
            class="btn-close"
            type="button"
            data-bs-dismiss="modal"
            aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <?= $csrf->field() ?>

          <?php
          weekly_return_fields(
            $weekStart
          );
          ?>

          <?php
          if (
            $recoveryModal === 'create'
            && $recoveryError !== ''
          ):
          ?>
            <div
              class="alert alert-danger"
              role="alert">
              <?= h($recoveryError) ?>
            </div>
          <?php endif; ?>

          <div
            class="alert alert-info"
            role="note">
            <i class="bi bi-info-circle"></i>

            O serviço ficará como
            <strong>
              Aguardando confirmação
            </strong>.

            A OS somente será criada quando o
            botão Confirmar for acionado.
          </div>

          <section class="form-section">
            <h3 class="form-section-title">
              Cliente e serviço
            </h3>

            <div class="form-row">
              <div class="form-group">
                <label
                  class="form-label"
                  for="week-create-client">
                  Cliente
                </label>

                <select
                  class="form-control-os"
                  id="week-create-client"
                  name="client_id"
                  required>
                  <option value="">
                    Selecione
                  </option>

                  <?php
                  weekly_client_options(
                    $clients,
                    weekly_recovery_value(
                      $createRecoveryData,
                      'client_id'
                    )
                  );
                  ?>
                </select>
              </div>

              <div class="form-group">
                <label
                  class="form-label"
                  for="week-create-service">
                  Serviço
                </label>

                <select
                  class="form-control-os"
                  id="week-create-service"
                  name="service_id"
                  required>
                  <option value="">
                    Selecione
                  </option>

                  <?php
                  weekly_service_options(
                    $services,
                    weekly_recovery_value(
                      $createRecoveryData,
                      'service_id'
                    )
                  );
                  ?>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label
                  class="form-label"
                  for="week-create-priority">
                  Prioridade
                </label>

                <?php
                $recoveredPriority =
                  weekly_recovery_value(
                    $createRecoveryData,
                    'priority',
                    weekly_recovery_value(
                      $createRecoveryData,
                      'prioridade',
                      'media'
                    )
                  );
                ?>

                <select
                  class="form-control-os"
                  id="week-create-priority"
                  name="priority"
                  required>
                  <?php
                  foreach (
                    [
                      'baixa',
                      'media',
                      'alta',
                      'urgente',
                    ]
                    as $priorityOption
                  ):
                  ?>
                    <option
                      value="<?= h($priorityOption) ?>"
                      <?= $recoveredPriority === $priorityOption ? 'selected' : '' ?>>
                      <?= h(
                        weekly_priority_label(
                          $priorityOption
                        )
                      ) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label
                  class="form-label"
                  for="week-create-location">
                  Local do serviço
                </label>

                <input
                  class="form-control-os"
                  id="week-create-location"
                  name="equipment_location"
                  value="<?= h(
                            weekly_recovery_value(
                              $createRecoveryData,
                              'equipment_location',
                              weekly_recovery_value(
                                $createRecoveryData,
                                'local_servico'
                              )
                            )
                          ) ?>"
                  maxlength="150">
              </div>
            </div>
          </section>

          <section class="form-section">
            <h3 class="form-section-title">
              Data e horário
            </h3>

            <div class="form-row">
              <div class="form-group">
                <label
                  class="form-label"
                  for="week-create-start">
                  Início
                </label>

                <input
                  class="form-control-os"
                  id="week-create-start"
                  name="agendado_inicio"
                  type="datetime-local"
                  value="<?= h(
                            str_replace(
                              ' ',
                              'T',
                              weekly_recovery_value(
                                $createRecoveryData,
                                'agendado_inicio'
                              )
                            )
                          ) ?>"
                  required>
              </div>

              <div class="form-group">
                <label
                  class="form-label"
                  for="week-create-end">
                  Fim
                </label>

                <input
                  class="form-control-os"
                  id="week-create-end"
                  name="agendado_fim"
                  type="datetime-local"
                  value="<?= h(
                            str_replace(
                              ' ',
                              'T',
                              weekly_recovery_value(
                                $createRecoveryData,
                                'agendado_fim'
                              )
                            )
                          ) ?>"
                  required>
              </div>
            </div>
          </section>

          <section class="form-section">
            <h3 class="form-section-title">
              Equipe
            </h3>

            <div class="form-row">
              <div class="form-group">
                <label
                  class="form-label"
                  for="week-create-primary">
                  Responsável principal
                </label>

                <select
                  class="form-control-os js-week-primary-employee"
                  id="week-create-primary"
                  name="funcionario_principal_id"
                  required>
                  <option value="">
                    Selecione
                  </option>

                  <?php
                  weekly_employee_options(
                    $employees,
                    weekly_recovery_value(
                      $createRecoveryData,
                      'funcionario_principal_id'
                    )
                  );
                  ?>
                </select>
              </div>

              <div class="form-group">
                <label
                  class="form-label"
                  for="week-create-support">
                  Funcionário de apoio
                </label>

                <select
                  class="form-control-os js-week-support-employee"
                  id="week-create-support"
                  name="funcionario_apoio_id">
                  <option value="">
                    Sem apoio
                  </option>

                  <?php
                  weekly_employee_options(
                    $employees,
                    weekly_recovery_value(
                      $createRecoveryData,
                      'funcionario_apoio_id'
                    )
                  );
                  ?>
                </select>
              </div>
            </div>
          </section>

          <section class="form-section">
            <h3 class="form-section-title">
              Observação
            </h3>

            <div class="form-group mb-0">
              <label
                class="form-label"
                for="week-create-notes">
                Informações adicionais
              </label>

              <textarea
                class="form-control-os"
                id="week-create-notes"
                name="notes"
                maxlength="1000"
                rows="3"><?= h(
                            weekly_recovery_value(
                              $createRecoveryData,
                              'notes',
                              weekly_recovery_value(
                                $createRecoveryData,
                                'observacao'
                              )
                            )
                          ) ?></textarea>
            </div>
          </section>
        </div>

        <div class="modal-footer">
          <button
            class="btn-modal-cancel"
            type="button"
            data-bs-dismiss="modal">
            Cancelar
          </button>

          <button
            class="btn-modal-save"
            type="submit">
            <i class="bi bi-calendar-plus"></i>
            Cadastrar planejamento
          </button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($canConfirm): ?>
  <div
    class="modal fade"
    id="modal-week-confirm"
    tabindex="-1"
    aria-hidden="true"
    aria-labelledby="week-confirm-title">
    <div
      class="modal-dialog modal-dialog-centered">
      <form
        class="modal-content visual-modal"
        method="post"
        action="actions/painel-semanal-servico-confirmar.php">
        <div class="modal-header">
          <div>
            <h2
              class="modal-title fs-5"
              id="week-confirm-title">
              Confirmar e gerar OS
            </h2>

            <p class="text-muted small mb-0">
              Esta ação criará a Ordem de Serviço.
            </p>
          </div>

          <button
            class="btn-close"
            type="button"
            data-bs-dismiss="modal"
            aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <?= $csrf->field() ?>

          <?php
          weekly_return_fields(
            $weekStart
          );
          ?>

          <input
            type="hidden"
            name="id"
            id="week-confirm-id">

          <div
            class="alert alert-warning"
            role="alert">
            <i class="bi bi-exclamation-triangle"></i>

            Após confirmar, o planejamento será
            convertido em uma Ordem de Serviço
            agendada.
          </div>

          <div class="weekly-confirm-summary">
            <div class="weekly-confirm-item">
              <span>Planejamento</span>

              <strong
                id="week-confirm-code">
                —
              </strong>
            </div>

            <div class="weekly-confirm-item">
              <span>Cliente</span>

              <strong
                id="week-confirm-client">
                —
              </strong>
            </div>

            <div class="weekly-confirm-item">
              <span>Serviço</span>

              <strong
                id="week-confirm-service">
                —
              </strong>
            </div>

            <div class="weekly-confirm-item">
              <span>Data e horário</span>

              <strong
                id="week-confirm-schedule">
                —
              </strong>
            </div>

            <div class="weekly-confirm-item">
              <span>Equipe</span>

              <strong
                id="week-confirm-team">
                —
              </strong>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button
            class="btn-modal-cancel"
            type="button"
            data-bs-dismiss="modal">
            Voltar
          </button>

          <button
            class="btn-modal-save"
            type="submit">
            <i class="bi bi-check2-circle"></i>
            Confirmar e gerar OS
          </button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<script
  type="application/json"
  id="weekly-page-data">
  <?= $pageData ?>
</script>