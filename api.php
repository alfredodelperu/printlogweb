<?php
// api.php - Versión final para printologweb
include "config.php";
header('Content-Type: application/json');

// Validar tabla solicitada
$allowedTables = ['HistoryTasks', 'RecordTasks'];
$table = $_GET['table'] ?? 'HistoryTasks';
if (!in_array($table, $allowedTables)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tabla no válida. Solo se permiten: HistoryTasks, RecordTasks']);
    exit;
}

// Obtener parámetros de filtro
$dateFrom = $_GET['dateFrom'] ?? '';
$dateTo = $_GET['dateTo'] ?? '';
$filename = $_GET['filename'] ?? '';
$filenameLogic = $_GET['filenameLogic'] ?? 'or';
$pcs = $_GET['pcs'] ?? '';
$event = $_GET['event'] ?? '';
$selected = $_GET['selected'] ?? ''; // Para exportación de registros seleccionados
$limit = $_GET['limit'] ?? 1000;

// Validar ordenamiento
$allowedColumns = [
    'id', 'bmppath', 'anchomm', 'largomm', 'largototal', 'copiasrequeridas', 
    'produccion', 'pc_name', 'fecha1', 'fecha2'
];
$order_by = $_GET['order_by'] ?? 'fecha1';
$order_dir = strtoupper($_GET['order_dir'] ?? 'DESC');

if (!in_array($order_by, $allowedColumns)) {
    $order_by = 'fecha1';
}
if ($order_dir !== 'ASC') {
    $order_dir = 'DESC';
}

// Construir condiciones WHERE
$whereConditions = [];
$params = [];
$paramCount = 1;

// Rango de fechas
if ($dateFrom && $dateTo) {
    $dateFromFull = $dateFrom . ' 00:00:00+00';
    $dateToFull = $dateTo . ' 23:59:59+00';
    $whereConditions[] = "fecha1 BETWEEN $" . $paramCount . " AND $" . ($paramCount + 1);
    $params[] = $dateFromFull;
    $params[] = $dateToFull;
    $paramCount += 2;
}

// Nombre de archivo (BmpPath)
if ($filename) {
    $terms = array_map('trim', explode(',', $filename));
    $filenameConditions = [];
    foreach ($terms as $term) {
        if (!empty($term)) {
            $filenameConditions[] = "LOWER(bmppath) LIKE $" . $paramCount;
            $params[] = '%' . strtolower($term) . '%';
            $paramCount++;
        }
    }
    if (!empty($filenameConditions)) {
        $connector = ($filenameLogic === 'and') ? ' AND ' : ' OR ';
        $whereConditions[] = '(' . implode($connector, $filenameConditions) . ')';
    }
}

// PCs seleccionadas
if ($pcs) {
    $pcList = array_map('trim', explode(',', $pcs));
    $pcPlaceholders = [];
    foreach ($pcList as $pc) {
        if (!empty($pc)) {
            $pcPlaceholders[] = '$' . $paramCount;
            $params[] = $pc;
            $paramCount++;
        }
    }
    if (!empty($pcPlaceholders)) {
        $whereConditions[] = 'pc_name IN (' . implode(',', $pcPlaceholders) . ')';
    }
}

// Estado (completado/incompleto)
if ($event !== '') {
    $whereConditions[] = "completado = $" . $paramCount;
    $params[] = (int)$event;
    $paramCount++;
}

// Registros seleccionados (para exportación)
if ($selected) {
    $selectedIds = explode(',', $selected);
    $idPlaceholders = [];
    foreach ($selectedIds as $id) {
        if (is_numeric($id)) {
            $idPlaceholders[] = '$' . $paramCount;
            $params[] = (int)$id;
            $paramCount++;
        }
    }
    if (!empty($idPlaceholders)) {
        $whereConditions[] = 'id IN (' . implode(',', $idPlaceholders) . ')';
    }
}

// Cláusula WHERE
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Consulta principal: unir ambas tablas con ImpresionesImagenes
$query = "
    SELECT 
        h.id,
        h.codigoimagen,
        h.bmppath,
        h.anchoPx,
        h.largoPx,
        h.resolucionX,
        h.resolucionY,
        h.anchomm,
        h.largomm,
        h.anchomm2,
        h.largomm2,
        h.anchomm3,
        h.copiasrequeridas,
        h.completado,
        h.produccion,
        h.velocidadm2h,
        h.velocidadlineal,
        h.modoimpresion,
        h.fecha1,
        h.fecha2,
        h.tiempotranscurrido1,
        h.tiempotranscurrido2,
        h.uid,
        h.pc_name,
        h.largototal,
        h.largoimpreso,
        h.copiasimpresas,
        i.imagen_jpg AS imagen_base64 -- Solo para conexión interna, no se devuelve en JSON
    FROM \"{$table}\" h
    LEFT JOIN \"ImpresionesImagenes\" i ON h.codigoimagen = i.codigoimagen AND h.pc_name = i.pc_name
    {$whereClause}
    ORDER BY {$order_by} {$order_dir}
    LIMIT {$limit}
";

$result = pg_query_params($conn, $query, $params);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error en la consulta: ' . pg_last_error($conn),
        'query' => $query,
        'params' => $params
    ]);
    exit;
}

