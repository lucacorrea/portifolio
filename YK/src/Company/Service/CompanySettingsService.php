<?php

declare(strict_types=1);

namespace App\Company\Service;

use InvalidArgumentException;
use PDO;

final class CompanySettingsService
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array<string,mixed> */
    public function get(): array
    {
        $statement = $this->connection->query('SELECT * FROM configuracoes_empresa WHERE id = 1');
        $row = $statement->fetch();
        return $row === false ? [] : $row;
    }

    public function save(array $data, int $userId): void
    {
        $payload = [
            'razao_social' => $this->clean($data['razao_social'] ?? null, 150),
            'nome_fantasia' => $this->clean($data['nome_fantasia'] ?? null, 150),
            'documento' => $this->clean($data['documento'] ?? null, 30),
            'telefone' => $this->clean($data['telefone'] ?? null, 30),
            'endereco' => $this->clean($data['endereco'] ?? null, 255),
            'logo' => $this->clean($data['logo'] ?? null, 255),
            'user_id' => $userId,
        ];

        $statement = $this->connection->prepare(
            'INSERT INTO configuracoes_empresa
                (id, razao_social, nome_fantasia, documento, telefone, endereco, logo, atualizado_por)
             VALUES
                (1, :razao_social, :nome_fantasia, :documento, :telefone, :endereco, :logo, :user_id)
             ON DUPLICATE KEY UPDATE
                razao_social = VALUES(razao_social),
                nome_fantasia = VALUES(nome_fantasia),
                documento = VALUES(documento),
                telefone = VALUES(telefone),
                endereco = VALUES(endereco),
                logo = VALUES(logo),
                atualizado_por = VALUES(atualizado_por)'
        );
        $statement->execute($payload);
    }

    private function clean(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        if (str_contains($text, "\0") || $text !== strip_tags($text) || mb_strlen($text) > $max) {
            throw new InvalidArgumentException('Dados da empresa inválidos.');
        }
        return $text;
    }
}
