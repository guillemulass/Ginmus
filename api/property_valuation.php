<?php
/**
 * @file api/property_valuation.php
 * @description Endpoint para la valoración de propiedades.
 * Recibe datos de un inmueble en JSON (POST), los pasa a un script de Python
 * que ejecuta un modelo de Machine Learning, y devuelve la predicción.
 */

// --- CONFIGURACIÓN DE ERRORES Y CABECERAS ---
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/../php_error.log');
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- VALIDACIÓN DE LA PETICIÓN HTTP ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// --- PROCESAMIENTO DE LA ENTRADA ---
$input_json = file_get_contents('php://input');
$input_data = json_decode($input_json, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input_data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos de entrada inválidos.']);
    exit;
}

// --- CONFIGURACIÓN DE LA EJECUCIÓN DE PYTHON ---
// Define el ejecutable de Python. Esto puede variar según el servidor.
$python_executable = 'python3'; 
$python_script_path = realpath(__DIR__ . '/../scripts_python/predict_price.py');

if (!$python_script_path) {
    error_log("CRITICAL: El script de Python 'predict_price.py' no se encontró.");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Error interno del servidor: Script de predicción no encontrado."]);
    exit;
}

// Se usan `escapeshellarg` para prevenir la inyección de comandos, una medida de seguridad CRÍTICA.
$command = escapeshellarg($python_executable) . ' ' . escapeshellarg($python_script_path);

// --- LÓGICA DE EJECUCIÓN DEL SCRIPT EXTERNO ---
// `proc_open` permite una comunicación bidireccional con el proceso de Python.
$descriptorspec = [
   0 => ['pipe', 'r'],  // stdin es una tubería de la que el hijo leerá
   1 => ['pipe', 'w'],  // stdout es una tubería a la que el hijo escribirá
   2 => ['pipe', 'w']   // stderr es una tubería a la que el hijo escribirá los errores
];
$proc = proc_open($command, $descriptorspec, $pipes);

if (is_resource($proc)) {
    // 1. Escribir los datos JSON de entrada al stdin del script de Python.
    fwrite($pipes[0], $input_json);
    fclose($pipes[0]);

    // 2. Leer la salida estándar (stdout) y la salida de error (stderr) del script.
    $python_output_json = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $python_error_output = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    // 3. Cerrar el proceso y obtener su código de salida.
    $exit_code = proc_close($proc);

    // --- RESPUESTA AL CLIENTE ---
    if ($exit_code === 0 && !empty($python_output_json)) {
        $prediction_result = json_decode($python_output_json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // El script se ejecutó correctamente y devolvió un JSON válido.
            echo json_encode($prediction_result);
        } else {
            // El script se ejecutó, pero su salida no fue un JSON válido.
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Respuesta inválida del script de Python.', 'debug_python_stdout' => $python_output_json, 'debug_python_stderr' => $python_error_output]);
        }
    } else {
        // El script de Python falló (código de salida no es 0) o no produjo salida.
        error_log("Error al ejecutar script de Python. Código: {$exit_code}. Stderr: {$python_error_output}");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al ejecutar el script de predicción.', 'debug_info' => ['exit_code' => $exit_code, 'python_stderr' => $python_error_output]]);
    }
} else {
    error_log("CRITICAL: No se pudo iniciar el proceso del script de Python usando proc_open.");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo iniciar el proceso del script de Python.']);
}
?>