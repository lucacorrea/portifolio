<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ComidaMesaPoloData
{
    /** @param array<string,string> $fieldErrors */
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $address,
        public bool $active,
        public array $fieldErrors = [],
    ) {
    }

    /** @param array<string,mixed> $input */
    public static function fromArray(array $input): self
    {
        $errors = [];
        $id = null;
        if (isset($input['polo_id']) && trim((string) $input['polo_id']) !== '') {
            if (preg_match('/^\d+$/', (string) $input['polo_id']) !== 1 || (int) $input['polo_id'] <= 0) {
                $errors['polo_id'] = 'Polo inválido.';
            } else {
                $id = (int) $input['polo_id'];
            }
        }

        $name = trim((string) ($input['nome'] ?? ''));
        $address = trim((string) ($input['endereco'] ?? ''));
        $active = (string) ($input['ativo'] ?? '1') === '1';

        return new self(
            $id,
            $name,
            $address === '' ? null : $address,
            $active,
            $errors
        );
    }
}
