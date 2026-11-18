<?php
declare(strict_types=1);
// Chamado apenas pelo instalador CLI, após criação do administrador e das tabelas.
if (PHP_SAPI!=='cli') exit(403);
$seed=json_decode(file_get_contents(BASE_PATH.'/database/demo-original.json'),true,flags:JSON_THROW_ON_ERROR);
$db=\App\Core\Database::pdo();$adminId=(int)$db->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
$worksByTitle=[];
$modules=['obras'=>'works','ordens'=>'service_orders','fiscalizacao'=>'inspections','projetos'=>'documents','orcamentos'=>'measurements','materiais'=>'materials','patrimonio'=>'buildings','protocolos'=>'protocols'];
$maps=[
 'obras'=>['title'=>'title','tipo'=>'type','local'=>'local','responsavel'=>'responsible','status'=>'status','progresso'=>'progress','orcamento'=>'budget','inicio'=>'start_date','fim'=>'end_date','contrato'=>'contract_ref','descricao'=>'description'],
 'ordens'=>['title'=>'title','local'=>'local','solicitante'=>'requester','responsavel'=>'responsible','prioridade'=>'priority','status'=>'status','prazo'=>'due_date','descricao'=>'description'],
 'fiscalizacao'=>['title'=>'title','responsavel'=>'responsible','tipo'=>'type','data'=>'inspection_date','status'=>'status','descricao'=>'description'],
 'projetos'=>['title'=>'title','tipo'=>'type','data'=>'document_date','status'=>'status','descricao'=>'description'],
 'orcamentos'=>['title'=>'title','valor'=>'value','responsavel'=>'responsible','status'=>'status','descricao'=>'description'],
 'materiais'=>['title'=>'title','unidade'=>'unit','local'=>'local','quantidade'=>'quantity','minimo'=>'minimum','descricao'=>'description'],
 'patrimonio'=>['title'=>'title','local'=>'local','tipo'=>'type','condicao'=>'condition_status','responsavel'=>'responsible','area'=>'area','status'=>'status','descricao'=>'description'],
 'protocolos'=>['title'=>'title','setor'=>'origin_department','destino'=>'destination_department','data'=>'opened_on','status'=>'status','prioridade'=>'priority','descricao'=>'description']
];
// Coordenadas inventadas em torno do centro urbano, utilizadas SOMENTE com --demo.
$fakeCoords=[[-4.0850,-63.1420],[-4.0788,-63.1490],[-4.0871,-63.1274],[-4.1004,-63.1522],[-4.0911,-63.1680],[-4.0722,-63.1290],[-4.0820,-63.1389],[-4.0958,-63.1457]];
$db->beginTransaction();
try {
 foreach($modules as $module=>$table){foreach($seed[$module] as $index=>$old){
  $data=['code'=>$old['id']];
  foreach($maps[$module] as $oldKey=>$column) if (array_key_exists($oldKey,$old))$data[$column]=$old[$oldKey];
  if (in_array($module,['ordens','fiscalizacao','projetos','orcamentos'],true)){
   $name=(string)($old['obra']??'');$data['work_id']=$worksByTitle[$name]??null;
  }
  if($module==='projetos'){$data['version']='1';}
  if($module==='orcamentos'){
   // Setembro/2026 ou Outubro/2026 -> AAAA-MM.
   $period=$old['periodo']??'Novembro/2026';$names=['Janeiro'=>'01','Fevereiro'=>'02','Março'=>'03','Abril'=>'04','Maio'=>'05','Junho'=>'06','Julho'=>'07','Agosto'=>'08','Setembro'=>'09','Outubro'=>'10','Novembro'=>'11','Dezembro'=>'12'];
   [$m,$y]=array_pad(explode('/',$period,2),2,'2026');$data['period']=$y.'-'.($names[$m]??'11');
  }
  if($module==='obras'||$module==='patrimonio'){
   $p=$fakeCoords[$index%count($fakeCoords)];$data['latitude']=$p[0]+($module==='patrimonio'?.002:0);
   $data['longitude']=$p[1]+($module==='patrimonio'?.002:0);
  }
  $data['created_by']=$adminId;$data['updated_by']=$adminId;
  $fields=array_keys($data);$stmt=$db->prepare('INSERT INTO '.$table.'('.implode(',',$fields).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')');
  $stmt->execute(array_values($data));$id=(int)$db->lastInsertId();
  if ($module==='obras') $worksByTitle[$old['title']]=$id;
  if ($module==='materiais' && (float)$data['quantity']>0){
   $db->prepare('INSERT INTO stock_movements(material_id,movement_type,amount,note,user_id) VALUES (?,?,?,?,?)')->execute([$id,'Entrada',$data['quantity'],'Saldo inicial de demonstração',$adminId]);
  }
  if($module==='protocolos'){
   $db->prepare('INSERT INTO protocol_movements(protocol_id,from_department,to_department,note,user_id) VALUES (?,?,?,?,?)')->execute([$id,$data['origin_department'],$data['destination_department'],'Abertura fictícia do processo',$adminId]);
  }
 }}
 $milestones=[
  ['Conferência de instalação elétrica','Reforma de unidade pública','2026-11-25','Programado'],
  ['Entrega parcial de materiais','Manutenção de via urbana','2026-12-04','Aguardando'],
  ['Vistoria de recebimento','Adequação de prédio público','2026-12-12','Programado'],
  ['Conferência da drenagem','Implantação de drenagem','2027-01-10','Programado']
 ];
 foreach($milestones as $i=>[$title,$work,$date,$status]){
  $db->prepare('INSERT INTO milestones(code,title,work_id,due_date,status,description,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?)')
   ->execute(['MRC-'.str_pad((string)($i+1),5,'0',STR_PAD_LEFT),$title,$worksByTitle[$work]??null,$date,$status,'Marco demonstrativo de planejamento',$adminId,$adminId]);
 }
 $db->commit();file_put_contents(BASE_PATH.'/storage/DEMO_MODE',"Dados e coordenadas fictícios instalados em ".gmdate('c')."\n");
} catch(Throwable $e){$db->rollBack();throw $e;}
