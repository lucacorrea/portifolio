<?php

declare(strict_types=1);

namespace App\Schedule\Service;

use App\ServiceOrder\DTO\ServiceOrderFormData;
use App\ServiceOrder\DTO\ServiceOrderScheduleData;
use App\ServiceOrder\DTO\ServiceOrderTeamData;
use App\ServiceOrder\Service\ServiceOrderManagementService;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

final class WeeklyServicePlanningService
{
    private const PRIORITIES = [
        'baixa',
        'media',
        'alta',
        'urgente',
    ];

    private const STATUSES = [
        'aguardando_confirmacao',
        'confirmado',
        'cancelado',
    ];

    public function __construct(
        private readonly PDO $connection,
        private readonly ServiceOrderManagementService $orders
    ) {
    }

    /**
     * Cadastra um serviço semanal sem criar Ordem de Serviço.
     *
     * @return array{
     *     id:int,
     *     code:string,
     *     status:string
     * }
     */
    public function create(
        array $data,
        int $userId
    ): array {
        if ($userId <= 0) {
            throw new InvalidArgumentException(
                'Usuário inválido para cadastrar o serviço.'
            );
        }

        return $this->transactional(
            function () use (
                $data,
                $userId
            ): array {
                $clientId = $this->positiveInt(
                    $data['client_id']
                        ?? $data['cliente_id']
                        ?? null,
                    'cliente'
                );

                $serviceId = $this->positiveInt(
                    $data['service_id']
                        ?? $data['servico_id']
                        ?? null,
                    'serviço'
                );

                $priority = trim(
                    (string) (
                        $data['priority']
                        ?? $data['prioridade']
                        ?? 'media'
                    )
                );

                if (
                    !in_array(
                        $priority,
                        self::PRIORITIES,
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Prioridade inválida.'
                    );
                }

                $start = $this->dateTime(
                    $data['agendado_inicio']
                        ?? $data['scheduled_start']
                        ?? null,
                    'início'
                );

                $end = $this->dateTime(
                    $data['agendado_fim']
                        ?? $data['scheduled_end']
                        ?? null,
                    'fim'
                );

                if ($end <= $start) {
                    throw new InvalidArgumentException(
                        'O fim do serviço deve ser posterior ao início.'
                    );
                }

                $primaryEmployeeId =
                    $this->optionalPositiveInt(
                        $data['funcionario_principal_id']
                            ?? $data['primary_employee_id']
                            ?? null
                    );

                $supportEmployeeId =
                    $this->optionalPositiveInt(
                        $data['funcionario_apoio_id']
                            ?? $data['support_employee_id']
                            ?? null
                    );

                if (
                    $primaryEmployeeId === null
                    && $supportEmployeeId !== null
                ) {
                    throw new InvalidArgumentException(
                        'Informe o responsável principal antes do funcionário de apoio.'
                    );
                }

                if (
                    $primaryEmployeeId !== null
                    && $supportEmployeeId === $primaryEmployeeId
                ) {
                    throw new InvalidArgumentException(
                        'O responsável principal e o apoio devem ser funcionários diferentes.'
                    );
                }

                $local = $this->optionalText(
                    $data['equipment_location']
                        ?? $data['local_servico']
                        ?? null,
                    150
                );

                $notes = $this->optionalText(
                    $data['notes']
                        ?? $data['observacao']
                        ?? null,
                    1000
                );

                $this->lockActiveClient($clientId);
                $this->lockActiveService($serviceId);

                $employeeIds = array_values(
                    array_filter(
                        [
                            $primaryEmployeeId,
                            $supportEmployeeId,
                        ],
                        static fn (?int $id): bool =>
                            $id !== null
                    )
                );

                $this->validateEmployees(
                    $employeeIds
                );

                $this->assertNoScheduleConflict(
                    $employeeIds,
                    $start,
                    $end,
                    null
                );

                $statement = $this->connection->prepare(
                    'INSERT INTO servicos_semanais
                        (
                            codigo,
                            cliente_id,
                            servico_id,
                            prioridade,
                            local_servico,
                            agendado_inicio,
                            agendado_fim,
                            funcionario_principal_id,
                            funcionario_apoio_id,
                            observacao,
                            status,
                            criado_por
                        )
                     VALUES
                        (
                            NULL,
                            :client_id,
                            :service_id,
                            :priority,
                            :service_location,
                            :scheduled_start,
                            :scheduled_end,
                            :primary_employee_id,
                            :support_employee_id,
                            :notes,
                            "aguardando_confirmacao",
                            :created_by
                        )'
                );

                $statement->bindValue(
                    'client_id',
                    $clientId,
                    PDO::PARAM_INT
                );

                $statement->bindValue(
                    'service_id',
                    $serviceId,
                    PDO::PARAM_INT
                );

                $statement->bindValue(
                    'priority',
                    $priority
                );

                $statement->bindValue(
                    'service_location',
                    $local,
                    $local === null
                        ? PDO::PARAM_NULL
                        : PDO::PARAM_STR
                );

                $statement->bindValue(
                    'scheduled_start',
                    $start->format('Y-m-d H:i:s')
                );

                $statement->bindValue(
                    'scheduled_end',
                    $end->format('Y-m-d H:i:s')
                );

                $statement->bindValue(
                    'primary_employee_id',
                    $primaryEmployeeId,
                    $primaryEmployeeId === null
                        ? PDO::PARAM_NULL
                        : PDO::PARAM_INT
                );

                $statement->bindValue(
                    'support_employee_id',
                    $supportEmployeeId,
                    $supportEmployeeId === null
                        ? PDO::PARAM_NULL
                        : PDO::PARAM_INT
                );

                $statement->bindValue(
                    'notes',
                    $notes,
                    $notes === null
                        ? PDO::PARAM_NULL
                        : PDO::PARAM_STR
                );

                $statement->bindValue(
                    'created_by',
                    $userId,
                    PDO::PARAM_INT
                );

                $statement->execute();

                $planningId = (int) (
                    $this->connection->lastInsertId()
                );

                if ($planningId <= 0) {
                    throw new InvalidArgumentException(
                        'Não foi possível identificar o serviço cadastrado.'
                    );
                }

                $code = sprintf(
                    'SEM-%06d',
                    $planningId
                );

                $updateCode = $this->connection->prepare(
                    'UPDATE servicos_semanais
                        SET codigo = :code
                      WHERE id = :id
                        AND codigo IS NULL'
                );

                $updateCode->execute([
                    'id' => $planningId,
                    'code' => $code,
                ]);

                if ($updateCode->rowCount() !== 1) {
                    throw new InvalidArgumentException(
                        'Não foi possível gerar o código do serviço semanal.'
                    );
                }

                return [
                    'id' => $planningId,
                    'code' => $code,
                    'status' => 'aguardando_confirmacao',
                ];
            }
        );
    }

