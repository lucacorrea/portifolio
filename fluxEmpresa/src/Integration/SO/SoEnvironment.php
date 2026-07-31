<?php
declare(strict_types=1);

namespace App\Integration\SO;

final class SoEnvironment
{
    private const KEYS=['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','DB_CHARSET'];
    private array $values=[];
    public function __construct(private readonly string $filePath) {}
    public static function resolveFilePath(string $projectRoot): string { $configured=getenv('SO_ENV_PATH'); return is_string($configured)&&trim($configured)!==''?trim($configured):dirname(rtrim($projectRoot,DIRECTORY_SEPARATOR),2).DIRECTORY_SEPARATOR.'configuracoes'.DIRECTORY_SEPARATOR.'so'.DIRECTORY_SEPARATOR.'.env'; }
    public function load(): void { if(!is_file($this->filePath)||!is_readable($this->filePath)) throw new SoIntegrationException('Arquivo de integração indisponível.'); $lines=file($this->filePath,FILE_IGNORE_NEW_LINES); if($lines===false) throw new SoIntegrationException('Arquivo de integração indisponível.'); foreach($lines as $line){$line=trim($line);if($line===''||str_starts_with($line,'#')||str_starts_with($line,';'))continue; $parts=explode('=',$line,2);if(count($parts)!==2)continue;[$key,$value]=$parts;$key=trim($key);if(!in_array($key,self::KEYS,true))continue;$value=trim($value);if(strlen($value)>=2&&in_array($value[0],['\"',"'"],true)&&$value[0]===$value[strlen($value)-1])$value=substr($value,1,-1);if(str_contains($value,"\0"))throw new SoIntegrationException('Arquivo de integração inválido.');$this->values[$key]=$value;} }
    public function get(string $key, ?string $default=null): ?string { return $this->values[$key]??$default; }
    public function require(string $key): string { $value=trim((string)($this->values[$key]??''));if($value==='')throw new SoIntegrationException('Configuração da integração incompleta.');return $value; }
}
