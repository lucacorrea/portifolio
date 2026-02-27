<?php
namespace App\Models;

use App\Config\Database;
use PDO;

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $tenantCol = 'UNSET';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    protected function getFilialContext() {
        if (!isset($_SESSION['filial_id'])) return null;
        if (($_SESSION['usuario_nivel'] ?? '') === 'master') return null;
        if (($_SESSION['is_matriz'] ?? false)) return null;
        return $_SESSION['filial_id'];
    }

    protected function columnExists($column) {
        $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE '$column'");
        return (bool)$stmt->fetch();
    }

    protected function getTenantColumn() {
        if ($this->tenantCol !== 'UNSET') return $this->tenantCol;

        if ($this->table === 'filiais') {
            $this->tenantCol = 'id';
            return 'id';
        }
        
        // Defensive check: only filter if column exists
        $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE 'filial_id'");
        $exists = $stmt->fetch();
        $this->tenantCol = $exists ? 'filial_id' : null;
        return $this->tenantCol;
    }

    public function find($id) {
        $filialId = $this->getFilialContext();
        $col = $this->getTenantColumn();
        
        if ($filialId && $col) {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND $col = ?");
            $stmt->execute([$id, $filialId]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
        }
        return $stmt->fetch();
    }

    public function all($order = "id DESC") {
        $filialId = $this->getFilialContext();
        $col = $this->getTenantColumn();
        
        if ($filialId && $col) {
            return $this->db->query("SELECT * FROM {$this->table} WHERE $col = $filialId ORDER BY {$order}")->fetchAll();
        }
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY {$order}")->fetchAll();
    }

    public function paginate($perPage = 15, $currentPage = 1, $order = "id DESC") {
        $filialId = $this->getFilialContext();
        $col = $this->getTenantColumn();
        $offset = ($currentPage - 1) * $perPage;
        
        $where = ($filialId && $col) ? "WHERE $col = $filialId" : "";
        
        $total = $this->db->query("SELECT COUNT(*) FROM {$this->table} $where")->fetchColumn();
        $pages = ceil($total / $perPage);
        
        $sql = "SELECT * FROM {$this->table} $where ORDER BY {$order} LIMIT $perPage OFFSET $offset";
        $data = $this->db->query($sql)->fetchAll();
        
        return [
            'data' => $data,
            'total' => $total,
            'pages' => $pages,
            'current' => $currentPage,
            'per_page' => $perPage
        ];
    }

    public function delete($id) {
        $filialId = $this->getFilialContext();
        $col = $this->getTenantColumn();
        
        if ($filialId && $col) {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ? AND $col = ?");
            return $stmt->execute([$id, $filialId]);
        }
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function create($data) {
        $filialId = $this->getFilialContext() ?: ($_SESSION['filial_id'] ?? null);
        $col = $this->getTenantColumn();

        if ($filialId && $col === 'filial_id' && !isset($data['filial_id'])) {
            $data['filial_id'] = $filialId;
        }

        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $filialId = $this->getFilialContext();
        $col = $this->getTenantColumn();
        $fields = array_map(fn($field) => "$field = ?", array_keys($data));
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $params = array_values($data);
        $params[] = $id;

        if ($filialId && $col) {
            $sql .= " AND $col = ?";
            $params[] = $filialId;
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    protected function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
