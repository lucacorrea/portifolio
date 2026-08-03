<?php

declare(strict_types=1);

namespace App\Integration\SO\Repository;

use PDO;

final class SoAcquisitionReadRepository
{
    public function __construct(private readonly PDO $connection) {}

    /** @return array<string,mixed>|null */
    public function findSupplier(int $supplierSoId): ?array
    {
        $statement = $this->connection->prepare('SELECT id, nome, cnpj FROM fornecedores WHERE id = :fornecedor_id LIMIT 1');
        $statement->execute(['fornecedor_id' => $supplierSoId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,summary:array<string,mixed>} */
    public function paginate(int $supplierSoId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        [$where, $params] = $this->filters($supplierSoId, $filters);
        $from = ' FROM aquisicoes a JOIN fornecedores f ON f.id = a.fornecedor_id LEFT JOIN oficios o ON o.id = a.oficio_id LEFT JOIN secretarias s ON s.id = o.secretaria_id LEFT JOIN integracao_fluxempresa_aquisicoes ifa ON ifa.aquisicao_id = a.id ';
        $count = $this->connection->prepare('SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where));
        $count->execute($params);
        $summary = $this->connection->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(a.valor_total), 0) AS valor_total, COALESCE(SUM(a.status <> 'finalizada'), 0) AS aguardando_entrega, COALESCE(SUM(a.status = 'finalizada'), 0) AS finalizadas, MAX(COALESCE(a.data_finalizacao, a.criado_em)) AS ultima_aquisicao" . $from . ' WHERE ' . implode(' AND ', $where));
        $summary->execute($params);
        $sql = 'SELECT a.id, a.numero_aq, a.codigo_entrega, a.oficio_id, a.fornecedor_id, a.valor_total, a.responsavel_entrega, a.status, a.data_finalizacao, a.criado_em, f.nome AS fornecedor_nome, f.cnpj AS fornecedor_cnpj, o.numero AS oficio_numero, o.local AS oficio_local, o.justificativa AS oficio_justificativa, s.nome AS secretaria_nome, COUNT(DISTINCT ia.id) AS itens_total, CASE WHEN ifa.aquisicao_id IS NULL THEN \'SO\' ELSE \'Flux Empresas\' END AS origem' . $from . ' LEFT JOIN itens_aquisicao ia ON ia.aquisicao_id = a.id WHERE ' . implode(' AND ', $where) . ' GROUP BY a.id ORDER BY COALESCE(a.data_finalizacao, a.criado_em) DESC, a.id DESC LIMIT :limit OFFSET :offset';
        $statement = $this->connection->prepare($sql);
        foreach ($params as $key => $value) $statement->bindValue(':' . $key, $value);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        return ['items' => $statement->fetchAll(), 'total' => (int) $count->fetchColumn(), 'summary' => $summary->fetch() ?: []];
    }

    /** @return array<string,mixed>|null */
    public function findById(int $supplierSoId, int $acquisitionSoId): ?array
    {
        $statement = $this->connection->prepare('SELECT a.*, f.nome AS fornecedor_nome, f.cnpj AS fornecedor_cnpj, o.numero AS oficio_numero, o.local AS oficio_local, o.justificativa AS oficio_justificativa, s.nome AS secretaria_nome FROM aquisicoes a JOIN fornecedores f ON f.id = a.fornecedor_id LEFT JOIN oficios o ON o.id = a.oficio_id LEFT JOIN secretarias s ON s.id = o.secretaria_id WHERE a.fornecedor_id = :fornecedor_id AND a.id = :aquisicao_id LIMIT 1');
        $statement->execute(['fornecedor_id' => $supplierSoId, 'aquisicao_id' => $acquisitionSoId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function findItems(int $supplierSoId, int $acquisitionSoId): array
    {
        $statement = $this->connection->prepare('SELECT ia.id, ia.produto, ia.quantidade, ia.valor_unitario FROM itens_aquisicao ia JOIN aquisicoes a ON a.id = ia.aquisicao_id WHERE a.fornecedor_id = :fornecedor_id AND a.id = :aquisicao_id ORDER BY ia.id');
        $statement->execute(['fornecedor_id' => $supplierSoId, 'aquisicao_id' => $acquisitionSoId]);
        return $statement->fetchAll();
    }

    /** @return array{0:array<int,string>,1:array<string,mixed>} */
    private function filters(int $supplierSoId, array $filters): array
    {
        $where = ['a.fornecedor_id = :fornecedor_id']; $params = ['fornecedor_id' => $supplierSoId];
        $term = trim((string) ($filters['search'] ?? ''));
        if ($term !== '') { $where[] = '(a.numero_aq LIKE :term OR a.codigo_entrega LIKE :term OR o.numero LIKE :term OR s.nome LIKE :term OR EXISTS (SELECT 1 FROM itens_aquisicao ia2 WHERE ia2.aquisicao_id = a.id AND ia2.produto LIKE :term))'; $params['term'] = '%' . $term . '%'; }
        foreach (['status' => 'a.status', 'origin' => 'ifa.origem_flux'] as $key => $column) { $value = trim((string) ($filters[$key] ?? '')); if ($value !== '') { if ($key === 'origin') $where[] = $value === 'flux' ? 'ifa.aquisicao_id IS NOT NULL' : 'ifa.aquisicao_id IS NULL'; else { $where[] = $column . ' = :' . $key; $params[$key] = $value; } } }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) { $value = (string) ($filters[$key] ?? ''); if ($value !== '') { $where[] = 'DATE(COALESCE(a.data_finalizacao, a.criado_em)) ' . $operator . ' :' . $key; $params[$key] = $value; } }
        return [$where, $params];
    }
}
