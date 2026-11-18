<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Modules,Repository};
use App\Services\Export;
final class ReportsController {
 public static function index(): void {
  Auth::requireLogin();$module=(string)($_GET['module']??'obras');if (!Modules::isValid($module))$module='obras';
  $filters=ModuleController::filters();
  if ($module==='materiais' && $filters['status']==='Estoque baixo')$filters['status']='Baixo';
  $format=(string)($_GET['format']??'');
  if ($format==='csv')Export::csv($module,$filters);
  if ($format==='xlsx')Export::xlsx($module,$filters);
  $config=Modules::get($module);$rows=Repository::list($module,$filters,200);$count=Repository::count($module,$filters);
  $works=Repository::choices();$statuses=Repository::statusCounts($module);
  \render($format==='print'?'modules/report-print':'modules/reports',compact('module','config','rows','count','filters','works','statuses')+['pageTitle'=>'Relatórios']);
 }
}
