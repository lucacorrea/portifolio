<?php
declare(strict_types=1);

/**
 * assets/dados/db_init.php
 * Inicialização automática do banco de dados.
 */

function db_initialize(PDO $pdo): void
{
    $logFile = __DIR__ . '/db_init.log';
    $log = function($msg) use ($logFile) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
    };

    $log("Iniciando inicialização do banco...");

    $queries = [
        // Fornecedores
        "CREATE TABLE IF NOT EXISTS fornecedores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(200) NOT NULL,
            status VARCHAR(10) DEFAULT 'ATIVO',
            doc VARCHAR(30),
            tel VARCHAR(30),
            email VARCHAR(190),
            endereco VARCHAR(255),
            cidade VARCHAR(120),
            uf VARCHAR(2),
            contato VARCHAR(120),
            obs TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        "CREATE INDEX IF NOT EXISTS idx_fornecedores_nome ON fornecedores (nome)",

        // Categorias
        "CREATE TABLE IF NOT EXISTS categorias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(200) NOT NULL,
            descricao VARCHAR(320),
            cor VARCHAR(7) DEFAULT '#60a5fa',
            obs TEXT,
            status VARCHAR(10) DEFAULT 'ATIVO',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",

        // Produtos
        "CREATE TABLE IF NOT EXISTS produtos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(50) NOT NULL,
            nome VARCHAR(255) NOT NULL,
            categoria_id INT,
            fornecedor_id INT,
            unidade VARCHAR(50),
            preco DECIMAL(10,2) DEFAULT 0,
            estoque INT DEFAULT 0,
            minimo INT DEFAULT 0,
            status VARCHAR(10) DEFAULT 'ATIVO',
            obs VARCHAR(255),
            imagem VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_produtos_codigo (codigo)
        )",

        // Clientes
        "CREATE TABLE IF NOT EXISTS clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            cpf VARCHAR(20) DEFAULT NULL,
            telefone VARCHAR(20) DEFAULT NULL,
            endereco TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_clientes_cpf (cpf)
        )",

        // Vendas
        "CREATE TABLE IF NOT EXISTS vendas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data DATE NOT NULL,
            cliente VARCHAR(180) NULL,
            canal VARCHAR(20) NOT NULL DEFAULT 'PRESENCIAL',
            endereco VARCHAR(255) NULL,
            obs VARCHAR(255) NULL,
            desconto_tipo VARCHAR(10) NOT NULL DEFAULT 'PERC',
            desconto_valor DECIMAL(10,2) NOT NULL DEFAULT 0,
            taxa_entrega DECIMAL(10,2) NOT NULL DEFAULT 0,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            pagamento_mode VARCHAR(10) NOT NULL DEFAULT 'UNICO',
            pagamento VARCHAR(30) NOT NULL DEFAULT 'DINHEIRO',
            pagamento_json TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",

        // Venda Itens
        "CREATE TABLE IF NOT EXISTS venda_itens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            venda_id INT NOT NULL,
            produto_id INT NOT NULL,
            codigo VARCHAR(50) NOT NULL,
            nome VARCHAR(255) NOT NULL,
            unidade VARCHAR(50) DEFAULT NULL,
            preco_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            qtd INT NOT NULL DEFAULT 1,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",

        // Fiados
        "CREATE TABLE IF NOT EXISTS fiados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            venda_id INT NOT NULL,
            cliente_id INT NOT NULL,
            valor_total DECIMAL(10,2) NOT NULL,
            valor_pago DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('PENDENTE', 'PARCIAL', 'PAGO') DEFAULT 'PENDENTE',
            data_vencimento DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",

        // Entradas
        "CREATE TABLE IF NOT EXISTS entradas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data DATE NOT NULL,
            nf VARCHAR(60) NOT NULL,
            fornecedor_id INT NOT NULL,
            produto_id INT NOT NULL,
            unidade VARCHAR(40) NOT NULL,
            qtd INT NOT NULL DEFAULT 0,
            custo DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // Saídas
        "CREATE TABLE IF NOT EXISTS saidas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data DATE NOT NULL,
            pedido VARCHAR(60) NOT NULL,
            cliente VARCHAR(180) NOT NULL,
            canal VARCHAR(20) NOT NULL,
            pagamento VARCHAR(30) NOT NULL,
            produto_id INT NOT NULL,
            unidade VARCHAR(40) NOT NULL,
            qtd DECIMAL(10,3) NOT NULL DEFAULT 0,
            preco DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",

        // Devoluções
        "CREATE TABLE IF NOT EXISTS devolucoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            venda_no INT NULL,
            cliente VARCHAR(180) NULL,
            data DATE NOT NULL,
            hora TIME NOT NULL,
            tipo VARCHAR(10) NOT NULL DEFAULT 'TOTAL',
            produto VARCHAR(255) NULL,
            qtd INT NULL,
            valor DECIMAL(10,2) NOT NULL DEFAULT 0,
            motivo VARCHAR(40) NOT NULL DEFAULT 'OUTRO',
            obs VARCHAR(255) NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'ABERTO',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",

        // Usuários
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            senha VARCHAR(255) NOT NULL,
            perfil ENUM('ADMIN', 'VENDEDOR') DEFAULT 'VENDEDOR',
            status ENUM('ATIVO', 'INATIVO') DEFAULT 'ATIVO',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",

        // Parâmetros
        "CREATE TABLE IF NOT EXISTS parametros (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chave VARCHAR(50) NOT NULL UNIQUE,
            valor TEXT,
            descricao VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    ];

    foreach ($queries as $q) {
        try {
            $pdo->exec($q);
            $log("Sucesso: " . substr($q, 0, 50) . "...");
        } catch (Throwable $e) {
            $log("ERRO na query [" . substr($q, 0, 50) . "...]: " . $e->getMessage());
        }
    }

    try {
        // Inserir Admin padrão se não houver usuários
        $count = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
        if ($count === 0) {
            $senha = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, status) VALUES (?, ?, ?, ?, ?)")
                ->execute(['Administrador', 'admin@admin.com', $senha, 'ADMIN', 'ATIVO']);
            $log("Admin padrão criado.");
        }

        // Inserir Parâmetros padrão se não houver
        $countP = (int)$pdo->query("SELECT COUNT(*) FROM parametros")->fetchColumn();
        if ($countP === 0) {
            $params = [
                ['NOME_EMPRESA', 'Distribuidora Exemplo', 'Nome da empresa no sistema'],
                ['CNPJ', '00.000.000/0001-00', 'CNPJ da empresa'],
                ['TELEFONE', '(92) 99999-9999', 'Telefone de contato']
            ];
            $st = $pdo->prepare("INSERT INTO parametros (chave, valor, descricao) VALUES (?, ?, ?)");
            foreach ($params as $p) $st->execute($p);
            $log("Parâmetros padrão criados.");
        }
    } catch (Throwable $e) {
        $log("ERRO ao inserir dados básicos: " . $e->getMessage());
    }

    $log("Inicialização concluída.");
}
