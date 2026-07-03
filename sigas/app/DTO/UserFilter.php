<?php

declare(strict_types=1);

namespace App\DTO;

use App\Domain\UserStatus;

final readonly class UserFilter
{
    /** @var array<string, string> */
    public const ALLOWED_SORTS = [
        'nome' => 'u.nome',
        'criado_em' => 'u.criado_em',
        'ultimo_login_em' => 'u.ultimo_login_em',
        'status' => 'u.status',
    ];

    public Pagination $pagination;
    public string $sortBy;
    public string $direction;

    public function __construct(
        public ?string $search = null,
        public ?int $sectorId = null,
        public ?int $levelId = null,
        public ?UserStatus $status = null,
        int $page = 1,
        int $perPage = 20,
        string $sortBy = 'nome',
        string $direction = 'asc',
    ) {
        $this->pagination = new Pagination($page, $perPage);
        $this->sortBy = array_key_exists($sortBy, self::ALLOWED_SORTS) ? $sortBy : 'nome';
        $this->direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    public function sortColumn(): string
    {
        return self::ALLOWED_SORTS[$this->sortBy];
    }
}
