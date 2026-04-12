<?php
/**
 * SCRIPT DE ALINHAMENTO FISCAL (CENTRALIZADOR)
 * Este script limpa dados fiscais residuais das filiais para forçar a herança 100% da Matriz.
 */
require_once 'config.php';
$db = \App\Config\Database::getInstance()->getConnection();

echo "<h3>Alinhamento Fiscal Centralizado</h3>";

// 1. Identifica a Matriz
$matriz = $db->query("SELECT id, nome, cnpj FROM filiais WHERE principal = 1 LIMIT 1")->fetch();

if (!$matriz) {
    die("<p style='color:red'>ERRO: Nenhuma unidade marcada como 'Principal' (Matriz) encontrada no banco.</p>");
}

echo "<p>Identificada Matriz Principal: <b>{$matriz['nome']}</b> (ID: {$matriz['id']})</p>";

// 2. Limpa dados fiscais das filiais (Exceto a Matriz)
$sql = "UPDATE filiais SET 
        cnpj = NULL, 
        inscricao_estadual = NULL, 
        razao_social = NULL,
        certificado_pfx = NULL, 
        csc_token = NULL, 
        csc_id = NULL,
        ambiente = NULL
        WHERE id != ?";

$stmt = $db->prepare($sql);
$stmt->execute([$matriz['id']]);
$count = $stmt->rowCount();

echo "<p style='color:green'>Sucesso! <b>{$count}</b> filiais foram limpas e agora herdarão 100% da identidade fiscal da Matriz.</p>";

// 3. Verifica a configuração global do certificado
$global = $db->query("SELECT id, certificado_path FROM sefaz_config LIMIT 1")->fetch();
if (!$global || empty($global['certificado_path'])) {
    echo "<p style='color:orange'>AVISO: O sistema não encontrou um Certificado Digital na Central Fiscal Global. 
          Certifique-se de fazer o upload do .pfx no painel de Configurações da Matriz.</p>";
} else {
    echo "<p style='color:blue'>Certificado Global detectado: {$global['certificado_path']}</p>";
}

echo "<hr><p>Agora, qualquer venda em qualquer filial usará obrigatoriamente o CNPJ: <b>{$matriz['cnpj']}</b>.</p>";
unlink(__FILE__); // Autodestruição por segurança
