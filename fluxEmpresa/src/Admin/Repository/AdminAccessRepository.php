<?php
declare(strict_types=1);
namespace App\Admin\Repository;
use PDO;
final class AdminAccessRepository
{
 public function __construct(private readonly PDO $connection){}
 public function open(int $companyId,int $userId,string $ip): int{$s=$this->connection->prepare('INSERT INTO empresa_acessos_administrativos (empresa_id, usuario_id, ip, iniciado_em) VALUES (:empresa,:usuario,:ip,NOW())');$s->execute(['empresa'=>$companyId,'usuario'=>$userId,'ip'=>$ip]);return (int)$this->connection->lastInsertId();}
 public function closeOpenForUser(int $userId):void{$s=$this->connection->prepare('UPDATE empresa_acessos_administrativos SET encerrado_em=NOW() WHERE usuario_id=:id AND encerrado_em IS NULL');$s->execute(['id'=>$userId]);}
 public function close(int $id):void{$s=$this->connection->prepare('UPDATE empresa_acessos_administrativos SET encerrado_em=NOW() WHERE id=:id AND encerrado_em IS NULL');$s->execute(['id'=>$id]);}
 public function paginate(int $page,int $perPage):array{$s=$this->connection->prepare('SELECT a.id,a.empresa_id,a.usuario_id,a.ip,a.iniciado_em,a.encerrado_em,e.nome_fantasia,u.nome usuario FROM empresa_acessos_administrativos a LEFT JOIN empresas e ON e.id=a.empresa_id LEFT JOIN usuarios u ON u.id=a.usuario_id ORDER BY a.id DESC LIMIT :limit OFFSET :offset');$s->bindValue(':limit',$perPage,PDO::PARAM_INT);$s->bindValue(':offset',max(0,($page-1)*$perPage),PDO::PARAM_INT);$s->execute();return $s->fetchAll();}
}
