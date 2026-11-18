<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Csrf,Modules,Repository,Validator};
use App\Services\{Uploads,Workflow};
use InvalidArgumentException;
final class ModuleController {
 public static function index(string $module): void {
  Auth::requireLogin();$config=Modules::get($module);$id=max(0,(int)($_GET['id']??0));$action=(string)($_GET['action']??'list');
  if ($_SERVER['REQUEST_METHOD']==='POST') {self::post($module);return;}
  $works=Repository::choices();
  if ($action==='create'||$action==='edit') {
   Auth::requireWrite();$record=$action==='edit'?Repository::find($module,$id):null;
   if ($action==='edit'&&!$record) \error_response(404,'Registro não localizado.');
   \render('modules/form',['pageTitle'=>($record?'Editar ': 'Novo ').$config['singular'],'config'=>$config,'module'=>$module,'record'=>$record,'works'=>$works]);return;
  }
  if ($action==='show') {
   $record=Repository::find($module,$id);if (!$record) \error_response(404,'Registro não localizado.');
   $movements=$module==='materiais'?Workflow::movements($id):[];
   $history=$module==='protocolos'?Workflow::history($id):[];
   \render('modules/show',compact('module','config','record','works','movements','history')+['pageTitle'=>$record['title']]);return;
  }
  $filters=self::filters();$page=min(10000,max(1,(int)($_GET['page']??1)));$perPage=15;
  $total=Repository::count($module,$filters);$rows=Repository::list($module,$filters,$perPage,($page-1)*$perPage);
  $statusCounts=Repository::statusCounts($module);
  \render('modules/list',compact('module','config','rows','works','filters','page','total','perPage','statusCounts')+['pageTitle'=>$config['name']]);
 }
 public static function filters(): array {return ['q'=>substr(trim((string)($_GET['q']??'')),0,120),'status'=>substr(trim((string)($_GET['status']??'')),0,80),'from'=>(string)($_GET['from']??''),'to'=>(string)($_GET['to']??'')];}
 private static function post(string $module): void {
  Csrf::verify();Auth::requireWrite();$task=(string)($_POST['task']??'save');$id=(int)($_POST['id']??0);
  try {
   if ($task==='delete'){
    Auth::requireDelete();Repository::delete($module,$id,(int)Auth::id());\flash('Registro arquivado. O histórico permanece disponível para auditoria.');\redirect($module);
   }
   if ($task==='stock') {
    if ($module!=='materiais')\error_response(404);
    Workflow::moveStock($id,(string)($_POST['movement_type']??''),(float)($_POST['amount']??0),(string)($_POST['note']??''),(int)Auth::id());
    \flash('Movimentação registrada e saldo atualizado.');\redirect('materiais',['action'=>'show','id'=>$id]);
   }
   if ($task==='forward') {
    if ($module!=='protocolos')\error_response(404);
    Workflow::forwardProtocol($id,(string)($_POST['destination_department']??''),(string)($_POST['note']??''),(int)Auth::id());
    \flash('Encaminhamento registrado no histórico.');\redirect('protocolos',['action'=>'show','id'=>$id]);
   }
   if ($task!=='save')\error_response(400,'Ação desconhecida.');
   $record=$id?Repository::find($module,$id):null;
   if ($id && !$record)\error_response(404,'Registro não encontrado.');
   $data=Validator::record($module,$_POST,(bool)$id);
   $file=null;
   if ($module==='projetos' && isset($_FILES['upload'])) {
    $file=Uploads::store($_FILES['upload']);if ($file) $data=array_merge($data,$file,['uploaded_by'=>Auth::id()]);
   }
   try {$savedId=$id?:Repository::create($module,$data,(int)Auth::id());if ($id)Repository::update($module,$id,$data,(int)Auth::id());}
   catch(\Throwable $e){if ($file && isset($file['stored_filename']))@unlink(BASE_PATH.'/storage/uploads/'.$file['stored_filename']);throw $e;}
   \flash($id?'Alterações salvas com sucesso.':'Registro cadastrado com sucesso.');\redirect($module,['action'=>'show','id'=>$savedId]);
  } catch(InvalidArgumentException $e) {\flash($e->getMessage(),'error');\redirect($module,$id?['action'=>'edit','id'=>$id]:['action'=>'create']);}
 }
 public static function download(): never {
  Auth::requireLogin();$id=(int)($_GET['id']??0);$record=Repository::find('projetos',$id);
  if (!$record || !$record['stored_filename'])\error_response(404,'Documento não disponível.');
  \App\Core\Audit::log('download','projetos',$id);Uploads::download($record);
 }
}
