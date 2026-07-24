<?php

declare(strict_types=1);

namespace App\Schedule\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

final class AgendaReminderFormData
{
    public function __construct(
        private readonly string $title,
        private readonly ?string $description,
        private readonly DateTimeImmutable $start,
        private readonly ?DateTimeImmutable $end
    ) {
        if ($this->end !== null && $this->end <= $this->start) {
            throw new InvalidArgumentException('O fim do lembrete deve ser posterior ao início.');
        }
    }

    public static function fromArray(array $data): self
    {
        $title = trim((string) ($data['title'] ?? $data['titulo'] ?? ''));
        if ($title === '' || str_contains($title, "\0") || $title !== strip_tags($title) || strlen($title) > 150) {
            throw new InvalidArgumentException('Informe um título válido.');
        }
        $description = trim((string) ($data['description'] ?? $data['descricao'] ?? ''));
        if (str_contains($description, "\0") || strlen($description) > 5000) {
            throw new InvalidArgumentException('Descrição inválida.');
        }
        $end = trim((string) ($data['end'] ?? $data['fim'] ?? ''));

        return new self(
            $title,
            $description === '' ? null : $description,
            self::dateTime((string) ($data['start'] ?? $data['inicio'] ?? ''), 'início'),
            $end === '' ? null : self::dateTime($end, 'fim')
        );
    }

    public function title(): string { return $this->title; }
    public function description(): ?string { return $this->description; }
    public function start(): DateTimeImmutable { return $this->start; }
    public function end(): ?DateTimeImmutable { return $this->end; }

    private static function dateTime(string $value, string $field): DateTimeImmutable
    {
        $normalized = str_replace('T', ' ', trim($value));
        $withSeconds = strlen($normalized) === 16 ? $normalized . ':00' : $normalized;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $withSeconds);
        if (!$date || $date->format('Y-m-d H:i:s') !== $withSeconds) {
            throw new InvalidArgumentException('Informe uma data válida para ' . $field . '.');
        }
        return $date;
    }
}
