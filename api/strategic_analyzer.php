<?php
// api/strategic_analyzer.php

// --- Configuración básica ---
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/../php_error.log');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");

// --- Carga de variables de entorno ---
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
    echo json_encode(['success' => false, 'message' => 'Error de configuración del servidor (API Key no encontrada).']);
    exit;
}
// Fin de la carga de entorno

// --- Recibir datos del frontend ---
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE || !$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos de entrada inválidos.']);
    exit;
}
$propertyData = $input;

// --- LÓGICA DEL AGENTE DE IA ---

// ===================================================================
// == TAREA 1: Análisis de Mercado (RAG) - IMPLEMENTACIÓN REAL      ==
// ===================================================================

$csv_path = __DIR__ . '/../anuncios.csv';
$comparables = find_comparables($csv_path, $propertyData);

$comparables_text = "";
if (!empty($comparables)) {
    $comparables_text = "Se han encontrado las siguientes propiedades comparables en la base de datos:\n";
    $count = 1;
    foreach ($comparables as $comp) {
        $comparables_text .= "- Comparable {$count}: " . ($comp['Título'] ?? 'Inmueble') . " en " . ($comp['Ubicación'] ?? 'ubicación similar') . " con un precio de " . ($comp['Precio'] ?? 'N/A') . ". Características: " . ($comp['Características'] ?? 'N/A') . "\n";
        $count++;
    }
} else {
    $comparables_text = "No se encontraron propiedades directamente comparables en la base de datos. El análisis se basará en conocimiento general del mercado.\n";
}

$market_analysis_prompt = "
Eres un analista de mercado inmobiliario. A continuación se presentan los datos de una propiedad y una lista de propiedades comparables extraídas de una base de datos local.

**Datos de la Propiedad a Analizar:**
- Tipo: {$propertyData['type']}
- Ubicación: {$propertyData['location']}
- Superficie: {$propertyData['area']} m²
- Habitaciones: {$propertyData['rooms']}
- Baños: {$propertyData['baths']}
- Estado: {$propertyData['state']}
- Características Adicionales: {$propertyData['features']}

**Datos de Mercado (Comparables encontrados):**
{$comparables_text}

**Tu Tarea:**
Genera un breve pero incisivo 'Análisis de Mercado'. Tu respuesta debe incluir:
1.  Un **Resumen de Mercado** que evalúe cómo se posiciona la propiedad a analizar frente a los comparables (si los hay) o el mercado general. Menciona si su precio parece competitivo o si sus características destacan.
2.  Una sección de **Puntos Clave** en formato de lista, destacando 2-3 observaciones importantes (ej. 'El precio por m² es competitivo para la zona', 'La falta de ascensor puede ser un factor limitante', etc.).
Formatea tu respuesta de manera clara y profesional. Usa Markdown para la negrita (**texto**).
";

$market_analysis_result = call_llm($market_analysis_prompt, $openrouterApiKey);

if ($market_analysis_result === null) {
    $market_analysis_result = "No se pudo generar el análisis de mercado debido a un error de comunicación con la IA.";
}


// ===================================================================
// == TAREA 2: Análisis F.O.D.A. - IMPLEMENTACIÓN REAL              ==
// ===================================================================

$swot_prompt = "
Eres un estratega inmobiliario experto. Tu tarea es realizar un análisis F.O.D.A. (Fortalezas, Oportunidades, Debilidades, Amenazas) para la siguiente propiedad.

**Contexto Previo (Datos y Análisis de Mercado):**
- **Propiedad:** Tipo {$propertyData['type']} en {$propertyData['location']}, con {$propertyData['area']} m², {$propertyData['rooms']} habitaciones, {$propertyData['baths']} baños, en estado '{$propertyData['state']}'. Características extra: '{$propertyData['features']}'.
- **Análisis de Mercado Previo:** {$market_analysis_result}

**Tu Tarea:**
Basándote EXCLUSIVAMENTE en el contexto proporcionado, genera un análisis F.O.D.A. conciso. Estructura tu respuesta en cuatro puntos claros y utiliza un lenguaje directo y profesional. Usa Markdown para la negrita (**texto**).
";

$swot_analysis_result = call_llm($swot_prompt, $openrouterApiKey);

if ($swot_analysis_result === null) {
    $swot_analysis_result = "No se pudo generar el análisis F.O.D.A. debido a un error de comunicación con la IA.";
}

// ===================================================================
// == TAREA 3: Buyer Persona - IMPLEMENTACIÓN REAL                  ==
// ===================================================================

