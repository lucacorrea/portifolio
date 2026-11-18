<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\{Database,Env};
if (!is_file(BASE_PATH.'/.env')) {fwrite(STDERR,"FALHA: arquivo .env não encontrado.\n");exit(1);}
if (Env::get('DB_DRIVER')!=='mysql') {fwrite(STDERR,"FALHA: configure DB_DRIVER=mysql.\n");exit(1);}
if (in_array(Env::get('DB_PASSWORD'),['','SUBSTITUA_PELA_SENHA_NOVA_NO_SERVIDOR'],true)) {fwrite(STDERR,"FALHA: defina uma senha no .env privado.\n");exit(1);}
if (!extension_loaded('pdo_mysql')) {fwrite(STDERR,"FALHA: PDO MySQL indisponível.\n");exit(2);}
try {
 $db=Database::pdo();$db->query('SELECT 1')->fetchColumn();echo "OK: conexão MySQL estabelecida.\n";
 $tables=(int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn();
 echo "Tabelas existentes: {$tables}.\n";
} catch (Throwable $e) {error_log('OBRAS360 diag DB: '.get_class($e).' - '.$e->getCode());fwrite(STDERR,"FALHA: banco indisponível. Revise host, usuário, senha e criação no hPanel.\n");exit(3);}
