<?php
declare(strict_types = 1);
// Carga las clases compartidas del sistema.
require_once dirname(__DIR__) . '/includes/db/Conexion.php';
require_once dirname(__DIR__) . '/includes/db/ConfiguracionRepo.php';
require_once dirname(__DIR__) . '/includes/db/MensajeRepo.php';
require_once dirname(__DIR__) . '/includes/db/CatalogoRepo.php';
require_once dirname(__DIR__) . '/includes/db/UsuarioRepo.php';
require_once dirname(__DIR__) . '/includes/db/BitacoraRepo.php';
require_once dirname(__DIR__) . '/includes/db/PracticaRepo.php';
require_once dirname(__DIR__) . '/includes/db/PostulacionRepo.php';
require_once dirname(__DIR__) . '/includes/db/EmpresaRepo.php';
require_once dirname(__DIR__) . '/includes/Http/Respuesta.php';
require_once dirname(__DIR__) . '/includes/Http/Peticion.php';
require_once dirname(__DIR__) . '/includes/Seguridad/Sesion.php';
require_once dirname(__DIR__) . '/includes/Seguridad/Acceso.php';
require_once dirname(__DIR__) . '/includes/Servicios/ServicioCierre.php';
require_once dirname(__DIR__) . '/includes/Servicios/ArchivosCv.php';
// Configura el entorno y protege las respuestas del navegador.
date_default_timezone_set(getenv('APP_TIMEZONE') ?: date_default_timezone_get());
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
// Protege la sesión antes de consultar la identidad.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    // La configuración controla el tiempo de inactividad.
    // El vencimiento real se valida con configuracion; esto solo fija la limpieza máxima del manejador de sesiones.
    ini_set('session.gc_maxlifetime', '86400');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Strict',
    ]);
    session_start();
}
// Envía la identidad y la IP para la auditoría en la base de datos.
Conexion::contextoAuditoria(
    isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null,
    substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)
);
