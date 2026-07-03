<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\UserStatus;
use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        public int $id,
        public ?int $setorId,
        public ?int $setorSolicitadoId,
        public ?int $nivelId,
        public string $nome,
        public string $cpf,
        public ?string $matricula,
        public ?string $cargo,
        public string $email,
        public ?string $telefone,
        private string $senhaHash,
        public UserStatus $status,
        public bool $precisaTrocarSenha,
        public int $tentativasLogin,
        public ?DateTimeImmutable $bloqueadoAte,
        public ?DateTimeImmutable $ultimoLoginEm,
        public ?string $ultimoLoginIp,
        public ?int $aprovadoPor,
        public ?DateTimeImmutable $aprovadoEm,
        public ?int $rejeitadoPor,
        public ?DateTimeImmutable $rejeitadoEm,
        public ?string $motivoRejeicao,
        public ?string $observacaoInterna,
        public int $versaoAutorizacao,
        public DateTimeImmutable $criadoEm,
        public ?DateTimeImmutable $atualizadoEm,
        public ?DateTimeImmutable $excluidoEm,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            self::nullableInt($row['setor_id'] ?? null),
            self::nullableInt($row['setor_solicitado_id'] ?? null),
            self::nullableInt($row['nivel_id'] ?? null),
            (string) $row['nome'],
            (string) $row['cpf'],
            self::nullableString($row['matricula'] ?? null),
            self::nullableString($row['cargo'] ?? null),
            (string) $row['email'],
            self::nullableString($row['telefone'] ?? null),
            (string) $row['senha_hash'],
            UserStatus::from((string) $row['status']),
            (bool) $row['precisa_trocar_senha'],
            (int) $row['tentativas_login'],
            self::nullableDate($row['bloqueado_ate'] ?? null),
            self::nullableDate($row['ultimo_login_em'] ?? null),
            self::nullableString($row['ultimo_login_ip'] ?? null),
            self::nullableInt($row['aprovado_por'] ?? null),
            self::nullableDate($row['aprovado_em'] ?? null),
            self::nullableInt($row['rejeitado_por'] ?? null),
            self::nullableDate($row['rejeitado_em'] ?? null),
            self::nullableString($row['motivo_rejeicao'] ?? null),
            self::nullableString($row['observacao_interna'] ?? null),
            (int) $row['versao_autorizacao'],
            new DateTimeImmutable((string) $row['criado_em']),
            self::nullableDate($row['atualizado_em'] ?? null),
            self::nullableDate($row['excluido_em'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'setor_id' => $this->setorId,
            'setor_solicitado_id' => $this->setorSolicitadoId,
            'nivel_id' => $this->nivelId,
            'nome' => $this->nome,
            'cpf' => $this->cpf,
            'matricula' => $this->matricula,
            'cargo' => $this->cargo,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'status' => $this->status->value,
            'precisa_trocar_senha' => $this->precisaTrocarSenha,
            'versao_autorizacao' => $this->versaoAutorizacao,
        ];
    }

    public function getPasswordHashForVerification(): string
    {
        return $this->senhaHash;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private static function nullableDate(mixed $value): ?DateTimeImmutable
    {
        return empty($value) ? null : new DateTimeImmutable((string) $value);
    }
}
