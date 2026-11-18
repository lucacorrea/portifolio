<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Env;
use InvalidArgumentException;
final class Uploads {
 private const ALLOWED=['pdf'=>['application/pdf'],'png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],
  'docx'=>['application/zip','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
  'xlsx'=>['application/zip','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']];
 public static function store(array $input): ?array {
  if (($input['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
  if (($input['error']??0)!==UPLOAD_ERR_OK) throw new InvalidArgumentException('Falha no envio do arquivo. Verifique o limite de upload do servidor.');
  $max=max(1,(int)Env::get('MAX_UPLOAD_MB','10'))*1024*1024;
  if (($input['size']??0)<1 || $input['size']>$max) throw new InvalidArgumentException('Arquivo vazio ou maior que o limite de '.(int)Env::get('MAX_UPLOAD_MB','10').' MB.');
  $tmp=(string)($input['tmp_name']??'');if (!is_uploaded_file($tmp)) throw new InvalidArgumentException('Upload não reconhecido pelo servidor.');
  $original=(string)($input['name']??'arquivo');$original=substr(basename(str_replace('\\','/',$original)),0,180);
  $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));if (!isset(self::ALLOWED[$ext])) throw new InvalidArgumentException('Tipo de arquivo não permitido.');
  $finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($tmp)?:'application/octet-stream';
  if (!in_array($mime,self::ALLOWED[$ext],true)) throw new InvalidArgumentException('A extensão não corresponde ao conteúdo do arquivo.');
  // Verificação básica das assinaturas para formatos OpenXML ZIP (sem macros).
  if (in_array($ext,['docx','xlsx'],true)) {
   $fh=fopen($tmp,'rb');$magic=fread($fh,4);fclose($fh);
   if (substr($magic,0,2)!=='PK') throw new InvalidArgumentException('Arquivo Office inválido.');
  }
  $stored=bin2hex(random_bytes(24)).'.'.$ext;
  $dest=BASE_PATH.'/storage/uploads/'.$stored;
  if (!move_uploaded_file($tmp,$dest)) throw new InvalidArgumentException('Não foi possível armazenar o arquivo.');
  @chmod($dest,0640);
  return ['original_filename'=>$original,'stored_filename'=>$stored,'mime_type'=>$mime,'file_size'=>(int)$input['size']];
 }
 public static function path(array $record): string {
  $name=(string)($record['stored_filename']??'');
  if (!preg_match('/^[a-f0-9]{48}\.(pdf|png|jpe?g|docx|xlsx)$/',$name))throw new InvalidArgumentException('Arquivo indisponível.');
  return BASE_PATH.'/storage/uploads/'.$name;
 }
 public static function download(array $record): never {
  $path=self::path($record);if (!is_file($path)) \error_response(404,'Arquivo não localizado.');
  $name=preg_replace('/[^\p{L}\p{N}\s._-]/u','_', (string)($record['original_filename']??'arquivo'))?:'arquivo';
  header('Content-Type: application/octet-stream');header('Content-Length: '.filesize($path));
  header('Content-Disposition: attachment; filename="documento"; filename*=UTF-8\'\''.rawurlencode($name));
  header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
  readfile($path);exit;
 }
}
