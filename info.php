<?php
// info.php - Diagnóstico completo para printologweb
header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "🧠 DIAGNÓSTICO DE ENTORNO PHP - printologweb\n";
echo "========================================\n\n";

// 1. Versión de PHP
echo "🔹 Versión de PHP: " . phpversion() . "\n\n";

// 2. Archivo php.ini cargado
echo "🔹 Archivo php.ini cargado: ";
$ini = php_ini_loaded_file();
if ($ini) {
    echo $ini . "\n";
} else {
    echo "❌ No se encontró archivo php.ini cargado.\n";
}

// 3. Extensiones cargadas
echo "\n🔹 Extensiones cargadas (filtradas por PostgreSQL):\n";
$extensions = get_loaded_extensions();
if (in_array('pgsql', $extensions)) {
    echo "    ✅ pgsql → ✔️ INSTALADA Y ACTIVA\n";
} else {
    echo "    ❌ pgsql → ❌ NO INSTALADA O DESACTIVADA\n";
}

// Mostrar todas las extensiones (opcional, solo si quieres ver más)
// echo "\n🔹 Todas las extensiones cargadas:\n";
// foreach ($extensions as $ext) {
//     echo "    - " . $ext . "\n";
// }

// 4. Verificar funciones de PostgreSQL
echo "\n🔹 Funciones de PostgreSQL disponibles:\n";
$pg_functions = [
    'pg_connect',
    'pg_query',
    'pg_fetch_assoc',
    'pg_last_error'
];

foreach ($pg_functions as $func) {
    if (function_exists($func)) {
        echo "    ✅ " . $func . "\n";
    } else {
        echo "    ❌ " . $func . "\n";
    }
}

// 5. Variables de entorno (si usas variables de conexión en config.php)
echo "\n🔹 Variables de entorno relacionadas con PostgreSQL:\n";
$env_vars = ['PGHOST', 'PGPORT', 'PGDATABASE', 'PGUSER', 'PGPASSWORD'];
foreach ($env_vars as $var) {
    $value = getenv($var);
    if ($value !== false) {
        echo "    ✅ " . $var . " = " . $value . "\n";
    } else {
        echo "    ❌ " . $var . " = no definida\n";
    }
}

// 6. Ruta del servidor web
echo "\n🔹 Ruta del script actual: " . __FILE__ . "\n";

// 7. Usuario del proceso PHP (para identificar permisos)
echo "\n🔹 Usuario del proceso PHP: " . exec('whoami') . "\n";

echo "\n========================================\n";
echo "✅ RECOMENDACIÓN FINAL:\n";
echo "   Si 'pgsql' está ausente, instala la extensión:\n";
echo "   Ubuntu: sudo apt install php-pgsql && sudo systemctl restart apache2\n";
echo "   Windows: Descomenta 'extension=pgsql' en php.ini y reinicia Apache\n";
echo "========================================\n";