<?php
declare(strict_types=1);
namespace App\Core;
use PDO;use RuntimeException;
final class Repository {
 public static function list(string $module,array $filters=[],int $limit=20,int $offset=0): array {
  $config=Modules::get($module);$table=$config['table'];[$where,$params]=self::filters($module,$filters);
  $q=Database::pdo()->prepare("SELECT * FROM {$table} {$where} ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?");
  $i=1;foreach($params as $p){$q->bindValue($i++,$p);}$q->bindValue($i++,$limit,PDO::PARAM_INT);$q->bindValue($i,$offset,PDO::PARAM_INT);$q->execute();
  return $q->fetchAll();
 }
 public static function count(string $module,array $filters=[]): int {
  $config=Modules::get($module);[$where,$params]=self::filters($module,$filters);
  $q=Database::pdo()->prepare('SELECT COUNT(*) FROM '.$config['table'].' '.$where);$q->execute($params);return (int)$q->fetchColumn();
 }
 private static function filters(string $module,array $filters): array {
  $config=Modules::get($module);$w=['deleted_at IS NULL'];$p=[];
  $search=trim((string)($filters['q']??''));$status=trim((string)($filters['status']??''));
  if ($search!=='') {
   $columns=array_intersect(['code','title','local','responsible','origin_department','destination_department'],array_merge(['code'],array_keys($config['fields'])));
   $sub=[];foreach ($columns as $col) {$sub[]="{$col} LIKE ?";$p[]='%'.mb_case_fallback(substr($search,0,120)).'%';}
   if ($sub) $w[]='('.implode(' OR ',$sub).')';
  }
  if ($status!=='') {if ($module==='materiais') {
    if ($status==='Baixo') $w[]='quantity < minimum';
    if ($status==='Normal') $w[]='quantity >= minimum';
   } elseif ($module==='patrimonio' && str_starts_with($status,'cond:')) { $w[]='condition_status = ?';$p[]=substr($status,5);
   } else {$w[]='status = ?';$p[]=$status;}
  }
  $dateField=$config['date_field']??null;
  if ($dateField) {
   if (preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($filters['from']??''))) {$w[]="{$dateField} >= ?";$p[]=$filters['from'];}
   if (preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($filters['to']??''))) {$w[]="{$dateField} <= ?";$p[]=$filters['to'];}
  }
  return ['WHERE '.implode(' AND ',$w),$p];
 }
 public static function find(string $module,int $id): ?array {
  $table=Modules::get($module)['table'];$q=Database::pdo()->prepare("SELECT * FROM {$table} WHERE id=? AND deleted_at IS NULL");$q->execute([$id]);return $q->fetch()?:null;
 }
 public static function raw(string $module): array {return self::list($module,[],10000);}
 public static function choices(): array {
  $q=Database::pdo()->query('SELECT id,code,title FROM works WHERE deleted_at IS NULL ORDER BY title');return $q->fetchAll();
 }
 public static function workName(?int $id,array $choices): string {
  if (!$id) return '—';foreach($choices as $w)if((int)$w['id']===$id)return (string)$w['title'];return 'Obra #'.$id;
 }
 public static function create(string $module,array $values,int $userId): int {
  $config=Modules::get($module);$table=$config['table'];$db=Database::pdo();
  $columns=array_keys($values);$columns[]='created_by';$columns[]='updated_by';$placeholders=array_fill(0,count($columns),'?');
  $db->beginTransaction();
  try {
   $q=$db->prepare("INSERT INTO {$table} (".implode(',',$columns).") VALUES (".implode(',',$placeholders).")");
   $q->execute([...array_values($values),$userId,$userId]);$id=(int)$db->lastInsertId();
   $code=self::makeCode($module,$id);$db->prepare("UPDATE {$table} SET code=? WHERE id=?")->execute([$code,$id]);
   if ($module==='materiais' && (float)($values['quantity']??0)>0) {
    $db->prepare("INSERT INTO stock_movements(material_id,movement_type,amount,note,user_id) VALUES (?,'Entrada',?,'Saldo inicial',?)")
     ->execute([$id,(float)$values['quantity'],$userId]);
   }
   if ($module==='protocolos') $db->prepare('INSERT INTO protocol_movements(protocol_id,from_department,to_department,note,user_id) VALUES (?,?,?,?,?)')->execute([$id,$values['origin_department']??null,$values['destination_department']??null,'Abertura do protocolo',$userId]);
   $db->commit();Audit::log('create',$module,$id,['code'=>$code,'title'=>$values['title']??'']);return $id;
  } catch(\Throwable $e){$db->rollBack();throw $e;}
 }
 public static function update(string $module,int $id,array $values,int $userId): void {
  $old=self::find($module,$id);if (!$old) \error_response(404,'Registro não encontrado.');
  if ($module==='materiais')unset($values['quantity']);
  $table=Modules::get($module)['table'];$set=[];foreach(array_keys($values) as $col)$set[]=$col.'=?';$set[]='updated_by=?';$set[]='updated_at=?';
  Database::pdo()->prepare("UPDATE {$table} SET ".implode(',',$set).' WHERE id=? AND deleted_at IS NULL')
   ->execute([...array_values($values),$userId,gmdate('Y-m-d H:i:s'),$id]);
  Audit::log('update',$module,$id,['code'=>$old['code'],'title'=>$old['title']]);
 }
 public static function delete(string $module,int $id,int $userId): void {
  $old=self::find($module,$id);if (!$old) \error_response(404,'Registro não encontrado.');
  if ($module==='materiais') {
   $q=Database::pdo()->prepare('SELECT COUNT(*) FROM stock_movements WHERE material_id=?');$q->execute([$id]);
   // Registros de estoque permanecem no histórico mesmo após ocultação.
  }
  $table=Modules::get($module)['table'];Database::pdo()->prepare("UPDATE {$table} SET deleted_at=?,updated_by=? WHERE id=?")->execute([gmdate('Y-m-d H:i:s'),$userId,$id]);
  Audit::log('soft_delete',$module,$id,['code'=>$old['code'],'title'=>$old['title']]);
 }
 public static function makeCode(string $module,int $id): string {
  $prefix=Modules::get($module)['prefix'];
  if ($module==='obras')return $prefix.date('Y').'-'.str_pad((string)$id,4,'0',STR_PAD_LEFT);
  if ($module==='protocolos')return date('Y').'.'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
  return $prefix.str_pad((string)$id,5,'0',STR_PAD_LEFT);
 }
 public static function totals(): array {
  $db=Database::pdo();$query=fn(string $sql)=>(float)$db->query($sql)->fetchColumn();
  return [
   'works'=>(int)$query('SELECT COUNT(*) FROM works WHERE deleted_at IS NULL'),
   'active'=>(int)$query("SELECT COUNT(*) FROM works WHERE deleted_at IS NULL AND status IN ('Em andamento','Em atenção')"),
   'budget'=>$query('SELECT COALESCE(SUM(budget),0) FROM works WHERE deleted_at IS NULL'),
   'measured'=>$query("SELECT COALESCE(SUM(value),0) FROM measurements WHERE deleted_at IS NULL AND status='Aprovada'"),
   'orders'=>(int)$query("SELECT COUNT(*) FROM service_orders WHERE deleted_at IS NULL AND status != 'Concluída'"),
   'alerts'=>(int)$query('SELECT COUNT(*) FROM materials WHERE deleted_at IS NULL AND quantity<minimum'),
   'protocols'=>(int)$query("SELECT COUNT(*) FROM protocols WHERE deleted_at IS NULL AND status NOT IN ('Concluído','Arquivado')")
  ];
 }
 public static function statusCounts(string $module): array {
  $table=Modules::get($module)['table'];$key=$module==='materiais'?'CASE WHEN quantity<minimum THEN \'Estoque baixo\' ELSE \'Normal\' END':'status';
  return Database::pdo()->query("SELECT {$key} AS label,COUNT(*) AS total FROM {$table} WHERE deleted_at IS NULL GROUP BY {$key} ORDER BY total DESC")->fetchAll();
 }
}
