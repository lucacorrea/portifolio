<?php
require_once 'api.php';

try {
    // 1. Check current counts
    $stmt = $pdo->query("SELECT analisador, COUNT(*) as qtd FROM processos GROUP BY analisador");
    $counts = $stmt->fetchAll();
    
    echo "<h3>Estado atual do banco:</h3>";
    echo "<ul>";
    foreach ($counts as $row) {
        echo "<li>" . ($row['analisador'] ?: 'N/A') . ": " . $row['qtd'] . "</li>";
    }
    echo "</ul>";

    // 2. Forced Update
    echo "<h3>Tentando forçar atualização...</h3>";
    // Usando TRIM e garantindo que pegamos qualquer variação de Kellen Viana
    $stmt = $pdo->prepare("UPDATE processos SET analisador = 'KELLEN' WHERE UPPER(analisador) LIKE '%KELLEN%'");
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo "Registros atualizados para 'KELLEN': $affected<br>";

    // 3. Update users table too
    $stmt = $pdo->prepare("UPDATE usuarios SET nome = 'KELLEN' WHERE UPPER(nome) LIKE '%KELLEN%'");
    $stmt->execute();
    
    echo "<br><a href='index.php'>Voltar e atualizar o Dashboard</a>";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
