<?php
// export.php - Versión unificada para exportar ambos tipos
include "config.php";

require_once 'tcpdf/tcpdf.php';

$type = $_GET['type'] ?? 'printolog';
$format = $_GET['format'] ?? 'excel';
$selected = $_GET['selected'] ?? '';

$allowedTypes = ['riplog', 'printolog'];
if (!in_array($type, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo no válido. Usa: riplog o printolog']);
    exit;
}

// Filtros
$dateFrom = $_GET['dateFrom'] ?? '';
$dateTo = $_GET['dateTo'] ?? '';
$filename = $_GET['filename'] ?? '';
$filenameLogic = $_GET['filenameLogic'] ?? 'or';
$pcs = $_GET['pcs'] ?? '';
$event = $_GET['event'] ?? '';

$whereConditions = [];
$params = [];
$paramCount = 1;

if ($dateFrom && $dateTo) {
    $dateFromFull = $dateFrom . ' 00:00:00+00';
    $dateToFull = $dateTo . ' 23:59:59+00';
    $whereConditions[] = "fecha1 BETWEEN $" . $paramCount . " AND $" . ($paramCount + 1);
    $params[] = $dateFromFull;
    $params[] = $dateToFull;
    $paramCount += 2;
}

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

if ($event !== '' && $type === 'printolog') {
    $whereConditions[] = "completado = $" . $paramCount;
    $params[] = (int)$event;
    $paramCount++;
}

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

if ($type === 'riplog') {
    $query = "
        SELECT 
            id, evento, archivo, ancho, largo, copias, fecha, hora, pc_name,
            (CASE WHEN ancho >= 60 OR largo >= 60 THEN GREATEST(ancho, largo) ELSE LEAST(ancho, largo) END * copias) / 100 AS ml_total,
            (ancho * largo * copias) / 10000 AS m2_total
        FROM riplog
        $whereClause
        ORDER BY fecha DESC, hora DESC
    ";
} else {
    $query = "
        SELECT 
            h.id,
            h.codigoimagen,
            h.bmppath,
            h.anchomm,
            h.largomm,
            h.copiasrequeridas,
            h.completado,
            h.produccion,
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
            h.largototal / 1000 AS ml_total,
            (h.anchomm * h.largomm) / 1000000 AS m2_total
        FROM (
            SELECT * FROM \"HistoryTasks\"
            UNION ALL
            SELECT * FROM \"RecordTasks\"
        ) AS h
        $whereClause
        ORDER BY fecha1 DESC, id DESC
    ";
}

$result = pg_query_params($conn, $query, $params);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en consulta: ' . pg_last_error($conn)]);
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
            'fecha1' => $row['fecha1'] ?: '-',
            'fecha2' => $row['fecha2'] ?: '-',
            'duracion' => $duracionFormat,
            'uid' => $row['uid'] ?: '-',
            'pc_name' => $row['pc_name'] ?: '-',
            'ml_total' => round($row['ml_total'], 2),
            'm2_total' => round($row['m2_total'], 2)
        ];
    }
}

