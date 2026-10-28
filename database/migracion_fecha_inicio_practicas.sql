ALTER TABLE practicas
  ADD COLUMN fecha_inicio DATE NULL AFTER ubicacion,
  ADD KEY ix_practicas_inicio (fecha_inicio, id_estado, eliminado);

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
