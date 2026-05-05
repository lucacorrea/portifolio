<?php
require dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Debug Conexão Banco de Dados</h1>";

$config = Database::config();
echo "<h2>Configuração Atual:</h2>";
echo "<ul>";
echo "<li>Driver: " . $config['driver'] . "</li>";
echo "<li>Host: " . $config['host'] . "</li>";
echo "<li>Porta: " . $config['port'] . "</li>";
echo "<li>Banco: " . $config['database'] . "</li>";
echo "<li>Usuário: " . $config['username'] . "</li>";
echo "</ul>";

try {
    echo "<h3>Tentando conectar...</h3>";
    $pdo = Database::pdo();
    echo "<p style='color: green; font-weight: bold;'>CONECTADO COM SUCESSO!</p>";
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h4>Tabelas encontradas:</h4>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";

} catch (PDOException $e) {
    echo "<p style='color: red; font-weight: bold;'>ERRO DE CONEXÃO:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<h4>Código de erro:</h4>";
    echo "<pre>" . $e->getCode() . "</pre>";
    
    if (str_contains($e->getMessage(), 'Access denied')) {
        echo "<p><strong>Dica:</strong> Usuário ou senha incorretos no arquivo <code>app/Config/database.php</code>.</p>";
    } elseif (str_contains($e->getMessage(), 'Unknown database')) {
        echo "<p><strong>Dica:</strong> O banco de dados <code>" . $config['database'] . "</code> não existe no servidor.</p>";
    } elseif (str_contains($e->getMessage(), 'Connection refused') || str_contains($e->getMessage(), 'host')) {
        echo "<p><strong>Dica:</strong> O host <code>" . $config['host'] . "</code> não está acessível ou o MySQL não está rodando.</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color: orange;'>Erro inesperado: " . $e->getMessage() . "</p>";
}
