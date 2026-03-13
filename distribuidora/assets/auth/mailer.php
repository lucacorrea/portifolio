<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('plhb_mailer_bootstrap')) {
    function plhb_mailer_bootstrap(): void
    {
        if (class_exists(PHPMailer::class)) {
            return;
        }

        $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (is_file($vendorAutoload)) {
            require_once $vendorAutoload;
            return;
        }

        $manualBase = dirname(__DIR__) . '/libs/PHPMailer/src/';
        $files = [
            $manualBase . 'Exception.php',
            $manualBase . 'PHPMailer.php',
            $manualBase . 'SMTP.php',
        ];

        foreach ($files as $file) {
            if (!is_file($file)) {
                throw new RuntimeException(
                    'PHPMailer não encontrado. Instale via Composer ou copie para /assets/libs/PHPMailer/src/'
                );
            }
            require_once $file;
        }
    }
}

if (!function_exists('plhb_mail_config')) {
    function plhb_mail_config(): array
    {
        $configFile = dirname(__DIR__) . '/config/mail.php';
        if (!is_file($configFile)) {
            throw new RuntimeException('Arquivo de configuração de e-mail não encontrado.');
        }

        $config = require $configFile;
        if (!is_array($config)) {
            throw new RuntimeException('Configuração de e-mail inválida.');
        }

        return $config;
    }
}

if (!function_exists('plhb_make_mailer')) {
    function plhb_make_mailer(): PHPMailer
    {
        plhb_mailer_bootstrap();
        $cfg = plhb_mail_config();

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->isHTML(true);

        $mail->Host = (string)($cfg['host'] ?? '');
        $mail->Port = (int)($cfg['port'] ?? 587);
        $mail->Timeout = (int)($cfg['timeout'] ?? 20);
        $mail->SMTPAuth = (bool)($cfg['smtp_auth'] ?? true);

        if ($mail->SMTPAuth) {
            $mail->Username = (string)($cfg['username'] ?? '');
            $mail->Password = (string)($cfg['password'] ?? '');
        }

        $encryption = strtolower(trim((string)($cfg['encryption'] ?? 'tls')));

        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $fromEmail = trim((string)($cfg['from_email'] ?? ''));
        $fromName  = trim((string)($cfg['from_name'] ?? 'Sistema'));

        if ($fromEmail === '') {
            throw new RuntimeException('Defina from_email em assets/config/mail.php');
        }

        $mail->setFrom($fromEmail, $fromName);

        $replyEmail = trim((string)($cfg['reply_to_email'] ?? ''));
        $replyName  = trim((string)($cfg['reply_to_name'] ?? $fromName));

        if ($replyEmail !== '') {
            $mail->addReplyTo($replyEmail, $replyName);
        }

        return $mail;
    }
}

if (!function_exists('enviar_codigo_recuperacao')) {
    function enviar_codigo_recuperacao(string $nome, string $email, string $codigo): void
    {
        $mail = plhb_make_mailer();

        $nomeExibicao = trim($nome) !== '' ? $nome : $email;
        $codigoHtml = implode(' ', str_split($codigo, 3));

        $mail->addAddress($email, $nomeExibicao);
        $mail->Subject = 'Codigo de recuperacao de senha - Painel da Distribuidora PLHB';

        $mail->Body = '
            <div style="font-family:Arial,Helvetica,sans-serif;background:#f6f8fb;padding:24px;color:#111827">
                <div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden">
                    <div style="padding:24px 28px;background:linear-gradient(90deg,#4f46e5,#5b6df6);color:#ffffff">
                        <h1 style="margin:0;font-size:22px;">Recuperacao de senha</h1>
                        <p style="margin:8px 0 0;font-size:14px;opacity:.95;">Painel da Distribuidora PLHB</p>
                    </div>

                    <div style="padding:28px">
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
                            Ola, <strong>' . htmlspecialchars($nomeExibicao, ENT_QUOTES | ENT_SUBSTITUTE) . '</strong>.
                        </p>

                        <p style="margin:0 0 18px;font-size:15px;line-height:1.7;">
                            Recebemos uma solicitacao para redefinir sua senha. Use o codigo abaixo:
                        </p>

                        <div style="margin:20px 0;padding:18px;border:1px dashed #c7d2fe;background:#eef2ff;border-radius:14px;text-align:center;">
                            <span style="font-size:34px;letter-spacing:6px;font-weight:700;color:#4338ca;">' . htmlspecialchars($codigoHtml, ENT_QUOTES | ENT_SUBSTITUTE) . '</span>
                        </div>

                        <p style="margin:0 0 10px;font-size:14px;line-height:1.7;color:#6b7280;">
                            Esse codigo expira em <strong>5 minutos</strong>.
                        </p>

                        <p style="margin:0;font-size:14px;line-height:1.7;color:#6b7280;">
                            Se voce nao fez essa solicitacao, ignore este e-mail.
                        </p>
                    </div>
                </div>
            </div>
        ';

        $mail->AltBody =
            "Codigo de recuperacao de senha - Painel da Distribuidora PLHB\n\n" .
            "Seu codigo de recuperacao e: {$codigo}\n" .
            "Validade: 5 minutos.\n\n" .
            "Se voce nao solicitou isso, ignore este e-mail.";

        $mail->send();
    }
}

?>