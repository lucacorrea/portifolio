<?php

declare(strict_types=1);

namespace App\ServiceOrder\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

final class ServiceOrderScheduleData
{
    public function __construct(
        private readonly DateTimeImmutable $start,
        private readonly DateTimeImmutable $end
    ) {
        if ($this->end <= $this->start) {
            throw new InvalidArgumentException('O fim do agendamento deve ser posterior ao início.');
        }
    }

    public static function fromArray(array $data): ?self
    {
        $start = trim((string) ($data['agendado_inicio'] ?? $data['scheduled_start'] ?? ''));
        $end = trim((string) ($data['agendado_fim'] ?? $data['scheduled_end'] ?? ''));

        if ($start === '' && $end === '') {
            return null;
        }

        if ($start === '' || $end === '') {
            throw new InvalidArgumentException('Informe início e fim do agendamento.');
        }

        return new self(self::dateTime($start, 'início do agendamento'), self::dateTime($end, 'fim do agendamento'));
    }

    public function start(): DateTimeImmutable { return $this->start; }
    public function end(): DateTimeImmutable { return $this->end; }

    private static function dateTime(string $value, string $field): DateTimeImmutable
    {
        $normalized = str_replace('T', ' ', $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', strlen($normalized) === 16 ? $normalized . ':00' : $normalized);

        if (!$date || $date->format('Y-m-d H:i:s') !== (strlen($normalized) === 16 ? $normalized . ':00' : $normalized)) {
            throw new InvalidArgumentException('Informe uma data válida para ' . $field . '.');
        }

        return $date;
    }
}
