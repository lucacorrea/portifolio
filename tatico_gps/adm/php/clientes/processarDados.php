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

/*
|--------------------------------------------------------------------------
| CONFIGURAÇÃO DA API DO WHATSAPP
|--------------------------------------------------------------------------
| Configure sua API real aqui.
| Exemplo Evolution API:
| define('WHATSAPP_API_URL', 'https://SEU-DOMINIO/message/sendText/NOME_DA_INSTANCIA');
| define('WHATSAPP_API_KEY', 'SUA_API_KEY');
*/
if (!defined('WHATSAPP_API_URL')) {
    define('WHATSAPP_API_URL', '');
}
if (!defined('WHATSAPP_API_KEY')) {
    define('WHATSAPP_API_KEY', '');
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

function somenteNumeros(?string $valor): string
{
    return preg_replace('/\D+/', '', (string)$valor) ?? '';
}

function normalizarTelefoneWhatsapp(?string $telefone): string
{
    $telefone = somenteNumeros($telefone);

    if ($telefone === '') {
        return '';
    }

    if (strlen($telefone) === 10 || strlen($telefone) === 11) {
        $telefone = '55' . $telefone;
    }

    return $telefone;
}

function buscarConfiguracaoAutomacao(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            empresa_nome,
            pix_nome_recebedor,
            pix_tipo_chave,
            pix_chave
        FROM configuracoes_automacao
        ORDER BY id DESC
        LIMIT 1
    ");

    $cfg = $stmt->fetch(PDO::FETCH_ASSOC);

    return $cfg ?: [
        'empresa_nome' => 'Tático GPS',
        'pix_nome_recebedor' => '',
        'pix_tipo_chave' => '',
        'pix_chave' => '',
    ];
}

function montarMensagemBoasVindas(array $cliente, array $cfg): string
{
    $nome = trim((string)($cliente['nome'] ?? 'Cliente'));
    $empresa = trim((string)($cfg['empresa_nome'] ?? 'Tático GPS'));
    $pixNome = trim((string)($cfg['pix_nome_recebedor'] ?? ''));
    $pixTipo = trim((string)($cfg['pix_tipo_chave'] ?? ''));
    $pixChave = trim((string)($cfg['pix_chave'] ?? ''));
    $mensalidade = 'R$ ' . number_format((float)($cliente['mensalidade'] ?? 0), 2, ',', '.');
    $vencimento = str_pad((string)((int)($cliente['dia_vencimento'] ?? 0)), 2, '0', STR_PAD_LEFT);
    $formaPagamento = trim((string)($cliente['forma_pagamento'] ?? 'PIX'));

    $mensagem = "Olá, {$nome}! Seja bem-vindo(a) ao {$empresa}.\n\n";
    $mensagem .= "Seu cadastro foi realizado com sucesso em nosso sistema.\n\n";
    $mensagem .= "Resumo do seu cadastro:\n";
    $mensagem .= "• Mensalidade: {$mensalidade}\n";
    $mensagem .= "• Vencimento: dia {$vencimento}\n";
    $mensagem .= "• Forma de pagamento: {$formaPagamento}\n\n";

    if ($formaPagamento === 'PIX' && $pixChave !== '') {
        $mensagem .= "Dados para pagamento via PIX:\n";
        if ($pixNome !== '') {
            $mensagem .= "• Recebedor: {$pixNome}\n";
        }
        if ($pixTipo !== '') {
            $mensagem .= "• Tipo da chave: {$pixTipo}\n";
        }
        $mensagem .= "• Chave PIX: {$pixChave}\n\n";
    }

    $mensagem .= "Obrigado pela confiança.\n";
    $mensagem .= "Qualquer dúvida, estamos à disposição.";

    return $mensagem;
}

function registrarLogWhatsapp(
    PDO $pdo,
    int $clienteId,
    string $telefone,
    string $mensagem,
    string $statusEnvio,
    string $respostaApi = ''
): void {
    $sql = "INSERT INTO whatsapp_envios (
                cliente_id,
                telefone,
                mensagem,
                status_envio,
                resposta_api
            ) VALUES (
                :cliente_id,
                :telefone,
                :mensagem,
                :status_envio,
                :resposta_api
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
    $stmt->bindValue(':telefone', $telefone);
    $stmt->bindValue(':mensagem', $mensagem);
    $stmt->bindValue(':status_envio', $statusEnvio);
    $stmt->bindValue(':resposta_api', $respostaApi);
    $stmt->execute();
}

function enviarWhatsappAutomatico(string $telefone, string $mensagem): array
{
    if (WHATSAPP_API_URL === '' || WHATSAPP_API_KEY === '') {
        return [
            'ok' => false,
            'status' => 'nao_configurado',
            'resposta' => 'API do WhatsApp não configurada.',
        ];
    }

    $payload = [
        'number' => $telefone,
        'text' => $mensagem,
    ];

    $ch = curl_init(WHATSAPP_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . WHATSAPP_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return [
            'ok' => false,
            'status' => 'erro',
            'resposta' => 'cURL: ' . $curlError,
        ];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'ok' => true,
            'status' => 'enviado',
            'resposta' => (string)$response,
        ];
    }

    return [
        'ok' => false,
        'status' => 'falhou',
        'resposta' => 'HTTP ' . $httpCode . ' | ' . (string)$response,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarComErro('Requisição inválida.');
}

$acao = $_POST['acao'] ?? '';

if ($acao === 'salvar_cliente' || $acao === 'editar_cliente') {
    $id = (int)($_POST['id'] ?? 0);

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
        if ($acao === 'salvar_cliente') {
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
        } else {
            if ($id <= 0) {
                voltarComErro('ID do cliente inválido.');
            }

            $sql = "UPDATE clientes SET
                        nome = :nome,
                        cpf = :cpf,
                        telefone = :telefone,
                        email = :email,
                        endereco = :endereco,
                        mensalidade = :mensalidade,
                        dia_vencimento = :dia_vencimento,
                        forma_pagamento = :forma_pagamento,
                        qtd_veiculos = :qtd_veiculos,
                        tipo_veiculo = :tipo_veiculo,
                        status = :status,
                        mensagem_automatica = :mensagem_automatica,
                        whatsapp_principal = :whatsapp_principal,
                        observacoes = :observacoes
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        }

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

        $clienteId = $acao === 'salvar_cliente' ? (int)$pdo->lastInsertId() : $id;

        if ($acao === 'salvar_cliente') {
            $_SESSION['flash_sucesso'] = 'Cliente cadastrado com sucesso.';
        } else {
            $_SESSION['flash_sucesso'] = 'Cliente atualizado com sucesso.';
        }

        if ($acao === 'salvar_cliente' && $mensagem_automatica === 1) {
            $telefoneEnvio = $whatsapp_principal !== '' ? $whatsapp_principal : $telefone;
            $telefoneEnvio = normalizarTelefoneWhatsapp($telefoneEnvio);

            if ($telefoneEnvio !== '') {
                $cfg = buscarConfiguracaoAutomacao($pdo);

                $dadosCliente = [
                    'nome' => $nome,
                    'mensalidade' => $mensalidade,
                    'dia_vencimento' => $dia_vencimento,
                    'forma_pagamento' => $forma_pagamento,
                ];

                $mensagem = montarMensagemBoasVindas($dadosCliente, $cfg);
                $retorno = enviarWhatsappAutomatico($telefoneEnvio, $mensagem);

                registrarLogWhatsapp(
                    $pdo,
                    $clienteId,
                    $telefoneEnvio,
                    $mensagem,
                    $retorno['status'],
                    $retorno['resposta']
                );

                if ($retorno['ok']) {
                    $_SESSION['flash_sucesso'] = 'Cliente cadastrado com sucesso e mensagem enviada no WhatsApp.';
                    unset($_SESSION['flash_erro']);
                } else {
                    $_SESSION['flash_erro'] = 'Cliente cadastrado, mas a mensagem automática no WhatsApp não foi enviada.';
                }
            } else {
                $_SESSION['flash_erro'] = 'Cliente cadastrado, mas não foi possível enviar WhatsApp porque o telefone está vazio ou inválido.';
            }
        }

        header('Location: ../../clientes.php');
        exit;
    } catch (Throwable $e) {
        $erro = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo "<script>alert('Erro ao processar cliente: {$erro}'); history.back();</script>";
        exit;
    }
}

voltarComErro('Ação inválida.');

?>
