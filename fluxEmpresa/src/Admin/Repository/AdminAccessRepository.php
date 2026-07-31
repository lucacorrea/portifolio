<?php
declare(strict_types=1);
namespace App\Admin\Repository;
use PDO;
final class AdminAccessRepository
{
 public function __construct(private readonly PDO $connection){}
 public function open(int $companyId,int $userId,string $ip,string $reason,string $sessionKey): int{$s=$this->connection->prepare('INSERT INTO empresa_acessos_administrativos (empresa_id, usuario_id, ip, motivo, sessao_chave, iniciado_em) VALUES (:empresa,:usuario,:ip,:motivo,:sessao,NOW())');$s->execute(['empresa'=>$companyId,'usuario'=>$userId,'ip'=>$ip,'motivo'=>$reason,'sessao'=>$sessionKey]);return (int)$this->connection->lastInsertId();}
 public function closeOpenForSession(int $userId,string $sessionKey):void{$s=$this->connection->prepare('UPDATE empresa_acessos_administrativos SET encerrado_em=NOW() WHERE usuario_id=:id AND sessao_chave=:sessao AND encerrado_em IS NULL');$s->execute(['id'=>$userId,'sessao'=>$sessionKey]);}
 /** @return array<string,mixed>|null */
 public function findOpenAuthorized(int $accessId,int $userId,int $companyId,string $sessionBindingHash):?array
 {
  $s=$this->connection->prepare("SELECT a.id,a.empresa_id,a.usuario_id,a.iniciado_em,e.uuid,COALESCE(NULLIF(e.nome_fantasia,''),e.razao_social) empresa_nome FROM empresa_acessos_administrativos a INNER JOIN empresas e ON e.id=a.empresa_id AND e.status='ativo' WHERE a.id=:access_id AND a.usuario_id=:user_id AND a.empresa_id=:company_id AND a.sessao_chave=:session_key AND a.encerrado_em IS NULL AND a.iniciado_em>=DATE_SUB(NOW(), INTERVAL 4 HOUR) LIMIT 1");
  $s->execute(['access_id'=>$accessId,'user_id'=>$userId,'company_id'=>$companyId,'session_key'=>$sessionBindingHash]);
  $row=$s->fetch();
  return is_array($row)?$row:null;
 }
 public function closeOwned(int $accessId,int $userId,string $sessionBindingHash):void
 {
  $s=$this->connection->prepare('UPDATE empresa_acessos_administrativos SET encerrado_em=NOW() WHERE id=:access_id AND usuario_id=:user_id AND sessao_chave=:session_key AND encerrado_em IS NULL');
  $s->execute(['access_id'=>$accessId,'user_id'=>$userId,'session_key'=>$sessionBindingHash]);
 }
 public function closeForUserAndCompany(int $accessId,int $userId,int $companyId):void
 {
  $s=$this->connection->prepare('UPDATE empresa_acessos_administrativos SET encerrado_em=NOW() WHERE id=:access_id AND usuario_id=:user_id AND empresa_id=:company_id AND encerrado_em IS NULL');
  $s->execute(['access_id'=>$accessId,'user_id'=>$userId,'company_id'=>$companyId]);
 }
 public function paginate(int $page,int $perPage):array{$total=(int)$this->connection->query('SELECT COUNT(*) FROM empresa_acessos_administrativos')->fetchColumn();$s=$this->connection->prepare('SELECT a.id,a.empresa_id,a.usuario_id,a.ip,a.motivo,a.iniciado_em,a.encerrado_em,e.nome_fantasia,e.razao_social,u.nome usuario FROM empresa_acessos_administrativos a LEFT JOIN empresas e ON e.id=a.empresa_id LEFT JOIN usuarios u ON u.id=a.usuario_id ORDER BY a.id DESC LIMIT :limit OFFSET :offset');$s->bindValue(':limit',$perPage,PDO::PARAM_INT);$s->bindValue(':offset',max(0,($page-1)*$perPage),PDO::PARAM_INT);$s->execute();return ['items'=>$s->fetchAll(),'total'=>$total];}
}
