<?php
// export.php - Versión final para printologweb
include "config.php";

// Incluir TCPDF si se usa PDF
require_once 'tcpdf/tcpdf.php';

// Validar tabla solicitada
$allowedTables = ['HistoryTasks', 'RecordTasks'];
$table = $_GET['table'] ?? 'HistoryTasks';
if (!in_array($table, $allowedTables)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tabla no válida. Solo se permiten: HistoryTasks, RecordTasks']);
    exit;
}

// Obtener parámetros
$dateFrom = $_GET['dateFrom'] ?? '';
$dateTo = $_GET['dateTo'] ?? '';
$filename = $_GET['filename'] ?? '';
$filenameLogic = $_GET['filenameLogic'] ?? 'or';
$pcs = $_GET['pcs'] ?? '';
$event = $_GET['event'] ?? '';
$format = $_GET['format'] ?? 'excel';
$selected = $_GET['selected'] ?? '';

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

// Consulta principal: unir ambas tablas
$query = "
    SELECT 
        id,
        codigoimagen,
        bmppath,
        anchomm,
        largomm,
        copiasrequeridas,
        completado,
        produccion,
        modoimpresion,
        fecha1,
        fecha2,
        tiempotranscurrido1,
        tiempotranscurrido2,
        uid,
        pc_name,
        largototal,
        largoimpreso,
        copiasimpresas
    FROM \"{$table}\"
    {$whereClause}
    ORDER BY fecha1 DESC, id DESC
";

$result = pg_query_params($conn, $query, $params);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en consulta: ' . pg_last_error($conn)]);
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
        'ancho_cm' => $anchoCm,
        'largo_cm' => $largoCm,
        'copias_requeridas' => $row['copiasrequeridas'] ? (int)$row['copiasrequeridas'] : 1,
        'copias_impresas' => $row['copiasimpresas'] ? (float)$row['copiasimpresas'] : 0,
        'completado' => (int)$row['completado'],
        'produccion' => $row['produccion'] ? (float)$row['produccion'] : 0.0,
        'modoimpresion' => $row['modoimpresion'] ?: '-',
        'fecha1' => $row['fecha1'] ?: '-',
        'fecha2' => $row['fecha2'] ?: '-',
        'duracion' => $duracionFormat,
        'uid' => $row['uid'] ?: '-',
        'pc_name' => $row['pc_name'] ?: '-',
        'ml_total' => $mlTotal,
        'm2_total' => $m2Total
    ];
}

