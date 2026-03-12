<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "--- USER DB RESEARCH (u784961086_pdv) ---\n";

try {
    $pdo = new PDO("mysql:host=localhost;dbname=u784961086_pdv", "u784961086_pdv", "Uv$1NhLlkRub");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connection Success.\n";
    
    echo "\n[integracao_nfce counts]:\n";
    try {
        $res = $pdo->query("SELECT empresa_id, razao_social FROM integracao_nfce")->fetchAll(PDO::FETCH_ASSOC);
        print_r($res);
    } catch (Exception $e) { echo "integracao_nfce Error: " . $e->getMessage() . "\n"; }
    
    echo "\n[filiais counts]:\n";
    try {
        $res = $pdo->query("SELECT id, nome, razao_social FROM filiais")->fetchAll(PDO::FETCH_ASSOC);
        print_r($res);
    } catch (Exception $e) { echo "filiais Error: " . $e->getMessage() . "\n"; }

    echo "\n[vendas sample]:\n";
    try {
        $res = $pdo->query("SELECT id, empresa_id FROM vendas ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        print_r($res);
    } catch (Exception $e) { echo "vendas Error: " . $e->getMessage() . "\n"; }

} catch (Exception $e) {
    echo "PDO Error: " . $e->getMessage() . "\n";
}
