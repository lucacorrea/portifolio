<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Repository};
use App\Services\Export;
final class MapController {
 public static function index(): void {
  Auth::requireLogin();if (($_GET['format']??'')==='geojson')Export::geojson();
  $points=[];
  foreach(['obras'=>'Obra','patrimonio'=>'Prédio público'] as $module=>$label){foreach(Repository::raw($module) as $row){
   if ($row['latitude']===null||$row['longitude']===null)continue;
   $points[]=['id'=>$row['id'],'module'=>$module,'category'=>$label,'code'=>$row['code'],'title'=>$row['title'],'local'=>$row['local'],'status'=>$row['status'],'lat'=>(float)$row['latitude'],'lng'=>(float)$row['longitude']];
  }}
  \render('modules/map',['pageTitle'=>'Mapa de obras e patrimônio','points'=>$points]);
 }
}
