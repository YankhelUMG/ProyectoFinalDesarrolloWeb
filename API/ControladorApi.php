<?php
declare(strict_types = 1);
/** Elige un controlador sin mezclar consultas y reglas de negocio. */
final class ControladorApi {
    /** Elige el controlador según la ruta y el método HTTP. */
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
