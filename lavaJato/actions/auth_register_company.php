<?php
// autoErp/actions/AuthRegisterCompany.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

function go_err(string $msg): void {
  header('Location: ../criarConta.php?err=1&msg=' . urlencode($msg));
  exit;
}
function go_ok(string $msg = ''): void {
  $q = $msg !== '' ? '&msg=' . urlencode($msg) : '';
  header('Location: ../criarConta.php?ok=1' . $q);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Location: ../criarConta.php'); exit;
}

// CSRF
$csrfForm = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['csrf_register_company']) || !hash_equals($_SESSION['csrf_register_company'], $csrfForm)) {
  go_err('Falha de segurança. Atualize a página.');
}

// Honeypot
if (!empty($_POST['website'] ?? '')) {
  go_ok(); // finge sucesso
}

/* ========= Conexão ========= */
require_once __DIR__ . '/../conexao/conexao.php'; // deve definir $pdo

if (!isset($pdo) || !($pdo instanceof PDO)) {
  go_err('Conexão indisponível.');
}

$nome   = trim((string)($_POST['proprietario_nome']  ?? ''));
$email  = strtolower(trim((string)($_POST['proprietario_email'] ?? '')));
$cnpj   = preg_replace('/\D+/', '', (string)($_POST['cnpj'] ?? ''));
$aceite = isset($_POST['aceite']) ? 1 : 0;

$senha1 = trim((string)($_POST['proprietario_senha']  ?? ''));
$senha2 = trim((string)($_POST['proprietario_senha2'] ?? ''));

