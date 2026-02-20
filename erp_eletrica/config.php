<?php
session_start();

// DEPOIS (para funcionar na hospedagem)
define('DB_HOST', 'localhost'); // ou 'localhost' (teste primeiro)
define('DB_USER', 'u784961086_pdv');        // usuário que você criar no painel
define('DB_PASS', 'X9#ZC#n^');          // senha que você definir
define('DB_NAME', 'u784961086_pdv');           // nome que você der ao banco

// Conexão com o banco de dados
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Funções úteis
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

// Criar tabelas se não existirem
$sql = "
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cpf_cnpj VARCHAR(20),
    telefone VARCHAR(20),
    email VARCHAR(100),
    endereco TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    categoria VARCHAR(50),
    preco_custo DECIMAL(10,2),
    preco_venda DECIMAL(10,2),
    quantidade INT DEFAULT 0,
    estoque_minimo INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS os (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_os VARCHAR(20) UNIQUE,
    cliente_id INT,
    data_abertura DATE,
    data_conclusao DATE,
    status ENUM('aberta', 'em_andamento', 'concluida', 'cancelada') DEFAULT 'aberta',
    descricao TEXT,
    valor_total DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE TABLE IF NOT EXISTS itens_os (
    id INT AUTO_INCREMENT PRIMARY KEY,
    os_id INT,
    produto_id INT,
    quantidade INT,
    valor_unitario DECIMAL(10,2),
    subtotal DECIMAL(10,2),
    FOREIGN KEY (os_id) REFERENCES os(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

CREATE TABLE IF NOT EXISTS contas_receber (
    id INT AUTO_INCREMENT PRIMARY KEY,
    os_id INT,
    descricao VARCHAR(255),
    valor DECIMAL(10,2),
    data_vencimento DATE,
    data_pagamento DATE,
    status ENUM('pendente', 'pago', 'atrasado') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (os_id) REFERENCES os(id)
);
";

try {
    $pdo->exec($sql);
} catch(PDOException $e) {
    // Tabelas já existem
}
?>