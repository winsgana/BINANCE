<?php
$TOKEN = "7957554764:AAHUzfquZDDVEiwOy_u292haqMmPK2uCKDI";  // Token del bot

date_default_timezone_set('America/La_Paz'); // Asegura la hora local correcta

$content = file_get_contents("php://input");
$update = json_decode($content, true);

file_put_contents("callback_log.txt", "📌 Callback recibido: " . json_encode($update, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

if (!$update || !isset($update["callback_query"])) {
    file_put_contents("callback_log.txt", "❌ Error: No hay callback_query en la solicitud.\n", FILE_APPEND);
    exit;
}

$callbackData = $update["callback_query"]["data"];
$chatId = $update["callback_query"]["message"]["chat"]["id"];
$messageId = $update["callback_query"]["message"]["message_id"];
$user = $update["callback_query"]["from"];

// Extraer datos del callback_data
preg_match('/(completado|rechazado)-(DP\d{4})-(.*?)-(\d{1,12})-(\d{8})/', $callbackData, $matches);
if (!$matches) {
    file_put_contents("callback_log.txt", "❌ Error: callback_data desconocido ($callbackData).\n", FILE_APPEND);
    exit;
}

$accion = $matches[1];  // "completado" o "rechazado"
$uniqueId = $matches[2];  // El uniqueId generado en procesar.php
$monto = $matches[3];  // El monto enviado desde procesar.php
$docNumber = $matches[4];  // El número de documento de procesar.php
$phoneNumber = "591" . $matches[5];  // El número de teléfono enviado desde procesar.php

// Obtener nombre del usuario
$adminName = isset($user["first_name"]) ? $user["first_name"] : "Administrador";
if (isset($user["username"])) {
    $adminName .= " (@" . $user["username"] . ")";
}

// Acción tomada
$accionTexto = ($accion === "completado") ? "✅ COMPLETADO" : "❌ RECHAZADO";
$fechaAccion = date('Y-m-d H:i:s');

// Eliminar el mensaje original
$urlDelete = "https://api.telegram.org/bot$TOKEN/deleteMessage";
$postDataDelete = [
    "chat_id" => $chatId,
    "message_id" => $messageId
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlDelete);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataDelete);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$responseDelete = curl_exec($ch);
$curl_error = curl_error($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

file_put_contents("callback_log.txt", "📌 Respuesta de borrar mensaje: " . $responseDelete . "\n", FILE_APPEND);

if ($responseDelete === false || $http_status != 200) {
    file_put_contents("callback_log.txt", "❌ Error al borrar el mensaje: $curl_error\n", FILE_APPEND);
    exit;
}

// Enviar un nuevo mensaje con la información actualizada
$url = "https://api.telegram.org/bot$TOKEN/sendMessage";
$nuevoTexto = "🆔 Número de Orden: `$uniqueId`\n" .
              "👤 Administrador: $adminName\n" .
              "📅 Fecha de acción: $fechaAccion\n" .
              "🪪 Documento: $docNumber\n" .
              "📱 Teléfono: $fullPhoneNumber\n" .
              "💰 Monto: $monto BOB\n" .
              "$accionTexto";

$postDataSend = [
    "chat_id" => $chatId,
    "text" => $nuevoTexto,
    "parse_mode" => "Markdown"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataSend);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$responseSend = curl_exec($ch);
$curl_error = curl_error($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

file_put_contents("callback_log.txt", "📌 Respuesta de enviar mensaje nuevo: " . $responseSend . "\n", FILE_APPEND);

if ($responseSend === false || $http_status != 200) {
    file_put_contents("callback_log.txt", "❌ Error al enviar el mensaje: $curl_error\n", FILE_APPEND);
}

// ========== Envío de WhatsApp ==========
// Mensaje para el cliente
if ($accion === "completado") {
    $whatsappMessage = "✅ Su solicitud con orden $uniqueId ha sido COMPLETADA con éxito.%0AGracias por confiar en nosotros.";
} else {
    $whatsappMessage = "❌ Su solicitud con orden $uniqueId ha sido RECHAZADA.%0APor favor, contáctenos para más información.";
}

// Enviar mensaje por WhatsApp
sendWhatsApp($phoneNumber, $whatsappMessage);

// Función para enviar WhatsApp
function sendWhatsApp($phoneNumber, $message) {
    $apiKey = '6d32dd80bef8d29e2652d9c68148193d1ff229c248e8f731';

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

exit;
?>