/* ========= Validações ========= */
if (!$aceite) go_err('Você precisa aceitar os Termos e a Política.');
if (mb_strlen($nome) < 3) go_err('Informe o nome completo.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) go_err('Informe um e-mail válido.');
if (!preg_match('/^\d{14}$/', $cnpj)) go_err('CNPJ deve ter 14 dígitos numéricos.');

if ($senha1 === '' || $senha2 === '') go_err('Informe e confirme a senha.');
if ($senha1 !== $senha2) go_err('As senhas precisam ser iguais.');
if (mb_strlen($senha1) < 3) go_err('A senha deve ter no mínimo 3 caracteres.');

$hash = password_hash($senha1, PASSWORD_DEFAULT);
if ($hash === false) go_err('Falha ao processar senha.');

/* ========= Helpers ========= */
function base_url_auth(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
  if ($base === '.' || $base === '/') $base = '';
  return rtrim($scheme . '://' . $host . $base, '/');
}
function fmt_cnpj(string $n): string {
  $n = preg_replace('/\D+/', '', $n);
  if (strlen($n) !== 14) return $n;
  return substr($n,0,2).'.'.substr($n,2,3).'.'.substr($n,5,3).'/'.substr($n,8,4).'-'.substr($n,12,2);
}

/* ========= Mail config (opcional) ========= */
$enviarEmailDisponivel = false;
$pathMailCandidates = [
  realpath(__DIR__ . '/../config/mail.php'),
  realpath(__DIR__ . '/../../config/mail.php'),
];
foreach ($pathMailCandidates as $pm) {
  if ($pm && file_exists($pm)) {
    require_once $pm;
    if (function_exists('enviar_email')) $enviarEmailDisponivel = true;
    break;
  }
}

/* ========= Detecta coluna de senha ========= */
$temColSenha = false;
try {
  $pdo->query("SELECT proprietario_senha_hash FROM solicitacoes_empresas_peca LIMIT 0");
  $temColSenha = true;
} catch (Throwable $e) {
  $temColSenha = false;
}

try {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // já tem pendente igual? (evita spam)
  $st = $pdo->prepare("
    SELECT id
      FROM solicitacoes_empresas_peca
     WHERE status = 'pendente'
       AND proprietario_email = :pe
       AND cnpj = :c
     ORDER BY id DESC
     LIMIT 1
  ");
  $st->execute([':pe' => $email, ':c' => $cnpj]);
  $ja = $st->fetch(PDO::FETCH_ASSOC);

  if ($ja) {
    go_ok('Sua solicitação já está pendente de aprovação.');
  }

  $nomeFantasiaVazio = '';

  if ($temColSenha) {
    $ins = $pdo->prepare("
      INSERT INTO solicitacoes_empresas_peca
        (nome_fantasia, cnpj, telefone, email, proprietario_nome, proprietario_email, proprietario_senha_hash, status, token_aprovacao, criado_em)
      VALUES
        (:nf, :cnpj, NULL, NULL, :pn, :pe, :ph, 'pendente', NULL, NOW())
    ");
    $ins->execute([
      ':nf'   => $nomeFantasiaVazio,
      ':cnpj' => $cnpj,
      ':pn'   => $nome,
      ':pe'   => $email,
      ':ph'   => $hash,
    ]);
  } else {
    $ins = $pdo->prepare("
      INSERT INTO solicitacoes_empresas_peca
        (nome_fantasia, cnpj, telefone, email, proprietario_nome, proprietario_email, status, token_aprovacao, criado_em)
      VALUES
        (:nf, :cnpj, NULL, NULL, :pn, :pe, 'pendente', NULL, NOW())
    ");
    $ins->execute([
      ':nf'   => $nomeFantasiaVazio,
      ':cnpj' => $cnpj,
      ':pn'   => $nome,
      ':pe'   => $email,
    ]);
  }

  $solicitacaoId = (int)$pdo->lastInsertId();

  // e-mail admin
  $adminEmail = 'suportelucacorrea@gmail.com';
  $agoraBr = date('d/m/Y H:i');
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido';

  $base = base_url_auth();
  $linkAdm = $base . '/../admin/pages/solicitacao.php?status=pendente';

  $assunto = 'Nova solicitação de empresa — #' . $solicitacaoId;

  $htmlMsg = '
    <div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#1f2937;background:#ffffff;padding:16px">
      <div style="max-width:700px;margin:0 auto;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
        <div style="background:#0ea5e9;color:#fff;padding:14px 18px">
          <h2 style="margin:0;font-size:18px;font-weight:700;">AutoERP — Nova solicitação de empresa</h2>
        </div>
        <div style="padding:18px">
          <p style="margin:0 0 10px">Uma nova solicitação de cadastro foi enviada.</p>
          <ul style="margin:0 0 12px;padding-left:18px">
            <li><strong>ID:</strong> ' . htmlspecialchars((string)$solicitacaoId, ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Proprietário:</strong> ' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>E-mail:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>CNPJ:</strong> ' . htmlspecialchars(fmt_cnpj($cnpj), ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Data/Hora:</strong> ' . htmlspecialchars($agoraBr, ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>IP:</strong> ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Navegador:</strong> ' . htmlspecialchars($ua, ENT_QUOTES, 'UTF-8') . '</li>
          </ul>
          <p style="margin:10px 0 16px">
            <a href="' . htmlspecialchars($linkAdm, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0ea5e9;color:#fff;text-decoration:none;padding:10px 14px;border-radius:6px;font-weight:600">
              Abrir solicitações pendentes
            </a>
          </p>
          <p style="margin:0;color:#64748b;font-size:12px">Este e-mail foi enviado automaticamente pelo AutoERP.</p>
        </div>
      </div>
    </div>
  ';

  if ($enviarEmailDisponivel) {
    @enviar_email($adminEmail, $assunto, $htmlMsg);
  } else {
    $from = 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: AutoERP <{$from}>\r\n";
    @mail($adminEmail, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $htmlMsg, $headers);
  }

  // Regenera CSRF após sucesso
  $_SESSION['csrf_register_company'] = bin2hex(random_bytes(32));

  go_ok();
} catch (Throwable $e) {
  go_err('Erro ao salvar sua solicitação.');
}
