<?php
// ERP Elétrica - Core Configuration
require_once 'autoloader.php';
require_once __DIR__ . '/src/App/Config/Helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'erp_eletrica');

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

// ... rest of the file logic ...
// Note: runMigrations is already called below.

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
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
    
    if (!empty($niveis_permitidos) && !in_array($_SESSION['usuario_nivel'], $niveis_permitidos)) {
        header('Location: index.php?msg=Acesso negado');
        exit;
    }
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

