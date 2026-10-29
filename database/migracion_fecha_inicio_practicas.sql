USE sistema_practicas;

-- Permite repetir la migración sin borrar las prácticas existentes.
SET @falta_fecha = (SELECT COUNT(*) = 0 FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'practicas' AND column_name = 'fecha_inicio');
SET @sql_fecha = IF(@falta_fecha,
  'ALTER TABLE practicas ADD COLUMN fecha_inicio DATE NULL AFTER ubicacion', 'SELECT 1');
PREPARE migracion_fecha FROM @sql_fecha;
EXECUTE migracion_fecha;
DEALLOCATE PREPARE migracion_fecha;

SET @falta_indice = (SELECT COUNT(*) = 0 FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'practicas' AND index_name = 'ix_practicas_inicio');
SET @sql_indice = IF(@falta_indice,
  'ALTER TABLE practicas ADD KEY ix_practicas_inicio (fecha_inicio, id_estado, eliminado)', 'SELECT 1');
PREPARE migracion_indice FROM @sql_indice;
EXECUTE migracion_indice;
DEALLOCATE PREPARE migracion_indice;

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
