<?php
include_once '../../dist/assets/conexao.php'; // Inclui o arquivo de conexão com o banco de dados

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bairro_nome = isset($_POST['bairro_nome']) ? trim($_POST['bairro_nome']) : '';

    if ($bairro_nome === '') {
        echo "<script>alert('O nome do bairro é obrigatório!');</script>";
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO bairros (nome) VALUES (:nome)");
        $stmt->bindParam(':nome', $bairro_nome);
        $stmt->execute();

        echo "<script>alert('Bairro cadastrado com sucesso!'); window.location.href='../cadastrarBairro.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao cadastrar bairro: " . addslashes($e->getMessage()) . "');</script>";
    }
} else {
    echo "<script>alert('Método de requisição inválido!');</script>";
}
?>