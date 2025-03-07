<?php
$TOKEN = "7957554764:AAHUzfquZDDVEiwOy_u292haqMmPK2dCKDI";  
date_default_timezone_set('America/La_Paz');

$content = file_get_contents("php://input");
$update = json_decode($content, true);

file_put_contents("callback_log.txt", "📌 Callback recibido: " . json_encode($update, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

$callbackData = $update["callback_query"]["data"] ?? '';
$chatId = $update["callback_query"]["message"]["chat"]["id"] ?? '';
$messageId = $update["callback_query"]["message"]["message_id"] ?? '';
$messageText = $update["callback_query"]["message"]["caption"] ?? $update["callback_query"]["message"]["text"] ?? '';

// Log del callback_data
file_put_contents("callback_log.txt", "📥 CallbackData: $callbackData\n", FILE_APPEND);

// Extraer datos del callback_data (sin teléfono, porque lo leeremos del mensaje)
preg_match('/(completado|rechazado)-(RT\d{4})-(.*?)-(\d{1,12})/', $callbackData, $matches);
if (!$matches) {
    file_put_contents("callback_log.txt", "❌ Error: No se pudo extraer callback_data.\n", FILE_APPEND);
    exit;
}

$accion = $matches[1];
$uniqueId = $matches[2];
$monto = $matches[3];
$docNumber = $matches[4];

// ✅ Extraer teléfono desde el caption
preg_match('/📱 Teléfono: `?(\d{11,12})`?/', $messageText, $phoneMatch);
$fullPhoneNumber = $phoneMatch[1] ?? null;

if (!$fullPhoneNumber) {
    file_put_contents("callback_log.txt", "❌ Error: No se pudo extraer el teléfono desde el mensaje.\n", FILE_APPEND);
    exit;
}

// Datos del admin
$user = $update["callback_query"]["from"];
$adminName = $user["first_name"] ?? "Administrador";
if (!empty($user["username"])) {
    $adminName .= " (@" . $user["username"] . ")";
}

$accionTexto = $accion === "completado" ? "✅ COMPLETADO" : "❌ RECHAZADO";
$fechaAccion = date('Y-m-d H:i:s');

// Borrar mensaje original
file_get_contents("https://api.telegram.org/bot$TOKEN/deleteMessage?" . http_build_query([
    "chat_id" => $chatId,
    "message_id" => $messageId
]));

// Enviar nuevo mensaje con resumen
$nuevoTexto = "🆔 Número de Orden: `$uniqueId`\n" .
              "👤 Administrador: $adminName\n" .
              "📅 Fecha de acción: $fechaAccion\n" .
              "🪪 Documento: $docNumber\n" .
              "📱 Teléfono: `$fullPhoneNumber`\n" .
              "💰 Monto: $monto BOB\n" .
              "$accionTexto";

file_get_contents("https://api.telegram.org/bot$TOKEN/sendMessage?" . http_build_query([
    "chat_id" => $chatId,
    "text" => $nuevoTexto,
    "parse_mode" => "Markdown"
]));

// Enviar WhatsApp
$whatsappMessage = $accion === "completado"
    ? "✅ Su solicitud ha sido COMPLETADA con éxito.%0AGracias por confiar en nosotros."
    : "❌ Su solicitud ha sido RECHAZADA.%0APor favor, contáctenos para más información.";

sendWhatsApp($fullPhoneNumber, $whatsappMessage);

function sendWhatsApp($phoneNumber, $message) {
    $apiKey = '6d32dd80bef8d29e2652d9c68148193d1ff229c248e8f731';
    file_get_contents("https://api.smsmobileapi.com/sendsms/?" . http_build_query([
        "recipients" => $phoneNumber,
        "message" => $message,
        "apikey" => $apiKey,
        "waonly" => "yes"
    ]));
}
?>
