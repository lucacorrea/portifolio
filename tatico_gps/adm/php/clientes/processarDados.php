<?php
declare(strict_types=1);

session_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../conexao.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "<script>alert('Erro: conexão com o banco não foi carregada.'); history.back();</script>";
    exit;
}

function voltarComErro(string $mensagem): never
{
    $mensagem = htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8');
    echo "<script>alert('{$mensagem}'); history.back();</script>";
    exit;
}

function limparTexto(?string $valor): string
{
    return trim((string)$valor);
}

function limparNumeroBrasil(?string $valor): string
{
    $valor = trim((string)$valor);
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    $valor = preg_replace('/[^\d.\-]/', '', $valor);
    return $valor !== '' ? $valor : '0';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarComErro('Requisição inválida.');
}

$acao = $_POST['acao'] ?? '';

if ($acao === 'salvar_cliente') {
    $nome = limparTexto($_POST['nome'] ?? '');
    $cpf = limparTexto($_POST['cpf'] ?? '');
    $telefone = limparTexto($_POST['telefone'] ?? '');
    $email = limparTexto($_POST['email'] ?? '');
    $endereco = limparTexto($_POST['endereco'] ?? '');

    $mensalidade = (float) limparNumeroBrasil($_POST['mensalidade'] ?? '0');
    $dia_vencimento = (int) ($_POST['dia_vencimento'] ?? 0);
    $forma_pagamento = limparTexto($_POST['forma_pagamento'] ?? 'PIX');

    $qtd_veiculos = (int) ($_POST['qtd_veiculos'] ?? 1);
    $tipo_veiculo = limparTexto($_POST['tipo_veiculo'] ?? '');
    $status = limparTexto($_POST['status'] ?? 'Ativo');
    $mensagem_automatica = (int) ($_POST['mensagem_automatica'] ?? 1);
    $whatsapp_principal = limparTexto($_POST['whatsapp_principal'] ?? '');
    $observacoes = limparTexto($_POST['observacoes'] ?? '');

    if ($nome === '') {
        voltarComErro('Informe o nome do cliente.');
    }

    if ($mensalidade <= 0) {
        voltarComErro('Informe uma mensalidade válida.');
    }

    if ($dia_vencimento < 1 || $dia_vencimento > 31) {
        voltarComErro('Informe um dia de vencimento válido.');
    }

    if ($qtd_veiculos < 1) {
        $qtd_veiculos = 1;
    }

    if (!in_array($status, ['Ativo', 'Pendente', 'Bloqueado', 'Inativo'], true)) {
        $status = 'Ativo';
    }

    if (!in_array($forma_pagamento, ['PIX', 'Dinheiro', 'Cartão', 'Boleto', 'Transferência'], true)) {
        $forma_pagamento = 'PIX';
    }

    try {
        $sql = "INSERT INTO clientes (
                    nome,
                    cpf,
                    telefone,
                    email,
                    endereco,
                    mensalidade,
                    dia_vencimento,
                    forma_pagamento,
                    qtd_veiculos,
                    tipo_veiculo,
                    status,
                    mensagem_automatica,
                    whatsapp_principal,
                    observacoes
                ) VALUES (
                    :nome,
                    :cpf,
                    :telefone,
                    :email,
                    :endereco,
                    :mensalidade,
                    :dia_vencimento,
                    :forma_pagamento,
                    :qtd_veiculos,
                    :tipo_veiculo,
                    :status,
                    :mensagem_automatica,
                    :whatsapp_principal,
                    :observacoes
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':nome', $nome);
        $stmt->bindValue(':cpf', $cpf);
        $stmt->bindValue(':telefone', $telefone);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':endereco', $endereco);
        $stmt->bindValue(':mensalidade', $mensalidade);
        $stmt->bindValue(':dia_vencimento', $dia_vencimento, PDO::PARAM_INT);
        $stmt->bindValue(':forma_pagamento', $forma_pagamento);
        $stmt->bindValue(':qtd_veiculos', $qtd_veiculos, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_veiculo', $tipo_veiculo);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':mensagem_automatica', $mensagem_automatica, PDO::PARAM_INT);
        $stmt->bindValue(':whatsapp_principal', $whatsapp_principal);
        $stmt->bindValue(':observacoes', $observacoes);
        $stmt->execute();

        $_SESSION['flash_sucesso'] = 'Cliente cadastrado com sucesso.';
        header('Location: ../../clientes.php');
        exit;
    } catch (Throwable $e) {
        $erro = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo "<script>alert('Erro ao salvar cliente: {$erro}'); history.back();</script>";
        exit;
    }
}

voltarComErro('Ação inválida.');

?>