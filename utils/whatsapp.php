<?php
require_once 'config.php';

function sendWhatsApp($phoneNumber, $message) {
    $url = "https://api.smsmobileapi.com/sendsms/?" . http_build_query([
        "recipients" => $phoneNumber,
        "message"    => $message,
        "apikey"     => SMS_API_KEY,
        "waonly"     => "yes"
    ]);

    file_put_contents("whatsapp_log.txt", date('Y-m-d H:i:s') . " - Enviando a $phoneNumber: $message\n", FILE_APPEND);
    return file_get_contents($url);
}
?>
