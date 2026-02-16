<?php
// install/setup.php

require_once __DIR__ . '/../config/database.php';

try {
    echo "<h1>Instalação do ERP Eletrica</h1>";
    echo "<pre>";

    // 1. Limpar tabelas existentes (ordem inversa de dependência)
    echo "Limpando banco de dados...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = ['venda_itens', 'vendas', 'pre_venda_itens', 'pre_vendas', 'movimentacoes_estoque', 'estoque', 'produtos', 'categorias', 'clientes', 'usuarios', 'filiais', 'fornecedores', 'fluxo_caixa'];
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS $table");
        echo "Tabela $table removida.\n";
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 2. Criar Tabelas

    // Filiais
    $sql = "CREATE TABLE filiais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        endereco VARCHAR(255),
        cidade VARCHAR(100),
        estado Char(2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela filiais criada.\n";

    // Usuários
    $sql = "CREATE TABLE usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filial_id INT,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        senha VARCHAR(255) NOT NULL,
        nivel ENUM('admin', 'gerente', 'caixa', 'vendedor', 'estoque') NOT NULL,
        ativo TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (filial_id) REFERENCES filiais(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela usuarios criada.\n";

    // Clientes
    $sql = "CREATE TABLE clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        cpf_cnpj VARCHAR(20),
        ie VARCHAR(20),
        endereco VARCHAR(255),
        cidade VARCHAR(100),
        estado CHAR(2),
        tipo ENUM('pessoa_fisica', 'pessoa_juridica') DEFAULT 'pessoa_fisica',
        limite_credito DECIMAL(10, 2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela clientes criada.\n";

    // Categorias
    $sql = "CREATE TABLE categorias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela categorias criada.\n";

    // Produtos
    $sql = "CREATE TABLE produtos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        categoria_id INT,
        nome VARCHAR(150) NOT NULL,
        codigo_interno VARCHAR(50) UNIQUE,
        codigo_barras VARCHAR(50),
        ncm VARCHAR(20),
        unidade VARCHAR(10),
        preco_custo DECIMAL(10, 2),
        preco_venda DECIMAL(10, 2), -- Preço Normal
        preco_prefeitura DECIMAL(10, 2),
        preco_avista DECIMAL(10, 2),
        imagem VARCHAR(255),
        min_estoque INT DEFAULT 10,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (categoria_id) REFERENCES categorias(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela produtos criada.\n";

    // Estoque
    $sql = "CREATE TABLE estoque (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produto_id INT,
        filial_id INT,
        quantidade INT DEFAULT 0,
        localizacao VARCHAR(50),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_estoque (produto_id, filial_id),
        FOREIGN KEY (produto_id) REFERENCES produtos(id),
        FOREIGN KEY (filial_id) REFERENCES filiais(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela estoque criada.\n";

    // Pré-Vendas (Balcão)
    $sql = "CREATE TABLE pre_vendas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filial_id INT,
        cliente_id INT,
        vendedor_id INT,
        total DECIMAL(10, 2),
        status ENUM('aberta', 'finalizada', 'cancelada') DEFAULT 'aberta',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (filial_id) REFERENCES filiais(id),
        FOREIGN KEY (cliente_id) REFERENCES clientes(id),
        FOREIGN KEY (vendedor_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela pre_vendas criada.\n";

    // Itens Pré-Venda
    $sql = "CREATE TABLE pre_venda_itens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pre_venda_id INT,
        produto_id INT,
        quantidade INT,
        preco_unitario DECIMAL(10, 2),
        subtotal DECIMAL(10, 2),
        FOREIGN KEY (pre_venda_id) REFERENCES pre_vendas(id),
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela pre_venda_itens criada.\n";

    // Vendas (Caixa)
    $sql = "CREATE TABLE vendas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filial_id INT,
        cliente_id INT,
        vendedor_id INT,
        caixa_id INT,
        pre_venda_id INT,
        total DECIMAL(10, 2),
        forma_pagamento VARCHAR(50), -- Dinheiro, Crédito, Débito, PIX, Misto
        desconto DECIMAL(10, 2) DEFAULT 0.00,
        acrescimo DECIMAL(10, 2) DEFAULT 0.00,
        observacoes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (filial_id) REFERENCES filiais(id),
        FOREIGN KEY (cliente_id) REFERENCES clientes(id),
        FOREIGN KEY (vendedor_id) REFERENCES usuarios(id),
        FOREIGN KEY (caixa_id) REFERENCES usuarios(id),
        FOREIGN KEY (pre_venda_id) REFERENCES pre_vendas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela vendas criada.\n";

    // Itens Venda
    $sql = "CREATE TABLE venda_itens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        venda_id INT,
        produto_id INT,
        quantidade INT,
        preco_unitario DECIMAL(10, 2),
        subtotal DECIMAL(10, 2),
        FOREIGN KEY (venda_id) REFERENCES vendas(id),
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela venda_itens criada.\n";

    // Movimentações de Estoque
    $sql = "CREATE TABLE movimentacoes_estoque (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produto_id INT,
        filial_id INT,
        usuario_id INT,
        tipo ENUM('entrada', 'saida', 'transferencia', 'ajuste', 'venda', 'devolucao'),
        quantidade INT,
        motivo VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (produto_id) REFERENCES produtos(id),
        FOREIGN KEY (filial_id) REFERENCES filiais(id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela movimentacoes_estoque criada.\n";
    
     // Fluxo de Caixa
    $sql = "CREATE TABLE fluxo_caixa (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filial_id INT,
        caixa_id INT,
        tipo ENUM('abertura', 'fechamento', 'sangria', 'suprimento') NOT NULL,
        valor DECIMAL(10, 2) NOT NULL,
        observacao TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (filial_id) REFERENCES filiais(id),
        FOREIGN KEY (caixa_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
    echo "Tabela fluxo_caixa criada.\n";


    // 3. Inserir Dados (Seed)

    // Filiais
    $stmt = $pdo->prepare("INSERT INTO filiais (nome, cidade, estado) VALUES (?, ?, ?)");
    $stmt->execute(['Matriz Coari', 'Coari', 'AM']);
    $id_coari = $pdo->lastInsertId();
    $stmt->execute(['Filial Codajás', 'Codajás', 'AM']);
    $id_codajas = $pdo->lastInsertId();
    echo "Filiais criadas: Coari ($id_coari), Codajás ($id_codajas).\n";

    // Usuários
    $password = password_hash('123456', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO usuarios (filial_id, nome, email, senha, nivel) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$id_coari, 'Administrador', 'admin@admin.com', $password, 'admin']);
    $stmt->execute([$id_coari, 'Gerente Coari', 'gerente@coari.com', $password, 'gerente']);
    $stmt->execute([$id_coari, 'Vendedor Coari', 'vendedor@coari.com', $password, 'vendedor']);
    $stmt->execute([$id_coari, 'Caixa Coari', 'caixa@coari.com', $password, 'caixa']);
    $stmt->execute([$id_codajas, 'Gerente Codajás', 'gerente@codajas.com', $password, 'gerente']);
    echo "Usuários criados (senha padrão: 123456).\n";

    // Categorias
    $categorias = ['Fios e Cabos', 'Iluminação', 'Disjuntores', 'Tomadas e Interruptores', 'Ferramentas', 'Eletrodutos'];
    $cat_ids = [];
    $stmt = $pdo->prepare("INSERT INTO categorias (nome) VALUES (?)");
    foreach ($categorias as $cat) {
        $stmt->execute([$cat]);
        $cat_ids[$cat] = $pdo->lastInsertId();
    }
    echo "Categorias criadas.\n";

    // Produtos
    $produtos_data = [
        ['Fio Cabo Flexível 2.5mm 100m', 'Fios e Cabos', 150.00],
        ['Fio Cabo Flexível 4.0mm 100m', 'Fios e Cabos', 280.00],
        ['Fio Cabo Flexível 6.0mm 100m', 'Fios e Cabos', 450.00],
        ['Lâmpada LED 9W', 'Iluminação', 12.00],
        ['Lâmpada LED 12W', 'Iluminação', 15.00],
        ['Refletor LED 50W', 'Iluminação', 85.00],
        ['Disjuntor Unipolar 16A', 'Disjuntores', 18.00],
        ['Disjuntor Bipolar 32A', 'Disjuntores', 45.00],
        ['Disjuntor Tripolar 63A', 'Disjuntores', 90.00],
        ['Tomada Simples 10A', 'Tomadas e Interruptores', 8.50],
        ['Interruptor Simples', 'Tomadas e Interruptores', 7.90],
        ['Alicate Universal', 'Ferramentas', 35.00],
        ['Chave de Fenda Philips', 'Ferramentas', 15.00],
        ['Multímetro Digital', 'Ferramentas', 65.00],
        ['Eletroduto Corrugado 3/4 50m', 'Eletrodutos', 60.00],
    ];

    $stmt = $pdo->prepare("INSERT INTO produtos (categoria_id, nome, codigo_interno, codigo_barras, unidade, preco_custo, preco_venda, preco_prefeitura, preco_avista) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $prod_count = 1;
    foreach ($produtos_data as $p) {
        $cat_id = $cat_ids[$p[1]];
        $nome = $p[0];
        $preco_custo = $p[2] * 0.6; // Margem 40%
        $preco_venda = $p[2];
        $preco_prefeitura = $p[2] * 1.15; // +15%
        $preco_avista = $p[2] * 0.90; // -10%
        $cod = str_pad($prod_count, 6, '0', STR_PAD_LEFT);
        $ean = '789' . str_pad($prod_count, 10, '0', STR_PAD_LEFT);
        
        $stmt->execute([$cat_id, $nome, $cod, $ean, 'UN', $preco_custo, $preco_venda, $preco_prefeitura, $preco_avista]);
        $prod_id = $pdo->lastInsertId();

        // Estoque Inicial
        $pdo->exec("INSERT INTO estoque (produto_id, filial_id, quantidade) VALUES ($prod_id, $id_coari, " . rand(50, 200) . ")");
        $pdo->exec("INSERT INTO estoque (produto_id, filial_id, quantidade) VALUES ($prod_id, $id_codajas, " . rand(20, 100) . ")");
        
        $prod_count++;
    }
    echo "Produtos e Estoque criados.\n";

    // Clientes
    $stmt = $pdo->prepare("INSERT INTO clientes (nome, cpf_cnpj, cidade, estado, tipo) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Cliente Balcão', '000.000.000-00', 'Coari', 'AM', 'pessoa_fisica']);
    $stmt->execute(['Construtora Norte', '12.345.678/0001-90', 'Coari', 'AM', 'pessoa_juridica']);
    $stmt->execute(['Prefeitura de Coari', '98.765.432/0001-10', 'Coari', 'AM', 'pessoa_juridica']);
    echo "Clientes criados.\n";

    echo "<h1>Instalação Concluída com Sucesso!</h1>";
    echo "<p>Agora você pode acessar o sistema.</p>";
    echo "<a href='../public/index.php'>Ir para o Sistema</a>";

} catch (PDOException $e) {
    echo "<h1>Erro na Instalação</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
