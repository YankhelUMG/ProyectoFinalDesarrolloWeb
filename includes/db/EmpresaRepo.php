<?php
declare(strict_types = 1);
/** Gestiona el perfil, aprobación e historial de las empresas. */
final class EmpresaRepo {
    public function identificacionFiscalExiste(string $identificacion): bool {
        $consultaSql = Conexion::obtener()->prepare('SELECT 1 FROM empresas WHERE eliminado=0 AND UPPER(identificacion_fiscal)=UPPER(:identificacion) LIMIT 1');
        $consultaSql->execute(['identificacion' => $identificacion]);
        return (bool)$consultaSql->fetchColumn();
    }
    public function perfil(int $id): ?array {
        $consultaSql = Conexion::obtener()->prepare('SELECT * FROM vw_empresas WHERE id_usuario=:id LIMIT 1');
        $consultaSql->execute(['id' => $id]);
        return $consultaSql->fetch() ?: null;
    }
    public function crearEmpresa(array $datosEntrada): int {
        $pdo = Conexion::obtener();
        $consultaSql = $pdo->prepare('INSERT INTO empresas(id_usuario,id_estado_revision,nombre_legal,nombre_comercial,identificacion_fiscal,sector,descripcion,correo_contacto,telefono,sitio_web,direccion,creado_por) VALUES(:usuario,:estado,:legal,:comercial,:fiscal,:sector,:descripcion,:correo,:telefono,:web,:direccion,:actor)');
        $consultaSql->execute($datosEntrada);
        return (int)$pdo->lastInsertId();
    }
    public function idEmpresa(int $idUsuario): int {
        $consultaSql = Conexion::obtener()->prepare('SELECT id_empresa FROM empresas WHERE id_usuario=:id AND eliminado=0 LIMIT 1');
        $consultaSql->execute(['id' => $idUsuario]);
        return (int)$consultaSql->fetchColumn();
    }
    public function porId(int $id): ?array {
        $consultaSql = Conexion::obtener()->prepare('SELECT * FROM vw_empresas WHERE id_empresa=:id LIMIT 1');
        $consultaSql->execute(['id' => $id]);
        return $consultaSql->fetch() ?: null;
    }
    public function revisar(int $id, int $estado, int $actor, ?string $comentario): void {
        $consultaSql = Conexion::obtener()->prepare('UPDATE empresas SET id_estado_revision=:estado,observaciones_coordinacion=:comentario,revisada_en=NOW(),id_administrador_revision=:revisor,actualizado_por=:actor WHERE id_empresa=:id AND eliminado=0');
        $consultaSql->execute(['estado' => $estado, 'comentario' => $comentario, 'revisor' => $actor, 'actor' => $actor, 'id' => $id]);
    }
    public function registrarHistorial(int $id, ?int $origen, int $destino, int $actor, ?string $comentario): void {
        $consultaSql = Conexion::obtener()->prepare("INSERT INTO historial_estados(entidad,id_registro,id_estado_anterior,id_estado_nuevo,id_usuario,comentario,creado_por) VALUES('empresa',:id,:origen,:destino,:actor,:comentario,:creado_por)");
        $consultaSql->execute(['id' => $id, 'origen' => $origen, 'destino' => $destino, 'actor' => $actor, 'creado_por' => $actor, 'comentario' => $comentario]);
    }
    public function aprobada(int $id): bool {
        $consultaSql = Conexion::obtener()->prepare("SELECT 1 FROM vw_empresas WHERE id_usuario=:id AND estado_codigo='aprobada' LIMIT 1");
        $consultaSql->execute(['id' => $id]);
        return (bool)$consultaSql->fetchColumn();
    }
    public function guardarPerfil(int $id, array $datosEntrada): void {
        $consultaSql = Conexion::obtener()->prepare('UPDATE empresas SET nombre_legal=:legal,nombre_comercial=:comercial,identificacion_fiscal=:fiscal,sector=:sector,descripcion=:descripcion,correo_contacto=:correo,telefono=:telefono,sitio_web=:web,direccion=:direccion,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $consultaSql->execute($datosEntrada + ['id' => $id]);
    }
    public function eliminarPerfil(int $id, int $actor): void {
        $consultaSql = Conexion::obtener()->prepare('UPDATE empresas SET eliminado=1,eliminado_en=NOW(),eliminado_por=:eliminado_por,actualizado_por=:actualizado_por WHERE id_usuario=:id AND eliminado=0');
        $consultaSql->execute(['eliminado_por' => $actor, 'actualizado_por' => $actor, 'id' => $id]);
    }
    public function restaurarEmpresa(int $id, int $actor): void {
        $consultaSql = Conexion::obtener()->prepare('UPDATE empresas SET eliminado=0,eliminado_en=NULL,eliminado_por=NULL,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=1');
        $consultaSql->execute(['actor' => $actor, 'id' => $id]);
    }
}