// Generar archivo según formato
switch ($format) {

    case 'csv':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="printologweb_export.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'ID', 'Código Imagen', 'Archivo BMP', 'Ancho (cm)', 'Largo (cm)',
            'Copias Enviadas', 'Copias Impresas', 'Estado', 'Producción (%)', 
            'Modo Impresión', 'Fecha Inicio', 'Fecha Fin', 'Duración', 'UID', 'PC', 
            'ML Total (m)', 'M² Total'
        ]);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['codigoimagen'],
                $row['bmppath'],
                $row['ancho_cm'],
                $row['largo_cm'],
                $row['copias_requeridas'],
                $row['copias_impresas'],
                $row['completado'] == 1 ? 'Completada' : 'Incompleta',
                number_format($row['produccion'], 1, '.', ''),
                $row['modoimpresion'],
                $row['fecha1'],
                $row['fecha2'],
                $row['duracion'],
                $row['uid'],
                $row['pc_name'],
                $row['ml_total'],
                $row['m2_total']
            ]);
        }
        fclose($output);
        exit;

    case 'excel':
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="printologweb_export.xlsx"');
        echo "<table border='1'>
            <thead>
                <tr style='background-color: #f0f0f0;'>
                    <th>ID</th><th>Código Imagen</th><th>Archivo BMP</th><th>Ancho (cm)</th><th>Largo (cm)</th>
                    <th>Copias Enviadas</th><th>Copias Impresas</th><th>Estado</th><th>Producción (%)</th>
                    <th>Modo Impresión</th><th>Fecha Inicio</th><th>Fecha Fin</th><th>Duración</th>
                    <th>UID</th><th>PC</th><th>ML Total (m)</th><th>M² Total</th>
                </tr>
            </thead>
            <tbody>";
        foreach ($rows as $row) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['codigoimagen']}</td>
                <td>" . htmlspecialchars($row['bmppath']) . "</td>
                <td>" . ($row['ancho_cm'] ?: '-') . "</td>
                <td>" . ($row['largo_cm'] ?: '-') . "</td>
                <td>{$row['copias_requeridas']}</td>
                <td>" . number_format($row['copias_impresas'], 2, '.', '') . "</td>
                <td>" . ($row['completado'] == 1 ? 'Completada' : 'Incompleta') . "</td>
                <td>" . number_format($row['produccion'], 1, '.', '') . "%</td>
                <td>" . htmlspecialchars($row['modoimpresion']) . "</td>
                <td>" . htmlspecialchars($row['fecha1']) . "</td>
                <td>" . htmlspecialchars($row['fecha2']) . "</td>
                <td>{$row['duracion']}</td>
                <td>" . htmlspecialchars($row['uid']) . "</td>
                <td>" . htmlspecialchars($row['pc_name']) . "</td>
                <td>" . number_format($row['ml_total'], 2, '.', '') . "</td>
                <td>" . number_format($row['m2_total'], 2, '.', '') . "</td>
            </tr>";
        }
        echo "</tbody></table>";
        exit;

    case 'pdf':
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetTitle('Exportación PrintologWeb');
        $pdf->SetAuthor('PrintologWeb Dashboard');
        $pdf->SetHeaderData('', 0, 'Exportación de Impresiones - PrintologWeb', '');
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->AddPage();

        $html = '
            <table border="1" cellpadding="3" style="font-size: 7pt; width: 100%;">
                <thead>
                    <tr style="background-color: #f0f0f0;">
                        <th style="width: 5%;">ID</th>
                        <th style="width: 8%;">Cód. Img.</th>
                        <th style="width: 20%;">Archivo BMP</th>
                        <th style="width: 8%;">Ancho (cm)</th>
                        <th style="width: 8%;">Largo (cm)</th>
                        <th style="width: 6%;">Copias Env.</th>
                        <th style="width: 6%;">Copias Imp.</th>
                        <th style="width: 8%;">Estado</th>
                        <th style="width: 6%;">Producción (%)</th>
                        <th style="width: 10%;">Modo</th>
                        <th style="width: 12%;">Fecha Inicio</th>
                        <th style="width: 12%;">Fecha Fin</th>
                        <th style="width: 6%;">Duración</th>
                        <th style="width: 8%;">PC</th>
                        <th style="width: 6%;">ML Total</th>
                        <th style="width: 6%;">M² Total</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($rows as $row) {
            $estado = $row['completado'] == 1 ? 'Completada' : 'Incompleta';
            $produccion = number_format($row['produccion'], 1, '.', '');
            $ml = number_format($row['ml_total'], 2, '.', '');
            $m2 = number_format($row['m2_total'], 2, '.', '');

            $html .= '<tr>
                <td>' . $row['id'] . '</td>
                <td>' . $row['codigoimagen'] . '</td>
                <td>' . htmlspecialchars($row['bmppath']) . '</td>
                <td>' . ($row['ancho_cm'] ?: '-') . '</td>
                <td>' . ($row['largo_cm'] ?: '-') . '</td>
                <td>' . $row['copias_requeridas'] . '</td>
                <td>' . number_format($row['copias_impresas'], 2, '.', '') . '</td>
                <td>' . htmlspecialchars($estado) . '</td>
                <td>' . $produccion . '%</td>
                <td>' . htmlspecialchars($row['modoimpresion']) . '</td>
                <td>' . htmlspecialchars($row['fecha1']) . '</td>
                <td>' . htmlspecialchars($row['fecha2']) . '</td>
                <td>' . $row['duracion'] . '</td>
                <td>' . htmlspecialchars($row['pc_name']) . '</td>
                <td>' . $ml . '</td>
                <td>' . $m2 . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('printologweb_export.pdf', 'D');
        exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Formato no soportado. Usa: csv, excel o pdf']);
        exit;
}
?>