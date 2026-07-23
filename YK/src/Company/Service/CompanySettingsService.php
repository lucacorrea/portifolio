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
            'documento' => $this->document($data['documento'] ?? null),
            'inscricao_estadual' => $this->clean($data['inscricao_estadual'] ?? null, 40),
            'inscricao_municipal' => $this->clean($data['inscricao_municipal'] ?? null, 40),
            'email' => $this->email($data['email'] ?? null),
            'crt' => $this->crt($data['crt'] ?? null),
            'cnae_principal' => $this->digits($data['cnae_principal'] ?? null, 7),
            'telefone' => $this->clean($data['telefone'] ?? null, 30),
            'endereco' => $this->clean($data['endereco'] ?? null, 255),
            'endereco_logradouro' => $this->clean($data['endereco_logradouro'] ?? null, 150),
            'endereco_numero' => $this->clean($data['endereco_numero'] ?? null, 30),
            'endereco_complemento' => $this->clean($data['endereco_complemento'] ?? null, 100),
            'endereco_bairro' => $this->clean($data['endereco_bairro'] ?? null, 100),
            'endereco_cidade' => $this->clean($data['endereco_cidade'] ?? null, 100),
            'endereco_uf' => $this->uf($data['endereco_uf'] ?? null),
            'endereco_cep' => $this->digits($data['endereco_cep'] ?? null, 8),
            'codigo_municipio_ibge' => $this->digits($data['codigo_municipio_ibge'] ?? null, 7),
            'logo' => $this->clean($data['logo'] ?? null, 255),
            'user_id' => $userId,
        ];

        $statement = $this->connection->prepare(
            'INSERT INTO configuracoes_empresa
                (id, razao_social, nome_fantasia, documento, inscricao_estadual,
                 inscricao_municipal, email, crt, cnae_principal, telefone, endereco,
                 endereco_logradouro, endereco_numero, endereco_complemento, endereco_bairro,
                 endereco_cidade, endereco_uf, endereco_cep, codigo_municipio_ibge, logo, atualizado_por)
             VALUES
                (1, :razao_social, :nome_fantasia, :documento, :inscricao_estadual,
                 :inscricao_municipal, :email, :crt, :cnae_principal, :telefone, :endereco,
                 :endereco_logradouro, :endereco_numero, :endereco_complemento, :endereco_bairro,
                 :endereco_cidade, :endereco_uf, :endereco_cep, :codigo_municipio_ibge, :logo, :user_id)
             ON DUPLICATE KEY UPDATE
                razao_social = VALUES(razao_social),
                nome_fantasia = VALUES(nome_fantasia),
                documento = VALUES(documento),
                inscricao_estadual = VALUES(inscricao_estadual),
                inscricao_municipal = VALUES(inscricao_municipal),
                email = VALUES(email),
                crt = VALUES(crt),
                cnae_principal = VALUES(cnae_principal),
                telefone = VALUES(telefone),
                endereco = VALUES(endereco),
                endereco_logradouro = VALUES(endereco_logradouro),
                endereco_numero = VALUES(endereco_numero),
                endereco_complemento = VALUES(endereco_complemento),
                endereco_bairro = VALUES(endereco_bairro),
                endereco_cidade = VALUES(endereco_cidade),
                endereco_uf = VALUES(endereco_uf),
                endereco_cep = VALUES(endereco_cep),
                codigo_municipio_ibge = VALUES(codigo_municipio_ibge),
                logo = VALUES(logo),
                atualizado_por = VALUES(atualizado_por)'
        );
        $statement->execute($payload);
    }

    private function clean(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        $length = function_exists('mb_strlen')
            ? mb_strlen($text, 'UTF-8')
            : (function_exists('iconv_strlen') ? iconv_strlen($text, 'UTF-8') : strlen($text));
        if ($length === false || str_contains($text, "\0") || $text !== strip_tags($text) || $length > $max) {
            throw new InvalidArgumentException('Dados da empresa inválidos.');
        }
        return $text;
    }

    private function email(mixed $value): ?string
    {
        $email = $this->clean($value, 150);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('E-mail da empresa inválido.');
        }

        return $email;
    }

    private function document(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') return null;
        if (preg_match('/^[0-9.\/\-\s]+$/', $raw) !== 1) {
            throw new InvalidArgumentException('Informe um CPF ou CNPJ válido para a empresa.');
        }

        $document = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($document) === 11) {
            if (!self::isValidCpf($document)) {
                throw new InvalidArgumentException('Informe um CPF válido para a empresa.');
            }
            return $document;
        }
        if (strlen($document) === 14) {
            if (!self::isValidCnpj($document)) {
                throw new InvalidArgumentException('Informe um CNPJ válido para a empresa.');
            }
            return $document;
        }

        throw new InvalidArgumentException('Informe um CPF ou CNPJ válido para a empresa.');
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (preg_match('/^\d{11}$/', $cpf) !== 1 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) return false;
        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $cpf[$index] * (($position + 1) - $index);
            }
            if ((int) $cpf[$position] !== ((10 * $sum) % 11) % 10) return false;
        }
        return true;
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (preg_match('/^\d{14}$/', $cnpj) !== 1 || preg_match('/^(\d)\1{13}$/', $cnpj) === 1) return false;
        $rounds = [
            12 => [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            13 => [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];
        foreach ($rounds as $position => $weights) {
            $sum = 0;
            foreach ($weights as $index => $weight) $sum += (int) $cnpj[$index] * $weight;
            $remainder = $sum % 11;
            if ((int) $cnpj[$position] !== ($remainder < 2 ? 0 : 11 - $remainder)) return false;
        }
        return true;
    }

    private function digits(mixed $value, int $length): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw !== '' && preg_match('/^[0-9.\/\-\s]+$/', $raw) !== 1) {
            throw new InvalidArgumentException('Dados fiscais da empresa inválidos.');
        }
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === null || $digits === '') return null;
        if (strlen($digits) !== $length) {
            throw new InvalidArgumentException('Dados fiscais da empresa inválidos.');
        }

        return $digits;
    }

    private function crt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
        $crt = filter_var($value, FILTER_VALIDATE_INT);
        if ($crt === false || !in_array($crt, [1, 2, 3, 4], true)) {
            throw new InvalidArgumentException('Regime tributário inválido.');
        }

        return $crt;
    }

    private function uf(mixed $value): ?string
    {
        $uf = strtoupper(trim((string) ($value ?? '')));
        if ($uf === '') return null;
        if (preg_match('/^[A-Z]{2}$/', $uf) !== 1) {
            throw new InvalidArgumentException('UF da empresa inválida.');
        }

        return $uf;
    }
}
