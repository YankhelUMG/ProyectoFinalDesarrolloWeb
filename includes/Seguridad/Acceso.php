<?php
declare(strict_types = 1);
/** Aplica las comprobaciones de rol, permiso, CSRF y aprobación de empresa. */
final class Acceso {
    public static function requerirRol(array $roles, bool $empresaPendiente = false): array {
        $u = Sesion::actual($empresaPendiente);
        if (!in_array($u['rol_codigo'] ?? '', $roles, true)) {
            (new BitacoraRepo())->registrar('ACCESO_DENEGADO', 'usuarios', (string)$u['id_usuario'], null, ['motivo' => 'rol_no_autorizado'], (int)$u['id_usuario']);
            Respuesta::error((new MensajeRepo())->obtener('error_sin_permiso', 'No tienes permiso para realizar esta acción.'), 403);
        }
        $ruta = implode('/', Peticion::ruta());
        if (!empty($u['debe_cambiar_password']) && $ruta !== 'auth/cambiar-clave') Respuesta::error((new MensajeRepo())->obtener('error_cambio_password', 'Debes cambiar la contraseña temporal antes de continuar.'), 403);
        return $u;
    }
    public static function requerirPermiso(string $permiso, bool $empresaPendiente = false): array {
        $u = Sesion::actual($empresaPendiente);
        $ruta = implode('/', Peticion::ruta());
        if (!empty($u['debe_cambiar_password']) && $ruta !== 'auth/cambiar-clave') Respuesta::error((new MensajeRepo())->obtener('error_cambio_password', 'Debes cambiar la contraseña temporal antes de continuar.'), 403);
        if (!(new CatalogoRepo())->tienePermiso((int)$u['id_usuario'], $permiso)) {
            (new BitacoraRepo())->registrar('ACCESO_DENEGADO', 'permisos', $permiso, null, ['usuario_id' => $u['id_usuario']], (int)$u['id_usuario']);
            Respuesta::error((new MensajeRepo())->obtener('error_sin_permiso', 'No tienes permiso para realizar esta acción.'), 403);
        }
        return $u;
    }
    public static function csrfEnMutacion(): void {
        if (in_array(Peticion::metodo(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) Sesion::verificarCsrf();
    }
    public static function requiereEmpresaAprobada(int $usuarioId): void {
        if (!(new EmpresaRepo())->aprobada($usuarioId)) Respuesta::error((new MensajeRepo())->obtener('error_empresa_pendiente', 'La empresa debe estar aprobada para realizar esta acción.'), 403);
    }
}
