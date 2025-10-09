<?php
/**
 * @file api/get_anuncios_csv.php
 * @description Endpoint para leer un archivo CSV de anuncios y devolver su contenido
 * en formato JSON. Se utiliza para alimentar el módulo de Búsqueda de Clientes.
 */

// --- CONFIGURACIÓN DE ERRORES Y CABECERAS ---
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/../php_error.log');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");

// --- VALIDACIÓN DE LA PETICIÓN HTTP ---
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// --- LÓGICA DE LECTURA DE ARCHIVO ---
$csvFilePath = __DIR__ . '/../anuncios.csv';

if (!file_exists($csvFilePath) || !is_readable($csvFilePath)) {
    error_log("ERROR: Archivo CSV no encontrado o sin permisos en: " . $csvFilePath);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: Archivo de datos no encontrado.']);
    exit;
}

$data = [];
$headers = [];
$lastModified = null;

// Obtener la fecha de última modificación del archivo.
$lastModifiedTimestamp = filemtime($csvFilePath);
if ($lastModifiedTimestamp !== false) {
    $lastModified = date('Y-m-d H:i:s', $lastModifiedTimestamp);
}

// Abrir el archivo CSV para lectura.
if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
    // Leer la primera línea para obtener las cabeceras.
    $csv_headers = fgetcsv($handle, 1000, ",");
    if ($csv_headers === FALSE) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al procesar el archivo CSV: No se pudieron leer los encabezados.']);
        fclose($handle);
        exit;
    }
    $headers = array_map('trim', $csv_headers);

    // Leer el resto de las filas.
    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        // Asegurarse de que el número de columnas de la fila coincida con el de las cabeceras
        // para evitar errores y manejar filas malformadas.
        if (count($headers) === count($row)) {
            // Combinar las cabeceras con la fila para crear un array asociativo.
            $data[] = array_combine($headers, $row);
        }
    }
    fclose($handle);

    // --- RESPUESTA AL CLIENTE ---
    echo json_encode([
        'success' => true,
        'headers' => $headers,
        'data' => $data,
        'lastModified' => $lastModified
    ]);
} else {
    error_log("ERROR: No se pudo abrir el archivo CSV para lectura: " . $csvFilePath);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: No se pudo leer el archivo de datos.']);
}
?>