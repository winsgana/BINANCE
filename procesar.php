<?php
date_default_timezone_set('America/La_Paz');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Configuración del bot de Telegram para pagos al cliente (QR)
$TOKEN = "7649868783:AAF-aTgEuA2o7q2jaXGJ5awrysEy04hgJl4";
$CHAT_ID = "-4757550811";

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

if ($_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["message" => "Error al subir el archivo: " . $_FILES["file"]["error"]]);
    exit;
}

// Generar número de orden secuencial
$uniqueIdFile = "unique_id.txt";
if (!file_exists($uniqueIdFile)) {
    file_put_contents($uniqueIdFile, "0");
}
$lastUniqueId = (int)file_get_contents($uniqueIdFile);
$newUniqueId = $lastUniqueId + 1;
file_put_contents($uniqueIdFile, $newUniqueId);

$uniqueId = "RT" . str_pad($newUniqueId, 4, "0", STR_PAD_LEFT);

// Validar número de documento
if (!isset($_POST['docNumber']) || empty(trim($_POST['docNumber']))) {
    http_response_code(400);
    echo json_encode(["message" => "Número de documento es requerido"]);
    exit;
}
$docNumber = substr(trim($_POST['docNumber']), 0, 12);

// Formatear número de teléfono
$phoneNumber = preg_replace('/\D/', '', $_POST["phoneNumber"]);
if (strlen($phoneNumber) !== 8) {
    http_response_code(400);
    echo json_encode(["message" => "Número debe tener 8 dígitos"]);
    exit;
}
$fullPhoneNumber = "591" . $phoneNumber;

// Validar monto
if (!isset($_POST['monto']) || empty(trim($_POST['monto']))) {
    http_response_code(400);
    echo json_encode(["message" => "El monto es requerido"]);
    exit;
}
$monto = $_POST['monto'];

$nombreArchivo = $_FILES["file"]["name"];
$rutaTemporal = $_FILES["file"]["tmp_name"];
$fecha = date('Y-m-d H:i:s');

// Enviar documento a Telegram
$url = "https://api.telegram.org/bot$TOKEN/sendDocument";

$caption = "🆔 Número de Orden: `$uniqueId`\n" .
           "📅 Fecha de carga: $fecha\n" .
           "🪪 Documento: $docNumber\n" .
           "📱 Teléfono: $fullPhoneNumber\n" .
           "💰 Monto: $monto\n\n" .
           "🔔 Por favor, Realizar el pago.";

$keyboard = json_encode([
    "inline_keyboard" => [
        [["text" => "✅ Completado", "callback_data" => "completado-$uniqueId-$monto-$docNumber-$phoneNumber"]],
        [["text" => "❌ Rechazado", "callback_data" => "rechazado-$uniqueId-$monto-$docNumber-$phoneNumber"]]
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

// Enviar notificación de WhatsApp
$whatsappMessage = "✅ Su solicitud ha sido recibida\n\n" .
                   "🗓 Fecha: $fecha\n" .
                   "💰 Monto: $monto BOB\n\n" .
                   "🔔 Te notificaremos cuando este procesada.";

sendWhatsApp($fullPhoneNumber, $whatsappMessage);

// Función para enviar WhatsApp usando GET directo (como lo hiciste manualmente)
function sendWhatsApp($phoneNumber, $message) {
    $apiKey = '6d32dd80bef8d29e2652d9c68148193d1ff229c248e8f731';

    // Codificar el mensaje completo
    $message = rawurlencode($message);
    
    $url = "https://api.smsmobileapi.com/sendsms/?" . http_build_query([
        "recipients" => $phoneNumber,
        "message"    => $message,
        "apikey"     => $apiKey,
        "waonly"     => "yes"
    ]);

    $response = file_get_contents($url);
    file_put_contents("whatsapp_log.txt", date('Y-m-d H:i:s') . " - URL: $url\nResponse: $response\n", FILE_APPEND);

    return $response;
}

echo json_encode(["message" => "✅ Comprobante enviado a administradores en Telegram y notificación enviada por WhatsApp", "orden" => $uniqueId]);
?>
