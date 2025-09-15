<?php
// get_image.php - Carga la imagen de impresión desde ImpresionesImagenes
include "config.php";

// Validar parámetros
$codigoImagen = $_GET['codigoimagen'] ?? '';
$pcName = $_GET['pc_name'] ?? '';

if (empty($codigoImagen) || !is_numeric($codigoImagen)) {
    http_response_code(400);
    header('Content-Type: image/png');
    echo file_get_contents('images/placeholder.png'); // Imagen por defecto
    exit;
}

if (empty($pcName)) {
    http_response_code(400);
    header('Content-Type: image/png');
    echo file_get_contents('images/placeholder.png');
    exit;
}

// Preparar consulta segura
$query = "
    SELECT imagen_jpg 
    FROM \"ImpresionesImagenes\" 
    WHERE codigoimagen = $1 AND pc_name = $2
    LIMIT 1
";

$params = [(int)$codigoImagen, $pcName];

$result = pg_query_params($conn, $query, $params);

if (!$result) {
    http_response_code(500);
    header('Content-Type: image/png');
    echo file_get_contents('images/placeholder.png');
    exit;
}

$row = pg_fetch_assoc($result);

if (!$row || empty($row['imagen_jpg'])) {
    http_response_code(404);
    header('Content-Type: image/png');
    echo file_get_contents('images/placeholder.png');
    exit;
}

// Obtener la imagen en formato BYTEA y convertirla a base64
$imageData = pg_unescape_bytea($row['imagen_jpg']);

// Detectar tipo MIME (solo JPEG o PNG soportados)
$mime = mime_content_type('data://application/octet-stream,' . urlencode($imageData));
if ($mime !== 'image/jpeg' && $mime !== 'image/jpg' && $mime !== 'image/png') {
    $mime = 'image/jpeg'; // Por defecto, asumimos JPEG
}

// Establecer headers correctos
header('Content-Type: ' . $mime);
header('Cache-Control: max-age=3600'); // Cache 1 hora
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Enviar la imagen directamente (sin base64 encoding en HTTP, porque es más eficiente)
echo $imageData;

pg_close($conn);
exit;
?>