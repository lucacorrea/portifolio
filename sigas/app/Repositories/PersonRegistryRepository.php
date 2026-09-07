<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Validator;
use App\Exceptions\RepositoryException;
use PDO;
use PDOException;
use Throwable;

final class PersonRegistryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByCpf(string $cpf): ?array
    {
        $cpf = Validator::onlyDigits($cpf);
        if (!Validator::cpf($cpf)) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT p.*,
                        f.id AS familia_id,
                        f.codigo AS familia_codigo,
                        f.zona, f.logradouro, f.numero, f.complemento,
                        f.bairro, f.comunidade, f.ponto_referencia, f.cep,
                        f.quantidade_membros, f.renda_familiar
                 FROM pessoas p
                 LEFT JOIN familias f ON f.responsavel_pessoa_id = p.id
                 WHERE p.cpf = :cpf
                 LIMIT 1'
            );
            $stmt->execute(['cpf' => $cpf]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            throw new RepositoryException('Falha ao consultar a pessoa no cadastro central.', 0, $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findById(int $personId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT p.*,
                        f.id AS familia_id,
                        f.codigo AS familia_codigo,
                        f.zona, f.logradouro, f.numero, f.complemento,
                        f.bairro, f.comunidade, f.ponto_referencia, f.cep,
                        f.quantidade_membros, f.renda_familiar
                 FROM pessoas p
                 LEFT JOIN familias f ON f.responsavel_pessoa_id = p.id
                 WHERE p.id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $personId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            throw new RepositoryException('Falha ao consultar a pessoa no cadastro central.', 0, $exception);
        }
    }

    /**
     * Cria a pessoa uma única vez ou atualiza dados cadastrais seguros do registro já existente.
     * O CPF é a chave de identidade desta operação e nunca é trocado silenciosamente.
     *
     * @param array<string,mixed> $person
     * @param array<string,mixed> $family
     * @return array{person_id:int,family_id:int,created:bool}
     */
    public function saveByCpf(array $person, array $family, int $userId): array
    {
        $cpf = Validator::onlyDigits((string) ($person['cpf'] ?? ''));
        if (!Validator::cpf($cpf)) {
            throw new RepositoryException('CPF inválido para o cadastro central.');
        }

        $name = trim((string) ($person['nome'] ?? ''));
        if ($name === '') {
            throw new RepositoryException('Nome da pessoa é obrigatório.');
        }

        $this->pdo->beginTransaction();

        try {
            $lock = $this->pdo->prepare('SELECT id FROM pessoas WHERE cpf = :cpf LIMIT 1 FOR UPDATE');
            $lock->execute(['cpf' => $cpf]);
            $personId = (int) ($lock->fetchColumn() ?: 0);
            $created = false;

            $personParams = [
                'nome' => $name,
                'cpf' => $cpf,
                'nis' => $this->nullable($person['nis'] ?? null),
                'rg' => $this->nullable($person['rg'] ?? null),
                'data_nascimento' => $this->nullable($person['data_nascimento'] ?? null),
                'telefone' => $this->nullable($person['telefone'] ?? null),
                'email' => $this->nullable($person['email'] ?? null),
                'usuario_id' => $userId,
            ];

            if ($personId <= 0) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO pessoas
                        (nome, cpf, nis, rg, data_nascimento, telefone, email,
                         status, criado_por, atualizado_por)
                     VALUES
                        (:nome, :cpf, :nis, :rg, :data_nascimento, :telefone, :email,
                         \'ativo\', :usuario_id, :usuario_id)'
                );
                $insert->execute($personParams);
                $personId = (int) $this->pdo->lastInsertId();
                $created = true;
            } else {
                $personParams['id'] = $personId;
                $update = $this->pdo->prepare(
                    'UPDATE pessoas
                     SET nome = :nome,
                         nis = COALESCE(:nis, nis),
                         rg = COALESCE(:rg, rg),
                         data_nascimento = COALESCE(:data_nascimento, data_nascimento),
                         telefone = COALESCE(:telefone, telefone),
                         email = COALESCE(:email, email),
                         atualizado_por = :usuario_id
                     WHERE id = :id'
                );
                $update->execute($personParams);
            }

            $familyId = $this->saveFamily($personId, $family, $userId);
            $this->pdo->commit();

            return ['person_id' => $personId, 'family_id' => $familyId, 'created' => $created];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof RepositoryException) {
                throw $exception;
            }
            throw new RepositoryException('Falha ao salvar a pessoa no cadastro central.', 0, $exception);
        }
    }

    /** @param array<string,mixed> $family */
    private function saveFamily(int $personId, array $family, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM familias WHERE responsavel_pessoa_id = :pessoa_id LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['pessoa_id' => $personId]);
        $familyId = (int) ($stmt->fetchColumn() ?: 0);

        $params = [
            'pessoa_id' => $personId,
            'zona' => $this->nullable($family['zona'] ?? null),
            'logradouro' => $this->nullable($family['logradouro'] ?? $family['endereco'] ?? null),
            'numero' => $this->nullable($family['numero'] ?? null),
            'complemento' => $this->nullable($family['complemento'] ?? null),
            'bairro' => $this->nullable($family['bairro'] ?? null),
            'comunidade' => $this->nullable($family['comunidade'] ?? null),
            'ponto_referencia' => $this->nullable($family['ponto_referencia'] ?? $family['referencia'] ?? null),
            'cep' => $this->nullable($family['cep'] ?? null),
            'quantidade_membros' => max(1, (int) ($family['quantidade_membros'] ?? 1)),
            'renda_familiar' => $this->nullableDecimal($family['renda_familiar'] ?? null),
            'usuario_id' => $userId,
        ];

        if ($familyId <= 0) {
            $params['codigo'] = sprintf('FAM-%010d', $personId);
            $insert = $this->pdo->prepare(
                'INSERT INTO familias
                    (codigo, responsavel_pessoa_id, zona, logradouro, numero,
                     complemento, bairro, comunidade, ponto_referencia, cep,
                     quantidade_membros, renda_familiar, status, criado_por, atualizado_por)
                 VALUES
                    (:codigo, :pessoa_id, :zona, :logradouro, :numero,
                     :complemento, :bairro, :comunidade, :ponto_referencia, :cep,
                     :quantidade_membros, :renda_familiar, \'ativo\', :usuario_id, :usuario_id)'
            );
            $insert->execute($params);
            return (int) $this->pdo->lastInsertId();
        }

        $params['id'] = $familyId;
        $update = $this->pdo->prepare(
            'UPDATE familias
             SET zona = COALESCE(:zona, zona),
                 logradouro = COALESCE(:logradouro, logradouro),
                 numero = COALESCE(:numero, numero),
                 complemento = COALESCE(:complemento, complemento),
                 bairro = COALESCE(:bairro, bairro),
                 comunidade = COALESCE(:comunidade, comunidade),
                 ponto_referencia = COALESCE(:ponto_referencia, ponto_referencia),
                 cep = COALESCE(:cep, cep),
                 quantidade_membros = :quantidade_membros,
                 renda_familiar = COALESCE(:renda_familiar, renda_familiar),
                 atualizado_por = :usuario_id
             WHERE id = :id'
        );
        $update->execute($params);
        return $familyId;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $normalized = str_replace(['.', ','], ['', '.'], trim((string) $value));
        if (!is_numeric($normalized)) {
            return null;
        }
        return number_format((float) $normalized, 2, '.', '');
    }
}
