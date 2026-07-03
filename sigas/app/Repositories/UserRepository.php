<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Core\Validator;
use App\Domain\UserStatus;
use App\DTO\PaginatedResult;
use App\DTO\UserData;
use App\DTO\UserFilter;
use App\Exceptions\RepositoryException;
use App\Models\User;
use DateTimeInterface;
use PDO;
use PDOException;

final class UserRepository
{
    private const USER_COLUMNS = 'id, setor_id, setor_solicitado_id, nivel_id, nome, cpf, matricula, cargo, email,
        telefone, senha_hash, status, precisa_trocar_senha, tentativas_login, bloqueado_ate, ultimo_login_em,
        ultimo_login_ip, aprovado_por, aprovado_em, rejeitado_por, rejeitado_em, motivo_rejeicao,
        observacao_interna, versao_autorizacao, criado_em, atualizado_em, excluido_em';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?User
    {
        return $this->findOne('id = :id', ['id' => $id]);
    }

    public function findByCpf(string $cpf): ?User
    {
        return $this->findOne('cpf = :cpf', ['cpf' => Validator::onlyDigits($cpf)]);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOne('email = :email', ['email' => mb_strtolower(trim($email))]);
    }

    public function findByIdentity(string $identity): ?User
    {
        $identity = trim($identity);

        if (Validator::cpf($identity)) {
            return $this->findByCpf($identity);
        }

        return Validator::email($identity) ? $this->findByEmail($identity) : null;
    }

    public function cpfExists(string $cpf, ?int $ignoreUserId = null): bool
    {
        return $this->exists('cpf', Validator::onlyDigits($cpf), $ignoreUserId);
    }

    public function emailExists(string $email, ?int $ignoreUserId = null): bool
    {
        return $this->exists('email', mb_strtolower(trim($email)), $ignoreUserId);
    }

    public function registrationExists(string $registration, ?int $ignoreUserId = null): bool
    {
        return $this->exists('matricula', trim($registration), $ignoreUserId);
    }

    public function createPending(UserData $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO usuarios
                    (setor_solicitado_id, nome, cpf, matricula, cargo, email, telefone, senha_hash, status, criado_em)
                 VALUES
                    (:setor_solicitado_id, :nome, :cpf, :matricula, :cargo, :email, :telefone, :senha_hash, :status, NOW())'
            );
            $stmt->execute([
                'setor_solicitado_id' => $data->setorSolicitadoId,
                'nome' => $data->nome,
                'cpf' => $data->cpf,
                'matricula' => $data->matricula,
                'cargo' => $data->cargo,
                'email' => $data->email,
                'telefone' => $data->telefone,
                'senha_hash' => $data->senhaHash ?? '',
                'status' => UserStatus::PENDING->value,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw $this->fail('createPending', 'Falha ao criar usuário.', $exception);
        }
    }

