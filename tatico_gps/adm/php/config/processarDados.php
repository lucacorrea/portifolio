<?php
declare(strict_types=1);

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
if ($acao !== 'salvar_configuracao_automacao') {
    voltarComErro('Ação inválida.');
}

$empresa_nome = limparTexto($_POST['empresa_nome'] ?? '');
$empresa_cnpj = limparTexto($_POST['empresa_cnpj'] ?? '');
$empresa_telefone = limparTexto($_POST['empresa_telefone'] ?? '');
$empresa_email = limparTexto($_POST['empresa_email'] ?? '');
$empresa_endereco = limparTexto($_POST['empresa_endereco'] ?? '');

$automacao_ativa = isset($_POST['automacao_ativa']) ? 1 : 0;

$dia_vencimento_padrao = (int)($_POST['dia_vencimento_padrao'] ?? 10);
$mensalidade_padrao = (float)limparNumeroBrasil($_POST['mensalidade_padrao'] ?? '0');
$multa_atraso = (float)limparNumeroBrasil($_POST['multa_atraso'] ?? '0');
$juros_atraso = (float)limparNumeroBrasil($_POST['juros_atraso'] ?? '0');
$bloquear_apos_dias = (int)($_POST['bloquear_apos_dias'] ?? 7);

$pix_nome_recebedor = limparTexto($_POST['pix_nome_recebedor'] ?? '');
$pix_tipo_chave = limparTexto($_POST['pix_tipo_chave'] ?? '');
$pix_chave = limparTexto($_POST['pix_chave'] ?? '');

$mensagem_10_dias = limparTexto($_POST['mensagem_10_dias'] ?? '');
$mensagem_5_dias = limparTexto($_POST['mensagem_5_dias'] ?? '');
$mensagem_dia_vencimento = limparTexto($_POST['mensagem_dia_vencimento'] ?? '');
$mensagem_7_dias_atraso = limparTexto($_POST['mensagem_7_dias_atraso'] ?? '');

$status_cliente_apos_atraso = limparTexto($_POST['status_cliente_apos_atraso'] ?? 'Pendente');
$status_cliente_apos_bloqueio = limparTexto($_POST['status_cliente_apos_bloqueio'] ?? 'Bloqueado');

if ($empresa_nome === '') {
    voltarComErro('Informe o nome da empresa.');
}

if ($dia_vencimento_padrao < 1 || $dia_vencimento_padrao > 31) {
    voltarComErro('O dia de vencimento deve estar entre 1 e 31.');
}

if ($mensalidade_padrao <= 0) {
    voltarComErro('Informe uma mensalidade válida.');
}

if ($pix_nome_recebedor === '') {
    voltarComErro('Informe o nome do recebedor do PIX.');
}

if ($pix_tipo_chave === '') {
    voltarComErro('Informe o tipo da chave PIX.');
}

if ($pix_chave === '') {
    voltarComErro('Informe a chave PIX.');
}

if ($mensagem_10_dias === '' || $mensagem_5_dias === '' || $mensagem_dia_vencimento === '' || $mensagem_7_dias_atraso === '') {
    voltarComErro('Preencha todas as mensagens automáticas.');
}

if ($bloquear_apos_dias < 1) {
    voltarComErro('Informe uma quantidade válida de dias para bloqueio.');
}

