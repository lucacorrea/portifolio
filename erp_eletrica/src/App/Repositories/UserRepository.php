<?php
namespace App\Repositories;

class UserRepository extends BaseRepository {
    protected $table = 'usuarios';

    public function findByEmail(string $email) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function updateLastLogin(int $id) {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET last_login = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
