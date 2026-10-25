
CREATE DATABASE IF NOT EXISTS sistema_practicas
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_practicas;
SET NAMES utf8mb4;

-- Key updates cascade to keep linked IDs consistent. PHP applies logical removals
-- so an opportunity or account deletion cannot erase application/audit history.

CREATE TABLE roles (
  id_rol SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL UNIQUE,
  descripcion VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE permisos (
  id_permiso SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(80) NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE roles_permisos (
  id_rol SMALLINT UNSIGNED NOT NULL,
  id_permiso SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (id_rol,id_permiso),
  CONSTRAINT fk_rp_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (id_permiso) REFERENCES permisos(id_permiso) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE usuarios (
  id_usuario BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(40) NOT NULL UNIQUE,
  id_rol SMALLINT UNSIGNED NOT NULL,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  telefono VARCHAR(8),
  correo VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  estado ENUM('activo','pendiente','requiere_cambios','rechazada','baneado','borrado') NOT NULL DEFAULT 'pendiente',
  CONSTRAINT chk_usuario_telefono CHECK (telefono IS NULL OR telefono REGEXP '^[0-9]{8}$'),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE estudiantes (
  id_estudiante BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario BIGINT UNSIGNED NOT NULL UNIQUE,
  carnet VARCHAR(40) NOT NULL UNIQUE,
  carrera VARCHAR(150) NOT NULL,
  semestre VARCHAR(40),
  universidad VARCHAR(180) NOT NULL,
  cv_nombre VARCHAR(255),
  cv_ruta VARCHAR(500),
  CONSTRAINT fk_estudiante_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE administradores (
  id_administrador BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario BIGINT UNSIGNED NOT NULL UNIQUE,
  cargo VARCHAR(100),
  CONSTRAINT fk_admin_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE empresas (
  id_empresa BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_legal VARCHAR(180) NOT NULL,
  nombre_comercial VARCHAR(180),
  identificacion_fiscal VARCHAR(50) UNIQUE,
  sector VARCHAR(100),
  descripcion TEXT,
  correo_contacto VARCHAR(254) NOT NULL,
  telefono VARCHAR(8),
  sitio_web VARCHAR(255),
  direccion VARCHAR(255),
  observaciones_coordinacion TEXT NULL,
  revisada_en DATETIME NULL,
  id_administrador_revision BIGINT UNSIGNED NULL,
  id_usuario BIGINT UNSIGNED NOT NULL UNIQUE,
  CONSTRAINT fk_empresa_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_empresa_admin_revision FOREIGN KEY (id_administrador_revision) REFERENCES administradores(id_administrador)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_empresa_telefono CHECK (telefono IS NULL OR telefono REGEXP '^[0-9]{8}$')
) ENGINE=InnoDB;

CREATE TABLE oportunidades (
  id_oportunidad BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_empresa BIGINT UNSIGNED NOT NULL,
  id_administrador BIGINT UNSIGNED NULL,
  titulo VARCHAR(200) NOT NULL,
  descripcion TEXT NOT NULL,
  requisitos TEXT,
  ubicacion VARCHAR(200),
  modalidad ENUM('presencial','remota','hibrida') NOT NULL DEFAULT 'presencial',
  tipo_practica VARCHAR(100),
  remuneracion DECIMAL(12,2),
  moneda CHAR(3) NOT NULL DEFAULT 'GTQ',
  vacantes SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_inicio DATE,
  fecha_fin DATE,
  fecha_limite_postulacion DATE NOT NULL,
  estado ENUM('pendiente_revision','requiere_cambios','borrador','publicada','rechazada','pausada','cerrada','borrado') NOT NULL DEFAULT 'pendiente_revision',
  observaciones_coordinacion TEXT NULL,
  publicada_en DATETIME NULL,
  CONSTRAINT fk_oportunidad_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_oportunidad_admin FOREIGN KEY (id_administrador) REFERENCES administradores(id_administrador)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_oportunidad_vacantes CHECK (vacantes > 0),
  CONSTRAINT chk_oportunidad_remuneracion CHECK (remuneracion IS NULL OR remuneracion >= 0),
  CONSTRAINT chk_oportunidad_fechas CHECK (fecha_fin IS NULL OR fecha_inicio IS NULL OR fecha_fin >= fecha_inicio)
) ENGINE=InnoDB;

-- Cada empresa queda vinculada a la cuenta que la registra mediante empresas.id_usuario.
-- Cada palabra queda asociada directamente a su oportunidad.
-- Ejemplos de nombre_busqueda: '3 meses', 'computo', 'principiantes'.
CREATE TABLE palabras_clave (
  id_palabra_clave BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_oportunidad BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  nombre_busqueda VARCHAR(100) NOT NULL,
  estado ENUM('activo','borrado') NOT NULL DEFAULT 'activo',
  categoria ENUM('duracion','area','nivel','modalidad','habilidad','otro') NOT NULL DEFAULT 'otro',
  UNIQUE KEY uq_palabra_oportunidad (id_oportunidad, nombre_busqueda),
  CONSTRAINT fk_palabra_oportunidad FOREIGN KEY (id_oportunidad) REFERENCES oportunidades(id_oportunidad)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE estados_postulacion (
  id_estado SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  nombre VARCHAR(60) NOT NULL,
  descripcion VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE postulaciones (
  id_postulacion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_estudiante BIGINT UNSIGNED NOT NULL,
  id_oportunidad BIGINT UNSIGNED NOT NULL,
  id_estado SMALLINT UNSIGNED NOT NULL,
  cv_snapshot_ruta VARCHAR(500),
  carta_presentacion TEXT,
  observaciones TEXT,
  fecha_postulacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_postulacion_estudiante_oportunidad (id_estudiante, id_oportunidad),
  CONSTRAINT fk_postulacion_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_postulacion_oportunidad FOREIGN KEY (id_oportunidad) REFERENCES oportunidades(id_oportunidad)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_postulacion_estado FOREIGN KEY (id_estado) REFERENCES estados_postulacion(id_estado)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Audit snapshots are JSON so each event stores only the changed fields and values.
CREATE TABLE bitacora (
  id_bitacora BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario BIGINT UNSIGNED NULL,
  accion VARCHAR(60) NOT NULL,
  tabla_afectada VARCHAR(64) NOT NULL,
  id_registro BIGINT UNSIGNED NULL,
  datos_anteriores JSON NULL,
  datos_nuevos JSON NULL,
  ip_origen VARCHAR(45),
  fecha_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bitacora_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- PHP procesa esta cola y envía el correo. intentos/error permiten reintentar y diagnosticar fallos.
CREATE TABLE cola_notificaciones (
  id_notificacion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(50) NOT NULL,
  destinatario VARCHAR(254) NOT NULL,
  asunto VARCHAR(200) NOT NULL,
  cuerpo TEXT NOT NULL,
  id_postulacion BIGINT UNSIGNED NULL,
  estado ENUM('pendiente','procesando','enviada','fallida') NOT NULL DEFAULT 'pendiente',
  intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  error_ultimo TEXT,
  procesado_en DATETIME NULL,
  CONSTRAINT fk_notif_postulacion FOREIGN KEY (id_postulacion) REFERENCES postulaciones(id_postulacion)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Se guarda el hash del token de sesión; la cookie lleva el token original.
CREATE TABLE sesiones_usuario (
  id_sesion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  ip_origen VARCHAR(45),
  user_agent VARCHAR(500),
  creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_en DATETIME NOT NULL,
  revocada_en DATETIME NULL,
  CONSTRAINT fk_sesion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO roles (nombre, descripcion) VALUES
 ('usuario','Cuenta básica para consultar oportunidades públicas'),
 ('administrador','Revisa oportunidades, empresas y postulaciones'),
 ('superadministrador','Administra roles, cuentas y configuración del sistema'),
 ('estudiante','Consulta oportunidades y administra sus postulaciones'),
 ('empresa','Cuenta de contacto de una empresa');

INSERT INTO estados_postulacion (codigo,nombre,descripcion) VALUES
 ('recibida','Recibida','Postulación enviada y pendiente de revisión'),
 ('en_revision','En revisión','La empresa o coordinación está evaluando'),
 ('entrevista','Entrevista','Se programó una entrevista'),
 ('aceptada','Aceptada','Postulación aceptada'),
 ('rechazada','Rechazada','Postulación no seleccionada'),
 ('retirada','Retirada','El estudiante retiró su postulación'),
 ('borrado','Borrado','Registro retirado lógicamente sin borrar datos');

INSERT INTO permisos (codigo,nombre,descripcion) VALUES
 ('usuarios.gestionar','Gestionar usuarios','Crear, consultar, modificar y eliminar lógicamente usuarios'),
 ('empresas.gestionar','Gestionar empresas','Registrar y modificar empresas'),
 ('oportunidades.consultar','Consultar oportunidades','Ver oportunidades publicadas'),
 ('oportunidades.gestionar','Gestionar oportunidades','Crear, modificar, publicar, pausar y cerrar oportunidades'),
 ('postulaciones.crear','Postularse','Crear una postulación a una oportunidad'),
 ('postulaciones.consultar_propias','Consultar mis postulaciones','Ver el seguimiento de las postulaciones propias'),
 ('postulaciones.consultar_todas','Consultar postulaciones','Ver postulaciones de todas las oportunidades'),
 ('postulaciones.cambiar_estado','Cambiar estado de postulación','Actualizar estados y registrar comentarios'),
 ('bitacora.consultar','Consultar bitácora','Consultar el registro de auditoría');

INSERT INTO permisos (codigo,nombre,descripcion) VALUES
 ('empresas.aprobar','Aprobar empresas','Validar solicitudes de registro de empresas'),
 ('empresas.perfil_propio','Editar perfil empresarial propio','Corregir perfil y reenviarlo a coordinación'),
 ('usuarios.perfil_propio','Editar perfil propio','Administrar los datos del perfil estudiantil propio'),
 ('oportunidades.gestionar_propias','Gestionar oportunidades propias','Crear y modificar oportunidades de la empresa autenticada'),
 ('oportunidades.aprobar','Revisar oportunidades','Publicar o rechazar oportunidades pendientes'),
 ('usuarios.roles.gestionar','Gestionar perfiles','Asignar perfiles a usuarios desde superadministración');

INSERT INTO roles_permisos (id_rol,id_permiso)
SELECT r.id_rol,p.id_permiso FROM roles r CROSS JOIN permisos p
WHERE (r.nombre='administrador' AND p.codigo IN ('empresas.aprobar','oportunidades.consultar','oportunidades.aprobar','postulaciones.consultar_todas','bitacora.consultar'))
   OR (r.nombre='superadministrador' AND p.codigo IN ('usuarios.gestionar','empresas.gestionar','empresas.aprobar','empresas.perfil_propio','usuarios.perfil_propio','oportunidades.consultar','oportunidades.gestionar','oportunidades.gestionar_propias','oportunidades.aprobar','postulaciones.crear','postulaciones.consultar_propias','postulaciones.consultar_todas','bitacora.consultar','usuarios.roles.gestionar'))
   OR (r.nombre='usuario' AND p.codigo IN ('oportunidades.consultar'))
   OR (r.nombre='estudiante' AND p.codigo IN ('oportunidades.consultar','postulaciones.crear','postulaciones.consultar_propias','usuarios.perfil_propio'))
   OR (r.nombre='empresa' AND p.codigo IN ('oportunidades.consultar','empresas.perfil_propio','oportunidades.gestionar_propias','postulaciones.consultar_todas','postulaciones.cambiar_estado'));

-- Vistas de lectura para la aplicación.
-- Búsqueda por etiqueta (consulta preparada):
-- SELECT o.* FROM vw_oportunidades_publicadas o
-- WHERE EXISTS (SELECT 1 FROM palabras_clave pc
--   WHERE pc.id_oportunidad=o.id_oportunidad AND pc.nombre_busqueda=LOWER(TRIM(?)));
-- Para varias palabras, agregar un EXISTS con su propio parámetro por cada palabra.
CREATE VIEW vw_oportunidades_publicadas AS
SELECT o.id_oportunidad,o.titulo,o.descripcion,o.requisitos,o.ubicacion,
       o.modalidad,o.tipo_practica,o.remuneracion,o.moneda,o.vacantes,
       o.fecha_inicio,o.fecha_fin,o.fecha_limite_postulacion,o.publicada_en,
       e.id_empresa,COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,
       (SELECT GROUP_CONCAT(pc.nombre ORDER BY pc.nombre SEPARATOR ', ')
          FROM palabras_clave pc WHERE pc.id_oportunidad=o.id_oportunidad AND pc.estado='activo') AS palabras_clave
FROM oportunidades o JOIN empresas e ON e.id_empresa=o.id_empresa
WHERE o.estado='publicada' AND o.fecha_limite_postulacion >= CURRENT_DATE;
CREATE VIEW vw_roles_catalogo AS SELECT id_rol,nombre,descripcion FROM roles;

CREATE VIEW vw_seguimiento_postulaciones AS
SELECT p.id_postulacion,p.id_estudiante,est.id_usuario,p.id_oportunidad,p.fecha_postulacion,
       p.carta_presentacion,p.observaciones,
       ep.codigo AS estado_codigo,ep.nombre AS estado,
       o.titulo AS oportunidad,o.modalidad,o.ubicacion,
       COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa
FROM postulaciones p JOIN estudiantes est ON est.id_estudiante=p.id_estudiante
JOIN estados_postulacion ep ON ep.id_estado=p.id_estado
JOIN oportunidades o ON o.id_oportunidad=p.id_oportunidad
JOIN empresas e ON e.id_empresa=o.id_empresa
WHERE ep.codigo <> 'borrado';
CREATE VIEW vw_seguimiento_historial AS
SELECT b.id_bitacora,b.id_registro AS id_postulacion,b.accion,b.fecha_evento,
       JSON_UNQUOTE(JSON_EXTRACT(b.datos_anteriores,'$.id_estado')) AS old_id,
       JSON_UNQUOTE(JSON_EXTRACT(b.datos_nuevos,'$.id_estado')) AS new_id,
       JSON_UNQUOTE(JSON_EXTRACT(b.datos_nuevos,'$.comentario')) AS comentario
FROM bitacora b WHERE b.tabla_afectada='postulaciones' AND b.accion IN ('INSERT','CAMBIO_ESTADO');
CREATE VIEW vw_estados_postulacion AS SELECT id_estado,codigo,nombre,descripcion FROM estados_postulacion;
CREATE VIEW vw_estudiante_perfil AS
SELECT e.id_estudiante,e.id_usuario,e.carnet,e.carrera,e.semestre,e.universidad,e.cv_nombre,e.cv_ruta,
       u.nombres,u.apellidos,u.correo,u.telefono
FROM estudiantes e JOIN usuarios u ON u.id_usuario=e.id_usuario;

CREATE VIEW vw_usuario_autenticacion AS
SELECT u.id_usuario,u.usuario,u.nombres,u.apellidos,u.correo,u.password_hash,u.estado,r.nombre AS role
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol;
CREATE VIEW vw_usuario_acceso AS
SELECT u.id_usuario,u.usuario,u.nombres,u.apellidos,u.correo,u.estado,r.nombre AS role
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol;

-- Session validation reads a narrow SQL view; PHP writes session registration/revocation with prepared statements.
CREATE VIEW vw_sesion_acceso AS
SELECT s.id_usuario,s.token_hash,s.revocada_en,s.expira_en,
       u.usuario,u.nombres,u.apellidos,u.correo,u.estado,r.nombre AS role
FROM sesiones_usuario s JOIN usuarios u ON u.id_usuario=s.id_usuario
JOIN roles r ON r.id_rol=u.id_rol;
CREATE VIEW vw_usuario_permisos AS
SELECT u.id_usuario,r.nombre AS role,p.codigo AS permiso
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol
JOIN roles_permisos rp ON rp.id_rol=r.id_rol JOIN permisos p ON p.id_permiso=rp.id_permiso;
CREATE VIEW vw_admin_oportunidades AS
SELECT o.id_oportunidad,o.id_empresa,o.titulo,o.descripcion,o.requisitos,o.ubicacion,o.modalidad,o.tipo_practica,
       o.remuneracion,o.moneda,o.vacantes,o.fecha_inicio,o.fecha_fin,o.fecha_limite_postulacion,o.estado,o.observaciones_coordinacion,o.publicada_en,
       COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,
       (SELECT GROUP_CONCAT(pc.nombre ORDER BY pc.nombre SEPARATOR ', ') FROM palabras_clave pc WHERE pc.id_oportunidad=o.id_oportunidad AND pc.estado='activo') AS palabras_clave
FROM oportunidades o JOIN empresas e ON e.id_empresa=o.id_empresa;
CREATE VIEW vw_admin_postulaciones AS
SELECT p.id_postulacion,p.fecha_postulacion,p.carta_presentacion,p.observaciones,ep.codigo AS estado,ep.nombre AS estado_nombre,
       o.id_oportunidad,o.titulo AS oportunidad,e.id_empresa,COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,
       est.id_estudiante,est.carnet,est.carrera,est.semestre,est.universidad,est.cv_nombre,
       u.id_usuario,u.nombres,u.apellidos,u.correo,u.telefono,e.correo_contacto,est.cv_ruta
FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado JOIN oportunidades o ON o.id_oportunidad=p.id_oportunidad
JOIN empresas e ON e.id_empresa=o.id_empresa JOIN estudiantes est ON est.id_estudiante=p.id_estudiante
JOIN usuarios u ON u.id_usuario=est.id_usuario WHERE ep.codigo<>'borrado';
CREATE VIEW vw_empresa_postulaciones AS
SELECT p.*,o.titulo AS oportunidad,COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,e.id_usuario AS id_usuario_empresa,
       u.nombres,u.apellidos,u.correo,u.telefono,est.carnet,est.carrera,est.semestre,est.universidad,est.cv_nombre,est.cv_ruta,
       u.id_usuario AS id_usuario_estudiante,ep.codigo AS estado_codigo,ep.nombre AS estado
FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado JOIN oportunidades o ON o.id_oportunidad=p.id_oportunidad
JOIN empresas e ON e.id_empresa=o.id_empresa JOIN estudiantes est ON est.id_estudiante=p.id_estudiante
JOIN usuarios u ON u.id_usuario=est.id_usuario WHERE ep.codigo<>'borrado';
CREATE VIEW vw_admin_empresas_revision AS
SELECT e.id_empresa,e.id_usuario,e.nombre_legal,e.nombre_comercial,e.identificacion_fiscal,e.sector,e.descripcion,e.correo_contacto,
       e.telefono,e.sitio_web,e.direccion,u.nombres,u.apellidos,u.correo,u.estado
FROM empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario JOIN roles r ON r.id_rol=u.id_rol WHERE r.nombre='empresa';
CREATE VIEW vw_admin_usuarios_roles AS
SELECT u.id_usuario,u.nombres,u.apellidos,u.usuario,u.correo,u.estado,r.nombre AS role
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol;
CREATE VIEW vw_admin_bitacora AS
SELECT b.id_bitacora,b.fecha_evento,b.accion,b.tabla_afectada,b.id_registro,b.datos_anteriores,b.datos_nuevos,b.ip_origen,
       CONCAT(u.nombres,' ',u.apellidos) AS actor
FROM bitacora b LEFT JOIN usuarios u ON u.id_usuario=b.id_usuario;
CREATE VIEW vw_empresa_perfil AS
SELECT e.*,u.estado AS estado_cuenta,u.nombres AS usuario_nombres,u.apellidos AS usuario_apellidos,u.correo AS usuario_correo
FROM empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario;
CREATE VIEW vw_empresa_oportunidades AS
SELECT o.*,COALESCE(NULLIF(o.estado,''),'pendiente_revision') AS estado_panel,
       (SELECT GROUP_CONCAT(pc.nombre ORDER BY pc.nombre SEPARATOR ', ') FROM palabras_clave pc WHERE pc.id_oportunidad=o.id_oportunidad AND pc.estado='activo') AS palabras_clave,
       (SELECT COUNT(*) FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado WHERE p.id_oportunidad=o.id_oportunidad AND ep.codigo<>'borrado') AS numero_postulaciones,
       (SELECT COUNT(*) FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado WHERE p.id_oportunidad=o.id_oportunidad AND ep.codigo='aceptada') AS perfiles_aceptados,
       (SELECT COUNT(*) FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado WHERE p.id_oportunidad=o.id_oportunidad AND ep.codigo NOT IN ('aceptada','rechazada','retirada','borrado')) AS perfiles_pendientes,
       e.id_usuario AS id_usuario_empresa
FROM oportunidades o JOIN empresas e ON e.id_empresa=o.id_empresa WHERE COALESCE(o.estado,'')<>'borrado';
CREATE VIEW vw_empresa_notificaciones AS
SELECT n.id_notificacion,n.asunto,n.estado,n.intentos,n.error_ultimo,n.procesado_en,e.id_usuario AS id_usuario_empresa,
       o.id_oportunidad,o.titulo AS oportunidad
FROM cola_notificaciones n JOIN postulaciones p ON p.id_postulacion=n.id_postulacion
JOIN oportunidades o ON o.id_oportunidad=p.id_oportunidad JOIN empresas e ON e.id_empresa=o.id_empresa
WHERE n.tipo='perfiles_aceptados';

-- Antes de escribir, PHP define estas variables en la misma conexión:
-- SET @app_user_id = ID del usuario autenticado; SET @app_ip = IP validada;
-- Para actualizar estado: SET @app_comentario = 'Comentario opcional'.
DELIMITER $$
-- En instalaciones nuevas el CHECK de ocho dígitos protege campos telefónicos;
-- en bases heredadas esta validación complementa PHP sin truncar datos previos.
CREATE TRIGGER trg_usuario_phone_validate_bi BEFORE INSERT ON usuarios FOR EACH ROW
BEGIN
  IF NEW.telefono IS NOT NULL AND NEW.telefono NOT REGEXP '^[0-9]{8}$' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El teléfono debe contener exactamente ocho dígitos';
  END IF;
END$$
CREATE TRIGGER trg_usuario_phone_validate_bu BEFORE UPDATE ON usuarios FOR EACH ROW
BEGIN
  IF NEW.telefono IS NOT NULL AND NEW.telefono <> OLD.telefono AND NEW.telefono NOT REGEXP '^[0-9]{8}$' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El teléfono debe contener exactamente ocho dígitos';
  END IF;
END$$
CREATE TRIGGER trg_empresa_phone_validate_bi BEFORE INSERT ON empresas FOR EACH ROW
BEGIN
  IF NEW.telefono IS NOT NULL AND NEW.telefono NOT REGEXP '^[0-9]{8}$' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El teléfono debe contener exactamente ocho dígitos';
  END IF;
END$$
CREATE TRIGGER trg_empresa_phone_validate_bu BEFORE UPDATE ON empresas FOR EACH ROW
BEGIN
  IF NEW.telefono IS NOT NULL AND NEW.telefono <> OLD.telefono AND NEW.telefono NOT REGEXP '^[0-9]{8}$' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El teléfono debe contener exactamente ocho dígitos';
  END IF;
END$$
CREATE TRIGGER trg_postulaciones_ai AFTER INSERT ON postulaciones FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','postulaciones',NEW.id_postulacion,NULL,
    JSON_OBJECT('id_estudiante',NEW.id_estudiante,'id_oportunidad',NEW.id_oportunidad,
                'id_estado',NEW.id_estado,'comentario','Postulación recibida'),@app_ip);
END$$

CREATE TRIGGER trg_postulaciones_au AFTER UPDATE ON postulaciones FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),IF(OLD.id_estado <> NEW.id_estado,'CAMBIO_ESTADO','UPDATE'),
    'postulaciones',NEW.id_postulacion,
    JSON_OBJECT('id_estado',OLD.id_estado,'observaciones',OLD.observaciones),
    JSON_OBJECT('id_estado',NEW.id_estado,'observaciones',NEW.observaciones,
                'comentario',NULLIF(@app_comentario,'')),@app_ip);
END$$

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

-- The audit trail covers profile and company changes without recording password hashes or session secrets.
CREATE TRIGGER trg_usuarios_ai AFTER INSERT ON usuarios FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','usuarios',NEW.id_usuario,
    JSON_OBJECT('id_rol',NEW.id_rol,'nombres',NEW.nombres,'apellidos',NEW.apellidos,'correo',NEW.correo,'estado',NEW.estado),@app_ip);
END$$
CREATE TRIGGER trg_usuarios_au AFTER UPDATE ON usuarios FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','usuarios',NEW.id_usuario,
    JSON_OBJECT('id_rol',OLD.id_rol,'nombres',OLD.nombres,'apellidos',OLD.apellidos,'telefono',OLD.telefono,'correo',OLD.correo,'estado',OLD.estado),
    JSON_OBJECT('id_rol',NEW.id_rol,'nombres',NEW.nombres,'apellidos',NEW.apellidos,'telefono',NEW.telefono,'correo',NEW.correo,'estado',NEW.estado),@app_ip);
END$$
CREATE TRIGGER trg_empresas_ai AFTER INSERT ON empresas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','empresas',NEW.id_empresa,
    JSON_OBJECT('nombre_legal',NEW.nombre_legal,'nombre_comercial',NEW.nombre_comercial,'identificacion_fiscal',NEW.identificacion_fiscal,'sector',NEW.sector,'descripcion',NEW.descripcion,'correo_contacto',NEW.correo_contacto,'telefono',NEW.telefono,'sitio_web',NEW.sitio_web,'direccion',NEW.direccion,'observaciones_coordinacion',NEW.observaciones_coordinacion),@app_ip);
END$$
CREATE TRIGGER trg_empresas_au AFTER UPDATE ON empresas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','empresas',NEW.id_empresa,
    JSON_OBJECT('nombre_legal',OLD.nombre_legal,'nombre_comercial',OLD.nombre_comercial,'identificacion_fiscal',OLD.identificacion_fiscal,'sector',OLD.sector,'descripcion',OLD.descripcion,'correo_contacto',OLD.correo_contacto,'telefono',OLD.telefono,'sitio_web',OLD.sitio_web,'direccion',OLD.direccion,'observaciones_coordinacion',OLD.observaciones_coordinacion,'revisada_en',OLD.revisada_en,'id_administrador_revision',OLD.id_administrador_revision),
    JSON_OBJECT('nombre_legal',NEW.nombre_legal,'nombre_comercial',NEW.nombre_comercial,'identificacion_fiscal',NEW.identificacion_fiscal,'sector',NEW.sector,'descripcion',NEW.descripcion,'correo_contacto',NEW.correo_contacto,'telefono',NEW.telefono,'sitio_web',NEW.sitio_web,'direccion',NEW.direccion,'observaciones_coordinacion',NEW.observaciones_coordinacion,'revisada_en',NEW.revisada_en,'id_administrador_revision',NEW.id_administrador_revision),@app_ip);
END$$
CREATE TRIGGER trg_estudiantes_ai AFTER INSERT ON estudiantes FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','estudiantes',NEW.id_estudiante,
    JSON_OBJECT('carnet',NEW.carnet,'carrera',NEW.carrera,'universidad',NEW.universidad),@app_ip);
END$$
CREATE TRIGGER trg_estudiantes_au AFTER UPDATE ON estudiantes FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','estudiantes',NEW.id_estudiante,
    JSON_OBJECT('carnet',OLD.carnet,'carrera',OLD.carrera,'universidad',OLD.universidad,'cv_nombre',OLD.cv_nombre),
    JSON_OBJECT('carnet',NEW.carnet,'carrera',NEW.carrera,'universidad',NEW.universidad,'cv_nombre',NEW.cv_nombre),@app_ip);
END$$
CREATE TRIGGER trg_admins_ai AFTER INSERT ON administradores FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','administradores',NEW.id_administrador,JSON_OBJECT('id_usuario',NEW.id_usuario,'cargo',NEW.cargo),@app_ip);
END$$
CREATE TRIGGER trg_admins_au AFTER UPDATE ON administradores FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','administradores',NEW.id_administrador,JSON_OBJECT('cargo',OLD.cargo),JSON_OBJECT('cargo',NEW.cargo),@app_ip);
END$$
CREATE TRIGGER trg_keywords_ai AFTER INSERT ON palabras_clave FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','palabras_clave',NEW.id_palabra_clave,JSON_OBJECT('id_oportunidad',NEW.id_oportunidad,'nombre',NEW.nombre,'nombre_busqueda',NEW.nombre_busqueda,'categoria',NEW.categoria,'estado',NEW.estado),@app_ip);
END$$
CREATE TRIGGER trg_keywords_au AFTER UPDATE ON palabras_clave FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','palabras_clave',NEW.id_palabra_clave,JSON_OBJECT('id_oportunidad',OLD.id_oportunidad,'nombre',OLD.nombre,'nombre_busqueda',OLD.nombre_busqueda,'categoria',OLD.categoria,'estado',OLD.estado),JSON_OBJECT('id_oportunidad',NEW.id_oportunidad,'nombre',NEW.nombre,'nombre_busqueda',NEW.nombre_busqueda,'categoria',NEW.categoria,'estado',NEW.estado),@app_ip);
END$$
CREATE TRIGGER trg_keywords_ad AFTER DELETE ON palabras_clave FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'DELETE','palabras_clave',OLD.id_palabra_clave,JSON_OBJECT('id_oportunidad',OLD.id_oportunidad,'nombre',OLD.nombre),@app_ip);
END$$
CREATE TRIGGER trg_notifications_ai AFTER INSERT ON cola_notificaciones FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','cola_notificaciones',NEW.id_notificacion,JSON_OBJECT('tipo',NEW.tipo,'destinatario',NEW.destinatario,'estado',NEW.estado),@app_ip);
END$$
CREATE TRIGGER trg_notifications_au AFTER UPDATE ON cola_notificaciones FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','cola_notificaciones',NEW.id_notificacion,JSON_OBJECT('estado',OLD.estado,'intentos',OLD.intentos),JSON_OBJECT('estado',NEW.estado,'intentos',NEW.intentos),@app_ip);
END$$
CREATE TRIGGER trg_sessions_ai AFTER INSERT ON sesiones_usuario FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','sesiones_usuario',NEW.id_sesion,JSON_OBJECT('id_usuario',NEW.id_usuario,'ip_origen',NEW.ip_origen,'expira_en',NEW.expira_en),@app_ip);
END$$
CREATE TRIGGER trg_sessions_au AFTER UPDATE ON sesiones_usuario FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','sesiones_usuario',NEW.id_sesion,JSON_OBJECT('revocada_en',OLD.revocada_en,'expira_en',OLD.expira_en),JSON_OBJECT('revocada_en',NEW.revocada_en,'expira_en',NEW.expira_en),@app_ip);
END$$
-- Reference and access-control tables are audited too. The bitacora table itself is
-- deliberately excluded to avoid recursive trigger writes; credential/token fields are never included.
CREATE TRIGGER trg_roles_ai AFTER INSERT ON roles FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','roles',NEW.id_rol,JSON_OBJECT('nombre',NEW.nombre,'descripcion',NEW.descripcion),@app_ip);
END$$
CREATE TRIGGER trg_roles_au AFTER UPDATE ON roles FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','roles',NEW.id_rol,JSON_OBJECT('nombre',OLD.nombre,'descripcion',OLD.descripcion),JSON_OBJECT('nombre',NEW.nombre,'descripcion',NEW.descripcion),@app_ip);
END$$
CREATE TRIGGER trg_permissions_ai AFTER INSERT ON permisos FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','permisos',NEW.id_permiso,JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre),@app_ip);
END$$
CREATE TRIGGER trg_permissions_au AFTER UPDATE ON permisos FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','permisos',NEW.id_permiso,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre),@app_ip);
END$$
CREATE TRIGGER trg_role_permissions_ai AFTER INSERT ON roles_permisos FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','roles_permisos',NEW.id_rol,JSON_OBJECT('id_rol',NEW.id_rol,'id_permiso',NEW.id_permiso),@app_ip);
END$$
CREATE TRIGGER trg_role_permissions_ad AFTER DELETE ON roles_permisos FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'DELETE','roles_permisos',OLD.id_rol,JSON_OBJECT('id_rol',OLD.id_rol,'id_permiso',OLD.id_permiso),@app_ip);
END$$
CREATE TRIGGER trg_states_ai AFTER INSERT ON estados_postulacion FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'INSERT','estados_postulacion',NEW.id_estado,JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre),@app_ip);
END$$
CREATE TRIGGER trg_states_au AFTER UPDATE ON estados_postulacion FOR EACH ROW
BEGIN
  INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen)
  VALUES(NULLIF(@app_user_id,0),'UPDATE','estados_postulacion',NEW.id_estado,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre),@app_ip);
END$$

-- Postulación atómica: valida estudiante/oportunidad, impide duplicados por UNIQUE
-- y pone el aviso a empresa en la cola dentro de la misma transacción.
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
  -- Lock the opportunity row so a concurrent close cannot race a new application.
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
    AND o.fecha_limite_postulacion >= CURRENT_DATE AND u.estado='activo';
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

-- Baja lógica: cambiar usuarios.estado, oportunidades.estado o postulaciones.id_estado a 'borrado'.
-- No borrar físicamente datos del sistema para conservar la bitácora y sus relaciones.
-- id_administrador en oportunidades registra quién publicó/gestiona el registro para seguimiento.
-- fecha_evento en bitacora conserva cuándo ocurrió cada acción y cambio de estado.
-- consultas preparadas y permisos mínimos de MySQL siguen siendo responsabilidad de PHP/configuración.
