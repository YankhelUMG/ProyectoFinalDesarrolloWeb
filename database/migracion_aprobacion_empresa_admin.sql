USE sistema_practicas;

INSERT IGNORE INTO rol_permiso(id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
FROM roles r
JOIN permisos p ON p.codigo = 'empresas.revisar' AND p.eliminado = 0
WHERE r.codigo = 'admin' AND r.eliminado = 0;

INSERT IGNORE INTO transiciones_estado(entidad, id_estado_origen, id_estado_destino, id_rol_permitido, requiere_comentario)
SELECT 'empresa', origen.id_estado, destino.id_estado, rol.id_rol, transicion.requiere_comentario
FROM (
  SELECT 'pendiente' AS codigo_origen, 'aprobada' AS codigo_destino, 'admin' AS codigo_rol, 0 AS requiere_comentario
  UNION ALL SELECT 'pendiente', 'rechazada', 'admin', 1
) AS transicion
JOIN estados origen ON origen.tipo_entidad = 'empresa' AND origen.codigo = transicion.codigo_origen AND origen.eliminado = 0
JOIN estados destino ON destino.tipo_entidad = 'empresa' AND destino.codigo = transicion.codigo_destino AND destino.eliminado = 0
JOIN roles rol ON rol.codigo = transicion.codigo_rol AND rol.eliminado = 0;
