<?php
// public_html/plataforma/api/environment_report.php - Backend para generar informes de entorno

session_start();
header('Content-Type: application/json');
// Permite CORS desde cualquier origen. En producción, habria que restringir esto al dominio por seguridad.
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Manejar petición de preflight OPTIONS (necesario para CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Función para limpiar el texto markdown
function cleanMarkdownText($text) {
    // Eliminar asteriscos de títulos y texto en negrita
    $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
    // Eliminar asteriscos simples (cursiva)
    $text = preg_replace('/\*(.*?)\*/', '$1', $text);
    // Eliminar hashtags de títulos
    $text = preg_replace('/#+\s*/', '', $text);
    // Eliminar guiones de listas y convertir a puntos
    $text = preg_replace('/^-\s+/m', '• ', $text);
    // Limpiar espacios extra
    $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);
    return trim($text);
}

// Cargar variables de entorno desde el archivo .env
$dotenv_path = __DIR__ . '/../.env';
if (file_exists($dotenv_path)) {
    $lines = file($dotenv_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Ignorar comentarios
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . '=' . trim($value));
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error de configuración del servidor: Faltan claves API (.env no encontrado).',
        'debug_info' => ['dotenv_path_checked' => $dotenv_path, 'file_exists' => false]
    ]);
    exit;
}

// Obtener las claves API
$google_maps_api_key = getenv('GOOGLE_MAPS_API_KEY');
$openrouter_api_key = getenv('OPENROUTER_API_KEY');
$helicone_api_key = getenv('HELICONE_API_KEY');

if (!$google_maps_api_key || !$openrouter_api_key) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de configuración: Claves API de Google Maps o OpenRouter no definidas en .env.',
        'debug_info' => [
            'google_maps_api_key_status' => empty($google_maps_api_key) ? 'NOT SET' : 'SET',
            'openrouter_api_key_status' => empty($openrouter_api_key) ? 'NOT SET' : 'SET'
        ]
    ]);
    exit;
}

// Obtener los datos JSON del cuerpo de la petición
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input) || !isset($input['address']) || !isset($input['radius'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Datos JSON inválidos o incompletos desde el cliente.',
        'debug_info' => [
            'json_error' => json_last_error_msg(),
            'received_input' => $input
        ]
    ]);
    exit;
}

$address = $input['address'];
$radius = (int)$input['radius'];

if (empty($address) || $radius <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Dirección o radio inválidos.',
        'debug_info' => [
            'address' => $address,
            'radius' => $radius
        ]
    ]);
    exit;
}

// --- Paso 1: Geocodificación de la dirección con Google Geocoding API ---
$geocode_url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=" . $google_maps_api_key;
$ch_geocode = curl_init();
curl_setopt($ch_geocode, CURLOPT_URL, $geocode_url);
curl_setopt($ch_geocode, CURLOPT_RETURNTRANSFER, true);
$geocode_response = curl_exec($ch_geocode);
$geocode_http_code = curl_getinfo($ch_geocode, CURLINFO_HTTP_CODE);
$geocode_curl_error = curl_error($ch_geocode);
curl_close($ch_geocode);

$geocode_data = json_decode($geocode_response, true);

if ($geocode_response === false || $geocode_http_code !== 200 || $geocode_data['status'] !== 'OK' || empty($geocode_data['results'])) {
    $error_message = $geocode_data['error_message'] ?? ($geocode_data['status'] ?? 'Respuesta inválida o desconocida');
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo encontrar la dirección. Por favor, sé más específico o verifica la ortografía.',
        'debug_info' => [
            'api_call' => 'Geocoding',
            'http_code' => $geocode_http_code,
            'curl_error' => $geocode_curl_error,
            'api_status' => $geocode_data['status'] ?? 'N/A',
            'api_error_message' => $error_message,
            'full_response' => $geocode_response
        ]
    ]);
    exit;
}

$location = $geocode_data['results'][0]['geometry']['location'];
$lat = $location['lat'];
$lng = $location['lng'];

// --- Paso 2: Búsqueda de lugares de interés cercanos con Google Places API ---
$places_types = [
    'restaurant', 'cafe', 'park', 'school', 'hospital', 'supermarket',
    'bus_station', 'train_station', 'pharmacy', 'atm', 'police',
    'fire_station', 'library', 'gym', 'shopping_mall', 'bank', 'university',
    'art_gallery', 'movie_theater', 'night_club', 'spa', 'zoo'
];
$nearby_places_info = [];

foreach ($places_types as $type) {
    $effective_radius = min($radius, 50000);
    $places_url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?location={$lat},{$lng}&radius={$effective_radius}&type={$type}&key={$google_maps_api_key}";
    
    $ch_places = curl_init();
    curl_setopt($ch_places, CURLOPT_URL, $places_url);
    curl_setopt($ch_places, CURLOPT_RETURNTRANSFER, true);
    $places_response = curl_exec($ch_places);
    $places_http_code = curl_getinfo($ch_places, CURLINFO_HTTP_CODE);
    $places_curl_error = curl_error($ch_places);
    curl_close($ch_places);

    $places_data = json_decode($places_response, true);

    if ($places_response === false || $places_http_code !== 200) {
        error_log("Places API cURL ERROR for type {$type}: HTTP {$places_http_code}, Curl Error: {$places_curl_error}, Response: " . substr($places_response, 0, 200));
        continue;
    }

    if ($places_data['status'] === 'OK' && !empty($places_data['results'])) {
        foreach ($places_data['results'] as $place) {
            $name = $place['name'] ?? 'Desconocido';
            $vicinity = $place['vicinity'] ?? 'Desconocido';
            $nearby_places_info[] = [
                'name' => $name,
                'type' => str_replace('_', ' ', $type),
                'address' => $vicinity
            ];
        }
    } elseif ($places_data['status'] !== 'ZERO_RESULTS') {
        error_log("Places API ERROR for type {$type}: Status: " . ($places_data['status'] ?? 'N/A') . ", Message: " . ($places_data['error_message'] ?? 'N/A') . ", Response: " . substr($places_response, 0, 200));
    }
}

