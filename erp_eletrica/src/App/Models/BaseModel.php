<?php
namespace App\Models;

use App\Config\Database;
use PDO;

abstract class BaseModel {
    protected $db;
    protected $table;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    protected function getFilialContext() {
        if (!isset($_SESSION['filial_id'])) return null;
        if (($_SESSION['usuario_nivel'] ?? '') === 'master') return null; // Master can see everything
        if (($_SESSION['is_matriz'] ?? false)) return null; // Matriz can see everything by default
        return $_SESSION['filial_id'];
    }

    public function find($id) {
        $filialId = $this->getFilialContext();
        if ($filialId) {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND filial_id = ?");
            $stmt->execute([$id, $filialId]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
        }
        return $stmt->fetch();
    }

    public function all($order = "id DESC") {
        $filialId = $this->getFilialContext();
        if ($filialId) {
            return $this->db->query("SELECT * FROM {$this->table} WHERE filial_id = $filialId ORDER BY {$order}")->fetchAll();
        }
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY {$order}")->fetchAll();
    }

    public function delete($id) {
        $filialId = $this->getFilialContext();
        if ($filialId) {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ? AND filial_id = ?");
            return $stmt->execute([$id, $filialId]);
        }
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function create($data) {
        $filialId = $this->getFilialContext() ?: ($_SESSION['filial_id'] ?? null);
        if ($filialId && !isset($data['filial_id'])) {
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
        $fields = array_map(fn($field) => "$field = ?", array_keys($data));
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $params = array_values($data);
        $params[] = $id;

        if ($filialId) {
            $sql .= " AND filial_id = ?";
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
