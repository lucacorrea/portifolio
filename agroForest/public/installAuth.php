<?php
require dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$token = $_GET['token'] ?? '';
$expectedToken = '68dfaf2c46fd2d1b76d4df670dbb6761fa5093db61276dda';

if (!hash_equals($expectedToken, (string) $token)) {
    http_response_code(404);
    exit('Not found');
}

try {
    $pdo = db();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
          id INT AUTO_INCREMENT PRIMARY KEY,
          nome VARCHAR(120) NOT NULL,
          email VARCHAR(160) NOT NULL UNIQUE,
          cpf VARCHAR(20) DEFAULT NULL,
          senha VARCHAR(255) NOT NULL,
          nivel ENUM('recepcao','administrativo','dono') NOT NULL DEFAULT 'recepcao',
          ativo TINYINT(1) NOT NULL DEFAULT 1,
          ultimo_login DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columns = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'ultimo_login'")->fetch();
    if (!$columns) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN ultimo_login DATETIME NULL AFTER ativo');
    }

    $cpfColumn = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'cpf'")->fetch();
    if (!$cpfColumn) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(20) DEFAULT NULL AFTER email');
    }

    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nome, email, cpf, senha, nivel, ativo) VALUES
        (?, ?, ?, ?, 'recepcao', 1),
        (?, ?, ?, ?, 'administrativo', 1),
        (?, ?, ?, ?, 'dono', 1)
        ON DUPLICATE KEY UPDATE
          nome = VALUES(nome),
          cpf = VALUES(cpf),
          senha = VALUES(senha),
          nivel = VALUES(nivel),
          ativo = VALUES(ativo)
    ");

    $stmt->execute([
        'Recepção',
        'recepcao@agroforest.test',
        '11122233344',
        'pbkdf2_sha256$100000$58956eb178db20badfc23db429605cc4$6be2b831342df6b188c2a8ad601d18c6a0c56c2499a71e7f2bb7bfc619841e19',
        'Administrativo',
        'administrativo@agroforest.test',
        '22233344455',
        'pbkdf2_sha256$100000$d23c59529dfa23f756ac61d436b4a740$c514b9459bddd28735141f1f689d76c382f413d8a465668373d1f1e83c4febc2',
        'Dono',
        'dono@agroforest.test',
        '33344455566',
        'pbkdf2_sha256$100000$c88d6949ee4c91a8a4a6cd7d7c086c13$9accfad0d725ab7f227cabba34f79ae6507b93ffa041bf35f046aa96c3bb3771',
    ]);

    echo "Auth install OK\n";
    echo "Context: " . db_safe_context() . "\n";
    echo "Users:\n";
    echo "- Recepção Agro Forest ou recepcao@agroforest.test ou 11122233344 / recepcao123\n";
    echo "- Administrativo Agro Forest ou administrativo@agroforest.test ou 22233344455 / administrativo123\n";
    echo "- Dono Agro Forest ou dono@agroforest.test ou 33344455566 / dono123\n";
} catch (Throwable $exception) {
    http_response_code(500);
    app_log_write('error', 'Auth install failed: ' . db_safe_context(), $exception);
    echo "Auth install failed\n";
    echo "Context: " . db_safe_context() . "\n";
    echo "Error: " . $exception->getMessage() . "\n";
}
