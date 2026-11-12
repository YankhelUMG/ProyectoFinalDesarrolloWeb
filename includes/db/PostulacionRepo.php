<?php
declare(strict_types = 1);
/** Ejecuta consultas y cambios relacionados con postulaciones e historial. */
final class PostulacionRepo {
    /** Postulaciones de un estudiante. */
    public function propias(int $id): array {
        $consultaSql = Conexion::obtener()->prepare('SELECT v.*,p.fecha_limite_postulacion FROM vw_postulaciones_estudiante v JOIN practicas p ON p.id_practica=v.id_practica WHERE v.id_usuario=:id ORDER BY v.fecha_postulacion DESC');
        $consultaSql->execute(['id' => $id]);
        return $consultaSql->fetchAll();
    }
    /** Postulaciones recibidas por una empresa. */
    public function empresa(int $id, ?int $practica = null): array {
        $sql = 'SELECT * FROM vw_postulaciones_empresa WHERE id_usuario_empresa=:id' . ($practica !== null ? ' AND id_practica=:practica': '') . ' ORDER BY fecha_postulacion DESC';
        $consultaSql = Conexion::obtener()->prepare($sql);
        $p = ['id' => $id];
        if ($practica !== null) $p['practica'] = $practica;
        $consultaSql->execute($p);
        return $consultaSql->fetchAll();
    }
    /** Listado administrativo paginado. */
    public function admin(int $limite, int $offset, ?string $estado = null): array {
        $consultaSql = Conexion::obtener()->prepare('SELECT * FROM vw_postulaciones_admin WHERE (:estado_filtro IS NULL OR estado_codigo=:estado) ORDER BY fecha_postulacion DESC LIMIT :limite OFFSET :offset');
        $consultaSql->bindValue(':estado_filtro', $estado);
        $consultaSql->bindValue(':estado', $estado);
        $consultaSql->bindValue(':limite', $limite, PDO::PARAM_INT);
        $consultaSql->bindValue(':offset', $offset, PDO::PARAM_INT);
        $consultaSql->execute();
        return $consultaSql->fetchAll();
    }
    /** Busca una postulación por id. */
    public function porId(int $id): ?array {
        $consultaSql = Conexion::obtener()->prepare('SELECT * FROM vw_postulaciones_admin WHERE id_postulacion=:id LIMIT 1');
        $consultaSql->execute(['id' => $id]);
        return $consultaSql->fetch() ?: null;
    }
    /** Igual, pero bloquea la fila dentro de una transacción. */
    public function porIdBloqueo(int $id): ?array {
        $consultaSql = Conexion::obtener()->prepare("SELECT po.*,es.codigo AS estado_codigo,p.id_empresa,p.id_estado AS estado_practica FROM postulaciones po JOIN estados es ON es.id_estado=po.id_estado AND es.tipo_entidad='postulacion' JOIN practicas p ON p.id_practica=po.id_practica WHERE po.id_postulacion=:id AND po.eliminado=0 FOR UPDATE");
        $consultaSql->execute(['id' => $id]);
        return $consultaSql->fetch() ?: null;
    }
    /** Busca una postulación previa del estudiante a la práctica. */
    public function existente(int $estudiante, int $practica): ?array {
        $consultaSql = Conexion::obtener()->prepare('SELECT po.id_postulacion,es.codigo AS estado_codigo FROM postulaciones po JOIN estados es ON es.id_estado=po.id_estado AND es.tipo_entidad=\'postulacion\' WHERE po.id_estudiante=:e AND po.id_practica=:p AND po.eliminado=0');
        $consultaSql->execute(['e' => $estudiante, 'p' => $practica]);
        return $consultaSql->fetch() ?: null;
    }
    /** Inserta una postulación. */
    public function crear(array $datosEntrada): int {
        $pdo = Conexion::obtener();
        $consultaSql = $pdo->prepare('INSERT INTO postulaciones(id_estudiante,id_practica,id_estado,carta_presentacion,cv_ruta_snapshot,cv_nombre_snapshot,cv_mime_snapshot,creado_por) VALUES(:estudiante,:practica,:estado,:carta,:cv_ruta,:cv_nombre,:cv_mime,:actor)');
        $consultaSql->execute($datosEntrada);
        return (int)$pdo->lastInsertId();
    }
    /** Cambia el estado y el comentario. */
    public function cambiarEstado(int $id, int $estado, ?int $actor, ?string $comentario): void {
        $consultaSql = Conexion::obtener()->prepare('UPDATE postulaciones SET id_estado=:estado,comentario=:comentario,actualizado_por=:actor WHERE id_postulacion=:id AND eliminado=0');
        $consultaSql->execute(['estado' => $estado, 'comentario' => $comentario, 'actor' => $actor, 'id' => $id]);
    }
    /** Reactiva una postulación retirada. */
    public function reactivar(int $id, int $estado, int $actor, array $cv): void {
        $consultaSql = Conexion::obtener()->prepare('UPDATE postulaciones SET id_estado=:estado,comentario=NULL,cv_ruta_snapshot=:ruta,cv_nombre_snapshot=:nombre,cv_mime_snapshot=:mime,actualizado_por=:actor WHERE id_postulacion=:id AND eliminado=0');
        $consultaSql->execute(['estado' => $estado, 'actor' => $actor, 'id' => $id, 'ruta' => $cv['cv_ruta'], 'nombre' => $cv['cv_nombre'], 'mime' => $cv['cv_mime']]);
    }
    /** Bloquea la práctica y cuenta sus aceptadas. */
    public function cambiarCupo(int $practica): array {
        $consultaSql = Conexion::obtener()->prepare("SELECT p.cupo_maximo,p.id_estado,(SELECT COUNT(*) FROM postulaciones po JOIN estados e ON e.id_estado=po.id_estado AND e.codigo='aceptada' AND e.tipo_entidad='postulacion' WHERE po.id_practica=p.id_practica AND po.eliminado=0) AS aceptadas FROM practicas p WHERE p.id_practica=:id AND p.eliminado=0 FOR UPDATE");
        $consultaSql->execute(['id' => $practica]);
        return $consultaSql->fetch() ?: [];
    }
    /** Cuenta las postulaciones aceptadas de una práctica. */
    public function aceptadas(int $practica): int {
        $consultaSql = Conexion::obtener()->prepare("SELECT COUNT(*) FROM postulaciones po JOIN estados e ON e.id_estado=po.id_estado AND e.codigo='aceptada' AND e.tipo_entidad='postulacion' WHERE po.id_practica=:id AND po.eliminado=0");
        $consultaSql->execute(['id' => $practica]);
        return (int)$consultaSql->fetchColumn();
    }
    /** Pendientes de una práctica (bloqueadas). */
    public function pendientesPractica(int $practica): array {
        $consultaSql = Conexion::obtener()->prepare("SELECT po.id_postulacion,po.id_estado FROM postulaciones po JOIN estados es ON es.id_estado=po.id_estado AND es.tipo_entidad='postulacion' WHERE po.id_practica=:id AND po.eliminado=0 AND es.codigo='pendiente' FOR UPDATE");
        $consultaSql->execute(['id' => $practica]);
        return $consultaSql->fetchAll();
    }
    /** Postulaciones activas de un estudiante. */
    public function activasEstudiante(int $usuarioId): array {
        $consultaSql = Conexion::obtener()->prepare("SELECT po.id_postulacion,po.id_estado,es.codigo AS estado_codigo,po.id_practica FROM postulaciones po JOIN estudiantes st ON st.id_estudiante=po.id_estudiante JOIN estados es ON es.id_estado=po.id_estado AND es.tipo_entidad='postulacion' WHERE st.id_usuario=:id AND po.eliminado=0 AND es.codigo IN ('pendiente','aceptada')");
        $consultaSql->execute(['id' => $usuarioId]);
        return $consultaSql->fetchAll();
    }
    /** Postulaciones activas de una empresa. */
    public function activasEmpresa(int $usuarioId): array {
        $consultaSql = Conexion::obtener()->prepare("SELECT po.id_postulacion,po.id_estado,es.codigo AS estado_codigo,po.id_practica FROM postulaciones po JOIN practicas p ON p.id_practica=po.id_practica JOIN empresas em ON em.id_empresa=p.id_empresa JOIN estados es ON es.id_estado=po.id_estado AND es.tipo_entidad='postulacion' WHERE em.id_usuario=:id AND po.eliminado=0 AND es.codigo IN ('pendiente','aceptada')");
        $consultaSql->execute(['id' => $usuarioId]);
        return $consultaSql->fetchAll();
    }
    /** Datos para descargar el CV de una postulación. */
    public function cvDescarga(int $id): ?array {
        $consultaSql = Conexion::obtener()->prepare('SELECT eu.id_usuario AS id_usuario_estudiante,em.id_usuario AS id_usuario_empresa,po.cv_ruta_snapshot AS cv_ruta,po.cv_nombre_snapshot AS cv_nombre,po.cv_mime_snapshot AS cv_mime FROM postulaciones po JOIN estudiantes st ON st.id_estudiante=po.id_estudiante AND st.eliminado=0 JOIN usuarios eu ON eu.id_usuario=st.id_usuario AND eu.eliminado=0 JOIN practicas p ON p.id_practica=po.id_practica AND p.eliminado=0 JOIN empresas em ON em.id_empresa=p.id_empresa AND em.eliminado=0 WHERE po.id_postulacion=:id AND po.eliminado=0 LIMIT 1');
        $consultaSql->execute(['id' => $id]);
        return $consultaSql->fetch() ?: null;
    }
    /** Historial de estados de una postulación. */
    public function historial(int $id): array {
        $consultaSql = Conexion::obtener()->prepare('SELECT * FROM vw_historial_postulaciones WHERE id_postulacion=:id ORDER BY fecha_hora');
        $consultaSql->execute(['id' => $id]);
        return $consultaSql->fetchAll();
    }
    /** Totales por estado para el panel. */
    public function reporteEstados(): array {
        return Conexion::obtener()->query('SELECT estado_codigo,estado,color,cantidad FROM vw_dashboard_postulaciones ORDER BY estado_codigo')->fetchAll();
    }
    /** Resumen por estado de un estudiante. */
    public function resumenEstudiante(int $usuario): array {
        $consultaSql = Conexion::obtener()->prepare('SELECT estado_codigo,estado,estado_color AS color,COUNT(*) AS cantidad FROM vw_postulaciones_estudiante WHERE id_usuario=:id GROUP BY estado_codigo,estado,estado_color ORDER BY estado');
        $consultaSql->execute(['id' => $usuario]);
        return $consultaSql->fetchAll();
    }
    /** Resumen por estado de una empresa. */
    public function resumenEmpresa(int $usuario): array {
        $consultaSql = Conexion::obtener()->prepare('SELECT estado_codigo,estado,estado_color AS color,COUNT(*) AS cantidad FROM vw_postulaciones_empresa WHERE id_usuario_empresa=:id GROUP BY estado_codigo,estado,estado_color ORDER BY estado');
        $consultaSql->execute(['id' => $usuario]);
        return $consultaSql->fetchAll();
    }
    /** Guarda un cambio de estado en el historial. */
    public function registrarHistorial(string $entidad, int $id, ?int $origen, int $destino, ?int $actor, ?string $comentario): void {
        $consultaSql = Conexion::obtener()->prepare('INSERT INTO historial_estados(entidad,id_registro,id_estado_anterior,id_estado_nuevo,id_usuario,comentario,creado_por) VALUES(:entidad,:id,:origen,:destino,:actor,:comentario,:creado_por)');
        $consultaSql->execute([
            'entidad' => $entidad,
            'id' => $id,
            'origen' => $origen,
            'destino' => $destino,
            'actor' => $actor,
            'creado_por' => $actor,
            'comentario' => $comentario
        ]);
    }
}