$buyer_persona_prompt = "
Eres un especialista en marketing inmobiliario y perfiles de cliente. Tu misión es crear un 'Buyer Persona' detallado para una propiedad específica.

**Contexto Estratégico Acumulado:**
- **Propiedad:** Tipo {$propertyData['type']} en {$propertyData['location']}, con {$propertyData['area']} m², {$propertyData['rooms']} habitaciones, {$propertyData['baths']} baños, en estado '{$propertyData['state']}'. Características extra: '{$propertyData['features']}'.
- **Análisis de Mercado:** {$market_analysis_result}
- **Análisis F.O.D.A.:** {$swot_analysis_result}

**Tu Tarea:**
Basándote en TODO el contexto anterior, define el perfil del comprador o inquilino ideal para esta propiedad. Describe a esta persona o grupo (familia, pareja, inversor) de forma vívida. Tu respuesta debe incluir:
1.  **Título del Perfil:** Un nombre descriptivo (ej. 'La Joven Pareja Reformista', 'El Inversor Visionario').
2.  **Perfil Demográfico:** Rango de edad, profesión, nivel de ingresos aproximado.
3.  **Motivaciones y Objetivos:** ¿Qué buscan en una propiedad? ¿Por qué esta propiedad les encaja? (Ej: Buscan su primer hogar, una inversión rentable, una segunda residencia...).
4.  **Puntos de Dolor y Necesidades:** ¿Qué problemas les resuelve esta propiedad? (Ej: Necesitan espacio, quieren vivir en el centro, tienen un presupuesto ajustado...).
Usa Markdown para la negrita (**texto**).
";

$buyer_persona_result = call_llm($buyer_persona_prompt, $openrouterApiKey);

if ($buyer_persona_result === null) {
    $buyer_persona_result = "No se pudo generar el perfil del comprador ideal debido a un error de comunicación con la IA.";
}

// ===================================================================
// == TAREA 4: Contenido de Marketing - IMPLEMENTACIÓN REAL         ==
// ===================================================================

$marketing_prompt = "
Eres un copywriter experto en marketing inmobiliario. Tu objetivo es crear textos de venta persuasivos y personalizados para una propiedad, utilizando toda la estrategia de marketing que se ha desarrollado previamente.

**Contexto Estratégico Completo:**
- **Propiedad:** Tipo {$propertyData['type']} en {$propertyData['location']}, con {$propertyData['area']} m², {$propertyData['rooms']} habitaciones, {$propertyData['baths']} baños, en estado '{$propertyData['state']}'. Características extra: '{$propertyData['features']}'.
- **Análisis de Mercado:** {$market_analysis_result}
- **Análisis F.O.D.A.:** {$swot_analysis_result}
- **Buyer Persona (Cliente Ideal):** {$buyer_persona_result}

**Tu Tarea Final:**
Utiliza toda la información anterior para redactar los textos de venta para las siguientes plataformas. Los textos deben estar dirigidos al 'Buyer Persona' identificado, resaltar las 'Fortalezas' del F.O.D.A. y mitigar las 'Debilidades'.

1.  **Facebook:** Tono cercano y familiar. Destaca la vida en el barrio y el potencial del hogar. Usa 1-2 emojis.
2.  **Instagram:** Tono visual, enérgico y aspiracional. Frases cortas, muchos emojis relevantes y hashtags. Apela a las emociones y al estilo de vida.
3.  **Portales Inmobiliarios (Idealista, etc.):** Tono profesional y detallado. Estructura clara con un párrafo inicial potente, seguido de una descripción exhaustiva. Optimizado para la venta y sin emojis.

**Formato de Salida Obligatorio:**
Devuelve tu respuesta como un único objeto JSON válido, sin explicaciones ni texto adicional. Las claves deben ser 'facebook', 'instagram' y 'portales'.
";

$marketing_content_response = call_llm($marketing_prompt, $openrouterApiKey, true);

