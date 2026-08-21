<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use PDO;
use PDOException;

final class ModuleAccessRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function userOverride(int $userId, string $module): ?bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT permitido FROM usuario_modulo_excecoes
                 WHERE usuario_id = :usuario_id AND modulo = :modulo
                 LIMIT 1'
            );
            $stmt->execute(['usuario_id' => $userId, 'modulo' => $module]);
            $value = $stmt->fetchColumn();
            return $value === false ? null : (bool) $value;
        } catch (PDOException $e) {
            // Compatibilidade com bases que ainda não executaram a migration.
            Logger::application('Module access override unavailable.', ['code' => $e->getCode()]);
            return null;
        }
    }

    public function sectorHasConfiguration(int $sectorId): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM setor_modulos WHERE setor_id = :setor_id LIMIT 1');
            $stmt->execute(['setor_id' => $sectorId]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            Logger::application('Sector module matrix unavailable.', ['code' => $e->getCode()]);
            return false;
        }
    }

    public function sectorAllows(int $sectorId, string $module): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT permitido FROM setor_modulos
                 WHERE setor_id = :setor_id AND modulo = :modulo
                 LIMIT 1'
            );
            $stmt->execute(['setor_id' => $sectorId, 'modulo' => $module]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            Logger::application('Sector module access unavailable.', ['code' => $e->getCode()]);
            return true;
        }
    }
}
