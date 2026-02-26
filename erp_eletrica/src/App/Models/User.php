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
    
    public function findAdmins() {
        $filialId = $_SESSION['filial_id'] ?? null;
        
        // Try exact match for Branch
        $sql = "SELECT id, nome, auth_type, auth_pin FROM {$this->table} WHERE nivel = 'admin' AND ativo = 1";
        $params = [];
        if ($filialId) {
            $sqlBranch = $sql . " AND filial_id = ?";
            $res = $this->query($sqlBranch, [$filialId])->fetchAll();
            if (!empty($res)) return $res;
        }

        // Fallback: any active admin (if branch admin not found)
        return $this->query($sql)->fetchAll();
    }

    public function validateAuth($userId, $credential) {
        $user = $this->query("SELECT senha, auth_pin, auth_type FROM {$this->table} WHERE id = ?", [$userId])->fetch();
        if (!$user) return false;

        if ($user['auth_type'] === 'pin') {
            return $credential === $user['auth_pin'];
        } else {
            return password_verify($credential, $user['senha']);
        }
    }

    public function save($data) {
        $hasAuthCols = $this->columnExists('auth_pin');
        
        if (!empty($data['id'])) {
            $sql = "UPDATE {$this->table} SET nome = ?, email = ?, nivel = ?, ativo = ?, filial_id = ? ";
            $params = [$data['nome'], $data['email'], $data['nivel'], $data['ativo'], $data['filial_id']];
            
            if ($this->columnExists('desconto_maximo')) {
                $sql .= ", desconto_maximo = ? ";
                $params[] = $data['desconto_maximo'] ?? 0;
            }

            if ($hasAuthCols) {
                $sql .= ", auth_pin = ?, auth_type = ? ";
                $params[] = !empty($data['auth_pin']) ? $data['auth_pin'] : null;
                $params[] = $data['auth_type'] ?? 'password';
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
            $fields = ["nome", "email", "senha", "nivel", "ativo", "filial_id"];
            $placeholders = ["?", "?", "?", "?", "?", "?"];
            $params = [$data['nome'], $data['email'], $senha, $data['nivel'], (int)$data['ativo'], $data['filial_id']];
            
            if ($this->columnExists('desconto_maximo')) {
                $fields[] = "desconto_maximo";
                $placeholders[] = "?";
                $params[] = $data['desconto_maximo'] ?? 0;
            }

            if ($hasAuthCols) {
                $fields[] = "auth_pin";
                $fields[] = "auth_type";
                $placeholders[] = "?";
                $placeholders[] = "?";
                $params[] = !empty($data['auth_pin']) ? $data['auth_pin'] : null;
                $params[] = $data['auth_type'] ?? 'password';
            }

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            return $this->query($sql, $params);
        }
    public function paginate($perPage = 15, $currentPage = 1, $order = "u.id DESC") {
        $filialId = $this->getFilialContext();
        $offset = ($currentPage - 1) * $perPage;
        
        $where = $filialId ? "WHERE u.filial_id = $filialId" : "";
        
        $total = $this->query("SELECT COUNT(*) FROM {$this->table} u $where")->fetchColumn();
        $pages = ceil($total / $perPage);
        
        $sql = "SELECT u.*, f.nome as filial_nome 
                FROM {$this->table} u 
                LEFT JOIN filiais f ON u.filial_id = f.id 
                $where 
                ORDER BY {$order} 
                LIMIT $perPage OFFSET $offset";
        
        $data = $this->query($sql)->fetchAll();
        
        return [
            'data' => $data,
            'total' => $total,
            'pages' => $pages,
            'current' => $currentPage,
            'per_page' => $perPage
        ];
    }
}
