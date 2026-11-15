<?php
declare(strict_types = 1);
/** Cierra prácticas vencidas o completas y resuelve sus postulaciones pendientes. */
final class ServicioCierre {
    public function procesarAutomaticos(): int {
        $ids = array_map(static fn($r) => (int)$r['id_practica'], (new PracticaRepo())->candidatasCierre());
        $total = 0;
        foreach ($ids as $id) {
            try {
                if ($this->cerrar($id, null, 'sistema', true)) $total++;
            } catch (Throwable $e) {
                error_log('Cierre automático de práctica ' . $id . ': ' . $e->getMessage());
                try {
                    (new BitacoraRepo())->registrar('ERROR_CIERRE_AUTOMATICO', 'practicas', (string)$id, null, ['mensaje' => 'No se pudo completar el cierre automático.'], null);
                } catch (Throwable $errorBitacora) {
                    error_log('No fue posible registrar en bitácora el error de cierre automático de práctica ' . $id . '.');
                }
            }
        }
        return $total;
    }
    public function cerrar(int $id, ?array $actor, string $rolCodigo, bool $automatico = false): bool {
        return (new UsuarioRepo())->transaccion(fn() => $this->cerrarEnTransaccion($id, $actor, $rolCodigo, $automatico));
    }
    public function cerrarEnTransaccion(int $id, ?array $actor, string $rolCodigo, bool $automatico = false): bool {
        $actorId = $actor ? (int)$actor['id_usuario']: null;
        $rol = new CatalogoRepo();
        $systemRole = $rol->rolId($automatico ? 'sistema': $rolCodigo);
        if (!$systemRole) throw new DomainException('No está configurado el rol que ejecuta el cierre.');
        Conexion::contextoAuditoria($actorId, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
        $repo = new PracticaRepo();
        $fila = $repo->porIdParaActualizar($id, $actor && $actor['rol_codigo'] === 'empresa' ? (int)$actor['id_usuario']: null);
        if (!$fila || $fila['estado_codigo'] !== 'publicada') {
            if ($automatico) return false;
            throw new DomainException('La práctica no existe o ya está cerrada.');
        }
        if ($actor && $actor['rol_codigo'] === 'empresa' && (int)$fila['id_usuario_empresa'] !== (int)$actor['id_usuario']) throw new DomainException('La práctica no pertenece a tu empresa.');
        $ocupadas = (new PostulacionRepo())->aceptadas($id);
        $porFecha = $fila['fecha_limite_postulacion'] < date('Y-m-d');
        $porCupo = $ocupadas >= (int)$fila['cupo_maximo'];
        if ($automatico && !$porFecha && !$porCupo) return false;
        $estadoCerrada = $rol->estadoId('practica', 'cerrada');
        $desde = (int)$fila['id_estado'];
        $puede = $rol->puedeTransicionar('practica', $desde, $estadoCerrada, $systemRole);
        if ($puede === null) throw new DomainException('La transición de cierre automático no está configurada.');
        $comentarioSistema = (new ConfiguracionRepo())->obtener('practica_cierre_comentario', 'La práctica fue cerrada');
        $motivoCierre = null;
        if ($automatico) {
            $claveMotivo = $porCupo ? 'practica_cierre_cupo_motivo' : 'practica_cierre_fecha_motivo';
            $predeterminado = $porCupo ? 'Cierre automático por cupo completo.' : 'Cierre automático por fecha límite.';
            $motivoCierre = (new ConfiguracionRepo())->obtener($claveMotivo, $predeterminado);
        }
        $repo->cambiarEstado($id, $estadoCerrada, $actorId, $motivoCierre);
        $repo->registrarHistorial($id, $desde, $estadoCerrada, $actorId, $motivoCierre);
        $post = new PostulacionRepo();
        $pending = $post->pendientesPractica($id);
        $estadoPendiente = $rol->estadoId('postulacion', 'pendiente');
        $estadoRechazada = $rol->estadoId('postulacion', 'rechazada');
        $system = $rol->rolId('sistema');
        foreach ($pending as $p) {
            $requires = $rol->puedeTransicionar('postulacion', (int)$p['id_estado'], $estadoRechazada, $system);
            if ($requires === null) throw new DomainException('La transición de rechazo automático no está configurada.');
            $post->cambiarEstado((int)$p['id_postulacion'], $estadoRechazada, $actorId, $comentarioSistema);
            $post->registrarHistorial('postulacion', (int)$p['id_postulacion'], (int)$p['id_estado'], $estadoRechazada, $actorId, $comentarioSistema);
        }
        (new BitacoraRepo())->registrar($automatico ? 'CIERRE_AUTOMATICO': 'CIERRE_PRACTICA', 'practicas', (string)$id, null, ['estado' => 'cerrada', 'postulaciones_rechazadas' => count($pending), 'por_fecha' => $porFecha, 'por_cupo' => $porCupo], $actorId);
        return true;
    }
}
