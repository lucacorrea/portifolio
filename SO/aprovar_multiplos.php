<?php

require_once 'config/database.php';
require_once 'config/functions.php';

login_check();

$nivel=strtoupper($_SESSION['nivel']);

if(!in_array($nivel,['ADMIN','SUPORTE'])){

exit('Sem permissão.');

}

if(empty($_POST['oficios'])){

flash_message('warning','Nenhuma solicitação selecionada.');

header('Location: oficios_lista.php');

exit;

}

$ids=array_map('intval',$_POST['oficios']);

$placeholders=implode(',',array_fill(0,count($ids),'?'));

$sql="UPDATE oficios
SET status='APROVADO'
WHERE status='ENVIADO'
AND id IN($placeholders)";

$stmt=$pdo->prepare($sql);

$stmt->execute($ids);

flash_message('success',$stmt->rowCount().' solicitação(ões) aprovada(s).');

header('Location: oficios_lista.php');