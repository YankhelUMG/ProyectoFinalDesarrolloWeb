<?php
declare(strict_types=1);
/** Postulaciones e historial. */
final class PostulacionesControlador extends ControladorBase {
    /** Enruta listado, detalle, historial, cambio de estado y retiro. */
    public function rutasPostulaciones(): never {
        $segmentosRuta = array_slice($this->ruta, 1);
        $cierre = new ServicioCierre();
        if ($this->metodo === 'GET' && $segmentosRuta === []) {
            $cierre->procesarAutomaticos();
            $cuentaActual = Sesion::actual();
            $p = $this->paginacion();
            switch ($cuentaActual['rol_codigo']) {
                case 'estudiante': Acceso::requerirPermiso('postulaciones.consultar_propias');
                $rows = $this->postulaciones->propias((int)$cuentaActual['id_usuario']);
                break;
                case 'empresa': Acceso::requerirPermiso('postulaciones.consultar_empresa');
                Acceso::requiereEmpresaAprobada((int)$cuentaActual['id_usuario']);
                $rows = $this->postulaciones->empresa((int)$cuentaActual['id_usuario']);
                break;
                case 'admin': case 'superadmin': Acceso::requerirPermiso('postulaciones.consultar');
                $rows = $this->postulaciones->admin($p['limite'], $p['offset'], isset($_GET['estado']) ? (string)$_GET['estado']: null);
                break;
                default: Respuesta::error('No tienes permiso para consultar postulaciones.', 403);
            }
            Respuesta::exito($rows);
        }
        if ($this->metodo === 'POST' && $segmentosRuta === []) $this->crearPostulacion($this->entero('id_practica'));
        $id = (int)($segmentosRuta[0] ?? 0);
        if ($id < 1) Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'Postulación no encontrada.'), 404);
        if ($this->metodo === 'GET' && ($segmentosRuta[1] ?? '') === 'historial') {
            $cuentaActual = Sesion::actual();
            $cierre->procesarAutomaticos();
            $p = $this->postulaciones->porId($id);
            $this->autorizarLecturaPostulacion($cuentaActual, $p);
            Respuesta::exito($this->postulaciones->historial($id));
        }
        if ($this->metodo === 'GET' && count($segmentosRuta) === 1) {
            $cuentaActual = Sesion::actual();
            $cierre->procesarAutomaticos();
            $p = $this->postulaciones->porId($id);
            $this->autorizarLecturaPostulacion($cuentaActual, $p);
            Respuesta::exito($p);
        }
        if ($this->metodo === 'PUT' && count($segmentosRuta) === 1) $this->cambiarEstadoPostulacion($id, $cierre);
        if ($this->metodo === 'DELETE' && count($segmentosRuta) === 1) $this->retirarPostulacion($id);
        Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    /** El estudiante se postula a una práctica (valida cupo, fecha y CV). */
    public function crearPostulacion(int $practicaId): never {
        $cuentaActual = Acceso::requerirRol(['estudiante']);
        Acceso::requerirPermiso('postulaciones.crear');
        (new ServicioCierre())->procesarAutomaticos();
        $perfil = $this->estudiantes->estudiantePorUsuario((int)$cuentaActual['id_usuario']);
        if (!$perfil || empty($perfil['cv_ruta']) || empty($perfil['cv_nombre']) || ($perfil['cv_mime'] ?? '') !== 'application/pdf' || !empty($perfil['cv_eliminado']) || !ArchivosCv::resolverRuta((string)$perfil['cv_ruta']) || !$this->correoValido((string)($perfil['correo'] ?? '')) || !preg_match('/^[0-9]{8}$/', (string)($perfil['telefono'] ?? ''))) throw new DomainException((new ConfiguracionRepo())->mensaje('error_postulacion_requisitos', 'Completa y valida correo, teléfono y CV antes de postularte.'));
        if ($practicaId < 1) throw new DomainException('Selecciona una práctica válida.');
        $comentario = $this->texto('carta_presentacion', 3000, false);
        $estudiante = (int)$perfil['id_estudiante'];
        $id = $this->usuarios->transaccion(function()use($practicaId, $cuentaActual, $estudiante, $comentario, $perfil) {
            Conexion::contextoAuditoria((int)$cuentaActual['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
            $pr = $this->practicas->porIdParaActualizar($practicaId);
            if (!$pr || $pr['estado_codigo'] !== 'publicada' || $pr['fecha_limite_postulacion'] < date('Y-m-d')) throw new DomainException((new ConfiguracionRepo())->mensaje('error_practica_no_vigente', 'La práctica ya no recibe postulaciones.'));
            if ($this->postulaciones->aceptadas($practicaId) >= (int)$pr['cupo_maximo']) throw new DomainException((new ConfiguracionRepo())->mensaje('error_cupo_lleno', 'La práctica ya alcanzó su cupo.'));
            $existente = $this->postulaciones->existente($estudiante, $practicaId);
            $pendiente = $this->catalogos->estadoId('postulacion', 'pendiente');
            $estudianteRol = $this->catalogos->rolId('estudiante');
            if ($existente) {
                if ($existente['estado_codigo'] !== 'retirada') throw new DomainException((new ConfiguracionRepo())->mensaje('error_postulacion_duplicada', 'Ya existe una postulación activa para esta práctica.'));
                $anterior = $this->catalogos->estadoId('postulacion', 'retirada');
                $req = $this->catalogos->puedeTransicionar('postulacion', $anterior, $pendiente, $estudianteRol);
                if ($req === null) throw new DomainException('La reactivación no está permitida.');
                $this->postulaciones->reactivar((int)$existente['id_postulacion'], $pendiente, (int)$cuentaActual['id_usuario'], ['cv_ruta' => $perfil['cv_ruta'], 'cv_nombre' => $perfil['cv_nombre'], 'cv_mime' => $perfil['cv_mime']]);
                $this->postulaciones->registrarHistorial('postulacion', (int)$existente['id_postulacion'], $anterior, $pendiente, (int)$cuentaActual['id_usuario'], null);
                return (int)$existente['id_postulacion'];
            }
            $id = $this->postulaciones->crear([
                'estudiante' => $estudiante,
                'practica' => $practicaId,
                'estado' => $pendiente,
                'carta' => $comentario,
                'cv_ruta' => $perfil['cv_ruta'],
                'cv_nombre' => $perfil['cv_nombre'],
                'cv_mime' => $perfil['cv_mime'],
                'actor' => (int)$cuentaActual['id_usuario']
            ]);
            $this->postulaciones->registrarHistorial('postulacion', $id, null, $pendiente, (int)$cuentaActual['id_usuario'], null);
            return $id;
        });
        Respuesta::exito(['id_postulacion' => $id], (new ConfiguracionRepo())->mensaje('exito_postulacion', 'Postulación registrada.'), 201);
    }
    /** La empresa acepta o rechaza una postulación. */
    private function cambiarEstadoPostulacion(int $id, ServicioCierre $cierre): never {
        $cuentaActual = Acceso::requerirRol(['empresa', 'admin', 'superadmin']);
        Acceso::requerirPermiso('postulaciones.cambiar_estado');
        $esEmpresa = $cuentaActual['rol_codigo'] === 'empresa';
        if ($esEmpresa) Acceso::requiereEmpresaAprobada((int)$cuentaActual['id_usuario']);
        $cierre->procesarAutomaticos();
        $target = $this->texto('estado_codigo', 40, true);
        $comentario = $this->texto('comentario', 3000, false);
        $destino = $this->catalogos->estadoId('postulacion', $target);
        $roleId = $this->catalogos->rolId((string)$cuentaActual['rol_codigo']);
        $this->usuarios->transaccion(function()use($id, $cuentaActual, $destino, $comentario, $roleId, $cierre, $esEmpresa, $target) {
            Conexion::contextoAuditoria((int)$cuentaActual['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
            $initial = $this->postulaciones->porId($id);
            if (!$initial) throw new DomainException('La postulación no existe.');
            $practice = $this->practicas->porIdParaActualizar((int)$initial['id_practica'], $esEmpresa ? (int)$cuentaActual['id_usuario'] : null);
            if (!$practice || ($esEmpresa && $practice['estado_codigo'] !== 'publicada')) {
                throw new DomainException($esEmpresa ? 'La postulación no pertenece a una práctica abierta de tu empresa.' : 'La práctica de esta postulación no está disponible.');
            }
            $row = $this->postulaciones->porIdBloqueo($id);
            if (!$row) throw new DomainException('La postulación no existe.');
            $requires = $this->catalogos->puedeTransicionar('postulacion', (int)$row['id_estado'], $destino, $roleId);
            if ($requires === null) throw new DomainException('La transición de postulación no está permitida.');
            $this->validarComentario($comentario, $requires);
            if ($target === 'aceptada' && $this->postulaciones->aceptadas((int)$row['id_practica']) >= (int)$practice['cupo_maximo']) {
                throw new DomainException((new ConfiguracionRepo())->mensaje('error_cupo_lleno', 'La práctica ya alcanzó su cupo.'));
            }
            $this->postulaciones->cambiarEstado($id, $destino, (int)$cuentaActual['id_usuario'], $comentario);
            $this->postulaciones->registrarHistorial('postulacion', $id, (int)$row['id_estado'], $destino, (int)$cuentaActual['id_usuario'], $comentario);
            if ($target === 'aceptada' && $this->postulaciones->aceptadas((int)$row['id_practica']) >= (int)$practice['cupo_maximo']) {
                $cierre->cerrarEnTransaccion((int)$row['id_practica'], $cuentaActual, 'sistema', true);
            }
        });
        Respuesta::exito(['id_postulacion' => $id], (new ConfiguracionRepo())->mensaje('exito_guardado', 'Estado de postulación actualizado.'));
    }
    /** El estudiante retira su postulación y puede liberar cupo. */
    private function retirarPostulacion(int $id): never {
        $cuentaActual = Acceso::requerirRol(['estudiante']);
        Acceso::requerirPermiso('postulaciones.retirar_propia');
        (new ServicioCierre())->procesarAutomaticos();
        $this->usuarios->transaccion(function()use($id, $cuentaActual) {
            Conexion::contextoAuditoria((int)$cuentaActual['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
            $pre = $this->postulaciones->porId($id);
            if (!$pre || (int)$pre['id_usuario_estudiante'] !== (int)$cuentaActual['id_usuario']) throw new DomainException('La postulación no existe o no te pertenece.');
            $practice = $this->practicas->porIdParaActualizar((int)$pre['id_practica']);
            $row = $this->postulaciones->porIdBloqueo($id);
            $destino = $this->catalogos->estadoId('postulacion', 'retirada');
            $role = $this->catalogos->rolId('estudiante');
            $requires = $this->catalogos->puedeTransicionar('postulacion', (int)$row['id_estado'], $destino, $role);
            if ($requires === null) throw new DomainException('La postulación no se puede retirar en su estado actual.');
            $this->postulaciones->cambiarEstado($id, $destino, (int)$cuentaActual['id_usuario'], null);
            $this->postulaciones->registrarHistorial('postulacion', $id, (int)$row['id_estado'], $destino, (int)$cuentaActual['id_usuario'], null);
            $cierrePorCupo = (new ConfiguracionRepo())->obtener('practica_cierre_cupo_motivo', 'Cierre automático por cupo completo.');
            if ($practice && $practice['estado_codigo'] === 'cerrada' && $practice['observaciones_coordinacion'] === $cierrePorCupo && $practice['fecha_limite_postulacion'] >= date('Y-m-d')) {
                $published = $this->catalogos->estadoId('practica', 'publicada');
                $system = $this->catalogos->rolId('sistema');
                $can = $this->catalogos->puedeTransicionar('practica', (int)$practice['id_estado'], $published, $system);
                if ($can !== null && (new PostulacionRepo())->aceptadas((int)$practice['id_practica']) < (int)$practice['cupo_maximo']) {
                    $this->practicas->cambiarEstado((int)$practice['id_practica'], $published, null, null);
                    $this->practicas->registrarHistorial((int)$practice['id_practica'], (int)$practice['id_estado'], $published, (int)$cuentaActual['id_usuario'], 'Cupo liberado por retiro de una postulación aceptada.');
                }
            }
        });
        Respuesta::exito(['id_postulacion' => $id], (new ConfiguracionRepo())->mensaje('exito_retiro', 'Postulación retirada.'));
    }
    /** Verifica que el usuario pueda leer la postulación. */
    private function autorizarLecturaPostulacion(array $cuentaActual, ?array $p): void {
        if (!$p) Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'Postulación no encontrada.'), 404);
        match ($cuentaActual['rol_codigo']) {
            'estudiante' => Acceso::requerirPermiso('postulaciones.consultar_propias'),
            'empresa' => Acceso::requerirPermiso('postulaciones.consultar_empresa'),
            'admin', 'superadmin' => Acceso::requerirPermiso('postulaciones.consultar'),
            default => Respuesta::error((new ConfiguracionRepo())->mensaje('error_sin_permiso', 'No tienes permiso para consultar esta postulación.'), 403),
        };
        if ($cuentaActual['rol_codigo'] === 'empresa') Acceso::requiereEmpresaAprobada((int)$cuentaActual['id_usuario']);
        $ok = match($cuentaActual['rol_codigo']) {
            'estudiante' => (int)$p['id_usuario_estudiante'] === (int)$cuentaActual['id_usuario'], 'empresa' => (int)$p['id_usuario_empresa'] === (int)$cuentaActual['id_usuario'], 'admin', 'superadmin' => true, default => false
        };
        if (!$ok) Respuesta::error((new ConfiguracionRepo())->mensaje('error_sin_permiso', 'No tienes permiso para consultar esta postulación.'), 403);
    }
}
