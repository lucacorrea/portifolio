<?php
declare(strict_types=1);

namespace App\Integration\SO;

use PDO;
use PDOException;

final class SoDatabase
{
    private ?PDO $connection=null;
    public function __construct(private readonly SoEnvironment $environment) {}
    public function connection(): PDO { if($this->connection instanceof PDO)return $this->connection; if(!extension_loaded('pdo_mysql'))throw new SoIntegrationException('Driver indisponível.');$this->environment->load();$port=filter_var($this->environment->require('DB_PORT'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);$charset=$this->environment->get('DB_CHARSET','utf8mb4')??'utf8mb4';if($port===false||preg_match('/^[a-zA-Z0-9_]+$/',$charset)!==1)throw new SoIntegrationException('Configuração da integração inválida.');try{$this->connection=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$this->environment->require('DB_HOST'),$port,$this->environment->require('DB_DATABASE'),$charset),$this->environment->require('DB_USERNAME'),$this->environment->require('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);return $this->connection;}catch(PDOException){error_log('SO database connection failed.');throw new SoIntegrationException('Integração indisponível.');} }
}
