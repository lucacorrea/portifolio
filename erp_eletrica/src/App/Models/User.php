<?php
namespace App\Models;

class User extends BaseModel {
    protected $table = 'usuarios';

    public function findByEmail($email) {
        $stmt = $this->query("SELECT * FROM {$this->table} WHERE email = ? AND ativo = 1", [$email]);
        return $stmt->fetch();
    }

    public function updateLastLogin($id) {
        return $this->query("UPDATE {$this->table} SET last_login = NOW() WHERE id = ?", [$id]);
    }

    public function createDefaultAdmin() {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        return $this->query(
            "INSERT INTO {$this->table} (nome, email, senha, nivel) VALUES (?, ?, ?, ?)",
            ['Administrador', 'admin@erp.com', $hash, 'admin']
        );
    }
    
    public function all($orderBy = "nome") {
        return $this->query("SELECT * FROM {$this->table} ORDER BY $orderBy")->fetchAll();
    }

    public function save($data) {
        if (!empty($data['id'])) {
            $sql = "UPDATE {$this->table} SET nome = ?, email = ?, nivel = ?, ativo = ? ";
            $params = [$data['nome'], $data['email'], $data['nivel'], $data['ativo']];
            if (!empty($data['senha'])) {
                $sql .= ", senha = ? ";
                $params[] = password_hash($data['senha'], PASSWORD_DEFAULT);
            }
            $sql .= "WHERE id = ?";
            $params[] = $data['id'];
            return $this->query($sql, $params);
        } else {
            $senha = password_hash($data['senha'], PASSWORD_DEFAULT);
            return $this->query(
                "INSERT INTO {$this->table} (nome, email, senha, nivel, ativo) VALUES (?, ?, ?, ?, ?)",
                [$data['nome'], $data['email'], $senha, $data['nivel'], $data['ativo']]
            );
        }
    }
}
