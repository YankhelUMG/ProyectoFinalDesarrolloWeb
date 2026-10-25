<?php
declare(strict_types=1);

require_once __DIR__ . '/Mailer.php';

// Email only the candidates the company accepted when the opportunity closes.
function email_accepted_applicants(PDO $pdo, int $opportunityId, int $actorId): array
{
    $query = $pdo->prepare("SELECT oportunidad AS titulo,correo_contacto,id_postulacion,nombres,apellidos,correo,telefono,cv_ruta,cv_nombre FROM vw_admin_postulaciones WHERE id_oportunidad=:id AND estado='aceptada' ORDER BY fecha_postulacion");
    $query->execute(['id' => $opportunityId]);
    $rows = $query->fetchAll();
    if (!$rows) return ['sent' => false, 'count' => 0, 'message' => 'La convocatoria se cerró, pero no hay perfiles aceptados para enviar.'];

    $to = (string)$rows[0]['correo_contacto'];
    $subject = 'Perfiles aceptados: ' . (string)$rows[0]['titulo'];
    $body = "La convocatoria ha finalizado. Estos son los perfiles aceptados:\r\n\r\n";
    $attachments = [];
    foreach ($rows as $candidate) {
        $body .= 'Nombre: ' . $candidate['nombres'] . ' ' . $candidate['apellidos'] . "\r\n";
        $body .= 'Correo: ' . $candidate['correo'] . "\r\nTeléfono: " . ($candidate['telefono'] ?: 'No proporcionado') . "\r\n";
        $body .= 'CV: ' . ($candidate['cv_nombre'] ?: 'No disponible') . "\r\n\r\n";
        if (!empty($candidate['cv_ruta'])) $attachments[] = ['path' => $candidate['cv_ruta'], 'name' => $candidate['cv_nombre'] ?: 'CV'];
    }

    // Keep the message in the notification log even if the local mail transport fails.
    set_audit_context($actorId);
    $queue = $pdo->prepare("INSERT INTO cola_notificaciones(tipo,destinatario,asunto,cuerpo,id_postulacion,estado,intentos) VALUES('perfiles_aceptados',:to,:subject,:body,:application,'pendiente',0)");
    $queue->execute(['to' => $to, 'subject' => $subject, 'body' => $body, 'application' => $rows[0]['id_postulacion']]);
    $notificationId = (int)$pdo->lastInsertId();

    $delivery = send_project_email($to, $subject, $body, $attachments);
    $state = $delivery['sent'] ? 'enviada' : 'fallida';
    $update = $pdo->prepare('UPDATE cola_notificaciones SET estado=:state,intentos=intentos+1,error_ultimo=:error,procesado_en=NOW() WHERE id_notificacion=:id');
    $update->execute(['state' => $state, 'error' => $delivery['error'], 'id' => $notificationId]);
    return ['sent' => (bool)$delivery['sent'], 'count' => count($rows), 'message' => $delivery['sent']
        ? 'Convocatoria cerrada y correo enviado a la empresa con ' . count($rows) . ' perfil(es) aceptado(s).'
        : 'Convocatoria cerrada. El correo con ' . count($rows) . ' perfil(es) quedó registrado, pero el servidor no pudo enviarlo. Configura el SMTP y revisa la cola de notificaciones.'];
}