    /**
     * Confirma o planejamento e cria uma única OS.
     *
     * @return array{
     *     planning_id:int,
     *     planning_code:string,
     *     order_id:int,
     *     order_number:string
     * }
     */
    public function confirm(
        int $planningId,
        int $userId
    ): array {
        if ($planningId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException(
                'Serviço ou usuário inválido para confirmação.'
            );
        }

        return $this->transactional(
            function () use (
                $planningId,
                $userId
            ): array {
                $planning = $this->lockPlanning(
                    $planningId
                );

                if (
                    $planning['status']
                    === 'confirmado'
                ) {
                    $orderId = (int) (
                        $planning['ordem_servico_id']
                        ?? 0
                    );

                    if ($orderId <= 0) {
                        throw new InvalidArgumentException(
                            'O serviço está confirmado, mas não possui OS vinculada.'
                        );
                    }

                    $order = $this->orders->getOrder(
                        $orderId
                    );

                    return [
                        'planning_id' => $planningId,
                        'planning_code' => (string) (
                            $planning['codigo']
                            ?? ''
                        ),
                        'order_id' => $order->id(),
                        'order_number' => $order->displayNumber(),
                    ];
                }

                if (
                    $planning['status']
                    === 'cancelado'
                ) {
                    throw new InvalidArgumentException(
                        'Serviço cancelado não pode gerar Ordem de Serviço.'
                    );
                }

                if (
                    $planning['status']
                    !== 'aguardando_confirmacao'
                ) {
                    throw new InvalidArgumentException(
                        'O status atual não permite confirmar este serviço.'
                    );
                }

                $clientId = (int) (
                    $planning['cliente_id']
                    ?? 0
                );

                $serviceId = (int) (
                    $planning['servico_id']
                    ?? 0
                );

                $primaryEmployeeId =
                    $this->nullableInt(
                        $planning[
                            'funcionario_principal_id'
                        ] ?? null
                    );

                $supportEmployeeId =
                    $this->nullableInt(
                        $planning[
                            'funcionario_apoio_id'
                        ] ?? null
                    );

                if ($primaryEmployeeId === null) {
                    throw new InvalidArgumentException(
                        'Defina o responsável principal antes de confirmar e gerar a OS.'
                    );
                }

                $client = $this->lockActiveClient(
                    $clientId
                );

                $service = $this->lockActiveService(
                    $serviceId
                );

                $start = new DateTimeImmutable(
                    (string) $planning[
                        'agendado_inicio'
                    ]
                );

                $end = new DateTimeImmutable(
                    (string) $planning[
                        'agendado_fim'
                    ]
                );

                $employeeIds = array_values(
                    array_filter(
                        [
                            $primaryEmployeeId,
                            $supportEmployeeId,
                        ],
                        static fn (?int $id): bool =>
                            $id !== null
                    )
                );

                $this->validateEmployees(
                    $employeeIds
                );

                $this->assertNoScheduleConflict(
                    $employeeIds,
                    $start,
                    $end,
                    $planningId
                );

                $team = ServiceOrderTeamData::fromArray([
                    'funcionario_principal_id' =>
                        $primaryEmployeeId,

                    'funcionario_apoio_id' =>
                        $supportEmployeeId,
                ]);

                $schedule =
                    new ServiceOrderScheduleData(
                        $start,
                        $end
                    );

                $form = ServiceOrderFormData::fromArray([
                    'client_id' => $clientId,
                    'status' => 'agendada',

                    'priority' => (string) (
                        $planning['prioridade']
                        ?? 'media'
                    ),

                    'equipment_location' =>
                        $planning['local_servico']
                        ?? null,

                    'notes' =>
                        $planning['observacao']
                        ?? null,

                    'items' => [
                        [
                            'type' => 'servico',
                            'origin' => 'manual',
                            'reference_id' => $serviceId,
                            'description' => (string) (
                                $service['nome']
                                ?? 'Serviço'
                            ),
                            'unit' => 'un',
                            'quantity' => '1',
                            'unit_price' => (string) (
                                $service['valor']
                                ?? '0.00'
                            ),
                            'discount' => '0.00',
                        ],
                    ],
                ]);

                /*
                 * A criação da OS usa o mesmo fluxo oficial
                 * do restante do sistema.
                 */
                $order = $this->orders->createOrder(
                    $form,
                    $team,
                    $schedule
                );

                $update = $this->connection->prepare(
                    'UPDATE servicos_semanais
                        SET status = "confirmado",
                            ordem_servico_id = :order_id,
                            confirmado_em = CURRENT_TIMESTAMP,
                            confirmado_por = :confirmed_by
                      WHERE id = :id
                        AND status = "aguardando_confirmacao"
                        AND ordem_servico_id IS NULL'
                );

                $update->execute([
                    'id' => $planningId,
                    'order_id' => $order->id(),
                    'confirmed_by' => $userId,
                ]);

                if ($update->rowCount() !== 1) {
                    throw new InvalidArgumentException(
                        'O serviço foi alterado por outro usuário durante a confirmação.'
                    );
                }

                return [
                    'planning_id' => $planningId,

                    'planning_code' => (string) (
                        $planning['codigo']
                        ?? ''
                    ),

                    'order_id' => $order->id(),
                    'order_number' => $order->displayNumber(),
                ];
            }
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listBetween(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        array $filters = []
    ): array {
        if ($end <= $start) {
            throw new InvalidArgumentException(
                'Período semanal inválido.'
            );
        }

        $where = [
            'planejamento.agendado_inicio >= :start',
            'planejamento.agendado_inicio < :end',
        ];

        $parameters = [
            'start' => $start->format(
                'Y-m-d H:i:s'
            ),

            'end' => $end->format(
                'Y-m-d H:i:s'
            ),
        ];

        $status = trim(
            (string) (
                $filters['status']
                ?? ''
            )
        );

        if ($status !== '') {
            if (
                !in_array(
                    $status,
                    self::STATUSES,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Status de planejamento inválido.'
                );
            }

            $where[] =
                'planejamento.status = :status';

            $parameters['status'] = $status;
        } else {
            $where[] =
                'planejamento.status <> "cancelado"';
        }

        $employeeId = $this->optionalPositiveInt(
            $filters['employee_id']
                ?? null
        );

        if ($employeeId !== null) {
            $where[] = '(
                planejamento.funcionario_principal_id
                    = :employee_id
                OR
                planejamento.funcionario_apoio_id
                    = :employee_id
            )';

            $parameters['employee_id'] =
                $employeeId;
        }

        $search = trim(
            (string) (
                $filters['search']
                ?? ''
            )
        );

        if ($search !== '') {
            if (
                str_contains($search, "\0")
                || mb_strlen(
                    $search,
                    'UTF-8'
                ) > 150
            ) {
                throw new InvalidArgumentException(
                    'Pesquisa inválida.'
                );
            }

            $where[] = '(
                planejamento.codigo LIKE :search_code
                OR cliente.nome LIKE :search_client
                OR servico.nome LIKE :search_service
                OR planejamento.local_servico
                    LIKE :search_location
                OR ordem.numero LIKE :search_order
            )';

            $like = '%' . $search . '%';

            $parameters += [
                'search_code' => $like,
                'search_client' => $like,
                'search_service' => $like,
                'search_location' => $like,
                'search_order' => $like,
            ];
        }

        $statement = $this->connection->prepare(
            'SELECT
                planejamento.id,
                planejamento.codigo,
                planejamento.cliente_id,
                cliente.nome AS cliente_nome,
                planejamento.servico_id,
                servico.nome AS servico_nome,
                servico.valor AS servico_valor,
                planejamento.prioridade,
                planejamento.local_servico,
                planejamento.agendado_inicio,
                planejamento.agendado_fim,

                planejamento.funcionario_principal_id,
                principal.nome
                    AS funcionario_principal_nome,

                planejamento.funcionario_apoio_id,
                apoio.nome
                    AS funcionario_apoio_nome,

                planejamento.observacao,
                planejamento.status,
                planejamento.ordem_servico_id,
                ordem.numero AS ordem_servico_numero,

                planejamento.confirmado_em,
                planejamento.cancelado_em,
                planejamento.motivo_cancelamento,
                planejamento.criado_em,
                planejamento.atualizado_em

             FROM servicos_semanais
                AS planejamento

             JOIN clientes AS cliente
               ON cliente.id =
                  planejamento.cliente_id

             JOIN servicos AS servico
               ON servico.id =
                  planejamento.servico_id

             LEFT JOIN funcionarios AS principal
               ON principal.id =
                  planejamento.funcionario_principal_id

             LEFT JOIN funcionarios AS apoio
               ON apoio.id =
                  planejamento.funcionario_apoio_id

             LEFT JOIN ordens_servico AS ordem
               ON ordem.id =
                  planejamento.ordem_servico_id

             WHERE '
            . implode(
                ' AND ',
                $where
            )
            . '

             ORDER BY
                planejamento.agendado_inicio ASC,
                planejamento.id ASC

             LIMIT 500'
        );

        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * @return array<string,mixed>
     */
    public function getPlanning(
        int $planningId
    ): array {
        if ($planningId <= 0) {
            throw new InvalidArgumentException(
                'Serviço semanal inválido.'
            );
        }

        $statement = $this->connection->prepare(
            'SELECT
                planejamento.*,
                cliente.nome AS cliente_nome,
                servico.nome AS servico_nome,
                servico.valor AS servico_valor,
                principal.nome
                    AS funcionario_principal_nome,
                apoio.nome
                    AS funcionario_apoio_nome,
                ordem.numero AS ordem_servico_numero

             FROM servicos_semanais
                AS planejamento

             JOIN clientes AS cliente
               ON cliente.id =
                  planejamento.cliente_id

             JOIN servicos AS servico
               ON servico.id =
                  planejamento.servico_id

             LEFT JOIN funcionarios AS principal
               ON principal.id =
                  planejamento.funcionario_principal_id

             LEFT JOIN funcionarios AS apoio
               ON apoio.id =
                  planejamento.funcionario_apoio_id

             LEFT JOIN ordens_servico AS ordem
               ON ordem.id =
                  planejamento.ordem_servico_id

             WHERE planejamento.id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $planningId,
        ]);

        $planning = $statement->fetch();

        if ($planning === false) {
            throw new InvalidArgumentException(
                'Serviço semanal não encontrado.'
            );
        }

        return $planning;
    }