$rows = [];
while ($row = pg_fetch_assoc($result)) {
    // Calcular dimensiones en cm (de mm a cm)
    $anchoCm = $row['anchomm'] ? round($row['anchomm'] / 10, 1) : null;
    $largoCm = $row['largomm'] ? round($row['largomm'] / 10, 1) : null;

    // Calcular metros lineales (largo total en m)
    $mlTotal = $row['largototal'] ? round($row['largototal'] / 1000, 2) : 0;

    // Calcular metros cuadrados (de mm² a m²)
    $m2Total = $row['anchomm'] && $row['largomm'] 
        ? round(($row['anchomm'] * $row['largomm']) / 1000000, 2) 
        : 0;

    // Duración en hh:mm:ss
    $duracionSegundos = $row['tiempotranscurrido2'] ? intval($row['tiempotranscurrido2'] / 1000) : 0;
    $horas = floor($duracionSegundos / 3600);
    $minutos = floor(($duracionSegundos % 3600) / 60);
    $segundos = $duracionSegundos % 60;
    $duracionFormat = sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);

    $rows[] = [
        'id' => (int)$row['id'],
        'codigoimagen' => (int)$row['codigoimagen'],
        'bmppath' => $row['bmppath'] ?: '-',
        'anchoPx' => $row['anchoPx'] ? (int)$row['anchoPx'] : null,
        'largoPx' => $row['largoPx'] ? (int)$row['largoPx'] : null,
        'resolucionX' => $row['resolucionX'] ? (int)$row['resolucionX'] : null,
        'resolucionY' => $row['resolucionY'] ? (int)$row['resolucionY'] : null,
        'anchomm' => $row['anchomm'] ? (float)$row['anchomm'] : null,
        'largomm' => $row['largomm'] ? (float)$row['largomm'] : null,
        'anchomm2' => $row['anchomm2'] ? (float)$row['anchomm2'] : null,
        'largomm2' => $row['largomm2'] ? (float)$row['largomm2'] : null,
        'anchomm3' => $row['anchomm3'] ? (float)$row['anchomm3'] : null,
        'copiasrequeridas' => $row['copiasrequeridas'] ? (int)$row['copiasrequeridas'] : 1,
        'completado' => (int)$row['completado'],
        'produccion' => $row['produccion'] ? (float)$row['produccion'] : 0.0,
        'velocidadm2h' => $row['velocidadm2h'] ? (float)$row['velocidadm2h'] : null,
        'velocidadlineal' => $row['velocidadlineal'] ? (float)$row['velocidadlineal'] : null,
        'modoimpresion' => $row['modoimpresion'] ?: '-',
        'fecha1' => $row['fecha1'] ?: null,
        'fecha2' => $row['fecha2'] ?: null,
        'tiempotranscurrido1' => $row['tiempotranscurrido1'] ? (int)$row['tiempotranscurrido1'] : null,
        'tiempotranscurrido2' => $row['tiempotranscurrido2'] ? (int)$row['tiempotranscurrido2'] : null,
        'uid' => $row['uid'] ?: '-',
        'pc_name' => $row['pc_name'] ?: '-',
        'largototal' => $row['largototal'] ? (float)$row['largototal'] : 0.0,
        'largoimpreso' => $row['largoimpreso'] ? (float)$row['largoimpreso'] : 0.0,
        'copiasimpresas' => $row['copiasimpresas'] ? (float)$row['copiasimpresas'] : 0.0,
        'ancho_cm' => $anchoCm,
        'largo_cm' => $largoCm,
        'ml_total' => $mlTotal,
        'm2_total' => $m2Total,
        'duracion' => $duracionFormat
    ];
}

// Estadísticas (agregadas por tabla activa)
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN completado = 1 THEN 1 END) as completed_count,
        COUNT(CASE WHEN completado = 0 THEN 1 END) as incomplete_count,
        SUM(largototal / 1000) as ml_total,
        SUM(anchomm * largomm / 1000000) as m2_total,
        COUNT(DISTINCT pc_name) as unique_pcs
    FROM \"{$table}\"
    {$whereClause}
";

$stats_result = pg_query_params($conn, $statsQuery, $params);
if (!$stats_result) {
    $stats = [
        'total' => count($rows),
        'completed_count' => count(array_filter($rows, fn($r) => $r['completado'] === 1)),
        'incomplete_count' => count(array_filter($rows, fn($r) => $r['completado'] === 0)),
        'ml_total' => array_sum(array_column($rows, 'ml_total')),
        'm2_total' => array_sum(array_column($rows, 'm2_total')),
        'unique_pcs' => count(array_unique(array_column($rows, 'pc_name')))
    ];
} else {
    $stats = pg_fetch_assoc($stats_result);
}

// Obtener lista de PCs únicas
$pcQuery = "SELECT DISTINCT pc_name FROM \"{$table}\" WHERE pc_name IS NOT NULL ORDER BY pc_name";
$pcResult = pg_query($conn, $pcQuery);
$pcs_list = [];
while ($pcRow = pg_fetch_assoc($pcResult)) {
    $pcs_list[] = $pcRow['pc_name'];
}

echo json_encode([
    'data' => $rows,
    'stats' => [
        'total' => (int)$stats['total'],
        'completed_count' => (int)$stats['completed_count'],
        'incomplete_count' => (int)$stats['incomplete_count'],
        'ml_total' => round((float)$stats['ml_total'], 2),
        'm2_total' => round((float)$stats['m2_total'], 2),
        'unique_pcs' => (int)$stats['unique_pcs']
    ],
    'pcs_list' => $pcs_list,
    'filters_applied' => [
        'table' => $table,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'filename' => $filename,
        'filenameLogic' => $filenameLogic,
        'pcs' => $pcs,
        'event' => $event
    ]
]);

pg_close($conn);
?>