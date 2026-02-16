<?php
// app/models/Model.php

class Model {
    protected $db;

    public function __construct() {
        // Usar a conexão global $pdo definida em config/database.php
        // Ou recriar conexao se preferir encapsulamento total
        global $pdo;
        $this->db = $pdo;
    }
}
