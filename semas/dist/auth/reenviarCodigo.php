<?php
declare(strict_types=1);
session_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Conexão (ajuste o caminho se necessário)
require_once __DIR__ . '/../assets/conexao.php';

// (Opcional) fuso horário
date_default_timezone_set('America/Manaus');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('Requisição inválida.'); history.back();</script>";
    exit;
}

$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('Informe um e-mail válido.'); history.back();</script>";
    exit;
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1) Verifica se o e-mail existe
    $stmt = $pdo->prepare("SELECT id, nome FROM contas_acesso WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conta) {
        echo "<script>alert('E-mail não encontrado.'); history.back();</script>";
        exit;
    }

    $contaId = (int)$conta['id'];

    // 2) Invalida tokens anteriores ainda não usados
    $pdo->prepare("UPDATE senha_tokens SET used = 1 WHERE email = :email AND used = 0")
        ->execute([':email' => $email]);

    // 3) Gera novo código válido por 3 minutos
    //    (Se quiser permitir zeros à esquerda: str_pad(random_int(0,999999), 6, '0', STR_PAD_LEFT))
    $codigo = (string) random_int(100000, 999999);

    $now       = new DateTimeImmutable('now');
    $createdAt = $now->format('Y-m-d H:i:s');
    $expiresAt = $now->modify('+3 minutes')->format('Y-m-d H:i:s');

    // 4) Salva novo token
    $ins = $pdo->prepare("
        INSERT INTO senha_tokens (conta_id, email, codigo, used, created_at, expires_at)
        VALUES (:conta_id, :email, :codigo, 0, :created_at, :expires_at)
    ");
    $ins->execute([
        ':conta_id'   => $contaId,
        ':email'      => $email,
        ':codigo'     => $codigo,
        ':created_at' => $createdAt,
        ':expires_at' => $expiresAt
    ]);

    // 5) Envia e-mail HTML (mesmo estilo que você usou)
    $assunto     = "Novo Código de Verificação - Redefinição de Senha";
    $nomeUsuario = $conta['nome'] ?? 'Usuário';

    $mensagem = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container {
                max-width: 600px; margin: auto; padding: 20px;
                border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9;
            }
            .logo { text-align: center; margin-bottom: 20px; }
            p { font-size: 15px; line-height: 1.5; }
            .codigo {
                font-size: 26px; font-weight: bold; color: #2e7d32;
                background-color: #eafbe7; padding: 15px 30px; border-radius: 8px;
                display: inline-block; width: 100%; box-sizing: border-box;
                text-align: center; letter-spacing: 2px;
                box-shadow: 0 0 5px rgba(0,0,0,.08);
            }
            .footer { font-size: 13px; color: #999; text-align: center; margin-top: 24px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='logo'>
                <img src='https://semth.com.br/semas/dist/assets/images/logo/prefeitura.png'
                 width='150' alt='Prefeitura Logo'
                 style='display:block;margin:0 auto;max-width:100%;height:auto;'>
            </div>
            <p>Olá, <strong>" . htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8') . "</strong>!</p>
            <p>Geramos um <strong>novo código</strong> para redefinição da sua senha no sistema da<strong>SEMAS</strong>.</p>
            <p>Use o código abaixo. Ele é válido por <strong>3 minutos</strong>:</p>
            <div class='codigo'>{$codigo}</div>
            <p>Se você não solicitou, pode ignorar esta mensagem.</p>
            <div class='footer'>
                Este é um e-mail automático. Não responda diretamente a esta mensagem.<br>
                Suporte: suporte@coarimeular.gov.br
            </div>
        </div>
    </body>
    </html>
    ";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Coari Meu Lar <no-reply@coarimeular.gov.br>\r\n";
    $headers .= "Reply-To: no-reply@coarimeular.gov.br\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $ok = @mail($email, $assunto, $mensagem, $headers);

    if (!$ok) {
        echo "<script>alert('Erro ao enviar o e-mail. Tente novamente.'); history.back();</script>";
        exit;
    }

    // 6) Sucesso: alerta e volta para a tela de verificação
    //    A página verificarCodigo.php vai sincronizar o countdown com o novo expires_at
    echo "<script>
        alert('Novo código enviado. Você tem 3 minutos para usá-lo.');
        window.location.href = '../verificarCodigo.php?email=" . rawurlencode($email) . "';
    </script>";
    exit;

} catch (Throwable $e) {
    $msg = addslashes($e->getMessage());
    echo "<script>alert('Erro: {$msg}'); history.back();</script>";
    exit;
}

?>