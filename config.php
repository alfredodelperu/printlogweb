<?php
$host = "fc_memoria";   // nombre del servicio en Easypanel
$port = "5432";
$dbname = "impresiones_fullcolor";
$user = "memoria";
$password = "tu_password";

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}
?>