// --- MODIFICACIÓN CLAVE: Lógica robusta para extraer y validar el JSON ---
if ($marketing_content_response) {
    // Busca un bloque JSON (que empieza con { y termina con }) dentro de la respuesta del LLM
    preg_match('/\{.*?\}/s', $marketing_content_response, $matches);
    
    if (isset($matches[0])) {
        $json_string = $matches[0];
        $marketing_data = json_decode($json_string, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            $marketing_content_result = "
**Facebook:**
" . ($marketing_data['facebook'] ?? 'No se pudo generar.') . "

**Instagram:**
" . ($marketing_data['instagram'] ?? 'No se pudo generar.') . "

**Portales Inmobiliarios:**
" . ($marketing_data['portales'] ?? 'No se pudo generar.');
        } else {
            // El bloque encontrado no era un JSON válido.
            error_log("Error de JSON en Agente Estratégico (JSON malformado): " . $json_string);
            $marketing_content_result = "Error: La IA devolvió un formato de datos inválido para el contenido de marketing.";
        }
    } else {
        // No se encontró ningún bloque JSON en la respuesta.
        error_log("Error de JSON en Agente Estratégico (No se encontró JSON): " . $marketing_content_response);
        $marketing_content_result = "Error: La IA no devolvió el contenido de marketing en el formato esperado.";
    }
} else {
    $marketing_content_result = "No se pudo generar el contenido de marketing debido a un error de comunicación con la IA.";
}
// --- FIN DE LA MODIFICACIÓN ---

// --- Devolver la Respuesta Final ---
$final_response = [
    'success' => true,
    'data' => [
        'market_analysis' => trim($market_analysis_result),
        'swot_analysis' => trim($swot_analysis_result),
        'buyer_persona' => trim($buyer_persona_result),
        'marketing_content' => trim($marketing_content_result)
    ]
];

echo json_encode($final_response);

// ===================================================================
// == FUNCIONES AUXILIARES                                          ==
// ===================================================================

/**
 * Función para encontrar propiedades comparables en un archivo CSV.
 * @param string $csv_path Ruta al archivo CSV.
 * @param array $propertyData Datos de la propiedad a comparar.
 * @return array Lista de propiedades comparables.
 */
function find_comparables($csv_path, $propertyData) {
    if (!file_exists($csv_path)) {
        return [];
    }

    $all_ads = array_map('str_getcsv', file($csv_path));
    $header = array_shift($all_ads);
    $ads_as_assoc = [];
    foreach ($all_ads as $row) {
        if(count($header) == count($row)) {
            $ads_as_assoc[] = array_combine($header, $row);
        }
    }
    
    $target_location = strtolower($propertyData['location']);
    $target_area = (int)$propertyData['area'];
    $area_margin = $target_area * 0.25; // Margen del 25% para la superficie

    $comparables = [];

    foreach ($ads_as_assoc as $ad) {
        // Ignorar si el anuncio no tiene datos clave
        if (empty($ad['Ubicación']) || empty($ad['Características'])) {
            continue;
        }

        $ad_location = strtolower($ad['Ubicación']);
        
        // Criterio 1: La ubicación debe contener alguna palabra clave de la ubicación objetivo
        $location_keywords = preg_split('/[\s,]+/', $target_location);
        $location_match = false;
        foreach($location_keywords as $keyword) {
            if(strlen($keyword) > 3 && strpos($ad_location, $keyword) !== false) {
                $location_match = true;
                break;
            }
        }
        if (!$location_match) {
            continue;
        }

        // Criterio 2: La superficie debe estar dentro del margen
        $ad_area = 0;
        if (preg_match('/(\d+)\s*m²/', $ad['Características'], $matches)) {
            $ad_area = (int)$matches[1];
        }
        if ($ad_area < ($target_area - $area_margin) || $ad_area > ($target_area + $area_margin)) {
            continue;
        }

        $comparables[] = $ad;
    }

    // Ordenar por precio (si está disponible) y devolver los 5 mejores
    usort($comparables, function($a, $b) {
        $price_a = (int)preg_replace('/[^\d]/', '', $a['Precio'] ?? '0');
        $price_b = (int)preg_replace('/[^\d]/', '', $b['Precio'] ?? '0');
        return $price_a <=> $price_b;
    });

    return array_slice($comparables, 0, 5);
}

/**
 * Función para hacer una llamada a la API del LLM.
 * @param string $prompt El prompt para el LLM.
 * @param string $apiKey La clave de API de OpenRouter.
 * @param bool $is_json_mode Si se debe solicitar una respuesta JSON.
 * @return string|null La respuesta del LLM o null en caso de error.
 */
function call_llm($prompt, $apiKey, $is_json_mode = false) {
    $llmApiUrl = "https://openrouter.ai/api/v1/chat/completions";
    $payload = [
        'model' => 'openai/gpt-4o',
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.5
    ];
    if ($is_json_mode) {
        $payload['response_format'] = ['type' => 'json_object'];
    }

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ];

    $ch = curl_init($llmApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Error en la llamada al LLM (HTTP: $httpCode): $response");
        return null;
    }

    $responseData = json_decode($response, true);
    return $responseData['choices'][0]['message']['content'] ?? null;
}
?>