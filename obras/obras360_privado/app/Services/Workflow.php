<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database,Repository,Audit};
use InvalidArgumentException;
final class Workflow {
 public static function moveStock(int $materialId,string $type,float $amount,string $note,int $userId): void {
  if (!in_array($type,['Entrada','Saída'],true)||!is_finite($amount)||$amount<0.01||$amount>100000000) throw new InvalidArgumentException('Movimentação inválida.');
  if (strlen($note)>400) throw new InvalidArgumentException('Observação muito longa.');
  $amount=round($amount,2);
  $db=Database::pdo();$db->beginTransaction();
  try {
   $sql='SELECT * FROM materials WHERE id=? AND deleted_at IS NULL'.(Database::driver()==='mysql'?' FOR UPDATE':'');
   $q=$db->prepare($sql);$q->execute([$materialId]);$m=$q->fetch();if (!$m) throw new InvalidArgumentException('Material não encontrado.');
   $new=(float)$m['quantity']+($type==='Entrada'?$amount:-$amount);
   if ($new < -0.00001) throw new InvalidArgumentException('Saldo insuficiente para esta saída.');
   $db->prepare('UPDATE materials SET quantity=?,updated_at=?,updated_by=? WHERE id=?')->execute([round($new,2),gmdate('Y-m-d H:i:s'),$userId,$materialId]);
   $db->prepare('INSERT INTO stock_movements (material_id,movement_type,amount,note,user_id) VALUES (?,?,?,?,?)')->execute([$materialId,$type,$amount,$note,$userId]);
   $db->commit();Audit::log('stock_movement','materiais',$materialId,['type'=>$type,'amount'=>$amount]);
  } catch(\Throwable $e){$db->rollBack();throw $e;}
 }
 public static function forwardProtocol(int $protocolId,string $to,string $note,int $userId): void {
  $to=trim($to);$note=trim($note);
  if ($to===''||strlen($to)>150||strlen($note)>3000) throw new InvalidArgumentException('Informe o destino e uma observação válida.');
  $db=Database::pdo();$db->beginTransaction();
  try {
   $q=$db->prepare('SELECT * FROM protocols WHERE id=? AND deleted_at IS NULL'.(Database::driver()==='mysql'?' FOR UPDATE':''));$q->execute([$protocolId]);$p=$q->fetch();
   if (!$p) throw new InvalidArgumentException('Protocolo não encontrado.');
   if ($p['status']==='Arquivado') throw new InvalidArgumentException('Protocolo arquivado não pode ser encaminhado.');
   $db->prepare('UPDATE protocols SET destination_department=?,status=?,updated_by=?,updated_at=? WHERE id=?')->execute([$to,'Em tramitação',$userId,gmdate('Y-m-d H:i:s'),$protocolId]);
   $db->prepare('INSERT INTO protocol_movements(protocol_id,from_department,to_department,note,user_id) VALUES (?,?,?,?,?)')->execute([$protocolId,$p['destination_department'],$to,$note,$userId]);
   $db->commit();Audit::log('forward','protocolos',$protocolId,['from'=>$p['destination_department'],'to'=>$to]);
  }catch(\Throwable $e){$db->rollBack();throw $e;}
 }
 public static function movements(int $materialId): array {$q=Database::pdo()->prepare('SELECT m.*,u.name AS actor FROM stock_movements m LEFT JOIN users u ON u.id=m.user_id WHERE material_id=? ORDER BY m.id DESC LIMIT 100');$q->execute([$materialId]);return $q->fetchAll();}
 public static function history(int $protocolId): array {$q=Database::pdo()->prepare('SELECT m.*,u.name AS actor FROM protocol_movements m LEFT JOIN users u ON u.id=m.user_id WHERE protocol_id=? ORDER BY m.id DESC LIMIT 100');$q->execute([$protocolId]);return $q->fetchAll();}
}