    /**
     * @return array<string,mixed>
     */
    private function lockPlanning(
        int $planningId
    ): array {
        $statement = $this->connection->prepare(
            'SELECT *
               FROM servicos_semanais
              WHERE id = :id
              LIMIT 1
              FOR UPDATE'
        );

        $statement->execute([
            'id' => $planningId,
        ]);

        $planning = $statement->fetch();

        if ($planning === false) {
            throw new InvalidArgumentException(
                'Serviço semanal não encontrado.'
            );
        }

        return $planning;
    }

    /**
     * @return array<string,mixed>
     */
    private function lockActiveClient(
        int $clientId
    ): array {
        $statement = $this->connection->prepare(
            'SELECT
                id,
                nome,
                status
             FROM clientes
             WHERE id = :id
               AND excluido_em IS NULL
             LIMIT 1
             FOR UPDATE'
        );

        $statement->execute([
            'id' => $clientId,
        ]);

        $client = $statement->fetch();

        if ($client === false) {
            throw new InvalidArgumentException(
                'Cliente não encontrado.'
            );
        }

        if (
            (string) ($client['status'] ?? '')
            !== 'ativo'
        ) {
            throw new InvalidArgumentException(
                'O cliente selecionado está inativo.'
            );
        }

        return $client;
    }

