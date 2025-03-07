<?php
function mensajeRecepcion($fecha, $monto) {
    return "✅ Su solicitud ha sido recibida\n\n" .
           "🗓 Fecha: $fecha\n" .
           "💰 Monto: $monto BOB\n\n" .
           "🔔 Te notificaremos cuando esté procesada.";
}

function mensajeCompletado() {
    return "¡Es oficial! ✅ Tu solicitud está completamente lista.\n\n" .
           "Gracias por ser parte de Winsgana, donde cada jugada cuenta y cada momento puede ser épico.\n\n" .
           "🔥 Te deseamos la mejor de las suertes, porque la suerte premia a los valientes.\n\n" .
           "🔔 Recuerda: este es un canal de notificaciones automáticas.";
}

function mensajeRechazado() {
    return "⚠️ Tu solicitud fue rechazada.\n\n" .
           "Si crees que esto fue un error, puedes comunicarte con nuestro equipo de soporte para ayudarte.\n\n" .
           "🔔 Recuerda: este es un canal de notificaciones automáticas.";
}
?>
