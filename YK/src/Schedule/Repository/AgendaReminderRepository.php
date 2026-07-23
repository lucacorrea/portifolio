<?php

declare(strict_types=1);

namespace App\Schedule\Repository;

use App\Schedule\DTO\AgendaReminderFormData;
use App\Schedule\Entity\AgendaReminder;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class AgendaReminderRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return AgendaReminder[] */
    public function findBetween(DateTimeImmutable $start, DateTimeImmutable $end, array $statuses = ['ativo']): array
    {
        $statuses = array_values(array_unique(array_filter(
            $statuses,
            static fn(mixed $status): bool => is_string($status) && in_array($status, ['ativo', 'concluido', 'cancelado'], true)
        )));
        if ($statuses === []) {
            return [];
        }

        $statusPlaceholders = [];
        $parameters = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ];
        foreach ($statuses as $index => $status) {
            $key = 'status_' . $index;
            $statusPlaceholders[] = ':' . $key;
            $parameters[$key] = $status;
        }

        $statement = $this->connection->prepare(
            'SELECT id, titulo, descricao, inicio, fim, status
               FROM agenda_lembretes
              WHERE inicio >= :start AND inicio < :end
                AND status IN (' . implode(', ', $statusPlaceholders) . ')
              ORDER BY inicio ASC, id ASC'
        );
        $statement->execute($parameters);
        return array_map(static fn(array $row): AgendaReminder => AgendaReminder::fromArray($row), $statement->fetchAll());
    }

    public function findById(int $id): ?AgendaReminder
    {
        if ($id <= 0) throw new InvalidArgumentException('ID de lembrete inválido.');
        $statement = $this->connection->prepare('SELECT id, titulo, descricao, inicio, fim, status FROM agenda_lembretes WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row === false ? null : AgendaReminder::fromArray($row);
    }

    public function create(AgendaReminderFormData $data): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO agenda_lembretes (titulo, descricao, inicio, fim) VALUES (:title, :description, :start, :end)'
        );
        $statement->execute($this->params($data));
    }

    public function update(int $id, AgendaReminderFormData $data): bool
    {
        if ($id <= 0) throw new InvalidArgumentException('ID de lembrete inválido.');
        $params = $this->params($data);
        $params['id'] = $id;
        $statement = $this->connection->prepare(
            "UPDATE agenda_lembretes
                SET titulo = :title, descricao = :description, inicio = :start, fim = :end
              WHERE id = :id AND status = 'ativo'"
        );
        $statement->execute($params);
        return $statement->rowCount() === 1;
    }

    public function cancel(int $id): bool
    {
        if ($id <= 0) throw new InvalidArgumentException('ID de lembrete inválido.');
        $statement = $this->connection->prepare("UPDATE agenda_lembretes SET status = 'cancelado' WHERE id = :id AND status = 'ativo'");
        $statement->execute(['id' => $id]);
        return $statement->rowCount() === 1;
    }

    public function complete(int $id, int $userId): bool
    {
        if ($id <= 0) throw new InvalidArgumentException('ID de lembrete inválido.');
        if ($userId <= 0) throw new InvalidArgumentException('Usuário inválido.');

        $statement = $this->connection->prepare(
            "UPDATE agenda_lembretes
                SET status = 'concluido', concluido_em = COALESCE(concluido_em, CURRENT_TIMESTAMP), concluido_por = :user_id
              WHERE id = :id AND status = 'ativo'"
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        return $statement->rowCount() === 1;
    }

    private function params(AgendaReminderFormData $data): array
    {
        return [
            'title' => $data->title(),
            'description' => $data->description(),
            'start' => $data->start()->format('Y-m-d H:i:s'),
            'end' => $data->end()?->format('Y-m-d H:i:s'),
        ];
    }
}