    /**
     * @return array<string,mixed>
     */
    private function lockActiveService(
        int $serviceId
    ): array {
        $statement = $this->connection->prepare(
            'SELECT
                id,
                nome,
                valor,
                status
             FROM servicos
             WHERE id = :id
               AND excluido_em IS NULL
             LIMIT 1
             FOR UPDATE'
        );

        $statement->execute([
            'id' => $serviceId,
        ]);

        $service = $statement->fetch();

        if ($service === false) {
            throw new InvalidArgumentException(
                'Serviço não encontrado.'
            );
        }

        if (
            (string) ($service['status'] ?? '')
            !== 'ativo'
        ) {
            throw new InvalidArgumentException(
                'O serviço selecionado está inativo.'
            );
        }

        return $service;
    }

    /**
     * @param int[] $employeeIds
     */
    private function validateEmployees(
        array $employeeIds
    ): void {
        if ($employeeIds === []) {
            return;
        }

        $placeholders = [];
        $parameters = [];

        foreach (
            array_values(
                array_unique($employeeIds)
            ) as $index => $employeeId
        ) {
            $key = 'employee_' . $index;

            $placeholders[] = ':' . $key;
            $parameters[$key] = $employeeId;
        }

        $statement = $this->connection->prepare(
            'SELECT id
               FROM funcionarios
              WHERE id IN (
                ' . implode(', ', $placeholders) . '
              )
              FOR UPDATE'
        );

        $statement->execute($parameters);

        $foundIds = array_map(
            'intval',
            $statement->fetchAll(
                PDO::FETCH_COLUMN
            )
        );

        foreach ($employeeIds as $employeeId) {
            if (
                !in_array(
                    $employeeId,
                    $foundIds,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Funcionário selecionado não foi encontrado.'
                );
            }
        }
    }

