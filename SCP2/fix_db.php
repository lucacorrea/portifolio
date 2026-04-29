<?php
require_once 'api.php'; // Reuse connection

try {
    // 1. Update existing processes
    $stmt = $pdo->prepare("UPDATE processos SET analisador = 'KELLEN' WHERE analisador LIKE '%KELLEN VIANA%'");
    $stmt->execute();
    $updatedProcessos = $stmt->rowCount();

    // 2. Update user name in usuarios table
    $stmt = $pdo->prepare("UPDATE usuarios SET nome = 'KELLEN' WHERE nome LIKE '%KELLEN VIANA%'");
    $stmt->execute();
    $updatedUsuarios = $stmt->rowCount();

    echo "Sucesso!<br>";
    echo "Processos atualizados: $updatedProcessos<br>";
    echo "Usuários atualizados: $updatedUsuarios<br>";
    echo "<br><a href='index.php'>Voltar para o Dashboard</a>";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
