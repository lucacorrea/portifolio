<?php

declare(strict_types=1);

namespace App\CRM\Repository;

use App\CRM\DTO\ClientFormData;
use App\CRM\Entity\Client;
use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

final class ClientRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return Client[] */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $documentSearch = preg_replace('/\D+/', '', $search) ?? '';
            $where[] = '(codigo LIKE :search_code OR nome LIKE :search_name OR documento LIKE :search_document OR telefone LIKE :search_phone OR whatsapp LIKE :search_whatsapp OR email LIKE :search_email OR cidade LIKE :search_city)';
            $like = '%' . $search . '%';
            $params += [
                'search_code' => $like,
                'search_name' => $like,
                'search_document' => $documentSearch === '' ? '__never_match__' : '%' . $documentSearch . '%',
                'search_phone' => $like,
                'search_whatsapp' => $like,
                'search_email' => $like,
                'search_city' => $like,
            ];
        }

        foreach (['type' => 'tipo_pessoa', 'status' => 'status', 'city' => 'cidade'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = $value;
            }
        }

        $sql = 'SELECT id, codigo, tipo_pessoa, nome, documento, telefone, whatsapp, email,
                       endereco, numero, complemento, bairro, cidade, uf, cep, observacoes,
                       status, criado_em, atualizado_em
                  FROM clientes';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY nome ASC, id ASC';
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return array_map(static fn(array $row): Client => Client::fromArray($row), $statement->fetchAll());
    }

    public function findById(int $id): ?Client
    {
        $this->assertPositiveId($id);
        $statement = $this->connection->prepare(
            'SELECT id, codigo, tipo_pessoa, nome, documento, telefone, whatsapp, email,
                    endereco, numero, complemento, bairro, cidade, uf, cep, observacoes,
                    status, criado_em, atualizado_em
               FROM clientes
              WHERE id = :id
              LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : Client::fromArray($row);
    }

    /** @return array{total:int,active:int,inactive:int,new_month:int} */
    public function summary(): array
    {
        $statement = $this->connection->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status = 'inativo' THEN 1 ELSE 0 END) AS inactive,
                    SUM(CASE WHEN criado_em >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') THEN 1 ELSE 0 END) AS new_month
               FROM clientes"
        );
        $row = $statement->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'inactive' => (int) ($row['inactive'] ?? 0),
            'new_month' => (int) ($row['new_month'] ?? 0),
        ];
    }

    public function create(ClientFormData $data): Client
    {
        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO clientes
                    (tipo_pessoa, nome, documento, telefone, whatsapp, email, endereco, numero,
                     complemento, bairro, cidade, uf, cep, observacoes, status)
                 VALUES
                    (:person_type, :name, :document, :phone, :whatsapp, :email, :address, :number,
                     :complement, :district, :city, :state, :zip_code, :notes, :status)'
            );
            $this->bindForm($statement, $data);
            $statement->execute();

            $id = (int) $this->connection->lastInsertId();
            $this->assertPositiveId($id);
            $code = sprintf('CLI-%06d', $id);
            $update = $this->connection->prepare('UPDATE clientes SET codigo = :code WHERE id = :id');
            $update->execute(['code' => $code, 'id' => $id]);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }

        $client = $this->findById($id);
        if ($client === null) {
            throw new InvalidArgumentException('Cliente não encontrado após cadastro.');
        }
        return $client;
    }

    public function update(int $id, ClientFormData $data): void
    {
        $this->assertPositiveId($id);
        $statement = $this->connection->prepare(
            'UPDATE clientes
                SET tipo_pessoa = :person_type,
                    nome = :name,
                    documento = :document,
                    telefone = :phone,
                    whatsapp = :whatsapp,
                    email = :email,
                    endereco = :address,
                    numero = :number,
                    complemento = :complement,
                    bairro = :district,
                    cidade = :city,
                    uf = :state,
                    cep = :zip_code,
                    observacoes = :notes,
                    status = :status
              WHERE id = :id'
        );
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $this->bindForm($statement, $data);
        $statement->execute();
    }

    public function changeStatus(int $id, string $status): void
    {
        $this->assertPositiveId($id);
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            throw new InvalidArgumentException('Status inválido.');
        }
        $statement = $this->connection->prepare('UPDATE clientes SET status = :status WHERE id = :id');
        $statement->execute(['status' => $status, 'id' => $id]);
    }

    public function existsByDocument(string $document, ?int $ignoreId = null): bool
    {
        $document = preg_replace('/\D+/', '', $document) ?? '';
        if ($document === '') return false;
        $sql = 'SELECT COUNT(*) FROM clientes WHERE documento = :document';
        $params = ['document' => $document];
        if ($ignoreId !== null) {
            $this->assertPositiveId($ignoreId);
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        return (int) $statement->fetchColumn() > 0;
    }

    /** @return array<string, true> */
    public function importedSourceCodes(): array
    {
        $statement = $this->connection->query("SELECT codigo FROM clientes WHERE codigo LIKE 'A7-%'");
        $codes = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $code) {
            $codes[(string) $code] = true;
        }
        return $codes;
    }

    /** @return array<int, array{nome:string,telefone:?string}> */
    public function clientIdentityData(): array
    {
        $statement = $this->connection->query(
            "SELECT nome, telefone FROM clientes WHERE telefone IS NOT NULL AND telefone <> ''"
        );
        return $statement->fetchAll();
    }

    /**
     * @param array<int, array{code:string,data:ClientFormData}> $rows
     */
    public function createImportedBatch(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $ownsTransaction = !$this->connection->inTransaction();
        try {
            if ($ownsTransaction) {
                $this->connection->beginTransaction();
            }

            $statement = $this->connection->prepare(
                'INSERT INTO clientes
                    (codigo, tipo_pessoa, nome, documento, telefone, whatsapp, email, endereco, numero,
                     complemento, bairro, cidade, uf, cep, observacoes, status)
                 VALUES
                    (:code, :person_type, :name, :document, :phone, :whatsapp, :email, :address, :number,
                     :complement, :district, :city, :state, :zip_code, :notes, :status)'
            );

            foreach ($rows as $row) {
                $code = (string) ($row['code'] ?? '');
                $data = $row['data'] ?? null;
                if (preg_match('/^A7-\d{1,10}$/', $code) !== 1 || !$data instanceof ClientFormData) {
                    throw new InvalidArgumentException('Registro de importação inválido.');
                }
                $statement->bindValue('code', $code);
                $this->bindForm($statement, $data);
                $statement->execute();
            }

            if ($ownsTransaction) {
                $this->connection->commit();
            }
            return count($rows);
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                throw new InvalidArgumentException('Um dos clientes já foi importado. Analise o PDF novamente.', 0, $exception);
            }
            throw $exception;
        }
    }

    private function bindForm(\PDOStatement $statement, ClientFormData $data): void
    {
        $statement->bindValue('person_type', $data->personType());
        $statement->bindValue('name', $data->name());
        $statement->bindValue('document', $data->document());
        $statement->bindValue('phone', $data->phone());
        $statement->bindValue('whatsapp', $data->whatsapp());
        $statement->bindValue('email', $data->email());
        $statement->bindValue('address', $data->address());
        $statement->bindValue('number', $data->number());
        $statement->bindValue('complement', $data->complement());
        $statement->bindValue('district', $data->district());
        $statement->bindValue('city', $data->city());
        $statement->bindValue('state', $data->state());
        $statement->bindValue('zip_code', $data->zipCode());
        $statement->bindValue('notes', $data->notes());
        $statement->bindValue('status', $data->status());
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) throw new InvalidArgumentException('ID de cliente inválido.');
    }
}
