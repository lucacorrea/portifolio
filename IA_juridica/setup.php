<?php
/**
 * Setup script to initialize the database
 */

require_once __DIR__ . '/database/init.php';

echo "<h1>Setup IA Jurídica</h1>";

if (initializeDatabase()) {
    echo "<p style='color: green;'>✅ Banco de dados inicializado com sucesso!</p>";
    echo "<p>A tabela 'documentos' foi criada ou já existe.</p>";
} else {
    echo "<p style='color: red;'>❌ Erro ao inicializar o banco de dados. Verifique as credenciais em config/database.php.</p>";
}

echo "<hr>";
echo "<a href='dashboard.php'>Ir para o Dashboard</a>";