// --- Paso 3: Generar informe con LLM (OpenRouter) ---
$llm_prompt = "Eres un asistente experto en análisis de entorno de propiedades en España. Genera un informe detallado y conciso del entorno para la propiedad ubicada en {$address} (Latitud: {$lat}, Longitud: {$lng}) con un radio de {$radius} metros. Incluye información sobre los siguientes puntos de interés encontrados:\n\n";

if (!empty($nearby_places_info)) {
    $llm_prompt .= "Puntos de interés cercanos:\n";
    $grouped_places = [];
    foreach ($nearby_places_info as $place) {
        $grouped_places[$place['type']][] = $place['name'] . " (" . $place['address'] . ")";
    }
    foreach ($grouped_places as $type => $places) {
        $llm_prompt .= "- " . ucfirst($type) . ": " . implode(", ", array_slice($places, 0, 5)) . (count($places) > 5 ? " y más..." : "") . "\n";
    }
} else {
    $llm_prompt .= "No se encontraron puntos de interés significativos en el radio especificado.\n";
}

$llm_prompt .= "\nPor favor, estructura el informe con las siguientes secciones claras y con un tono profesional y objetivo:
1. Resumen de la Ubicación: Breve descripción general de la zona.
2. Servicios Esenciales Cercanos: Farmacias, supermercados, bancos, hospitales, escuelas.
3. Transporte y Conectividad: Paradas de autobús, estaciones de tren, acceso a carreteras.
4. Ocio y Estilo de Vida: Restaurantes, cafeterías, parques, gimnasios, lugares de entretenimiento.
5. Análisis General del Entorno: Una conclusión que resuma los pros y contras del entorno para un potencial residente o inversor.

IMPORTANTE: 
La respuesta debe ser en español, en formato de texto plano sin ningún tipo de formato Markdown (sin asteriscos, hashtags, ni otros símbolos de formato). Solo texto limpio y bien estructurado con títulos numerados y listas simples usando guiones. 
Menciona 1-2 ejemplos destacados por categoría, priorizando los más cercanos y/o mejor valorados (si hay datos de rating).Tras esto lista 5 ejemplos de lugares destacados, por ejemplo: \"Entre los lugares destacados se encuentran: [Nombre del lugar 1] a [distancia] metros, [Nombre del lugar 2] a [distancia] metros, etc.\". Si no está disponible la distancia, obvia este dato.
Si no hay lugares en una categoría, menciona que \"no se encontraron lugares relevantes\" o \"no se identificaron servicios destacados\" para esa categoría.
NO inventes información. Basa todas las afirmaciones en los datos JSON proporcionados. 
NO incluyas el JSON original en tu respuesta. El informe debe fluir como una narrativa.";

$llm_api_url = "https://openrouter.ai/api/v1/chat/completions";
$llm_headers = [
    'Authorization: Bearer ' . $openrouter_api_key,
    'Content-Type: application/json',
    'HTTP-Referer: https://tu-dominio.com/plataforma',
    'X-Title: Ginmus Environment Report'
];
if (!empty($helicone_api_key)) {
    $llm_headers[] = "Helicone-Auth: Bearer " . $helicone_api_key;
}

$llm_data = [
    'model' => 'openai/gpt-4o',
    'messages' => [
        ['role' => 'user', 'content' => $llm_prompt]
    ],
    'temperature' => 0.7,
    'max_tokens' => 1200,
];

$ch_llm = curl_init($llm_api_url);
curl_setopt($ch_llm, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_llm, CURLOPT_POST, true);
curl_setopt($ch_llm, CURLOPT_POSTFIELDS, json_encode($llm_data));
curl_setopt($ch_llm, CURLOPT_HTTPHEADER, $llm_headers);

$llm_response = curl_exec($ch_llm);
$llm_http_code = curl_getinfo($ch_llm, CURLINFO_HTTP_CODE);
$llm_curl_error = curl_error($ch_llm);
curl_close($ch_llm);

$llm_result = json_decode($llm_response, true);

if ($llm_response === false || $llm_http_code !== 200 || !isset($llm_result['choices'][0]['message']['content'])) {
    $error_message = $llm_result['error']['message'] ?? 'No message content or unexpected LLM response structure.';
    echo json_encode([
        'success' => false,
        'message' => 'Error al generar el informe con IA.',
        'debug_info' => [
            'api_call' => 'LLM (OpenRouter)',
            'http_code' => $llm_http_code,
            'curl_error' => $llm_curl_error,
            'api_response_error' => $error_message,
            'full_response' => $llm_response
        ]
    ]);
    exit;
}

$generated_report = $llm_result['choices'][0]['message']['content'];

// Limpiar el texto de cualquier formato markdown que pueda haber
$clean_report = cleanMarkdownText($generated_report);

echo json_encode(['success' => true, 'report' => $clean_report]);
?>