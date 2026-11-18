<?php
declare(strict_types=1);
/** Uso: php bin/setup.php [--demo] [--email=admin@...] [--name=...] */
if (PHP_SAPI!=='cli'){http_response_code(403);exit('Somente CLI.');}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\{Auth,Database,Env,Repository};
if (!is_file(BASE_PATH.'/.env')) {fwrite(STDERR,"ERRO: copie .env.example para .env e ajuste a configuração.\n");exit(1);}
$demo=in_array('--demo',$argv,true);$options=[];
if ($demo && !in_array(Env::get('APP_ENV','local'),['local','development','test'],true)) {fwrite(STDERR,"ERRO: a carga --demo só é permitida em ambiente local ou de teste.\n");exit(1);}
foreach($argv as $argument){if (str_starts_with($argument,'--')&&str_contains($argument,'=')){[$k,$v]=explode('=',substr($argument,2),2);$options[$k]=$v;}}
try {
 $db=Database::pdo();$driver=Database::driver();$schema=BASE_PATH.'/database/'.$driver.'.sql';
 echo "OBRAS360 · Instalação (".$driver.")\n";
 $sql=file_get_contents($schema);
 // O arquivo de migração contém apenas instruções independentes, sem procedimentos SQL.
 foreach (preg_split('/;\s*(?=\n|$)/',$sql) as $statement) {
  $statement=trim($statement);
  if ($statement===''||preg_match('/^\s*--[^\n]*$/',$statement))continue;
  $db->exec($statement);
 }
 echo "Tabelas verificadas.\n";
 $users=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
 if (!$users) {
  $name=trim((string)($options['name']??'Administrador inicial'));
  $email=strtolower(trim((string)($options['email']??'')));
  if ($email==='') throw new RuntimeException('Informe --email=seu.email.institucional para criar o primeiro administrador.');
  if (!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('E-mail administrativo inválido.');
  $password=Auth::generatePassword();
  $db->prepare('INSERT INTO users(name,email,password_hash,role,active,must_change_password) VALUES (?,?,?,?,1,1)')->execute([$name,$email,Auth::hash($password),'admin']);
  echo "\n=== CREDENCIAL INICIAL (MOSTRADA APENAS AGORA) ===\n";
  echo "E-mail: {$email}\nSenha temporária: {$password}\n";
  echo "No primeiro acesso, cadastre sua senha pessoal.\n===============================================\n\n";
 } else echo "Usuários já cadastrados: {$users}. Conta existente preservada.\n";
 if ($demo){
  $count=0;foreach(['works','service_orders','inspections','documents','measurements','materials','buildings','protocols','milestones'] as $table) $count+=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
  if (!$count){require __DIR__.'/seed-demo.php';echo "Dados fictícios de demonstração importados.\n";}
  else echo "A carga demonstrativa foi ignorada: há obras registradas.\n";
 }
 echo "Pronto. Inicie: php -S 127.0.0.1:8000 -t public\n";
} catch(Throwable $e){fwrite(STDERR,"Erro na instalação: ".$e->getMessage()."\n");exit(1);}
