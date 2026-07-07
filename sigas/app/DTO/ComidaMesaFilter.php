<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ComidaMesaFilter
{
    public const PER_PAGE = 20;

    /** @var list<string> */
    private const PROGRAM_STATUSES = ['em_analise', 'ativa', 'lista_espera', 'suspensa', 'bloqueada', 'encerrada'];

    /** @var list<string> */
    private const DELIVERY_STATUSES = ['recebida', 'aguardando', 'bloqueada', 'indisponivel'];

    public ?string $search;
    public ?int $competenceId;
    public ?string $programStatus;
    public ?string $deliveryStatus;
    public ?string $zone;
    public ?string $district;
    public ?string $community;
    public ?int $poleId;
    public int $page;
    public int $perPage;

    public function __construct(
        ?string $search = null,
        ?int $competenceId = null,
        ?string $programStatus = null,
        ?string $deliveryStatus = null,
        ?string $zone = null,
        ?string $district = null,
        ?string $community = null,
        ?int $poleId = null,
        int $page = 1,
    ) {
        $this->search = $this->cleanString($search, 150);
        $this->competenceId = $this->positiveIntOrNull($competenceId);
        $this->programStatus = in_array($programStatus, self::PROGRAM_STATUSES, true) ? $programStatus : null;
        $this->deliveryStatus = in_array($deliveryStatus, self::DELIVERY_STATUSES, true) ? $deliveryStatus : null;
        $this->zone = $this->cleanString($zone, 20);
        $this->district = $this->cleanString($district, 120);
        $this->community = $this->cleanString($community, 150);
        $this->poleId = $this->positiveIntOrNull($poleId);
        $this->page = max(1, $page);
        $this->perPage = self::PER_PAGE;
    }

    private function cleanString(?string $value, int $maxLength): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function positiveIntOrNull(?int $value): ?int
    {
        return $value !== null && $value > 0 ? $value : null;
    }
}
