<?php
namespace App\Repositories;

class UserRepository extends BaseRepository {
    protected $table = 'usuarios';

    public function findByIdentifier(string $identifier) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE (email = ? OR id = ? OR LOWER(nome) = LOWER(?)) AND ativo = 1");
        $stmt->execute([$identifier, $identifier, $identifier]);
        return $stmt->fetch();
    }

    public function updateLastLogin(int $id) {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET last_login = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
