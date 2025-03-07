<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Configuración del bot de Telegram para pagos al cliente (QR)
$TOKEN = "7649868783:AAF-aTgEuA2o7q2jaXGJ5awrysEy04hgJl4";  // Tu token de bot
$CHAT_ID = "-4757550811";  // Chat ID para pagos al cliente

// Solo se aceptan solicitudes POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["message" => "Method Not Allowed"]);
    exit;
}

// Verificar que se haya subido un archivo
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(["message" => "No se ha subido ningún archivo"]);
    exit;
}

// Verificar si hay error en la subida
if ($_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["message" => "Error al subir el archivo: " . $_FILES["file"]["error"]]);
    exit;
}

// Generar número de orden secuencial
$uniqueIdFile = "unique_id.txt";
if (!file_exists($uniqueIdFile)) {
    file_put_contents($uniqueIdFile, "0");  // Inicializar el archivo si no existe
}
$lastUniqueId = (int)file_get_contents($uniqueIdFile);
$newUniqueId = $lastUniqueId + 1;
file_put_contents($uniqueIdFile, $newUniqueId);  // Guardar el nuevo número

$uniqueId = "RT" . str_pad($newUniqueId, 4, "0", STR_PAD_LEFT);

// Verificar número de documento
if (!isset($_POST['docNumber']) || empty(trim($_POST['docNumber']))) {
    http_response_code(400);
    echo json_encode(["message" => "Número de documento es requerido"]);
    exit;
}
$docNumber = substr(trim($_POST['docNumber']), 0, 12);

// Verificar número de teléfono y agregar el indicativo +591
if (!isset($_POST['phoneNumber']) || empty(trim($_POST['phoneNumber']))) {
    http_response_code(400);
    echo json_encode(["message" => "Número de teléfono es requerido"]);
    exit;
}
$phoneNumber = trim($_POST['phoneNumber']);

// Asegurarse de que el número de teléfono tenga el formato correcto
if (!preg_match('/^\d{8}$/', $phoneNumber)) {
    http_response_code(400);
    echo json_encode(["message" => "Número de teléfono debe tener 8 dígitos sin el indicativo."]);
    exit;
}

// Agregar el indicativo +591
$phoneNumber = "+591" . $phoneNumber;

// Verificar y tomar el monto directamente como lo recibe el formulario
if (!isset($_POST['monto']) || empty(trim($_POST['monto']))) {
    http_response_code(400);
    echo json_encode(["message" => "El monto es requerido"]);
    exit;
}
$monto = $_POST['monto'];  // Tomar el monto directamente como lo recibe

$nombreArchivo = $_FILES["file"]["name"];
$rutaTemporal = $_FILES["file"]["tmp_name"];
$fecha = date('Y-m-d H:i:s');

$url = "https://api.telegram.org/bot$TOKEN/sendDocument";

// Preparar el mensaje que se enviará a Telegram
$caption = "🆔 Número de Orden: `$uniqueId`\n" .
           "📅 Fecha de carga: $fecha\n" .
           "🪪 Documento: $docNumber\n" .
           "💰 Monto: $monto\n\n" .
           "🔔 Por favor, Realizar el pago.";

$keyboard = json_encode([
    "inline_keyboard" => [
        [["text" => "✅ Completado", "callback_data" => "completado-$uniqueId-$monto-$docNumber"]],
        [["text" => "❌ Rechazado", "callback_data" => "rechazado-$uniqueId-$monto-$docNumber"]]
    ]
]);

$postData = [
    "chat_id" => $CHAT_ID,
    "document" => new CURLFile($rutaTemporal, mime_content_type($rutaTemporal), $nombreArchivo),
    "caption" => $caption,
    "parse_mode" => "Markdown",
    "reply_markup" => $keyboard
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$curl_error = curl_error($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $http_status != 200) {
    http_response_code(500);
    echo json_encode([
        "message"    => "Error al enviar a Telegram.",
        "curl_error" => $curl_error,
        "http_status"=> $http_status,
        "response"   => $response
    ]);
    exit;
}

// Enviar mensaje de WhatsApp al cliente
$whatsappMessage = "✅ Su solicitud ha sido recibida.\n" .
                   "📅 Fecha: $fecha\n" .
                   "💰 Monto: $monto\n" .
                   "🔔 Te notificaremos cuando este procesada.";

sendWhatsAppNotification($phoneNumber, $whatsappMessage);

// Función para enviar notificación de WhatsApp
function sendWhatsAppNotification($phoneNumber, $message) {
    $apiKey = '6d32dd80bef8d29e2652d9c68148193d1ff229c248e8f731'; // Tu clave API
    $url = "https://api.smsmobileapi.com/sendsms?apikey=$apiKey&waonly=yes&recipients=" . urlencode($phoneNumber) . "&message=" . urlencode($message);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    
    // Verificar si hubo un error en la solicitud
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        file_put_contents("whatsapp_error_log.txt", "Error al enviar WhatsApp: $error_msg\n", FILE_APPEND);
    } else {
        // Registra la respuesta de la API
        file_put_contents("whatsapp_response_log.txt", "Respuesta de WhatsApp: $response\n", FILE_APPEND);
    }

    curl_close($ch);
    return $response;
}

echo json_encode(["message" => "✅ Comprobante enviado a administradores en Telegram y notificación enviada por WhatsApp", "orden" => $uniqueId]);
?>
