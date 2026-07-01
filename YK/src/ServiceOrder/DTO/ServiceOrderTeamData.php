<?php

declare(strict_types=1);

namespace App\ServiceOrder\DTO;

use InvalidArgumentException;

final class ServiceOrderTeamData
{
    /** @param ServiceOrderTeamMemberData[] $members */
    public function __construct(private readonly array $members)
    {
        $seen = [];
        $primaryCount = 0;

        foreach ($this->members as $member) {
            if (!$member instanceof ServiceOrderTeamMemberData) {
                throw new InvalidArgumentException('Equipe inválida.');
            }

            if (isset($seen[$member->employeeId()])) {
                throw new InvalidArgumentException('O mesmo funcionário não pode aparecer mais de uma vez na equipe.');
            }

            $seen[$member->employeeId()] = true;
            if ($member->primary()) {
                $primaryCount++;
            }
        }

        if ($this->members !== [] && $primaryCount !== 1) {
            throw new InvalidArgumentException('Quando houver equipe, informe exatamente um responsável principal.');
        }
    }

    public static function fromArray(array $data): self
    {
        $members = [];
        $rawMembers = $data['team_members'] ?? $data['equipe'] ?? null;

        if (is_array($rawMembers)) {
            foreach ($rawMembers as $row) {
                if (!is_array($row) || trim((string) ($row['funcionario_id'] ?? $row['employee_id'] ?? '')) === '') {
                    continue;
                }
                $members[] = ServiceOrderTeamMemberData::fromArray($row);
            }

            return new self($members);
        }

        $primary = self::optionalPositiveInt($data['funcionario_principal_id'] ?? $data['primary_employee_id'] ?? null);
        $support = self::optionalPositiveInt($data['funcionario_apoio_id'] ?? $data['support_employee_id'] ?? null);

        if ($primary !== null) {
            $members[] = new ServiceOrderTeamMemberData($primary, 'Responsável técnico', true);
        }

        if ($support !== null && $support !== $primary) {
            $members[] = new ServiceOrderTeamMemberData($support, 'Técnico', false);
        }

        return new self($members);
    }

    /** @return ServiceOrderTeamMemberData[] */
    public function members(): array
    {
        return $this->members;
    }

    /** @return int[] */
    public function employeeIds(): array
    {
        return array_map(static fn(ServiceOrderTeamMemberData $member): int => $member->employeeId(), $this->members);
    }

    public function hasMembers(): bool
    {
        return $this->members !== [];
    }

    public function primaryEmployeeId(): ?int
    {
        foreach ($this->members as $member) {
            if ($member->primary()) {
                return $member->employeeId();
            }
        }

        return null;
    }

    public function firstSupportEmployeeId(): ?int
    {
        foreach ($this->members as $member) {
            if (!$member->primary()) {
                return $member->employeeId();
            }
        }

        return null;
    }

    private static function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($int) ? $int : null;
    }
}
