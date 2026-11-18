<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(403);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Auth;use App\Services\Export;
function check(bool $ok,string $msg): void {if(!$ok){fwrite(STDERR,'FALHOU: '.$msg."\n");exit(1);}echo 'OK: '.$msg."\n";}
$pass='UmaSenhaDeTeste_MuitoForte#2026';
$h1=Auth::hash($pass);$h2=Auth::hash($pass);
check($h1!==$h2,'duas senhas iguais geram hashes diferentes');
check(password_verify($pass,$h1)&&password_verify($pass,$h2),'password_verify funciona');
check(!password_verify('SenhaErrada',$h1),'senha incorreta falha');
check(Auth::validPassword($pass) && !Auth::validPassword('1234'),'validação de tamanho');
check(str_starts_with($h1,'$argon2id$'),'Argon2id é usado');
$method=new ReflectionMethod(Export::class,'zip');$zip=$method->invoke(null,['teste.txt'=>'arquivo de teste','xl/test.xml'=>'<a/>']);
check(str_starts_with($zip,"PK\x03\x04")&&str_contains($zip,"PK\x05\x06"),'ZIP OOXML gerado');
$f=tempnam(sys_get_temp_dir(),'obras');file_put_contents($f,$zip);
$zipPath=$f.'.zip';rename($f,$zipPath);
if (trim((string)shell_exec('command -v unzip'))!==''){
 $out=[];$code=0;exec('unzip -t '.escapeshellarg($zipPath).' 2>&1',$out,$code);check($code===0,'estrutura do ZIP aceita pelo unzip');
}
@unlink($zipPath);
echo "Testes rápidos de segurança concluídos.\n";
