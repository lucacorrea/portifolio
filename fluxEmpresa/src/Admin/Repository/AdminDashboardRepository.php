<?php
declare(strict_types=1);
namespace App\Admin\Repository;
final class AdminDashboardRepository { public function __construct(private readonly \PDO $connection) {} public function segments(): array { try { return $this->connection->query("SELECT segmento, COUNT(*) total FROM empresas WHERE segmento IS NOT NULL AND segmento <> '' GROUP BY segmento ORDER BY total DESC LIMIT 7")->fetchAll(); } catch (\Throwable) { return []; } } public function months(): array { try { return $this->connection->query("SELECT DATE_FORMAT(criado_em, '%Y-%m') month, COUNT(*) total FROM empresas WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY month ORDER BY month")->fetchAll(); } catch (\Throwable) { return []; } } }
