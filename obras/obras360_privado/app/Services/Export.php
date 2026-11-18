<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Repository,Modules,Audit};
final class Export {
 public static function dataset(string $module,array $filters=[]): array {
  $config=Modules::get($module);$rows=Repository::list($module,$filters,20000);
  $works=Repository::choices();$columns=$config['columns'];
  $columns['description']='Observações';
  foreach ($rows as &$row){if (isset($row['work_id']))$row['work_id']=Repository::workName($row['work_id']===null?null:(int)$row['work_id'],$works);}
  return [$columns,$rows];
 }
 public static function csv(string $module,array $filters=[]): never {
  [$cols,$rows]=self::dataset($module,$filters);$filename='obras360_'.$module.'_'.date('Ymd').'.csv';
  header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$filename.'"');
  echo "\xEF\xBB\xBF";$out=fopen('php://output','wb');
  fputcsv($out,array_values($cols),';','"','');
  foreach ($rows as $row){$line=[];foreach(array_keys($cols) as $key)$line[]=self::csvSafe((string)($row[$key]??''));fputcsv($out,$line,';','"','');}
  fclose($out);Audit::log('export_csv',$module,null,['rows'=>count($rows)]);exit;
 }
 private static function csvSafe(string $s): string {
  // Impede execução de fórmulas em Excel/Calc, inclusive com whitespace inicial.
  $s=str_replace(["\0","\r"],'',$s);
  return preg_match('/^[\s\x00-\x1f]*[=+@\-]/u',$s)?"'".$s:$s;
 }
 public static function xlsx(string $module,array $filters=[]): never {
  [$cols,$rows]=self::dataset($module,$filters);$cells=[array_values($cols)];
  foreach ($rows as $row){$line=[];foreach(array_keys($cols) as $key)$line[]=$row[$key]??'';$cells[]=$line;}
  $xmlRows='';foreach ($cells as $r=>$line){$xmlRows.='<row r="'.($r+1).'">';foreach($line as $c=>$value){
   $ref=self::col($c).($r+1);$isNumber=is_numeric($value)&&$r>0&&in_array(array_keys($cols)[$c],['budget','value','progress','quantity','minimum','area'],true);
   if ($isNumber)$xmlRows.='<c r="'.$ref.'" s="1"><v>'.((float)$value).'</v></c>';
   else $xmlRows.='<c r="'.$ref.'" t="inlineStr"'.($r===0?' s="2"':'').'><is><t xml:space="preserve">'.self::xml((string)$value).'</t></is></c>';
  }$xmlRows.='</row>';}
  $files=[
   '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
   '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
   'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="OBRAS360" sheetId="1" r:id="rId1"/></sheets></workbook>',
   'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
   'xl/styles.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="12"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="12"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF153D54"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1"/></cellXfs></styleSheet>',
   'xl/worksheets/sheet1.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="21"/><cols><col min="1" max="12" width="26" customWidth="1"/></cols><sheetData>'.$xmlRows.'</sheetData><autoFilter ref="A1:'.self::col(count($cols)-1).count($cells).'"/></worksheet>'
  ];
  $data=self::zip($files);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="obras360_'.$module.'_'.date('Ymd').'.xlsx"');header('Content-Length: '.strlen($data));echo $data;
  Audit::log('export_xlsx',$module,null,['rows'=>count($rows)]);exit;
 }
 private static function col(int $i): string {$s='';do{$s=chr(65+$i%26).$s;$i=intdiv($i,26)-1;}while($i>=0);return $s;}
 private static function xml(string $s): string {
  // XML 1.0 não permite caracteres de controle.
  $s=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$s)??'';
  return htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8');
 }
 /** Gerador ZIP OOXML básico, sem dependência de ZipArchive. */
 private static function zip(array $files): string {
  $data='';$central='';$count=0;
  foreach($files as $name=>$content){
   $compressed=gzdeflate($content,6);$crc=crc32($content);$offset=strlen($data);$nl=strlen($name);
   $local=pack('VvvvvvVVVvv',0x04034b50,20,0,8,0,33,$crc,strlen($compressed),strlen($content),$nl,0);
   $data.=$local.$name.$compressed;
   $central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,8,0,33,$crc,strlen($compressed),strlen($content),$nl,0,0,0,0,0,$offset).$name;
   $count++;
  }
  return $data.$central.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($central),strlen($data),0);
 }
 public static function geojson(): never {
  $features=[];
  foreach (['obras','patrimonio'] as $module){foreach(Repository::raw($module) as $r){
   if ($r['latitude']===null||$r['longitude']===null)continue;
   $features[]=['type'=>'Feature','geometry'=>['type'=>'Point','coordinates'=>[(float)$r['longitude'],(float)$r['latitude']]],
    'properties'=>['id'=>$r['id'],'module'=>$module,'code'=>$r['code'],'title'=>$r['title'],'status'=>$r['status']]];
  }}
  header('Content-Type: application/geo+json; charset=utf-8');
  header('Content-Disposition: attachment; filename="obras360_mapa_'.date('Ymd').'.geojson"');
  echo json_encode(['type'=>'FeatureCollection','features'=>$features],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_INVALID_UTF8_SUBSTITUTE);
  Audit::log('export_geojson','mapa',null,['features'=>count($features)]);exit;
 }
}
