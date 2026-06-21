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
    public function findBetween(DateTimeImmutable $start, DateTimeImmutable $end, string $status = 'ativo'): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, titulo, descricao, inicio, fim, status
               FROM agenda_lembretes
              WHERE inicio >= :start AND inicio < :end AND status = :status
              ORDER BY inicio ASC, id ASC'
        );
        $statement->execute(['start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s'), 'status' => $status]);
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

    public function update(int $id, AgendaReminderFormData $data): void
    {
        if ($id <= 0) throw new InvalidArgumentException('ID de lembrete inválido.');
        $params = $this->params($data);
        $params['id'] = $id;
        $statement = $this->connection->prepare(
            'UPDATE agenda_lembretes SET titulo = :title, descricao = :description, inicio = :start, fim = :end WHERE id = :id'
        );
        $statement->execute($params);
    }

    public function cancel(int $id): void
    {
        if ($id <= 0) throw new InvalidArgumentException('ID de lembrete inválido.');
        $this->connection->prepare("UPDATE agenda_lembretes SET status = 'cancelado' WHERE id = :id")->execute(['id' => $id]);
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
