-- Triggers de auditoría y protección contra borrado físico.
-- Ejecutar después de sql/database.sql.

USE sistema_practicas;
DELIMITER $$
DROP TRIGGER IF EXISTS trg_roles_ai$$
CREATE TRIGGER trg_roles_ai AFTER INSERT ON roles FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('roles',CAST(NEW.id_rol AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_roles_au$$
CREATE TRIGGER trg_roles_au AFTER UPDATE ON roles FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('roles',CAST(NEW.id_rol AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'descripcion',OLD.descripcion,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_roles_bd$$
CREATE TRIGGER trg_roles_bd BEFORE DELETE ON roles FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_permisos_ai$$
CREATE TRIGGER trg_permisos_ai AFTER INSERT ON permisos FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('permisos',CAST(NEW.id_permiso AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_permisos_au$$
CREATE TRIGGER trg_permisos_au AFTER UPDATE ON permisos FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('permisos',CAST(NEW.id_permiso AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'descripcion',OLD.descripcion,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_permisos_bd$$
CREATE TRIGGER trg_permisos_bd BEFORE DELETE ON permisos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_tipos_entidad_ai$$
CREATE TRIGGER trg_tipos_entidad_ai AFTER INSERT ON tipos_entidad FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('tipos_entidad',CAST(NEW.id_tipo_entidad AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_tipos_entidad_au$$
CREATE TRIGGER trg_tipos_entidad_au AFTER UPDATE ON tipos_entidad FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('tipos_entidad',CAST(NEW.id_tipo_entidad AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_tipos_entidad_bd$$
CREATE TRIGGER trg_tipos_entidad_bd BEFORE DELETE ON tipos_entidad FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_estados_ai$$
CREATE TRIGGER trg_estados_ai AFTER INSERT ON estados FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('estados',CAST(NEW.id_estado AS CHAR),'INSERT',JSON_OBJECT('tipo_entidad',NEW.tipo_entidad,'codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'color',NEW.color,'orden',NEW.orden,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_estados_au$$
CREATE TRIGGER trg_estados_au AFTER UPDATE ON estados FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('estados',CAST(NEW.id_estado AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('tipo_entidad',OLD.tipo_entidad,'codigo',OLD.codigo,'nombre',OLD.nombre,'descripcion',OLD.descripcion,'color',OLD.color,'orden',OLD.orden,'eliminado',OLD.eliminado),JSON_OBJECT('tipo_entidad',NEW.tipo_entidad,'codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'color',NEW.color,'orden',NEW.orden,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_estados_bd$$
CREATE TRIGGER trg_estados_bd BEFORE DELETE ON estados FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_usuarios_ai$$
CREATE TRIGGER trg_usuarios_ai AFTER INSERT ON usuarios FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('usuarios',CAST(NEW.id_usuario AS CHAR),'INSERT',JSON_OBJECT('id_rol',NEW.id_rol,'id_estado',NEW.id_estado,'usuario',NEW.usuario,'correo',NEW.correo,'nombres',NEW.nombres,'apellidos',NEW.apellidos,'telefono',NEW.telefono,'debe_cambiar_password',NEW.debe_cambiar_password,'ultimo_acceso',NEW.ultimo_acceso,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_usuarios_au$$
CREATE TRIGGER trg_usuarios_au AFTER UPDATE ON usuarios FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('usuarios',CAST(NEW.id_usuario AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_rol',OLD.id_rol,'id_estado',OLD.id_estado,'usuario',OLD.usuario,'correo',OLD.correo,'nombres',OLD.nombres,'apellidos',OLD.apellidos,'telefono',OLD.telefono,'debe_cambiar_password',OLD.debe_cambiar_password,'ultimo_acceso',OLD.ultimo_acceso,'eliminado',OLD.eliminado),JSON_OBJECT('id_rol',NEW.id_rol,'id_estado',NEW.id_estado,'usuario',NEW.usuario,'correo',NEW.correo,'nombres',NEW.nombres,'apellidos',NEW.apellidos,'telefono',NEW.telefono,'debe_cambiar_password',NEW.debe_cambiar_password,'ultimo_acceso',NEW.ultimo_acceso,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_usuarios_bd$$
CREATE TRIGGER trg_usuarios_bd BEFORE DELETE ON usuarios FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_sesiones_usuario_ai$$
CREATE TRIGGER trg_sesiones_usuario_ai AFTER INSERT ON sesiones_usuario FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('sesiones_usuario',CAST(NEW.id_sesion AS CHAR),'INSERT',JSON_OBJECT('id_usuario',NEW.id_usuario,'ip_origen',NEW.ip_origen,'creada_en',NEW.creada_en,'expira_en',NEW.expira_en,'ultima_actividad',NEW.ultima_actividad,'revocada_en',NEW.revocada_en,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_sesiones_usuario_au$$
CREATE TRIGGER trg_sesiones_usuario_au AFTER UPDATE ON sesiones_usuario FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('sesiones_usuario',CAST(NEW.id_sesion AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_usuario',OLD.id_usuario,'ip_origen',OLD.ip_origen,'creada_en',OLD.creada_en,'expira_en',OLD.expira_en,'ultima_actividad',OLD.ultima_actividad,'revocada_en',OLD.revocada_en,'eliminado',OLD.eliminado),JSON_OBJECT('id_usuario',NEW.id_usuario,'ip_origen',NEW.ip_origen,'creada_en',NEW.creada_en,'expira_en',NEW.expira_en,'ultima_actividad',NEW.ultima_actividad,'revocada_en',NEW.revocada_en,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_sesiones_usuario_bd$$
CREATE TRIGGER trg_sesiones_usuario_bd BEFORE DELETE ON sesiones_usuario FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_rol_permiso_ai$$
CREATE TRIGGER trg_rol_permiso_ai AFTER INSERT ON rol_permiso FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('rol_permiso',CONCAT(NEW.id_rol,':',NEW.id_permiso),'INSERT',JSON_OBJECT('id_rol',NEW.id_rol,'id_permiso',NEW.id_permiso,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_rol_permiso_au$$
CREATE TRIGGER trg_rol_permiso_au AFTER UPDATE ON rol_permiso FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('rol_permiso',CONCAT(NEW.id_rol,':',NEW.id_permiso),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_rol',OLD.id_rol,'id_permiso',OLD.id_permiso,'eliminado',OLD.eliminado),JSON_OBJECT('id_rol',NEW.id_rol,'id_permiso',NEW.id_permiso,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_rol_permiso_bd$$
CREATE TRIGGER trg_rol_permiso_bd BEFORE DELETE ON rol_permiso FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_areas_ai$$
CREATE TRIGGER trg_areas_ai AFTER INSERT ON areas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('areas',CAST(NEW.id_area AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_areas_au$$
CREATE TRIGGER trg_areas_au AFTER UPDATE ON areas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('areas',CAST(NEW.id_area AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'descripcion',OLD.descripcion,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_areas_bd$$
CREATE TRIGGER trg_areas_bd BEFORE DELETE ON areas FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_carreras_ai$$
CREATE TRIGGER trg_carreras_ai AFTER INSERT ON carreras FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('carreras',CAST(NEW.id_carrera AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_carreras_au$$
CREATE TRIGGER trg_carreras_au AFTER UPDATE ON carreras FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('carreras',CAST(NEW.id_carrera AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_carreras_bd$$
CREATE TRIGGER trg_carreras_bd BEFORE DELETE ON carreras FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_modalidades_ai$$
CREATE TRIGGER trg_modalidades_ai AFTER INSERT ON modalidades FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('modalidades',CAST(NEW.id_modalidad AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_modalidades_au$$
CREATE TRIGGER trg_modalidades_au AFTER UPDATE ON modalidades FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('modalidades',CAST(NEW.id_modalidad AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_modalidades_bd$$
CREATE TRIGGER trg_modalidades_bd BEFORE DELETE ON modalidades FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_transiciones_estado_ai$$
CREATE TRIGGER trg_transiciones_estado_ai AFTER INSERT ON transiciones_estado FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('transiciones_estado',CAST(NEW.id_transicion AS CHAR),'INSERT',JSON_OBJECT('entidad',NEW.entidad,'id_estado_origen',NEW.id_estado_origen,'id_estado_destino',NEW.id_estado_destino,'id_rol_permitido',NEW.id_rol_permitido,'requiere_comentario',NEW.requiere_comentario,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_transiciones_estado_au$$
CREATE TRIGGER trg_transiciones_estado_au AFTER UPDATE ON transiciones_estado FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('transiciones_estado',CAST(NEW.id_transicion AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('entidad',OLD.entidad,'id_estado_origen',OLD.id_estado_origen,'id_estado_destino',OLD.id_estado_destino,'id_rol_permitido',OLD.id_rol_permitido,'requiere_comentario',OLD.requiere_comentario,'eliminado',OLD.eliminado),JSON_OBJECT('entidad',NEW.entidad,'id_estado_origen',NEW.id_estado_origen,'id_estado_destino',NEW.id_estado_destino,'id_rol_permitido',NEW.id_rol_permitido,'requiere_comentario',NEW.requiere_comentario,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_transiciones_estado_bd$$
CREATE TRIGGER trg_transiciones_estado_bd BEFORE DELETE ON transiciones_estado FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_estudiantes_ai$$
CREATE TRIGGER trg_estudiantes_ai AFTER INSERT ON estudiantes FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('estudiantes',CAST(NEW.id_estudiante AS CHAR),'INSERT',JSON_OBJECT('id_usuario',NEW.id_usuario,'id_carrera',NEW.id_carrera,'carnet',NEW.carnet,'semestre',NEW.semestre,'universidad',NEW.universidad,'cv_nombre',NEW.cv_nombre,'cv_mime',NEW.cv_mime,'cv_eliminado',NEW.cv_eliminado,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_estudiantes_au$$
CREATE TRIGGER trg_estudiantes_au AFTER UPDATE ON estudiantes FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('estudiantes',CAST(NEW.id_estudiante AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_usuario',OLD.id_usuario,'id_carrera',OLD.id_carrera,'carnet',OLD.carnet,'semestre',OLD.semestre,'universidad',OLD.universidad,'cv_nombre',OLD.cv_nombre,'cv_mime',OLD.cv_mime,'cv_eliminado',OLD.cv_eliminado,'eliminado',OLD.eliminado),JSON_OBJECT('id_usuario',NEW.id_usuario,'id_carrera',NEW.id_carrera,'carnet',NEW.carnet,'semestre',NEW.semestre,'universidad',NEW.universidad,'cv_nombre',NEW.cv_nombre,'cv_mime',NEW.cv_mime,'cv_eliminado',NEW.cv_eliminado,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_estudiantes_bd$$
CREATE TRIGGER trg_estudiantes_bd BEFORE DELETE ON estudiantes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_empresas_ai$$
CREATE TRIGGER trg_empresas_ai AFTER INSERT ON empresas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('empresas',CAST(NEW.id_empresa AS CHAR),'INSERT',JSON_OBJECT('id_usuario',NEW.id_usuario,'id_estado_revision',NEW.id_estado_revision,'nombre_legal',NEW.nombre_legal,'nombre_comercial',NEW.nombre_comercial,'identificacion_fiscal',NEW.identificacion_fiscal,'sector',NEW.sector,'correo_contacto',NEW.correo_contacto,'telefono',NEW.telefono,'sitio_web',NEW.sitio_web,'direccion',NEW.direccion,'observaciones_coordinacion',NEW.observaciones_coordinacion,'revisada_en',NEW.revisada_en,'id_administrador_revision',NEW.id_administrador_revision,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_empresas_au$$
CREATE TRIGGER trg_empresas_au AFTER UPDATE ON empresas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('empresas',CAST(NEW.id_empresa AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_usuario',OLD.id_usuario,'id_estado_revision',OLD.id_estado_revision,'nombre_legal',OLD.nombre_legal,'nombre_comercial',OLD.nombre_comercial,'identificacion_fiscal',OLD.identificacion_fiscal,'sector',OLD.sector,'correo_contacto',OLD.correo_contacto,'telefono',OLD.telefono,'sitio_web',OLD.sitio_web,'direccion',OLD.direccion,'observaciones_coordinacion',OLD.observaciones_coordinacion,'revisada_en',OLD.revisada_en,'id_administrador_revision',OLD.id_administrador_revision,'eliminado',OLD.eliminado),JSON_OBJECT('id_usuario',NEW.id_usuario,'id_estado_revision',NEW.id_estado_revision,'nombre_legal',NEW.nombre_legal,'nombre_comercial',NEW.nombre_comercial,'identificacion_fiscal',NEW.identificacion_fiscal,'sector',NEW.sector,'correo_contacto',NEW.correo_contacto,'telefono',NEW.telefono,'sitio_web',NEW.sitio_web,'direccion',NEW.direccion,'observaciones_coordinacion',NEW.observaciones_coordinacion,'revisada_en',NEW.revisada_en,'id_administrador_revision',NEW.id_administrador_revision,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_empresas_bd$$
CREATE TRIGGER trg_empresas_bd BEFORE DELETE ON empresas FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_practicas_ai$$
CREATE TRIGGER trg_practicas_ai AFTER INSERT ON practicas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('practicas',CAST(NEW.id_practica AS CHAR),'INSERT',JSON_OBJECT('id_empresa',NEW.id_empresa,'id_estado',NEW.id_estado,'id_area',NEW.id_area,'id_modalidad',NEW.id_modalidad,'titulo',NEW.titulo,'descripcion',NEW.descripcion,'requisitos',NEW.requisitos,'ubicacion',NEW.ubicacion,'cupo_maximo',NEW.cupo_maximo,'fecha_limite_postulacion',NEW.fecha_limite_postulacion,'publicada_en',NEW.publicada_en,'observaciones_coordinacion',NEW.observaciones_coordinacion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_practicas_au$$
CREATE TRIGGER trg_practicas_au AFTER UPDATE ON practicas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('practicas',CAST(NEW.id_practica AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_empresa',OLD.id_empresa,'id_estado',OLD.id_estado,'id_area',OLD.id_area,'id_modalidad',OLD.id_modalidad,'titulo',OLD.titulo,'descripcion',OLD.descripcion,'requisitos',OLD.requisitos,'ubicacion',OLD.ubicacion,'cupo_maximo',OLD.cupo_maximo,'fecha_limite_postulacion',OLD.fecha_limite_postulacion,'publicada_en',OLD.publicada_en,'observaciones_coordinacion',OLD.observaciones_coordinacion,'eliminado',OLD.eliminado),JSON_OBJECT('id_empresa',NEW.id_empresa,'id_estado',NEW.id_estado,'id_area',NEW.id_area,'id_modalidad',NEW.id_modalidad,'titulo',NEW.titulo,'descripcion',NEW.descripcion,'requisitos',NEW.requisitos,'ubicacion',NEW.ubicacion,'cupo_maximo',NEW.cupo_maximo,'fecha_limite_postulacion',NEW.fecha_limite_postulacion,'publicada_en',NEW.publicada_en,'observaciones_coordinacion',NEW.observaciones_coordinacion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_practicas_bd$$
CREATE TRIGGER trg_practicas_bd BEFORE DELETE ON practicas FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_postulaciones_ai$$
CREATE TRIGGER trg_postulaciones_ai AFTER INSERT ON postulaciones FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('postulaciones',CAST(NEW.id_postulacion AS CHAR),'INSERT',JSON_OBJECT('id_estudiante',NEW.id_estudiante,'id_practica',NEW.id_practica,'id_estado',NEW.id_estado,'carta_presentacion',NEW.carta_presentacion,'comentario',NEW.comentario,'fecha_postulacion',NEW.fecha_postulacion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_postulaciones_au$$
CREATE TRIGGER trg_postulaciones_au AFTER UPDATE ON postulaciones FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('postulaciones',CAST(NEW.id_postulacion AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_estudiante',OLD.id_estudiante,'id_practica',OLD.id_practica,'id_estado',OLD.id_estado,'carta_presentacion',OLD.carta_presentacion,'comentario',OLD.comentario,'fecha_postulacion',OLD.fecha_postulacion,'eliminado',OLD.eliminado),JSON_OBJECT('id_estudiante',NEW.id_estudiante,'id_practica',NEW.id_practica,'id_estado',NEW.id_estado,'carta_presentacion',NEW.carta_presentacion,'comentario',NEW.comentario,'fecha_postulacion',NEW.fecha_postulacion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_postulaciones_bd$$
CREATE TRIGGER trg_postulaciones_bd BEFORE DELETE ON postulaciones FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_historial_estados_ai$$
CREATE TRIGGER trg_historial_estados_ai AFTER INSERT ON historial_estados FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('historial_estados',CAST(NEW.id_historial AS CHAR),'INSERT',JSON_OBJECT('entidad',NEW.entidad,'id_registro',NEW.id_registro,'id_estado_anterior',NEW.id_estado_anterior,'id_estado_nuevo',NEW.id_estado_nuevo,'id_usuario',NEW.id_usuario,'comentario',NEW.comentario,'fecha_hora',NEW.fecha_hora,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_historial_estados_au$$
CREATE TRIGGER trg_historial_estados_au AFTER UPDATE ON historial_estados FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('historial_estados',CAST(NEW.id_historial AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('entidad',OLD.entidad,'id_registro',OLD.id_registro,'id_estado_anterior',OLD.id_estado_anterior,'id_estado_nuevo',OLD.id_estado_nuevo,'id_usuario',OLD.id_usuario,'comentario',OLD.comentario,'fecha_hora',OLD.fecha_hora,'eliminado',OLD.eliminado),JSON_OBJECT('entidad',NEW.entidad,'id_registro',NEW.id_registro,'id_estado_anterior',NEW.id_estado_anterior,'id_estado_nuevo',NEW.id_estado_nuevo,'id_usuario',NEW.id_usuario,'comentario',NEW.comentario,'fecha_hora',NEW.fecha_hora,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_historial_estados_bd$$
CREATE TRIGGER trg_historial_estados_bd BEFORE DELETE ON historial_estados FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_configuracion_ai$$
CREATE TRIGGER trg_configuracion_ai AFTER INSERT ON configuracion FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('configuracion',CAST(NEW.id_configuracion AS CHAR),'INSERT',JSON_OBJECT('clave',NEW.clave,'valor',NEW.valor,'tipo',NEW.tipo,'descripcion',NEW.descripcion,'editable',NEW.editable,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_configuracion_au$$
CREATE TRIGGER trg_configuracion_au AFTER UPDATE ON configuracion FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('configuracion',CAST(NEW.id_configuracion AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('clave',OLD.clave,'valor',OLD.valor,'tipo',OLD.tipo,'descripcion',OLD.descripcion,'editable',OLD.editable,'eliminado',OLD.eliminado),JSON_OBJECT('clave',NEW.clave,'valor',NEW.valor,'tipo',NEW.tipo,'descripcion',NEW.descripcion,'editable',NEW.editable,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_configuracion_bd$$
CREATE TRIGGER trg_configuracion_bd BEFORE DELETE ON configuracion FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_mensajes_ai$$
CREATE TRIGGER trg_mensajes_ai AFTER INSERT ON mensajes FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('mensajes',CAST(NEW.id_mensaje AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'texto',NEW.texto,'tipo',NEW.tipo,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_mensajes_au$$
CREATE TRIGGER trg_mensajes_au AFTER UPDATE ON mensajes FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('mensajes',CAST(NEW.id_mensaje AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'texto',OLD.texto,'tipo',OLD.tipo,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'texto',NEW.texto,'tipo',NEW.tipo,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_mensajes_bd$$
CREATE TRIGGER trg_mensajes_bd BEFORE DELETE ON mensajes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_intentos_login_ai$$
CREATE TRIGGER trg_intentos_login_ai AFTER INSERT ON intentos_login FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('intentos_login',CAST(NEW.id_intento AS CHAR),'INSERT',JSON_OBJECT('identificador',NEW.identificador,'ip_cliente',NEW.ip_cliente,'exitoso',NEW.exitoso,'ocurrido_en',NEW.ocurrido_en,'bloqueado_hasta',NEW.bloqueado_hasta,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_intentos_login_au$$
CREATE TRIGGER trg_intentos_login_au AFTER UPDATE ON intentos_login FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('intentos_login',CAST(NEW.id_intento AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('identificador',OLD.identificador,'ip_cliente',OLD.ip_cliente,'exitoso',OLD.exitoso,'ocurrido_en',OLD.ocurrido_en,'bloqueado_hasta',OLD.bloqueado_hasta,'eliminado',OLD.eliminado),JSON_OBJECT('identificador',NEW.identificador,'ip_cliente',NEW.ip_cliente,'exitoso',NEW.exitoso,'ocurrido_en',NEW.ocurrido_en,'bloqueado_hasta',NEW.bloqueado_hasta,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_intentos_login_bd$$
CREATE TRIGGER trg_intentos_login_bd BEFORE DELETE ON intentos_login FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_bitacora_no_update$$
CREATE TRIGGER trg_bitacora_no_update BEFORE UPDATE ON bitacora FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La bitácora es de solo inserción';
END$$
DROP TRIGGER IF EXISTS trg_bitacora_no_delete$$
CREATE TRIGGER trg_bitacora_no_delete BEFORE DELETE ON bitacora FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La bitácora no permite borrado físico';
END$$
DELIMITER ;



