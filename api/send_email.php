<?php
/**
 * @file api/send_email.php
 * @description Endpoint para procesar el formulario de contacto.
 * Recibe los datos del formulario (POST), los valida y los envía
 * a una dirección de correo electrónico predefinida.
 */

// --- CONFIGURACIÓN DE CABECERAS Y ERRORES ---
// Permite CORS desde cualquier origen. En producción, habria que restringir esto al dominio por seguridad.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Maneja la petición de pre-vuelo (preflight) OPTIONS enviada por los navegadores para CORS.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// --- VALIDACIÓN DE LA PETICIÓN HTTP ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// --- RECOGIDA Y SANITIZACIÓN DE DATOS ---
// Se usan múltiples funciones para limpiar y asegurar los datos de entrada:
// - `trim`: Elimina espacios en blanco al inicio y al final.
// - `strip_tags`: Elimina cualquier etiqueta HTML/PHP.
// - `htmlspecialchars`: Convierte caracteres especiales a entidades HTML para prevenir ataques XSS.
$name = isset($_POST['contact-name']) ? htmlspecialchars(strip_tags(trim($_POST['contact-name']))) : '';
$email = isset($_POST['contact-email']) ? htmlspecialchars(strip_tags(trim($_POST['contact-email']))) : '';
$message = isset($_POST['contact-message']) ? htmlspecialchars(strip_tags(trim($_POST['contact-message']))) : '';

// El destinatario se define en el servidor por seguridad, nunca se toma del cliente.
$recipient_email = 'guillemuba13@gmail.com'; 

// --- VALIDACIÓN DE DATOS ---
if (empty($name) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Por favor, completa todos los campos correctamente.']);
    exit;
}

// --- CONSTRUCCIÓN DEL CORREO ---
$subject = "Mensaje de Contacto desde Ginmus.com de: " . $name;
$body = "Nombre: " . $name . "\n";
$body .= "Email: " . $email . "\n\n";
$body .= "Mensaje:\n" . $message;

$headers = "From: " . $email . "\r\n";
$headers .= "Reply-To: " . $email . "\r\n"; // Asegura que al responder, se responda al usuario.
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// --- ENVÍO DEL CORREO ---
// La función `mail()` de PHP depende de la configuración del servidor (sendmail, Postfix, etc.).
if (mail($recipient_email, $subject, $body, $headers)) {
    echo json_encode(['success' => true, 'message' => '¡Mensaje enviado con éxito!']);
} else {
    // Si mail() falla, es un problema del servidor. Se registra para depuración.
    error_log("Fallo al enviar correo desde el formulario de contacto. De: {$email}");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Hubo un error al enviar tu mensaje.']);
}
?>