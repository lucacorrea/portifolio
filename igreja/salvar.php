<?php
include 'conexao.php';

$fotoNome = null;
if (!empty($_FILES['foto']['name'])) {
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $fotoNome = uniqid('foto_', true) . '.' . $ext;
    $destino = __DIR__ . '/uploads/' . $fotoNome;
    if (!is_dir(__DIR__ . '/uploads')) {
        mkdir(__DIR__ . '/uploads', 0777, true);
    }
    move_uploaded_file($_FILES['foto']['tmp_name'], $destino);
}

$sql = "INSERT INTO membros (
    foto, nome_completo, sexo, data_nascimento, nacionalidade, naturalidade, estado_uf, escolaridade,
    profissao, identidade, cpf, pai, mae, estado_civil, conjuge, filhos, rua, numero, bairro, cep,
    cidade, estado, telefone, tipo_ingresso, data_decisao, procedencia, congregacao, area, nucleo, observacao
) VALUES (
    :foto, :nome_completo, :sexo, :data_nascimento, :nacionalidade, :naturalidade, :estado_uf, :escolaridade,
    :profissao, :identidade, :cpf, :pai, :mae, :estado_civil, :conjuge, :filhos, :rua, :numero, :bairro, :cep,
    :cidade, :estado, :telefone, :tipo_ingresso, :data_decisao, :procedencia, :congregacao, :area, :nucleo, :observacao
)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':foto' => $fotoNome,
    ':nome_completo' => $_POST['nome_completo'] ?? null,
    ':sexo' => $_POST['sexo'] ?? null,
    ':data_nascimento' => $_POST['data_nascimento'] ?: null,
    ':nacionalidade' => $_POST['nacionalidade'] ?? null,
    ':naturalidade' => $_POST['naturalidade'] ?? null,
    ':estado_uf' => $_POST['estado_uf'] ?? null,
    ':escolaridade' => $_POST['escolaridade'] ?? null,
    ':profissao' => $_POST['profissao'] ?? null,
    ':identidade' => $_POST['identidade'] ?? null,
    ':cpf' => $_POST['cpf'] ?? null,
    ':pai' => $_POST['pai'] ?? null,
    ':mae' => $_POST['mae'] ?? null,
    ':estado_civil' => $_POST['estado_civil'] ?? null,
    ':conjuge' => $_POST['conjuge'] ?? null,
    ':filhos' => $_POST['filhos'] !== '' ? (int)$_POST['filhos'] : 0,
    ':rua' => $_POST['rua'] ?? null,
    ':numero' => $_POST['numero'] ?? null,
    ':bairro' => $_POST['bairro'] ?? null,
    ':cep' => $_POST['cep'] ?? null,
    ':cidade' => $_POST['cidade'] ?? null,
    ':estado' => $_POST['estado'] ?? null,
    ':telefone' => $_POST['telefone'] ?? null,
    ':tipo_ingresso' => $_POST['tipo_ingresso'] ?? null,
    ':data_decisao' => $_POST['data_decisao'] ?: null,
    ':procedencia' => $_POST['procedencia'] ?? null,
    ':congregacao' => $_POST['congregacao'] ?? null,
    ':area' => $_POST['area'] ?? null,
    ':nucleo' => $_POST['nucleo'] ?? null,
    ':observacao' => $_POST['observacao'] ?? null,
]);

header('Location: listar.php');
exit;
?>