try {
    $pdo->beginTransaction();

    $stmtCheck = $pdo->query("SELECT id FROM configuracoes_automacao ORDER BY id DESC LIMIT 1");
    $registro = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($registro && isset($registro['id'])) {
        $sql = "UPDATE configuracoes_automacao SET
                    empresa_nome = :empresa_nome,
                    empresa_cnpj = :empresa_cnpj,
                    empresa_telefone = :empresa_telefone,
                    empresa_email = :empresa_email,
                    empresa_endereco = :empresa_endereco,
                    automacao_ativa = :automacao_ativa,
                    dia_vencimento_padrao = :dia_vencimento_padrao,
                    mensalidade_padrao = :mensalidade_padrao,
                    multa_atraso = :multa_atraso,
                    juros_atraso = :juros_atraso,
                    bloquear_apos_dias = :bloquear_apos_dias,
                    pix_nome_recebedor = :pix_nome_recebedor,
                    pix_tipo_chave = :pix_tipo_chave,
                    pix_chave = :pix_chave,
                    mensagem_10_dias = :mensagem_10_dias,
                    mensagem_5_dias = :mensagem_5_dias,
                    mensagem_dia_vencimento = :mensagem_dia_vencimento,
                    mensagem_7_dias_atraso = :mensagem_7_dias_atraso,
                    status_cliente_apos_atraso = :status_cliente_apos_atraso,
                    status_cliente_apos_bloqueio = :status_cliente_apos_bloqueio
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', (int)$registro['id'], PDO::PARAM_INT);
    } else {
        $sql = "INSERT INTO configuracoes_automacao (
                    empresa_nome,
                    empresa_cnpj,
                    empresa_telefone,
                    empresa_email,
                    empresa_endereco,
                    automacao_ativa,
                    dia_vencimento_padrao,
                    mensalidade_padrao,
                    multa_atraso,
                    juros_atraso,
                    bloquear_apos_dias,
                    pix_nome_recebedor,
                    pix_tipo_chave,
                    pix_chave,
                    mensagem_10_dias,
                    mensagem_5_dias,
                    mensagem_dia_vencimento,
                    mensagem_7_dias_atraso,
                    status_cliente_apos_atraso,
                    status_cliente_apos_bloqueio
                ) VALUES (
                    :empresa_nome,
                    :empresa_cnpj,
                    :empresa_telefone,
                    :empresa_email,
                    :empresa_endereco,
                    :automacao_ativa,
                    :dia_vencimento_padrao,
                    :mensalidade_padrao,
                    :multa_atraso,
                    :juros_atraso,
                    :bloquear_apos_dias,
                    :pix_nome_recebedor,
                    :pix_tipo_chave,
                    :pix_chave,
                    :mensagem_10_dias,
                    :mensagem_5_dias,
                    :mensagem_dia_vencimento,
                    :mensagem_7_dias_atraso,
                    :status_cliente_apos_atraso,
                    :status_cliente_apos_bloqueio
                )";
        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindValue(':empresa_nome', $empresa_nome);
    $stmt->bindValue(':empresa_cnpj', $empresa_cnpj);
    $stmt->bindValue(':empresa_telefone', $empresa_telefone);
    $stmt->bindValue(':empresa_email', $empresa_email);
    $stmt->bindValue(':empresa_endereco', $empresa_endereco);
    $stmt->bindValue(':automacao_ativa', $automacao_ativa, PDO::PARAM_INT);
    $stmt->bindValue(':dia_vencimento_padrao', $dia_vencimento_padrao, PDO::PARAM_INT);
    $stmt->bindValue(':mensalidade_padrao', $mensalidade_padrao);
    $stmt->bindValue(':multa_atraso', $multa_atraso);
    $stmt->bindValue(':juros_atraso', $juros_atraso);
    $stmt->bindValue(':bloquear_apos_dias', $bloquear_apos_dias, PDO::PARAM_INT);
    $stmt->bindValue(':pix_nome_recebedor', $pix_nome_recebedor);
    $stmt->bindValue(':pix_tipo_chave', $pix_tipo_chave);
    $stmt->bindValue(':pix_chave', $pix_chave);
    $stmt->bindValue(':mensagem_10_dias', $mensagem_10_dias);
    $stmt->bindValue(':mensagem_5_dias', $mensagem_5_dias);
    $stmt->bindValue(':mensagem_dia_vencimento', $mensagem_dia_vencimento);
    $stmt->bindValue(':mensagem_7_dias_atraso', $mensagem_7_dias_atraso);
    $stmt->bindValue(':status_cliente_apos_atraso', $status_cliente_apos_atraso);
    $stmt->bindValue(':status_cliente_apos_bloqueio', $status_cliente_apos_bloqueio);

    $stmt->execute();

    $pdo->commit();

    echo "<script>window.location.href='../../configuracoes.php';</script>";
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $erro = addslashes($e->getMessage());
    echo "<script>alert('Erro ao salvar: {$erro}'); history.back();</script>";
    exit;
}