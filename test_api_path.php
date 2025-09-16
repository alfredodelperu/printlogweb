<?php
// test_api_path.php
header('Content-Type: text/plain');
echo "Archivo actualmente ejecutado: " . __FILE__ . "\n";
echo "Contenido de la línea 315:\n";
echo "----------------------------------------\n";

$lines = file(__FILE__);
if (isset($lines[314])) { // línea 315 en índice 314
    echo $lines[314];
} else {
    echo "Línea 315 no encontrada.\n";
}