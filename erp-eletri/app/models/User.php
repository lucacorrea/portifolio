<?php
// app/models/User.php

class User extends Model {
    
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE email = :email AND ativo = 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['senha'])) {
            return $user;
        }
        
        return false;
    }

    public function getAllUsers() {
        $stmt = $this->db->query("SELECT u.*, f.nome as filial_nome FROM usuarios u LEFT JOIN filiais f ON u.filial_id = f.id ORDER BY u.nome");
        return $stmt->fetchAll();
    }
}
