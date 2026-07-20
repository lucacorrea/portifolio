<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Schedule/Entity/AgendaReminder.php';
require dirname(__DIR__) . '/src/Schedule/DTO/AgendaReminderFormData.php';
require dirname(__DIR__) . '/src/Schedule/Repository/AgendaReminderRepository.php';
require dirname(__DIR__) . '/src/Schedule/Service/AgendaManagementService.php';
require dirname(__DIR__) . '/src/Schedule/Service/AgendaDayBoard.php';

use App\Schedule\Repository\AgendaReminderRepository;
use App\Schedule\Service\AgendaDayBoard;
use App\Schedule\Service\AgendaManagementService;

function agendaReminderAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

final class AgendaReminderFakePdo extends PDO
{
    /** @var array<int,array<string,mixed>> */
    public array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new AgendaReminderFakeStatement($this, $query);
    }
}

final class AgendaReminderFakeStatement extends PDOStatement
{
    private array|false $result = false;
    private array $results = [];
    private int $affectedRows = 0;

    public function __construct(private readonly AgendaReminderFakePdo $connection, private readonly string $query)
    {
    }

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->result = false;
        $this->results = [];
        $this->affectedRows = 0;

        if (str_contains($this->query, "SET status = 'cancelado'")) {
            $id = (int) ($params['id'] ?? 0);
            foreach ($this->connection->rows as &$row) {
                if ((int) $row['id'] !== $id || $row['status'] !== 'ativo') continue;
                $row['status'] = 'cancelado';
                $this->affectedRows = 1;
                break;
            }
            unset($row);
            return true;
        }

        if (str_contains($this->query, "SET status = 'concluido'")) {
            $id = (int) ($params['id'] ?? 0);
            foreach ($this->connection->rows as &$row) {
                if ((int) $row['id'] !== $id || $row['status'] !== 'ativo') continue;
                $row['status'] = 'concluido';
                $row['concluido_em'] ??= '2026-07-20 10:30:00';
                $row['concluido_por'] = (int) $params['user_id'];
                $this->affectedRows = 1;
                break;
            }
            unset($row);
            return true;
        }

        if (str_contains($this->query, 'WHERE id = :id LIMIT 1')) {
            $id = (int) ($params['id'] ?? 0);
            foreach ($this->connection->rows as $row) {
                if ((int) $row['id'] === $id) {
                    $this->result = $row;
                    break;
                }
            }
            return true;
        }

        if (str_contains($this->query, 'status IN (')) {
            $statuses = [];
            foreach ($params as $key => $value) {
                if (str_starts_with((string) $key, 'status_')) $statuses[] = (string) $value;
            }
            $this->results = array_values(array_filter(
                $this->connection->rows,
                static fn(array $row): bool => in_array($row['status'], $statuses, true)
                    && $row['inicio'] >= $params['start']
                    && $row['inicio'] < $params['end']
            ));
            usort($this->results, static fn(array $a, array $b): int => [$a['inicio'], $a['id']] <=> [$b['inicio'], $b['id']]);
            return true;
        }

        throw new RuntimeException('SQL não suportado pelo teste: ' . $this->query);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->result;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->results;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }
}

$rows = [
    1 => ['id'=>1, 'titulo'=>'Pendente', 'descricao'=>null, 'inicio'=>'2026-07-20 08:00:00', 'fim'=>null, 'status'=>'ativo'],
    2 => ['id'=>2, 'titulo'=>'Cancelado', 'descricao'=>null, 'inicio'=>'2026-07-20 09:00:00', 'fim'=>null, 'status'=>'cancelado'],
    3 => ['id'=>3, 'titulo'=>'Feito', 'descricao'=>null, 'inicio'=>'2026-07-20 10:00:00', 'fim'=>null, 'status'=>'concluido', 'concluido_por'=>5],
];
$connection = new AgendaReminderFakePdo($rows);
$repository = new AgendaReminderRepository($connection);
$service = new AgendaManagementService($repository);

