<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class Pagination
{
    private int $page;
    private int $perPage;

    public function __construct(
        int $page = 1,
        int $perPage = 20,
    ) {
        $this->page = max(1, $page);
        $this->perPage = min(100, max(10, $perPage));
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