    /**
     * @param int[] $employeeIds
     */
    private function assertNoScheduleConflict(
        array $employeeIds,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?int $ignorePlanningId
    ): void {
        if ($employeeIds === []) {
            return;
        }

        $employeeIds = array_values(
            array_unique($employeeIds)
        );

        $osPlaceholders = [];
        $osParameters = [
            'start' => $start->format(
                'Y-m-d H:i:s'
            ),

            'end' => $end->format(
                'Y-m-d H:i:s'
            ),
        ];

        foreach (
            $employeeIds
            as $index => $employeeId
        ) {
            $key = 'os_employee_' . $index;

            $osPlaceholders[] = ':' . $key;
            $osParameters[$key] = $employeeId;
        }

        $osStatement = $this->connection->prepare(
            'SELECT DISTINCT funcionario.nome
               FROM funcionarios AS funcionario

               JOIN ordem_servico_funcionarios
                    AS equipe
                 ON equipe.funcionario_id =
                    funcionario.id
                AND equipe.ativo = 1

               JOIN ordens_servico AS ordem
                 ON ordem.id =
                    equipe.ordem_servico_id

              WHERE funcionario.id IN (
                    '
                    . implode(
                        ', ',
                        $osPlaceholders
                    )
                    . '
              )

                AND ordem.excluida_em IS NULL

                AND ordem.status IN (
                    "agendada",
                    "em_deslocamento",
                    "em_execucao"
                )

                AND ordem.agendado_inicio IS NOT NULL
                AND ordem.agendado_fim IS NOT NULL

                AND :start < ordem.agendado_fim
                AND :end > ordem.agendado_inicio

              FOR UPDATE'
        );

        $osStatement->execute($osParameters);

        $conflictingNames = array_map(
            'strval',
            $osStatement->fetchAll(
                PDO::FETCH_COLUMN
            )
        );

        $primaryPlaceholders = [];
        $supportPlaceholders = [];

        $planningParameters = [
            'start' => $start->format(
                'Y-m-d H:i:s'
            ),

            'end' => $end->format(
                'Y-m-d H:i:s'
            ),
        ];

        foreach (
            $employeeIds
            as $index => $employeeId
        ) {
            $primaryKey =
                'planning_primary_' . $index;

            $supportKey =
                'planning_support_' . $index;

            $primaryPlaceholders[] =
                ':' . $primaryKey;

            $supportPlaceholders[] =
                ':' . $supportKey;

            $planningParameters[$primaryKey] =
                $employeeId;

            $planningParameters[$supportKey] =
                $employeeId;
        }

        $planningSql =
            'SELECT DISTINCT funcionario.nome
               FROM servicos_semanais
                    AS planejamento

               JOIN funcionarios AS funcionario
                 ON funcionario.id IN (
                    planejamento.funcionario_principal_id,
                    planejamento.funcionario_apoio_id
                 )

              WHERE planejamento.status =
                    "aguardando_confirmacao"

                AND (
                    planejamento.funcionario_principal_id
                    IN (
                        '
                        . implode(
                            ', ',
                            $primaryPlaceholders
                        )
                        . '
                    )

                    OR

                    planejamento.funcionario_apoio_id
                    IN (
                        '
                        . implode(
                            ', ',
                            $supportPlaceholders
                        )
                        . '
                    )
                )

                AND :start <
                    planejamento.agendado_fim

                AND :end >
                    planejamento.agendado_inicio';

        if ($ignorePlanningId !== null) {
            $planningSql .=
                ' AND planejamento.id
                    <> :ignore_planning_id';

            $planningParameters[
                'ignore_planning_id'
            ] = $ignorePlanningId;
        }

        $planningSql .= ' FOR UPDATE';

        $planningStatement =
            $this->connection->prepare(
                $planningSql
            );

        $planningStatement->execute(
            $planningParameters
        );

        $conflictingNames = array_merge(
            $conflictingNames,
            array_map(
                'strval',
                $planningStatement->fetchAll(
                    PDO::FETCH_COLUMN
                )
            )
        );

        $conflictingNames = array_values(
            array_unique(
                array_filter(
                    $conflictingNames,
                    static fn (string $name): bool =>
                        trim($name) !== ''
                )
            )
        );

        if ($conflictingNames === []) {
            return;
        }

        throw new InvalidArgumentException(
            count($conflictingNames) === 1
                ? (
                    'O funcionário '
                    . $conflictingNames[0]
                    . ' já possui serviço nesse período.'
                )
                : (
                    'Os funcionários '
                    . implode(
                        ', ',
                        $conflictingNames
                    )
                    . ' já possuem serviços nesse período.'
                )
        );
    }

