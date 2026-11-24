<?php
/** Verificações locais do OBRAS360 (não acessa o banco real e não requer segredos). */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
require dirname(__DIR__) . '/app/relatorios.php';
require_once dirname(__DIR__) . '/app/auth.php';
$failures=[]; $passes=0;
function check_audit(bool $ok, string $name): void {global $failures,$passes;$passes+=(int)$ok;if(!$ok)$failures[]=$name;echo ($ok?'OK':'FALHA').' '.$name."\n";}
check_audit(password_secure('Abcdef1!'),'senha válida com 8 caracteres');
check_audit(!password_secure('Abcde1!'),'senha curta com 7 caracteres rejeitada');
check_audit(!password_secure('abcdefgh'),'senha sem variedade rejeitada');
check_audit(can_manage_users(['role'=>'suporte']) && can_manage_users(['role'=>'administrador']),'suporte e administrador gerenciam contas');
check_audit(!can_manage_users(['role'=>'gestor']) && !can_write(['role'=>'consulta']),'consulta não escreve e gestor não administra usuários');
$modules = modules();
check_audit(count($modules)===8,'8 definições de módulos');
foreach($modules as $k=>$v){
  $_POST=[];
  foreach($v['fields'] as $f){
    [$name,,$type]=$f;
    $_POST[$name] = match($type){
      'select' => $f[4][0] ?? '',
      'number' => '25',
      'date' => '2026-11-24',
      'textarea' => 'Registro para homologação',
      default => 'Registro de teste'
    };
  }
  try {$res=validated_payload($k);check_audit(isset($res['title'])&&count($res)>0,$k.' payload válido');}
  catch(Throwable $e){check_audit(false,$k.' payload válido: '.$e->getMessage());}
  $required=array_values(array_filter($v['fields'],fn($f)=>!empty($f[3])));
  if($required){
    $name=$required[0][0];$_POST[$name]='';
    try{validated_payload($k);check_audit(false,$k.' exige campo obrigatório');}
    catch(HttpError $e){check_audit(true,$k.' exige campo obrigatório');}
  }
}
$_POST=['title'=>'Teste','tipo'=>$modules['obras']['fields'][1][4][0], 'local'=>'Local', 'responsavel'=>'Equipe','status'=>$modules['obras']['fields'][4][4][0], 'progresso'=>'111'];
try{validated_payload('obras');check_audit(false,'progresso <= 100');}
catch(HttpError $e){check_audit(true,'progresso <= 100');}
$_POST['progresso']='50';$_POST['lat']='-91';
try{validated_payload('obras');check_audit(false,'latitude inválida rejeitada');}
catch(HttpError $e){check_audit(true,'latitude inválida rejeitada');}
$filters=['situacao'=>'all','busca'=>'','inicio'=>'2026-11-01','fim'=>'2026-11-30','ordem'=>'','direcao'=>'asc'];
$rows=[['id'=>'A','data'=>'2026-11-24'],['id'=>'B','data'=>'2026-10-01'],['id'=>'C','data'=>'']];
$r=rpt_apply_filters($rows,$filters);
check_audit(count($r)===1 && $r[0]['id']==='A','filtro exclui registros fora do período e sem data');
$filters['inicio']='';$filters['fim']='';$r=rpt_apply_filters($rows,$filters);
check_audit(count($r)===3,'sem período inclui registros sem data');
$dataset=['module'=>'ordens','user'=>['name'=>'Teste local'],'filters'=>$filters,'rows'=>[
  ['id'=>'OS-TESTE','title'=>'Limpeza de vala','local'=>'Centro','responsavel'=>'Equipe', 'prioridade'=>'Alta','status'=>'Em execução','prazo'=>'2026-11-30','descricao'=>'Teste interno','demo'=>true]
],'status'=>['Em execução'=>1], 'demo'=>1,'budget'=>0,'measured'=>0,'progress'=>0,'progressCount'=>0,'total'=>1];
$xlsx=rpt_xlsx_bytes($dataset);
file_put_contents(dirname(__DIR__,2).'/auditoria_excel_teste.xlsx',$xlsx);
check_audit(strlen($xlsx)>4000&&substr($xlsx,0,2)==='PK','xlsx gerado em memória');
$print=rpt_print_document($dataset);
check_audit(str_contains($print,'Limpeza de vala')&&str_contains($print,'OBRAS360'),'documento imprimível contém registros');
echo "TOTAL: ".$passes.' de '.($passes+count($failures))." verificações\n";
exit($failures?1:0);
