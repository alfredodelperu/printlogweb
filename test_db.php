<?php
// test_db.php - Diagnóstico de conexión a PostgreSQL
header('Content-Type: text/plain; charset=utf-8');

echo "🔍 TEST DE CONEXIÓN A POSTGRESQL\n";
echo "========================================\n";

// Intentar cargar config.php
if (!file_exists('config.php')) {
    echo "❌ Archivo config.php no encontrado.\n";
    exit;
}

include 'config.php';

if (!$conn) {
    echo "❌ ERROR: No se pudo conectar a PostgreSQL.\n";
    echo "Detalle: " . pg_last_error() . "\n";
} else {
    echo "✅ CONEXIÓN EXITOSA a PostgreSQL\n";

    // Prueba una consulta simple
    $result = pg_query($conn, "SELECT version()");
    if ($result) {
        $row = pg_fetch_row($result);
        echo "✅ Versión de PostgreSQL: " . $row[0] . "\n";
    } else {
        echo "❌ Error al ejecutar consulta: " . pg_last_error() . "\n";
    }

    // Verificar tablas
    $tables = ['riplog', 'HistoryTasks', 'RecordTasks'];
    foreach ($tables as $table) {
        $res = pg_query($conn, "SELECT COUNT(*) FROM \"$table\"");
        if ($res) {
            $count = pg_fetch_result($res, 0);
            echo "✅ Tabla \"$table\": $count registros\n";
        } else {
            echo "❌ Tabla \"$table\" no existe o no accesible: " . pg_last_error() . "\n";
        }
    }
}