$service->completeReminder(1, 9);
agendaReminderAssert($connection->rows[1]['status'] === 'concluido', 'Lembrete ativo deve ser concluído.');
agendaReminderAssert($connection->rows[1]['concluido_por'] === 9, 'Conclusão deve registrar o usuário.');

$service->completeReminder(1, 12);
agendaReminderAssert($connection->rows[1]['concluido_por'] === 9, 'Repetição idempotente não deve sobrescrever a auditoria original.');
agendaReminderAssert($repository->cancel(1) === false, 'Cancelamento concorrente não pode sobrescrever um lembrete concluído.');
agendaReminderAssert($connection->rows[1]['status'] === 'concluido', 'Conclusão deve permanecer protegida contra cancelamento concorrente.');

try {
    $service->completeReminder(2, 9);
    throw new RuntimeException('Lembrete cancelado não pode ser concluído.');
} catch (InvalidArgumentException $exception) {
    agendaReminderAssert(str_contains($exception->getMessage(), 'ativos'), 'Cancelado deve retornar erro de estado seguro.');
}

try {
    $service->completeReminder(99, 9);
    throw new RuntimeException('ID inexistente deveria falhar.');
} catch (InvalidArgumentException $exception) {
    agendaReminderAssert($exception->getMessage() === 'Lembrete não encontrado.', 'ID inexistente deve ser rejeitado.');
}

$start = new DateTimeImmutable('2026-07-20 00:00:00');
$end = new DateTimeImmutable('2026-07-21 00:00:00');
$activeOnly = $service->listRemindersBetween($start, $end);
agendaReminderAssert($activeOnly === [], 'Visão semanal deve manter apenas lembretes ativos.');
$includingCompleted = $service->listRemindersBetween($start, $end, true);
agendaReminderAssert(count($includingCompleted) === 2, 'Visão diária deve incluir lembretes concluídos sem exibir cancelados.');

$grouped = AgendaDayBoard::group([
    ['type'=>'service_order', 'status'=>'em_execucao', 'time'=>'2026-07-20 09:00:00', 'id'=>'os-1'],
    ['type'=>'reminder', 'status'=>'ativo', 'time'=>'2026-07-20 08:00:00', 'id'=>'reminder-1'],
    ['type'=>'reminder', 'status'=>'concluido', 'time'=>'2026-07-20 10:00:00', 'id'=>'reminder-2'],
    ['type'=>'service_order', 'status'=>'novo_status', 'time'=>'2026-07-20 11:00:00', 'id'=>'other-1'],
]);
$groupKeys = array_column($grouped, 'key');
agendaReminderAssert($groupKeys === ['reminder_active', 'em_execucao', 'reminder_completed', 'other'], 'Cards diários devem seguir a ordem de status definida.');
$groupedIds = [];
foreach ($grouped as $group) foreach ($group['events'] as $event) $groupedIds[] = $event['id'];
sort($groupedIds);
agendaReminderAssert($groupedIds === ['os-1', 'other-1', 'reminder-1', 'reminder-2'], 'Cada evento deve aparecer exatamente uma vez e status futuros não podem desaparecer.');
agendaReminderAssert($grouped[2]['label'] === 'Lembretes feitos', 'Lembretes concluídos devem permanecer no card Feitos.');

$action = file_get_contents(dirname(__DIR__) . '/actions/agenda-lembrete-concluir.php');
agendaReminderAssert(is_string($action), 'A action de conclusão deve existir.');
agendaReminderAssert(str_contains($action, 'os_require_post_request();'), 'Conclusão deve aceitar somente POST.');
agendaReminderAssert(str_contains($action, "os_action_context('agenda.editar')"), 'Conclusão deve exigir CSRF, login e permissão de edição.');
agendaReminderAssert(str_contains($action, 'agenda_return_target()'), 'Conclusão deve retornar com segurança para a Agenda.');

echo "AgendaReminderStatusTest: OK\n";
