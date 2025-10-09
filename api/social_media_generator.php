<?php
/**
 * @file api/social_media_generator.php
 * @description Endpoint para generar textos de marketing para redes sociales.
 * Recibe la descripción de una propiedad y las plataformas de destino,
 * y utiliza un LLM para crear contenido adaptado a cada una.
 */

// --- CONFIGURACIÓN DE ERRORES Y CABECERAS ---
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/../php_error.log');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");

// --- VALIDACIÓN DE LA PETICIÓN HTTP ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// --- PROCESAMIENTO DE LA ENTRADA ---
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($input['description']) || !isset($input['platforms'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos de entrada inválidos.']);
    exit;
}
$propertyDescription = $input['description'];
$platforms = $input['platforms'];

// --- CARGA DE VARIABLES DE ENTORNO ---
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[$name] = $value;
    }
}
$openrouterApiKey = $_ENV['OPENROUTER_API_KEY'] ?? null;
if (!$openrouterApiKey) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de configuración del servidor (API Key).']);
    exit;
}

// --- INGENIERÍA DE PROMPTS (PROMPT ENGINEERING) ---
$system_prompt = "Eres un experto en marketing inmobiliario y copywriting para redes sociales. Tu tarea es generar textos de venta para un inmueble a partir de los datos proporcionados. Debes adaptar el tono y formato a cada plataforma solicitada.";
$user_prompt = "Datos del inmueble:\n" . $propertyDescription . "\n\n";
$user_prompt .= "Genera los textos para las siguientes plataformas:\n";

$platform_instructions = [
    'facebook' => "- **Facebook:** Crea un texto para un público de 40-60 años. Usa un tono cercano y familiar. Destaca la comodidad, la ubicación y la vida tranquila. Longitud media. Usa 1 o 2 emojis apropiados (🏡🔑).",
    'instagram' => "- **Instagram:** Crea un texto para un público de 25-40 años. Muy visual y directo. Frases cortas y enérgicas. Menciona las fotos ('Imagina despertar aquí...'). Usa varios emojis de tendencia (✨💎☀️).",
    'portales' => "- **Portales (Idealista, etc.):** Crea un texto profesional, detallado y optimizado para SEO. Estructura en párrafos claros: un resumen atractivo, una descripción detallada de las estancias, calidades y extras. Termina con una llamada a la acción clara para concertar una visita. Tono formal y vendedor. No uses emojis."
];

foreach ($platforms as $platform) {
    if (isset($platform_instructions[$platform])) {
        $user_prompt .= $platform_instructions[$platform] . "\n";
    }
}
$user_prompt .= "\nIMPORTANTE: Devuelve tu respuesta exclusivamente como un objeto JSON válido. Las claves del JSON deben ser los nombres de las plataformas en minúsculas ('facebook', 'instagram', 'portales'). No incluyas nada más fuera del objeto JSON.";

// --- SECCIÓN RESTAURADA: LLAMADA AL LLM ---
$llmApiUrl = "https://openrouter.ai/api/v1/chat/completions";
$payload = [
    'model' => 'openai/gpt-4o',
    'messages' => [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $user_prompt]
    ],
    'response_format' => ['type' => 'json_object'],
    'temperature' => 0.7
];
$headers = [
    'Authorization: Bearer ' . $openrouterApiKey,
    'Content-Type: application/json',
];

$ch = curl_init($llmApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
// NOTA: Deshabilitar la verificación SSL es útil para entornos de desarrollo locales.
// En un servidor de producción con un certificado SSL válido, esto debería eliminarse o establecerse en `true`.
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    error_log("Error LLM - HTTP: $httpCode, cURL: $curlError, Response: $response");
    http_response_code(502); // 502 Bad Gateway (indica un problema con el servicio externo)
    echo json_encode(['success' => false, 'message' => 'Error al comunicarse con el servicio de IA.']);
    exit;
}

$responseData = json_decode($response, true);
$contentString = $responseData['choices'][0]['message']['content'] ?? null;

if (!$contentString) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'La IA no devolvió contenido válido.']);
    exit;
}

$generated_texts = json_decode($contentString, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Error de JSON devuelto por la IA: " . $contentString);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'La IA devolvió un formato JSON inválido.']);
    exit;
}

// --- RESPUESTA AL CLIENTE ---
echo json_encode(['success' => true, 'data' => $generated_texts]);
?>