    private function positiveInt(
        mixed $value,
        string $field
    ): int {
        $integer = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if (!is_int($integer)) {
            throw new InvalidArgumentException(
                'Informe um ' . $field . ' válido.'
            );
        }

        return $integer;
    }

    private function optionalPositiveInt(
        mixed $value
    ): ?int {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return null;
        }

        $integer = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if (!is_int($integer)) {
            throw new InvalidArgumentException(
                'Identificador informado é inválido.'
            );
        }

        return $integer;
    }

    private function nullableInt(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0
            ? $integer
            : null;
    }

    private function dateTime(
        mixed $value,
        string $field
    ): DateTimeImmutable {
        $text = trim(
            (string) ($value ?? '')
        );

        if ($text === '') {
            throw new InvalidArgumentException(
                'Informe o ' . $field
                . ' do serviço.'
            );
        }

        $normalized = str_replace(
            'T',
            ' ',
            $text
        );

        if (strlen($normalized) === 16) {
            $normalized .= ':00';
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $normalized
        );

        $errors =
            DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $date->format('Y-m-d H:i:s')
                !== $normalized
        ) {
            throw new InvalidArgumentException(
                'Informe uma data válida para '
                . $field . '.'
            );
        }

        return $date;
    }

    private function optionalText(
        mixed $value,
        int $maximumLength
    ): ?string {
        $text = trim(
            (string) ($value ?? '')
        );

        if ($text === '') {
            return null;
        }

        $length = function_exists(
            'mb_strlen'
        )
            ? mb_strlen(
                $text,
                'UTF-8'
            )
            : strlen($text);

        if (
            str_contains($text, "\0")
            || $text !== strip_tags($text)
            || $length > $maximumLength
        ) {
            throw new InvalidArgumentException(
                'Há texto inválido no serviço semanal.'
            );
        }

        return $text;
    }

    /**
     * @template T
     *
     * @param callable():T $callback
     *
     * @return T
     */
    private function transactional(
        callable $callback
    ): mixed {
        $ownsTransaction =
            !$this->connection->inTransaction();

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $result = $callback();

            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if (
                $ownsTransaction
                && $this->connection->inTransaction()
            ) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}