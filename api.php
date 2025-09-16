<?php
// api.php - Versión final con tres fuentes independientes: riplog, HistoryTasks, RecordTasks
include "config.php";
header('Content-Type: application/json');

$type = $_GET['type'] ?? 'riplog'; // 'riplog', 'history', 'record'
$limit = $_GET['limit'] ?? 1000;

$allowedTypes = ['riplog', 'history', 'record'];
if (!in_array($type, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo no válido. Usa: riplog, history o record']);
    exit;
}

// Parámetros de filtro
$dateFrom = $_GET['dateFrom'] ?? '';
$dateTo = $_GET['dateTo'] ?? '';
$filename = $_GET['filename'] ?? '';
$filenameLogic = $_GET['filenameLogic'] ?? 'or';
$pcs = $_GET['pcs'] ?? '';
$event = $_GET['event'] ?? '';
$selected = $_GET['selected'] ?? '';

$whereConditions = [];
$params = [];
$paramCount = 1;

// Rango de fechas (solo para history y record)
if ($dateFrom && $dateTo && in_array($type, ['history', 'record'])) {
    $dateFromFull = $dateFrom . ' 00:00:00+00';
    $dateToFull = $dateTo . ' 23:59:59+00';
    $whereConditions[] = "fecha1 BETWEEN $" . $paramCount . " AND $" . ($paramCount + 1);
    $params[] = $dateFromFull;
    $params[] = $dateToFull;
    $paramCount += 2;
}

// Nombre de archivo
if ($filename) {
    $terms = array_map('trim', explode(',', $filename));
    $filenameConditions = [];
    foreach ($terms as $term) {
        if (!empty($term)) {
            if ($type === 'riplog') {
                $filenameConditions[] = "LOWER(archivo) LIKE $" . $paramCount;
            } else {
                $filenameConditions[] = "LOWER(bmppath) LIKE $" . $paramCount;
            }
            $params[] = '%' . strtolower($term) . '%';
            $paramCount++;
        }
    }
    if (!empty($filenameConditions)) {
        $connector = ($filenameLogic === 'and') ? ' AND ' : ' OR ';
        $whereConditions[] = '(' . implode($connector, $filenameConditions) . ')';
    }
}

// PCs
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

// Estado (solo para history y record)
if ($event !== '' && in_array($type, ['history', 'record'])) {
    $whereConditions[] = "completado = $" . $paramCount;
    $params[] = (int)$event;
    $paramCount++;
}

// Tipo de evento (solo para riplog)
if ($event !== '' && $type === 'riplog') {
    $whereConditions[] = "evento = $" . $paramCount;
    $params[] = $event;
    $paramCount++;
}

// Registros seleccionados
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

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$order_by = $_GET['order_by'] ?? ($type === 'riplog' ? 'fecha,hora' : 'fecha1');
$order_dir = strtoupper($_GET['order_dir'] ?? 'DESC');

$allowedColumns = [
    'riplog' => ['id', 'archivo', 'evento', 'ancho', 'largo', 'copias', 'fecha', 'hora', 'pc_name'],
    'history' => ['id', 'bmppath', 'anchomm', 'largomm', 'copiasrequeridas', 'completado', 'produccion', 'fecha1', 'fecha2', 'pc_name'],
    'record' => ['id', 'bmppath', 'anchomm', 'largomm', 'copiasrequeridas', 'completado', 'produccion', 'fecha1', 'fecha2', 'pc_name']
];

if (!in_array($order_by, $allowedColumns[$type])) {
    $order_by = $type === 'riplog' ? 'fecha,hora' : 'fecha1';
}
if ($order_dir !== 'ASC') {
    $order_dir = 'DESC';
}

// Consulta según tipo
if ($type === 'riplog') {
    $query = "
        SELECT 
            id, evento, archivo, ancho, largo, copias, fecha, hora, pc_name,
            CASE WHEN ancho >= 60 OR largo >= 60 THEN GREATEST(ancho, largo) ELSE LEAST(ancho, largo) END AS dimension,
            (CASE WHEN ancho >= 60 OR largo >= 60 THEN GREATEST(ancho, largo) ELSE LEAST(ancho, largo) END * copias) / 100 AS ml_total,
            (ancho * largo * copias) / 10000 AS m2_total
        FROM riplog
        $whereClause
        ORDER BY $order_by $order_dir
        LIMIT $limit
    ";
    
    $statsQuery = "
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN evento = 'RIP' THEN 1 END) as rip_count,
            COUNT(CASE WHEN evento = 'PRINT' THEN 1 END) as print_count,
            SUM(copias) as total_copies,
            COUNT(DISTINCT pc_name) as unique_pcs,
            SUM((CASE WHEN ancho >= 60 OR largo >= 60 THEN GREATEST(ancho, largo) ELSE LEAST(ancho, largo) END * copias) / 100) as ml_total,
            SUM((ancho * largo * copias) / 10000) as m2_total
        FROM riplog
        $whereClause
    ";

} elseif ($type === 'history') {
    $query = "
        SELECT 
            id,
            codigoimagen,
            bmppath,
            anchomm,
            largomm,
            anchomm2,
            largomm2,
            anchomm3,
            copiasrequeridas,
            completado,
            produccion,
            velocidadm2h,
            velocidadlineal,
            modoimpresion,
            fecha1,
            fecha2,
            tiempotranscurrido1,
            tiempotranscurrido2,
            uid,
            pc_name,
            largototal,
            largoimpreso,
            copiasimpresas,
            largototal / 1000 AS ml_total,
            (anchomm * largomm) / 1000000 AS m2_total
        FROM \"HistoryTasks\"
        $whereClause
        ORDER BY $order_by $order_dir
        LIMIT $limit
    ";

    $statsQuery = "
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN completado = 1 THEN 1 END) as completed_count,
            COUNT(CASE WHEN completado = 0 THEN 1 END) as incomplete_count,
            SUM(largototal / 1000) as ml_total,
            SUM(anchomm * largomm / 1000000) as m2_total,
            COUNT(DISTINCT pc_name) as unique_pcs
        FROM \"HistoryTasks\"
        $whereClause
    ";

} else { // record
    $query = "
        SELECT 
            id,
            codigoimagen,
            bmppath,
            anchomm,
            largomm,
            anchomm2,
            largomm2,
            anchomm3,
            copiasrequeridas,
            completado,
            produccion,
            velocidadm2h,
            velocidadlineal,
            modoimpresion,
            fecha1,
            fecha2,
            tiempotranscurrido1,
            tiempotranscurrido2,
            uid,
            pc_name,
            largototal,
            largoimpreso,
            copiasimpresas,
            largototal / 1000 AS ml_total,
            (anchomm * largomm) / 1000000 AS m2_total
        FROM \"RecordTasks\"
        $whereClause
        ORDER BY $order_by $order_dir
        LIMIT $limit
    ";

    $statsQuery = "
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN completado = 1 THEN 1 END) as completed_count,
            COUNT(CASE WHEN completado = 0 THEN 1 END) as incomplete_count,
            SUM(largototal / 1000) as ml_total,
            SUM(anchomm * largomm / 1000000) as m2_total,
            COUNT(DISTINCT pc_name) as unique_pcs
        FROM \"RecordTasks\"
        $whereClause
    ";
}

