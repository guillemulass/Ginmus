<?php
/**
 * @file api/chatbot_RAG_agent.php
 * @description Endpoint para un Chatbot Agente con lógica ReAct y búsqueda web en tiempo real.
 */

// --- CONFIGURACIÓN Y CABECERAS ---
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/../php_error.log');
error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Método no permitido.']));
}
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($input['message'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Formato de petición inválido.']));
}
$userMessage = $input['message'];

// --- CARGA DE VARIABLES DE ENTORNO ---
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}
$openrouterApiKey = $_ENV['OPENROUTER_API_KEY'] ?? null;
$tavilyApiKey = $_ENV['TAVILY_API_KEY'] ?? null;
if (!$openrouterApiKey || !$tavilyApiKey) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => 'Error de configuración del servidor. Faltan claves API. Revisa tu archivo .env.']));
}

// ===================================================================
// == DEFINICIÓN DE HERRAMIENTAS
// ===================================================================
function buscar_en_anuncios($query) {
    $csvFilePath = __DIR__ . '/../anuncios.csv';
    if (!file_exists($csvFilePath)) return "Error: Archivo de anuncios no encontrado.";
    $contexto = "";
    $keywords = preg_split('/\s+/', strtolower($query));
    $found_count = 0;
    if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
        $headers = fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== FALSE && $found_count < 3) {
            if (count($headers) !== count($data)) continue;
            $row = array_combine($headers, $data);
            $row_text = strtolower(implode(" ", $row));
            foreach ($keywords as $keyword) {
                if (strlen($keyword) > 3 && str_contains($row_text, $keyword)) {
                    $contexto .= "- " . ($row['Título'] ?? 'Anuncio') . " en " . ($row['Ubicación'] ?? 'N/A') . " por " . ($row['Precio'] ?? 'N/A') . ".\n";
                    $found_count++;
                    break;
                }
            }
        }
        fclose($handle);
    }
    return $contexto ?: "No se encontraron anuncios relevantes para la consulta '{$query}'.";
}

function buscar_en_documentos($query) {
    $knowledgeFilePath = __DIR__ . '/../conocimiento.txt';
    if (!file_exists($knowledgeFilePath)) return "Error: Base de conocimiento no encontrada.";
    $contexto = "";
    $content = file_get_contents($knowledgeFilePath);
    $chunks = explode('---', $content);
    $keywords = preg_split('/\s+/', strtolower($query));
    foreach ($chunks as $chunk) {
        $chunk_lower = strtolower($chunk);
        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 4 && str_contains($chunk_lower, $keyword)) {
                $contexto .= "Sección relevante encontrada:\n" . trim($chunk) . "\n\n";
                return $contexto;
            }
        }
    }
    return "No se encontró información relevante en los documentos para la consulta '{$query}'.";
}

// --- ÚLTIMO INTENTO: Usando la cabecera Authorization: Bearer ---
function buscar_en_la_web($query, $apiKey) {
    $tavilyApiUrl = "https://api.tavily.com/search";
    
    $payload = json_encode([
        'api_key' => $apiKey, // Algunos servicios requieren la clave aquí también cuando se usa Bearer
        'query' => $query,
        'search_depth' => 'basic',
        'max_results' => 3
    ]);

    // --- CAMBIO PRINCIPAL ---
    // En lugar de 'x-tavily-api-key', usamos 'Authorization: Bearer'.
    // Esto a veces evita problemas con proxies/firewalls.
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey 
    ];

    $ch = curl_init($tavilyApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return "Error de cURL al conectar con Tavily: " . $error;
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $data = json_decode($response, true);
        $errorMessage = $data['error'] ?? (is_array($data['detail']) ? json_encode($data['detail']) : ($data['detail'] ?? 'Respuesta inválida'));
        return "Error: Tavily API devolvió un estado HTTP {$httpCode}. Mensaje: {$errorMessage}";
    }

    $data = json_decode($response, true);
    if (empty($data['results'])) {
        return "No se encontraron resultados en la web para la consulta '{$query}'.";
    }

    $contexto = "Resultados de la búsqueda web:\n";
    foreach ($data['results'] as $result) {
        $contexto .= "- Fuente: " . ($result['url'] ?? 'N/A') . "\n";
        $contexto .= "  Contenido: " . ($result['content'] ?? 'N/A') . "\n\n";
    }
    return $contexto;
}

// ===================================================================
// == LÓGICA DEL AGENTE (Sin cambios)
// ===================================================================
$thinking_process = [];

$router_prompt = "
Eres un agente 'router'. Tu trabajo es analizar la pregunta del usuario y decidir qué herramienta usar. Responde EXCLUSIVAMENTE con un objeto JSON que contenga 'tool' y 'query'.

