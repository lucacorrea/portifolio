<?php
declare(strict_types=1);

namespace App\Access\Repository;

use App\Access\Entity\Profile;
use InvalidArgumentException;
use PDO;

final class ProfileRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?Profile
    {
        $this->assertPositiveId($id);

        $statement = $this->connection->prepare(
            'SELECT id, nome, descricao, protegido, status, criado_em, atualizado_em
               FROM perfis
              WHERE id = :id
              LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : Profile::fromArray($row);
    }

    public function findByName(string $name): ?Profile
    {
        $statement = $this->connection->prepare(
            'SELECT id, nome, descricao, protegido, status, criado_em, atualizado_em
               FROM perfis
              WHERE nome = :name
              LIMIT 1'
        );
        $statement->execute(['name' => trim($name)]);

        $row = $statement->fetch();

        return $row === false ? null : Profile::fromArray($row);
    }

    /**
     * @return Profile[]
     */
    public function findAll(): array
    {
        $statement = $this->connection->query(
            'SELECT id, nome, descricao, protegido, status, criado_em, atualizado_em
               FROM perfis
              ORDER BY nome ASC'
        );

        return array_map(
            static fn (array $row): Profile => Profile::fromArray($row),
            $statement->fetchAll()
        );
    }

    public function create(Profile $profile): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO perfis (nome, descricao, protegido, status)
             VALUES (:name, :description, :protected, :status)'
        );
        $statement->execute([
            'name' => $profile->name(),
            'description' => $profile->description(),
            'protected' => $profile->isProtected() ? 1 : 0,
            'status' => $profile->status(),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function update(Profile $profile): bool
    {
        $id = $profile->id();
        if ($id === null) {
            throw new InvalidArgumentException('ID do perfil e obrigatorio para atualizar.');
        }

        $this->assertPositiveId($id);

        $statement = $this->connection->prepare(
            'UPDATE perfis
                SET nome = :name,
                    descricao = :description,
                    protegido = :protected,
                    status = :status
              WHERE id = :id'
        );

        return $statement->execute([
            'id' => $id,
            'name' => $profile->name(),
            'description' => $profile->description(),
            'protected' => $profile->isProtected() ? 1 : 0,
            'status' => $profile->status(),
        ]);
    }

    public function existsByName(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM perfis WHERE nome = :name';
        $params = ['name' => trim($name)];

        if ($ignoreId !== null) {
            $this->assertPositiveId($ignoreId);
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public function countUsers(int $profileId): int
    {
        $this->assertPositiveId($profileId);

        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM usuarios WHERE perfil_id = :profile_id'
        );
        $statement->execute(['profile_id' => $profileId]);

        return (int) $statement->fetchColumn();
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID invalido.');
        }
    }
}
