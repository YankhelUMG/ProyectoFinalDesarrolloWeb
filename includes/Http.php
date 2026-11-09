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

    /** Adapta rutas antiguas antes de elegir el controlador. */
    public static function prepararRutaAnterior(): void {
        $ruta = Peticion::ruta();
        $anterior = implode('/', $ruta);
        $destinos = [
            'auth' => 'auth',
            'auth/login' => 'auth',
            'acceso-catalogos/POST/auth' => 'auth',
            'administracion/POST/empresas' => 'admin/empresas',
            'administracion/POST/usuarios' => 'admin/usuarios',
            'practicas/POST/oportunidades' => 'practicas',
            'postulaciones/POST/postulaciones' => 'postulaciones',
            'perfil/POST/perfil' => 'perfil',
            'perfil/POST/perfil_empresa' => 'perfil',
            'consultas/GET/cv' => 'cv',
        ];
        // Solo adapta archivos PHP heredados; las rutas REST pasan sin cambios.
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if (!str_ends_with(strtolower($path), '.php') || !isset($destinos[$anterior])) return;

        $destino = $destinos[$anterior];
        $metodo = strtoupper((string)($_POST['_method'] ?? Peticion::metodo()));
        $metodo = in_array($metodo, ['GET', 'POST', 'PUT', 'DELETE'], true) ? $metodo : Peticion::metodo();
        $accion = (string)($_POST['action'] ?? 'login');
        if ($destino === 'auth') {
            $destino .= '/' . match ($metodo === 'DELETE' ? 'logout' : $accion) {
                'login' => $metodo === 'GET' ? 'me' : 'login',
                'logout' => 'logout',
                'registro', 'register' => 'registro-estudiante',
                'registro_empresa' => 'registro-empresa',
                'cambiar_clave', 'change_password' => 'cambiar-clave',
                default => $accion,
            };
            $_POST['identificador'] ??= $_POST['usuario'] ?? $_POST['username'] ?? '';
            $_POST['contrasena'] ??= $_POST['password'] ?? '';
        }
        if ($destino === 'admin/empresas' && $metodo !== 'GET') {
            $destino .= '/' . (int)($_POST['id_empresa'] ?? 0) . '/revision';
            $_POST['estado_codigo'] ??= match ($_POST['decision'] ?? '') {
                'aprobar' => 'aprobada', 'rechazar' => 'rechazada',
                'solicitar_cambios' => 'requiere_cambios', default => '',
            };
            $_POST['comentario'] ??= $_POST['comentario_revision'] ?? '';
            $metodo = 'PUT';
        }
        if ($destino === 'practicas') {
            $_POST['id_practica'] ??= $_POST['id_oportunidad'] ?? null;
            if ($metodo !== 'POST' && !empty($_POST['id_practica'])) $destino .= '/' . (int)$_POST['id_practica'];
        }
        if ($destino === 'postulaciones') {
            $_POST['id_practica'] ??= $_POST['id_oportunidad'] ?? null;
            if ($metodo !== 'POST' && !empty($_POST['id_postulacion'])) $destino .= '/' . (int)$_POST['id_postulacion'];
            $_POST['estado_codigo'] ??= $_POST['estado'] ?? '';
            $_POST['comentario'] ??= $_POST['observaciones'] ?? '';
        }
        if ($destino === 'cv') $destino .= '/' . (int)($_GET['id'] ?? $_GET['postulacion'] ?? 0);
        if (isset($_POST['csrf']) && !isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = $_POST['csrf'];
        }
        $_SERVER['REQUEST_METHOD'] = $metodo;
        $_SERVER['REQUEST_URI'] = '/api/' . $destino;
    }
}

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
