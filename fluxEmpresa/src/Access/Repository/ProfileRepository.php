<?php
declare(strict_types=1);

namespace App\Access\Repository;

use App\Access\DTO\ProfileListItem;
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

    /**
     * @return ProfileListItem[]
     */
    public function findAllWithStatistics(array $filters = []): array
    {
        $where = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = 'p.nome LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['ativo', 'inativo'], true)) {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }

        $type = (string) ($filters['type'] ?? '');
        if ($type === 'protegido') {
            $where[] = 'p.protegido = 1';
        } elseif ($type === 'personalizado') {
            $where[] = 'p.protegido = 0';
        }

        $sql = 'SELECT
                    p.id,
                    p.nome,
                    p.descricao,
                    p.protegido,
                    p.status,
                    p.criado_em,
                    p.atualizado_em,
                    COUNT(DISTINCT u.id) AS total_usuarios,
                    COUNT(DISTINCT pp.permissao_id) AS total_permissoes
                FROM perfis p
                LEFT JOIN usuarios u ON u.perfil_id = p.id
                LEFT JOIN perfil_permissoes pp ON pp.perfil_id = p.id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY p.id, p.nome, p.descricao, p.protegido, p.status, p.criado_em, p.atualizado_em
                  ORDER BY p.protegido DESC, p.nome ASC';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return array_map(
            static fn (array $row): ProfileListItem => ProfileListItem::fromArray($row),
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

    public function changeStatus(int $profileId, string $status): void
    {
        $this->assertPositiveId($profileId);
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            throw new InvalidArgumentException('Status de perfil invalido.');
        }

        $statement = $this->connection->prepare(
            'UPDATE perfis SET status = :status WHERE id = :id'
        );
        $statement->execute([
            'id' => $profileId,
            'status' => $status,
        ]);
    }

    public function delete(int $profileId): void
    {
        $this->assertPositiveId($profileId);

        $statement = $this->connection->prepare(
            'DELETE FROM perfis WHERE id = :id'
        );
        $statement->execute(['id' => $profileId]);
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

    public function nameExists(string $name, ?int $ignoreId = null): bool
    {
        return $this->existsByName($name, $ignoreId);
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

    public function countPermissions(int $profileId): int
    {
        $this->assertPositiveId($profileId);

        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM perfil_permissoes WHERE perfil_id = :profile_id'
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
