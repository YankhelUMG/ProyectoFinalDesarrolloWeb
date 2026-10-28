-- Vistas de lectura para la aplicación. Todas excluyen registros eliminados.
-- Ejecutar después de sql/database.sql.

USE sistema_practicas;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_catalogos AS
SELECT 'rol' AS tipo,r.id_rol AS id,r.codigo,r.nombre,r.descripcion,NULL AS color,NULL AS orden
FROM roles r WHERE r.eliminado=0
UNION ALL SELECT 'permiso',p.id_permiso,p.codigo,p.nombre,p.descripcion,NULL,NULL FROM permisos p WHERE p.eliminado=0
UNION ALL SELECT e.tipo_entidad,e.id_estado,e.codigo,e.nombre,e.descripcion,e.color,e.orden
FROM estados e JOIN tipos_entidad t ON t.codigo=e.tipo_entidad AND t.eliminado=0 WHERE e.eliminado=0
UNION ALL SELECT 'area',a.id_area,a.codigo,a.nombre,a.descripcion,NULL,NULL FROM areas a WHERE a.eliminado=0
UNION ALL SELECT 'carrera',c.id_carrera,c.codigo,c.nombre,NULL,NULL,NULL FROM carreras c WHERE c.eliminado=0
UNION ALL SELECT 'modalidad',m.id_modalidad,m.codigo,m.nombre,NULL,NULL,NULL FROM modalidades m WHERE m.eliminado=0
UNION ALL SELECT 'tipo_entidad',t.id_tipo_entidad,t.codigo,t.nombre,NULL,NULL,NULL FROM tipos_entidad t WHERE t.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_usuario_acceso AS
SELECT u.id_usuario,u.usuario,u.correo,u.nombres,u.apellidos,u.telefono,
       u.id_rol,u.id_estado,u.debe_cambiar_password,u.ultimo_acceso,
       r.codigo AS rol_codigo,r.nombre AS rol_nombre,
       e.codigo AS estado_codigo,e.nombre AS estado_nombre
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol AND r.eliminado=0
JOIN estados e ON e.id_estado=u.id_estado AND e.tipo_entidad='usuario' AND e.eliminado=0
WHERE u.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_usuario_permisos AS
SELECT u.id_usuario,r.codigo AS rol_codigo,p.codigo AS permiso_codigo
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol AND r.eliminado=0
JOIN rol_permiso rp ON rp.id_rol=r.id_rol AND rp.eliminado=0
JOIN permisos p ON p.id_permiso=rp.id_permiso AND p.eliminado=0
JOIN estados eu ON eu.id_estado=u.id_estado AND eu.tipo_entidad='usuario' AND eu.codigo='activo' AND eu.eliminado=0
WHERE u.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_estudiantes AS
SELECT e.id_estudiante,e.id_usuario,e.id_carrera,c.codigo AS carrera_codigo,c.nombre AS carrera,
       e.carnet,e.semestre,e.universidad,e.cv_nombre,e.cv_eliminado,
       u.nombres,u.apellidos,u.correo,u.telefono
FROM estudiantes e JOIN usuarios u ON u.id_usuario=e.id_usuario AND u.eliminado=0
JOIN estados eu ON eu.id_estado=u.id_estado AND eu.tipo_entidad='usuario' AND eu.codigo='activo' AND eu.eliminado=0
JOIN carreras c ON c.id_carrera=e.id_carrera AND c.eliminado=0 WHERE e.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_empresas AS
SELECT e.id_empresa,e.id_usuario,e.id_estado_revision,ee.codigo AS estado_codigo,ee.nombre AS estado,ee.color AS estado_color,
       e.nombre_legal,e.nombre_comercial,e.identificacion_fiscal,e.sector,e.descripcion,
       e.correo_contacto,e.telefono,e.sitio_web,e.direccion,e.observaciones_coordinacion,
       e.revisada_en,e.id_administrador_revision,u.nombres,u.apellidos,u.correo AS correo_usuario
