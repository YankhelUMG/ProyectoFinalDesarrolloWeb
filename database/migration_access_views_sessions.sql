-- Ejecutar con una cuenta administradora de MySQL sobre sistema_practicas.
-- La aplicación consulta estas vistas; los cambios de datos siguen pasando por PHP preparado.

ALTER TABLE palabras_clave ADD COLUMN estado ENUM('activo','borrado') NOT NULL DEFAULT 'activo';

INSERT INTO permisos(codigo,nombre,descripcion) VALUES
 ('empresas.aprobar','Aprobar empresas','Validar solicitudes de registro de empresas'),
 ('empresas.perfil_propio','Editar perfil empresarial propio','Corregir perfil y reenviarlo a coordinación'),
 ('usuarios.perfil_propio','Editar perfil propio','Administrar los datos del perfil estudiantil propio'),
 ('oportunidades.gestionar_propias','Gestionar oportunidades propias','Crear y modificar oportunidades de la empresa autenticada'),
 ('oportunidades.aprobar','Revisar oportunidades','Publicar o rechazar oportunidades pendientes'),
 ('usuarios.roles.gestionar','Gestionar perfiles','Asignar perfiles a usuarios desde superadministración')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),descripcion=VALUES(descripcion);

-- Add the intended grants without duplicating existing role-permission pairs.
INSERT INTO roles_permisos(id_rol,id_permiso)
SELECT r.id_rol,p.id_permiso FROM roles r JOIN permisos p ON
 (r.nombre='administrador' AND p.codigo IN ('empresas.aprobar','oportunidades.consultar','oportunidades.aprobar','postulaciones.consultar_todas','postulaciones.cambiar_estado','bitacora.consultar')) OR
 (r.nombre='superadministrador' AND p.codigo IN ('usuarios.gestionar','empresas.gestionar','empresas.aprobar','empresas.perfil_propio','usuarios.perfil_propio','oportunidades.consultar','oportunidades.gestionar','oportunidades.gestionar_propias','oportunidades.aprobar','postulaciones.crear','postulaciones.consultar_propias','postulaciones.consultar_todas','postulaciones.cambiar_estado','bitacora.consultar','usuarios.roles.gestionar')) OR
 (r.nombre='usuario' AND p.codigo='oportunidades.consultar') OR
 (r.nombre='estudiante' AND p.codigo IN ('oportunidades.consultar','postulaciones.crear','postulaciones.consultar_propias','usuarios.perfil_propio')) OR
 (r.nombre='empresa' AND p.codigo IN ('oportunidades.consultar','empresas.perfil_propio','oportunidades.gestionar_propias','postulaciones.consultar_todas','postulaciones.cambiar_estado'))
ON DUPLICATE KEY UPDATE id_permiso=VALUES(id_permiso);

-- A regular administrator must not administer user roles or accounts.
DELETE rp FROM roles_permisos rp JOIN roles r ON r.id_rol=rp.id_rol
JOIN permisos p ON p.id_permiso=rp.id_permiso
WHERE r.nombre='administrador' AND p.codigo IN ('usuarios.gestionar','usuarios.roles.gestionar');

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_usuario_autenticacion AS
SELECT u.id_usuario,u.usuario,u.nombres,u.apellidos,u.correo,u.password_hash,u.estado,r.nombre AS role
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_usuario_acceso AS
SELECT u.id_usuario,u.usuario,u.nombres,u.apellidos,u.correo,u.estado,r.nombre AS role
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_sesion_acceso AS
SELECT s.id_usuario,s.token_hash,s.revocada_en,s.expira_en,
       u.usuario,u.nombres,u.apellidos,u.correo,u.estado,r.nombre AS role
FROM sesiones_usuario s JOIN usuarios u ON u.id_usuario=s.id_usuario
JOIN roles r ON r.id_rol=u.id_rol;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_usuario_permisos AS
SELECT u.id_usuario,r.nombre AS role,p.codigo AS permiso
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol
JOIN roles_permisos rp ON rp.id_rol=r.id_rol
JOIN permisos p ON p.id_permiso=rp.id_permiso
WHERE u.estado IN ('activo','pendiente','requiere_cambios','rechazada');

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_seguimiento_postulaciones AS
SELECT p.id_postulacion,p.id_estudiante,est.id_usuario,p.id_oportunidad,p.fecha_postulacion,
       p.carta_presentacion,p.observaciones,ep.codigo AS estado_codigo,ep.nombre AS estado,
       o.titulo AS oportunidad,o.modalidad,o.ubicacion,
       COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa
FROM postulaciones p JOIN estudiantes est ON est.id_estudiante=p.id_estudiante
JOIN estados_postulacion ep ON ep.id_estado=p.id_estado
JOIN oportunidades o ON o.id_oportunidad=p.id_oportunidad
JOIN empresas e ON e.id_empresa=o.id_empresa WHERE ep.codigo<>'borrado';

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_seguimiento_historial AS
SELECT b.id_bitacora,b.id_registro AS id_postulacion,b.accion,b.fecha_evento,
       JSON_UNQUOTE(JSON_EXTRACT(b.datos_anteriores,'$.id_estado')) AS old_id,
       JSON_UNQUOTE(JSON_EXTRACT(b.datos_nuevos,'$.id_estado')) AS new_id,
       JSON_UNQUOTE(JSON_EXTRACT(b.datos_nuevos,'$.comentario')) AS comentario
FROM bitacora b WHERE b.tabla_afectada='postulaciones' AND b.accion IN ('INSERT','CAMBIO_ESTADO');

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_estados_postulacion AS
SELECT id_estado,codigo,nombre,descripcion FROM estados_postulacion;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_estudiante_perfil AS
SELECT e.id_estudiante,e.id_usuario,e.carnet,e.carrera,e.semestre,e.universidad,e.cv_nombre,e.cv_ruta,
       u.nombres,u.apellidos,u.correo,u.telefono
FROM estudiantes e JOIN usuarios u ON u.id_usuario=e.id_usuario;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_admin_oportunidades AS
SELECT o.id_oportunidad,o.id_empresa,o.titulo,o.descripcion,o.requisitos,o.ubicacion,o.modalidad,
       o.tipo_practica,o.remuneracion,o.moneda,o.vacantes,o.fecha_inicio,o.fecha_fin,
       o.fecha_limite_postulacion,o.estado,o.observaciones_coordinacion,
       o.publicada_en,COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,
       (SELECT GROUP_CONCAT(pc.nombre ORDER BY pc.nombre SEPARATOR ', ') FROM palabras_clave pc WHERE pc.id_oportunidad=o.id_oportunidad AND pc.estado='activo') AS palabras_clave
FROM oportunidades o JOIN empresas e ON e.id_empresa=o.id_empresa;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_roles_catalogo AS
SELECT id_rol,nombre,descripcion FROM roles;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_oportunidades_publicadas AS
SELECT o.id_oportunidad,o.titulo,o.descripcion,o.requisitos,o.ubicacion,o.modalidad,o.tipo_practica,
       o.remuneracion,o.moneda,o.vacantes,o.fecha_inicio,o.fecha_fin,o.fecha_limite_postulacion,o.publicada_en,
       e.id_empresa,COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,
       (SELECT GROUP_CONCAT(pc.nombre ORDER BY pc.nombre SEPARATOR ', ') FROM palabras_clave pc WHERE pc.id_oportunidad=o.id_oportunidad AND pc.estado='activo') AS palabras_clave
FROM oportunidades o JOIN empresas e ON e.id_empresa=o.id_empresa
WHERE o.estado='publicada' AND o.fecha_limite_postulacion>=CURRENT_DATE;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_admin_postulaciones AS
SELECT p.id_postulacion,p.fecha_postulacion,p.carta_presentacion,p.observaciones,
       ep.codigo AS estado,ep.nombre AS estado_nombre,o.id_oportunidad,o.titulo AS oportunidad,
       e.id_empresa,COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,
       est.id_estudiante,est.carnet,est.carrera,est.semestre,est.universidad,est.cv_nombre,
       u.id_usuario,u.nombres,u.apellidos,u.correo,u.telefono,e.correo_contacto,est.cv_ruta
FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado
JOIN oportunidades o ON o.id_oportunidad=p.id_oportunidad
JOIN empresas e ON e.id_empresa=o.id_empresa JOIN estudiantes est ON est.id_estudiante=p.id_estudiante
JOIN usuarios u ON u.id_usuario=est.id_usuario WHERE ep.codigo<>'borrado';

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_empresa_postulaciones AS
SELECT p.*,o.titulo AS oportunidad,COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,
       e.id_usuario AS id_usuario_empresa,u.nombres,u.apellidos,u.correo,u.telefono,
       est.carnet,est.carrera,est.semestre,est.universidad,est.cv_nombre,est.cv_ruta,
       u.id_usuario AS id_usuario_estudiante,ep.codigo AS estado_codigo,ep.nombre AS estado
FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado
JOIN oportunidades o ON o.id_oportunidad=p.id_oportunidad JOIN empresas e ON e.id_empresa=o.id_empresa
JOIN estudiantes est ON est.id_estudiante=p.id_estudiante JOIN usuarios u ON u.id_usuario=est.id_usuario
WHERE ep.codigo<>'borrado';

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_admin_empresas_revision AS
SELECT e.id_empresa,e.id_usuario,e.nombre_legal,e.nombre_comercial,e.identificacion_fiscal,e.sector,
       e.descripcion,e.correo_contacto,e.telefono,e.sitio_web,e.direccion,u.nombres,u.apellidos,u.correo,u.estado
FROM empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario
JOIN roles r ON r.id_rol=u.id_rol WHERE r.nombre='empresa';

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_empresa_perfil AS
SELECT e.*,u.estado AS estado_cuenta,u.nombres AS usuario_nombres,u.apellidos AS usuario_apellidos,
       u.correo AS usuario_correo
FROM empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_empresa_oportunidades AS
SELECT o.*,
       COALESCE(NULLIF(o.estado,''),'pendiente_revision') AS estado_panel,
       (SELECT GROUP_CONCAT(pc.nombre ORDER BY pc.nombre SEPARATOR ', ') FROM palabras_clave pc WHERE pc.id_oportunidad=o.id_oportunidad AND pc.estado='activo') AS palabras_clave,
       (SELECT COUNT(*) FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado WHERE p.id_oportunidad=o.id_oportunidad AND ep.codigo<>'borrado') AS numero_postulaciones,
       (SELECT COUNT(*) FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado WHERE p.id_oportunidad=o.id_oportunidad AND ep.codigo='aceptada') AS perfiles_aceptados,
       (SELECT COUNT(*) FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado WHERE p.id_oportunidad=o.id_oportunidad AND ep.codigo NOT IN ('aceptada','rechazada','retirada','borrado')) AS perfiles_pendientes,
       e.id_usuario AS id_usuario_empresa
FROM oportunidades o JOIN empresas e ON e.id_empresa=o.id_empresa WHERE COALESCE(o.estado,'')<>'borrado';

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_admin_usuarios_roles AS
SELECT u.id_usuario,u.nombres,u.apellidos,u.usuario,u.correo,u.estado,r.nombre AS role
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_admin_bitacora AS
SELECT b.id_bitacora,b.fecha_evento,b.accion,b.tabla_afectada,b.id_registro,
       b.datos_anteriores,b.datos_nuevos,b.ip_origen,
       CONCAT(u.nombres,' ',u.apellidos) AS actor
FROM bitacora b LEFT JOIN usuarios u ON u.id_usuario=b.id_usuario;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_empresa_notificaciones AS
SELECT n.id_notificacion,n.asunto,n.estado,n.intentos,n.error_ultimo,n.procesado_en,
       e.id_usuario AS id_usuario_empresa,o.id_oportunidad,o.titulo AS oportunidad
FROM cola_notificaciones n JOIN postulaciones p ON p.id_postulacion=n.id_postulacion
JOIN oportunidades o ON o.id_oportunidad=p.id_oportunidad JOIN empresas e ON e.id_empresa=o.id_empresa
WHERE n.tipo='perfiles_aceptados';

-- Grant only read access to these views for the PHP application account.
GRANT SELECT ON sistema_practicas.vw_usuario_autenticacion TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_usuario_acceso TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_sesion_acceso TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_usuario_permisos TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_roles_catalogo TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_oportunidades_publicadas TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_seguimiento_postulaciones TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_seguimiento_historial TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_estados_postulacion TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_estudiante_perfil TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_admin_oportunidades TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_admin_postulaciones TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_admin_empresas_revision TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_admin_usuarios_roles TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_admin_bitacora TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_empresa_perfil TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_empresa_oportunidades TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_empresa_postulaciones TO 'usr_proyecto'@'localhost';
GRANT SELECT ON sistema_practicas.vw_empresa_notificaciones TO 'usr_proyecto'@'localhost';

-- Keep a full before/after JSON snapshot when a tag is renamed or soft-deleted.
DROP TRIGGER IF EXISTS trg_keywords_au;
DELIMITER $$
CREATE TRIGGER trg_keywords_au AFTER UPDATE ON palabras_clave FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','palabras_clave',NEW.id_palabra_clave,
    JSON_OBJECT('id_oportunidad',OLD.id_oportunidad,'nombre',OLD.nombre,'nombre_busqueda',OLD.nombre_busqueda,'categoria',OLD.categoria,'estado',OLD.estado),
    JSON_OBJECT('id_oportunidad',NEW.id_oportunidad,'nombre',NEW.nombre,'nombre_busqueda',NEW.nombre_busqueda,'categoria',NEW.categoria,'estado',NEW.estado),@app_ip);
END$$
DELIMITER ;
