<?php
declare(strict_types=1);

namespace App\Access\Entity;

use InvalidArgumentException;

final class Permission
{
    private const VALID_STATUSES = ['ativo', 'inativo'];

    public function __construct(
        private readonly ?int $id,
        private string $group,
        private string $module,
        private string $code,
        private string $name,
        private readonly ?string $description,
        private readonly int $sortOrder,
        private string $status,
        private readonly ?string $createdAt = null
    ) {
        $this->group = $this->validateRequired($group, 'Grupo', 100);
        $this->module = $this->validateModule($module);
        $this->code = $this->validateCode($code);
        $this->name = $this->validateRequired($name, 'Nome', 150);
        $this->status = $this->validateStatus($status);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['id']) ? (int) $data['id'] : null,
            (string) ($data['grupo'] ?? ''),
            (string) ($data['modulo'] ?? ''),
            (string) ($data['codigo'] ?? ''),
            (string) ($data['nome'] ?? ''),
            isset($data['descricao']) ? (string) $data['descricao'] : null,
            (int) ($data['ordem'] ?? 0),
            (string) ($data['status'] ?? 'ativo'),
            isset($data['criado_em']) ? (string) $data['criado_em'] : null
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function module(): string
    {
        return $this->module;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }

    private function validateRequired(string $value, string $label, int $maxLength): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($value === '') {
            throw new InvalidArgumentException($label . ' da permissao e obrigatorio.');
        }

        if (strlen($value) > $maxLength) {
            throw new InvalidArgumentException($label . ' da permissao excede o limite.');
        }

        return $value;
    }

    private function validateModule(string $module): string
    {
        $module = trim($module);

        if (!preg_match('/^[a-z0-9_]+$/', $module)) {
            throw new InvalidArgumentException('Modulo da permissao invalido.');
        }

        return $module;
    }

    private function validateCode(string $code): string
    {
        $code = trim($code);

        if (!preg_match('/^[a-z0-9_]+\.[a-z0-9_]+$/', $code)) {
            throw new InvalidArgumentException('Codigo da permissao invalido.');
        }

        if (!str_starts_with($code, $this->module . '.')) {
            throw new InvalidArgumentException('Codigo da permissao nao corresponde ao modulo.');
        }

        return $code;
    }

    private function validateStatus(string $status): string
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Status de permissao invalido.');
        }

        return $status;
    }
}