FROM empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario AND u.eliminado=0
JOIN estados ee ON ee.id_estado=e.id_estado_revision AND ee.tipo_entidad='empresa' AND ee.eliminado=0
WHERE e.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_usuarios_admin AS
SELECT u.id_usuario,u.usuario,u.correo,u.nombres,u.apellidos,u.telefono,
       u.debe_cambiar_password,u.ultimo_acceso,u.id_rol,r.codigo AS rol_codigo,r.nombre AS rol,
       u.id_estado,es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       u.creado_en,u.actualizado_en,u.eliminado
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol AND r.eliminado=0
JOIN estados es ON es.id_estado=u.id_estado AND es.tipo_entidad='usuario' AND es.eliminado=0
WHERE u.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_empresas_revision AS
SELECT e.*,u.usuario
FROM vw_empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario
WHERE e.estado_codigo IN ('pendiente','rechazada');

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_practicas_publicadas AS
SELECT p.id_practica,p.titulo,p.descripcion,p.requisitos,p.ubicacion,p.fecha_inicio,p.cupo_maximo,
       p.fecha_limite_postulacion,p.publicada_en,p.id_empresa,
       COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,
       a.id_area,a.codigo AS area_codigo,a.nombre AS area,
       m.id_modalidad,m.codigo AS modalidad_codigo,m.nombre AS modalidad,
       es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       (SELECT COUNT(*) FROM postulaciones po JOIN estados ep ON ep.id_estado=po.id_estado AND ep.tipo_entidad='postulacion' AND ep.eliminado=0
        WHERE po.id_practica=p.id_practica AND po.eliminado=0 AND ep.codigo='aceptada') AS cupos_ocupados
FROM practicas p JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='practica' AND es.eliminado=0
JOIN empresas e ON e.id_empresa=p.id_empresa AND e.eliminado=0
JOIN estados ee ON ee.id_estado=e.id_estado_revision AND ee.tipo_entidad='empresa' AND ee.codigo='aprobada' AND ee.eliminado=0
JOIN areas a ON a.id_area=p.id_area AND a.eliminado=0
JOIN modalidades m ON m.id_modalidad=p.id_modalidad AND m.eliminado=0
WHERE p.eliminado=0 AND es.codigo='publicada' AND p.fecha_limite_postulacion>=CURRENT_DATE;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_practicas_empresa AS
SELECT p.*,es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       a.codigo AS area_codigo,a.nombre AS area,m.codigo AS modalidad_codigo,m.nombre AS modalidad,
       e.id_usuario AS id_usuario_empresa,
       (SELECT COUNT(*) FROM postulaciones po WHERE po.id_practica=p.id_practica AND po.eliminado=0) AS total_postulaciones,
       (SELECT COUNT(*) FROM postulaciones po JOIN estados ep ON ep.id_estado=po.id_estado AND ep.tipo_entidad='postulacion' AND ep.eliminado=0
        WHERE po.id_practica=p.id_practica AND po.eliminado=0 AND ep.codigo='aceptada') AS cupos_ocupados,
       (SELECT COUNT(*) FROM postulaciones po JOIN estados ep ON ep.id_estado=po.id_estado AND ep.tipo_entidad='postulacion' AND ep.eliminado=0
        WHERE po.id_practica=p.id_practica AND po.eliminado=0 AND ep.codigo='pendiente') AS pendientes
FROM practicas p JOIN empresas e ON e.id_empresa=p.id_empresa AND e.eliminado=0
JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='practica' AND es.eliminado=0
JOIN areas a ON a.id_area=p.id_area AND a.eliminado=0 JOIN modalidades m ON m.id_modalidad=p.id_modalidad AND m.eliminado=0
WHERE p.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_practicas_admin AS
SELECT p.id_practica,p.id_empresa,COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,
       p.id_estado,es.codigo AS estado_codigo,es.nombre AS estado,
       p.id_area,a.nombre AS area,p.id_modalidad,m.nombre AS modalidad,
       p.titulo,p.descripcion,p.requisitos,p.ubicacion,p.cupo_maximo,
       p.fecha_inicio,p.fecha_limite_postulacion,p.publicada_en,p.observaciones_coordinacion,
       p.creado_en,p.actualizado_en,
       (SELECT COUNT(*) FROM postulaciones po WHERE po.id_practica=p.id_practica AND po.eliminado=0) AS total_postulaciones,
       (SELECT COUNT(*) FROM postulaciones po JOIN estados ep ON ep.id_estado=po.id_estado AND ep.tipo_entidad='postulacion' AND ep.codigo='aceptada' AND ep.eliminado=0
        WHERE po.id_practica=p.id_practica AND po.eliminado=0) AS aceptadas
FROM practicas p JOIN empresas e ON e.id_empresa=p.id_empresa AND e.eliminado=0
JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='practica' AND es.eliminado=0
JOIN areas a ON a.id_area=p.id_area AND a.eliminado=0
JOIN modalidades m ON m.id_modalidad=p.id_modalidad AND m.eliminado=0
WHERE p.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_postulaciones_estudiante AS
SELECT po.id_postulacion,po.id_estudiante,st.id_usuario,po.id_practica,po.id_estado,
       po.carta_presentacion,po.comentario,po.fecha_postulacion,
       es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       p.titulo AS practica,COALESCE(em.nombre_comercial,em.nombre_legal) AS empresa
