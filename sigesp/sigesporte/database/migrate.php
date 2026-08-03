<?php
declare(strict_types=1);
$config=parse_ini_file(dirname(__DIR__).'/.env', false, INI_SCANNER_RAW) ?: parse_ini_file(dirname(__DIR__).'/.env.example', false, INI_SCANNER_RAW);
try{$pdo=new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4',$config['DB_HOST'],$config['DB_PORT']),$config['DB_USERNAME'],$config['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$pdo->exec(file_get_contents(__DIR__.'/schema.sql'));echo "Schema SIGESP aplicado com sucesso.\n";}catch(Throwable $e){fwrite(STDERR,"Migração não executada: habilite pdo_mysql e confira o banco.\n");exit(1);}
