<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class PaginatedResult
{
    public function __construct(
        private array $items,
        private int $total,
        private int $page,
        private int $perPage,
    ) {
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->getTotalPages();
    }
}