FROM postulaciones po JOIN estudiantes st ON st.id_estudiante=po.id_estudiante AND st.eliminado=0
JOIN estados es ON es.id_estado=po.id_estado AND es.tipo_entidad='postulacion' AND es.eliminado=0
JOIN practicas p ON p.id_practica=po.id_practica AND p.eliminado=0
JOIN empresas em ON em.id_empresa=p.id_empresa AND em.eliminado=0 WHERE po.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_postulaciones_empresa AS
SELECT po.id_postulacion,po.id_estudiante,po.id_practica,po.id_estado,
       po.carta_presentacion,po.comentario,po.fecha_postulacion,
       es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       em.id_usuario AS id_usuario_empresa,p.titulo AS practica,
       st.carnet,st.semestre,st.universidad,po.cv_nombre_snapshot AS cv_nombre,
       st.cv_eliminado,c.nombre AS carrera,u.id_usuario AS id_usuario_estudiante,u.nombres,u.apellidos,u.correo,u.telefono
FROM postulaciones po JOIN estados es ON es.id_estado=po.id_estado AND es.tipo_entidad='postulacion' AND es.eliminado=0
JOIN estudiantes st ON st.id_estudiante=po.id_estudiante AND st.eliminado=0
JOIN usuarios u ON u.id_usuario=st.id_usuario AND u.eliminado=0
JOIN estados eu ON eu.id_estado=u.id_estado AND eu.tipo_entidad='usuario' AND eu.codigo='activo' AND eu.eliminado=0
JOIN carreras c ON c.id_carrera=st.id_carrera AND c.eliminado=0
JOIN practicas p ON p.id_practica=po.id_practica AND p.eliminado=0
JOIN empresas em ON em.id_empresa=p.id_empresa AND em.eliminado=0 WHERE po.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_postulaciones_admin AS
SELECT po.id_postulacion,po.id_estudiante,po.id_practica,po.id_estado,
       po.carta_presentacion,po.comentario,po.fecha_postulacion,
       es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       p.titulo AS practica,COALESCE(em.nombre_comercial,em.nombre_legal) AS empresa,
       em.id_empresa,em.id_usuario AS id_usuario_empresa,
       st.carnet,st.semestre,st.universidad,po.cv_nombre_snapshot AS cv_nombre,st.cv_eliminado,
       c.nombre AS carrera,u.id_usuario AS id_usuario_estudiante,u.nombres,u.apellidos,u.correo,u.telefono
FROM postulaciones po JOIN estados es ON es.id_estado=po.id_estado AND es.tipo_entidad='postulacion' AND es.eliminado=0
JOIN estudiantes st ON st.id_estudiante=po.id_estudiante
JOIN usuarios u ON u.id_usuario=st.id_usuario
JOIN carreras c ON c.id_carrera=st.id_carrera
JOIN practicas p ON p.id_practica=po.id_practica
JOIN empresas em ON em.id_empresa=p.id_empresa WHERE po.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_historial_postulaciones AS
SELECT h.id_historial,h.entidad,h.id_registro AS id_postulacion,
       h.id_estado_anterior,ea.nombre AS estado_anterior,h.id_estado_nuevo,en.nombre AS estado_nuevo,
       h.id_usuario,CONCAT(u.nombres,' ',u.apellidos) AS actor,h.comentario,h.fecha_hora,
       po.id_estudiante,st.id_usuario AS propietario
FROM historial_estados h JOIN estados en ON en.id_estado=h.id_estado_nuevo AND en.eliminado=0
LEFT JOIN estados ea ON ea.id_estado=h.id_estado_anterior AND ea.eliminado=0
LEFT JOIN usuarios u ON u.id_usuario=h.id_usuario
JOIN postulaciones po ON h.entidad='postulacion' AND po.id_postulacion=h.id_registro
JOIN estudiantes st ON st.id_estudiante=po.id_estudiante WHERE h.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_bitacora AS
SELECT b.id_bitacora,b.tabla_afectada,b.id_registro,b.accion,b.valores_anteriores,b.valores_nuevos,
       b.usuario_id,CONCAT(u.nombres,' ',u.apellidos) AS usuario,b.ip_cliente,b.fecha_hora
FROM bitacora b LEFT JOIN usuarios u ON u.id_usuario=b.usuario_id;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_dashboard_practicas AS
SELECT es.codigo AS estado_codigo,es.nombre AS estado,es.color,COUNT(*) AS cantidad
FROM practicas p JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='practica' AND es.eliminado=0
WHERE p.eliminado=0 GROUP BY es.id_estado,es.codigo,es.nombre,es.color;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_dashboard_postulaciones AS
SELECT es.codigo AS estado_codigo,es.nombre AS estado,es.color,COUNT(*) AS cantidad
FROM postulaciones p JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='postulacion' AND es.eliminado=0
WHERE p.eliminado=0 GROUP BY es.id_estado,es.codigo,es.nombre,es.color;



