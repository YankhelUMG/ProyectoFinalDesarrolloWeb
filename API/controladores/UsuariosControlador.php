<?php
declare(strict_types=1);
/** Gestión de cuentas y sus relaciones. */
final class UsuariosControlador extends ControladorBase {
    /** Gestión de cuentas: listar, crear, editar, desactivar, eliminar y restaurar. */
    public function adminUsuarios(array $cuentaActual, array $segmentosRuta): never {
        Acceso::requerirPermiso('usuarios.gestionar');
        $id = (int)($segmentosRuta[0] ?? 0);
        $sub = $segmentosRuta[1] ?? '';
        if ($segmentosRuta === [] && $this->metodo === 'GET') {
            $p = $this->paginacion();
            $excluirSuperadministradores = $cuentaActual['rol_codigo'] !== 'superadmin';
            $rows = ($_GET['eliminados'] ?? '') === '1'
                ? $this->usuarios->listarEliminados($p['limite'], $p['offset'], $excluirSuperadministradores)
                : $this->usuarios->listarPagina(
                    $p['limite'],
                    $p['offset'],
                    isset($_GET['q']) ? (string)$_GET['q'] : null,
                    $excluirSuperadministradores,
                    isset($_GET['estado']) && $_GET['estado'] !== '' ? (string)$_GET['estado'] : null,
                );
            Respuesta::exito(['elementos' => $rows, 'pagina' => $p['pagina'], 'por_pagina' => $p['limite']]);
        }
        if ($segmentosRuta === [] && $this->metodo === 'POST') $this->crearCuentaGestionada($cuentaActual);
        if ($sub === '' && $this->metodo === 'GET') {
            $target = $this->usuarios->detalleVisible($id);
            if (!$target) Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró la cuenta.'), 404);
            $this->protegerSuperadmin($cuentaActual, $target);
            Respuesta::exito($target);
        }
        if ($id < 1) Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'Identificador de usuario inválido.'), 404);
        if ($sub === 'restablecer-clave' && $this->metodo === 'POST') {
            $actor = Acceso::requerirRol(['superadmin']);
            Acceso::requerirPermiso('usuarios.password_reset');
            $target = $this->usuarios->detalleAdmin($id);
            if (!$target || $target['eliminado']) Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró una cuenta activa.'), 404);
            $longitudMinima = min(200, max(8, (int)(new ConfiguracionRepo())->obtener('password_longitud_minima', '10')));
            $aleatorio = rtrim(strtr(base64_encode(random_bytes($longitudMinima)), '+/', '-_'), '=');
            $temporal = substr($aleatorio, 0, $longitudMinima - 3) . 'A9!';
            $this->usuarios->transaccion(function() use ($id, $temporal, $actor) {
                $this->usuarios->restablecerClave($id, password_hash($temporal, PASSWORD_DEFAULT), (int)$actor['id_usuario']);
                $this->usuarios->revocarSesionesUsuario($id, (int)$actor['id_usuario']);
            });
            (new BitacoraRepo())->registrar('CLAVE_RESTABLECIDA', 'usuarios', (string)$id, null, ['debe_cambiar_password' => true], (int)$actor['id_usuario']);
            Respuesta::exito(['contrasena_temporal' => $temporal, 'debe_cambiar_password' => true], 'Contraseña temporal generada. Se muestra una sola vez.');
        }
        if ($sub === 'restaurar' && $this->metodo === 'POST') {
            $target = $this->usuarios->detalleAdmin($id);
            $this->protegerSuperadmin($cuentaActual, $target);
            if (!$target || (int)$target['eliminado'] !== 1) throw new DomainException('La cuenta no está eliminada.');
            $this->usuarios->transaccion(function()use($cuentaActual, $target, $id) {
                $role = $this->rolCodigoPorId((int)$target['id_rol']);
                if ($role === 'empresa') $this->empresas->restaurarEmpresa($id, (int)$cuentaActual['id_usuario']);
                if ($role === 'estudiante') $this->estudiantes->restaurarEstudiante($id, (int)$cuentaActual['id_usuario']);
                $this->usuarios->restaurar($id, (int)$cuentaActual['id_usuario']);
                $activo = $this->catalogos->estadoId('usuario', 'activo');
                $this->usuarios->cambiarEstado($id, $activo, (int)$cuentaActual['id_usuario']);
                $this->usuarios->registrarHistorial($id, (int)$target['id_estado'], $activo, (int)$cuentaActual['id_usuario'], 'Cuenta restaurada.');
            });
            (new BitacoraRepo())->registrar('RESTAURACION', 'usuarios', (string)$id, null, ['eliminado' => false], (int)$cuentaActual['id_usuario']);
            Respuesta::exito(['id_usuario' => $id], 'Cuenta restaurada.');
        }
        if ($sub === 'estado' && $this->metodo === 'PUT') {
            $target = $this->usuarios->detalleAdmin($id);
            $this->protegerSuperadmin($cuentaActual, $target);
            if (!$target || !empty($target['eliminado'])) throw new DomainException('No se encontró una cuenta activa.');
            $estadoCodigo = $this->texto('estado_codigo', 40, true);
            if ($estadoCodigo === 'inactivo') {
                $this->eliminarCuentaGestionada($cuentaActual, $target, false);
                Respuesta::exito(['id_usuario' => $id, 'estado_codigo' => $estadoCodigo], 'Cuenta desactivada y relaciones activas canceladas.');
            }
            if (
                $estadoCodigo === 'activo' &&
                $this->rolCodigoPorId((int)$target['id_rol']) === 'empresa' &&
                !$this->empresas->aprobada($id)
            ) throw new DomainException('Primero debe aprobarse la solicitud de la empresa.');
            $destino = $this->catalogos->estadoId('usuario', $estadoCodigo);
            $actual = (int)$target['id_estado'];
            $req = $this->catalogos->puedeTransicionar('usuario', $actual, $destino, $this->catalogos->rolId((string)$cuentaActual['rol_codigo']));
            if ($req === null) throw new DomainException('La transición de cuenta no está permitida.');
            $comentario = $this->texto('comentario', 500, false);
            $this->validarComentario($comentario, $req);
            $this->usuarios->transaccion(function()use($id, $destino, $actual, $cuentaActual, $comentario) {
                $this->usuarios->cambiarEstado($id, $destino, (int)$cuentaActual['id_usuario']);
                $this->usuarios->registrarHistorial($id, $actual, $destino, (int)$cuentaActual['id_usuario'], $comentario);
            });
            Respuesta::exito(['id_usuario' => $id, 'estado_codigo' => $estadoCodigo], 'Estado de la cuenta actualizado.');
        }
        if ($this->metodo === 'PUT' && $sub === 'rol') {
            $this->asignarRolGestionado($cuentaActual, $id);
        }
        if ($this->metodo === 'PUT' && $sub === '') {
            $target = $this->usuarios->detalleAdmin($id);
            $this->protegerSuperadmin($cuentaActual, $target);
            if (!$target || !empty($target['eliminado'])) throw new DomainException('No se encontró una cuenta activa.');
            $correo = $this->correo('correo');
            $nombres = $this->nombrePersona('nombres');
            $apellidos = $this->nombrePersona('apellidos');
            $telefono = $this->telefono('telefono', $this->rolCodigoPorId((int)$target['id_rol']) === 'estudiante');
            $this->usuarios->actualizarDatos($id, ['correo' => $correo, 'nombres' => $nombres, 'apellidos' => $apellidos, 'telefono' => $telefono, 'actor' => (int)$cuentaActual['id_usuario']]);
            Respuesta::exito(['id_usuario' => $id], (new ConfiguracionRepo())->mensaje('exito_guardado', 'Cuenta actualizada.'));
        }
        if ($this->metodo === 'DELETE' && $sub === '') {
            $target = $this->usuarios->detalleAdmin($id);
            $this->protegerSuperadmin($cuentaActual, $target);
            if (!$target || $target['eliminado']) throw new DomainException('La cuenta no existe o ya está eliminada.');
            $this->eliminarCuentaGestionada($cuentaActual, $target);
            Respuesta::exito(['id_usuario' => $id], 'Cuenta desactivada y conservada en el historial.');
        }
        Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    /** Crea una cuenta desde el panel de administración. */
    private function crearCuentaGestionada(array $cuentaActual): never {
        Acceso::requerirPermiso('usuarios.gestionar');
        $rolCodigo = $this->texto('rol_codigo', 40, true);
        $roles = $cuentaActual['rol_codigo'] === 'superadmin' ? ['superadmin', 'admin', 'empresa', 'estudiante']: ['empresa', 'estudiante'];
        if (!in_array($rolCodigo, $roles, true)) Respuesta::error((new ConfiguracionRepo())->mensaje('error_sin_permiso', 'No puedes crear ese tipo de cuenta.'), 403);
        $correo = $this->correo('correo');
        $telefono = $this->telefono('telefono', in_array($rolCodigo, ['estudiante', 'empresa'], true));
        $usuario = $this->texto('usuario', 40, true);
        $nombres = $this->nombrePersona('nombres');
        $apellidos = $this->nombrePersona('apellidos');
        $clave = $this->claveValida();
        $rol = $this->catalogos->rolId($rolCodigo);
        $estadoUsuario = $this->catalogos->estadoId('usuario', 'activo');
        $cv = null;
        if ($rolCodigo === 'estudiante') {
            $carrera = $this->entero('id_carrera');
            if (!$this->catalogos->idActivo('carreras', $carrera)) throw new DomainException('Selecciona una carrera activa.');
            $carnet = $this->carnet();
            $universidad = $this->universidad();
            $semestre = $this->semestre();
            $cv = (new ArchivosCv())->guardar($_FILES['cv'] ?? []);
        }
        if ($rolCodigo === 'empresa') {
            $legal = $this->texto('nombre_legal', 180, true);
            $contacto = $this->correo('correo_contacto');
            $comercial = $this->texto('nombre_comercial', 180, false);
            $fiscal = $this->texto('identificacion_fiscal', 50, false);
            $sector = $this->texto('sector', 100, false);
            $descripcion = $this->texto('descripcion', 5000, false);
            $web = $this->texto('sitio_web', 255, false);
            if ($web !== null && (!filter_var($web, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($web, PHP_URL_SCHEME)), ['http', 'https'], true))) throw new DomainException('Ingresa una dirección web segura válida o deja el campo vacío.');
            $direccion = $this->texto('direccion', 255, false);
        }
        $id = $this->usuarios->transaccion(function()use($cuentaActual, $rolCodigo, $rol, $estadoUsuario, $correo, $telefono, $usuario, $nombres, $apellidos, $clave, $cv) {
            $id = $this->usuarios->registrar([
                'rol' => $rol,
                'estado' => $estadoUsuario,
                'usuario' => $usuario,
                'correo' => $correo,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'telefono' => $telefono,
                'hash' => password_hash($clave, PASSWORD_DEFAULT),
                'cambio' => 1,
                'actor' => (int)$cuentaActual['id_usuario']
            ]);
            $this->usuarios->registrarHistorial($id, null, $estadoUsuario, (int)$cuentaActual['id_usuario'], 'Cuenta creada por coordinación.');
            if ($rolCodigo === 'estudiante') $this->estudiantes->crearEstudiante([
                'usuario' => $id,
                'carrera' => $this->entero('id_carrera'),
                'carnet' => $this->carnet(),
                'semestre' => $this->semestre(),
                'universidad' => $this->universidad(),
                'cv_nombre' => $cv['nombre'],
                'cv_ruta' => $cv['ruta'],
                'cv_mime' => $cv['mime'],
                'actor' => (int)$cuentaActual['id_usuario']
            ]);
            if ($rolCodigo === 'empresa') {
                $estadoEmpresa = $this->catalogos->estadoId('empresa', 'pendiente');
                $empresaId = $this->empresas->crearEmpresa([
                    'usuario' => $id,
                    'estado' => $estadoEmpresa,
                    'legal' => $this->texto('nombre_legal', 180, true),
                    'comercial' => $this->texto('nombre_comercial', 180, false),
                    'fiscal' => $this->texto('identificacion_fiscal', 50, false),
                    'sector' => $this->texto('sector', 100, false),
                    'descripcion' => $this->texto('descripcion', 5000, false),
                    'correo' => $this->correo('correo_contacto'),
                    'telefono' => $telefono,
                    'web' => $this->texto('sitio_web', 255, false),
                    'direccion' => $this->texto('direccion', 255, false),
                    'actor' => (int)$cuentaActual['id_usuario']
                ]);
                $this->empresas->registrarHistorial($empresaId, null, $estadoEmpresa, (int)$cuentaActual['id_usuario'], 'Cuenta creada por coordinación.');
            }
            return $id;
        });
        (new BitacoraRepo())->registrar('CREACION_CUENTA', 'usuarios', (string)$id, null, ['rol' => $rolCodigo], (int)$cuentaActual['id_usuario']);
        Respuesta::exito(['id_usuario' => $id, 'debe_cambiar_password' => true], 'Cuenta creada. La persona deberá cambiar su contraseña temporal.', 201);
    }
    /** Cambia el rol de una cuenta. */
    private function asignarRolGestionado(array $cuentaActual, int $id): never {
        $target = $this->usuarios->detalleAdmin($id);
        $this->protegerSuperadmin($cuentaActual, $target);
        $rolCodigo = $this->texto('rol_codigo', 40, true);
        $permitidos = $cuentaActual['rol_codigo'] === 'superadmin' ? ['superadmin', 'admin', 'empresa', 'estudiante']: ['admin', 'empresa', 'estudiante'];
        if (!in_array($rolCodigo, $permitidos, true)) Respuesta::error((new ConfiguracionRepo())->mensaje('error_sin_permiso', 'No puedes asignar ese perfil.'), 403);
        $actual = (int)$target['id_rol'];
        $nuevo = $this->catalogos->rolId($rolCodigo);
        if (!$nuevo) throw new DomainException('El perfil no está configurado.');
        if ($actual !== $nuevo) throw new DomainException('Para preservar los datos del perfil, no se permite cambiar de rol a una cuenta existente.');
        Respuesta::exito(['id_usuario' => $id, 'rol_codigo' => $rolCodigo], 'El perfil ya coincide con la cuenta.');
    }
    /** Impide que se modifique a un superadministrador sin permiso. */
    private function protegerSuperadmin(array $actor, ?array $target): void {
        if (!$target) throw new DomainException('No se encontró la cuenta.');
        $role = (int)$target['id_rol'];
        $super = $this->catalogos->rolId('superadmin');
        if ((int)$role === (int)$super && $actor['rol_codigo'] !== 'superadmin') Respuesta::error((new ConfiguracionRepo())->mensaje('error_sin_permiso', 'Solo superadministración puede administrar otra cuenta superadministradora.'), 403);
    }
    /** Elimina (lógicamente) una cuenta y cierra sus pendientes. */
    private function eliminarCuentaGestionada(array $actor, array $target, bool $eliminarLogico = true): void {
        $id = (int)$target['id_usuario'];
        $roleCode = $this->rolCodigoPorId((int)$target['id_rol']);
        $actorRole = $actor['rol_codigo'];
        $this->usuarios->transaccion(function()use($actor, $target, $id, $roleCode, $actorRole, $eliminarLogico) {
            Conexion::contextoAuditoria((int)$actor['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
            if ($roleCode === 'empresa') {
                foreach ($this->postulaciones->activasEmpresa($id) as $p) $this->cancelarPostulacionGestionada($p, $actor, $actorRole);
                foreach ($this->practicas->idsEmpresa($id) as $p) {
                    if (($p['estado_codigo'] ?? '') === 'cancelada') continue;
                    $cancel = $this->catalogos->estadoId('practica', 'cancelada');
                    $can = $this->catalogos->puedeTransicionar('practica', (int)$p['id_estado'], $cancel, $this->catalogos->rolId($actorRole));
                    if ($can === null) throw new DomainException('Falta configurar la cancelación de una práctica de la empresa. No se desactivó la cuenta.');
                    $comentario = 'Empresa desactivada.';
                    $this->practicas->cambiarEstado((int)$p['id_practica'], $cancel, (int)$actor['id_usuario'], $comentario);
                    $this->practicas->registrarHistorial((int)$p['id_practica'], (int)$p['id_estado'], $cancel, (int)$actor['id_usuario'], $comentario);
                }
                if ($eliminarLogico) $this->empresas->eliminarPerfil($id, (int)$actor['id_usuario']);
            }
            if ($roleCode === 'estudiante') {
                foreach ($this->postulaciones->activasEstudiante($id) as $p) $this->cancelarPostulacionGestionada($p, $actor, $actorRole);
                if ($eliminarLogico) $this->estudiantes->eliminarEstudiante($id, (int)$actor['id_usuario']);
            }
            $inactivo = $this->catalogos->estadoId('usuario', 'inactivo');
            if ((int)$target['id_estado'] !== $inactivo) {
                $req = $this->catalogos->puedeTransicionar('usuario', (int)$target['id_estado'], $inactivo, $this->catalogos->rolId($actorRole));
                if ($req === null) throw new DomainException('La transición de desactivación de cuenta no está configurada.');
                $this->usuarios->cambiarEstado($id, $inactivo, (int)$actor['id_usuario']);
                $this->usuarios->registrarHistorial($id, (int)$target['id_estado'], $inactivo, (int)$actor['id_usuario'], 'Cuenta desactivada.');
            }
            $this->usuarios->revocarSesionesUsuario($id, (int)$actor['id_usuario']);
            if ($eliminarLogico) $this->usuarios->eliminar($id, (int)$actor['id_usuario']);
        });
    }
    /** Cancela una postulación al eliminar la cuenta. */
    private function cancelarPostulacionGestionada(array $p, array $actor, string $role): void {
        $destino = $this->catalogos->estadoId('postulacion', 'cancelada');
        $req = $this->catalogos->puedeTransicionar('postulacion', (int)$p['id_estado'], $destino, $this->catalogos->rolId($role));
        if ($req === null) throw new DomainException('Falta configurar la cancelación de una postulación activa. No se desactivó la cuenta.');
        $comentario = 'Cuenta relacionada desactivada.';
        $this->postulaciones->cambiarEstado((int)$p['id_postulacion'], $destino, (int)$actor['id_usuario'], $comentario);
        $this->postulaciones->registrarHistorial('postulacion', (int)$p['id_postulacion'], (int)$p['id_estado'], $destino, (int)$actor['id_usuario'], $comentario);
    }
    /** Obtiene el código de un rol por su id. */
    private function rolCodigoPorId(int $id): string {
        foreach ($this->catalogos->lista('rol') as $r) if ((int)$r['id'] === $id) return (string)$r['codigo'];
        return '';
    }
}
