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
        $filialId = $this->getFilialContext();
        $where = $filialId ? "WHERE u.filial_id = $filialId" : "";
        return $this->query("
            SELECT u.*, f.nome as filial_nome 
            FROM {$this->table} u
            LEFT JOIN filiais f ON u.filial_id = f.id
            $where
            ORDER BY $orderBy
        ")->fetchAll();
    }
    
    public function save($data) {
        $hasDiscountCol = $this->columnExists('desconto_maximo');
        
        if (!empty($data['id'])) {
            $sql = "UPDATE {$this->table} SET nome = ?, email = ?, nivel = ?, ativo = ?, filial_id = ? ";
            $params = [$data['nome'], $data['email'], $data['nivel'], $data['ativo'], $data['filial_id']];
            
            if ($hasDiscountCol) {
                $sql .= ", desconto_maximo = ? ";
                $params[] = $data['desconto_maximo'] ?? 0;
            }

            if (!empty($data['senha'])) {
                $sql .= ", senha = ? ";
                $params[] = password_hash($data['senha'], PASSWORD_DEFAULT);
            }
            $sql .= "WHERE id = ?";
            $params[] = $data['id'];
            return $this->query($sql, $params);
        } else {
            $senha = password_hash($data['senha'], PASSWORD_DEFAULT);
            $fields = "nome, email, senha, nivel, ativo, filial_id";
            $values = "?, ?, ?, ?, ?, ?";
            $params = [$data['nome'], $data['email'], $senha, $data['nivel'], $data['ativo'], $data['filial_id']];
            
            if ($hasDiscountCol) {
                $fields .= ", desconto_maximo";
                $values .= ", ?";
                $params[] = $data['desconto_maximo'] ?? 0;
            }

            return $this->query("INSERT INTO {$this->table} ($fields) VALUES ($values)", $params);
        }
    }
}
