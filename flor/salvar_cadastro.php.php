<?php
session_start();
require './conex.php';

$nome     = trim($_POST['nome'] ?? '');
$email    = trim($_POST['email'] ?? '');
$cidade   = trim($_POST['cidade'] ?? '');
$endereco = trim($_POST['endereco'] ?? '');
$cep      = trim($_POST['cep'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$produto_id = $_POST['produto_id'] ?? 0;

/* VALIDAÇÃO */
if ($nome === '' || $email === '' || $endereco === '') {
    die("Erro: Preencha todos os campos obrigatórios.");
}

/* SALVAR NO BANCO */
$stmt = $conex->prepare("
    INSERT INTO clientes (nome, email, cidade, endereco, cep, telefone)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("ssssss", $nome, $email, $cidade, $endereco, $cep, $telefone);

if ($stmt->execute()) {

    // cria sessão
    $_SESSION['cliente_logado'] = true;
    $_SESSION['cliente_nome']   = $nome;
    $_SESSION['cliente_email']  = $email;

    // volta ao produto
    header("Location: product-details.php?id=$produto_id&sucesso=1");
    exit;
}

die("Erro ao salvar cadastro: " . $conex->error);
