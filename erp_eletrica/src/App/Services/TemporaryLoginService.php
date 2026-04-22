<?php
namespace App\Services;

class TemporaryLoginService extends BaseService {
    public function __construct() {
        $db = \App\Config\Database::getInstance()->getConnection();
        parent::__construct(new class($db) {
            public $db;
            public function __construct($db) { $this->db = $db; }
            public function create($data) {
                $fields = array_keys($data);
                $placeholders = array_fill(0, count($fields), '?');
                $sql = "INSERT INTO logins_temporarios (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array_values($data));
                return $this->db->lastInsertId();
            }
            public function findByUsername($username) {
                $stmt = $this->db->prepare("SELECT * FROM logins_temporarios WHERE usuario_aleatorio = ? AND status = 'ativo' AND validade > NOW() LIMIT 1");
                $stmt->execute([$username]);
                return $stmt->fetch();
            }
            public function invalidate($id) {
                return $this->db->prepare("UPDATE logins_temporarios SET status = 'invalidado' WHERE id = ?")->execute([$id]);
            }
            public function markAsUsed($id) {
                return $this->db->prepare("UPDATE logins_temporarios SET status = 'utilizado' WHERE id = ?")->execute([$id]);
            }
        });
    }

    public function generateLogin($adminId, $filialId, $durationMinutes = 60) {
        $username = "ADM_" . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT); // Shorter numeric prefix like ADM_123
        $password = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT); // 6-digit numeric password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $validade = date('Y-m-d H:i:s', strtotime("+$durationMinutes minutes"));

        $this->repository->create([
            'usuario_aleatorio' => $username,
            'senha_hash' => $hash,
            'admin_criador_id' => $adminId,
            'filial_id' => $filialId,
            'validade' => $validade,
            'status' => 'ativo'
        ]);

        return [
            'username' => $username,
            'password' => $password,
            'validade' => $validade
        ];
    }

    public function validate($username, $password) {
        $temp = $this->repository->findByUsername($username);
        if ($temp && password_verify($password, $temp['senha_hash'])) {
            return $temp;
        }
        return false;
    }
}