$result = pg_query_params($conn, $query, $params);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error en consulta: ' . pg_last_error($conn),
        'query' => $query,
        'params' => $params
    ]);
    exit;
}

$rows = [];
while ($row = pg_fetch_assoc($result)) {
    if ($type === 'riplog') {
        $rows[] = [
            'id' => (int)$row['id'],
            'evento' => $row['evento'],
            'archivo' => $row['archivo'],
            'ancho' => $row['ancho'] ? (float)$row['ancho'] : null,
            'largo' => $row['largo'] ? (float)$row['largo'] : null,
            'copias' => $row['copias'] ? (int)$row['copias'] : 1,
            'fecha' => $row['fecha'],
            'hora' => $row['hora'],
            'pc_name' => $row['pc_name'],
            'ml_total' => round($row['ml_total'], 2),
            'm2_total' => round($row['m2_total'], 2)
        ];
    } else {
        $anchoCm = $row['anchomm'] ? round($row['anchomm'] / 10, 1) : null;
        $largoCm = $row['largomm'] ? round($row['largomm'] / 10, 1) : null;
        $duracionSegundos = $row['tiempotranscurrido2'] ? intval($row['tiempotranscurrido2'] / 1000) : 0;
        $horas = floor($duracionSegundos / 3600);
        $minutos = floor(($duracionSegundos % 3600) / 60);
        $segundos = $duracionSegundos % 60;
        $duracionFormat = sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);

        $rows[] = [
            'id' => (int)$row['id'],
            'codigoimagen' => (int)$row['codigoimagen'],
            'bmppath' => $row['bmppath'] ?: '-',
            'ancho_cm' => $anchoCm,
            'largo_cm' => $largoCm,
            'copias_requeridas' => $row['copiasrequeridas'] ? (int)$row['copiasrequeridas'] : 1,
            'copias_impresas' => $row['copiasimpresas'] ? (float)$row['copiasimpresas'] : 0,
            'completado' => (int)$row['completado'],
            'produccion' => $row['produccion'] ? (float)$row['produccion'] : 0.0,
            'modoimpresion' => $row['modoimpresion'] ?: '-',
            'fecha1' => $row['fecha1'] ?: null,
            'fecha2' => $row['fecha2'] ?: null,
            'duracion' => $duracionFormat,
            'uid' => $row['uid'] ?: '-',
            'pc_name' => $row['pc_name'] ?: '-',
            'largototal' => $row['largototal'] ? (float)$row['largototal'] : 0.0,
            'ml_total' => round($row['ml_total'], 2),
            'm2_total' => round($row['m2_total'], 2)
        ];
    }
}

$stats_result = pg_query_params($conn, $statsQuery, $params);
if (!$stats_result) {
    $stats = [];
} else {
    $stats = pg_fetch_assoc($stats_result);
}

$pcs_list = [];
if ($type === 'riplog') {
    $pcQuery = "SELECT DISTINCT pc_name FROM riplog WHERE pc_name IS NOT NULL ORDER BY pc_name";
} else {
    $pcQuery = "SELECT DISTINCT pc_name FROM \"{$type === 'history' ? 'HistoryTasks' : 'RecordTasks'}\" WHERE pc_name IS NOT NULL ORDER BY pc_name";
}
$pcResult = pg_query($conn, $pcQuery);
while ($pcRow = pg_fetch_assoc($pcResult)) {
    $pcs_list[] = $pcRow['pc_name'];
}

echo json_encode([
    'data' => $rows,
    'stats' => $stats,
    'pcs_list' => $pcs_list,
    'type' => $type
]);

pg_close($conn);
?>