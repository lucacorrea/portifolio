<?php
/**
 * Instalação inicial autorizada pelas credenciais do próprio MySQL.
 * Não publica senhas: gera um conexao.php privado fora do Git e uma senha Argon2id.
 * Após instalar, o formulário é automaticamente desabilitado.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/auth.php';

$existingConnection = obras_arquivo_conexao();
$reconfigureSupport = (($_GET['reconfigurar'] ?? $_POST['reconfigurar'] ?? '') === '1');
// Uma conta de suporte JÁ instalada não pode ter a senha substituída por
// uma pessoa anônima, mesmo que ela conheça a senha do MySQL.
if ($existingConnection !== null && $reconfigureSupport) {
    try {
        $installerActor = user();
        if (!$installerActor || $installerActor['role'] !== 'suporte') {
            http_response_code(403);
            exit('A recuperação remota de suporte exige uma sessão autenticada de suporte.');
        }
    } catch (Throwable $e) {
        error_log('[OBRAS360 INSTALAR] Recuperação não autorizada: banco indisponível.');
        http_response_code(403);
        exit('Recuperação indisponível; utilize o procedimento administrativo no servidor.');
    }
}
if ($existingConnection !== null && !$reconfigureSupport) {
    try {
        if ((int)db()->query('SELECT 1')->fetchColumn() === 1) {
            $ready = (int)db()->query("SELECT COUNT(*) FROM users WHERE username='suporte' AND role='suporte' AND active=1")->fetchColumn();
            if ($ready > 0) {
                header('Location: acesso.php', true, 303);
                exit;
            }
        }
    } catch (Throwable $e) {
        // A conexão anterior está quebrada. Recuperação exige comprovar a senha MySQL.
        error_log('[OBRAS360 INSTALAR] Conexão anterior não respondeu; oferecer recuperação autenticada por MySQL.');
    }
}
header('X-Robots-Tag: noindex, nofollow, noarchive');
$error = '';

function install_attempt_allowed(): bool {
    $ip = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $path = sys_get_temp_dir() . '/obras360_install_' . substr($ip, 0, 32);
    $f = @fopen($path, 'c+');
    if (!$f || !flock($f, LOCK_EX)) {
        if ($f) fclose($f);
        // Se a hospedagem bloquear arquivos temporários, limitar pela sessão.
        $item = $_SESSION['install_limit'] ?? ['started' => time(), 'count' => 0];
        if (!is_array($item) || time() - (int)($item['started'] ?? 0) >= 900)
            $item = ['started' => time(), 'count' => 0];
        $allowed = (int)$item['count'] < 5;
        if ($allowed) $item['count']++;
        $_SESSION['install_limit'] = $item;
        return $allowed;
    }
    $raw = stream_get_contents($f);
    $item = json_decode(is_string($raw) ? $raw : '', true);
    if (!is_array($item) || time() - (int)($item['started'] ?? 0) >= 900)
        $item = ['started' => time(), 'count' => 0];
    $allowed = (int)$item['count'] < 5;
    if ($allowed) $item['count']++;
    rewind($f); ftruncate($f, 0); fwrite($f, json_encode($item)); fflush($f);
    flock($f, LOCK_UN); fclose($f);
    return $allowed;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        verify_csrf();
        if (!install_attempt_allowed()) throw new RuntimeException('Muitas tentativas. Aguarde 15 minutos.');
        $dbPass = $_POST['db_password'] ?? null;
        $supportPass = $_POST['support_password'] ?? null;
        $supportConfirm = $_POST['support_confirm'] ?? null;
        if (!is_string($dbPass) || $dbPass === '' || strlen($dbPass) > 512)
            throw new RuntimeException('Informe a senha atual do banco MySQL.');
        if (!is_string($supportPass) || !is_string($supportConfirm)
            || !hash_equals($supportPass, $supportConfirm) || !password_secure($supportPass))
            throw new RuntimeException('Crie uma senha de suporte com pelo menos 8 caracteres, maiúscula, minúscula, número e símbolo; confira a confirmação.');
        if (!extension_loaded('pdo_mysql')) throw new RuntimeException('Ative a extensão pdo_mysql na Hostinger.');
        if (!defined('PASSWORD_ARGON2ID')) throw new RuntimeException('Sua versão do PHP não oferece suporte a Argon2id.');
        $pdo = obras_conectar_com_dados('u784961086_obras', 'u784961086_obras', $dbPass);
        $needed = ['users','login_limits','sequences','records','milestones',
            'stock_movements','protocol_forwards','attachments','audit_logs'];
        $existing = $pdo->query('SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE()')
            ->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff($needed, array_map('strtolower', $existing));
        if ($missing) throw new RuntimeException('Importe primeiro 01_CREATE_TABLE.sql e 02_INSERT_INICIAIS.sql no phpMyAdmin. Tabelas ausentes: ' . implode(', ', $missing));
        // Se o arquivo privado desapareceu mas o suporte já havia sido ativado,
        // NÃO permitir que qualquer visitante recrie sua senha com a senha MySQL.
        if ($existingConnection === null) {
            $st=$pdo->query("SELECT COUNT(*) FROM users WHERE role='suporte' AND active=1");
            if ((int)$st->fetchColumn() > 0) {
                throw new RuntimeException('Instalação já concluída. A recuperação de conexão deve ser feita pelo administrador diretamente na hospedagem.');
            }
        }

        $hash = password_hash($supportPass, PASSWORD_ARGON2ID, ['memory_cost'=>65536, 'time_cost'=>3,'threads'=>2]);
        if (!$hash) throw new RuntimeException('Não foi possível proteger a senha de suporte.');
        $config = [
            'DB_NAME' => 'u784961086_obras',
            'DB_USER' => 'u784961086_obras',
            'DB_PASS' => $dbPass,
        ];
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("SELECT id FROM users WHERE username='suporte' OR email='suporte@obras360.local' LIMIT 1 FOR UPDATE");
            $st->execute();
            $id = $st->fetchColumn();
            if ($id) {
                $pdo->prepare("UPDATE users SET username='suporte',email='suporte@obras360.local',
                    name='Suporte OBRAS360',role='suporte',password_hash=?,active=1,
                    must_change_password=0,failed_attempts=0,locked_until=NULL,
                    password_changed_at=NOW() WHERE id=?")->execute([$hash,$id]);
            } else {
                $pdo->prepare("INSERT INTO users(username,email,name,role,password_hash,active,
                    must_change_password,password_changed_at) VALUES
                    ('suporte','suporte@obras360.local','Suporte OBRAS360','suporte',?,1,0,NOW())")
                    ->execute([$hash]);
            }
            obras_gravar_conexao_privada($config, $existingConnection !== null);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        // Nunca guardar a senha do MySQL nem a do suporte na sessão.
        unset($dbPass, $supportPass, $supportConfirm, $hash, $config);
        header('Location: acesso.php?instalado=1', true, 303);
        exit;
    } catch (PDOException $e) {
        error_log('[OBRAS360 INSTALAR] PDO SQLSTATE=' . (string)$e->getCode()
            . ' driver=' . (string)($e->errorInfo[1] ?? '0'));
        $code = (int)($e->errorInfo[1] ?? 0);
        $error = match ($code) {
            1044, 1045 => 'DB-CREDENCIAIS: a senha ou o usuário do MySQL foi recusado.',
            1049 => 'DB-NOME: o banco não foi encontrado.',
            2002, 2003, 2005, 2006 => 'DB-CONEXAO: o servidor MySQL está indisponível.',
            default => 'Falha no MySQL. Verifique os logs PHP da hospedagem.',
        };
    } catch (Throwable $e) {
        $error = $e->getMessage();
        // Mensagem do banco não é mostrada ao público: a exceção PDO tem tratamento separado.
        error_log('[OBRAS360 INSTALAR] ' . get_class($e));
    }
}
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalar conexão · OBRAS360</title><link rel="stylesheet" href="assets/styles.css?v=<?= (int) (@filemtime(__DIR__ . '/assets/styles.css') ?: 1) ?>"><link rel="stylesheet" href="assets/interactive.css?v=<?= (int) (@filemtime(__DIR__ . '/assets/interactive.css') ?: 1) ?>">
<style>body{margin:0;background:#f3f7fa;font-family:Arial,sans-serif;color:#12334a}.shell{max-width:720px;margin:48px auto;background:white;box-shadow:0 12px 45px #12334a20;border-radius:18px;padding:34px}.brand{color:#078b7f;font-size:22px;font-weight:800}.shell h1{font-size:25px;margin:18px 0 6px}.muted{color:#69818d;line-height:1.6;font-size:14px}.box{background:#eaf6f4;border:1px solid #c9e8e2;border-radius:10px;padding:14px;color:#19655e}.error{background:#fff0ec;border:1px solid #edb0a2;border-radius:10px;padding:14px;color:#a33c2b}form{display:grid;gap:16px;margin-top:25px}label{font-size:13px;font-weight:700;display:grid;gap:7px}input,select{height:45px;padding:0 12px;border:1px solid #cbdce4;border-radius:8px;font-size:14px;background:white}button{background:#0c9182;color:white;border:0;border-radius:8px;height:48px;font-weight:800;cursor:pointer}button:hover{background:#076d66}@media(max-width:760px){.shell{margin:12px;padding:20px}}</style></head><body><main class="shell">
<div class="brand">◆ OBRAS 360</div><h1>Instalação inicial da conexão MySQL</h1>
<p class="muted">Instalação inicial ou recuperação de conexão danificada, antes do login. A senha do banco é usada para autenticar no MySQL e gravada em um arquivo PHP privado no servidor, <b>não no GitHub</b>. Não é necessário configurar servidor MySQL, APP_AUDIT_SALT ou .env.</p>
<div class="box"><b>1.</b> Primeiro importe os dois SQLs: <code>database/01_CREATE_TABLE.sql</code> e <code>database/02_INSERT_INICIAIS.sql</code>.<br><b>2.</b> Preencha os dados e crie a senha do suporte.<br><b>3.</b> Após instalar, esta página deixa de aceitar novas configurações.</div>
<?php if ($error): ?><p class="error" role="alert"><?=h($error)?></p><?php endif; ?>
<form method="post" action="instalar.php" autocomplete="off">
<?php if ($reconfigureSupport): ?><input type="hidden" name="reconfigurar" value="1"><?php endif; ?>
<input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
<label>Banco de dados<input value="u784961086_obras" readonly></label>
<label>Usuário MySQL<input value="u784961086_obras" readonly></label>
<label>Senha atual do banco MySQL<input name="db_password" type="password" maxlength="512" required autocomplete="off" placeholder="Senha do banco na Hostinger"></label>
<label>Nova senha de acesso para o usuário suporte<input name="support_password" type="password" minlength="8" maxlength="128" required autocomplete="new-password" placeholder="8 caracteres, letras, número e símbolo"></label>
<label>Confirmar senha do suporte<input name="support_confirm" type="password" minlength="8" maxlength="128" required autocomplete="new-password"></label>
<button type="submit">Testar MySQL, configurar e habilitar suporte</button>
</form><p class="muted">Para instalações novas, defina uma senha exclusiva para o suporte. Para redefinir a senha depois da instalação, entre com uma conta suporte ativa antes de usar <code>instalar.php?reconfigurar=1</code>. Sem acesso de suporte, a recuperação deve ser feita administrativamente no servidor. Remova o instalador após os testes.</p>
</main></body></html>
