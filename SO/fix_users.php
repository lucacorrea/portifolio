<?php
require_once 'config/database.php';

try {
    // Corrige os usuários que ficaram com o nível em branco no banco de dados.
    // Procura por logins que contenham 'sefaz' ou 'casa' e aplica o nível correto.
    
    $stmt = $pdo->prepare("UPDATE usuarios SET nivel = 'SEFAZ' WHERE usuario LIKE '%sefaz%' OR nome LIKE '%sefaz%'");
    $stmt->execute();
    echo "Nível corporativo SEFAZ restaurado para os usuários correspondentes.<br>";

    $stmt2 = $pdo->prepare("UPDATE usuarios SET nivel = 'CASA_CIVIL' WHERE usuario LIKE '%casa%' OR nome LIKE '%casa%'");
    $stmt2->execute();
    echo "Nível corporativo CASA_CIVIL restaurado para os usuários correspondentes.<br>";

    echo "<h1>Correção de Usuários Concluída!</h1>";
    echo "<p>Agora acesse o sistema novamente com o login da sefaz ou casa civil.</p>";

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
