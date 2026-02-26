<?php
require_once 'config.php';

echo "<h1>Fazendo Manutenção no Banco de Dados...</h1>";

try {
    // Tenta adicionar a coluna auth_pin
    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN auth_pin VARCHAR(255) DEFAULT NULL");
        echo "<p>✅ Coluna 'auth_pin' adicionada.</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Coluna 'auth_pin' já deve existir ou erro menor: " . $e->getMessage() . "</p>";
    }

    // Tenta adicionar a coluna auth_type
    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN auth_type ENUM('password', 'pin') DEFAULT 'password'");
        echo "<p>✅ Coluna 'auth_type' adicionada.</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Coluna 'auth_type' já deve existir ou erro menor: " . $e->getMessage() . "</p>";
    }

    // Limpa a tabela de migrações para garantir que a 009 possa rodar de novo se necessário no futuro
    // Mas o principal é que os comandos acima já resolvem.
    
    echo "<h2>Concluído!</h2>";
    echo "<p>Por favor, apague este arquivo (fix_db.php) por segurança após rodar.</p>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>Erro Crítico:</h2> " . $e->getMessage();
}
