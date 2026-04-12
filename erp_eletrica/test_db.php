<?php
require 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

echo "<pre>";
echo "<strong>--- DIAGNÓSTICO DE VENDAS (312 a 318) ---</strong>\n\n";

$ids = [312, 313, 314, 315, 316, 317, 318];

foreach ($ids as $id) {
    echo "VENDA #$id:\n";
    $stmtV = $db->prepare("SELECT id, tipo_nota, status, valor_total FROM vendas WHERE id = ?");
    $stmtV->execute([$id]);
    print_r($stmtV->fetch(PDO::FETCH_ASSOC));

    echo "NFC-E EMITIDAS:\n";
    $stmtN = $db->prepare("SELECT id, status_sefaz, chave, mensagem FROM nfce_emitidas WHERE venda_id = ?");
    $stmtN->execute([$id]);
    $results = $stmtN->fetchAll(PDO::FETCH_ASSOC);
    if (empty($results)) {
        echo "Nenhum registro em nfce_emitidas.\n";
    } else {
        print_r($results);
    }
    echo "------------------------------------------\n";
}
echo "</pre>";

