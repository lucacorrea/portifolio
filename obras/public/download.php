<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/data.php';
try {
    require_login();
    $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
    if(!$id)throw new HttpError('Anexo inválido.',404);
    $st=db()->prepare('SELECT * FROM attachments WHERE id=? AND deleted_at IS NULL LIMIT 1');
    $st->execute([$id]);$row=$st->fetch();
    if(!$row || !record_row($row['module'],$row['record_code']))throw new HttpError('Anexo não encontrado.',404);
    $dir=realpath(APP_ROOT.'/storage/uploads');
    $path=$dir ? realpath($dir.'/'.$row['stored_name']) : false;
    if(!$path || !str_starts_with($path,$dir.DIRECTORY_SEPARATOR) || !is_file($path))throw new HttpError('Arquivo indisponível.',404);
    $name=preg_replace('/[\r\n"\\]/','_', $row['original_name']);
    $ascii=preg_replace('/[^A-Za-z0-9._-]/','_', $name);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($name));
    header('Content-Length: '.filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} catch (HttpError $e) {http_response_code($e->httpCode);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}
catch (Throwable $e) {error_log($e->getMessage());http_response_code(500);echo 'Arquivo não disponível.';}
