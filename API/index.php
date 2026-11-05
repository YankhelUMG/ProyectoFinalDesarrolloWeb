<?php
declare(strict_types = 1);
// Carga la aplicación, dirige la solicitud y controla errores.
$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
if (preg_match('#/api/admin/roles\.php$#i', $path, $routeMatch, PREG_OFFSET_CAPTURE)) {
    if (preg_match('#/api(?=/|$)#i', $path, $apiMatch, PREG_OFFSET_CAPTURE)) {
        $prefix = substr($path, 0, $apiMatch[0][1] + strlen($apiMatch[0][0]));
        $query = parse_url($uri, PHP_URL_QUERY);
        $_SERVER['REQUEST_URI'] = $prefix . '/admin/catalogos/roles' . ($query ? '?' . $query : '');
    }
}
if (preg_match('#/api/admin/index\.php$#i', $path, $routeMatch, PREG_OFFSET_CAPTURE)) {
    $prefix = substr($path, 0, $routeMatch[0][1]);
    header('Location: ' . $prefix . '/admin.php', true, 302);
    exit;
}
if (preg_match('#/api/admin/roles/index\.php$#i', $path)) {
    $base = preg_replace('#/(?:public/)?api/.*$#i', '', $path) ?? '';
    header('Location: ' . $base . '/pages/usuarios.php', true, 302);
    exit;
}
/** Elige un controlador sin mezclar consultas y reglas de negocio. */
final class ControladorApi {
    public function ejecutar(): never {
        $ruta = Peticion::ruta();
        $metodo = Peticion::metodo();
        if ($metodo === 'OPTIONS') {
            header('Allow: GET, POST, PUT, DELETE, OPTIONS');
            http_response_code(204);
            exit;
        }
        if (!in_array($metodo, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
            Respuesta::error('Método HTTP no permitido.', 405);
        }
        Acceso::csrfEnMutacion();
        $destinos = [
            'csrf' => [AccesoControlador::class, 'catalogosPublicos'],
            'sistema' => [AccesoControlador::class, 'catalogosPublicos'],
            'catalogos' => [AccesoControlador::class, 'catalogos'],
            'auth' => [AccesoControlador::class, 'sesion'],
            'practicas' => [PracticasControlador::class, 'rutasPracticas'],
            'postulaciones' => [PostulacionesControlador::class, 'rutasPostulaciones'],
            'admin' => [AdministracionControlador::class, 'rutasAdmin'],
            'perfil' => [PerfilControlador::class, 'rutasPerfil'],
            'cv' => [PerfilControlador::class, 'descargarCv'],
            'reportes' => [PerfilControlador::class, 'reportes'],
            'dashboard' => [PerfilControlador::class, 'dashboard'],
        ];
        $destino = $destinos[$ruta[0] ?? ''] ?? null;
        if ($destino === null) Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
        [$clase, $accion] = $destino;
        if (($ruta[0] === 'practicas' && $metodo === 'DELETE') ||
            (in_array($ruta[0], ['csrf', 'sistema', 'catalogos', 'auth'], true) && $metodo === 'PUT') ||
            (in_array($ruta[0], ['cv', 'reportes', 'dashboard'], true) && $metodo !== 'GET')) {
            Respuesta::error('Método no permitido para este grupo de rutas.', 405);
        }
        $controlador = new $clase($ruta, $metodo, Peticion::datos());
        $controlador->$accion();
    }
}

try {
    require_once dirname(__DIR__) . '/config/bootstrap.php';
    Peticion::prepararRutaAnterior();
    require_once __DIR__ . '/controladores/ControladorBase.php';
    foreach (['Acceso', 'Practicas', 'Postulaciones', 'Administracion', 'Usuarios', 'Perfil'] as $nombre) {
        require_once __DIR__ . '/controladores/' . $nombre . 'Controlador.php';
    }
    (new ControladorApi())->ejecutar();
} catch (DomainException $e) {
    Respuesta::error($e->getMessage(), 422);
} catch (PDOException $e) {
    error_log($e->__toString());
    if (str_contains($e->getMessage(), "Unknown column 'fecha_inicio'")) {
        Respuesta::error('La base de datos necesita la actualización de fecha de inicio antes de publicar prácticas.', 500, ['fecha_inicio' => 'Falta actualizar la base de datos para este campo.']);
    }
    if ((string)$e->getCode() === '23000') {
        $mensajeDuplicado = mb_strtolower($e->getMessage());
        $erroresDuplicados = [];
        if (str_contains($mensajeDuplicado, 'uq_usuarios_correo_activo')) $erroresDuplicados['correo'] = 'Este correo ya está registrado.';
        if (str_contains($mensajeDuplicado, 'uq_usuarios_usuario_activo')) $erroresDuplicados['usuario'] = 'Este nombre de usuario ya está registrado.';
        if (str_contains($mensajeDuplicado, 'uq_estudiante_carnet_activo')) $erroresDuplicados['carnet'] = 'Este carné ya está registrado.';
        if (str_contains($mensajeDuplicado, 'uq_empresa_fiscal_activo')) $erroresDuplicados['identificacion_fiscal'] = 'Esta identificación fiscal ya está registrada.';
        Respuesta::error($erroresDuplicados ? 'Corrige los campos marcados.': (new ConfiguracionRepo())->mensaje('error_duplicado', 'Ya existe un registro con esos datos.'), 409, $erroresDuplicados);
    }
    if (class_exists('Respuesta')) Respuesta::error('No se pudo completar la solicitud.', 500);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"mensaje":"No se pudo completar la solicitud.","datos":null,"errores":{}}';
} catch (Throwable $e) {
    error_log($e->__toString());
    if (class_exists('Respuesta')) Respuesta::error('No se pudo completar la solicitud.', 500);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"mensaje":"No se pudo completar la solicitud.","datos":null,"errores":{}}';
}
