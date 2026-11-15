<?php
declare(strict_types=1);
/** Consultas del perfil académico y el CV del estudiante. */
final class EstudianteRepo {
    public function carnetExiste(string $carnet): bool {
        $consultaSql = Conexion::obtener()->prepare('SELECT 1 FROM estudiantes WHERE eliminado=0 AND LOWER(carnet)=LOWER(:carnet) LIMIT 1');
        $consultaSql->execute(['carnet' => $carnet]);
        return (bool)$consultaSql->fetchColumn();
    }
    public function crearEstudiante(array $datosEntrada): int {
        $pdo = Conexion::obtener();
        $consultaSql = $pdo->prepare('INSERT INTO estudiantes(id_usuario,id_carrera,carnet,semestre,universidad,cv_nombre,cv_ruta,cv_mime,creado_por) VALUES(:usuario,:carrera,:carnet,:semestre,:universidad,:cv_nombre,:cv_ruta,:cv_mime,:actor)');
        $consultaSql->execute($datosEntrada);
        return (int)$pdo->lastInsertId();
    }
    public function estudiantePorUsuario(int $id): ?array {
        $consultaSql = Conexion::obtener()->prepare('SELECT v.*,e.cv_ruta,e.cv_mime FROM vw_estudiantes v JOIN estudiantes e ON e.id_estudiante=v.id_estudiante WHERE v.id_usuario=:id LIMIT 1');
        $consultaSql->execute(['id' => $id]);
        return $consultaSql->fetch() ?: null;
    }
    public function cvPerfilEstudiante(int $idUsuario): ?array {
        $consultaSql = Conexion::obtener()->prepare('SELECT id_estudiante,cv_nombre,cv_ruta,cv_mime,cv_eliminado FROM estudiantes WHERE id_usuario=:id AND eliminado=0 LIMIT 1');
        $consultaSql->execute(['id' => $idUsuario]);
        return $consultaSql->fetch() ?: null;
    }
    public function guardarPerfilEstudiante(int $id, array $datosEntrada): void {
        $consultaSql = Conexion::obtener()->prepare('UPDATE estudiantes SET id_carrera=:carrera,carnet=:carnet,semestre=:semestre,universidad=:universidad,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $consultaSql->execute($datosEntrada + ['id' => $id]);
    }
    public function guardarCv(int $id, string $nombre, string $ruta, string $mime, int $actor): void {
        $consultaSql = Conexion::obtener()->prepare('UPDATE estudiantes SET cv_nombre=:nombre,cv_ruta=:ruta,cv_mime=:mime,cv_eliminado=0,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $consultaSql->execute(['nombre' => $nombre, 'ruta' => $ruta, 'mime' => $mime, 'actor' => $actor, 'id' => $id]);
    }
    public function marcarCvEliminado(int $id, int $actor): void {
        $consultaSql = Conexion::obtener()->prepare('UPDATE estudiantes SET cv_eliminado=1,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $consultaSql->execute(['actor' => $actor, 'id' => $id]);
    }
    public function eliminarEstudiante(int $id, int $actor): void {
        $consultaSql = Conexion::obtener()->prepare('UPDATE estudiantes SET eliminado=1,eliminado_en=NOW(),eliminado_por=:eliminado_por,actualizado_por=:actualizado_por WHERE id_usuario=:id AND eliminado=0');
        $consultaSql->execute(['eliminado_por' => $actor, 'actualizado_por' => $actor, 'id' => $id]);
    }
    public function restaurarEstudiante(int $id, int $actor): void {
        $consultaSql = Conexion::obtener()->prepare('UPDATE estudiantes SET eliminado=0,eliminado_en=NULL,eliminado_por=NULL,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=1');
        $consultaSql->execute(['actor' => $actor, 'id' => $id]);
    }
}
