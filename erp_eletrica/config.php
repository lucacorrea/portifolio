<?php
// ERP Elétrica - Core Configuration
require_once 'autoloader.php';
require_once __DIR__ . '/src/App/Config/Helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'u784961086_pdv');
define('DB_PASS', 'Uv$1NhLlkRub');
define('DB_NAME', 'u784961086_pdv');

// Technical Constants
define('APP_NAME', 'ERP Elétrica');
define('APP_VERSION', '2.0.0');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Database Connection via Singleton
try {
    $database = \App\Config\Database::getInstance();
    $pdo = $database->getConnection();
} catch(Exception $e) {
    die("Erro crítico de conexão: " . $e->getMessage());
}

// Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// CSRF Prevention
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validateCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ... rest of the file logic ...
// Note: runMigrations is already called below.

// Migration Runner
try {
    $migrationService = new \App\Services\MigrationService();
    $migrationService->run();
} catch (Exception $e) {
    error_log("Erro de migração: " . $e->getMessage());
}

// Global Exception Handler
set_exception_handler(function($e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    if (defined('DEBUG') && DEBUG) {
        echo "<h1>Erro Crítico</h1><pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
    } else {
        echo "<h1>Desculpe, ocorreu um erro interno.</h1><p>Por favor, tente novamente mais tarde.</p>";
    }
});

// Session Timeout Logic
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=Sessão expirada por inatividade");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Auth Helpers
function checkAuth($niveis_permitidos = []) {
    \App\Services\AuthService::check($niveis_permitidos);
}

function gerarProximoNumeroOS($pdo) {
    try {
        $prefix = 'OS-' . date('Y-m');
        $stmt = $pdo->prepare("SELECT numero_os FROM os WHERE numero_os LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$prefix . '-%']);
        $last = $stmt->fetch();
        
        if ($last) {
            $parts = explode('-', $last['numero_os']);
            $lastNum = (int)end($parts);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }
        
        return $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'OS-' . date('YmdHis'); // Fallback
    }
}
?>

