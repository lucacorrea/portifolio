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

    public function columnExists($column) {
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

    public function paginate($perPage = 15, $currentPage = 1, $order = "id DESC", $filters = []) {
        $filialId = $this->getFilialContext();
        $col = $this->getTenantColumn();
        $offset = ($currentPage - 1) * $perPage;
        
        $where = "WHERE 1=1";
        $params = [];
        
        if ($filialId && $col) {
            $where .= " AND $col = ?";
            $params[] = $filialId;
        }

        if (!empty($filters['q'])) {
            $where .= " AND (nome LIKE ? OR codigo LIKE ?)";
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
        }

        if (!empty($filters['categoria'])) {
            $where .= " AND categoria = ?";
            $params[] = $filters['categoria'];
        }
        
        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} $where");
        $totalStmt->execute($params);
        $total = $totalStmt->fetchColumn();
        
        $pages = ceil($total / $perPage);
        
        $sql = "SELECT * FROM {$this->table} $where ORDER BY {$order} LIMIT $perPage OFFSET $offset";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
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
        $data = $this->filterData($data);
        $filialId = $this->getFilialContext() ?: ($_SESSION['filial_id'] ?? null);
        $col = $this->getTenantColumn();

        if ($filialId && $col === 'filial_id' && !isset($data['filial_id'])) {
            $data['filial_id'] = $filialId;
        }

        // Camada 2: Injetar sync_origin e sync_id quando rodando no servidor local
        if (defined('IS_LOCAL_SERVER') && IS_LOCAL_SERVER) {
            if ($this->columnExists('sync_origin') && !isset($data['sync_origin'])) {
                $data['sync_origin'] = 'local';
            }
            if ($this->columnExists('sync_id') && !isset($data['sync_id'])) {
                $data['sync_id'] = 'L-' . $this->table . '-' . time() . '-' . bin2hex(random_bytes(4));
            }
        }

        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $data = $this->filterData($data);
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

    public function save($data) {
        if (isset($data['id']) && !empty($data['id'])) {
            $id = $data['id'];
            unset($data['id']);
            return $this->update($id, $data);
        }
        unset($data['id']);
        return $this->create($data);
    }

    protected function filterData($data) {
        $stmt = $this->db->query("DESCRIBE {$this->table}");
        $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        return array_filter($data, function($key) use ($columns) {
            return in_array($key, $columns);
        }, ARRAY_FILTER_USE_KEY);
    }

    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
