<?php

declare(strict_types=1);

namespace App\Schedule\Service;

use App\Schedule\DTO\AgendaReminderFormData;
use App\Schedule\Entity\AgendaReminder;
use App\Schedule\Repository\AgendaReminderRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class AgendaManagementService
{
    public function __construct(private readonly AgendaReminderRepository $reminders)
    {
    }

    /** @return AgendaReminder[] */
    public function listRemindersBetween(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        if ($end <= $start) throw new InvalidArgumentException('Período inválido.');
        return $this->reminders->findBetween($start, $end);
    }

    public function getReminder(int $id): AgendaReminder
    {
        $reminder = $this->reminders->findById($id);
        if ($reminder === null) throw new InvalidArgumentException('Lembrete não encontrado.');
        return $reminder;
    }

    public function createReminder(AgendaReminderFormData $data): void { $this->reminders->create($data); }
    public function updateReminder(int $id, AgendaReminderFormData $data): void { $this->getReminder($id); $this->reminders->update($id, $data); }
    public function cancelReminder(int $id): void { $this->getReminder($id); $this->reminders->cancel($id); }
}
