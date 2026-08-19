<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Instalação permitida somente via CLI.');
}
require_once dirname(__DIR__) . '/lib/bootstrap.php';
$sql = file_get_contents(__DIR__ . '/001_primeiro_emprego.sql');
if ($sql === false) {
    throw new RuntimeException('SQL não encontrado.');
}
$pdo = pe_db();
$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement === '' || strpos($statement, '--') === 0 && substr_count($statement, "\n") === 0) {
        continue;
    }
    $pdo->exec($statement);
}
echo "Estrutura Meu Primeiro Emprego instalada com sucesso.\n";
