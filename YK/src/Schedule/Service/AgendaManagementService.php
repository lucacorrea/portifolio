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
    public function listRemindersBetween(DateTimeImmutable $start, DateTimeImmutable $end, bool $includeCompleted = false): array
    {
        if ($end <= $start) throw new InvalidArgumentException('Período inválido.');
        return $this->reminders->findBetween($start, $end, $includeCompleted ? ['ativo', 'concluido'] : ['ativo']);
    }

    public function getReminder(int $id): AgendaReminder
    {
        $reminder = $this->reminders->findById($id);
        if ($reminder === null) throw new InvalidArgumentException('Lembrete não encontrado.');
        return $reminder;
    }

    public function createReminder(AgendaReminderFormData $data): void { $this->reminders->create($data); }
    public function updateReminder(int $id, AgendaReminderFormData $data): void
    {
        if (!$this->getReminder($id)->isActive()) throw new InvalidArgumentException('Somente lembretes ativos podem ser editados.');
        if (!$this->reminders->update($id, $data)) {
            throw new InvalidArgumentException('O lembrete foi alterado por outro usuário. Atualize a Agenda.');
        }
    }

    public function cancelReminder(int $id): void
    {
        $reminder = $this->getReminder($id);
        if ($reminder->isCanceled()) return;
        if (!$reminder->isActive()) throw new InvalidArgumentException('Somente lembretes ativos podem ser cancelados.');
        if ($this->reminders->cancel($id)) return;

        $current = $this->getReminder($id);
        if (!$current->isCanceled()) {
            throw new InvalidArgumentException('O lembrete foi alterado por outro usuário. Atualize a Agenda.');
        }
    }

    public function completeReminder(int $id, int $userId): void
    {
        $reminder = $this->getReminder($id);
        if ($reminder->isCompleted()) return;
        if (!$reminder->isActive()) throw new InvalidArgumentException('Somente lembretes ativos podem ser marcados como feitos.');
        if ($this->reminders->complete($id, $userId)) return;

        $current = $this->getReminder($id);
        if (!$current->isCompleted()) {
            throw new InvalidArgumentException('O lembrete foi alterado por outro usuário. Atualize a Agenda.');
        }
    }
}