    public function updateBasicData(int $userId, UserData $data): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE usuarios
                 SET nome = :nome, cpf = :cpf, matricula = :matricula, cargo = :cargo,
                     email = :email, telefone = :telefone, setor_solicitado_id = :setor_solicitado_id
                 WHERE id = :id AND excluido_em IS NULL'
            );
            $stmt->execute([
                'id' => $userId,
                'nome' => $data->nome,
                'cpf' => $data->cpf,
                'matricula' => $data->matricula,
                'cargo' => $data->cargo,
                'email' => $data->email,
                'telefone' => $data->telefone,
                'setor_solicitado_id' => $data->setorSolicitadoId,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('updateBasicData', 'Falha ao atualizar usuário.', $exception);
        }
    }

    public function assignSector(int $userId, int $sectorId): void
    {
        $this->updateColumn($userId, 'setor_id', $sectorId);
    }

    public function assignAccessLevel(int $userId, int $levelId): void
    {
        $this->updateColumn($userId, 'nivel_id', $levelId);
    }

    public function updateStatus(int $userId, UserStatus $status): void
    {
        $this->updateColumn($userId, 'status', $status->value);
    }

    public function incrementAuthorizationVersion(int $userId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE usuarios
                 SET versao_autorizacao = versao_autorizacao + 1
                 WHERE id = :id AND excluido_em IS NULL'
            );
            $stmt->execute(['id' => $userId]);

            $version = $this->pdo->prepare('SELECT versao_autorizacao FROM usuarios WHERE id = :id');
            $version->execute(['id' => $userId]);

            return (int) $version->fetchColumn();
        } catch (PDOException $exception) {
            throw $this->fail('incrementAuthorizationVersion', 'Falha ao atualizar autorização.', $exception);
        }
    }

    public function countActiveAdministrators(): int
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT COUNT(*)
                 FROM usuarios u
                 INNER JOIN niveis_acesso n ON n.id = u.nivel_id
                 WHERE n.slug = 'administrador'
                   AND u.status = 'ativo'
                   AND u.excluido_em IS NULL"
            );

            return (int) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw $this->fail('countActiveAdministrators', 'Falha ao contar administradores.', $exception);
        }
    }

    public function list(UserFilter $filter): PaginatedResult
    {
        [$where, $params] = $this->filterWhere($filter);

        try {
            $count = $this->pdo->prepare("SELECT COUNT(*) FROM usuarios u WHERE {$where}");
            $count->execute($params);
            $total = (int) $count->fetchColumn();

            $sql = 'SELECT ' . self::USER_COLUMNS . "
                    FROM usuarios u
                    WHERE {$where}
                    ORDER BY {$filter->sortColumn()} {$filter->direction}
                    LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }

            $stmt->bindValue(':limit', $filter->pagination->getPerPage(), PDO::PARAM_INT);
            $stmt->bindValue(':offset', $filter->pagination->getOffset(), PDO::PARAM_INT);
            $stmt->execute();

            $items = array_map(static fn (array $row): User => User::fromArray($row), $stmt->fetchAll());

            return new PaginatedResult($items, $total, $filter->pagination->getPage(), $filter->pagination->getPerPage());
        } catch (PDOException $exception) {
            throw $this->fail('list', 'Falha ao listar usuários.', $exception);
        }
    }

    public function countByStatus(UserStatus $status): int
    {
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE status = :status AND excluido_em IS NULL');
            $stmt->execute(['status' => $status->value]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw $this->fail('countByStatus', 'Falha ao contar usuários.', $exception);
        }
    }

    public function registerFailedLogin(int $userId, int $attempts, ?DateTimeInterface $blockedUntil): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE usuarios
                 SET tentativas_login = :tentativas_login, bloqueado_ate = :bloqueado_ate
                 WHERE id = :id AND excluido_em IS NULL'
            );
            $stmt->execute([
                'id' => $userId,
                'tentativas_login' => $attempts,
                'bloqueado_ate' => $blockedUntil?->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('registerFailedLogin', 'Falha ao registrar tentativa de login.', $exception);
        }
    }

    public function registerSuccessfulLogin(int $userId, ?string $ip): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE usuarios
                 SET tentativas_login = 0,
                     bloqueado_ate = NULL,
                     ultimo_login_em = NOW(),
                     ultimo_login_ip = :ultimo_login_ip
                 WHERE id = :id AND excluido_em IS NULL'
            );
            $stmt->execute([
                'id' => $userId,
                'ultimo_login_ip' => $ip,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('registerSuccessfulLogin', 'Falha ao registrar login.', $exception);
        }
    }

    public function clearExpiredLoginLock(int $userId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE usuarios
                 SET tentativas_login = 0, bloqueado_ate = NULL
                 WHERE id = :id
                   AND excluido_em IS NULL
                   AND bloqueado_ate IS NOT NULL
                   AND bloqueado_ate <= NOW()'
            );
            $stmt->execute(['id' => $userId]);
        } catch (PDOException $exception) {
            throw $this->fail('clearExpiredLoginLock', 'Falha ao limpar bloqueio de login.', $exception);
        }
    }

    /** @param array<string, mixed> $params */
    private function findOne(string $where, array $params): ?User
    {
        try {
            $stmt = $this->pdo->prepare('SELECT ' . self::USER_COLUMNS . " FROM usuarios WHERE {$where} AND excluido_em IS NULL LIMIT 1");
            $stmt->execute($params);
            $row = $stmt->fetch();

            return is_array($row) ? User::fromArray($row) : null;
        } catch (PDOException $exception) {
            throw $this->fail('findOne', 'Falha ao consultar usuário.', $exception);
        }
    }

    private function exists(string $column, string $value, ?int $ignoreUserId): bool
    {
        $allowed = ['cpf', 'email', 'matricula'];

        if (!in_array($column, $allowed, true) || $value === '') {
            return false;
        }

        try {
            $params = ['value' => $value];
            $sql = "SELECT 1 FROM usuarios WHERE {$column} = :value AND excluido_em IS NULL";

            if ($ignoreUserId !== null) {
                $sql .= ' AND id <> :ignore_id';
                $params['ignore_id'] = $ignoreUserId;
            }

            $sql .= ' LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (bool) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw $this->fail('exists', 'Falha ao verificar duplicidade.', $exception);
        }
    }

    private function updateColumn(int $userId, string $column, int|string $value): void
    {
        $allowed = ['setor_id', 'nivel_id', 'status'];

        if (!in_array($column, $allowed, true)) {
            throw new RepositoryException('Campo de usuário não permitido.');
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE usuarios SET {$column} = :value WHERE id = :id AND excluido_em IS NULL");
            $stmt->execute(['id' => $userId, 'value' => $value]);
        } catch (PDOException $exception) {
            throw $this->fail('updateColumn', 'Falha ao atualizar usuário.', $exception);
        }
    }

    private function fail(string $operation, string $message, PDOException $exception): RepositoryException
    {
        Logger::application('Repository operation failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);

        return new RepositoryException($message, 0, $exception);
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function filterWhere(UserFilter $filter): array
    {
        $where = ['u.excluido_em IS NULL'];
        $params = [];

        if ($filter->search !== null && trim($filter->search) !== '') {
            $where[] = '(u.nome LIKE :search OR u.email LIKE :search OR u.cpf LIKE :search OR u.matricula LIKE :search)';
            $params['search'] = '%' . trim($filter->search) . '%';
        }

        if ($filter->sectorId !== null) {
            $where[] = 'u.setor_id = :sector_id';
            $params['sector_id'] = $filter->sectorId;
        }

        if ($filter->levelId !== null) {
            $where[] = 'u.nivel_id = :level_id';
            $params['level_id'] = $filter->levelId;
        }

        if ($filter->status !== null) {
            $where[] = 'u.status = :status';
            $params['status'] = $filter->status->value;
        }

        return [implode(' AND ', $where), $params];
    }
}
