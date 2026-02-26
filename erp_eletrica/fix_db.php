<?php
require_once 'config.php';

echo "<h1>Fazendo Manutenção no Banco de Dados (Parte 2)...</h1>";

try {
    // 1. Tabela 'usuarios'
    echo "<h3>Verificando tabela 'usuarios'...</h3>";
    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS auth_pin VARCHAR(255) DEFAULT NULL");
        echo "<p>✅ Coluna 'auth_pin' verificada/adicionada.</p>";
    } catch (Exception $e) { echo "<p>ℹ️ " . $e->getMessage() . "</p>"; }

    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS auth_type ENUM('password', 'pin') DEFAULT 'password'");
        echo "<p>✅ Coluna 'auth_type' verificada/adicionada.</p>";
    } catch (Exception $e) { echo "<p>ℹ️ " . $e->getMessage() . "</p>"; }

    // 2. Tabela 'vendas'
    echo "<h3>Verificando tabela 'vendas'...</h3>";
    try {
        $pdo->exec("ALTER TABLE vendas ADD COLUMN IF NOT EXISTS desconto_total DECIMAL(10,2) DEFAULT 0.00 AFTER valor_total");
        echo "<p>✅ Coluna 'desconto_total' adicionada em 'vendas'.</p>";
    } catch (Exception $e) { echo "<p>ℹ️ " . $e->getMessage() . "</p>"; }

    try {
        $pdo->exec("ALTER TABLE vendas ADD COLUMN IF NOT EXISTS autorizado_por INT NULL AFTER usuario_id");
        echo "<p>✅ Coluna 'autorizado_por' adicionada em 'vendas'.</p>";
    } catch (Exception $e) { echo "<p>ℹ️ " . $e->getMessage() . "</p>"; }

    try {
        $pdo->exec("ALTER TABLE vendas ADD CONSTRAINT fk_vendas_autorizado_por FOREIGN KEY (autorizado_por) REFERENCES usuarios(id) ON DELETE SET NULL");
        echo "<p>✅ Chave estrangeira de autorização vinculada.</p>";
    } catch (Exception $e) { echo "<p>ℹ️ Chave/Constraint já existe ou erro menor: " . $e->getMessage() . "</p>"; }

    echo "<h2>Concluído com Sucesso!</h2>";
    echo "<p>Por favor, apague este arquivo (fix_db.php) por segurança após rodar.</p>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>Erro Crítico:</h2> " . $e->getMessage();
}
