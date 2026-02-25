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
    
    public function all($orderBy = "u.nome") {
        return $this->query("
            SELECT u.*, f.nome as filial_nome 
            FROM {$this->table} u
            LEFT JOIN filiais f ON u.filial_id = f.id
            ORDER BY $orderBy
        ")->fetchAll();
    }
    
    public function save($data) {
        if (!empty($data['id'])) {
            $sql = "UPDATE {$this->table} SET nome = ?, email = ?, nivel = ?, ativo = ?, filial_id = ?, desconto_maximo = ? ";
            $params = [$data['nome'], $data['email'], $data['nivel'], $data['ativo'], $data['filial_id'], $data['desconto_maximo'] ?? 0];
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
                "INSERT INTO {$this->table} (nome, email, senha, nivel, ativo, filial_id, desconto_maximo) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$data['nome'], $data['email'], $senha, $data['nivel'], $data['ativo'], $data['filial_id'], $data['desconto_maximo'] ?? 0]
            );
        }
    }
}
