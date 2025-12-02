<?php
$host = "localhost";
$user = "u784961086_motos";
$pass = "=0Gsz;V9";
$db   = "u784961086_motos";

$con = new mysqli($host, $user, $pass, $db);

if ($con->connect_error) {
    die("Erro ao conectar: " . $con->connect_error);
}
?>
