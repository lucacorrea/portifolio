<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ClientRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function connection(): PDO
    {
        return $this->db;
    }

    public function findAll(int $empresaId, string $query = ''): array
    {
        $sql = 'SELECT
                c.id,
                c.nome AS name,
                c.telefone AS phone,
                c.cpf_cnpj AS cpf,
                c.endereco AS address,
                c.observacao AS observation,
                COALESCE(SUM(cc.saldo_aberto), 0) AS debt,
                COALESCE(SUM(cc.valor_pago), 0) AS paid,
                MIN(CASE WHEN cc.saldo_aberto > 0 THEN cc.vencimento END) AS due,
                MAX(CASE WHEN cc.status = \'atrasado\' OR (cc.saldo_aberto > 0 AND cc.vencimento < CURDATE()) THEN 1 ELSE 0 END) AS has_overdue
             FROM clientes c
             LEFT JOIN cliente_contas cc ON cc.cliente_id = c.id
                AND cc.empresa_id = c.empresa_id
                AND cc.status <> \'cancelado\'
             WHERE c.empresa_id = :empresa_id
               AND c.ativo = 1';

        $params = [
            ':empresa_id' => $empresaId,
        ];

        $query = trim($query);
        if ($query !== '') {
            $sql .= '
               AND (
                   c.nome LIKE :like_nome
                   OR c.telefone LIKE :like_telefone
                   OR c.cpf_cnpj LIKE :like_documento
               )';

            $like = '%' . $query . '%';
            $params[':like_nome'] = $like;
            $params[':like_telefone'] = $like;
            $params[':like_documento'] = $like;
        }

        $sql .= '
             GROUP BY c.id, c.nome, c.telefone, c.cpf_cnpj, c.endereco, c.observacao
             ORDER BY c.nome ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'mapClient'], $stmt->fetchAll());
    }

    public function findById(int $empresaId, int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                c.id,
                c.nome AS name,
                c.telefone AS phone,
                c.cpf_cnpj AS cpf,
                c.endereco AS address,
                c.observacao AS observation,
                COALESCE(SUM(cc.saldo_aberto), 0) AS debt,
                COALESCE(SUM(cc.valor_pago), 0) AS paid,
                MIN(CASE WHEN cc.saldo_aberto > 0 THEN cc.vencimento END) AS due,
                MAX(CASE WHEN cc.status = \'atrasado\' OR (cc.saldo_aberto > 0 AND cc.vencimento < CURDATE()) THEN 1 ELSE 0 END) AS has_overdue
             FROM clientes c
             LEFT JOIN cliente_contas cc ON cc.cliente_id = c.id
                AND cc.empresa_id = c.empresa_id
                AND cc.status <> \'cancelado\'
             WHERE c.empresa_id = :empresa_id
               AND c.id = :id
               AND c.ativo = 1
             GROUP BY c.id, c.nome, c.telefone, c.cpf_cnpj, c.endereco, c.observacao
             LIMIT 1'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
        ]);

        $client = $stmt->fetch();

        return $client ? $this->mapClient($client) : null;
    }

    public function create(int $empresaId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO clientes (empresa_id, nome, telefone, cpf_cnpj, endereco, observacao)
             VALUES (:empresa_id, :nome, :telefone, :cpf_cnpj, :endereco, :observacao)'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':nome' => $data['nome'],
            ':telefone' => $data['telefone'] ?: null,
            ':cpf_cnpj' => $data['cpf_cnpj'] ?: null,
            ':endereco' => $data['endereco'] ?: null,
            ':observacao' => $data['observacao'] ?: null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $empresaId, int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE clientes
             SET nome = :nome,
                 telefone = :telefone,
                 cpf_cnpj = :cpf_cnpj,
                 endereco = :endereco,
                 observacao = :observacao
             WHERE empresa_id = :empresa_id AND id = :id'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
            ':nome' => $data['nome'],
            ':telefone' => $data['telefone'] ?: null,
            ':cpf_cnpj' => $data['cpf_cnpj'] ?: null,
            ':endereco' => $data['endereco'] ?: null,
            ':observacao' => $data['observacao'] ?: null,
        ]);
    }

    public function inactivate(int $empresaId, int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE clientes
             SET ativo = 0
             WHERE empresa_id = :empresa_id AND id = :id'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $id,
        ]);
    }

    private function mapClient(array $client): array
    {
        $debt = (float)$client['debt'];
        $status = 'Em dia';

        if ($debt > 0) {
            $status = ((int)$client['has_overdue'] === 1) ? 'Atrasado' : 'Devendo';
        }

        return [
            'id' => (int)$client['id'],
            'name' => $client['name'],
            'phone' => $client['phone'] ?? '',
            'cpf' => $client['cpf'] ?? '',
            'address' => $client['address'] ?? '',
            'observation' => $client['observation'] ?? '',
            'debt' => $debt,
            'paid' => (float)$client['paid'],
            'due' => $client['due'] ?? '',
            'status' => $status,
            'history' => [],
        ];
    }
}
