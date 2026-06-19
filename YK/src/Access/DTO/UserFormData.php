<?php

declare(strict_types=1);

namespace App\Access\DTO;

use InvalidArgumentException;

final class UserFormData
{
    private const VALID_STATUSES = [
        'ativo',
        'inativo',
    ];

    public function __construct(
        private readonly int $profileId,
        private readonly string $name,
        private readonly string $username,
        private readonly string $email,
        private readonly ?string $phone,
        private readonly string $status,
        private readonly bool $mustChangePassword,
        private readonly ?string $password
    ) {
    }

    /**
     * Campos esperados:
     *
     * profile_id
     * name
     * username
     * email
     * phone
     * status
     * must_change_password
     * password
     * password_confirmation
     */
    public static function fromArray(
        array $data,
        bool $passwordRequired = false
    ): self {
        $password = (string) ($data['password'] ?? '');
        $passwordConfirmation = (string) (
            $data['password_confirmation'] ?? ''
        );

        $normalizedPassword = self::normalizePassword(
            $password,
            $passwordConfirmation,
            $passwordRequired
        );

        return new self(
            profileId: self::normalizeProfileId(
                $data['profile_id'] ?? null
            ),
            name: self::normalizeName(
                (string) ($data['name'] ?? '')
            ),
            username: self::normalizeUsername(
                (string) ($data['username'] ?? '')
            ),
            email: self::normalizeEmail(
                (string) ($data['email'] ?? '')
            ),
            phone: self::normalizePhone(
                isset($data['phone'])
                    ? (string) $data['phone']
                    : null
            ),
            status: self::normalizeStatus(
                (string) ($data['status'] ?? 'ativo')
            ),
            mustChangePassword: self::normalizeBoolean(
                $data['must_change_password'] ?? false
            ),
            password: $normalizedPassword
        );
    }

    public function profileId(): int
    {
        return $this->profileId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function password(): ?string
    {
        return $this->password;
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    private static function normalizeProfileId(mixed $profileId): int
    {
        $profileId = filter_var(
            $profileId,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($profileId === false) {
            throw new InvalidArgumentException(
                'Selecione um perfil valido.'
            );
        }

        return $profileId;
    }

    private static function normalizeName(string $name): string
    {
        $name = self::normalizeText(
            $name,
            'nome do usuario'
        );

        if ($name === '') {
            throw new InvalidArgumentException(
                'Nome do usuario e obrigatorio.'
            );
        }

        if (strlen($name) > 150) {
            throw new InvalidArgumentException(
                'Nome do usuario deve ter no maximo 150 caracteres.'
            );
        }

        return $name;
    }

    private static function normalizeUsername(
        string $username
    ): string {
        $username = trim($username);

        if (
            !preg_match(
                '/^[a-zA-Z0-9_.-]{3,80}$/',
                $username
            )
        ) {
            throw new InvalidArgumentException(
                'O usuario deve ter entre 3 e 80 caracteres e conter somente letras, numeros, ponto, hifen ou sublinhado.'
            );
        }

        return $username;
    }

    private static function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));

        if (
            strlen($email) > 150
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            throw new InvalidArgumentException(
                'Informe um e-mail valido.'
            );
        }

        return $email;
    }

    private static function normalizePhone(
        ?string $phone
    ): ?string {
        if ($phone === null) {
            return null;
        }

        $phone = self::normalizeText(
            $phone,
            'telefone'
        );

        if ($phone === '') {
            return null;
        }

        if (strlen($phone) > 30) {
            throw new InvalidArgumentException(
                'Telefone deve ter no maximo 30 caracteres.'
            );
        }

        if (
            !preg_match(
                '/^[0-9\s()+.\-]+$/',
                $phone
            )
        ) {
            throw new InvalidArgumentException(
                'Telefone possui caracteres invalidos.'
            );
        }

        return $phone;
    }

    private static function normalizeStatus(
        string $status
    ): string {
        $status = trim($status);

        if (
            !in_array(
                $status,
                self::VALID_STATUSES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Status de usuario invalido.'
            );
        }

        return $status;
    }

    private static function normalizePassword(
        string $password,
        string $confirmation,
        bool $required
    ): ?string {
        if ($password === '' && $confirmation === '') {
            if ($required) {
                throw new InvalidArgumentException(
                    'Senha e obrigatoria.'
                );
            }

            return null;
        }

        if ($password === '' || $confirmation === '') {
            throw new InvalidArgumentException(
                'Informe a senha e a confirmacao.'
            );
        }

        if (str_contains($password, "\0")) {
            throw new InvalidArgumentException(
                'Senha invalida.'
            );
        }

        $length = strlen($password);

        if ($length < 8 || $length > 72) {
            throw new InvalidArgumentException(
                'A senha deve ter entre 8 e 72 caracteres.'
            );
        }

        if (!preg_match('/[A-Za-z]/', $password)) {
            throw new InvalidArgumentException(
                'A senha deve conter pelo menos uma letra.'
            );
        }

        if (!preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException(
                'A senha deve conter pelo menos um numero.'
            );
        }

        if (!hash_equals($password, $confirmation)) {
            throw new InvalidArgumentException(
                'A confirmacao da senha nao corresponde.'
            );
        }

        return $password;
    }

    private static function normalizeBoolean(mixed $value): bool
    {
        return in_array(
            $value,
            [
                true,
                1,
                '1',
                'on',
                'yes',
                'sim',
            ],
            true
        );
    }

    private static function normalizeText(
        string $value,
        string $field
    ): string {
        if ($value !== strip_tags($value)) {
            throw new InvalidArgumentException(
                'HTML nao e permitido no campo ' . $field . '.'
            );
        }

        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException(
                'O campo ' . $field . ' possui conteudo invalido.'
            );
        }

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? '';

        return trim($value);
    }
}
