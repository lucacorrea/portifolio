<?php
/**
 * OBRAS360 - diagnostico temporario e independente do arquivo .env.
 * REMOVA este arquivo da hospedagem apos testar.
 * O formulario nao persiste a senha MySQL e usa apenas hosts predefinidos.
 */
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
if (PHP_SAPI !== 'cli' && !$https) {
    http_response_code(403);
    exit('Diagnóstico permitido apenas em HTTPS.');
}
if ($https) header('Strict-Transport-Security: max-age=31536000');
if (PHP_SAPI !== 'cli') {
    session_name('obras360_connection_diagnostic');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/obras/public','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function status(string $label, bool $ok, string $detail = ''): array {
    return ['label'=>$label,'ok'=>$ok,'detail'=>$detail];
}
/** Tentativas por IP, sem armazenar endereço IP nem senha em texto puro. */
function attempt_allowed(): bool {
    $now=time();
    $ipHash=hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $file=rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'obras360_diag_'.substr($ipHash,0,32).'.json';
    $fh=@fopen($file,'c+');
    if ($fh!==false && @flock($fh,LOCK_EX)) {
        $raw=stream_get_contents($fh);
        $item=json_decode($raw===false?'':$raw,true);
        if (!is_array($item) || !isset($item['start'],$item['count']) || $now-(int)$item['start']>=900) {
            $item=['start'=>$now,'count'=>0];
        }
        $allowed=(int)$item['count']<6;
        if ($allowed) ++$item['count'];
        rewind($fh); ftruncate($fh,0); fwrite($fh,json_encode($item)); fflush($fh);
        flock($fh,LOCK_UN); fclose($fh);
        return $allowed;
    }
    if (is_resource($fh)) fclose($fh);
    $item=$_SESSION['obras_diag_rate']??['start'=>$now,'count'=>0];
    if (!is_array($item)||$now-(int)($item['start']??0)>=900) $item=['start'=>$now,'count'=>0];
    $allowed=(int)($item['count']??0)<6;
    if($allowed)++$item['count'];
    $_SESSION['obras_diag_rate']=$item;
    return $allowed;
}
function mysql_error_code(PDOException $e): string {
    $driver=(int)($e->errorInfo[1]??0);
    if (str_contains(strtolower($e->getMessage()),'could not find driver')) return 'DB-DRIVER';
    return match ($driver) {
        1044,1045 => 'DB-CREDENCIAIS',
        1049 => 'DB-NOME',
        2002,2003,2005,2006,2013 => 'DB-CONEXAO',
        default => 'DB-SQL',
    };
}

$hosts=['srv1885.hstgr.io'=>'Servidor do hPanel (srv1885.hstgr.io)','localhost'=>'Servidor local (localhost)'];
$host='srv1885.hstgr.io';
$results=[];
$done=false;
$connectionOk=false;
$error='';
$envStatus='Não verificado';
$token=$_SESSION['obras_diag_csrf']??null;
if (!is_string($token) || strlen($token)<32) {
    $token=bin2hex(random_bytes(32));
    $_SESSION['obras_diag_csrf']=$token;
}

if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    $done=true;
    $host=($_POST['host']??'');
    $password=$_POST['db_password']??null;
    if (!is_string($host)||!isset($hosts[$host])) {
        $error='Selecione um servidor permitido.';
    } elseif (!is_string($password)||$password==='') {
        $error='Digite a senha atual do MySQL para executar o teste.';
    } elseif (strlen($password)>256) {
        $error='Senha inválida.';
    } elseif (!is_string($_POST['csrf']??null)||!hash_equals($token,$_POST['csrf'])) {
        $error='Sessão expirada. Recarregue a página e tente novamente.';
    } elseif (!attempt_allowed()) {
        http_response_code(429);
        $error='Limite de tentativas atingido. Aguarde 15 minutos.';
    } else {
        $results[]=status('PHP 8.2 ou superior',PHP_VERSION_ID>=80200,'Versão instalada: '.PHP_VERSION);
        $results[]=status('Extensão PDO',extension_loaded('PDO'));
        $results[]=status('Driver pdo_mysql',extension_loaded('pdo_mysql'));
        $results[]=status('Senhas com Argon2id',defined('PASSWORD_ARGON2ID'));
        $results[]=status('Extensão fileinfo',extension_loaded('fileinfo'));
        $results[]=status('Extensão OpenSSL',extension_loaded('openssl'));
        if (!extension_loaded('pdo_mysql')) {
            $error='DB-DRIVER: a extensão pdo_mysql não está habilitada no PHP da hospedagem.';
        } elseif (PHP_VERSION_ID<80200) {
            $error='A versão do PHP é inferior à exigida pelo OBRAS360.';
        } else {
            try {
                // Teste isolado do .env: usa somente os dados enviados agora, em memória.
                $dsn='mysql:host='.$host.';port=3306;dbname=u784961086_obras;charset=utf8mb4';
                $db=new PDO($dsn,'u784961086_obras',$password,[
                    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES=>false,
                    PDO::ATTR_TIMEOUT=>5,
                    PDO::ATTR_PERSISTENT=>false,
                ]);
                $db->query('SELECT 1')->fetchColumn();
                $connectionOk=true;
                $results[]=status('Conexão MySQL e SELECT 1',true,'A senha informada foi aceita pelo servidor.');
                $schema=(string)$db->query('SELECT DATABASE()')->fetchColumn();
                $results[]=status('Banco correto selecionado',$schema==='u784961086_obras');
                $required=['users','login_limits','sequences','records','milestones','stock_movements','protocol_forwards','attachments','audit_logs'];
                $found=$db->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
                $missing=array_values(array_diff($required,array_map('strtolower',$found)));
                $results[]=status('Nove tabelas exigidas pelo OBRAS360',count($missing)===0,$missing?'Ausentes: '.implode(', ',$missing):'Todas presentes.');
                if(!in_array('users',$missing,true)) {
                    $st=$db->prepare('SELECT username, role, active, must_change_password FROM users WHERE username = ? LIMIT 1');
                    $st->execute(['suporte']);
                    $support=$st->fetch();
                    $results[]=status('Usuário inicial suporte encontrado',(bool)$support);
                    if($support){
                        $results[]=status('Conta suporte ativa e com acesso total',(int)$support['active']===1&&$support['role']==='suporte');
                        $results[]=status('Troca inicial de senha prevista',(int)$support['must_change_password']===1,'Indicador informativo; pode ser não se a senha já foi alterada.');
                    }
                }
                $db=null;
            } catch(PDOException $e) {
                $code=mysql_error_code($e);
                $results[]=status('Conexão MySQL',false,'Falha: '.$code);
                $error=match($code){
                    'DB-CREDENCIAIS'=>'DB-CREDENCIAIS: o MySQL rejeitou a senha ou o usuário informado.',
                    'DB-NOME'=>'DB-NOME: banco de dados não encontrado neste servidor.',
                    'DB-CONEXAO'=>'DB-CONEXAO: servidor MySQL inacessível por este endereço/porta.',
                    'DB-DRIVER'=>'DB-DRIVER: PHP sem extensão MySQL.',
                    default=>'DB-SQL: falha SQL na verificação. Veja o log PHP da hospedagem.',
                };
                error_log('[OBRAS360 DIAGNOSTICO] '. $code . ' SQLSTATE=' . (string)$e->getCode());
            } catch(Throwable $e) {
                $error='Erro inesperado durante o diagnóstico. Verifique o log PHP do servidor.';
                error_log('[OBRAS360 DIAGNOSTICO] '.get_class($e));
            }
        }
        // Verifica SOMENTE a presença do arquivo. Não lê nem divulga credenciais.
        $base=dirname(__DIR__);
        $external=dirname(dirname($base)).'/obras360-private/config/.env';
        $legacy=$base.'/config/.env';
        if (is_file($external) && is_readable($external)) $envStatus='Arquivo privado encontrado fora de public_html.';
        elseif (is_file($legacy) && is_readable($legacy)) $envStatus='Arquivo encontrado dentro do projeto (mova-o para fora de public_html).';
        else $envStatus='Arquivo .env não encontrado nos caminhos esperados. Isso explica o erro DB-CONFIGURACAO, mesmo que o teste manual funcione.';
    }
    // A senha nunca é renderizada, registrada ou salva na sessão.
    unset($password,$_POST['db_password']);
}
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Teste de conexão · OBRAS360</title>
<style>
:root{color-scheme:light;font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;color:#10374b;background:#f4f8f9}
*{box-sizing:border-box}body{margin:0;min-height:100vh}.wrap{max-width:840px;margin:auto;padding:38px 20px 70px}
.header{background:linear-gradient(125deg,#0d293e,#0c776c);color:white;border-radius:20px;padding:27px 30px;margin-bottom:20px}
.brand{font-size:18px;font-weight:850;letter-spacing:-.04em}.brand strong{color:#74e2cb}.header p{color:#c9e4e4;font-size:13px;line-height:1.65;margin:8px 0 0}
h1{font-size:27px;letter-spacing:-.04em;margin:15px 0 8px}h2{font-size:17px;margin:0 0 12px}
.card{padding:27px 30px;background:white;border:1px solid #dce9eb;border-radius:18px;margin-bottom:16px;box-shadow:0 8px 25px #102f3c07}
.sub{color:#627e8b;font-size:13px;line-height:1.7}.row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
label{display:block;font-size:12px;font-weight:700;margin-bottom:8px;color:#536f7b}input,select{display:block;width:100%;padding:13px 14px;border:1px solid #cfdee2;border-radius:9px;font:inherit;font-size:14px;background:#fff;color:#173a49}
button{border:0;background:#139487;color:white;font-weight:800;border-radius:9px;padding:14px 20px;cursor:pointer;margin-top:18px}button:hover{background:#0b7d73}
.ok{color:#14745b}.no{color:#ae493a}.result{display:flex;align-items:flex-start;gap:12px;border-bottom:1px solid #edf2f3;padding:10px 0;font-size:13px}.result:last-child{border:0}
.symbol{font-weight:900;min-width:20px}.detail{font-size:12px;color:#647c85;padding-top:4px}.warning{padding:15px;background:#fff1ec;color:#973e2a;border:1px solid #f8d0c2;border-radius:10px;font-size:13px;line-height:1.55}
.notice{padding:15px;background:#eaf8f3;color:#296f65;border:1px solid #cfe8df;border-radius:10px;font-size:13px;line-height:1.65}
.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;overflow-wrap:anywhere}a{color:#087c70;font-size:13px;font-weight:700}
footer{font-size:12px;color:#79919a;margin-top:20px;text-align:center}
@media(max-width:640px){.row{grid-template-columns:1fr}.wrap{padding:18px 12px}.card{padding:20px}.header{padding:22px}h1{font-size:23px}}
</style></head><body><div class="wrap">
<div class="header"><div class="brand">◈ OBRAS<strong>360</strong></div><h1>Diagnóstico da conexão MySQL</h1><p>Teste separado da configuração .env. Nenhuma tabela será criada ou modificada. A senha é usada somente nesta solicitação.</p></div>
<section class="card"><h2>1. Informar credenciais para teste temporário</h2>
<p class="sub">Banco e usuário fixos: <span class="mono">u784961086_obras</span>. Use a senha <b>atual</b> do banco da Hostinger. A senha não é gravada no servidor pelo diagnóstico.</p>
<form action="conexao.php" method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?=esc($token)?>">
<div class="row"><div><label for="host">Servidor MySQL</label><select id="host" name="host">
<?php foreach($hosts as $v=>$title): ?><option value="<?=esc($v)?>" <?=$host===$v?'selected':''?>><?=esc($title)?></option><?php endforeach; ?>
</select></div><div><label for="pass">Senha atual do banco</label><input id="pass" type="password" name="db_password" maxlength="256" required placeholder="Digite somente para testar" autocomplete="off"></div></div>
<button type="submit">Executar teste de conexão</button>
</form></section>
<?php if($done): ?><section class="card"><h2>2. Resultado do diagnóstico</h2>
<?php if($error!==''): ?><div class="warning" role="alert"><?=esc($error)?></div><?php endif; ?>
<?php if($connectionOk): ?><div class="notice"><b>Conexão manual bem-sucedida!</b> PHP e MySQL conseguiram se comunicar sem depender do .env. Agora basta configurar o arquivo privado para que o login use os mesmos dados.</div><?php endif; ?>
<?php foreach($results as $item): ?><div class="result"><span class="symbol <?=$item['ok']?'ok':'no'?>"><?=$item['ok']?'✓':'×'?></span><div><b><?=esc($item['label'])?></b><?php if($item['detail']!==''): ?><div class="detail"><?=esc($item['detail'])?></div><?php endif; ?></div></div><?php endforeach; ?>
<?php if($envStatus!=='Não verificado'): ?><p class="sub"><b>Arquivo .env atual:</b> <?=esc($envStatus)?></p><?php endif; ?>
</section><?php endif; ?>
<section class="card"><h2>3. Próximo passo</h2><p class="sub">Se o teste manual funcionar e o login continuar com <span class="mono">DB-CONFIGURACAO</span>, a falha está na leitura das variáveis do banco, e não no usuário de suporte. Depois, crie o arquivo <span class="mono">obras360-private/config/.env</span> fora de <span class="mono">public_html</span>, conforme o guia do projeto.</p>
<div class="warning"><b>Segurança:</b> esta é uma página temporária de instalação. <b>Remova <span class="mono">obras/public/conexao.php</span> do servidor e do GitHub assim que concluir o teste.</b> Não compartilhe a senha em prints ou mensagens.</div>
<p><a href="acesso.php">← Voltar ao login</a></p></section>
<footer>OBRAS360 · Diagnóstico temporário · Somente testes de leitura</footer></div></body></html>
