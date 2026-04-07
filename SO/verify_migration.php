<?php
require_once 'c:/xampp/htdocs/SO/config/database.php';

try {
    echo "--- TESTE DE CONEXÃO ---\n";
    $databases = $pdo->query("SHOW DATABASES LIKE 'sgao'")->fetch();
    if ($databases) {
        echo "Banco 'sgao' existe: SIM\n";
    }

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tabelas encontradas: " . implode(", ", $tables) . "\n";

    $users = $pdo->query("SELECT * FROM usuarios")->fetchAll();
    echo "Total de usuários: " . count($users) . "\n";

    foreach ($users as $user) {
        $check = password_verify('123', $user['senha']) ? 'VÁLIDA' : 'INVÁLIDA';
        echo "Usuário: {$user['usuario']} | Senha '123': $check\n";
    }

} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
