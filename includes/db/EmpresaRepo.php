<?php
declare(strict_types = 1);
/** Gestiona perfiles de empresa y datos académicos de estudiantes. */
final class EmpresaRepo {
    public function carnetExiste(string $carnet): bool {
        $q = Conexion::obtener()->prepare('SELECT 1 FROM estudiantes WHERE eliminado=0 AND LOWER(carnet)=LOWER(:carnet) LIMIT 1');
        $q->execute(['carnet' => $carnet]);
        return (bool)$q->fetchColumn();
    }
    public function identificacionFiscalExiste(string $identificacion): bool {
        $q = Conexion::obtener()->prepare('SELECT 1 FROM empresas WHERE eliminado=0 AND UPPER(identificacion_fiscal)=UPPER(:identificacion) LIMIT 1');
        $q->execute(['identificacion' => $identificacion]);
        return (bool)$q->fetchColumn();
    }
    public function perfil(int $id): ?array {
        $q = Conexion::obtener()->prepare('SELECT * FROM vw_empresas WHERE id_usuario=:id LIMIT 1');
        $q->execute(['id' => $id]);
        return $q->fetch() ?: null;
    }
    public function crearEmpresa(array $d): int {
        $pdo = Conexion::obtener();
        $q = $pdo->prepare('INSERT INTO empresas(id_usuario,id_estado_revision,nombre_legal,nombre_comercial,identificacion_fiscal,sector,descripcion,correo_contacto,telefono,sitio_web,direccion,creado_por) VALUES(:usuario,:estado,:legal,:comercial,:fiscal,:sector,:descripcion,:correo,:telefono,:web,:direccion,:actor)');
        $q->execute($d);
        return (int)$pdo->lastInsertId();
    }
    public function crearEstudiante(array $d): int {
        $pdo = Conexion::obtener();
        $q = $pdo->prepare('INSERT INTO estudiantes(id_usuario,id_carrera,carnet,semestre,universidad,cv_nombre,cv_ruta,cv_mime,creado_por) VALUES(:usuario,:carrera,:carnet,:semestre,:universidad,:cv_nombre,:cv_ruta,:cv_mime,:actor)');
        $q->execute($d);
        return (int)$pdo->lastInsertId();
    }
    public function idEmpresa(int $idUsuario): int {
        $q = Conexion::obtener()->prepare('SELECT id_empresa FROM empresas WHERE id_usuario=:id AND eliminado=0 LIMIT 1');
        $q->execute(['id' => $idUsuario]);
        return (int)$q->fetchColumn();
    }
    public function porId(int $id): ?array {
        $q = Conexion::obtener()->prepare('SELECT * FROM vw_empresas WHERE id_empresa=:id LIMIT 1');
        $q->execute(['id' => $id]);
        return $q->fetch() ?: null;
    }
    public function revisar(int $id, int $estado, int $actor, ?string $comentario): void {
        $q = Conexion::obtener()->prepare('UPDATE empresas SET id_estado_revision=:estado,observaciones_coordinacion=:comentario,revisada_en=NOW(),id_administrador_revision=:revisor,actualizado_por=:actor WHERE id_empresa=:id AND eliminado=0');
        $q->execute(['estado' => $estado, 'comentario' => $comentario, 'revisor' => $actor, 'actor' => $actor, 'id' => $id]);
    }
    public function registrarHistorial(int $id, ?int $origen, int $destino, int $actor, ?string $comentario): void {
        $q = Conexion::obtener()->prepare("INSERT INTO historial_estados(entidad,id_registro,id_estado_anterior,id_estado_nuevo,id_usuario,comentario,creado_por) VALUES('empresa',:id,:origen,:destino,:actor,:comentario,:creado_por)");
        $q->execute(['id' => $id, 'origen' => $origen, 'destino' => $destino, 'actor' => $actor, 'creado_por' => $actor, 'comentario' => $comentario]);
    }
    public function aprobada(int $id): bool {
        $q = Conexion::obtener()->prepare("SELECT 1 FROM vw_empresas WHERE id_usuario=:id AND estado_codigo='aprobada' LIMIT 1");
        $q->execute(['id' => $id]);
        return (bool)$q->fetchColumn();
    }
    public function guardarPerfil(int $id, array $d): void {
        $q = Conexion::obtener()->prepare('UPDATE empresas SET nombre_legal=:legal,nombre_comercial=:comercial,identificacion_fiscal=:fiscal,sector=:sector,descripcion=:descripcion,correo_contacto=:correo,telefono=:telefono,sitio_web=:web,direccion=:direccion,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $q->execute($d + ['id' => $id]);
    }
    public function estudiantePorUsuario(int $id): ?array {
        $q = Conexion::obtener()->prepare('SELECT v.*,e.cv_ruta,e.cv_mime FROM vw_estudiantes v JOIN estudiantes e ON e.id_estudiante=v.id_estudiante WHERE v.id_usuario=:id LIMIT 1');
        $q->execute(['id' => $id]);
        return $q->fetch() ?: null;
    }
    public function cvPerfilEstudiante(int $idUsuario): ?array {
        $q = Conexion::obtener()->prepare('SELECT id_estudiante,cv_nombre,cv_ruta,cv_mime,cv_eliminado FROM estudiantes WHERE id_usuario=:id AND eliminado=0 LIMIT 1');
        $q->execute(['id' => $idUsuario]);
        return $q->fetch() ?: null;
    }
    public function guardarPerfilEstudiante(int $id, array $d): void {
        $q = Conexion::obtener()->prepare('UPDATE estudiantes SET id_carrera=:carrera,carnet=:carnet,semestre=:semestre,universidad=:universidad,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $q->execute($d + ['id' => $id]);
    }
    public function guardarCv(int $id, string $nombre, string $ruta, string $mime, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE estudiantes SET cv_nombre=:nombre,cv_ruta=:ruta,cv_mime=:mime,cv_eliminado=0,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $q->execute(['nombre' => $nombre, 'ruta' => $ruta, 'mime' => $mime, 'actor' => $actor, 'id' => $id]);
    }
    public function marcarCvEliminado(int $id, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE estudiantes SET cv_eliminado=1,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $q->execute(['actor' => $actor, 'id' => $id]);
    }
    public function actualizarEstadoCuenta(int $id, int $estado, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE usuarios SET id_estado=:estado,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $q->execute(['estado' => $estado, 'actor' => $actor, 'id' => $id]);
    }
    public function eliminarPerfil(int $id, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE empresas SET eliminado=1,eliminado_en=NOW(),eliminado_por=:eliminado_por,actualizado_por=:actualizado_por WHERE id_usuario=:id AND eliminado=0');
        $q->execute(['eliminado_por' => $actor, 'actualizado_por' => $actor, 'id' => $id]);
    }
    public function eliminarEstudiante(int $id, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE estudiantes SET eliminado=1,eliminado_en=NOW(),eliminado_por=:eliminado_por,actualizado_por=:actualizado_por WHERE id_usuario=:id AND eliminado=0');
        $q->execute(['eliminado_por' => $actor, 'actualizado_por' => $actor, 'id' => $id]);
    }
    public function restaurarEmpresa(int $id, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE empresas SET eliminado=0,eliminado_en=NULL,eliminado_por=NULL,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=1');
        $q->execute(['actor' => $actor, 'id' => $id]);
    }
    public function restaurarEstudiante(int $id, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE estudiantes SET eliminado=0,eliminado_en=NULL,eliminado_por=NULL,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=1');
        $q->execute(['actor' => $actor, 'id' => $id]);
    }
}
