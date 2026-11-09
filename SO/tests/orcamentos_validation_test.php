<?php
require_once __DIR__ . '/../config/orcamentos_lote_validation.php';
$valid = ['token' => str_repeat('a', 32), 'lote_token' => str_repeat('b', 32), 'confirmado' => true,
    'modo' => 'aprovar', 'secretaria_id' => 1, 'fornecedor_id' => 2, 'local' => 'Unidade de teste',
    'justificativa' => 'Manutenção de equipamentos', 'total_documento' => '899.72',
    'itens' => [['codigo' => '244', 'produto' => 'Carga de gás refrigerante', 'unidade' => 'SERV',
        'quantidade' => '2', 'valor_unitario' => '449.86', 'valor_total' => '899.72']]];
$checks = 0;
function verify($condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function rejects(array $data, string $label): void {
    try { lote_validate($data); } catch (DomainException $e) { verify(true, $label); return; }
    throw new RuntimeException('Deveria rejeitar: ' . $label);
}
$result = lote_validate($valid);
verify($result['total'] === '899.72', 'Total monetário exato');
verify($result['itens'][0]['quantidade'] === '2.00', 'Quantidade normalizada');
verify($result['itens'][0]['codigo'] === '244', 'Referência não sequencial preservada');
foreach (['unidade' => '', 'quantidade' => '0', 'valor_unitario' => '-1', 'valor_total' => '899.73', 'produto' => str_repeat('x', 256)] as $key => $value) {
    $bad = $valid; $bad['itens'][0][$key] = $value; rejects($bad, $key);
}
foreach (['total_documento' => '1.00', 'confirmado' => false, 'modo' => 'livre', 'fornecedor_id' => '2', 'secretaria_id' => -1, 'token' => '../arquivo'] as $key => $value) {
    $bad = $valid; $bad[$key] = $value; rejects($bad, $key);
}
$bad = $valid; $bad['itens'][0]['quantidade'] = '2.001'; rejects($bad, 'Precisão excessiva');
$bad = $valid; $bad['itens'][0]['quantidade'] = '100000000'; rejects($bad, 'Overflow DECIMAL');
$bad = $valid; $bad['itens'][0]['valor_unitario'] = ['449.86']; rejects($bad, 'Tipo inválido');
$bad = $valid; $bad['itens'] = []; rejects($bad, 'Sem itens');
$fraction = $valid; $fraction['itens'][0]['quantidade'] = '1.25'; $fraction['itens'][0]['valor_unitario'] = '10.10';
$fraction['itens'][0]['valor_total'] = '12.63'; $fraction['total_documento'] = '12.63';
verify(lote_validate($fraction)['total'] === '12.63', 'Arredondamento por linha');
echo "{$checks} verificações de validação passaram.\n";
