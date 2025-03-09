<?php
require_once 'utils/config.php';
require_once 'utils/whatsapp.php';
require_once 'utils/mensajes.php';

date_default_timezone_set('America/La_Paz');

$content = file_get_contents("php://input");
$update = json_decode($content, true);

$callbackData = $update["callback_query"]["data"] ?? '';
$chatId = $update["callback_query"]["message"]["chat"]["id"] ?? '';
$messageId = $update["callback_query"]["message"]["message_id"] ?? '';
$messageText = $update["callback_query"]["message"]["caption"] ?? '';

// Obtener el documento del mensaje original
$fileId = $update["callback_query"]["message"]["document"]["file_id"] ?? '';

preg_match('/(completado|rechazado)-(RT\\d{4})-(.*?)-(\\d{1,12})/', $callbackData, $matches);
$accion = $matches[1] ?? '';
$uniqueId = $matches[2] ?? '';
$monto = $matches[3] ?? '';
$docNumber = $matches[4] ?? '';

preg_match('/📱 Teléfono: (\\d{8,12})/', $messageText, $phoneMatch);
$fullPhoneNumber = $phoneMatch[1] ?? '';

$adminName = $update["callback_query"]["from"]["first_name"] ?? "Administrador";
if (isset($update["callback_query"]["from"]["username"])) {
    $adminName .= " (@" . $update["callback_query"]["from"]["username"] . ")";
}

$accionTexto = $accion === "completado" ? "✅ COMPLETADO" : "❌ RECHAZADO";
$fechaAccion = date('Y-m-d H:i:s');

file_get_contents("https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/deleteMessage?" . http_build_query([
    "chat_id" => $chatId,
    "message_id" => $messageId
]));

$caption = "🆔 Número de Orden: `$uniqueId`\n" .
           "👤 Administrador: $adminName\n" .
           "📅 Fecha de acción: $fechaAccion\n" .
           "🪪 Documento: $docNumber\n" .
           "📱 Teléfono: `$fullPhoneNumber`\n" .
           "💰 Monto: $monto BOB\n" .
           "$accionTexto";

// Enviar siempre el documento con el mensaje de confirmación
file_get_contents("https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendDocument?" . http_build_query([
    "chat_id" => $chatId,
    "document" => $fileId,
    "caption" => $caption,
    "parse_mode" => "Markdown"
]));

if ($accion === "completado") {
    sendWhatsApp($fullPhoneNumber, mensajeCompletado());
} else {
    sendWhatsApp($fullPhoneNumber, mensajeRechazado());
}
?>
