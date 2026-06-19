<?php
declare(strict_types=1);

namespace App\Access\DTO;

use InvalidArgumentException;

final class ProfileFormData
{
    private const VALID_STATUSES = ['ativo', 'inativo'];

    public function __construct(
        private readonly string $name,
        private readonly ?string $description,
        private readonly string $status
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            self::normalizeName((string) ($data['name'] ?? '')),
            self::normalizeDescription(isset($data['description']) ? (string) $data['description'] : null),
            self::normalizeStatus((string) ($data['status'] ?? 'ativo'))
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function status(): string
    {
        return $this->status;
    }

    private static function normalizeName(string $name): string
    {
        $name = self::normalizeText($name);

        if ($name === '') {
            throw new InvalidArgumentException('Nome do perfil e obrigatorio.');
        }

        if (strlen($name) > 100) {
            throw new InvalidArgumentException('Nome do perfil deve ter no maximo 100 caracteres.');
        }

        return $name;
    }

    private static function normalizeDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $description = self::normalizeText($description);

        if ($description === '') {
            return null;
        }

        if (strlen($description) > 255) {
            throw new InvalidArgumentException('Descricao deve ter no maximo 255 caracteres.');
        }

        return $description;
    }

    private static function normalizeStatus(string $status): string
    {
        $status = trim($status);

        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Status de perfil invalido.');
        }

        return $status;
    }

    private static function normalizeText(string $value): string
    {
        if ($value !== strip_tags($value)) {
            throw new InvalidArgumentException('HTML nao e permitido nos dados do perfil.');
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }
}
