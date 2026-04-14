<?php
require_once 'config.php';

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    // 1. Identificar o nome do índice único de CNPJ
    $stmt = $db->query("SHOW INDEX FROM filiais WHERE Column_name = 'cnpj' AND Non_unique = 0");
    $index = $stmt->fetch();
    
    if ($index) {
        $indexName = $index['Key_name'];
        $db->exec("ALTER TABLE filiais DROP INDEX $indexName");
        echo "Sucesso: O índice único do CNPJ foi removido. Agora você pode ter várias filiais com o mesmo CNPJ.<br>";
    } else {
        echo "Aviso: Nenhum índice único encontrado para a coluna CNPJ. O erro de duplicidade pode vir de outra restrição.<br>";
    }

    // 2. Verificar se a Inscrição Estadual também precisa ser não-única (opcional, vamos apenas checar)
    $stmtIE = $db->query("SHOW INDEX FROM filiais WHERE Column_name = 'inscricao_estadual' AND Non_unique = 0");
    $indexIE = $stmtIE->fetch();
    if ($indexIE) {
        $nameIE = $indexIE['Key_name'];
        $db->exec("ALTER TABLE filiais DROP INDEX $nameIE");
        echo "Sucesso: O índice único da Inscrição Estadual também foi removido.<br>";
    }

} catch (Exception $e) {
    echo "Erro ao ajustar banco de dados: " . $e->getMessage();
}
unlink(__FILE__); // Remove o arquivo após execução por segurança
echo "<br>Arquivo de correção auto-removido por segurança.";
