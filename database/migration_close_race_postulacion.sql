-- Replaces sp_postular so an application and an opportunity close cannot race.
-- The opportunity row is locked during submission and its status/date rechecked.
DROP PROCEDURE IF EXISTS sp_postular;
DELIMITER $$
CREATE PROCEDURE sp_postular(IN p_id_estudiante BIGINT UNSIGNED, IN p_id_oportunidad BIGINT UNSIGNED,
                             IN p_cv VARCHAR(500), IN p_carta TEXT)
BEGIN
  DECLARE v_estado SMALLINT UNSIGNED;
  DECLARE v_email VARCHAR(254);
  DECLARE v_titulo VARCHAR(200);
  DECLARE v_estado_oportunidad VARCHAR(30);
  DECLARE v_fecha_limite DATE;
  DECLARE v_disponible INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id_estado INTO v_estado FROM estados_postulacion WHERE codigo='recibida';
  SELECT estado,fecha_limite_postulacion INTO v_estado_oportunidad,v_fecha_limite
    FROM oportunidades WHERE id_oportunidad=p_id_oportunidad FOR UPDATE;
  IF v_estado_oportunidad<>'publicada' OR v_fecha_limite<CURRENT_DATE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La oportunidad ya no recibe postulaciones';
  END IF;
  SELECT COUNT(*),MAX(o.titulo),MAX(e.correo_contacto)
    INTO v_disponible,v_titulo,v_email
  FROM oportunidades o
  JOIN estudiantes est ON est.id_estudiante=p_id_estudiante
  JOIN usuarios u ON u.id_usuario=est.id_usuario
  JOIN empresas e ON e.id_empresa=o.id_empresa
  WHERE o.id_oportunidad=p_id_oportunidad AND o.estado='publicada'
    AND o.fecha_limite_postulacion>=CURRENT_DATE AND u.estado='activo';
  IF v_disponible=0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Estudiante u oportunidad no elegible';
  END IF;
  INSERT INTO postulaciones(id_estudiante,id_oportunidad,id_estado,cv_snapshot_ruta,carta_presentacion)
    VALUES(p_id_estudiante,p_id_oportunidad,v_estado,p_cv,p_carta);
  INSERT INTO cola_notificaciones(tipo,destinatario,asunto,cuerpo,id_postulacion)
    VALUES('nueva_postulacion',v_email,CONCAT('Nueva postulación: ',v_titulo),
      'Hay una nueva candidatura. Ingrese al sistema para revisar los detalles.',LAST_INSERT_ID());
  COMMIT;
END$$
DELIMITER ;
