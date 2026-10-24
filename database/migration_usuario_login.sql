-- Ejecuta este script una sola vez si ya importaste la versión anterior del esquema.
-- Asigna como usuario la parte anterior a @ del correo existente.
USE sistema_practicas;
ALTER TABLE usuarios ADD COLUMN usuario VARCHAR(40) NULL AFTER id_usuario;
UPDATE usuarios
SET usuario = LEFT(LOWER(SUBSTRING_INDEX(correo, '@', 1)), 40)
WHERE usuario IS NULL OR usuario = '';
-- Evita colisiones si dos correos comparten la misma parte local.
UPDATE usuarios u
JOIN (
  SELECT usuario, MIN(id_usuario) AS primer_id
  FROM usuarios GROUP BY usuario HAVING COUNT(*) > 1
) repetidos ON repetidos.usuario = u.usuario
SET u.usuario = CONCAT(LEFT(u.usuario, 30), '_', u.id_usuario)
WHERE u.id_usuario <> repetidos.primer_id;
ALTER TABLE usuarios MODIFY usuario VARCHAR(40) NOT NULL,
  ADD UNIQUE KEY uq_usuarios_usuario (usuario);

-- Completa el perfil de superadministración para instalaciones anteriores.
INSERT IGNORE INTO roles (nombre, descripcion)
VALUES ('superadministrador', 'Administra roles, cuentas y configuración del sistema');
INSERT IGNORE INTO roles_permisos (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
FROM roles r CROSS JOIN permisos p
WHERE r.nombre = 'superadministrador'
  AND p.codigo IN ('usuarios.gestionar','empresas.gestionar','empresas.aprobar',
    'oportunidades.consultar','oportunidades.gestionar','oportunidades.aprobar',
    'postulaciones.consultar_todas','postulaciones.cambiar_estado',
    'bitacora.consultar','usuarios.roles.gestionar');

-- Las oportunidades esperan aprobación, por eso aún no tienen administrador asignado.
ALTER TABLE oportunidades MODIFY COLUMN id_administrador BIGINT UNSIGNED NULL;

-- Add a basic account role so superadministration can distinguish browsing users from students.
INSERT INTO roles(nombre,descripcion)
VALUES('usuario','Cuenta básica para consultar oportunidades públicas')
ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion);
INSERT INTO roles_permisos(id_rol,id_permiso)
SELECT r.id_rol,p.id_permiso FROM roles r JOIN permisos p ON p.codigo='oportunidades.consultar'
WHERE r.nombre='usuario'
ON DUPLICATE KEY UPDATE id_permiso=VALUES(id_permiso);

-- Permite pedir correcciones a la empresa y guardar el comentario de coordinación.
ALTER TABLE oportunidades
  MODIFY COLUMN estado ENUM('pendiente_revision','requiere_cambios','borrador','publicada','rechazada','pausada','cerrada','borrado') NOT NULL DEFAULT 'pendiente_revision',
  ADD COLUMN observaciones_coordinacion TEXT NULL AFTER estado;

-- Repara registros heredados que llegaron a tener estado vacío antes de la validación actual.
UPDATE oportunidades SET estado='pendiente_revision' WHERE estado IS NULL OR estado='';

-- Include review comments in the same before/after audit snapshot as opportunity status.
DROP TRIGGER IF EXISTS trg_oportunidades_ai;
DROP TRIGGER IF EXISTS trg_oportunidades_au;
DELIMITER $$
CREATE TRIGGER trg_oportunidades_ai AFTER INSERT ON oportunidades FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','oportunidades',NEW.id_oportunidad,
    JSON_OBJECT('titulo',NEW.titulo,'id_empresa',NEW.id_empresa,'estado',NEW.estado,
      'observaciones_coordinacion',NEW.observaciones_coordinacion),@app_ip);
END$$
CREATE TRIGGER trg_oportunidades_au AFTER UPDATE ON oportunidades FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','oportunidades',NEW.id_oportunidad,
    JSON_OBJECT('titulo',OLD.titulo,'estado',OLD.estado,'fecha_limite_postulacion',OLD.fecha_limite_postulacion,'publicada_en',OLD.publicada_en,'observaciones_coordinacion',OLD.observaciones_coordinacion),
    JSON_OBJECT('titulo',NEW.titulo,'estado',NEW.estado,'fecha_limite_postulacion',NEW.fecha_limite_postulacion,'publicada_en',NEW.publicada_en,'observaciones_coordinacion',NEW.observaciones_coordinacion),@app_ip);
END$$
DELIMITER ;
