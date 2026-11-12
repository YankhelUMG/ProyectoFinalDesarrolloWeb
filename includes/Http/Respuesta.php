<?php
declare(strict_types = 1);
/** Devuelve todas las respuestas de la API con el mismo formato JSON. */
final class Respuesta {
    public static function json(bool $ok, string $mensaje = '', mixed $datos = null, array $errores = [], int $estado = 200): never {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'mensaje' => $mensaje, 'datos' => $datos, 'errores' => (object)$errores], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    public static function exito(mixed $datos = null, string $mensaje = '', int $estado = 200): never {
        self::json(true, $mensaje, $datos, [], $estado);
    }
    public static function error(string $mensaje, int $estado = 400, array $errores = []): never {
        self::json(false, $mensaje, null, $errores, $estado);
    }
}
