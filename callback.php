<?php
$TOKEN = "7957554764:AAHUzfquZDDVEiwOy_u292haqMmPK2dCKDI";  // Token del bot
$LOG_CHAT_ID = "-4757550811";  // Chat donde quieres que te llegue el log (puede ser el mismo grupo de admin)

date_default_timezone_set('America/La_Paz');

$content = file_get_contents("php://input");
$update = json_decode($content, true);

// Log completo del callback recibido
file_put_contents("callback_log.txt", "📌 Callback recibido: " . json_encode($update, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

// Enviar el mismo log por Telegram (opcional, por si no puedes ver archivos en Render)
file_get_contents("https://api.telegram.org/bot$TOKEN/sendMessage?" . http_build_query([
    "chat_id" => $LOG_CHAT_ID,
    "text" => "📌 Callback recibido (json): " . json_encode($update, JSON_PRETTY_PRINT)
]));

// Validar que existe callback_query
if (!isset($update["callback_query"])) {
    file_put_contents("callback_log.txt", "❌ Error: No hay callback_query en la solicitud.\n", FILE_APPEND);
    exit;
}

// Extraer info clave
$callbackData = $update["callback_query"]["data"] ?? '';
$chatId = $update["callback_query"]["message"]["chat"]["id"] ?? '';
$messageId = $update["callback_query"]["message"]["message_id"] ?? '';

// Log del callback_data recibido
file_put_contents("callback_log.txt", "📥 callback_data recibido: $callbackData\n", FILE_APPEND);

// Enviar el callback_data a Telegram (por si no hay acceso a logs en Render)
file_get_contents("https://api.telegram.org/bot$TOKEN/sendMessage?" . http_build_query([
    "chat_id" => $LOG_CHAT_ID,
    "text" => "📥 callback_data recibido: $callbackData"
]));

exit; // No sigue la lógica completa, solo loguea. Así vemos qué llega realmente
?>
