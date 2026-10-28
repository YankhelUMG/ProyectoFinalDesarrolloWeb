<?php
declare(strict_types = 1);
/** Proporciona mensajes configurables para respuestas y errores. */
final class MensajeRepo {
    public function obtener(string $codigo, string $alternativo = 'No se pudo completar la solicitud.'): string {
        $q = Conexion::obtener()->prepare('SELECT texto FROM mensajes WHERE codigo=:codigo AND eliminado=0 LIMIT 1');
        $q->execute(['codigo' => $codigo]);
        return (string) ($q->fetchColumn() ?: $alternativo);
    }
}