switch ($format) {

    case 'csv':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="printologweb_export.csv"');
        $output = fopen('php://output', 'w');
        if ($type === 'riplog') {
            fputcsv($output, [
                'ID', 'Evento', 'Archivo', 'Ancho (cm)', 'Largo (cm)', 'Copias', 'Fecha', 'Hora', 'PC', 'ML Total (m)', 'M² Total'
            ]);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['evento'],
                    $row['archivo'],
                    $row['ancho'],
                    $row['largo'],
                    $row['copias'],
                    $row['fecha'],
                    $row['hora'],
                    $row['pc_name'],
                    $row['ml_total'],
                    $row['m2_total']
                ]);
            }
        } else {
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
        }
        fclose($output);
        exit;

    case 'excel':
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="printologweb_export.xlsx"');
        echo "<table border='1'>";
        if ($type === 'riplog') {
            echo "<thead><tr><th>ID</th><th>Evento</th><th>Archivo</th><th>Ancho (cm)</th><th>Largo (cm)</th><th>Copias</th><th>Fecha</th><th>Hora</th><th>PC</th><th>ML Total (m)</th><th>M² Total</th></tr></thead>";
            echo "<tbody>";
            foreach ($rows as $row) {
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['evento']}</td>
                    <td>" . htmlspecialchars($row['archivo']) . "</td>
                    <td>" . ($row['ancho'] ?: '-') . "</td>
                    <td>" . ($row['largo'] ?: '-') . "</td>
                    <td>{$row['copias']}</td>
                    <td>{$row['fecha']}</td>
                    <td>{$row['hora']}</td>
                    <td>" . htmlspecialchars($row['pc_name']) . "</td>
                    <td>" . number_format($row['ml_total'], 2, '.', '') . "</td>
                    <td>" . number_format($row['m2_total'], 2, '.', '') . "</td>
                </tr>";
            }
        } else {
            echo "<thead><tr><th>ID</th><th>Cód. Img.</th><th>Archivo BMP</th><th>Ancho (cm)</th><th>Largo (cm)</th><th>Copias Env.</th><th>Copias Imp.</th><th>Estado</th><th>Producción (%)</th><th>Modo</th><th>Fecha Inicio</th><th>Fecha Fin</th><th>Duración</th><th>UID</th><th>PC</th><th>ML Total</th><th>M² Total</th></tr></thead>";
            echo "<tbody>";
            foreach ($rows as $row) {
                $estado = $row['completado'] == 1 ? 'Completada' : 'Incompleta';
                $produccion = number_format($row['produccion'], 1, '.', '');
                $ml = number_format($row['ml_total'], 2, '.', '');
                $m2 = number_format($row['m2_total'], 2, '.', '');
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['codigoimagen']}</td>
                    <td>" . htmlspecialchars($row['bmppath']) . "</td>
                    <td>" . ($row['ancho_cm'] ?: '-') . "</td>
                    <td>" . ($row['largo_cm'] ?: '-') . "</td>
                    <td>{$row['copias_requeridas']}</td>
                    <td>" . number_format($row['copias_impresas'], 2, '.', '') . "</td>
                    <td>" . htmlspecialchars($estado) . "</td>
                    <td>" . $produccion . "%</td>
                    <td>" . htmlspecialchars($row['modoimpresion']) . "</td>
                    <td>" . htmlspecialchars($row['fecha1']) . "</td>
                    <td>" . htmlspecialchars($row['fecha2']) . "</td>
                    <td>{$row['duracion']}</td>
                    <td>" . htmlspecialchars($row['uid']) . "</td>
                    <td>" . htmlspecialchars($row['pc_name']) . "</td>
                    <td>" . $ml . "</td>
                    <td>" . $m2 . "</td>
                </tr>";
            }
        }
        echo "</tbody></table>";
        exit;

    case 'pdf':
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetTitle('Exportación PrintologWeb');
        $pdf->SetAuthor('PrintologWeb');
        $pdf->SetHeaderData('', 0, 'Exportación de Impresiones y Procesos', '');
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

        $html = '<table border="1" cellpadding="3" style="font-size: 7pt; width: 100%;">';
        if ($type === 'riplog') {
            $html .= '
                <thead>
                    <tr style="background-color: #f0f0f0;">
                        <th>ID</th><th>Evento</th><th>Archivo</th><th>Ancho</th><th>Largo</th><th>Copias</th><th>Fecha</th><th>Hora</th><th>PC</th><th>ML Total</th><th>M² Total</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>
                    <td>' . $row['id'] . '</td>
                    <td>' . htmlspecialchars($row['evento']) . '</td>
                    <td>' . htmlspecialchars($row['archivo']) . '</td>
                    <td>' . ($row['ancho'] ?: '-') . '</td>
                    <td>' . ($row['largo'] ?: '-') . '</td>
                    <td>' . $row['copias'] . '</td>
                    <td>' . htmlspecialchars($row['fecha']) . '</td>
                    <td>' . htmlspecialchars($row['hora']) . '</td>
                    <td>' . htmlspecialchars($row['pc_name']) . '</td>
                    <td>' . number_format($row['ml_total'], 2, '.', '') . '</td>
                    <td>' . number_format($row['m2_total'], 2, '.', '') . '</td>
                </tr>';
            }
        } else {
            $html .= '
                <thead>
                    <tr style="background-color: #f0f0f0;">
                        <th>ID</th><th>Cód. Img.</th><th>Archivo BMP</th><th>Ancho (cm)</th><th>Largo (cm)</th>
                        <th>Copias Env.</th><th>Copias Imp.</th><th>Estado</th><th>Producción (%)</th>
                        <th>Modo</th><th>Fecha Inicio</th><th>Fecha Fin</th><th>Duración</th>
                        <th>UID</th><th>PC</th><th>ML Total</th><th>M² Total</th>
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
                    <td>' . htmlspecialchars($row['uid']) . '</td>
                    <td>' . htmlspecialchars($row['pc_name']) . '</td>
                    <td>' . $ml . '</td>
                    <td>' . $m2 . '</td>
                </tr>';
            }
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