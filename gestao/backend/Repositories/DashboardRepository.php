<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class DashboardRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getTodaySummary(int $empresaId): array
    {
        $stmt = $this->db->prepare('
            SELECT 
                COUNT(*) as sales_count,
                COALESCE(SUM(total), 0) as total_sales
            FROM vendas
            WHERE empresa_id = :empresa_id 
              AND status = "finalizada"
              AND DATE(criado_em) = CURDATE()
        ');
        $stmt->execute(['empresa_id' => $empresaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['sales_count' => 0, 'total_sales' => 0.00];
    }
}
