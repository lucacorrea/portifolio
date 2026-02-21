<?php
namespace App\Models;

class Filial extends BaseModel {
    protected $table = 'filiais';

    public function getPrincipal() {
        $stmt = $this->query("SELECT * FROM {$this->table} WHERE principal = 1 LIMIT 1");
        return $stmt->fetch();
    }

    public function getAllBranches() {
        return $this->all();
    }
}