Herramientas disponibles:
1. `buscar_anuncios`: Úsala si el usuario pregunta por propiedades específicas, listados, pisos, casas, alquileres o ventas en una zona. El 'query' debe ser la descripción de la búsqueda.
2. `buscar_documentos`: Úsala si la pregunta es sobre leyes (LAU, LPH), contratos, gastos, hipotecas, o procesos de compra/alquiler. El 'query' debe ser el tema a buscar.
3. `buscar_en_la_web`: Úsala para preguntas sobre actualidad, eventos, noticias o información general que no se encuentre en los documentos locales (ej. '¿Qué tiempo hace en Cádiz?', '¿Cuándo empieza el COAC 2025?'). El 'query' debe ser la pregunta del usuario.
4. `respuesta_directa`: Úsala para saludos, preguntas generales que no requieren búsqueda, o si la pregunta no está relacionada con el sector inmobiliario. El 'query' debe ser la pregunta original.

Ejemplo de respuesta para la pregunta 'busco piso de 3 habitaciones en Cádiz':
{\"tool\": \"buscar_anuncios\", \"query\": \"piso 3 habitaciones en Cádiz\"}

Pregunta del usuario: \"{$userMessage}\"

Tu respuesta JSON:
";

$router_payload = [
    'model' => 'openai/gpt-4o',
    'messages' => [['role' => 'user', 'content' => $router_prompt]],
    'response_format' => ['type' => 'json_object']
];
$router_response_json = call_llm($router_payload, $openrouterApiKey);
$router_decision = json_decode($router_response_json, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($router_decision['tool']) || !isset($router_decision['query'])) {
    exit(json_encode(['success' => false, 'error' => 'El agente no pudo decidir una acción o el formato de decisión es incorrecto.']));
}
$thinking_process[] = [
    "type" => "reasoning",
    "title" => "Decidiendo qué hacer...",
    "content" => "He analizado la pregunta y he decidido que la mejor acción es usar la herramienta `{$router_decision['tool']}` para buscar sobre '{$router_decision['query']}'."
];

$tool_result = "";
$final_response = "";

switch ($router_decision['tool']) {
    case 'buscar_anuncios':
        $tool_result = buscar_en_anuncios($router_decision['query']);
        $thinking_process[] = ["type" => "tool_result", "title" => "Buscando en la base de datos de anuncios 🔎", "content" => $tool_result];
        break;
    case 'buscar_documentos':
        $tool_result = buscar_en_documentos($router_decision['query']);
        $thinking_process[] = ["type" => "tool_result", "title" => "Consultando la base de conocimiento 📚", "content" => $tool_result];
        break;
    case 'buscar_en_la_web':
        $tool_result = buscar_en_la_web($router_decision['query'], $tavilyApiKey);
        $thinking_process[] = ["type" => "tool_result", "title" => "Buscando en internet en tiempo real 🌐", "content" => $tool_result];
        break;
    default: // respuesta_directa
        $synthesizer_prompt = "Eres un experto en el sector inmobiliario en España. Responde de forma amable y concisa a la siguiente pregunta del usuario. Si la pregunta no es sobre inmobiliaria, responde cortésmente que solo puedes ayudar con temas del sector.\n\nPregunta: \"{$userMessage}\"";
        $final_response = call_llm(['model' => 'openai/gpt-4o', 'messages' => [['role' => 'user', 'content' => $synthesizer_prompt]]], $openrouterApiKey);
        break;
}

if (empty($final_response)) {
    $synthesizer_prompt = "
    Eres un experto en el sector inmobiliario en España. Tu tarea es responder a la pregunta del usuario usando la información de contexto que se te ha proporcionado. Basa tu respuesta PRINCIPALMENTE en este contexto.

    Contexto Obtenido de la Herramienta `{$router_decision['tool']}`:
    ---
    {$tool_result}
    ---

    Pregunta Original del Usuario:
    \"{$userMessage}\"

    Ahora, formula una respuesta clara y útil para el usuario.
    ";
    $final_response = call_llm(['model' => 'openai/gpt-4o', 'messages' => [['role' => 'user', 'content' => $synthesizer_prompt]]], $openrouterApiKey);
}

echo json_encode([
    'success' => true,
    'thinking_process' => $thinking_process,
    'final_response' => $final_response
]);

function call_llm($payload, $apiKey) {
    $llmApiUrl = "https://openrouter.ai/api/v1/chat/completions";
    $headers = ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'];
    $ch = curl_init($llmApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) { return "Error: No se pudo contactar con la IA."; }
    $responseData = json_decode($response, true);
    return $responseData['choices'][0]['message']['content'] ?? "La IA no devolvió una respuesta válida.";
}
?>