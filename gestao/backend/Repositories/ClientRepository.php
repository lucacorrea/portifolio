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

    public function findAll(int $empresaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                c.id,
                c.nome AS name,
                c.telefone AS phone,
                c.cpf_cnpj AS cpf,
                c.endereco AS address,
                COALESCE(SUM(cc.saldo_aberto), 0) AS debt,
                COALESCE(SUM(cc.valor_pago), 0) AS paid,
                MIN(CASE WHEN cc.saldo_aberto > 0 THEN cc.vencimento END) AS due,
                MAX(CASE WHEN cc.status = "atrasado" OR (cc.saldo_aberto > 0 AND cc.vencimento < CURDATE()) THEN 1 ELSE 0 END) AS has_overdue
             FROM clientes c
             LEFT JOIN cliente_contas cc ON cc.cliente_id = c.id
                AND cc.empresa_id = c.empresa_id
                AND cc.status <> "cancelado"
             WHERE c.empresa_id = :empresa_id
               AND c.ativo = 1
             GROUP BY c.id, c.nome, c.telefone, c.cpf_cnpj, c.endereco
             ORDER BY c.nome ASC'
        );
        $stmt->execute([':empresa_id' => $empresaId]);

        return array_map(static function (array $client): array {
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
                'debt' => $debt,
                'paid' => (float)$client['paid'],
                'due' => $client['due'] ?? '',
                'status' => $status,
                'history' => [],
            ];
        }, $stmt->fetchAll());
    }
}
