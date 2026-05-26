<?php
require_once 'config.php';

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, nome, razao_social, cnpj, inscricao_estadual, logradouro, numero, complemento, bairro, municipio, uf, cep, principal FROM filiais");
    $filiais = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($filiais, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
