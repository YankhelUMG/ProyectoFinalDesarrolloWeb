<?php
declare(strict_types=1);

// Estas comprobaciones no abren conexiones ni cargan credenciales.
require dirname(__DIR__) . '/API/controladores/ControladorBase.php';
foreach (glob(dirname(__DIR__) . '/API/controladores/*.php') as $archivo) require_once $archivo;
require dirname(__DIR__) . '/includes/Http/Peticion.php';
require dirname(__DIR__) . '/includes/Http/Respuesta.php';
require dirname(__DIR__) . '/includes/Seguridad/Sesion.php';

$verificaciones = 0;
function comprobar(bool $condicion, string $mensaje): void {
    global $verificaciones;
    if (!$condicion) throw new RuntimeException($mensaje);
    $verificaciones++;
}

foreach (glob(dirname(__DIR__) . '/API/controladores/*.php') as $archivo) {
    $clase = new ReflectionClass(basename($archivo, '.php'));
    preg_match_all('/\$this->(\w+)\s*\(/', file_get_contents($archivo), $llamadas);
    foreach (array_unique($llamadas[1]) as $metodo) {
        comprobar($clase->hasMethod($metodo), $clase->getName() . ' llama un método inexistente: ' . $metodo);
    }
}

foreach ([
    '/ProyectoFinalDesarrolloWeb/public/api/csrf' => ['csrf'],
    '/ProyectoFinalDesarrolloWeb/API/auth/login' => ['auth', 'login'],
    '/public/api/practicas/12/postulaciones?pagina=2' => ['practicas', '12', 'postulaciones'],
    '/API/perfil.php' => ['perfil'],
] as $uri => $esperada) {
    $_SERVER['REQUEST_URI'] = $uri;
    comprobar(Peticion::ruta() === $esperada, 'Se alteró la ruta ' . $uri);
}
$_SERVER['REQUEST_URI'] = '/ProyectoFinalDesarrolloWeb/API/acceso-catalogos/POST/auth.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
Peticion::prepararRutaAnterior();
comprobar(Peticion::ruta() === ['auth', 'me'], 'El enlace de acceso anterior debe conservar su ruta actual.');
$_SESSION = [];
$token = Sesion::csrfNuevo();
comprobar(strlen($token) === 64 && ctype_xdigit($token), 'El CSRF debe tener 32 bytes aleatorios en hexadecimal.');
comprobar(Sesion::csrfNuevo() === $token, 'Consultar el CSRF conserva el token de la misma sesión.');
echo "OK: $verificaciones comprobaciones de rutas, métodos y CSRF sin base de datos.", PHP_EOL;
