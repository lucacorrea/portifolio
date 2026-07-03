<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Core\Validator;
use App\DTO\ComidaMesaFilter;
use App\DTO\PaginatedResult;
use App\Exceptions\RepositoryException;
use PDO;
use PDOException;

final class ComidaMesaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function listCompetences(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT id, ano, mes, status, inicio_entregas, fim_entregas
                 FROM comida_mesa_competencias
                 ORDER BY ano DESC, mes DESC'
            );

            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            throw $this->fail('listCompetences', 'Falha ao consultar competências.', $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findCompetenceById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, ano, mes, status, inicio_entregas, fim_entregas
                 FROM comida_mesa_competencias
                 WHERE id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();

            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            throw $this->fail('findCompetenceById', 'Falha ao consultar competência.', $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findDefaultCompetence(): ?array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT id, ano, mes, status, inicio_entregas, fim_entregas
                 FROM comida_mesa_competencias
                 WHERE status = 'aberta'
                 ORDER BY ano DESC, mes DESC
                 LIMIT 1"
            );
            $row = $stmt->fetch();

            if (is_array($row)) {
                return $row;
            }

            $stmt = $this->pdo->query(
                'SELECT id, ano, mes, status, inicio_entregas, fim_entregas
                 FROM comida_mesa_competencias
                 ORDER BY ano DESC, mes DESC
                 LIMIT 1'
            );
            $row = $stmt->fetch();

            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            throw $this->fail('findDefaultCompetence', 'Falha ao consultar competência padrão.', $exception);
        }
    }

    /** @return list<array<string,mixed>> */
    public function listActivePoles(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT id, nome, slug
                 FROM comida_mesa_polos
                 WHERE ativo = 1
                 ORDER BY nome'
            );

            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            throw $this->fail('listActivePoles', 'Falha ao consultar polos.', $exception);
        }
    }

    /** @return array<string,int> */
    public function getStatistics(?int $competenceId): array
    {
        try {
            $statistics = [
                'familias_cadastradas' => $this->count('SELECT COUNT(*) FROM familias'),
                'beneficiarias_ativas' => $this->count("SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'ativa'"),
                'em_analise' => $this->count("SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'em_analise'"),
                'lista_espera' => $this->count("SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'lista_espera'"),
                'suspensas' => $this->count("SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'suspensa'"),
                'bloqueadas' => $this->count("SELECT COUNT(*) FROM comida_mesa_inscricoes WHERE status = 'bloqueada'"),
                'polos_ativos' => $this->count('SELECT COUNT(*) FROM comida_mesa_polos WHERE ativo = 1'),
                'entregas_competencia' => 0,
                'aguardando_retirada' => 0,
            ];

            if ($competenceId !== null) {
                $statistics['entregas_competencia'] = $this->count(
                    "SELECT COUNT(*)
                     FROM comida_mesa_entregas
                     WHERE competencia_id = :competencia_id AND status = 'entregue'",
                    ['competencia_id' => $competenceId]
                );
                $statistics['aguardando_retirada'] = $this->count(
                    "SELECT COUNT(*)
                     FROM comida_mesa_inscricoes i
                     WHERE i.status = 'ativa'
                       AND NOT EXISTS (
                           SELECT 1
                           FROM comida_mesa_entregas e
                           WHERE e.inscricao_id = i.id
                             AND e.competencia_id = :competencia_id
                             AND e.status = 'entregue'
                       )",
                    ['competencia_id' => $competenceId]
                );
            }

            return $statistics;
        } catch (PDOException $exception) {
            throw $this->fail('getStatistics', 'Falha ao consultar indicadores.', $exception);
        }
    }

    public function paginate(ComidaMesaFilter $filter): PaginatedResult
    {
        [$where, $params] = $this->filterWhere($filter);
        $deliveryJoin = $this->deliveryJoin($filter->competenceId, 'entrega_competencia_id');

        if ($filter->competenceId !== null) {
            $params['entrega_competencia_id'] = $filter->competenceId;
        }

        try {
            $count = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM comida_mesa_inscricoes i
                 INNER JOIN familias f ON f.id = i.familia_id
                 INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
                 LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
                 {$deliveryJoin}
                 WHERE {$where}"
            );
            $this->bindFilterParams($count, $params);
            $count->execute();
            $total = (int) $count->fetchColumn();
            $totalPages = max(1, (int) ceil($total / $filter->perPage));
            $page = min($filter->page, $totalPages);
            $offset = ($page - 1) * $filter->perPage;

            $stmt = $this->pdo->prepare(
                "SELECT
                    i.id AS inscricao_id,
                    f.id AS familia_id,
                    f.codigo AS familia_codigo,
                    p.id AS pessoa_id,
                    p.nome AS responsavel_nome,
                    p.cpf,
                    p.nis,
                    f.zona,
                    f.bairro,
                    f.comunidade,
                    i.polo_id,
                    polo.nome AS polo_nome,
                    i.status AS inscricao_status,
                    i.prioridade,
                    i.data_inscricao,
                    i.atualizado_em,
                    entrega.id AS entrega_id,
                    entrega.status AS entrega_status,
                    entrega.entregue_em AS entrega_data
                 FROM comida_mesa_inscricoes i
                 INNER JOIN familias f ON f.id = i.familia_id
                 INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
                 LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
                 {$deliveryJoin}
                 WHERE {$where}
                 ORDER BY
                    CASE i.prioridade
                        WHEN 'alta' THEN 1
                        WHEN 'normal' THEN 2
                        WHEN 'baixa' THEN 3
                        ELSE 4
                    END,
                    p.nome,
                    i.id
                 LIMIT :limit OFFSET :offset"
            );
            $this->bindFilterParams($stmt, $params);
            $stmt->bindValue(':limit', $filter->perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return new PaginatedResult($stmt->fetchAll(), $total, $page, $filter->perPage);
        } catch (PDOException $exception) {
            throw $this->fail('paginate', 'Falha ao consultar inscrições.', $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findByCpf(string $cpf, ?int $competenceId): ?array
    {
        $deliveryJoin = $this->deliveryJoin($competenceId, 'entrega_competencia_id');
        $params = ['cpf' => Validator::onlyDigits($cpf)];

        if ($competenceId !== null) {
            $params['entrega_competencia_id'] = $competenceId;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    p.id AS pessoa_id,
                    p.nome AS responsavel_nome,
                    p.cpf,
                    p.nis,
                    f.id AS familia_id,
                    f.codigo AS familia_codigo,
                    i.id AS inscricao_id,
                    i.status AS inscricao_status,
                    polo.nome AS polo_nome,
                    entrega.id AS entrega_id,
                    entrega.status AS entrega_status,
                    entrega.entregue_em AS entrega_data
                 FROM pessoas p
                 LEFT JOIN familias f ON f.responsavel_pessoa_id = p.id
                 LEFT JOIN comida_mesa_inscricoes i ON i.familia_id = f.id
                 LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
                 {$deliveryJoin}
                 WHERE p.cpf = :cpf
                 LIMIT 1"
            );
            $stmt->execute($params);
            $row = $stmt->fetch();

            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            throw $this->fail('findByCpf', 'Falha ao consultar CPF.', $exception);
        }
    }

    /** @param array<string,mixed> $params */
    private function count(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function deliveryJoin(?int $competenceId, string $placeholder): string
    {
        if ($competenceId === null) {
            return 'LEFT JOIN comida_mesa_entregas entrega ON 1 = 0';
        }

        return 'LEFT JOIN comida_mesa_entregas entrega
                ON entrega.inscricao_id = i.id
               AND entrega.competencia_id = :' . $placeholder;
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function filterWhere(ComidaMesaFilter $filter): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($filter->search !== null) {
            $where[] = '(p.nome LIKE :search OR p.cpf LIKE :search_digits OR p.nis LIKE :search OR f.codigo LIKE :search)';
            $params['search'] = '%' . $filter->search . '%';
            $params['search_digits'] = '%' . Validator::onlyDigits($filter->search) . '%';
        }

        if ($filter->programStatus !== null) {
            $where[] = 'i.status = :program_status';
            $params['program_status'] = $filter->programStatus;
        }

        if ($filter->zone !== null) {
            $where[] = 'f.zona = :zone';
            $params['zone'] = $filter->zone;
        }

        if ($filter->district !== null) {
            $where[] = 'f.bairro = :district';
            $params['district'] = $filter->district;
        }

        if ($filter->community !== null) {
            $where[] = 'f.comunidade = :community';
            $params['community'] = $filter->community;
        }

        if ($filter->poleId !== null) {
            $where[] = 'i.polo_id = :pole_id';
            $params['pole_id'] = $filter->poleId;
        }

        if ($filter->deliveryStatus !== null && $filter->competenceId !== null) {
            if ($filter->deliveryStatus === 'recebida') {
                $where[] = "entrega.id IS NOT NULL AND entrega.status = 'entregue'";
            } elseif ($filter->deliveryStatus === 'aguardando') {
                $where[] = "i.status = 'ativa' AND NOT EXISTS (
                    SELECT 1
                    FROM comida_mesa_entregas e2
                    WHERE e2.inscricao_id = i.id
                      AND e2.competencia_id = :aguardando_competencia_id
                      AND e2.status = 'entregue'
                )";
                $params['aguardando_competencia_id'] = $filter->competenceId;
            } elseif ($filter->deliveryStatus === 'bloqueada') {
                $where[] = "i.status IN ('suspensa', 'bloqueada')";
            } elseif ($filter->deliveryStatus === 'indisponivel') {
                $where[] = "i.status NOT IN ('ativa', 'suspensa', 'bloqueada')";
            }
        }

        return [implode(' AND ', $where), $params];
    }

    /** @param array<string,mixed> $params */
    private function bindFilterParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $type);
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
}
