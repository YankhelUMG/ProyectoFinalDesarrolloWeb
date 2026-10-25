USE sistema_practicas;

-- El administrador conserva la consulta para seguimiento, pero solo la empresa decide.
DELETE rp FROM roles_permisos rp
JOIN roles r ON r.id_rol=rp.id_rol
JOIN permisos p ON p.id_permiso=rp.id_permiso
WHERE r.nombre IN ('administrador','superadministrador')
  AND p.codigo='postulaciones.cambiar_estado';

INSERT INTO roles_permisos(id_rol,id_permiso)
SELECT r.id_rol,p.id_permiso FROM roles r CROSS JOIN permisos p
WHERE r.nombre='empresa' AND p.codigo='postulaciones.cambiar_estado'
ON DUPLICATE KEY UPDATE id_permiso=VALUES(id_permiso);

-- La regla también se aplica en el procedimiento de la base de datos.
DROP PROCEDURE IF EXISTS sp_cambiar_estado_postulacion;
DELIMITER $$
CREATE PROCEDURE sp_cambiar_estado_postulacion(IN p_id_postulacion BIGINT UNSIGNED,
  IN p_codigo_estado VARCHAR(30), IN p_comentario VARCHAR(500))
BEGIN
  DECLARE v_estado SMALLINT UNSIGNED DEFAULT NULL;
  DECLARE v_existe INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT COUNT(*) INTO v_existe FROM postulaciones WHERE id_postulacion=p_id_postulacion;
  IF v_existe=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Postulación no encontrada'; END IF;
  SELECT id_estado INTO v_estado FROM estados_postulacion WHERE codigo=p_codigo_estado;
  IF v_estado IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Estado inválido'; END IF;
  IF p_codigo_estado='rechazada' AND (p_comentario IS NULL OR TRIM(p_comentario)='') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El rechazo requiere un motivo';
  END IF;
  SET @app_comentario=p_comentario;
  UPDATE postulaciones
     SET id_estado=v_estado,
         observaciones=IF(p_comentario IS NOT NULL AND TRIM(p_comentario)<>'',p_comentario,observaciones)
   WHERE id_postulacion=p_id_postulacion;
  COMMIT;
END$$
DELIMITER ;
GRANT EXECUTE ON PROCEDURE sistema_practicas.sp_cambiar_estado_postulacion TO 'usr_proyecto'@'localhost';
