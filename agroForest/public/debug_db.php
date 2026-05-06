<?php
require dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$token = $_GET['token'] ?? '';
$expectedToken = '01b312f4a8d53b0a20d7d88257eb6d7dc83f9549ce5f1e3d';

if (PHP_SAPI !== 'cli' && !hash_equals($expectedToken, (string) $token)) {
    http_response_code(404);
    exit('Not found');
}

echo "<h1>Debug Conexão Banco de Dados</h1>";

$config = Database::config();
echo "<h2>Configuração Atual:</h2>";
echo "<ul>";
echo "<li>Contexto: " . htmlspecialchars(Database::safeContext()) . "</li>";
echo "<li>Log: " . htmlspecialchars((string) AppLogger::path()) . "</li>";
echo "<li>Log existe: " . (is_file((string) AppLogger::path()) ? 'sim' : 'não') . "</li>";
echo "</ul>";

try {
    echo "<h3>Tentando conectar...</h3>";
    $pdo = Database::pdo();
    echo "<p style='color: green; font-weight: bold;'>CONECTADO COM SUCESSO!</p>";
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h4>Tabelas encontradas:</h4>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars((string) $table) . "</li>";
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
        echo "<p><strong>Dica:</strong> O banco de dados <code>" . htmlspecialchars($config['database']) . "</code> não existe no servidor.</p>";
    } elseif (str_contains($e->getMessage(), 'Connection refused') || str_contains($e->getMessage(), 'host')) {
        echo "<p><strong>Dica:</strong> O host <code>" . htmlspecialchars($config['host']) . "</code> não está acessível ou o MySQL não está rodando.</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color: orange;'>Erro inesperado: " . $e->getMessage() . "</p>";
}
