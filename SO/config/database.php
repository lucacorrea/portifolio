<?php
// config/database.php

$host = 'localhost';
$db_user = 'u784961086_so';
$db_pass = 'Y>g39k3ql'; // Senha padrão do XAMPP é vazia
$dbname = 'u784961086_so';
$schema_file = __DIR__ . '/../database/schema.sql';

try {
    // 1. Conecta ao MySQL (sem o banco ainda)
    $pdo = new PDO("mysql:host=$host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Cria o banco se não existir
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 3. Conecta especificamente ao banco sgao
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 4. Se a tabela 'usuarios' não existir, roda o schema inicial
    $query = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    if (!$query->fetch()) {
        $sql = file_get_contents($schema_file);
        $pdo->exec($sql);
    }

        // Tabela oficio_anexos (Novo para múltiplos anexos)
        $query = $pdo->query("SHOW TABLES LIKE 'oficio_anexos'");
        if (!$query->fetch()) {
            $pdo->exec("CREATE TABLE oficio_anexos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                oficio_id INT NOT NULL,
                caminho VARCHAR(255) NOT NULL,
                tipo ENUM('ORCAMENTO', 'OFICIO') NOT NULL,
                nome_original VARCHAR(255),
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (oficio_id) REFERENCES oficios(id) ON DELETE CASCADE
            )");

            // Migrar dados existentes (apenas na criação da tabela)
            $stmt = $pdo->query("SELECT id, arquivo_orcamento, arquivo_oficio FROM oficios WHERE arquivo_orcamento IS NOT NULL OR arquivo_oficio IS NOT NULL");
            $oficios = $stmt->fetchAll();
            foreach ($oficios as $o) {
                if (!empty($o['arquivo_orcamento'])) {
                    $pdo->prepare("INSERT INTO oficio_anexos (oficio_id, caminho, tipo) VALUES (?, ?, 'ORCAMENTO')")
                        ->execute([$o['id'], $o['arquivo_orcamento']]);
                }
                if (!empty($o['arquivo_oficio'])) {
                    $pdo->prepare("INSERT INTO oficio_anexos (oficio_id, caminho, tipo) VALUES (?, ?, 'OFICIO')")
                        ->execute([$o['id'], $o['arquivo_oficio']]);
                }
            }
        }
    } catch (PDOException $e) {
        // Ignora erros se a tabela ainda não existir (o item 4 cuidará disso)
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados MySQL: " . $e->getMessage());
}

function getPDO() {
    global $pdo;
    return $pdo;
}
