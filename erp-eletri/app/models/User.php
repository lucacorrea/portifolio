<?php

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected $table = 'usuarios';

    public function login($email, $password)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = :email AND ativo = 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        // Check password - in the old code it was password_verify, assuming hashes are compatible
        if ($user && password_verify($password, $user['senha'])) {
            return $user;
        }
        
        return false;
    }

    public function getAllUsers()
    {
        // Using specific query
        $stmt = $this->db->query("SELECT u.*, f.nome as filial_nome FROM {$this->table} u LEFT JOIN filiais f ON u.filial_id = f.id ORDER BY u.nome");
        return $stmt->fetchAll();
    }
}
