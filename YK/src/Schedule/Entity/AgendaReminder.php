<?php

declare(strict_types=1);

namespace App\Schedule\Entity;

final class AgendaReminder
{
    public function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly ?string $description,
        private readonly string $start,
        private readonly ?string $end,
        private readonly string $status
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['titulo'] ?? ''),
            isset($data['descricao']) ? (string) $data['descricao'] : null,
            (string) ($data['inicio'] ?? ''),
            isset($data['fim']) ? (string) $data['fim'] : null,
            (string) ($data['status'] ?? 'ativo')
        );
    }

    public function id(): int { return $this->id; }
    public function title(): string { return $this->title; }
    public function description(): ?string { return $this->description; }
    public function start(): string { return $this->start; }
    public function end(): ?string { return $this->end; }
    public function status(): string { return $this->status; }
    public function isActive(): bool { return $this->status === 'ativo'; }
    public function isCompleted(): bool { return $this->status === 'concluido'; }
    public function isCanceled(): bool { return $this->status === 'cancelado'; }
}
