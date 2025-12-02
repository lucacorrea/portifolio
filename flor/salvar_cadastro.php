<?php
session_start();
require './conex.php';

/* PEGAR DADOS */
$nome      = $_POST['nome'] ?? '';
$email     = $_POST['email'] ?? '';
$cidade    = $_POST['cidade'] ?? '';
$endereco  = $_POST['endereco'] ?? '';
$cep       = $_POST['cep'] ?? '';
$telefone  = $_POST['telefone'] ?? '';

$produto_id = $_POST['produto_id'] ?? 0;

/* SALVAR NO BANCO */
$sql = $conex->prepare("
    INSERT INTO clientes (nome, email, cidade, endereco, cep, numero)
    VALUES (?, ?, ?, ?, ?, ?)
");
$sql->bind_param("ssssss", $nome, $email, $cidade, $endereco, $cep, $telefone);
$sql->execute();

/* PEGAR ID CADASTRADO */
$cliente_id = $sql->insert_id;

/* SALVAR NA SESSION */
$_SESSION['cliente_id'] = $cliente_id;
$_SESSION['cliente_nome'] = $nome;
$_SESSION['cliente_email'] = $email;

/* VOLTAR PARA O PRODUTO */
header("Location: product-details.php?id=$produto_id&sucesso=1");
exit;
