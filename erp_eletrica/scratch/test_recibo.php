<?php
$_GET['id'] = 1;
$_SESSION['usuario_id'] = 1;
ob_start();
include 'recibo_troca.php';
$output = ob_get_clean();
echo $output;
