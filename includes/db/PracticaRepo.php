<?php
declare(strict_types = 1);
/** Ejecuta consultas y cambios relacionados con prácticas profesionales. */
final class PracticaRepo {
    public function catalogo(array $f, int $limite, int $offset): array {
        $where = ['1=1'];
        $p = [];
        foreach (['area' => 'id_area', 'modalidad' => 'id_modalidad', 'empresa' => 'empresa', 'q' => 'titulo'] as $key => $column) if (!empty($f[$key])) {
            if ($key === 'q') {
                $where[] = '(v.titulo LIKE :q1 OR v.descripcion LIKE :q2 OR v.empresa LIKE :q3)';
                $like = '%' . trim((string)$f[$key]) . '%';
                $p += ['q1' => $like, 'q2' => $like, 'q3' => $like];
            } elseif ($key === 'empresa') {
                $where[] = 'v.empresa LIKE :empresa';
                $p['empresa'] = '%' . trim((string)$f[$key]) . '%';
            } else {
                $where[] = "v.$column = :$key";
                $p[$key] = $f[$key];
            }
        }
        $desde = trim((string)($f['fecha_inicio_desde'] ?? ''));
        $hasta = trim((string)($f['fecha_inicio_hasta'] ?? ''));
        foreach (['fecha_inicio_desde' => $desde, 'fecha_inicio_hasta' => $hasta] as $key => $fecha) if ($fecha !== '') {
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if (!$dt || $dt->format('Y-m-d') !== $fecha) throw new DomainException('El filtro de fecha de inicio no es válido.');
            $where[] = $key === 'fecha_inicio_desde' ? 'p.fecha_inicio >= :fecha_inicio_desde' : 'p.fecha_inicio <= :fecha_inicio_hasta';
            $p[$key] = $fecha;
        }
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) throw new DomainException('La fecha inicial del filtro no puede ser posterior a la fecha final.');
        // Consultar fecha_inicio desde la tabla base evita depender de que la vista
        // desplegada ya haya sido recreada con esa columna.
        $sql = 'SELECT v.*,p.fecha_inicio AS fecha_inicio FROM vw_practicas_publicadas v JOIN practicas p ON p.id_practica=v.id_practica WHERE ' . implode(' AND ', $where) . ' ORDER BY v.publicada_en DESC LIMIT :limite OFFSET :offset';
        $q = Conexion::obtener()->prepare($sql);
        foreach ($p as $k => $v) $q->bindValue(':' . $k, $v);
        $q->bindValue(':limite', $limite, PDO::PARAM_INT);
        $q->bindValue(':offset', $offset, PDO::PARAM_INT);
        $q->execute();
        return $q->fetchAll();
    }
    public function una(int $id): ?array {
        $q = Conexion::obtener()->prepare('SELECT v.*, p.fecha_inicio FROM vw_practicas_publicadas v JOIN practicas p ON p.id_practica=v.id_practica WHERE v.id_practica=:id LIMIT 1');
        $q->execute(['id' => $id]);
        return $q->fetch() ?: null;
    }
    public function unaEmpresa(int $id, int $usuarioEmpresa): ?array {
        $q = Conexion::obtener()->prepare('SELECT * FROM vw_practicas_empresa WHERE id_practica=:id AND id_usuario_empresa=:usuario LIMIT 1');
        $q->execute(['id' => $id, 'usuario' => $usuarioEmpresa]);
        return $q->fetch() ?: null;
    }
    public function unaAdmin(int $id): ?array {
        $q = Conexion::obtener()->prepare('SELECT * FROM vw_practicas_admin WHERE id_practica=:id LIMIT 1');
        $q->execute(['id' => $id]);
        return $q->fetch() ?: null;
    }
    public function listarEmpresa(int $usuarioId): array {
        $q = Conexion::obtener()->prepare('SELECT * FROM vw_practicas_empresa WHERE id_usuario_empresa=:id ORDER BY actualizado_en DESC');
        $q->execute(['id' => $usuarioId]);
        return $q->fetchAll();
    }
    public function idsEmpresa(int $usuarioId): array {
        $q = Conexion::obtener()->prepare("SELECT p.id_practica,p.id_estado,es.codigo AS estado_codigo FROM practicas p JOIN empresas e ON e.id_empresa=p.id_empresa JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='practica' WHERE e.id_usuario=:id AND p.eliminado=0");
        $q->execute(['id' => $usuarioId]);
        return $q->fetchAll();
    }
    public function listarAdmin(array $f, int $limite, int $offset, ?string $estado = null): array {
        $sql = 'SELECT * FROM vw_practicas_admin WHERE (:estado_filtro IS NULL OR estado_codigo=:estado) ORDER BY creado_en DESC LIMIT :limite OFFSET :offset';
        $q = Conexion::obtener()->prepare($sql);
        $q->bindValue(':estado_filtro', $estado);
        $q->bindValue(':estado', $estado);
        $q->bindValue(':limite', $limite, PDO::PARAM_INT);
        $q->bindValue(':offset', $offset, PDO::PARAM_INT);
        $q->execute();
        return $q->fetchAll();
    }
    public function crear(array $d): int {
        $pdo = Conexion::obtener();
        $q = $pdo->prepare('INSERT INTO practicas(id_empresa,id_estado,id_area,id_modalidad,titulo,descripcion,requisitos,ubicacion,fecha_inicio,cupo_maximo,fecha_limite_postulacion,creado_por) VALUES(:empresa,:estado,:area,:modalidad,:titulo,:descripcion,:requisitos,:ubicacion,:inicio,:cupo,:fecha,:actor)');
        $q->execute($d);
        return (int)$pdo->lastInsertId();
    }
    public function porIdParaActualizar(int $id, ?int $empresaUsuario = null): ?array {
        $sql = 'SELECT p.*,es.codigo AS estado_codigo,e.id_usuario AS id_usuario_empresa FROM practicas p JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad=\'practica\' JOIN empresas e ON e.id_empresa=p.id_empresa WHERE p.id_practica=:id AND p.eliminado=0' . ($empresaUsuario !== null ? ' AND e.id_usuario=:empresa': '') . ' FOR UPDATE';
        $q = Conexion::obtener()->prepare($sql);
        $params = ['id' => $id];
        if ($empresaUsuario !== null) $params['empresa'] = $empresaUsuario;
        $q->execute($params);
        return $q->fetch() ?: null;
    }
    public function guardar(array $d): void {
        $q = Conexion::obtener()->prepare('UPDATE practicas SET id_estado=:estado,id_area=:area,id_modalidad=:modalidad,titulo=:titulo,descripcion=:descripcion,requisitos=:requisitos,ubicacion=:ubicacion,fecha_inicio=:inicio,cupo_maximo=:cupo,fecha_limite_postulacion=:fecha,observaciones_coordinacion=:comentario,actualizado_por=:actor WHERE id_practica=:id AND eliminado=0');
        $q->execute($d);
    }
    public function cambiarEstado(int $id, int $estado, ?int $actor, ?string $comentario = null): void {
        $q = Conexion::obtener()->prepare('UPDATE practicas SET id_estado=:estado,observaciones_coordinacion=:comentario,actualizado_por=:actor WHERE id_practica=:id AND eliminado=0');
        $q->execute(['estado' => $estado, 'comentario' => $comentario, 'actor' => $actor, 'id' => $id]);
    }
    public function eliminar(int $id, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE practicas SET eliminado=1,eliminado_en=NOW(),eliminado_por=:eliminado_por,actualizado_por=:actualizado_por WHERE id_practica=:id AND eliminado=0');
        $q->execute(['eliminado_por' => $actor, 'actualizado_por' => $actor, 'id' => $id]);
    }
    public function pendientes(int $practica): array {
        return (new PostulacionRepo())->pendientesPractica($practica);
    }
    public function candidatasCierre(): array {
        return Conexion::obtener()->query("SELECT p.id_practica,p.cupo_maximo,es.codigo AS estado_codigo,p.fecha_limite_postulacion,(SELECT COUNT(*) FROM postulaciones po JOIN estados ep ON ep.id_estado=po.id_estado AND ep.tipo_entidad='postulacion' AND ep.codigo='aceptada' WHERE po.id_practica=p.id_practica AND po.eliminado=0) AS aceptadas FROM practicas p JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='practica' WHERE p.eliminado=0 AND es.codigo='publicada' AND (p.fecha_limite_postulacion<CURRENT_DATE OR (SELECT COUNT(*) FROM postulaciones po JOIN estados ep ON ep.id_estado=po.id_estado AND ep.tipo_entidad='postulacion' AND ep.codigo='aceptada' WHERE po.id_practica=p.id_practica AND po.eliminado=0)>=p.cupo_maximo)")->fetchAll();
    }
    public function reporteEstados(): array {
        return Conexion::obtener()->query('SELECT estado_codigo,estado,color,cantidad FROM vw_dashboard_practicas ORDER BY estado_codigo')->fetchAll();
    }
    public function resumenEmpresa(int $usuario): array {
        $q = Conexion::obtener()->prepare('SELECT estado_codigo,estado,estado_color AS color,COUNT(*) AS cantidad FROM vw_practicas_empresa WHERE id_usuario_empresa=:id GROUP BY estado_codigo,estado,estado_color ORDER BY estado');
        $q->execute(['id' => $usuario]);
        return $q->fetchAll();
    }
    public function registrarHistorial(int $id, ?int $origen, int $destino, ?int $actor, ?string $comentario): void {
        $q = Conexion::obtener()->prepare("INSERT INTO historial_estados(entidad,id_registro,id_estado_anterior,id_estado_nuevo,id_usuario,comentario,creado_por) VALUES('practica',:id,:origen,:destino,:actor,:comentario,:creado_por)");
        $q->execute(['id' => $id, 'origen' => $origen, 'destino' => $destino, 'actor' => $actor, 'creado_por' => $actor, 'comentario' => $comentario]);
    }
    public function empresasPendientes(): array {
        return Conexion::obtener()->query("SELECT * FROM vw_empresas_revision WHERE estado_codigo='pendiente' ORDER BY id_empresa DESC")->fetchAll();
    }
    public function empresaPorUsuario(int $id): ?array {
        $q = Conexion::obtener()->prepare('SELECT * FROM vw_empresas WHERE id_usuario=:id LIMIT 1');
        $q->execute(['id' => $id]);
        return $q->fetch() ?: null;
    }
    public function guardarEstadoEmpresa(int $id, int $estado, int $actor, ?string $comentario): void {
        $q = Conexion::obtener()->prepare('UPDATE empresas SET id_estado_revision=:estado,observaciones_coordinacion=:comentario,revisada_en=NOW(),id_administrador_revision=:revisor,actualizado_por=:actor WHERE id_empresa=:id AND eliminado=0');
        $q->execute(['estado' => $estado, 'comentario' => $comentario, 'revisor' => $actor, 'actor' => $actor, 'id' => $id]);
    }
}
