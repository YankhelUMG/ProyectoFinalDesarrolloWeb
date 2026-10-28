<?php
declare(strict_types = 1);
/** Gestiona cuentas, autenticación, sesiones e historial de usuarios. */
final class UsuarioRepo {
    public function usuarioExiste(string $usuario): bool {
        $q = Conexion::obtener()->prepare('SELECT 1 FROM usuarios WHERE eliminado=0 AND LOWER(usuario)=LOWER(:usuario) LIMIT 1');
        $q->execute(['usuario' => $usuario]);
        return (bool)$q->fetchColumn();
    }
    public function correoExiste(string $correo): bool {
        $q = Conexion::obtener()->prepare('SELECT 1 FROM usuarios WHERE eliminado=0 AND LOWER(correo)=LOWER(:correo) LIMIT 1');
        $q->execute(['correo' => $correo]);
        return (bool)$q->fetchColumn();
    }
    public function porAcceso(string $identificador): ?array {
        $q = Conexion::obtener()->prepare("SELECT u.id_usuario,u.id_rol,u.id_estado,u.usuario,u.correo,u.nombres,u.apellidos,u.password_hash,u.debe_cambiar_password,r.codigo AS rol_codigo,e.codigo AS estado_codigo,(SELECT ee.codigo FROM empresas em JOIN estados ee ON ee.id_estado=em.id_estado_revision AND ee.tipo_entidad='empresa' AND ee.eliminado=0 WHERE em.id_usuario=u.id_usuario AND em.eliminado=0 LIMIT 1) AS empresa_estado_codigo FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol AND r.eliminado=0 JOIN estados e ON e.id_estado=u.id_estado AND e.tipo_entidad='usuario' AND e.eliminado=0 WHERE u.eliminado=0 AND (LOWER(u.usuario)=LOWER(:usuario) OR LOWER(u.correo)=LOWER(:correo)) LIMIT 1");
        $q->execute(['usuario' => $identificador, 'correo' => $identificador]);
        return $q->fetch() ?: null;
    }
    public function porId(int $id): ?array {
        $q = Conexion::obtener()->prepare('SELECT * FROM vw_usuario_acceso WHERE id_usuario=:id LIMIT 1');
        $q->execute(['id' => $id]);
        return $q->fetch() ?: null;
    }
    public function sesiones(int $id, string $hash): ?array {
        $q = Conexion::obtener()->prepare("SELECT a.id_usuario,a.usuario,a.correo,a.nombres,a.apellidos,a.telefono,a.debe_cambiar_password,a.rol_codigo,a.rol_nombre,a.estado_codigo,a.estado_nombre,(SELECT e.codigo FROM empresas em JOIN estados e ON e.id_estado=em.id_estado_revision AND e.tipo_entidad='empresa' AND e.eliminado=0 WHERE em.id_usuario=a.id_usuario AND em.eliminado=0 LIMIT 1) AS empresa_estado_codigo FROM vw_usuario_acceso a WHERE a.id_usuario=:id AND EXISTS (SELECT 1 FROM sesiones_usuario s WHERE s.id_usuario=:sid AND s.token_hash=:token AND s.revocada_en IS NULL AND s.expira_en>NOW() AND s.eliminado=0) LIMIT 1");
        $q->execute(['id' => $id, 'sid' => $id, 'token' => $hash]);
        return $q->fetch() ?: null;
    }
    public function registrarSesion(int $id, string $hash, string $ip, string $agente, string $expira): void {
        $q = Conexion::obtener()->prepare('INSERT INTO sesiones_usuario(id_usuario,token_hash,ip_origen,user_agent,expira_en,ultima_actividad) VALUES(:id,:token,:ip,:agente,:expira,NOW())');
        $q->execute(['id' => $id, 'token' => $hash, 'ip' => $ip, 'agente' => $agente, 'expira' => $expira]);
    }
    public function revocarSesion(int $id, string $hash): void {
        $q = Conexion::obtener()->prepare('UPDATE sesiones_usuario SET revocada_en=NOW() WHERE id_usuario=:id AND token_hash=:token AND revocada_en IS NULL');
        $q->execute(['id' => $id, 'token' => $hash]);
    }
    public function revocarSesionesUsuario(int $id, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE sesiones_usuario SET revocada_en=NOW(),actualizado_por=:actor WHERE id_usuario=:id AND revocada_en IS NULL AND eliminado=0');
        $q->execute(['id' => $id, 'actor' => $actor]);
    }
    public function tocarSesion(int $id, string $hash, string $expira): void {
        $q = Conexion::obtener()->prepare('UPDATE sesiones_usuario SET ultima_actividad=NOW(),expira_en=:expira WHERE id_usuario=:id AND token_hash=:token AND revocada_en IS NULL AND eliminado=0');
        $q->execute(['expira' => $expira, 'id' => $id, 'token' => $hash]);
    }
    public function actualizarAcceso(int $id): void {
        $q = Conexion::obtener()->prepare('UPDATE usuarios SET ultimo_acceso=NOW() WHERE id_usuario=:id');
        $q->execute(['id' => $id]);
    }
    public function listar(): array {
        return Conexion::obtener()->query('SELECT * FROM vw_usuarios_admin ORDER BY nombres,apellidos')->fetchAll();
    }
    public function detalleAdmin(int $id): ?array {
        $q = Conexion::obtener()->prepare('SELECT * FROM usuarios WHERE id_usuario=:id LIMIT 1');
        $q->execute(['id' => $id]);
        return $q->fetch() ?: null;
    }
    public function listarPagina(int $limite, int $offset, ?string $busqueda = null, bool $excluirSuperadministradores = false): array {
        $sql = 'SELECT * FROM vw_usuarios_admin WHERE (:excluir_super = 0 OR rol_codigo <> \'superadmin\') AND (:q IS NULL OR usuario LIKE :q1 OR correo LIKE :q2 OR CONCAT(nombres,\' \',apellidos) LIKE :q3) ORDER BY nombres,apellidos LIMIT :limite OFFSET :offset';
        $q = Conexion::obtener()->prepare($sql);
        $term = $busqueda === null ? null: '%' . $busqueda . '%';
        $q->bindValue(':excluir_super', (int)$excluirSuperadministradores, PDO::PARAM_INT);
        $q->bindValue(':q', $term);
        $q->bindValue(':q1', $term);
        $q->bindValue(':q2', $term);
        $q->bindValue(':q3', $term);
        $q->bindValue(':limite', $limite, PDO::PARAM_INT);
        $q->bindValue(':offset', $offset, PDO::PARAM_INT);
        $q->execute();
        return $q->fetchAll();
    }
    public function listarEliminados(int $limite, int $offset, bool $excluirSuperadministradores = false): array {
        $q = Conexion::obtener()->prepare('SELECT u.id_usuario,u.usuario,u.correo,u.nombres,u.apellidos,r.codigo AS rol_codigo,e.codigo AS estado_codigo,u.eliminado_en FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol JOIN estados e ON e.id_estado=u.id_estado WHERE u.eliminado=1 AND (:excluir_super=0 OR r.codigo<>\'superadmin\') ORDER BY u.eliminado_en DESC LIMIT :limite OFFSET :offset');
        $q->bindValue(':excluir_super', (int)$excluirSuperadministradores, PDO::PARAM_INT);
        $q->bindValue(':limite', $limite, PDO::PARAM_INT);
        $q->bindValue(':offset', $offset, PDO::PARAM_INT);
        $q->execute();
        return $q->fetchAll();
    }
    public function detalleVisible(int $id): ?array {
        $q = Conexion::obtener()->prepare('SELECT * FROM vw_usuarios_admin WHERE id_usuario=:id LIMIT 1');
        $q->execute(['id' => $id]);
        return $q->fetch() ?: null;
    }
    public function actualizarDatos(int $id, array $d): void {
        $q = Conexion::obtener()->prepare('UPDATE usuarios SET correo=:correo,nombres=:nombres,apellidos=:apellidos,telefono=:telefono,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $q->execute($d + ['id' => $id]);
    }
    public function actualizarTelefono(int $id, ?string $telefono, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE usuarios SET telefono=:telefono,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $q->execute(['telefono' => $telefono, 'actor' => $actor, 'id' => $id]);
    }
    public function asignarRol(int $id, int $rol, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE usuarios SET id_rol=:rol,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $q->execute(['rol' => $rol, 'actor' => $actor, 'id' => $id]);
    }
    public function cambiarEstado(int $id, int $estado, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE usuarios SET id_estado=:estado,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $q->execute(['estado' => $estado, 'actor' => $actor, 'id' => $id]);
    }
    public function registrarHistorial(int $id, ?int $origen, int $destino, int $actor, ?string $comentario): void {
        $q = Conexion::obtener()->prepare("INSERT INTO historial_estados(entidad,id_registro,id_estado_anterior,id_estado_nuevo,id_usuario,comentario,creado_por) VALUES('usuario',:id,:origen,:destino,:actor,:comentario,:creado_por)");
        $q->execute(['id' => $id, 'origen' => $origen, 'destino' => $destino, 'actor' => $actor, 'creado_por' => $actor, 'comentario' => $comentario]);
    }
    public function eliminar(int $id, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE usuarios SET eliminado=1,eliminado_en=NOW(),eliminado_por=:eliminado_por,actualizado_por=:actualizado_por WHERE id_usuario=:id AND eliminado=0');
        $q->execute(['eliminado_por' => $actor, 'actualizado_por' => $actor, 'id' => $id]);
    }
    public function restaurar(int $id, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE usuarios SET eliminado=0,eliminado_en=NULL,eliminado_por=NULL,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=1');
        $q->execute(['actor' => $actor, 'id' => $id]);
        if (!$q->rowCount()) throw new DomainException('La cuenta no está eliminada o no existe.');
    }
    public function restablecerClave(int $id, string $hash, int $actor): void {
        $q = Conexion::obtener()->prepare('UPDATE usuarios SET password_hash=:hash,debe_cambiar_password=1,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $q->execute(['hash' => $hash, 'actor' => $actor, 'id' => $id]);
        if (!$q->rowCount()) throw new DomainException('No se encontró una cuenta activa.');
    }
    public function cambiarClave(int $id, string $hash): void {
        $q = Conexion::obtener()->prepare('UPDATE usuarios SET password_hash=:hash,debe_cambiar_password=0,actualizado_por=:actor WHERE id_usuario=:id AND eliminado=0');
        $q->execute(['hash' => $hash, 'actor' => $id, 'id' => $id]);
    }
    public function registrar(array $datos): int {
        $pdo = Conexion::obtener();
        $q = $pdo->prepare('INSERT INTO usuarios(id_rol,id_estado,usuario,correo,nombres,apellidos,telefono,password_hash,debe_cambiar_password,creado_por) VALUES(:rol,:estado,:usuario,:correo,:nombres,:apellidos,:telefono,:hash,:cambio,:actor)');
        $q->execute($datos);
        return (int) $pdo->lastInsertId();
    }
    public function guardarIntento(string $identificador, string $ip, bool $exitoso, ?string $bloqueadoHasta = null): void {
        $q = Conexion::obtener()->prepare('INSERT INTO intentos_login(identificador,ip_cliente,exitoso,bloqueado_hasta) VALUES(:identificador,:ip,:exitoso,:bloqueado)');
        $q->execute(['identificador' => mb_substr(mb_strtolower($identificador), 0, 254), 'ip' => $ip, 'exitoso' => (int)$exitoso, 'bloqueado' => $bloqueadoHasta]);
    }
    public function bloqueoVigente(string $identificador, string $ip): ?string {
        $q = Conexion::obtener()->prepare('SELECT MAX(bloqueado_hasta) FROM intentos_login WHERE identificador=:id AND ip_cliente=:ip AND ocurrido_en>DATE_SUB(NOW(),INTERVAL 1 DAY) AND eliminado=0');
        $q->execute(['id' => mb_substr(mb_strtolower($identificador), 0, 254), 'ip' => $ip]);
        $v = $q->fetchColumn();
        return $v ? (string)$v: null;
    }
    public function fallosRecientes(string $identificador, string $ip, int $minutos): int {
        $desde = (new DateTimeImmutable())->modify('-' . max(1, $minutos) . ' minutes')->format('Y-m-d H:i:s');
        $q = Conexion::obtener()->prepare('SELECT COUNT(*) FROM intentos_login i WHERE i.identificador=:id AND i.ip_cliente=:ip AND i.exitoso=0 AND i.ocurrido_en>=:desde AND i.ocurrido_en>(SELECT COALESCE(MAX(exitoso.ocurrido_en),\'1000-01-01 00:00:00\') FROM intentos_login exitoso WHERE exitoso.identificador=:id_exitoso AND exitoso.ip_cliente=:ip_exitoso AND exitoso.exitoso=1 AND exitoso.eliminado=0) AND i.eliminado=0');
        $normalizado = mb_substr(mb_strtolower($identificador), 0, 254);
        $q->execute(['id' => $normalizado, 'ip' => $ip, 'desde' => $desde, 'id_exitoso' => $normalizado, 'ip_exitoso' => $ip]);
        return (int)$q->fetchColumn();
    }
    public function transaccion(callable $accion): mixed {
        $pdo = Conexion::obtener();
        $pdo->beginTransaction();
        try {
            $resultado = $accion($pdo);
            $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
