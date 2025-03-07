<?php
$TOKEN = "7768625990:AAGd7poAx9VCb8zxSoVS01bWI-5NVY0v0CY";  
date_default_timezone_set('America/La_Paz');

$content = file_get_contents("php://input");
$update = json_decode($content, true);

// Log completo del callback recibido
file_put_contents("callback_log.txt", "📌 Callback recibido completo: " . json_encode($update, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

// Extraer data principal
$callbackData = $update["callback_query"]["data"] ?? '';
$chatId = $update["callback_query"]["message"]["chat"]["id"] ?? '';
$messageId = $update["callback_query"]["message"]["message_id"] ?? '';

// Extraer mensaje completo (sea caption o text)
$messageText = $update["callback_query"]["message"]["caption"] ?? $update["callback_query"]["message"]["text"] ?? '';

// Log directo del callback_data y mensaje recibido
file_put_contents("callback_log.txt", "📥 callback_data recibido: $callbackData\n📩 Mensaje recibido: $messageText\n", FILE_APPEND);

// Extraer datos base del callback_data
preg_match('/(completado|rechazado)-(RT\d{4})-(.*?)-(\d{1,12})/', $callbackData, $matches);
if (!$matches) {
    file_put_contents("callback_log.txt", "❌ Error: callback_data no coincide con el patrón.\n", FILE_APPEND);
    exit;
}

$accion = $matches[1];
$uniqueId = $matches[2];
$monto = $matches[3];
$docNumber = $matches[4];

// ✅ Aquí extraemos el teléfono directamente del mensaje
preg_match('/📱 Teléfono: `?(\d{8,12})`?/', $messageText, $phoneMatch);
$fullPhoneNumber = $phoneMatch[1] ?? null;

// Si no encontró el teléfono, lo registramos y detenemos
if (!$fullPhoneNumber) {
    file_put_contents("callback_log.txt", "❌ Error: No se encontró el teléfono en el mensaje.\n", FILE_APPEND);
    exit;
}

// Datos del admin
$user = $update["callback_query"]["from"];
$adminName = $user["first_name"] ?? "Administrador";
if (!empty($user["username"])) {
    $adminName .= " (@" . $user["username"] . ")";
}

// Acción tomada
$accionTexto = $accion === "completado" ? "✅ COMPLETADO" : "❌ RECHAZADO";
$fechaAccion = date('Y-m-d H:i:s');

// Eliminar mensaje original
file_get_contents("https://api.telegram.org/bot$TOKEN/deleteMessage?" . http_build_query([
    "chat_id" => $chatId,
    "message_id" => $messageId
]));

// Enviar mensaje actualizado
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

// Enviar mensaje a WhatsApp
$whatsappMessage = $accion === "completado"
    ? "✅ Su solicitud ha sido COMPLETADA con éxito.%0AGracias por confiar en nosotros."
    : "❌ Su solicitud ha sido RECHAZADA.%0APor favor, contáctenos para más información.";

sendWhatsApp($fullPhoneNumber, $whatsappMessage);

// Enviar WhatsApp
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
