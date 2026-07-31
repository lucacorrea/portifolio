<?php
declare(strict_types=1);
namespace App\Integration\SO\Repository;
use App\Integration\SO\DTO\SoSupplierData;
use PDO;
final class SoSupplierRepository
{
    public function __construct(private readonly PDO $connection) {}
    public function paginate(string $search, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage); $term = '%' . $search . '%';
        $sql = 'SELECT id, nome, cnpj, contato, telefone FROM fornecedores WHERE nome LIKE :search OR cnpj LIKE :search OR contato LIKE :search OR telefone LIKE :search ORDER BY nome ASC, id ASC LIMIT :limit OFFSET :offset';
        $statement = $this->connection->prepare($sql); $statement->bindValue(':search', $term); $statement->bindValue(':limit', $perPage, PDO::PARAM_INT); $statement->bindValue(':offset', $offset, PDO::PARAM_INT); $statement->execute();
        return array_map(fn(array $row): SoSupplierData => $this->map($row), $statement->fetchAll());
    }
    public function count(string $search): int { $s = $this->connection->prepare('SELECT COUNT(*) FROM fornecedores WHERE nome LIKE :search OR cnpj LIKE :search OR contato LIKE :search OR telefone LIKE :search'); $s->execute(['search' => '%' . $search . '%']); return (int) $s->fetchColumn(); }
    public function findById(int $id): ?SoSupplierData { $s = $this->connection->prepare('SELECT id, nome, cnpj, contato, telefone FROM fornecedores WHERE id = :id'); $s->execute(['id' => $id]); $row = $s->fetch(); return is_array($row) ? $this->map($row) : null; }
    private function map(array $row): SoSupplierData { return new SoSupplierData((int) $row['id'], (string) $row['nome'], (string) ($row['cnpj'] ?? ''), (string) ($row['contato'] ?? ''), (string) ($row['telefone'] ?? '')); }
}
