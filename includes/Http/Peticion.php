<?php
declare(strict_types = 1);
/** Normaliza la solicitud HTTP para que los controladores trabajen con datos claros. */
final class Peticion {
    /** Devuelve el verbo HTTP en mayúsculas. */
    public static function metodo(): string {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }
    /** Extrae los segmentos de ruta y elimina el sufijo .php cuando está presente. */
    public static function ruta(): array {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        if (preg_match('#/api(?=/|$)#i', $path, $match, PREG_OFFSET_CAPTURE)) {
            $path = substr($path, $match[0][1] + strlen($match[0][0]));
        }
        $partes = array_values(array_filter(explode('/', trim($path, '/')), static fn($v) => $v !== ''));
        if ($partes) {
            $ultimo = array_key_last($partes);
            $partes[$ultimo] = preg_replace('/\.php$/i', '', $partes[$ultimo]) ?? $partes[$ultimo];
        }
        return $partes;
    }
    /** Lee el cuerpo JSON o los campos de un formulario. */
    public static function datos(): array {
        $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($type, 'application/json')) {
            $d = json_decode((string)file_get_contents('php://input'), true);
            return is_array($d) ? $d : [];
        }
        return $_POST;
    }
    /** Obtiene un encabezado HTTP de forma segura. */
    public static function encabezado(string $nombre): ?string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $nombre));
        $value = $_SERVER[$key] ?? null;
        return is_string($value) ? $value : null;
    }
    public static function entero(string $key, int $default = 1): int {
        $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
        return $value === false || $value === null ? $default : max(1, (int)$value);
    }
}
