<?php
declare(strict_types = 1);
/** Mantiene una conexión PDO compartida y establece el contexto de auditoría. */
final class Conexion {
    private static ?PDO $pdo = null;
    private static ?int $usuarioContexto = null;
    private static string $ipContexto = '';
    public static function obtener(): PDO {
        if (self::$pdo instanceof PDO) return self::$pdo;
        self::cargarConfiguracion();
        $host = getenv('DATABASE_HOST');
        $base = getenv('DATABASE_NAME');
        $usuario = getenv('DATABASE_USER');
        $clave = getenv('DATABASE_PASSWORD');
        foreach (['DATABASE_HOST' => $host, 'DATABASE_NAME' => $base, 'DATABASE_USER' => $usuario, 'DATABASE_PASSWORD' => $clave] as $nombre => $valor) {
            if ($valor === false || trim($valor) === '') {
                throw new RuntimeException('Falta configurar ' . $nombre . ' en el archivo privado de conexión.');
            }
        }
        $dsn = "mysql:host={$host};dbname={$base};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $usuario, $clave, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        // Cada conexión comienza con el contexto que consumen los triggers.
        $usuarioSesion = $_SESSION['usuario_id'] ?? ($_SESSION['user_id'] ?? null);
        self::aplicarContexto(self::$pdo,
            self::$usuarioContexto ?? (is_numeric($usuarioSesion) && (int)$usuarioSesion > 0 ? (int)$usuarioSesion : null),
            self::$ipContexto !== '' ? self::$ipContexto : substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)
        );
        return self::$pdo;
    }
    public static function cargarConfiguracion(): void {
        // Permite definir una ruta explícita; en instalaciones copiadas usa primero el .env del proyecto.
        $rutaConfiguracion = getenv('APP_DATABASE_CONFIG');
        if (!$rutaConfiguracion) {
            $rutaLocal = dirname(__DIR__, 2) . '/config/.env';
            $rutaPlantilla = dirname(__DIR__, 2) . '/config/.env.example';
            $rutaPrivada = dirname(__DIR__, 4) . '/private/practicas.env';
            // Usa la plantilla pública en instalaciones recién descargadas sin configuración local.
            $rutaConfiguracion = is_file($rutaLocal) ? $rutaLocal : (is_file($rutaPlantilla) ? $rutaPlantilla : $rutaPrivada);
        }
        self::cargarEntorno($rutaConfiguracion);
    }
    public static function contextoAuditoria(?int $usuarioId, string $ip): void {
        self::$usuarioContexto = $usuarioId;
        self::$ipContexto = substr($ip, 0, 45);
        // La sesión pública y el token CSRF no necesitan abrir la base de datos.
        // Si ya hay una conexión, actualiza el contexto de auditoría de inmediato.
        if (self::$pdo instanceof PDO) self::aplicarContexto(self::$pdo, $usuarioId, self::$ipContexto);
    }
    private static function aplicarContexto(PDO $pdo, ?int $usuarioId, string $ip): void {
        $sentencia = $pdo->prepare('SET @usuario_id = :usuario_id, @ip_cliente = :ip_cliente');
        $sentencia->execute(['usuario_id' => $usuarioId, 'ip_cliente' => $ip !== '' ? $ip : null]);
    }
    public static function cargarEntorno(string $ruta): void {
        if (!is_file($ruta) || !is_readable($ruta)) return;
        foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) continue;
            [$nombre, $valor] = array_map('trim', explode('=', $linea, 2));
            $valor = trim($valor, "\"'");
            if ($nombre !== '' && getenv($nombre) === false) putenv($nombre . '=' . $valor);
        }
    }
}
