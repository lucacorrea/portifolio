<?php
require dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$token = $_GET['token'] ?? '';
$expectedToken = '01b312f4a8d53b0a20d7d88257eb6d7dc83f9549ce5f1e3d';

if (PHP_SAPI !== 'cli' && !hash_equals($expectedToken, (string) $token)) {
    http_response_code(404);
    exit('Not found');
}

echo "Agro Forest database check\n";
echo "Context: " . Database::safeContext() . "\n";
echo "Log path: " . BASE_PATH . "/storage/logs/app.log\n";
echo "Log directory writable: " . (is_writable(BASE_PATH . '/storage/logs') ? 'yes' : 'no') . "\n";

try {
    $pdo = Database::pdo();
    echo "PDO: connected\n";

    $version = $pdo->query('SELECT VERSION() AS version')->fetch();
    echo "MySQL: " . ($version['version'] ?? 'unknown') . "\n";

    $table = $pdo->query("SHOW TABLES LIKE 'usuarios'")->fetch();
    echo "usuarios table: " . ($table ? 'found' : 'missing') . "\n";

    if ($table) {
        $count = $pdo->query('SELECT COUNT(*) AS total FROM usuarios')->fetch();
        echo "usuarios count: " . ($count['total'] ?? '0') . "\n";
    }
} catch (Throwable $exception) {
    http_response_code(500);
    AppLogger::error('Database check failed: ' . Database::safeContext(), $exception);
    echo "PDO: failed\n";
    echo "Code: " . $exception->getCode() . "\n";
    echo "Error: " . $exception->getMessage() . "\n";
}
