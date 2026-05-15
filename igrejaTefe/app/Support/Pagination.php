<?php

declare(strict_types=1);

namespace App\Support;

final class Pagination
{
    public const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public static function fromRequest(array $input, int $defaultPerPage = 10): array
    {
        $page = max(1, (int) ($input['page'] ?? 1));
        $perPage = (int) ($input['per_page'] ?? $defaultPerPage);

        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = $defaultPerPage;
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    public static function meta(int $total, int $page, int $perPage): array
    {
        $total = max(0, $total);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 10;
        $totalPages = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $totalPages);
        $offset = ($currentPage - 1) * $perPage;

        return [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
            'per_page_options' => self::PER_PAGE_OPTIONS,
        ];
    }
}
