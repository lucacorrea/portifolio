<?php

declare(strict_types=1);

namespace App\Workforce\DTO;

use InvalidArgumentException;

final class EmployeeFormData
{
    public function __construct(
        private readonly string $name
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            self::normalizeName(
                (string) ($data['name'] ?? '')
            )
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    private static function normalizeName(string $name): string
    {
        if ($name !== strip_tags($name)) {
            throw new InvalidArgumentException(
                'HTML não é permitido no nome do funcionário.'
            );
        }

        if (str_contains($name, "\0")) {
            throw new InvalidArgumentException(
                'Nome do funcionário possui conteúdo inválido.'
            );
        }

        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException(
                'Nome do funcionário é obrigatório.'
            );
        }

        if (strlen($name) > 150) {
            throw new InvalidArgumentException(
                'Nome do funcionário deve ter no máximo 150 caracteres.'
            );
        }

        return $name;
    }
}
