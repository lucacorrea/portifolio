<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Adjust paths based on location in public/
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Autoload.php';

use App\Core\Database;

try {
    // Register Autoloader
    $autoloader = new App\Core\Autoload();
    $autoloader->register();

    // Connect
    $db = Database::getInstance()->getConnection();
    
    // Config
    $password = '123456';
    $email = 'admin@admin.com';
    
    // Hash
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update
    $stmt = $db->prepare("UPDATE usuarios SET senha = :senha WHERE email = :email");
    $stmt->execute(['senha' => $hash, 'email' => $email]);
    
    if ($stmt->rowCount() > 0) {
        echo "<div style='font-family: sans-serif; padding: 20px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px;'>";
        echo "<strong>Sucesso!</strong><br>";
        echo "Senha alterada para o usuário <strong>$email</strong>.<br>";
        echo "Nova senha: <strong>$password</strong><br>";
        echo "<br><a href='login' style='font-weight: bold;'>Ir para Login</a>";
        echo "</div>";
    } else {
        echo "<div style='font-family: sans-serif; padding: 20px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 5px;'>";
        echo "<strong>Atenção:</strong> Nenhum usuário encontrado com o email $email ou a senha já é esta.<br>";
        echo "Verifique se a tabela 'usuarios' existe e tem o registro do admin.";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='font-family: sans-serif; padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<strong>Erro:</strong> " . $e->getMessage();
    echo "</div>";
}
