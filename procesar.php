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

// Generar número de orden
$uniqueIdFile = "unique_id.txt";
if (!file_exists($uniqueIdFile)) {
    file_put_contents($uniqueIdFile, "0");
}
$lastUniqueId = (int)file_get_contents($uniqueIdFile);
$newUniqueId = $lastUniqueId + 1;
file_put_contents($uniqueIdFile, $newUniqueId);

$uniqueId = "RT" . str_pad($newUniqueId, 4, "0", STR_PAD_LEFT);

$docNumber = substr(trim($_POST['docNumber'] ?? ''), 0, 12);
$phoneNumber = preg_replace('/\D/', '', $_POST["phoneNumber"] ?? '');
if (strlen($phoneNumber) !== 8) {
    http_response_code(400);
    echo json_encode(["message" => "Número debe tener 8 dígitos"]);
    exit;
}
$fullPhoneNumber = "591" . $phoneNumber;

$monto = $_POST['monto'] ?? '';
$nombreArchivo = $_FILES["file"]["name"];
$rutaTemporal = $_FILES["file"]["tmp_name"];
$fecha = date('Y-m-d H:i:s');

$caption = "🆔 Número de Orden: `$uniqueId`\n" .
           "📅 Fecha de carga: $fecha\n" .
           "🪪 Documento: $docNumber\n" .
           "📱 Teléfono: $fullPhoneNumber\n" .
           "💰 Monto: $monto BOB\n\n" .
           "🔔 Por favor, Realizar el pago.";

// Callback con el teléfono incluido
$callbackData = "completado-$uniqueId-$monto-$docNumber-$fullPhoneNumber";

// Log para verificar que el callback_data es correcto
file_put_contents("procesar_log.txt", "📥 Callback generado: $callbackData\n", FILE_APPEND);

$keyboard = json_encode([
    "inline_keyboard" => [
        [["text" => "✅ Completado", "callback_data" => $callbackData]],
        [["text" => "❌ Rechazado", "callback_data" => str_replace('completado', 'rechazado', $callbackData)]]
    ]
]);

$postData = [
    "chat_id" => $CHAT_ID,
    "document" => new CURLFile($rutaTemporal, mime_content_type($rutaTemporal), $nombreArchivo),
    "caption" => $caption,
    "parse_mode" => "Markdown",
    "reply_markup" => $keyboard
];

$ch = curl_init("https://api.telegram.org/bot$TOKEN/sendDocument");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

file_put_contents("procesar_log.txt", "📤 Respuesta de Telegram: $response\n", FILE_APPEND);

// Enviar WhatsApp de recepción
$whatsappMessage = "✅ Su solicitud ha sido recibida\n\n🗓 Fecha: $fecha\n💰 Monto: $monto BOB\n\n🔔 Te notificaremos cuando esté procesada.";
sendWhatsApp($fullPhoneNumber, $whatsappMessage);

function sendWhatsApp($phoneNumber, $message) {
    $apiKey = '6d32dd80bef8d29e2652d9c68148193d1ff229c248e8f731';
    $url = "https://api.smsmobileapi.com/sendsms/?" . http_build_query([
        "recipients" => $phoneNumber,
        "message" => $message,
        "apikey" => $apiKey,
        "waonly" => "yes"
    ]);
    file_get_contents($url);
}

echo json_encode(["message" => "✅ Comprobante enviado a Telegram y WhatsApp"]);
?>

